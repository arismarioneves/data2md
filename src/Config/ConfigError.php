<?php

declare(strict_types=1);

namespace Data2Md\Config;

final class ConfigError extends \RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(private readonly array $problems)
    {
        parent::__construct(
            "Invalid configuration:\n  - " . implode("\n  - ", $problems)
        );
    }

    /** @return list<string> */
    public function getProblems(): array
    {
        return $this->problems;
    }
}
