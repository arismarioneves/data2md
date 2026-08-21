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

All 19 keys are required by `Config` — there is no code-level default for any
of them. The Dockerfile declares production `ENV` defaults for 18 of the 19;
`APP_URL` has none, on purpose, because there is no correct generic value and
deriving it from the request's `Host` header is exactly the behaviour this
project removed. In practice, on a fresh EasyPanel deployment you only need to
set two variables yourself:

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
| `CONVERT_QUEUE_WAIT_SECONDS` | `120` | 0–600 | Beyond this, `503` with `Retry-After` |
| `CONVERT_NICE` | `15` | 0–19 | `nice` value applied to the extractor subprocess |
| `PHP_MEMORY_LIMIT_MB` | `512` | 64–4096 | |
| `RATE_LIMIT_MAX` | `20` | 1–10000 | Requests per window, per IP |
| `RATE_LIMIT_WINDOW_SECONDS` | `60` | 1–86400 | |
| `TRUSTED_PROXIES` | `10.11.0.0/16` | CIDR list, empty allowed | **Confirm this on your VPS — see section 3** |
| `CORS_ALLOWED_ORIGINS` | *(empty)* | origin list, empty allowed | Empty disables CORS entirely; preflight then answers `405` |
| `LOG_RETENTION_DAYS` | `90` | 1–3650 | Operational log |
| `AUDIT_LOG_RETENTION_DAYS` | `30` | 1–3650 | Access/audit log |
| `GC_PROBABILITY` | `0.01` | 0.0–1.0 | Chance GC runs after any given request |
| `CONTACT_EMAIL` | `contato@data2md.com.br` | valid e-mail | Placeholder — override for a real deployment |

There is no `MD_RETENTION_DAYS` and there never was one. Converted Markdown is
permanent; a retention variable would imply otherwise. The only way Markdown
is removed is `bin/data2md delete <hash>` (one conversion) or
`bin/data2md prune --older-than=N` (by age) — both CLI-only, with no
self-service deletion exposed to users. Because of that, the data volume
grows monotonically over the life of the deployment; a disk-space alert on
the VPS is not optional, it's the only thing that will tell you before the
volume fills.

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
- A tag `vX.Y.Z` stamps `X.Y.Z` — the `v` is stripped by `docker/metadata-action`
  when it derives the image tags, so the *image* tags are `X.Y.Z` and `X.Y`,
  never `vX.Y.Z`. The `version` field in `/health` matches the stripped form.

`GET /health` returns this stamp in its `version` field. That field is the
only reliable way to confirm which code is actually running after a deploy —
the deploy log or the EasyPanel "Deploy" click only says that *something*
shipped, not what. Check `version` after every deploy, not just when
something looks wrong.

---

## 5. Sizing arithmetic

The resource budget is 2 GB memory / 1 CPU (see `DEPLOY.md` section 6 for
where that's configured). The peak memory a conversion workload can reach is:

```
RAM_peak = CONVERT_CONCURRENCY × (PHP_MEMORY_LIMIT_MB + ~60 MB)
```

The `~60 MB` is the resident cost of the `pdftotext` extractor subprocess
held alongside the PHP worker for the duration of one conversion — it runs as
a native subprocess with constant memory regardless of input size, so this
overhead doesn't grow with file size. With the shipped defaults:

```
RAM_peak = 2 × (512 MB + 60 MB) ≈ 1.1 GB   of 2 GB available
```

That leaves headroom for Apache's other workers serving static pages and the
landing site while the conversion queue is busy.

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
