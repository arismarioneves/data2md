<?php

declare(strict_types=1);

namespace Data2Md\Convert;

use Data2Md\Auth\Actor;
use Data2Md\Converter\ConversionException;
use Data2Md\Converter\ConverterInterface;
use Data2Md\Converter\NoTextLayerException;
use Data2Md\Log\Logger;
use Data2Md\Runtime\ConversionSemaphore;
use Data2Md\Runtime\QueueTimeoutException;
use Data2Md\Security\RateLimiter;
use Data2Md\Security\RateLimiterFactory;
use Data2Md\Security\Validator;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;

/**
 * The ordered gates every conversion passes, from raw superglobals to an
 * outcome.
 *
 * Nothing here may reference the response layer. ApiResponse exits, and exit
 * skips an enclosing finally, so a response written from inside this class
 * would leave the uploaded file on disk and the conversion slot held until the
 * process ended.
 *
 * The order is load-bearing. Cheap local checks, then a slot, then a probe,
 * then the quota charge, then the conversion: a caller is only charged once a
 * conversion is actually going to run.
 */
final class ConversionPipeline
{
    /** @param \Closure(string): ConverterInterface $converterFactory */
    public function __construct(
        private readonly Validator $validator,
        private readonly MappingStorage $mappings,
        private readonly FileStorage $files,
        private readonly ConversionSemaphore $semaphore,
        private readonly RateLimiterFactory $rateLimiters,
        private readonly Logger $logger,
        private readonly \Closure $converterFactory,
        private readonly int $previewBytes,
        /**
         * @param bool $httpUploads false only in tests, where there is no real
         *                          multipart upload to verify or move
         */
        private readonly bool $httpUploads = true,
    ) {
    }

    /**
     * @param array<string,mixed> $requestFiles $_FILES
     * @param array<string,mixed> $requestPost  $_POST
     */
    public function handle(
        array $requestFiles,
        array $requestPost,
        Actor $actor,
        string $userAgent = '',
    ): ConversionOutcome {
        $clientIp = $actor->ip;
        $loggedIp = Logger::truncateIp($clientIp);
        $tier     = $actor->tier;

        try {
            $upload = UploadedFile::fromRequest($requestFiles, $requestPost);
        } catch (MalformedUploadException $e) {
            return ConversionOutcome::failure($e->getMessage(), 400);
        }

        if (!$this->validator->isAllowedFormat($upload->format)) {
            return ConversionOutcome::failure(
                'Unsupported format. Allowed: ' . implode(', ', $this->validator->supportedFormats()),
                400
            );
        }

        $uploadErrors = $this->validator->validateUpload(
            $upload->toArray(),
            $upload->format,
            $tier->maxFileSizeBytes(),
            $tier->maxFileSizeLabel(),
            $this->httpUploads,
        );

        if ($uploadErrors !== []) {
            return ConversionOutcome::failure('Validation failed.', 422, $uploadErrors);
        }

        // Built before a slot exists so an unsupported format cannot leak one.
        $converter = ($this->converterFactory)($upload->format);

        try {
            $slot = $this->semaphore->acquire();
        } catch (QueueTimeoutException $e) {
            $this->logger->app('convert.rejected', ['ip' => $loggedIp, 'reason' => 'queue_full'], 'warning');

            return ConversionOutcome::failure(
                'The server is busy converting other documents. Please retry shortly.',
                503,
                headers: ['Retry-After' => (string) $e->retryAfter()],
            );
        }

        // Both remaining gates hold the slot, so they release it by hand before
        // returning. Past this point the finally below owns it.
        if (!$converter->probe($upload->tmpPath)) {
            $this->logger->app('convert.rejected', ['ip' => $loggedIp, 'reason' => 'no_text_layer'], 'warning');
            $slot->release();

            return ConversionOutcome::failure(NoTextLayerException::MESSAGE, 422);
        }

        // Charged in order, and anything already charged is given back if a
        // later bucket denies, so a caller is never billed for a conversion
        // that did not happen.
        $charged = [];

        foreach ($actor->quotaChecks() as $check) {
            $limiter = $this->rateLimiters->make($check->maxRequests, $check->windowSeconds);

            if ($limiter->isAllowed($check->key)) {
                $charged[] = [$limiter, $check->key];
                continue;
            }

            self::refundAll($charged);

            $this->logger->app('ratelimit.block', [
                'ip'   => $loggedIp,
                'user' => $actor->userId,
                'plan' => $actor->plan,
            ], 'warning');
            $slot->release();

            return ConversionOutcome::failure(
                sprintf(
                    'Rate limit exceeded. Maximum %d conversions per %d seconds.',
                    $check->maxRequests,
                    $check->windowSeconds
                ),
                429,
                headers: ['Retry-After' => (string) $limiter->retryAfter($check->key)],
            );
        }

        $originalPath = null;
        $hash         = null;
        $startedAt    = microtime(true);

        try {
            $originalName = $this->validator->sanitizeOriginalName($upload->name);

            $hash         = $this->mappings->generateUniqueHash();
            $originalPath = $this->files->acceptUpload($upload->tmpPath, $hash, $this->httpUploads);

            $result = $converter->convertToFile($originalPath, $this->files->markdownPath($hash));

            $this->mappings->save($hash, $originalName, $upload->format, $upload->size, userId: $actor->userId);

            $this->logger->app('convert.ok', [
                'hash'     => $hash,
                'bytes'    => $upload->size,
                'ms'       => (int) round((microtime(true) - $startedAt) * 1000),
                'pages'    => $result->pages,
                'queue_ms' => $slot->waitedMs(),
                'ip'       => $loggedIp,
                'user'     => $actor->userId,
                'plan'     => $actor->plan,
            ]);

            $this->logger->audit([
                'hash'          => $hash,
                'original_name' => $originalName,
                'bytes'         => $upload->size,
                'ip'            => $clientIp,
                'ua'            => substr($userAgent, 0, 256),
            ]);

            return ConversionOutcome::success([
                'hash'          => $hash,
                'original_name' => $originalName,
                'format'        => $upload->format,
                'lines'         => $result->lines,
                'characters'    => $result->characters,
                'pages'         => $result->pages,
                'download_url'  => '/download?hash=' . urlencode($hash),
                ...$this->preview($hash),
            ]);
        } catch (ConversionException $e) {
            // NoTextLayerException is the one failure only reachable after
            // walking the whole document, so it stays charged; refunding it
            // would make the most expensive path free.
            if (!$e instanceof NoTextLayerException) {
                self::refundAll($charged);
            }

            $this->logger->app('convert.fail', [
                'hash'   => $hash,
                'ip'     => $loggedIp,
                'ms'     => (int) round((microtime(true) - $startedAt) * 1000),
                'reason' => $e::class,
                'detail' => $e->detail(),
            ], 'error');

            return ConversionOutcome::failure($e->getMessage(), $e->httpStatus());
        } catch (\Throwable $e) {
            self::refundAll($charged);

            // The message may name internal paths, so it goes to the log.
            $this->logger->app('convert.fail', [
                'hash'   => $hash,
                'ip'     => $loggedIp,
                'reason' => $e::class,
                'detail' => $e->getMessage(),
            ], 'error');

            return ConversionOutcome::failure('Conversion failed.', 500);
        } finally {
            if ($originalPath !== null) {
                // The uploaded file is not kept, on success or on failure.
                $this->files->deleteOriginal($originalPath);
            }

            // Explicit, not left to Slot::__destruct(): that fires at shutdown,
            // after the caller's deferred garbage collection.
            $slot->release();
        }
    }

    /** @param list<array{0:RateLimiter,1:string}> $charged */
    private static function refundAll(array $charged): void
    {
        foreach ($charged as [$limiter, $key]) {
            $limiter->refund($key);
        }
    }

    /**
     * Read back from the file rather than held in memory, so the response cost
     * is bounded no matter how large the document is.
     *
     * @return array{preview:string,preview_truncated:bool}
     */
    private function preview(string $hash): array
    {
        $truncated = $this->files->markdownSize($hash) > $this->previewBytes;
        $preview   = $this->files->readMarkdownPrefix($hash, $this->previewBytes);

        if ($truncated) {
            $breakAt = strrpos($preview, "\n");

            if ($breakAt !== false && $breakAt > 0) {
                $preview = substr($preview, 0, $breakAt);
            }
        }

        return ['preview' => $preview, 'preview_truncated' => $truncated];
    }
}
