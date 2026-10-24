<?php
declare(strict_types=1);

/** The contact form: checks a submission, stores it and emails it to the site owner. */
final class Contact
{
    private const MIN_SECONDS = 3;
    private const MAX_SECONDS = 7200;
    private const PER_IP_PER_HOUR = 5;
    private const ALL_PER_HOUR = 40;

    private static function secret(): string
    {
        return (string)((rp_config()['secret'] ?? '') ?: 'rider-page');
    }

    /** Hidden form fields: the time the form was shown, signed so it can not be forged. */
    public static function token(?int $ts = null): array
    {
        $ts ??= time();
        return [(string)$ts, hash_hmac('sha256', (string)$ts, self::secret())];
    }

    /**
     * @param array $in the submitted form
     * @return array{ok:bool,error:string,values:array} error is a language key suffix: invalid, expired, spam, rate
     */
    public static function submit(array $in, string $ip): array
    {
        $values = [
            'name' => mb_substr(trim((string)preg_replace('/[\x00-\x1f\x7f]+/', ' ', (string)($in['name'] ?? ''))), 0, 100),
            'email' => trim((string)($in['email'] ?? '')),
            'message' => trim(str_replace("\r\n", "\n", (string)($in['message'] ?? ''))),
        ];
        $fail = static fn(string $e): array => ['ok' => false, 'error' => $e, 'values' => $values];

        // Bots fill in the hidden field. Pretend it worked so they do not adapt.
        if (trim((string)($in['website'] ?? '')) !== '') {
            return ['ok' => true, 'error' => '', 'values' => ['name' => '', 'email' => '', 'message' => '']];
        }

        $ts = (int)($in['t'] ?? 0);
        $age = time() - $ts;
        if (!hash_equals(self::token($ts)[1], (string)($in['s'] ?? '')) || $age < self::MIN_SECONDS || $age > self::MAX_SECONDS) {
            return $fail('expired');
        }
        if ($values['name'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190
            || mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 5000) {
            return $fail('invalid');
        }
        if (preg_match_all('~https?://~i', $values['message']) > 2) {
            return $fail('spam');
        }

        $hash = substr(hash('sha256', $ip . self::secret()), 0, 32);
        $since = date('Y-m-d H:i:s', time() - 3600);
        if ((int)Db::val('SELECT COUNT(*) FROM messages WHERE ip_hash = ? AND created_at >= ?', [$hash, $since]) >= self::PER_IP_PER_HOUR
            || (int)Db::val('SELECT COUNT(*) FROM messages WHERE created_at >= ?', [$since]) >= self::ALL_PER_HOUR) {
            return $fail('rate');
        }

        $id = Db::insert('messages', [
            'name' => $values['name'], 'email' => $values['email'], 'body' => $values['message'],
            'ip_hash' => $hash, 'created_at' => now(),
        ]);

        $site = (string)Settings::get('name', 'your site');
        $to = trim((string)Settings::get('email', ''));
        $text = $values['name'] . ' <' . $values['email'] . ">\n\n" . $values['message']
            . "\n\n--\nSent with the contact form on " . site_origin() . "/\nAlso saved in Admin > Messages.\n";
        [$sent, $err] = $to === ''
            ? [false, 'No contact email is set in your profile.']
            : Mailer::send($to, 'New message from ' . $values['name'] . ' via ' . $site, $text, $values['email'], $values['name']);
        Db::update('messages', ['mail_status' => $sent ? 'sent' : 'failed', 'mail_error' => mb_substr($err, 0, 250)], 'id = ?', [$id]);

        return ['ok' => true, 'error' => '', 'values' => ['name' => '', 'email' => '', 'message' => '']];
    }
}
