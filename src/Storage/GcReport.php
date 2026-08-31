<?php

declare(strict_types=1);

namespace Data2Md\Storage;

final class GcReport
{
    public function __construct(
        public readonly int $orphanMarkdown = 0,
        public readonly int $orphanMappings = 0,
        public readonly int $rateLimitFiles = 0,
        public readonly int $rotatedLogs = 0,
        public readonly int $staleTempFiles = 0,
        public readonly int $expiredSessions = 0,
        public readonly int $expiredLoginTokens = 0,
        public readonly bool $skipped = false,
    ) {
    }

    public function total(): int
    {
        return $this->orphanMarkdown
            + $this->orphanMappings
            + $this->rateLimitFiles
            + $this->rotatedLogs
            + $this->staleTempFiles
            + $this->expiredSessions
            + $this->expiredLoginTokens;
    }

    /** @return array<string,int> */
    public function toArray(): array
    {
        return [
            'orphan_markdown'      => $this->orphanMarkdown,
            'orphan_mappings'      => $this->orphanMappings,
            'rate_limit_files'     => $this->rateLimitFiles,
            'rotated_logs'         => $this->rotatedLogs,
            'stale_temp_files'     => $this->staleTempFiles,
            'expired_sessions'     => $this->expiredSessions,
            'expired_login_tokens' => $this->expiredLoginTokens,
        ];
    }
}
