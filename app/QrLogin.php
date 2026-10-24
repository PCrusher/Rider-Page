<?php
declare(strict_types=1);

/**
 * Log in on a computer by scanning a QR code with a phone that is already logged in.
 *
 *  1. The computer asks for a request. It gets a QR token (goes into the code), a poll secret (stays
 *     in that page) and a 4-digit number that is shown on the computer's screen.
 *  2. The phone opens the address in the QR code, sees which device is asking and has to type the
 *     4-digit number. Only someone who sees the computer's screen knows it, which defeats the trick of
 *     showing a victim somebody else's QR code.
 *  3. The computer, still polling with its poll secret, then receives the login. Every step is single-use.
 */
final class QrLogin
{
    /** Seconds the QR code can be scanned. */
    public const TTL = 20;
    /** Seconds the phone has to confirm after the code was scanned. */
    public const CONFIRM = 45;
    /** Seconds the computer has to pick up an approved login. */
    public const CLAIM = 60;
    private const MAX_TRIES = 3;

    private static function sessionHash(): string
    {
        return hash('sha256', session_status() === PHP_SESSION_ACTIVE ? session_id() : '');
    }

    private static function byToken(string $token): ?array
    {
        return preg_match('/^[a-f0-9]{64}$/', $token)
            ? Db::row('SELECT * FROM qr_logins WHERE token_hash = ?', [hash('sha256', $token)])
            : null;
    }

    private static function byPoll(string $poll): ?array
    {
        return preg_match('/^[a-f0-9]{64}$/', $poll)
            ? Db::row('SELECT * FROM qr_logins WHERE poll_hash = ?', [hash('sha256', $poll)])
            : null;
    }

    /** @return ?array{token:string,poll:string,code:string,ttl:int,url:string} null when too many were requested */
    public static function create(): ?array
    {
        Db::delete('qr_logins', 'created < ?', [time() - 600]);
        if ((int)Db::val('SELECT COUNT(*) FROM qr_logins WHERE ip = ? AND created >= ?', [client_ip(), time() - 600]) >= 20) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $poll = bin2hex(random_bytes(32));
        $code = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        Db::insert('qr_logins', [
            'token_hash' => hash('sha256', $token), 'poll_hash' => hash('sha256', $poll), 'sess_hash' => self::sessionHash(),
            'code' => $code, 'created' => time(), 'ip' => client_ip(),
            'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
        return ['token' => $token, 'poll' => $poll, 'code' => $code, 'ttl' => self::TTL, 'url' => site_origin() . '/admin/qr/approve?t=' . $token];
    }

    /** Is this request still open for a phone? (pending, and scanned in time or confirmed in time) */
    private static function open(array $row): bool
    {
        if ($row['status'] !== 'pending' || (int)$row['tries'] >= self::MAX_TRIES) {
            return false;
        }
        return (int)$row['opened'] > 0
            ? time() - (int)$row['opened'] <= self::CONFIRM
            : time() - (int)$row['created'] <= self::TTL;
    }

    /** The phone opened the address from the QR code. Returns the request while it can still be approved. */
    public static function scan(string $token): ?array
    {
        $row = self::byToken($token);
        if (!$row || !self::open($row)) {
            return null;
        }
        if ((int)$row['opened'] === 0) {
            Db::update('qr_logins', ['opened' => time()], 'id = ?', [(int)$row['id']]);
            $row['opened'] = time();
        }
        return $row;
    }

    /** @return ?string an error message, or null when the login was approved */
    public static function approve(string $token, int $userId, string $code): ?string
    {
        $row = self::byToken($token);
        if (!$row || !self::open($row)) {
            return 'This code is no longer valid. Show a new code on the other device and scan it again.';
        }
        if ((int)Db::val('SELECT qr_enabled FROM users WHERE id = ?', [$userId]) !== 1) {
            return 'QR login is switched off for your account. Switch it on under Account first.';
        }
        if (!hash_equals((string)$row['code'], trim($code))) {
            $tries = (int)$row['tries'] + 1;
            Db::update('qr_logins', ['tries' => $tries, 'status' => $tries >= self::MAX_TRIES ? 'failed' : 'pending'], 'id = ?', [(int)$row['id']]);
            return $tries >= self::MAX_TRIES
                ? 'The number was wrong three times. This request was cancelled. Show a new code on the other device.'
                : 'The number does not match the one on the other screen. Try again.';
        }
        Db::update('qr_logins', ['status' => 'approved', 'user_id' => $userId, 'approved' => time()], 'id = ?', [(int)$row['id']]);
        return null;
    }

    public static function deny(string $token): void
    {
        $row = self::byToken($token);
        if ($row && $row['status'] === 'pending') {
            Db::update('qr_logins', ['status' => 'denied'], 'id = ?', [(int)$row['id']]);
        }
    }

    /** @return array{state:string,left:int} state is pending, scanned, approved, expired, denied, failed or used */
    public static function state(string $poll): array
    {
        $row = self::byPoll($poll);
        if (!$row) {
            return ['state' => 'expired', 'left' => 0];
        }
        $now = time();
        switch ($row['status']) {
            case 'approved':
                $left = (int)$row['approved'] + self::CLAIM - $now;
                return $left > 0 ? ['state' => 'approved', 'left' => $left] : ['state' => 'expired', 'left' => 0];
            case 'denied':
            case 'failed':
            case 'used':
                return ['state' => (string)$row['status'], 'left' => 0];
        }
        if ((int)$row['opened'] > 0) {
            $left = (int)$row['opened'] + self::CONFIRM - $now;
            return $left > 0 ? ['state' => 'scanned', 'left' => $left] : ['state' => 'expired', 'left' => 0];
        }
        $left = (int)$row['created'] + self::TTL - $now;
        return $left > 0 ? ['state' => 'pending', 'left' => $left] : ['state' => 'expired', 'left' => 0];
    }

    /** The computer picks up an approved login. Works once, and only from the browser that asked. */
    public static function claim(string $poll): ?int
    {
        $row = self::byPoll($poll);
        if (!$row || $row['status'] !== 'approved' || time() - (int)$row['approved'] > self::CLAIM) {
            return null;
        }
        if (!hash_equals((string)$row['sess_hash'], self::sessionHash())) {
            return null;
        }
        $n = Db::run("UPDATE qr_logins SET status = 'used' WHERE id = ? AND status = 'approved'", [(int)$row['id']])->rowCount();
        if ($n !== 1 || !Users::find((int)$row['user_id'])) {
            return null;
        }
        return (int)$row['user_id'];
    }

    /** What the phone shows about the device that is asking. */
    public static function describe(array $row): array
    {
        return ['device' => Auth::device((string)$row['ua']), 'ip' => (string)$row['ip'], 'time' => (int)$row['created']];
    }
}
