<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Exercises the running container. Skipped unless D2MD_BASE_URL is set:
 *
 *   docker compose up -d app
 *   D2MD_BASE_URL=http://localhost:8080 docker compose run --rm \
 *       -e D2MD_BASE_URL test vendor/bin/phpunit --filter SmokeTest
 */
final class SmokeTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $url = getenv('D2MD_BASE_URL');

        if ($url === false || $url === '') {
            self::markTestSkipped('D2MD_BASE_URL not set');
        }

        $this->baseUrl = rtrim($url, '/');
    }

    /** @return array{status:int,body:string,headers:string} */
    private function get(string $path): array
    {
        // follow_location off so an asserted status is the one this route
        // returned, not the one at the end of a redirect chain.
        $context = stream_context_create(['http' => [
            'ignore_errors'   => true,
            'follow_location' => 0,
            'timeout'         => 30,
        ]]);
        $body    = (string) @file_get_contents($this->baseUrl . $path, false, $context);
        $status  = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return ['status' => $status, 'body' => $body, 'headers' => implode("\n", $http_response_header ?? [])];
    }

    /**
     * @param array<string,string> $fields
     * @return array{status:int,body:string,headers:string}
     */
    private function post(string $path, array $fields): array
    {
        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/x-www-form-urlencoded',
            'content'       => http_build_query($fields),
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout'       => 30,
        ]]);

        $body   = (string) @file_get_contents($this->baseUrl . $path, false, $context);
        $status = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return ['status' => $status, 'body' => $body, 'headers' => implode("\n", $http_response_header ?? [])];
    }

    public function testHealthReportsOk(): void
    {
        $response = $this->get('/health');

        self::assertSame(200, $response['status']);

        $payload = json_decode($response['body'], true);

        self::assertSame('ok', $payload['status']);
        self::assertNotContains(false, $payload['checks']);
    }

    /** @dataProvider pages */
    public function testPageRenders(string $path): void
    {
        self::assertSame(200, $this->get($path)['status']);
    }

    public static function pages(): array
    {
        return [['/'], ['/app'], ['/api'], ['/terms'], ['/privacy'], ['/login']];
    }

    public function testSignInPageOffersTheForm(): void
    {
        $response = $this->get('/login');

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('name="email"', $response['body']);
    }

    /** Nothing in front may cache a page that varies by session. */
    public function testSignInPageIsNotCacheable(): void
    {
        $headers = strtolower($this->get('/login')['headers']);

        self::assertStringContainsString('no-store', $headers);
        self::assertStringContainsString('vary: cookie', $headers);
    }

    public function testRequestingALinkReportsTheSameThingRegardless(): void
    {
        $known   = $this->post('/login', ['email' => 'smoke-' . bin2hex(random_bytes(4)) . '@example.com']);
        $unknown = $this->post('/login', ['email' => 'nobody-' . bin2hex(random_bytes(4)) . '@example.com']);

        self::assertSame(200, $known['status']);
        self::assertSame($unknown['status'], $known['status']);
        self::assertStringContainsString('inbox', strtolower($known['body']));
        self::assertStringContainsString('inbox', strtolower($unknown['body']));
    }

    public function testRejectedAddressIsReportedOnTheForm(): void
    {
        $response = $this->post('/login', ['email' => 'not-an-address']);

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('name="email"', $response['body'], 'the form comes back');
    }

    /**
     * Mail scanners, link previewers and corporate proxies all fetch a link
     * with GET, so a GET must never spend the token.
     */
    public function testFetchingAnUnknownLinkDoesNotError(): void
    {
        $response = $this->get('/login?token=' . str_repeat('a', 64));

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('no longer works', $response['body']);
    }

    public function testSignOutIsPostOnly(): void
    {
        self::assertSame(303, $this->get('/logout')['status']);
    }

    public function testLegalPagesCarryTheContactAddress(): void
    {
        foreach (['/terms', '/privacy'] as $path) {
            self::assertStringContainsString('mailto:', $this->get($path)['body'], "$path must publish a contact channel");
        }
    }

    public function testLegalPagesAreLinkedFromEveryPage(): void
    {
        foreach (['/', '/app', '/api'] as $path) {
            $body = $this->get($path)['body'];

            self::assertStringContainsString('href="/terms"', $body, "$path must link the terms");
            self::assertStringContainsString('href="/privacy"', $body, "$path must link the privacy page");
        }
    }

    public function testSecurityHeadersArePresent(): void
    {
        $headers = strtolower($this->get('/')['headers']);

        self::assertStringContainsString('x-content-type-options: nosniff', $headers);
        self::assertStringContainsString('x-frame-options: deny', $headers);
        self::assertStringNotContainsString('x-powered-by', $headers);
    }

    public function testDirectoryListingIsOff(): void
    {
        self::assertNotSame(200, $this->get('/assets/')['status']);
    }

    public function testDataDirectoriesAreNotReachable(): void
    {
        foreach (['/md/', '/data/', '/storage/', '/mappings/', '/config.php', '/composer.json'] as $path) {
            self::assertNotSame(200, $this->get($path)['status'], "$path must not be served");
        }
    }

    /** @return array<string,mixed>|null decoded JSON payload */
    private function postFixture(string $fixture): ?array
    {
        $path = dirname(__DIR__) . '/fixtures/' . $fixture;
        self::assertFileExists($path, 'run php tests/fixtures/make-pdf.php first');

        $boundary = '----d2md' . bin2hex(random_bytes(8));
        $body     = "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"$fixture\"\r\n"
            . "Content-Type: application/pdf\r\n\r\n"
            . file_get_contents($path) . "\r\n"
            . "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"format\"\r\n\r\npdf\r\n"
            . "--$boundary--\r\n";

        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: multipart/form-data; boundary=$boundary",
            'content'       => $body,
            'ignore_errors' => true,
            'timeout'       => 60,
        ]]);

        $raw = (string) file_get_contents($this->baseUrl . '/api', false, $context);

        return json_decode($raw, true);
    }

    /** The probe reaches this verdict from the first pages, before charging quota. */
    public function testDocumentWithNoTextLayerIsRejected(): void
    {
        $payload = $this->postFixture('image-only.pdf');

        self::assertFalse($payload['success'] ?? true, json_encode($payload));
        self::assertStringContainsString('no text layer', (string) ($payload['message'] ?? ''));
    }

    public function testConversionRoundTrip(): void
    {
        $payload = $this->postFixture('sample.pdf');

        self::assertTrue($payload['success'] ?? false, json_encode($payload));
        self::assertMatchesRegularExpression('/^[a-f0-9]{15}$/', $payload['hash']);
        self::assertStringContainsString('Hello data2md', $payload['preview']);

        $download = $this->get('/download?hash=' . $payload['hash']);

        self::assertSame(200, $download['status']);
        self::assertStringContainsString('Hello data2md', $download['body']);
    }

    public function testUnknownHashIsNotFound(): void
    {
        self::assertSame(404, $this->get('/download?hash=aaaaaaaaaaaaaaa')['status']);
    }

    public function testMalformedHashIsRejected(): void
    {
        self::assertSame(400, $this->get('/download?hash=../../etc/passwd')['status']);
    }

    /** @return array{status:int,body:string,headers:string} */
    private function postMultipart(string $boundary, string $body): array
    {
        $context = stream_context_create(['http' => [
            'method'         => 'POST',
            'header'         => "Content-Type: multipart/form-data; boundary=$boundary",
            'content'        => $body,
            'ignore_errors'  => true,
            'timeout'        => 30,
        ]]);

        $responseBody = (string) @file_get_contents($this->baseUrl . '/api', false, $context);
        $status       = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return ['status' => $status, 'body' => $responseBody, 'headers' => implode("\n", $http_response_header ?? [])];
    }

    /**
     * "format[]=pdf" turns $_POST['format'] into an array; before the fix
     * this reached strtolower(trim(array)) and crashed with an uncaught
     * TypeError — a bare 500 with an empty body — before the try/catch that
     * is supposed to guarantee the JSON error contract.
     */
    public function testArrayValuedFormatFieldGetsACleanBadRequest(): void
    {
        $fixture  = dirname(__DIR__) . '/fixtures/sample.pdf';
        $boundary = '----d2md' . bin2hex(random_bytes(8));
        $body     = "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"sample.pdf\"\r\n"
            . "Content-Type: application/pdf\r\n\r\n"
            . file_get_contents($fixture) . "\r\n"
            . "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"format[]\"\r\n\r\npdf\r\n"
            . "--$boundary--\r\n";

        $response = $this->postMultipart($boundary, $body);

        self::assertSame(400, $response['status']);
        $payload = json_decode($response['body'], true);
        self::assertIsArray($payload, 'response must be JSON, not an empty body from an uncaught TypeError');
        self::assertFalse($payload['success'] ?? true);
    }

    /**
     * Two "file[]" parts turn $_FILES['file']['error'] into an array; before
     * the fix this reached uploadErrorMessage(array), typed int — same
     * uncaught-TypeError, bare-500 failure as above.
     */
    public function testArrayValuedFileFieldGetsACleanBadRequest(): void
    {
        $fixture  = dirname(__DIR__) . '/fixtures/sample.pdf';
        $boundary = '----d2md' . bin2hex(random_bytes(8));
        $part     = static fn (string $name): string => "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"file[]\"; filename=\"$name\"\r\n"
            . "Content-Type: application/pdf\r\n\r\n"
            . file_get_contents($fixture) . "\r\n";

        $body = $part('a.pdf') . $part('b.pdf') . "--$boundary--\r\n";

        $response = $this->postMultipart($boundary, $body);

        self::assertSame(400, $response['status']);
        $payload = json_decode($response['body'], true);
        self::assertIsArray($payload, 'response must be JSON, not an empty body from an uncaught TypeError');
        self::assertFalse($payload['success'] ?? true);
    }
}
