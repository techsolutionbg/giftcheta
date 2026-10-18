<?php
declare(strict_types=1);
namespace Giftcheta\Core;

use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** SMTP transport for account security messages. */
final class SmtpMailer
{
    public function __construct(private readonly Config $config) {}

    public function send(string $recipient, string $subject, string $body): void
    {
        $host = trim((string) $this->config->get('mail.host', '127.0.0.1'));
        $port = (int) $this->config->get('mail.port', 1025);
        $encryption = strtolower((string) ($this->config->get('mail.encryption') ?? ''));
        $from = trim((string) $this->config->get('mail.from_address', 'no-reply@giftcheta.local'));
        $fromName = trim((string) $this->config->get('mail.from_name', 'Giftcheta'));
        $username = (string) ($this->config->get('mail.username') ?? '');
        $password = (string) ($this->config->get('mail.password') ?? '');
        if (strtolower((string) $this->config->get('mail.mailer', 'smtp')) !== 'smtp') throw new RuntimeException('Only SMTP mail transport is configured.');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid mail address configuration.');
        if ($host === '' || preg_match('/[\r\n\s\/\\?#@]/', $host) || $port < 1 || $port > 65535 || !in_array($encryption, ['', 'tls', 'ssl'], true)) throw new RuntimeException('Invalid SMTP configuration.');
        if (($username === '') !== ($password === '')) throw new RuntimeException('SMTP username and password must both be provided.');
        if ($this->config->get('app.env') === 'production' && $encryption === '') throw new RuntimeException('SMTP TLS is required in production.');

        $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';
        $dsnHost = str_contains($host, ':') ? '[' . trim($host, '[]') . ']' : $host;
        $credentials = $username === '' ? '' : rawurlencode($username) . ':' . rawurlencode($password) . '@';
        $dsn = $scheme . '://' . $credentials . $dsnHost . ':' . $port;
        if ($encryption === 'tls') $dsn .= '?require_tls=true';
        elseif ($encryption === '') $dsn .= '?auto_tls=false';

        $email = (new Email())
            ->from(new Address($from, $fromName))
            ->to($recipient)
            ->subject($subject)
            ->text($body);
        (new Mailer(Transport::fromDsn($dsn)))->send($email);
    }
}
