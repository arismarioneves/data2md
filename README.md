# data2md

Convert documents to clean, structured Markdown via a web interface or HTTP API.

## Features

- Web interface with drag-and-drop upload
- REST API endpoint for programmatic access
- Hash-based anonymous file storage (original filenames never exposed in URLs)
- Input validation: extension + MIME type verification
- Rate limiting per IP (configurable)
- CSRF protection on the web form
- URL-friendly routes — no `.php` extensions visible

**Supported formats:** PDF (via [smalot/pdfparser](https://github.com/smalot/pdfparser))
Architecture is open to additional formats — see [Adding a new format](#adding-a-new-format).

---

## Requirements

- PHP 8.1+
- Composer
- Apache with `mod_rewrite` and `mod_headers` enabled (or Nginx equivalent)

---

## Installation

```bash
git clone <repo-url> data2md
cd data2md
composer install
```

Set the web server document root to the `public/` directory.

### Apache virtual host example

```apache
<VirtualHost *:80>
    ServerName data2md.local
    DocumentRoot /var/www/data2md/public

    <Directory /var/www/data2md/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx equivalent

```nginx
server {
    listen 80;
    server_name data2md.local;
    root /var/www/data2md/public;
    index index.php;

    location / {
        try_files $uri $uri/ $uri.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Block access to sensitive directories
    location ~* ^/(data|md|mappings|storage|src|vendor)/ {
        deny all;
    }
}
```

### Directory permissions

The following directories must be writable by the web server process:

```bash
chmod 750 data/ data/pdf/ md/ mappings/ storage/ storage/rate_limits/
```

---

## Configuration

All tuneable values live in `config.php` at the project root:

| Constant | Default | Description |
|---|---|---|
| `MAX_FILE_SIZE` | `20 * 1024 * 1024` | Maximum upload size in bytes |
| `MAX_FILE_SIZE_LABEL` | `'20 MB'` | Human-readable label shown in the UI and API docs |
| `APP_MAX_EXECUTION_TIME` | `300` | PHP `max_execution_time` in seconds (for large files) |
| `APP_MEMORY_LIMIT` | `'512M'` | PHP `memory_limit` applied at runtime |
| `RATE_LIMIT_WEB` | `10` | Max requests per window on the web interface |
| `RATE_LIMIT_API` | `20` | Max requests per window on the API endpoint |
| `RATE_LIMIT_WINDOW` | `60` | Sliding window duration in seconds |

---

## Usage

### Web interface

Open the root URL in a browser, select or drop a file, choose the format, and click **Convert to Markdown**. The result is displayed inline with copy and download options.

For large files a loading overlay appears immediately on submit. PHP runtime limits are controlled via `APP_MAX_EXECUTION_TIME` and `APP_MEMORY_LIMIT` in `config.php`.

### API

**Endpoint:** `POST /api`
**Content-Type:** `multipart/form-data`

| Field | Required | Description |
|---|---|---|
| `file` | Yes | The file to convert |
| `format` | No | File format (default: `pdf`) |

**Success response (201):**

```json
{
  "success": true,
  "hash": "a3f9c12b84e7d01",
  "original_name": "report.pdf",
  "format": "pdf",
  "lines": 148,
  "characters": 9342,
  "download_url": "/download?hash=a3f9c12b84e7d01",
  "markdown": "# Report Title\n\n..."
}
```

**cURL example:**

```bash
curl -X POST https://yourdomain.com/api \
  -F "file=@document.pdf" \
  -F "format=pdf"
```

**Python example:**

```python
import requests

with open('document.pdf', 'rb') as f:
    r = requests.post('https://yourdomain.com/api', files={'file': f}, data={'format': 'pdf'})

print(r.json()['markdown'])
```

Visit `/api` in a browser for the full interactive API reference.

### Download a previously converted file

```
GET /download?hash={hash}
```

Returns the `.md` file as an attachment.

---

## Project structure

```
data2md/
├── config.php                  # Global constants (file size, rate limits, runtime)
├── composer.json
├── public/                     # Web root
│   ├── index.php               # Web interface
│   ├── api.php                 # API endpoint (GET: docs, POST: convert)
│   ├── download.php            # Serve converted .md files
│   └── assets/
│       ├── css/style.css
│       └── js/app.js
├── src/
│   ├── Converter/
│   │   ├── ConverterInterface.php
│   │   └── PdfConverter.php
│   ├── Storage/
│   │   ├── FileStorage.php     # File I/O, hash generation
│   │   └── MappingStorage.php  # hash → original name mapping (JSON)
│   ├── Security/
│   │   ├── Validator.php       # Upload validation (extension + MIME)
│   │   └── RateLimiter.php     # IP-based sliding window rate limiter
│   └── Response/
│       └── ApiResponse.php     # JSON response helpers
├── data/
│   └── pdf/                    # Uploaded files stored as {hash} (no extension)
├── md/                         # Converted Markdown stored as {hash}.md
├── mappings/
│   └── mappings.json           # Internal map: hash → original filename + format
└── storage/
    └── rate_limits/            # Per-IP request counters (hashed filenames)
```

---

## Adding a new format

1. Create `src/Converter/YourConverter.php` implementing `ConverterInterface`
2. Register the allowed extension and MIME type in `src/Security/Validator.php` under `ALLOWED`
3. Add a `case` to the `match ($format)` expression in `public/index.php` and `public/api.php`
4. Add the format pill button in `public/index.php`

---

## Security

| Measure | Detail |
|---|---|
| Hash-only filenames | Uploaded files are renamed to a 15-character hex hash; originals are never accessible by URL |
| Extension + MIME validation | Both the file extension and `finfo` MIME type are checked before accepting any upload |
| `is_uploaded_file()` check | Ensures files come from a real HTTP upload, not path injection |
| CSRF token | Web form is protected with a session-bound token verified with `hash_equals()` |
| Rate limiting | File-based sliding window per IP; IPs are stored as SHA-256 hashes |
| Security headers | `X-Frame-Options`, `X-Content-Type-Options`, `CSP`, `Referrer-Policy` on every response |
| Directory protection | `data/`, `md/`, `mappings/`, `storage/` are outside the web root and covered by `.htaccess` deny rules |
| Permissions | Stored files are `chmod 0640`; directories are `0750` |
