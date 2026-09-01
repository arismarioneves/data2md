<?php

declare(strict_types=1);

namespace Data2Md\Converter;

interface ConverterInterface
{
    /**
     * Convert $filePath and write the markdown to $outputPath.
     *
     * Streams rather than returning a string: the document is never held in
     * memory, so peak usage is independent of file size.
     *
     * @throws ConversionException
     */
    public function convertToFile(string $filePath, string $outputPath): ConversionResult;

    /**
     * Whether a full conversion is worth starting, decided from a small prefix
     * of the document. False means there is nothing to extract.
     *
     * Answers cheaply enough to run before a caller commits any quota: on a
     * 482 MB, 9952-page PDF the probe takes about 1.5s against 70s for a full
     * run on a 115 MB one. An unreadable document answers true, so the full run
     * reports the specific failure rather than this reporting an empty one.
     */
    public function probe(string $filePath): bool;

    /** @return list<string> */
    public function getSupportedMimeTypes(): array;

    /** @return list<string> */
    public function getSupportedExtensions(): array;

    public function getFormat(): string;
}
