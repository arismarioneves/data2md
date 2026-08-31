<?php

declare(strict_types=1);

/**
 * Builds the conversion pipeline and the storage it shares with the caller's
 * garbage-collection sweep.
 *
 * Defines $database, $files, $mappings and $pipeline.
 *
 * @var \Data2Md\Config\Config $config
 * @var \Data2Md\Log\Logger $logger
 */

use Data2Md\Convert\ConversionPipeline;
use Data2Md\Converter\MarkdownSanitizer;
use Data2Md\Converter\PdfConverter;
use Data2Md\Runtime\ConversionSemaphore;
use Data2Md\Runtime\Process;
use Data2Md\Security\RateLimiter;
use Data2Md\Security\Validator;
use Data2Md\Storage\Database;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;

const PREVIEW_BYTES = 8000;

$convertDataDir = $config->string('APP_DATA_DIR');

$database = new Database($convertDataDir . '/db/mappings.sqlite');
$mappings = new MappingStorage($database);
$files    = new FileStorage($convertDataDir . '/md', $convertDataDir . '/tmp');

$pipeline = new ConversionPipeline(
    validator:              new Validator(MAX_FILE_SIZE, MAX_FILE_SIZE_LABEL),
    mappings:               $mappings,
    files:                  $files,
    semaphore:              new ConversionSemaphore(
        $convertDataDir . '/tmp',
        $config->int('CONVERT_CONCURRENCY'),
        $config->int('CONVERT_QUEUE_WAIT_SECONDS'),
    ),
    rateLimiter:            new RateLimiter($convertDataDir . '/ratelimit', RATE_LIMIT_MAX, RATE_LIMIT_WINDOW),
    logger:                 $logger,
    converterFactory:       static fn (string $format): PdfConverter => match ($format) {
        'pdf'   => new PdfConverter(
            'pdftotext',
            new Process(
                $config->int('CONVERT_TIMEOUT_SECONDS'),
                Process::priorityPrefix($config->int('CONVERT_NICE')),
            ),
            new MarkdownSanitizer(),
            $convertDataDir . '/tmp',
        ),
        default => throw new \LogicException("Unsupported format: $format"),
    },
    previewBytes:           PREVIEW_BYTES,
    rateLimitMax:           RATE_LIMIT_MAX,
    rateLimitWindowSeconds: RATE_LIMIT_WINDOW,
);
