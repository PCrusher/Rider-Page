<?php
declare(strict_types=1);

/** Two-factor login: turning it on and off, checking codes, recovery codes. */
final class TwoFactor
{
    private const CODES = 10;

    public static function enabled(array $user): bool
    {
        return (int)($user['totp_enabled'] ?? 0) === 1 && (string)($user['totp_secret'] ?? '') !== '';
    }

    /** Switch on with a secret that the user already proved to work. @return string[] the recovery codes, shown once */
    public static function enable(int $uid, string $secret, int $step): array
    {
        Db::update('users', ['totp_secret' => Crypto::seal($secret), 'totp_enabled' => 1, 'totp_step' => $step], 'id = ?', [$uid]);
        return self::newCodes($uid);
    }

    public static function disable(int $uid): void
    {
        Db::update('users', ['totp_secret' => null, 'totp_enabled' => 0, 'totp_step' => 0], 'id = ?', [$uid]);
        Db::delete('recovery_codes', 'user_id = ?', [$uid]);
    }

    private static function normalise(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    /** Replace the recovery codes with ten new ones. @return string[] */
    public static function newCodes(int $uid): array
    {
        Db::delete('recovery_codes', 'user_id = ?', [$uid]);
        $codes = [];
        for ($i = 0; $i < self::CODES; $i++) {
            $raw = substr(Totp::encode(random_bytes(7)), 0, 10);
            Db::insert('recovery_codes', ['user_id' => $uid, 'code_hash' => hash('sha256', $raw)]);
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        }
        return $codes;
    }

    public static function codesLeft(int $uid): int
    {
        return (int)Db::val('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ? AND used = 0', [$uid]);
    }

    /**
     * Is this an app code (or, if allowed, an unused recovery code)? Each code works once.
     * Using a recovery code sends the user an email.
     */
    public static function check(int $uid, string $input, bool $allowRecovery = true): bool
    {
        $u = Users::find($uid);
        if (!$u || !self::enabled($u)) {
            return false;
        }
        $secret = Crypto::open((string)$u['totp_secret']);
        if ($secret !== null && ($step = Totp::verify($secret, $input, (int)$u['totp_step'])) !== null) {
            // Only the first request with this step wins, even if two arrive at once.
            return Db::run('UPDATE users SET totp_step = ? WHERE id = ? AND totp_step < ?', [$step, $uid, $step])->rowCount() === 1;
        }
        $raw = self::normalise($input);
        if ($allowRecovery && strlen($raw) === 10
            && Db::run('UPDATE recovery_codes SET used = ? WHERE user_id = ? AND code_hash = ? AND used = 0', [time(), $uid, hash('sha256', $raw)])->rowCount() === 1) {
            Notify::event($uid, 'recovery_used');
            return true;
        }
        return false;
    }
}
