<?php
/**
 * Mail sender.
 *
 * Local development has no mail server, so messages are appended to
 * storage/logs/mail.log (outside the web root) instead of being sent.
 * Swap send() for SMTP (e.g. PHPMailer) when deploying.
 */

declare(strict_types=1);

final class Mailer
{
    public static function logPath(): string
    {
        return env('MAIL_LOG', STORAGE_PATH . '/logs/mail.log');
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        // Strip CR/LF so header values can't be injected later with real SMTP.
        $to      = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], '', $subject);

        $entry = sprintf(
            "==================== %s UTC ====================\nTo: %s\nSubject: %s\n\n%s\n\n",
            gmdate('Y-m-d H:i:s'), $to, $subject, $body
        );
        return file_put_contents(self::logPath(), $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
