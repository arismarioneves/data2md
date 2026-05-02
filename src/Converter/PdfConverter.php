<?php

declare(strict_types=1);

namespace Data2Md\Converter;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class PdfConverter implements ConverterInterface
{
    private const GC_EVERY_N_PAGES = 20;

    private MarkdownSanitizer $sanitizer;

    public function __construct(?MarkdownSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new MarkdownSanitizer();
    }

    public function convert(string $filePath): string
    {
        $bin = $this->findPdfToText();

        if ($bin !== null) {
            try {
                $result = $this->convertWithPdfToText($filePath, $bin);
                // Empty means image-only PDF; fall through to smalot for metadata attempt
                if (trim($result) !== '') {
                    return $this->sanitizer->clean($result);
                }
            } catch (\Throwable) {
                // Binary present but failed (corrupted PDF, etc.) — try smalot
            }
        }

        return $this->sanitizer->clean($this->convertWithSmalot($filePath));
    }

    // ── pdftotext / Poppler (primary) ─────────────────────────────────────────
    // Runs as a native subprocess: constant memory, correct Unicode, handles
    // 500 MB+ PDFs without issue. Requires poppler-utils to be installed.

    private function convertWithPdfToText(string $filePath, string $bin): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'd2md_');

        try {
            $pipes = [];
            $proc  = proc_open(
                [$bin, '-layout', '-enc', 'UTF-8', $filePath, $tmpFile],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );

            if (!is_resource($proc)) {
                throw new \RuntimeException('proc_open failed to start pdftotext.');
            }

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);

            if ($exitCode !== 0 && (!file_exists($tmpFile) || filesize($tmpFile) === 0)) {
                throw new \RuntimeException("pdftotext exited with code $exitCode.");
            }

            $text = (string) file_get_contents($tmpFile);
            return $this->plaintextToMarkdown($text);

        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    private function plaintextToMarkdown(string $text): string
    {
        // pdftotext separates pages with a form-feed character (\x0c)
        $pages    = explode("\x0c", $text);
        $markdown = '';
        $pageNum  = 0;

        foreach ($pages as $pageText) {
            $pageText = trim($pageText);
            if ($pageText === '') {
                continue;
            }
            $pageNum++;
            $markdown .= '## Page ' . $pageNum . "\n\n";
            $markdown .= $this->normaliseText($pageText);
            $markdown .= "\n\n---\n\n";
        }

        return rtrim($markdown, "\n-");
    }

    private function findPdfToText(): ?string
    {
        if (!function_exists('proc_open')) {
            return null;
        }

        // Build candidate list: configured bin + common fallback paths on Windows
        $candidates = [defined('PDFTOTEXT_BIN') ? \PDFTOTEXT_BIN : 'pdftotext'];

        if (PHP_OS_FAMILY === 'Windows' && $candidates[0] === 'pdftotext') {
            array_push(
                $candidates,
                'C:\\Program Files\\Poppler\\bin\\pdftotext.exe',
                'C:\\poppler\\bin\\pdftotext.exe',
                'C:\\tools\\poppler\\bin\\pdftotext.exe'
            );
        }

        foreach ($candidates as $bin) {
            if ($bin !== '' && $this->probeBinary($bin)) {
                return $bin;
            }
        }

        return null;
    }

    private function probeBinary(string $bin): bool
    {
        $pipes = [];
        $proc  = @proc_open(
            [$bin, '-v'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($proc)) {
            return false;
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return true;
    }

    // ── smalot/pdfparser (fallback) ───────────────────────────────────────────
    // Pure-PHP parser. Works for most PDFs but exhausts memory on large files
    // with many/complex font encoding tables. Use pdftotext where possible.

    private function convertWithSmalot(string $filePath): string
    {
        $config = new Config();
        $config->setRetainImageContent(false);
        $parser = new Parser([], $config);

        try {
            $pdf = $parser->parseFile($filePath);
        } catch (\Exception $e) {
            throw new \RuntimeException('PDF parsing failed: ' . $e->getMessage(), 0, $e);
        }

        $details = $pdf->getDetails();
        $pages   = $pdf->getPages();
        unset($pdf);

        $markdown = $this->buildMetaHeader($details);

        foreach ($pages as $i => $page) {
            $text = $this->safeGetText($page);
            unset($pages[$i]);

            if ($text === '') {
                continue;
            }

            $markdown .= '## Page ' . ($i + 1) . "\n\n";
            $markdown .= $this->normaliseText($text);
            $markdown .= "\n\n---\n\n";

            if ($i > 0 && $i % self::GC_EVERY_N_PAGES === 0) {
                gc_collect_cycles();
            }
        }

        return rtrim($markdown, "\n-");
    }

    private function safeGetText(object $page): string
    {
        // Suppress PHP warnings from library internals: PCRE "regex too large"
        // on complex Unicode CMap tables, "undefined array key" on malformed streams.
        set_error_handler(static function (): bool { return true; });
        try {
            return trim($page->getText());
        } catch (\Throwable) {
            return '';
        } finally {
            restore_error_handler();
        }
    }

    private function buildMetaHeader(array $details): string
    {
        $title   = isset($details['Title'])   ? trim((string) $details['Title'])   : '';
        $author  = isset($details['Author'])  ? trim((string) $details['Author'])  : '';
        $subject = isset($details['Subject']) ? trim((string) $details['Subject']) : '';
        $pages   = isset($details['Pages'])   ? (int) $details['Pages']            : 0;

        $header = '';
        if ($title !== '') {
            $header .= '# ' . $this->cleanText($title) . "\n\n";
        }

        $meta = [];
        if ($author  !== '') $meta[] = '**Author:** '  . $this->cleanText($author);
        if ($subject !== '') $meta[] = '**Subject:** ' . $this->cleanText($subject);
        if ($pages   > 0)    $meta[] = '**Pages:** '   . $pages;

        if ($meta !== []) {
            $header .= implode("  \n", $meta) . "\n\n---\n\n";
        }

        return $header;
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    private function normaliseText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Trim each line individually — pdftotext -layout pads lines with
        // spaces to simulate column alignment, leaving large left margins.
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text  = implode("\n", $lines);

        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function cleanText(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $text);
    }

    public function getSupportedMimeTypes(): array
    {
        return ['application/pdf'];
    }

    public function getSupportedExtensions(): array
    {
        return ['pdf'];
    }

    public function getFormat(): string
    {
        return 'pdf';
    }
}
