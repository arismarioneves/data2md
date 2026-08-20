<?php

declare(strict_types=1);

namespace Data2Md\Converter;

abstract class ConversionException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $detail = null,
    ) {
        parent::__construct($message);
    }

    /** HTTP status that best describes this failure to an API client. */
    abstract public function httpStatus(): int;

    /**
     * Diagnostic detail for operators, such as raw stderr from the extractor.
     * May contain filesystem paths, so callers must log it rather than
     * forward it to an API client; getMessage() never contains it.
     */
    public function detail(): ?string
    {
        return $this->detail;
    }
}
