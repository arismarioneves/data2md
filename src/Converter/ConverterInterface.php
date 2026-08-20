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

    /** @return list<string> */
    public function getSupportedMimeTypes(): array;

    /** @return list<string> */
    public function getSupportedExtensions(): array;

    public function getFormat(): string;
}
