<?php

declare(strict_types=1);

namespace Data2Md\Converter;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class PdfConverter implements ConverterInterface
{
    private Parser $parser;

    public function __construct()
    {
        $config = new Config();
        $config->setRetainImageContent(false);
        $this->parser = new Parser([], $config);
    }

    public function convert(string $filePath): string
    {
        try {
            $pdf = $this->parser->parseFile($filePath);
        } catch (\Exception $e) {
            throw new \RuntimeException('PDF parsing failed: ' . $e->getMessage(), 0, $e);
        }

        $details = $pdf->getDetails();
        $pages   = $pdf->getPages();

        $markdown = $this->buildMetaHeader($details);

        foreach ($pages as $i => $page) {
            $text = trim($page->getText());
            if ($text === '') {
                continue;
            }
            $markdown .= '## Page ' . ($i + 1) . "\n\n";
            $markdown .= $this->normaliseText($text);
            $markdown .= "\n\n---\n\n";
        }

        return rtrim($markdown, "\n-");
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

    private function normaliseText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function cleanText(string $text): string
    {
        // Remove control characters; leave markdown to render as-is
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
