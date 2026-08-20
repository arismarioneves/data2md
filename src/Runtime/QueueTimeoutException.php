<?php

declare(strict_types=1);

namespace Data2Md\Runtime;

final class QueueTimeoutException extends \RuntimeException
{
    public function __construct(private readonly int $retryAfter)
    {
        parent::__construct('No conversion slot became available within the queue window.');
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
