<?php

declare(strict_types=1);

namespace Data2Md\Converter;

final class ConversionResult
{
    public function __construct(
        public readonly int $lines,
        public readonly int $characters,
        public readonly int $pages,
    ) {
    }
}
