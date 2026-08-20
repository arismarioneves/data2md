<?php

declare(strict_types=1);

namespace Data2Md\Converter;

final class ConversionFailedException extends ConversionException
{
    public function httpStatus(): int
    {
        return 422;
    }
}
