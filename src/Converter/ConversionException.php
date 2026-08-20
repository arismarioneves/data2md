<?php

declare(strict_types=1);

namespace Data2Md\Converter;

abstract class ConversionException extends \RuntimeException
{
    /** HTTP status that best describes this failure to an API client. */
    abstract public function httpStatus(): int;
}
