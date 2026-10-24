<?php
declare(strict_types=1);

/** "Forgot password": a link by email that works once for 60 minutes. */
final class PasswordReset
{
    public const TTL = 3600;
    private const PER_USER = 3;
    private const PER_IP = 5;

    /** Always returns quietly, so the page can give the same answer whether the address exists or not. */
    public static function request(string $email, string $ip): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            Db::delete('password_resets', 'created < ?', [time() - 86400]);
            $since = time() - 3600;
            if ((int)Db::val('SELECT COUNT(*) FROM password_resets WHERE ip = ? AND created >= ?', [$ip, $since]) >= self::PER_IP) {
                return;
            }
            $user = Db::row('SELECT * FROM users WHERE email = ?', [$email]);
            $token = bin2hex(random_bytes(32));
            if (!$user) {
                // Counted like a real request, so unknown addresses can not be probed without limit.
                Db::insert('password_resets', ['user_id' => 0, 'token_hash' => hash('sha256', $token), 'created' => time(), 'used' => 1, 'ip' => $ip]);
                return;
            }
            if ((int)Db::val('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created >= ?', [(int)$user['id'], $since]) >= self::PER_USER) {
                return;
            }
            Db::insert('password_resets', ['user_id' => (int)$user['id'], 'token_hash' => hash('sha256', $token), 'created' => time(), 'ip' => $ip]);
            $site = (string)(Settings::get('name', '') ?: 'Rider Page');
            [$ok, $err] = Mailer::send(
                $email,
                '[' . $site . '] Reset your password',
                "Someone asked to reset the password of your admin account.\n\nOpen this link within 60 minutes to choose a new password:\n"
                . site_origin() . '/admin/reset?t=' . $token . "\n\nIf you did not ask for this, ignore this email. Your password stays as it is.\n\n--\n" . $site . "\n"
            );
            if (!$ok) {
                error_log('Password reset email could not be sent: ' . $err);
            }
        } catch (Throwable $e) {
            error_log('Password reset failed: ' . $e->getMessage());
        }
    }

    public static function find(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = Db::row('SELECT * FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)]);
        return $row && (int)$row['used'] === 0 && (int)$row['user_id'] > 0 && time() - (int)$row['created'] <= self::TTL ? $row : null;
    }

    /** @return ?string an error message, or null when the password was changed */
    public static function finish(string $token, string $password): ?string
    {
        if (strlen($password) < 10) {
            return 'The new password needs at least 10 characters.';
        }
        $row = self::find($token);
        if (!$row || Db::run('UPDATE password_resets SET used = ? WHERE id = ? AND used = 0', [time(), (int)$row['id']])->rowCount() !== 1) {
            return 'This link is not valid any more. Ask for a new one.';
        }
        $uid = (int)$row['user_id'];
        Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change' => 0], 'id = ?', [$uid]);
        Auth::revokeAll($uid);
        QrLogin::revokeAll($uid);
        Notify::event($uid, 'password_reset');
        return null;
    }
}
