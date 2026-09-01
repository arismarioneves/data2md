<img src="public/assets/img/logo.png" alt="Data2MD" width="300">

# Data2MD

Convert documents to clean, structured Markdown via a web interface or HTTP API.

## Features

- Web interface with drag-and-drop upload and async progress tracking
- REST API endpoint for programmatic access
- Hash-based anonymous file storage (original filenames never exposed in URLs)
- Input validation: extension + MIME type verification via `finfo`
- IP-based rate limiting with a sliding window (configurable)
- URL-friendly routes — no `.php` extensions visible
- Preview limited in the browser; full file always available to download

**Supported formats:** PDF, via [pdftotext](https://poppler.freedesktop.org/) (Poppler) — a native subprocess, constant memory regardless of file size, handles 500 MB+ files.

---

## Requirements

- Docker and Docker Compose

Everything else — PHP, Poppler, the web server — is baked into the image; there is nothing to install on the host.

---

## Quick start

```bash
git clone <repo-url> data2md
cd data2md
docker compose up -d app
```

`docker-compose.yml` already sets a working `APP_URL` for local use; the app listens on `http://localhost:8080`. Confirm it's up:

```bash
curl -sS http://localhost:8080/health
```

For a real deployment (GHCR image, EasyPanel, rollback, operator commands), see [`docs/DEPLOY.md`](docs/DEPLOY.md).

---

## Configuration

There is no `config.php` — configuration is environment variables only, validated at container boot; a missing or invalid one aborts startup and names the problem in the log. `APP_URL` has no default and must always be set. The rest split in two: keys the `Dockerfile` declares as production `ENV`, and optional keys that default in code and only need setting to change behaviour. `bin/data2md config:check` reports the count of each.

See [`.env.example`](.env.example) for every variable with its default, and [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for the full reference — variable table, sizing arithmetic, and architecture.

---

## Usage

### Web interface

Open the root URL in a browser, select or drop a PDF file, and click **Convert to Markdown**. A loading overlay tracks progress in real time. The result is displayed inline with copy and download options.

> Large files (50 MB+) can take several seconds. The page never freezes — conversion runs async and the progress bar reflects both upload and server-side processing.

### API

**Endpoint:** `POST /api`  
**Content-Type:** `multipart/form-data`  
**Authentication:** `Authorization: Bearer d2md_…` — the API is part of the subscription

Tokens are created in the dashboard, or with `bin/data2md token:create <email>`. Without one the
endpoint answers `401`; with one on an account that has no subscription, `403` and
`"code": "plan_required"`. The browser uses `POST /convert`, which reads the session cookie
instead.

| Field | Required | Description |
|---|---|---|
| `file` | Yes | The file to convert |
| `format` | No | File format hint (default: `pdf`) |

**Success response (201):**

```json
{
  "success": true,
  "hash": "a3f9c12b84e7d01",
  "original_name": "report.pdf",
  "format": "pdf",
  "lines": 148,
  "characters": 9342,
  "pages": 3,
  "download_url": "/download?hash=a3f9c12b84e7d01",
  "preview": "## Page 1\n\n...",
  "preview_truncated": false
}
```

`preview` holds the first bytes of the converted Markdown; fetch `download_url` for the full file.

**cURL example:**

```bash
curl -X POST https://yourdomain.com/api \
  -H "Authorization: Bearer $D2MD_TOKEN" \
  -F "file=@document.pdf" \
  -F "format=pdf"
```

`GET /api/me` reports the plan behind a token, its limits and what is left of the current
window. It works on any plan and is not rate limited, so a `403` is always explainable.

**Python example:**

```python
import os, requests

headers = {'Authorization': 'Bearer ' + os.environ['D2MD_TOKEN']}

with open('document.pdf', 'rb') as f:
    r = requests.post('https://yourdomain.com/api', headers=headers,
                      files={'file': f}, data={'format': 'pdf'})

data = r.json()
print(data['preview'])          # first bytes of the converted Markdown
print(data['download_url'])     # fetch this for the full file
```

Visit `/api` in a browser for the full interactive API reference.

### Download a previously converted file

```
GET /download?hash={hash}
```

Returns the `.md` file as an attachment. Converted Markdown has no automatic expiry. It is removed by `bin/data2md delete <hash>`, by `bin/data2md prune --older-than=N`, or by a signed-in user deleting their own conversion from the dashboard.

---

## Project structure

```
data2md/
├── composer.json
├── Dockerfile
├── docker-compose.yml
├── docker/
│   ├── entrypoint.sh            # Validates config, prepares the data dir, starts Apache
│   ├── apache/                  # Vhost: security headers, mod_rewrite, upload limits
│   └── php/
├── public/                      # Web root
│   ├── index.php                # Landing page
│   ├── app.php                  # Converter web interface
│   ├── api.php                  # API endpoint (GET: docs, POST: convert)
│   ├── download.php             # Serve converted .md files
│   ├── health.php               # GET /health
│   ├── terms.php / privacy.php  # Legal pages
│   └── assets/
├── src/
│   ├── Config/                  # Env schema, validation, cross-key rules
│   ├── Converter/               # PdfConverter (pdftotext), ConverterInterface
│   ├── Storage/                 # FileStorage, MappingStorage (SQLite), Gc
│   ├── Security/                # Validator, RateLimiter
│   ├── Http/                    # ClientIp (trusted-proxy aware), Cors
│   ├── Log/                     # Two channels: operational and audit
│   └── Response/                # ApiResponse
├── bin/data2md                  # CLI: health, config:check, logs, gc, delete, prune
└── tests/
```

All application data — converted Markdown, the mapping database, logs, rate-limit counters — lives under `APP_DATA_DIR` (`/var/lib/data2md` by default), entirely outside `public/`. There is no `config.php` and no `.htaccess`; configuration is environment variables validated at boot, and the web server rules are baked into the image.

---

## Adding a new format

1. Create `src/Converter/YourConverter.php` implementing `ConverterInterface`
2. Register the allowed extension and MIME type in `src/Security/Validator.php` under `ALLOWED`
3. Add a `case` to the `match ($format)` expression in `public/api.php`
4. Add the format pill button in `public/app.php`

---

## Security

| Measure | Detail |
|---|---|
| Hash-only filenames | Uploaded files are renamed to a 15-character hex hash; originals are never accessible by URL |
| Extension + MIME validation | Both the file extension and `finfo` MIME type are checked before accepting any upload |
| `is_uploaded_file()` check | Ensures files come from a real HTTP upload, not path injection |
| Rate limiting | File-based sliding window per IP; IPs are stored as SHA-256 hashes; only proxies in `TRUSTED_PROXIES` may set the forwarded-for address |
| Security headers | `X-Frame-Options`, `X-Content-Type-Options`, `CSP`, `Referrer-Policy` on every response; `Server` and `X-Powered-By` are suppressed |
| Data outside the web root | Markdown, the mapping database, and logs live under `APP_DATA_DIR`, entirely outside `public/` — there is nothing under the document root to protect with deny rules |
| Permissions | Stored files are `chmod 0640`; directories are `0750` |
| Shell injection prevention | `pdftotext` is invoked via `proc_open` with an array command — no shell interpolation possible |
| Original file discarded | The uploaded file is deleted once conversion finishes, success or failure; only the converted Markdown persists |

---

## Testing

```bash
docker compose run --rm test vendor/bin/phpunit
```

This runs the full unit and integration suite inside the same base image used for production. A separate end-to-end smoke test exercises a running container and is skipped unless `D2MD_BASE_URL` is set:

```bash
docker compose up -d app
docker compose run --rm -e D2MD_BASE_URL=http://app test vendor/bin/phpunit --filter SmokeTest
```
