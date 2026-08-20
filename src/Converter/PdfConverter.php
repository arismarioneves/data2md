<?php

declare(strict_types=1);

namespace Data2Md\Converter;

use Data2Md\Runtime\Process;

/**
 * Extracts text from a PDF via pdftotext and streams the result to markdown.
 *
 * A PDF with no text layer (a scan or an image-only export) raises
 * NoTextLayerException instead of producing an empty or metadata-only file.
 */
final class PdfConverter implements ConverterInterface
{
    private const PAGE_BREAK = "\x0c";

    public function __construct(
        private readonly string $pdftotextBin,
        private readonly Process $process,
        private readonly MarkdownSanitizer $sanitizer,
        private readonly string $tmpDir,
    ) {
    }

    public function convertToFile(string $filePath, string $outputPath): ConversionResult
    {
        $textPath = $this->tmpDir . '/' . bin2hex(random_bytes(8)) . '.txt';

        try {
            $this->extract($filePath, $textPath);

            return $this->render($textPath, $outputPath);
        } finally {
            if (is_file($textPath)) {
                @unlink($textPath);
            }
        }
    }

    private function extract(string $filePath, string $textPath): void
    {
        $result = $this->process->run([
            $this->pdftotextBin,
            '-layout',
            '-enc', 'UTF-8',
            $filePath,
            $textPath,
        ]);

        if ($result->timedOut) {
            throw new ConversionTimeoutException('Conversion exceeded the time limit.');
        }

        if ($result->exitCode !== 0) {
            $detail = trim($result->stderr);

            throw new ConversionFailedException(
                'Could not read the PDF' . ($detail !== '' ? ': ' . $detail : '.')
            );
        }

        if (!is_file($textPath)) {
            throw new ConversionFailedException('Extraction produced no output.');
        }
    }

    private function render(string $textPath, string $outputPath): ConversionResult
    {
        $in = fopen($textPath, 'rb');
        if ($in === false) {
            throw new ConversionFailedException('Could not read the extracted text.');
        }

        $out = fopen($outputPath, 'wb');
        if ($out === false) {
            fclose($in);
            throw new ConversionFailedException('Could not open the markdown output.');
        }

        $writer   = new MarkdownStreamWriter($out, $this->sanitizer);
        $pages    = 0;
        $pageOpen = false;

        try {
            while (($rawLine = fgets($in)) !== false) {
                $line = rtrim($rawLine, "\r\n");

                // pdftotext separates pages with a form feed, which may share
                // a line with content on either side of it.
                $segments = explode(self::PAGE_BREAK, $line);

                foreach ($segments as $index => $segment) {
                    if ($index > 0) {
                        $pageOpen = false; // the form feed closed the previous page
                    }

                    if (trim($segment) === '') {
                        if ($pageOpen) {
                            $writer->blankLine();
                        }
                        continue;
                    }

                    if (!$pageOpen) {
                        $pages++;
                        $writer->writeRaw('## Page ' . $pages);
                        $writer->blankLine();
                        $pageOpen = true;
                    }

                    $writer->writeLine($segment);
                }
            }

            $stats = $writer->finish();
        } finally {
            fclose($in);
            fclose($out);
        }

        if ($pages === 0) {
            @unlink($outputPath);

            throw new NoTextLayerException(
                'This PDF has no text layer — it looks like a scan or an image-only export, and needs OCR.'
            );
        }

        return new ConversionResult($stats->lines, $stats->characters, $pages);
    }

    /** @return list<string> */
    public function getSupportedMimeTypes(): array
    {
        return ['application/pdf'];
    }

    /** @return list<string> */
    public function getSupportedExtensions(): array
    {
        return ['pdf'];
    }

    public function getFormat(): string
    {
        return 'pdf';
    }
}
