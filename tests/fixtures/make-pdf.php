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

/**
 * A PDF with one page per entry in $pageContents, each with its own content
 * stream and a shared font. An empty string produces a page with no content
 * stream operators at all.
 *
 * @param list<string> $pageContents
 */
function multi_page_pdf(array $pageContents, bool $withFont = true): string
{
    $count     = count($pageContents);
    $fontNum   = 3 + 2 * $count;
    $resources = $withFont ? '<</Font<</F1 ' . $fontNum . ' 0 R>>>>' : '<<>>';

    $kids = [];
    for ($i = 0; $i < $count; $i++) {
        $kids[] = (3 + $i) . ' 0 R';
    }

    $objects = [
        1 => '<</Type/Catalog/Pages 2 0 R>>',
        2 => '<</Type/Pages/Kids[' . implode(' ', $kids) . ']/Count ' . $count . '>>',
    ];

    for ($i = 0; $i < $count; $i++) {
        $pageNum    = 3 + $i;
        $contentNum = 3 + $count + $i;
        $content    = $pageContents[$i];

        $objects[$pageNum]    = '<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents ' . $contentNum . ' 0 R/Resources' . $resources . '>>';
        $objects[$contentNum] = '<</Length ' . strlen($content) . '>>stream' . "\n" . $content . 'endstream';
    }

    if ($withFont) {
        $objects[$fontNum] = '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>';
    }

    return build_pdf($objects);
}

// Three pages of text, for exercising page-break handling across more than
// one form feed.
file_put_contents(
    __DIR__ . '/multi-page.pdf',
    multi_page_pdf([
        "BT /F1 12 Tf 72 700 Td (Page one content here) Tj ET\n",
        "BT /F1 12 Tf 72 700 Td (Page two content here) Tj ET\n",
        "BT /F1 12 Tf 72 700 Td (Page three content here) Tj ET\n",
    ])
);

// Three pages where the middle one has no content stream operators at all —
// consecutive form feeds with nothing but layout whitespace between them.
file_put_contents(
    __DIR__ . '/blank-middle-page.pdf',
    multi_page_pdf([
        "BT /F1 12 Tf 72 700 Td (First page content) Tj ET\n",
        '',
        "BT /F1 12 Tf 72 700 Td (Third page content) Tj ET\n",
    ])
);

echo "fixtures written\n";
