<?php

declare(strict_types=1);

namespace Data2Md\Convert;

use Data2Md\Converter\ConversionException;
use Data2Md\Converter\ConverterInterface;
use Data2Md\Converter\NoTextLayerException;
use Data2Md\Log\Logger;
use Data2Md\Runtime\ConversionSemaphore;
use Data2Md\Runtime\QueueTimeoutException;
use Data2Md\Security\RateLimiter;
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
        private readonly RateLimiter $rateLimiter,
        private readonly Logger $logger,
        private readonly \Closure $converterFactory,
        private readonly int $previewBytes,
        private readonly int $rateLimitMax,
        private readonly int $rateLimitWindowSeconds,
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
        string $clientIp,
        string $userAgent = '',
    ): ConversionOutcome {
        $loggedIp = Logger::truncateIp($clientIp);

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

        $uploadErrors = $this->validator->validateUpload($upload->toArray(), $upload->format, $this->httpUploads);

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

        if (!$this->rateLimiter->isAllowed($clientIp)) {
            $this->logger->app('ratelimit.block', ['ip' => $loggedIp], 'warning');
            $slot->release();

            return ConversionOutcome::failure(
                sprintf(
                    'Rate limit exceeded. Maximum %d requests per %d seconds.',
                    $this->rateLimitMax,
                    $this->rateLimitWindowSeconds
                ),
                429,
                headers: ['Retry-After' => (string) $this->rateLimiter->retryAfter($clientIp)],
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

            $this->mappings->save($hash, $originalName, $upload->format, $upload->size);

            $this->logger->app('convert.ok', [
                'hash'     => $hash,
                'bytes'    => $upload->size,
                'ms'       => (int) round((microtime(true) - $startedAt) * 1000),
                'pages'    => $result->pages,
                'queue_ms' => $slot->waitedMs(),
                'ip'       => $loggedIp,
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
                $this->rateLimiter->refund($clientIp);
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
            $this->rateLimiter->refund($clientIp);

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
