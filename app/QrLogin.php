<?php
declare(strict_types=1);

/**
 * Log in on a phone by scanning a QR code that a logged-in computer shows.
 *
 *  1. On the Account page the user (password confirmed) asks for a code. It is valid for 20 seconds.
 *  2. The phone scans it and opens the address. The page names the account and asks for one tap.
 *     (A tap is needed so that link previews or scanners can not use up the code, and so nobody can
 *     push a stranger's code onto someone else's phone silently.)
 *  3. After the tap the phone is logged in. The code works once.
 */
final class QrLogin
{
    /** Seconds the QR code can be scanned. */
    public const TTL = 20;
    /** Seconds the phone has to tap the button after the code was opened. */
    public const CONFIRM = 30;
    private const PER_USER_PER_10_MIN = 5;

    private static function byToken(string $token): ?array
    {
        return preg_match('/^[a-f0-9]{64}$/', $token)
            ? Db::row('SELECT * FROM qr_tokens WHERE token_hash = ?', [hash('sha256', $token)])
            : null;
    }

    /** @return ?array{id:int,url:string,ttl:int} null when QR login is off for the user or too many codes were asked for */
    public static function create(int $userId): ?array
    {
        Db::delete('qr_tokens', 'created < ?', [time() - 3600]);
        if ((int)Db::val('SELECT qr_enabled FROM users WHERE id = ?', [$userId]) !== 1) {
            return null;
        }
        if ((int)Db::val('SELECT COUNT(*) FROM qr_tokens WHERE user_id = ? AND created >= ?', [$userId, time() - 600]) >= self::PER_USER_PER_10_MIN) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $id = Db::insert('qr_tokens', ['token_hash' => hash('sha256', $token), 'user_id' => $userId, 'created' => time(), 'ip' => client_ip()]);
        return ['id' => $id, 'url' => site_origin() . '/admin/qr/login?t=' . $token, 'ttl' => self::TTL];
    }

    private static function valid(array $row): bool
    {
        if ((int)$row['used'] > 0) {
            return false;
        }
        return (int)$row['opened'] > 0
            ? time() - (int)$row['opened'] <= self::CONFIRM
            : time() - (int)$row['created'] <= self::TTL;
    }

    /** The phone opened the address. Returns the code while it can still be used. */
    public static function open(string $token): ?array
    {
        $row = self::byToken($token);
        if (!$row || !self::valid($row) || (int)Db::val('SELECT qr_enabled FROM users WHERE id = ?', [(int)$row['user_id']]) !== 1) {
            return null;
        }
        if ((int)$row['opened'] === 0) {
            Db::update('qr_tokens', ['opened' => time()], 'id = ?', [(int)$row['id']]);
            $row['opened'] = time();
        }
        return $row;
    }

    /** The phone tapped the button. Works once. @return ?int the user to log in */
    public static function consume(string $token): ?int
    {
        $row = self::byToken($token);
        if (!$row || !self::valid($row)) {
            return null;
        }
        $n = Db::run('UPDATE qr_tokens SET used = ? WHERE id = ? AND used = 0', [time(), (int)$row['id']])->rowCount();
        if ($n !== 1) {
            return null;
        }
        $user = Users::find((int)$row['user_id']);
        return $user && (int)($user['qr_enabled'] ?? 0) === 1 ? (int)$user['id'] : null;
    }

    /** What the computer shows while it waits: waiting, opened (phone is looking at it), used or expired. */
    public static function state(int $id, int $userId): string
    {
        $row = Db::row('SELECT * FROM qr_tokens WHERE id = ? AND user_id = ?', [$id, $userId]);
        if (!$row) {
            return 'expired';
        }
        if ((int)$row['used'] > 0) {
            return 'used';
        }
        if (!self::valid($row)) {
            return 'expired';
        }
        return (int)$row['opened'] > 0 ? 'opened' : 'waiting';
    }

    /** Remove all open codes of a user (password change, user removed, QR login switched off). */
    public static function revokeAll(int $userId): void
    {
        try {
            Db::delete('qr_tokens', 'user_id = ?', [$userId]);
        } catch (Throwable) {
        }
    }
}
