<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Mail;

use Data2Md\Log\Logger;
use Data2Md\Mail\Mailer;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/d2md-mail-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->logDir);
    }

    private function logger(): Logger
    {
        return new Logger($this->logDir, 10_485_760, false);
    }

    private function logMailer(): Mailer
    {
        return new Mailer(
            logger:      $this->logger(),
            transport:   Mailer::TRANSPORT_LOG,
            fromAddress: 'no-reply@example.com',
            fromName:    'data2md',
        );
    }

    public function testLogTransportRecordsTheMessage(): void
    {
        self::assertTrue($this->logMailer()->send(
            'alice@example.com',
            'Sign in to data2md',
            'https://example.com/login?token=abc'
        ));

        $entry = json_decode(trim((string) file_get_contents($this->logDir . '/mail.jsonl')), true);

        self::assertSame('alice@example.com', $entry['to']);
        self::assertSame('Sign in to data2md', $entry['subject']);
        self::assertStringContainsString('token=abc', $entry['body']);
    }

    /** The entries carry a working sign-in link. */
    public function testMailChannelIsOwnerOnly(): void
    {
        $this->logMailer()->send('alice@example.com', 'subject', 'body');

        clearstatcache(true, $this->logDir . '/mail.jsonl');

        self::assertSame(0600, fileperms($this->logDir . '/mail.jsonl') & 0777);
    }

    public function testLogTransportDoesNotWriteTheOperationalChannel(): void
    {
        $this->logMailer()->send('alice@example.com', 'subject', 'body');

        self::assertFileDoesNotExist(
            $this->logDir . '/app.jsonl',
            'a sign-in link must not reach the channel that mirrors to stderr'
        );
    }

    /**
     * A relay that cannot be reached must not throw: the sign-in page shows the
     * same message either way, so it cannot reveal whether an address exists.
     */
    public function testUnreachableRelayIsReportedNotThrown(): void
    {
        $mailer = new Mailer(
            logger:         $this->logger(),
            transport:      Mailer::TRANSPORT_SMTP,
            fromAddress:    'no-reply@example.com',
            fromName:       'data2md',
            // Reserved by RFC 5737 for documentation, so nothing answers.
            host:           '192.0.2.1',
            port:           587,
            timeoutSeconds: 1,
        );

        $started = microtime(true);
        $sent    = $mailer->send('alice@example.com', 'subject', 'body');
        $elapsed = microtime(true) - $started;

        self::assertFalse($sent);
        self::assertLessThan(
            20.0,
            $elapsed,
            'the timeout must bound the attempt; PHPMailer defaults to 300 seconds'
        );

        $log = (string) file_get_contents($this->logDir . '/app.jsonl');

        self::assertStringContainsString('mail.send.fail', $log);
        self::assertStringNotContainsString('alice@example.com', $log, 'no address in the operational channel');
    }
}
