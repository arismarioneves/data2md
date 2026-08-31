<?php

declare(strict_types=1);

namespace Data2Md\Converter;

/**
 * The PDF parsed fine but carries no text — a scan or an image-only export.
 * There is nothing to extract; OCR would be a different feature.
 */
final class NoTextLayerException extends ConversionException
{
    /** Also reported by the probe, which reaches the same verdict earlier. */
    public const MESSAGE =
        'This PDF has no text layer — it looks like a scan or an image-only export, and needs OCR.';

    public function httpStatus(): int
    {
        return 422;
    }
}
