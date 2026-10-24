<?php
declare(strict_types=1);

/** Emails about what happens to an account, so that a stranger's login does not go unnoticed. */
final class Notify
{
    private static function who(): string
    {
        return Auth::device((string)($_SERVER['HTTP_USER_AGENT'] ?? '')) . ', IP address ' . client_ip() . ', ' . date('j F Y, H:i');
    }

    private static function site(): string
    {
        return (string)(Settings::get('name', '') ?: 'Rider Page');
    }

    public static function send(array $user, string $subject, string $text): void
    {
        $to = trim((string)($user['email'] ?? ''));
        if ($to === '') {
            return;
        }
        $body = $text . "\n\n--\n" . self::site() . "\n" . site_origin() . "/admin\n";
        [$ok, $err] = Mailer::send($to, '[' . self::site() . '] ' . $subject, $body);
        if (!$ok) {
            error_log('Security email could not be sent: ' . $err);
        }
    }

    /** Called after every successful login. Never blocks the login. */
    public static function login(int $uid, string $method): void
    {
        try {
            $u = Users::find($uid);
            if (!$u) {
                return;
            }
            $fp = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $known = (int)Db::val('SELECT COUNT(*) FROM login_devices WHERE user_id = ?', [$uid]);
            $seen = Db::row('SELECT id FROM login_devices WHERE user_id = ? AND fp = ?', [$uid, $fp]);
            if ($seen) {
                Db::update('login_devices', ['last_seen' => now()], 'id = ?', [(int)$seen['id']]);
            } else {
                Db::insert('login_devices', ['user_id' => $uid, 'fp' => $fp, 'first_seen' => now(), 'last_seen' => now()]);
            }
            Db::delete('login_devices', 'last_seen < ?', [date('Y-m-d H:i:s', time() - 180 * 86400)]);

            $trouble = "If this was not you, open Admin > Account, log out all other devices and change your password.";
            if ($method === 'qr') {
                self::send($u, 'Login with a QR code', "Someone logged in to your admin account by scanning a QR code.\n\n" . self::who() . "\n\n" . $trouble);
            } elseif (!$seen && $known > 0 && (int)($u['notify'] ?? 1) === 1) {
                self::send($u, 'Login from a new device', "Your admin account was used to log in from a device we have not seen before.\n\n" . self::who() . "\n\n" . $trouble);
            }
        } catch (Throwable) {
        }
    }

    /** Security changes. These are always sent. */
    public static function event(int $uid, string $event): void
    {
        try {
            $u = Users::find($uid);
            if (!$u) {
                return;
            }
            $text = [
                'password_changed' => ['Your password was changed', 'The password of your admin account was changed. All other devices were logged out.'],
                'password_reset' => ['Your password was reset', 'The password of your admin account was reset with the "Forgot password" link. All devices were logged out.'],
                '2fa_on' => ['Two-factor login is on', 'Two-factor login was switched on for your admin account.'],
                '2fa_off' => ['Two-factor login is off', 'Two-factor login was switched off for your admin account.'],
                '2fa_reset' => ['Two-factor login was reset', 'An admin switched off two-factor login for your account. You can switch it on again under Account.'],
                'recovery_used' => ['A recovery code was used', 'A one-time recovery code of your admin account was used to log in. Check how many you have left under Account.'],
                'recovery_mode' => ['The password was reset from the server', 'The password of your admin account was reset with the recovery file on the server, and two-factor login was switched off.'],
            ][$event] ?? null;
            if ($text) {
                self::send($u, $text[0], $text[1] . "\n\n" . self::who() . "\n\nIf this was not you, contact the other admins of the site and change your password at once.");
            }
        } catch (Throwable) {
        }
    }
}
