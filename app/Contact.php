<?php
declare(strict_types=1);

/** The contact form: checks a submission, stores it and emails it to the site owner. */
final class Contact
{
    private const MIN_SECONDS = 3;
    private const MAX_SECONDS = 7200;
    private const PER_IP_PER_HOUR = 5;
    private const ALL_PER_HOUR = 40;
    /** A message with this score or more goes to the Spam tab. */
    public const SPAM_SCORE = 50;
    /** The browser must find a number whose hash starts with this many zeros (invisible proof of work). */
    public const POW_ZEROS = 4;

    private const SEO_WORDS = [
        'seo', 'search engine', 'google ranking', 'first page', 'page one', 'backlink', 'link building', 'organic traffic',
        'website traffic', 'website audit', 'site audit', 'web design', 'web development', 'digital marketing', 'guest post',
        'load time', 'loading speed', 'page speed', 'free quote', 'free audit', 'our team', 'our agency', 'your website',
        'your site', 'rank higher', 'increase your sales', 'more customers', 'lead generation', 'social media marketing',
        'crypto', 'casino', 'forex', 'bitcoin', 'loan', 'viagra', 'whatsapp', 'telegram',
    ];
    private const DISPOSABLE = [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'temp-mail.org', '10minutemail.com', 'yopmail.com', 'trashmail.com',
        'sharklasers.com', 'getnada.com', 'throwawaymail.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com', 'mailnesia.com',
    ];

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
            || (int)Db::val('SELECT COUNT(*) FROM messages WHERE spam = 0 AND created_at >= ?', [$since]) >= self::ALL_PER_HOUR) {
            return $fail('rate');
        }

        [$score, $reasons] = self::score($values, trim((string)($in['pw'] ?? '')), (string)($in['s'] ?? ''));
        $spam = $score >= self::SPAM_SCORE;
        $id = Db::insert('messages', [
            'name' => $values['name'], 'email' => $values['email'], 'body' => $values['message'],
            'ip_hash' => $hash, 'created_at' => now(),
            'spam' => $spam ? 1 : 0, 'spam_score' => $score, 'spam_reasons' => mb_substr(implode(', ', $reasons), 0, 250),
            'mail_status' => $spam ? 'held' : 'pending',
        ]);
        if ($spam) {
            // Kept in the Spam tab, not emailed. The visitor sees the normal thank-you so bots do not adapt.
            Db::run('DELETE FROM messages WHERE spam = 1 AND created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
        } else {
            self::deliver((int)$id);
        }

        return ['ok' => true, 'error' => '', 'values' => ['name' => '', 'email' => '', 'message' => '']];
    }

    /** True when the number found by the browser solves the puzzle for this form token. */
    public static function powOk(string $token, string $nonce): bool
    {
        if ($nonce === '' || !preg_match('/^[0-9]{1,12}$/', $nonce) || $token === '') {
            return false;
        }
        return str_starts_with(hash('sha256', $token . ':' . $nonce), str_repeat('0', self::POW_ZEROS));
    }

    /** Spam points and the reasons for them. @return array{0:int,1:string[]} */
    public static function score(array $v, string $pow, string $token): array
    {
        $score = 0;
        $why = [];
        $add = static function (int $n, string $reason) use (&$score, &$why): void {
            $score += $n;
            $why[] = $reason . ' (+' . $n . ')';
        };
        $msg = mb_strtolower($v['message']);

        if (!self::powOk($token, $pow)) {
            $add(35, 'form not checked by the browser');
        }
        $hits = [];
        foreach (self::SEO_WORDS as $w) {
            if (preg_match('/(?<![a-z0-9])' . preg_quote($w, '/') . '(?![a-z0-9])/u', $msg)) {
                $hits[] = $w;
            }
        }
        if ($hits) {
            $add(min(60, 20 * count($hits)), 'sales wording: ' . implode(', ', array_slice($hits, 0, 4)));
        }
        $host = (string)preg_replace('/^www\./', '', (string)parse_url(site_origin(), PHP_URL_HOST));
        if ($host !== '' && $host !== 'localhost' && str_contains($msg, mb_strtolower($host))) {
            $add(25, 'names this website');
        }
        $links = preg_match_all('~https?://|www\.~i', $v['message']);
        if ($links > 0) {
            $add(min(40, 15 * $links), $links . ' link' . ($links > 1 ? 's' : ''));
        }
        if (preg_match('~https?://|www\.|@~i', $v['name'])) {
            $add(30, 'link or address in the name');
        }
        $domain = mb_strtolower((string)substr((string)strrchr($v['email'], '@'), 1));
        if (in_array($domain, self::DISPOSABLE, true)) {
            $add(40, 'throwaway email address');
        }
        try {
            $same = (int)Db::val('SELECT COUNT(*) FROM messages WHERE body = ? AND email <> ? AND created_at >= ?',
                [$v['message'], $v['email'], date('Y-m-d H:i:s', time() - 14 * 86400)]);
            if ($same > 0) {
                $add(40, 'same text sent by another address');
            }
        } catch (Throwable) {
        }
        return [$score, $why];
    }

    /** Emails a stored message to the owner and records the result. */
    public static function deliver(int $id): void
    {
        $m = Db::row('SELECT * FROM messages WHERE id = ?', [$id]);
        if (!$m) {
            return;
        }
        $site = (string)Settings::get('name', 'your site');
        $to = trim((string)Settings::get('email', ''));
        $text = $m['name'] . ' <' . $m['email'] . ">\n\n" . $m['body']
            . "\n\n--\nSent with the contact form on " . site_origin() . "/\nAlso saved in Admin > Messages.\n";
        [$sent, $err] = $to === ''
            ? [false, 'No contact email is set in your profile.']
            : Mailer::send($to, 'New message from ' . $m['name'] . ' via ' . $site, $text, (string)$m['email'], (string)$m['name']);
        Db::update('messages', ['mail_status' => $sent ? 'sent' : 'failed', 'mail_error' => mb_substr($err, 0, 250)], 'id = ?', [$id]);
    }
}
