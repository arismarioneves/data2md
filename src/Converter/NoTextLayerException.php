<?php

declare(strict_types=1);

namespace Data2Md\Converter;

/**
 * The PDF parsed fine but carries no text — a scan or an image-only export.
 * There is nothing to extract; OCR would be a different feature.
 */
final class NoTextLayerException extends ConversionException
{
    public function httpStatus(): int
    {
        return 422;
    }
}
