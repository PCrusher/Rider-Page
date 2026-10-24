<?php
declare(strict_types=1);

/**
 * Emergency way back in without email: create the file storage/RESET_ADMIN (by FTP) that contains a
 * secret phrase of at least 10 characters. For 15 minutes the page /admin/recover accepts the phrase,
 * the email address of an account and a new password. Two-factor login of that account is switched off.
 */
final class Recovery
{
    private const TTL = 900;
    private const MAX_FAILS = 8;

    public static function file(): string
    {
        return RP_STORAGE . '/RESET_ADMIN';
    }

    /** The phrase in the file, while the file is fresh and long enough. Old files are removed. */
    private static function phrase(): ?string
    {
        $f = self::file();
        if (!is_file($f)) {
            return null;
        }
        if (time() - (int)filemtime($f) > self::TTL) {
            @unlink($f);
            return null;
        }
        $p = trim((string)file_get_contents($f));
        return strlen($p) >= 10 ? $p : null;
    }

    public static function active(): bool
    {
        return self::phrase() !== null;
    }

    /** @return ?string an error message, or null when the password was reset */
    public static function run(string $phrase, string $email, string $password, string $ip): ?string
    {
        $expected = self::phrase();
        if ($expected === null) {
            return 'Recovery mode is not active. See the README for how to switch it on.';
        }
        Db::delete('login_attempts', 'ts < ?', [time() - 900]);
        if ((int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts >= ?', [$ip, time() - 900]) >= self::MAX_FAILS) {
            return 'Too many attempts. Wait 15 minutes.';
        }
        if (!hash_equals($expected, trim($phrase))) {
            Db::insert('login_attempts', ['ip' => $ip, 'ts' => time()]);
            return 'The phrase is not the one in the file.';
        }
        if (strlen($password) < 10) {
            return 'The new password needs at least 10 characters.';
        }
        $u = Db::row('SELECT id FROM users WHERE email = ?', [strtolower(trim($email))]);
        if (!$u) {
            return 'No account has this email address.';
        }
        $uid = (int)$u['id'];
        Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change' => 0], 'id = ?', [$uid]);
        TwoFactor::disable($uid);
        Auth::revokeAll($uid);
        QrLogin::revokeAll($uid);
        @unlink(self::file());
        Notify::event($uid, 'recovery_mode');
        return null;
    }
}
