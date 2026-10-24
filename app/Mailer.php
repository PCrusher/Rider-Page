<?php
declare(strict_types=1);

/**
 * Sends plain-text email. Uses SMTP when a server is set in Admin -> Email, otherwise PHP's mail().
 * SMTP is the reliable choice: many hosts drop or spam-filter mail() messages.
 */
final class Mailer
{
    /** For tests: a callable that receives the mail as an array and returns [bool, string]. */
    public static $transport = null;

    /** @return array{0:string,1:string} address and display name of the sender */
    public static function from(): array
    {
        $addr = trim((string)Settings::get('mail_from', ''));
        if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            $host = (string)parse_url(site_origin(), PHP_URL_HOST);
            $host = preg_replace('/^www\./i', '', $host ?: 'localhost');
            $addr = 'noreply@' . $host;
        }
        $name = trim((string)Settings::get('mail_from_name', '')) ?: (string)Settings::get('name', 'Rider Page');
        return [$addr, $name];
    }

    /** @return array{0:bool,1:string} success and an error message */
    public static function send(string $to, string $subject, string $body, string $replyTo = '', string $replyName = ''): array
    {
        $to = self::clean($to);
        [$from, $fromName] = self::from();
        $mail = ['to' => $to, 'subject' => self::clean($subject), 'body' => $body, 'reply_to' => self::clean($replyTo), 'from' => $from];
        if (self::$transport !== null) {
            return (self::$transport)($mail);
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [false, 'There is no valid recipient address.'];
        }

        $headers = [
            'From: ' . self::address($fromName, $from),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($mail['reply_to'] !== '' && filter_var($mail['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . self::address(self::clean($replyName), $mail['reply_to']);
        }
        $encodedSubject = '=?UTF-8?B?' . base64_encode($mail['subject']) . '?=';
        $encodedBody = chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n");

        $host = trim((string)Settings::get('smtp_host', ''));
        if ($host !== '') {
            $host_name = (string)parse_url(site_origin(), PHP_URL_HOST) ?: 'localhost';
            $data = 'Date: ' . date('r') . "\r\n"
                . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $host_name . ">\r\n"
                . implode("\r\n", $headers) . "\r\n"
                . 'To: ' . $to . "\r\n"
                . 'Subject: ' . $encodedSubject . "\r\n\r\n"
                . $encodedBody;
            return self::smtp($host, $from, $to, $data, $host_name);
        }

        $ok = @mail($to, $encodedSubject, $encodedBody, implode("\r\n", $headers));
        return $ok ? [true, ''] : [false, 'The server did not accept the message. Set up an SMTP server under Admin → Email.'];
    }

    private static function clean(string $s): string
    {
        return trim(str_replace(["\r", "\n", "\0"], ' ', $s));
    }

    private static function address(string $name, string $addr): string
    {
        return $name === '' ? $addr : '=?UTF-8?B?' . base64_encode($name) . '?= <' . $addr . '>';
    }

    /** Minimal SMTP client: STARTTLS or SSL, AUTH LOGIN, one recipient. */
    private static function smtp(string $host, string $from, string $to, string $data, string $helo): array
    {
        $port = (int)Settings::get('smtp_port', '587') ?: 587;
        $secure = (string)Settings::get('smtp_secure', 'tls');
        $user = (string)Settings::get('smtp_user', '');
        $pass = (string)Settings::get('smtp_pass', '');

        $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 12);
        if (!$fp) {
            return [false, 'Could not connect to ' . $host . ':' . $port . ' (' . $errstr . ')'];
        }
        stream_set_timeout($fp, 15);

        $read = static function () use ($fp): array {
            $text = '';
            $code = 0;
            while (($line = fgets($fp, 515)) !== false) {
                $text .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    $code = (int)substr($line, 0, 3);
                    break;
                }
            }
            return [$code, trim($text)];
        };
        $cmd = static function (string $c, array $ok) use ($fp, $read): array {
            fwrite($fp, $c . "\r\n");
            [$code, $text] = $read();
            return [in_array($code, $ok, true), $text];
        };
        $fail = static function (string $step, string $text) use ($fp): array {
            @fwrite($fp, "QUIT\r\n");
            @fclose($fp);
            return [false, $step . ' failed: ' . mb_substr($text, 0, 200)];
        };

        [$code, $text] = $read();
        if ($code !== 220) {
            return $fail('Connecting', $text);
        }
        [$ok, $text] = $cmd('EHLO ' . $helo, [250]);
        if (!$ok) {
            return $fail('Greeting', $text);
        }
        if ($secure === 'tls') {
            [$ok, $text] = $cmd('STARTTLS', [220]);
            if (!$ok || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return $fail('Starting encryption', $text);
            }
            [$ok, $text] = $cmd('EHLO ' . $helo, [250]);
            if (!$ok) {
                return $fail('Greeting', $text);
            }
        }
        if ($user !== '') {
            [$ok, $text] = $cmd('AUTH LOGIN', [334]);
            if ($ok) {
                [$ok, $text] = $cmd(base64_encode($user), [334]);
            }
            if ($ok) {
                [$ok, $text] = $cmd(base64_encode($pass), [235]);
            }
            if (!$ok) {
                return $fail('Login', $text);
            }
        }
        [$ok, $text] = $cmd('MAIL FROM:<' . $from . '>', [250]);
        if (!$ok) {
            return $fail('Sender', $text);
        }
        [$ok, $text] = $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        if (!$ok) {
            return $fail('Recipient', $text);
        }
        [$ok, $text] = $cmd('DATA', [354]);
        if (!$ok) {
            return $fail('Sending', $text);
        }
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $data)) ?? $data;
        fwrite($fp, str_replace("\n", "\r\n", $data) . "\r\n.\r\n");
        [$code, $text] = $read();
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
        return $code === 250 ? [true, ''] : [false, 'The server refused the message: ' . mb_substr($text, 0, 200)];
    }
}
