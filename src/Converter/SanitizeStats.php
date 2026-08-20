<?php

declare(strict_types=1);

namespace Data2Md\Converter;

final class SanitizeStats
{
    public function __construct(
        public readonly int $lines,
        public readonly int $characters,
    ) {
    }
}
