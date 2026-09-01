<?php

declare(strict_types=1);

namespace Data2Md\Mail;

use Data2Md\Config\Config;
use Data2Md\Log\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Outbound plain-text mail.
 *
 * With magic-link sign-in, delivery is login availability, so a failure is
 * logged and reported to the caller rather than thrown: the page must not
 * reveal whether an address exists.
 *
 * The 'log' transport writes the message to the mail channel instead of
 * sending, which is how development and CI exercise sign-in without a relay.
 */
final class Mailer
{
    public const TRANSPORT_SMTP = 'smtp';
    public const TRANSPORT_LOG  = 'log';

    public function __construct(
        private readonly Logger $logger,
        private readonly string $transport,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $host = '',
        private readonly int $port = 587,
        private readonly string $user = '',
        private readonly string $password = '',
        private readonly string $encryption = 'tls',
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public static function fromConfig(Config $config, Logger $logger): self
    {
        $from = $config->string('MAIL_FROM');

        return new self(
            logger:         $logger,
            transport:      $config->string('MAIL_TRANSPORT'),
            fromAddress:    $from !== '' ? $from : $config->string('CONTACT_EMAIL'),
            fromName:       $config->string('APP_NAME'),
            host:           $config->string('SMTP_HOST'),
            port:           $config->int('SMTP_PORT'),
            user:           $config->string('SMTP_USER'),
            password:       $config->string('SMTP_PASSWORD'),
            encryption:     $config->string('SMTP_ENCRYPTION'),
            timeoutSeconds: $config->int('SMTP_TIMEOUT_SECONDS'),
        );
    }

    /**
     * @param string      $body plain text, and the alternative part when html is given
     * @param string|null $html sent alongside the text, never instead of it
     */
    public function send(string $to, string $subject, string $body, ?string $html = null): bool
    {
        if ($this->transport === self::TRANSPORT_LOG) {
            // Only the text part: this channel exists so an operator can read the
            // link out of it, and a wall of markup buries the one line that matters.
            $this->logger->mail(['to' => $to, 'subject' => $subject, 'body' => $body]);

            return true;
        }

        try {
            $this->deliver($to, $subject, $body, $html);
        } catch (PHPMailerException | \Throwable $e) {
            $this->logger->app('mail.send.fail', [
                'host'   => $this->host,
                'reason' => $e::class,
                'detail' => $e->getMessage(),
            ], 'error');

            return false;
        }

        return true;
    }

    private function deliver(string $to, string $subject, string $body, ?string $html = null): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->Port = $this->port;

        if ($this->user !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $this->user;
            $mail->Password = $this->password;
        }

        $mail->SMTPSecure = match ($this->encryption) {
            'ssl'   => PHPMailer::ENCRYPTION_SMTPS,
            'none'  => '',
            default => PHPMailer::ENCRYPTION_STARTTLS,
        };

        if ($this->encryption === 'none') {
            $mail->SMTPAutoTLS = false;
        }

        // The default is 300 seconds. Apache here is prefork with 24 workers, so
        // a slow relay would park all of them, including the ones /health needs
        // to answer before the container is restarted.
        $mail->Timeout        = $this->timeoutSeconds;
        $mail->SMTPKeepAlive  = false;
        $mail->CharSet        = PHPMailer::CHARSET_UTF8;
        // A sign-in URL must not be folded across lines.
        $mail->WordWrap       = 0;
        $mail->SMTPDebug      = SMTP::DEBUG_OFF;

        $mail->setFrom($this->fromAddress, $this->fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;

        if ($html !== null) {
            // multipart/alternative. The text part is not a courtesy: a client
            // that shows only that one still has to be able to sign in, and mail
            // with no text alternative scores worse with spam filters.
            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = $body;
        } else {
            $mail->Body = $body;
        }

        $mail->send();
    }
}
