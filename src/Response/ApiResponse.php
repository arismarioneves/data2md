<?php

declare(strict_types=1);

namespace Data2Md\Response;

class ApiResponse
{
    public static function success(array $data, int $status = 200): never
    {
        self::send(['success' => true, ...$data], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        self::send($payload, $status);
    }

    private static function send(array $payload, int $status): never
    {
        // JSON_INVALID_UTF8_SUBSTITUTE is defence in depth: without it, a
        // single malformed byte anywhere in $payload (e.g. an unscrubbed
        // preview) makes json_encode() return false, and echo false sends an
        // empty 2xx body instead of an error.
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($body === false) {
            $body = '';
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // An explicit Content-Length plus an explicit flush() let the client
        // treat the response as complete as soon as these bytes arrive.
        // Without both, Apache either falls back to chunked transfer or
        // holds the body in PHP's output buffer until the whole script —
        // including any register_shutdown_function callback, such as the
        // deferred GC sweep in api.php — finishes. Verified against the app
        // image: identical output without this pair blocks the client for
        // as long as the shutdown callback runs; with it, the client returns
        // immediately regardless of what runs afterward.
        header('Content-Length: ' . strlen($body));
        echo $body;
        flush();
        exit;
    }
}
