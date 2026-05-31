<?php

final class MailService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require __DIR__ . '/../config/mail.php';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['enabled'])
            && $this->config['host'] !== ''
            && (int) $this->config['port'] > 0
            && $this->config['username'] !== ''
            && $this->config['password'] !== ''
            && filter_var($this->config['from_address'], FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(string $toEmail, string $toName, string $subject, string $html, string $text): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Gmail SMTP is not fully configured.');
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Recipient email is invalid.');
        }

        $host = (string) $this->config['host'];
        $port = (int) $this->config['port'];
        $encryption = (string) $this->config['encryption'];
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client(
            $remote,
            $errorCode,
            $errorMessage,
            (float) $this->config['timeout'],
            STREAM_CLIENT_CONNECT
        );
        if (!is_resource($socket)) {
            throw new RuntimeException('Unable to connect to Gmail SMTP: ' . ($errorMessage ?: (string) $errorCode));
        }

        stream_set_timeout($socket, (int) $this->config['timeout']);
        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO ' . $this->clientHostname(), [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to enable TLS for Gmail SMTP.');
                }
                $this->command($socket, 'EHLO ' . $this->clientHostname(), [250]);
            }

            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode((string) $this->config['username']), [334]);
            $this->command($socket, base64_encode((string) $this->config['password']), [235]);
            $this->command($socket, 'MAIL FROM:<' . $this->config['from_address'] . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            fwrite($socket, $this->buildMessage($toEmail, $toName, $subject, $html, $text) . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function buildMessage(string $toEmail, string $toName, string $subject, string $html, string $text): string
    {
        $boundary = '=_thanai_khu_dee_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->clientHostname() . '>',
            'From: ' . $this->mailbox((string) $this->config['from_address'], (string) $this->config['from_name']),
            'To: ' . $this->mailbox($toEmail, $toName),
            'Subject: ' . $this->encodedHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($text))),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($html))),
            '--' . $boundary . '--',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $body);
    }

    private function mailbox(string $email, string $name): string
    {
        return $name === '' ? '<' . $email . '>' : $this->encodedHeader($name) . ' <' . $email . '>';
    }

    private function encodedHeader(string $value): string
    {
        $clean = trim(str_replace(["\r", "\n"], '', $value));
        return preg_match('/[^\x20-\x7E]/', $clean)
            ? '=?UTF-8?B?' . base64_encode($clean) . '?='
            : $clean;
    }

    private function clientHostname(): string
    {
        $host = gethostname() ?: 'localhost';
        return preg_replace('/[^a-zA-Z0-9.-]/', '', $host) ?: 'localhost';
    }

    private function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCodes);
    }

    private function expect($socket, array $expectedCodes): string
    {
        $response = '';
        do {
            $line = fgets($socket, 1024);
            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                throw new RuntimeException(!empty($meta['timed_out']) ? 'Gmail SMTP timed out.' : 'Gmail SMTP closed the connection.');
            }
            $response .= $line;
        } while (strlen($line) >= 4 && $line[3] === '-');

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Gmail SMTP returned ' . $code . ': ' . trim($response));
        }

        return $response;
    }
}
