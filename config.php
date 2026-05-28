<?php

declare(strict_types=1);

// ── Upload limits ─────────────────────────────────────────────────────────────
define('MAX_FILE_SIZE',       200 * 1024 * 1024); // bytes — change here to affect all validation
define('MAX_FILE_SIZE_LABEL', '200 MB');

// ── PHP runtime ───────────────────────────────────────────────────────────────
// Applied at entry points so large files (e.g. 400 MB PDFs) don't time out.
define('APP_MAX_EXECUTION_TIME', 360);   // seconds
// smalot/pdfparser decompresses all object streams into memory at once.
// For large PDFs (400–500 MB) this can require 4–8× the file size in RAM.
// '-1' removes the PHP cap entirely; actual usage is bounded by the OS limit.
// If you need a hard cap, use a value like '8G' and ensure the server has enough RAM.
define('APP_MEMORY_LIMIT', '-1');
// ── Rate limiting ─────────────────────────────────────────────────────────────
define('RATE_LIMIT_WEB',    10);  // max requests per window on the web interface
define('RATE_LIMIT_API',    20);  // max requests per window on the API endpoint
define('RATE_LIMIT_WINDOW', 60);  // sliding window in seconds

// ── External tools ────────────────────────────────────────────────────────────
// pdftotext (Poppler) processes PDFs natively — handles 500 MB+ files with
// constant memory, and correctly decodes complex Unicode CMap font tables that
// crash the pure-PHP parser.
//
// Install:
//   Linux  : apt-get install poppler-utils
//   macOS  : brew install poppler
//   Windows: https://github.com/oschwartz10612/poppler-windows/releases
//            Extract and add the bin\ folder to your PATH.
//
// Set to '' to force the pure-PHP (smalot) fallback.
define('PDFTOTEXT_BIN', 'pdftotext');

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME',    'data2md');
define('APP_VERSION', '1.0.0');
