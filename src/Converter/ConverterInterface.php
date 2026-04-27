<?php

declare(strict_types=1);

namespace Data2Md\Converter;

interface ConverterInterface
{
    public function convert(string $filePath): string;
    public function getSupportedMimeTypes(): array;
    public function getSupportedExtensions(): array;
    public function getFormat(): string;
}
