<?php

declare(strict_types=1);

namespace Data2Md\Mail;

/**
 * The sign-in message, in both the forms a mail client might use.
 *
 * Built here rather than in the page so it can be read in a test without an
 * SMTP server, and so the two bodies cannot drift: a client that shows only the
 * plain part must still be able to sign in.
 *
 * Deliberately image-free. A remote image in an e-mail is a read receipt
 * whether or not it is meant as one, and the privacy page says there is no
 * tracking. Everything here is text and table cells.
 */
final class SignInMessage
{
    private function __construct(
        public readonly string $subject,
        public readonly string $text,
        public readonly string $html,
    ) {
    }

    public static function build(string $appName, string $url, int $ttlMinutes): self
    {
        return new self(
            subject: t('auth.mail.subject', $appName),
            text:    t('auth.mail.body', $appName, $url, $ttlMinutes),
            html:    self::render($appName, $url, $ttlMinutes),
        );
    }

    private static function render(string $appName, string $url, int $ttlMinutes): string
    {
        $name    = self::escape($appName);
        $href    = self::escape($url);
        $heading = self::escape(t('auth.mail.heading', $appName));
        $lead    = self::escape(t('auth.mail.lead', $ttlMinutes));
        $button  = self::escape(t('auth.mail.button'));
        $fall    = self::escape(t('auth.mail.fallback'));
        $ignore  = self::escape(t('auth.mail.ignore'));

        // Table layout and inline styles: a mail client is not a browser, and
        // most strip anything in a <style> block.
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="utf-8">
        <meta name="color-scheme" content="light">
        <title>$heading</title>
        </head>
        <body style="margin:0;padding:0;background:#f8f5ee;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8f5ee;">
          <tr>
            <td align="center" style="padding:32px 16px;">
              <table role="presentation" width="480" cellpadding="0" cellspacing="0" border="0" style="width:480px;max-width:100%;background:#ffffff;border:1px solid #e7e1d3;border-radius:16px;">
                <tr>
                  <td style="padding:32px 32px 8px 32px;font-family:Georgia,'Times New Roman',serif;font-size:22px;font-weight:bold;font-style:italic;color:#15110d;">
                    $name
                  </td>
                </tr>
                <tr>
                  <td style="padding:8px 32px 0 32px;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:1.25;color:#15110d;">
                    $heading
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 32px 0 32px;font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#57534e;">
                    $lead
                  </td>
                </tr>
                <tr>
                  <td style="padding:24px 32px 0 32px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td align="center" bgcolor="#ea580c" style="border-radius:999px;">
                          <a href="$href" style="display:inline-block;padding:13px 28px;font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:999px;">$button</a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:24px 32px 0 32px;font-family:Helvetica,Arial,sans-serif;font-size:13px;line-height:1.6;color:#78716c;">
                    $fall
                  </td>
                </tr>
                <tr>
                  <td style="padding:8px 32px 0 32px;">
                    <div style="font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.5;color:#15110d;background:#f1ecde;border-radius:8px;padding:12px;word-break:break-all;">$href</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:24px 32px 32px 32px;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#a8a29e;border-top:1px solid #e7e1d3;margin-top:8px;">
                    $ignore
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        </body>
        </html>
        HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
