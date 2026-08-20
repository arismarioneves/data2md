<?php

declare(strict_types=1);

/**
 * Generates the PDF fixtures. Run once; the output is committed.
 *
 *   php tests/fixtures/make-pdf.php
 */

/** @param array<int,string> $objects */
function build_pdf(array $objects): string
{
    $pdf     = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "$number 0 obj\n$body\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $size       = count($objects) + 1;

    $pdf .= "xref\n0 $size\n";
    $pdf .= sprintf("%010d %05d f \n", 0, 65535);

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d %05d n \n", $offsets[$i], 0);
    }

    $pdf .= "trailer\n<</Size $size/Root 1 0 R>>\nstartxref\n$xrefOffset\n%%EOF\n";

    return $pdf;
}

function page_pdf(string $content, bool $withFont = true): string
{
    $resources = $withFont ? '<</Font<</F1 5 0 R>>>>' : '<<>>';

    $objects = [
        1 => '<</Type/Catalog/Pages 2 0 R>>',
        2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
        3 => '<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources' . $resources . '>>',
        4 => '<</Length ' . strlen($content) . '>>stream' . "\n" . $content . 'endstream',
    ];

    if ($withFont) {
        $objects[5] = '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>';
    }

    return build_pdf($objects);
}

// A PDF with a real text layer.
file_put_contents(
    __DIR__ . '/sample.pdf',
    page_pdf("BT /F1 24 Tf 72 700 Td (Hello data2md) Tj ET\nBT /F1 12 Tf 72 660 Td (Second line of text) Tj ET\n")
);

// A PDF that draws a filled rectangle and no text at all — what a scanned,
// image-only document looks like to pdftotext.
file_put_contents(
    __DIR__ . '/image-only.pdf',
    page_pdf("0 0 0 rg\n100 100 200 200 re f\n", withFont: false)
);

// Not a PDF at all, for the corrupt-input path.
file_put_contents(__DIR__ . '/corrupt.pdf', "%PDF-1.4\nthis is not a valid pdf body\n");

echo "fixtures written\n";
