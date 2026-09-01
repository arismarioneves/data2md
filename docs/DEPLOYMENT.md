# Deployment reference

This is the reference companion to [`DEPLOY.md`](DEPLOY.md). `DEPLOY.md` is
the walkthrough — push, Actions, GHCR, EasyPanel, rollback, operator
commands, data lifecycle, common problems. This document is the material
that walkthrough doesn't repeat: the full configuration schema, the sizing
arithmetic behind the resource numbers, and how the pieces fit together.

If you're deploying for the first time, read `DEPLOY.md`. Come back here when
you need the variable you haven't seen yet, or the reasoning behind a number.

---

## 1. Architecture

data2md runs as a single container: `php:8.3-apache`, one process family, no
separate front and API tier. `docker/entrypoint.sh` validates every
configuration variable before Apache starts — an invalid or missing value
aborts the boot with every problem listed at once, never a partial start.

Inside the container:

- **Config** (`src/Config/Config.php`) — a typed schema validated once at
  boot. There are no defaults in code; production defaults are declared as
  `ENV` in the Dockerfile, so they're visible without opening a PHP file.
- **Conversion** — `pdftotext` runs as a subprocess with `nice`/`ionice`
  applied, so it yields CPU and I/O to the VPS's other services under
  contention rather than competing with them. A semaphore
  (`src/Runtime/ConversionSemaphore.php`) caps how many conversions run at
  once; beyond that, requests queue up to `CONVERT_QUEUE_WAIT_SECONDS` and
  then get a `503` with `Retry-After`.
- **Storage** — uploaded files are discarded once conversion finishes;
  converted Markdown and the hash-to-filename mapping (SQLite) persist on the
  `/var/lib/data2md` volume.
- **Logs** — two channels, operational (`app`) and audit (`audit`), each with
  its own retention. `bin/data2md logs` reads both.
- **GC** — runs opportunistically after a fraction of requests
  (`GC_PROBABILITY`) and removes only orphans, expired counters, and rotated
  logs past retention. It never removes a markdown file that still has a
  mapping record. See section 4 of `DEPLOY.md` for the full data-lifecycle
  table and the disk-space warning.

---

## 2. Configuration variables

`Config` validates two groups, and `bin/data2md config:check` prints the count
of each.

**19 required keys** describe the deployment itself and have no code-level
default. The Dockerfile declares production `ENV` values for 18 of them;
`APP_URL` has none, on purpose, because there is no correct generic value and
deriving it from the request's `Host` header is exactly the behaviour this
project removed.

**31 optional keys** describe behaviour that is off or conventional by default —
accounts, billing, per-tier conversion limits, sessions, outbound mail, Stripe.
Each carries a default in `Config::SCHEMA`, and a key left *blank* is read as
absent rather than failing boot, because control panels routinely leave a
declared variable empty. `.env.example` lists them all, commented out, with
their defaults as the values. They are deliberately not duplicated into the
Dockerfile: two copies of a default drift.

Some rules span keys and are checked together, so `config:check` reports them
the same way as a bad value:

- `ACCOUNTS_ENABLED=true` needs a way to send the sign-in link — `SMTP_HOST`,
  or `MAIL_TRANSPORT=log` for development and CI.
- `BILLING_ENABLED=true` needs `ACCOUNTS_ENABLED=true` and all three `STRIPE_*`
  values.
- A `sk_test_` key with `APP_ENV=production`, or `sk_live_` in development, is
  rejected: the mismatch otherwise fails silently, with every webhook answering
  400 and nothing else looking wrong.
- `SESSION_IDLE_DAYS` above `SESSION_LIFETIME_DAYS` can never expire anything.
- A `TIER_*_MAX_FILE_SIZE_MB` above `MAX_FILE_SIZE_MB` promises a size the
  request never reaches PHP to honour, since Apache's `LimitRequestBody` and
  PHP's `upload_max_filesize` come from the ceiling at boot.

In practice, on a fresh EasyPanel deployment you only need to set two variables
yourself:

- **`APP_URL`** — no default exists; the container refuses to boot without it.
- **`CONTACT_EMAIL`** — has a default, but it's this project's placeholder
  address. Set it to a real one — `/terms` and `/privacy` publish whatever
  this variable holds, verbatim.

Everything else has a workable production default and only needs overriding
for tuning.

| Variable | Default | Domain | Notes |
|---|---|---|---|
| `APP_ENV` | `production` | `production` \| `development` | |
| `APP_URL` | *(none — required)* | absolute `http(s)://` URL | No generic default is correct; boot fails without it |
| `APP_NAME` | `data2md` | 1–64 chars | |
| `APP_VERSION` | stamped by CI | string | Set via `APP_VERSION` build arg, not by hand; see section 4 |
| `APP_DATA_DIR` | `/var/lib/data2md` | absolute, writable path | Mount the volume here |
| `MAX_FILE_SIZE_MB` | `200` | 1–2048 | |
| `CONVERT_TIMEOUT_SECONDS` | `900` | 30–7200 | Per-conversion ceiling |
| `CONVERT_CONCURRENCY` | `2` | 1–16 | See sizing math below |
| `CONVERT_QUEUE_WAIT_SECONDS` | `20` | 0–600 | Beyond this, `503` with `Retry-After` |
| `CONVERT_NICE` | `15` | 0–19 | `nice` value applied to the extractor subprocess |
| `PHP_MEMORY_LIMIT_MB` | `512` | 64–4096 | |
| `RATE_LIMIT_MAX` | `20` | 1–10000 | Requests per window, per IP |
| `RATE_LIMIT_WINDOW_SECONDS` | `60` | 1–86400 | |
| `TRUSTED_PROXIES` | `10.11.0.0/16` | CIDR list, empty allowed | **Confirm this on your VPS — see section 3** |
| `CORS_ALLOWED_ORIGINS` | *(empty)* | origin list, empty allowed | Empty disables CORS entirely; preflight then answers `405` |
| `LOG_RETENTION_DAYS` | `90` | 1–3650 | Operational log |
| `AUDIT_LOG_RETENTION_DAYS` | `30` | 1–3650 | Access/audit log |
| `GC_PROBABILITY` | `0.01` | 0.0–1.0 | Chance GC runs after any given request |
| `CONTACT_EMAIL` | `support@aiu4.com` | valid e-mail | Published on the Terms and Privacy pages |

### Optional keys

Everything above is required and has a value in the image. Accounts, mail and
billing add keys that each carry a default in code, so an operator sets only
what they mean to change — `bin/data2md config:check` prints how many of each
the running build has, and names every problem at once rather than the first.

Three cross-checks refuse to boot rather than degrade quietly:

- `ACCOUNTS_ENABLED=true` with `MAIL_TRANSPORT=smtp` and no `SMTP_HOST`. A
  sign-in link is the only way into an account, so mail that cannot be sent
  is an outage, not a warning.
- `BILLING_ENABLED=true` without accounts, or with any `STRIPE_*` empty. Half
  a billing configuration is worse than none.
- A test-mode Stripe key with `APP_ENV=production`, or a live key in
  development. A key in the wrong mode fails silently: every webhook answers
  400 and nothing else looks wrong.

Do not set `APP_VERSION` on the service. It is written into the image by the
CI build and is how `/health` reports which commit is running; setting it in
the panel replaces that with whatever was typed.

There is no `MD_RETENTION_DAYS` and there never was one. Converted Markdown does
not expire; a retention variable would imply otherwise. It is removed by
`bin/data2md delete <hash>`, by `bin/data2md prune --older-than=N`, or by a
signed-in user deleting their own conversion from the dashboard. Nothing
removes it on a timer, so the data volume still grows over the life of the
deployment; a disk-space alert on the VPS is not optional, it's the only
thing that will tell you before the volume fills.

Deleting an account does **not** delete its conversions. They are detached
from it and stay reachable by hash, exactly as an anonymous conversion is.
The privacy page says so, and `bin/data2md user:delete` says so before it
acts.

---

## 3. `TRUSTED_PROXIES`

The Dockerfile default, `10.11.0.0/16`, is this VPS's actual EasyPanel/Traefik
network — confirmed by `docker network inspect`, not a generic Docker
default. If you deploy to a different VPS or the network is renumbered,
confirm it again the same way (`DEPLOY.md` section 5 has the exact command)
before going live.

Getting this wrong produces no error and no log entry pointing at it: every
request arrives with an `X-Forwarded-For` from an untrusted hop, so the rate
limiter falls back to treating all traffic as a single client address. The
limiter still runs — it just silently stops discriminating between users,
and one abusive client can exhaust the shared bucket for everyone behind the
proxy. There is nothing in `/health` or the logs that flags this on its own;
the only way to catch it is to check the value against the real network.

---

## 4. Release cycle and version verification

```
commit → push/merge to main → Actions runs the suite → publishes to GHCR
       → deploy hook → EasyPanel pulls the image → /health shows the new stamp
```

The test suite is a hard gate on the publish job (`needs: test` in
`.github/workflows/image.yml`): a failing suite means no image reaches GHCR,
and therefore none reaches production.

`APP_VERSION` is stamped by CI, not set by hand:

- A push to `main` stamps `main-<short-sha>` (e.g. `main-a1b2c3d`).
- The image is published as `:main` and as an immutable `:sha-<commit>`. Pin the
  `sha` form when a deployment target must not move under you.

`GET /health` returns this stamp in its `version` field. That field is the
only reliable way to confirm which code is actually running after a deploy —
the deploy log or the EasyPanel "Deploy" click only says that *something*
shipped, not what. Check `version` after every deploy, not just when
something looks wrong.

---

## 5. Sizing arithmetic

The resource budget is 2 GB memory / 1 CPU (see `DEPLOY.md` section 6 for
where that's configured). The converting workers are only part of the
budget — every Apache worker holds a resident mod_php process whether it is
converting, queued, or idle. The real ceiling is:

```
RAM_peak = CONVERT_CONCURRENCY × (PHP_MEMORY_LIMIT_MB + ~60 MB)
         + (MaxRequestWorkers − CONVERT_CONCURRENCY) × ~35 MB
```

The `~60 MB` is the resident cost of the `pdftotext` extractor subprocess
held alongside a *converting* PHP worker — it runs as a native subprocess
with constant memory regardless of input size, so this overhead doesn't grow
with file size. The `~35 MB` is a bare resident mod_php child: every other
Apache worker, whether it's serving a static page or parked inside
`ConversionSemaphore::acquire()` waiting for a slot. With the shipped
defaults (`MaxRequestWorkers=24` in `docker/apache/mpm.conf`):

```
RAM_peak = 2 × (512 MB + 60 MB) + (24 − 2) × 35 MB
         ≈ 1.14 GB + 0.77 GB ≈ 1.9 GB   of 2 GB available
```

That is close to the ceiling with no real headroom — which is exactly why
`MaxRequestWorkers` cannot be raised to give the queue more room, and why
`CONVERT_QUEUE_WAIT_SECONDS` is kept short (see `docker/apache/mpm.conf`):
a worker stuck waiting on a full semaphore is 35 MB and one fewer worker
available for everything else, including `/health`.

Raising `CONVERT_CONCURRENCY` does not raise throughput on a 1-CPU
allocation. Extraction is CPU-bound, so two concurrent conversions share one
core instead of running in parallel; `CONVERT_CONCURRENCY=2` exists to let
one request's upload I/O overlap with another request's CPU-bound extraction,
not to run two extractions at once. If production measurement shows no
benefit, drop it to 1 — it's an environment variable, not a rebuild.

---

## 6. See also

- [`DEPLOY.md`](DEPLOY.md) — the deployment walkthrough: registry access,
  EasyPanel setup, rollback, operator commands, data lifecycle, and common
  problems.
- [`.env.example`](../.env.example) — every variable with an inline comment
  where the reasoning isn't obvious from the name.
