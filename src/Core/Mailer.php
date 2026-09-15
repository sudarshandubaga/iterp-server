<?php

declare(strict_types=1);

namespace Iterp\Core;

use RuntimeException;

/**
 * Minimal SMTP mailer (plain PHP, no dependencies).
 *
 * Supports:
 *  - STARTTLS / implicit TLS    - AUTH LOGIN
 *  - HTML bodies with UTF-8 base64 encoding
 */
class Mailer
{
    private ?\Socket $socket = null;
    private string $host;
    private int   $port;
    private string $username;
    private string $password;
    private string $encryption;

    public function __construct()
    {
        $this->host       = config('mail.smtp.host', 'localhost');
        $this->port       = (int) config('mail.smtp.port', 25);
        $this->username   = config('mail.smtp.username', '');
        $this->password   = config('mail.smtp.password', '');
        $this->encryption = config('mail.smtp.encryption', 'tls');
    }

    public static function send(mixed $mailable): void
    {
        (new self())->sendMailable($mailable);
    }

    private function sendMailable(mixed $mailable): void
    {
        if ($mailable instanceof Mailable) {
            $fromName = config('mail.from.name', '');
            $fromAddr = $mailable->fromAddress() ?: config('mail.from.address', '');
            $to       = $mailable->to();
            $subject  = $mailable->subject();
            $body     = $mailable->html();
        } else {
            throw new RuntimeException('Mailer::send expects a Mailable instance.');
        }

        $this->connect();
        $this->authenticate();
        $this->sendMessage($fromAddr, $fromName, $to, $subject, $body);
        $this->disconnect();
    }

    private function connect(): void
    {
        $remote = $this->host . ':' . $this->port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $stream = @stream_socket_client(
            'tcp://' . $remote,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$stream) {
            throw new RuntimeException("Unable to connect to mail server: {$errstr} ({$errno})");
        }

        stream_set_timeout($stream, 30);
        $this->socket = $stream;

        $this->expect(220);

        // EHLO identifies the client to the server.
        $domain = 'localhost';
        $this->say('EHLO ' . $domain);
        $this->readAll();

        // Upgrade to TLS when requested.
        if ($this->encryption === 'tls') {
            $this->say('STARTTLS');
            $this->expect(220);
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Failed to negotiate TLS.');
            }
            $this->say('EHLO ' . $domain);
            $this->readAll();
        }
    }

    private function authenticate(): void
    {
        if ($this->username === '' && $this->password === '') {
            return; // open relay / local delivery
        }

        $this->say('AUTH LOGIN');
        $this->expect(334);
        $this->say(base64_encode($this->username));
        $this->expect(334);
        $this->say(base64_encode($this->password));
        $this->expect(235);
    }

    private function sendMessage(string $from, string $fromName, string $to, string $subject, string $html): void
    {
        $this->say('MAIL FROM:<' . $from . '>');
        $this->expect(250);
        $this->say('RCPT TO:<' . $to . '>');
        $this->expect(250);
        $this->say('DATA');
        $this->expect(354);

        $headers = [
            'MIME-Version'    => '1.0',
            'Content-Type'    => 'text/html; charset=UTF-8',
            'Content-Transfer-Encoding' => 'base64',
            'To'              => $to,
            'From'            => ($fromName ? $fromName . ' ' : '') . '<' . $from . '>',
            'Subject'         => '=?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date'            => date('r'),
            'Message-ID'      => '<' . bin2hex(random_bytes(8)) . '@' . $this->host . '>',
            'X-Mailer'        => 'Iterp Mailer',
        ];

        $headerBlock = '';
        foreach ($headers as $k => $v) {
            $headerBlock .= $k . ': ' . $v . "\r\n";
        }
        $headerBlock .= "\r\n";

        $encoded = rtrim(chunk_split(base64_encode($html), 76, "\r\n"));

        // "." full-stop transparency
        $encoded = str_replace("\r\n.", "\r\n..", $encoded);

        $this->write($headerBlock . $encoded . "\r\n.\r\n");
        $this->expect(250);
    }

    private function disconnect(): void
    {
        if ($this->socket) {
            $this->say('QUIT');
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function say(string $line): void
    {
        $this->write($line . "\r\n");
    }

    private function write(string $data): void
    {
        fwrite($this->socket, $data);
    }

    private function reply(): string
    {
        $buffer = '';
        while ($line = fgets($this->socket, 512)) {
            $buffer .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break; // multiline terminated by "code space"
            }
        }
        return trim($buffer);
    }

    private function expect(int $code): void
    {
        $reply = $this->reply();
        $prefix = (string) $code;
        if (strncmp($reply, $prefix, 3) !== 0) {
            throw new RuntimeException("SMTP error, expected {$code}, got: {$reply}");
        }
    }

    private function readAll(): void
    {
        // Drain remaining greeting/response lines (loose parser).
        $this->reply();
    }
}