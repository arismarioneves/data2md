<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use Data2Md\Converter\PdfConverter;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;
use Data2Md\Security\RateLimiter;
use Data2Md\Security\Validator;

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:");

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$mappingStorage = new MappingStorage(BASE_PATH . '/mappings');
$fileStorage    = new FileStorage(BASE_PATH . '/data', BASE_PATH . '/md', $mappingStorage);
$validator      = new Validator();
$rateLimiter    = new RateLimiter(BASE_PATH . '/storage/rate_limits', 10, 60);

$errors       = [];
$result       = null;
$uploadedHash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $clientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);

        if (!$rateLimiter->isAllowed($clientIp)) {
            $errors[] = 'Too many requests. Please wait a moment and try again.';
        } else {
            $format = strtolower(trim($_POST['format'] ?? 'pdf'));
            $file   = $_FILES['file'] ?? null;

            if ($file === null) {
                $errors[] = 'No file provided.';
            } else {
                $errors = $validator->validateUpload($file, $format);

                if ($errors === []) {
                    try {
                        $originalName = $validator->sanitizeOriginalName($file['name']);
                        $hash         = $fileStorage->storeUploadedFile($file, $originalName, $format);

                        $converter = match ($format) {
                            'pdf'   => new PdfConverter(),
                            default => throw new \InvalidArgumentException("Unsupported format: $format"),
                        };

                        $filePath = $fileStorage->getFilePath($hash, $format);
                        $markdown = $converter->convert($filePath);
                        $fileStorage->storeMarkdown($hash, $markdown);

                        $uploadedHash = $hash;
                        $result       = $markdown;
                    } catch (\Exception $e) {
                        $errors[] = 'Conversion failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>data2md — Convert Files to Markdown</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <a href="/" class="logo">
                    <span class="logo-mark">d2md</span>
                </a>
                <nav>
                    <a href="/api.php" class="nav-link">API</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">

            <section class="hero">
                <h1 class="hero-title">Convert files to <span class="accent">Markdown</span></h1>
                <p class="hero-sub">Upload a document and receive clean, structured Markdown output instantly.</p>
            </section>

            <?php if ($errors !== []): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($result === null): ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data" id="upload-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                    <input type="hidden" name="format" id="format-input" value="pdf">

                    <div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Upload file">
                        <div id="dz-idle">
                            <div class="dz-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                            </div>
                            <p class="dz-title">Drop your file here</p>
                            <p class="dz-sub">or <label for="file-input" class="browse-link">browse to upload</label></p>
                            <p class="dz-hint">PDF &bull; Max 20 MB</p>
                        </div>
                        <div id="dz-selected" hidden>
                            <div class="dz-icon dz-icon--ok" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 12 11 14 15 10"/></svg>
                            </div>
                            <p class="dz-title" id="dz-filename">document.pdf</p>
                            <p class="dz-sub">Ready to convert &mdash; <button type="button" class="browse-link" id="dz-change">change</button></p>
                        </div>
                        <input type="file" name="file" id="file-input" accept=".pdf,application/pdf" class="sr-only">
                    </div>

                    <div class="format-row">
                        <span class="format-label">Format</span>
                        <div class="format-pills" role="group" aria-label="File format">
                            <button type="button" class="pill pill--active" data-format="pdf">PDF</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block" id="convert-btn" disabled>
                        Convert to Markdown
                    </button>
                </form>
            </div>

            <?php else: ?>

            <div class="card card--result">
                <div class="result-header">
                    <div class="result-meta">
                        <span class="badge badge--ok">Converted</span>
                        <span class="result-hash">
                            <span class="result-hash-label">ID</span>
                            <code><?= htmlspecialchars($uploadedHash, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></code>
                        </span>
                    </div>
                    <div class="result-actions">
                        <button class="btn btn--ghost" id="copy-btn" type="button">Copy Markdown</button>
                        <a href="/download.php?hash=<?= urlencode($uploadedHash) ?>" class="btn btn--primary">
                            Download .md
                        </a>
                    </div>
                </div>

                <div class="preview">
                    <div class="preview-bar">
                        <span class="preview-bar-label">Markdown output</span>
                        <span class="preview-bar-info"><?= number_format(mb_strlen($result)) ?> chars</span>
                    </div>
                    <pre class="preview-body" id="md-output"><?= htmlspecialchars($result, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></pre>
                </div>

                <div class="result-footer">
                    <a href="/" class="btn btn--ghost">Convert Another File</a>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>data2md &mdash; Files are stored with anonymous identifiers. Your data is never publicly linked to your identity.</p>
        </div>
    </footer>

    <script src="/assets/js/app.js"></script>
</body>
</html>
