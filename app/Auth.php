<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_FAILS = 8;
    private const WINDOW = 900;
    /** A login that was not used for this many days ends by itself. */
    private const IDLE_DAYS = 30;

    public static function user(): ?array
    {
        rp_session();
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        static $valid = [];
        if (!isset($valid[$id])) {
            $valid[$id] = self::sessionStillValid((int)$id);
        }
        if (!$valid[$id]) {
            // Ended from another device, or idle for too long.
            unset($_SESSION['uid'], $_SESSION['sid']);
            return null;
        }
        static $cache = [];
        if (!isset($cache[$id])) {
            // SELECT * on purpose: right after an update this runs before the new columns exist,
            // so missing fields fall back to the old behaviour (a full admin).
            $u = Db::row('SELECT * FROM users WHERE id = ?', [(int)$id]);
            if ($u) {
                $u['role'] = Roles::valid((string)($u['role'] ?? '')) ? $u['role'] : 'admin';
                $u['name'] = (string)($u['name'] ?? '');
                $u['must_change'] = (int)($u['must_change'] ?? 0);
                $u['qr_enabled'] = (int)($u['qr_enabled'] ?? 0);
                $u['notify'] = (int)($u['notify'] ?? 1);
                $u['totp_enabled'] = TwoFactor::enabled($u) ? 1 : 0;
                unset($u['password_hash'], $u['totp_secret']);
            }
            $cache[$id] = $u;
        }
        return $cache[$id];
    }

    public static function gate(): array
    {
        $u = self::user();
        if ($u === null) {
            redirect('/admin/login');
        }
        return $u;
    }

    /** @return true|string true on success, 'mfa' when a second step follows, otherwise a message for the form */
    public static function attempt(string $email, string $password): bool|string
    {
        $ip = client_ip();
        Db::delete('login_attempts', 'ts < ?', [time() - self::WINDOW]);
        $fails = (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts >= ?', [$ip, time() - self::WINDOW]);
        if ($fails >= self::MAX_FAILS) {
            return 'Too many failed attempts. Please wait 15 minutes and try again.';
        }
        $row = Db::row('SELECT id, password_hash FROM users WHERE email = ?', [strtolower(trim($email))]);
        $ok = $row && password_verify($password, (string)$row['password_hash']);
        if (!$ok) {
            Db::insert('login_attempts', ['ip' => $ip, 'ts' => time()]);
            return 'Email or password is not correct.';
        }
        Db::delete('login_attempts', 'ip = ?', [$ip]);
        if (password_needs_rehash((string)$row['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [(int)$row['id']]);
        }
        $uid = (int)$row['id'];
        $u = Users::find($uid);
        if ($u && TwoFactor::enabled($u)) {
            // The password was right. The account has two-factor login, so a code is needed as well.
            rp_session();
            $_SESSION['mfa_uid'] = $uid;
            $_SESSION['mfa_until'] = time() + 300;
            $_SESSION['mfa_tries'] = 0;
            return 'mfa';
        }
        self::login($uid);
        return true;
    }

    /** The user whose password was accepted and who still has to give a code (for five minutes). */
    public static function pendingMfa(): ?int
    {
        rp_session();
        $uid = (int)($_SESSION['mfa_uid'] ?? 0);
        return $uid > 0 && (int)($_SESSION['mfa_until'] ?? 0) > time() ? $uid : null;
    }

    /** @return true|string true when the code was right and the user is logged in */
    public static function completeMfa(string $code): bool|string
    {
        $uid = self::pendingMfa();
        if ($uid === null) {
            return 'Your login took too long. Please start again.';
        }
        $ip = client_ip();
        if ((int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts >= ?', [$ip, time() - self::WINDOW]) >= self::MAX_FAILS) {
            return 'Too many failed attempts. Please wait 15 minutes and try again.';
        }
        if (TwoFactor::check($uid, $code, true)) {
            unset($_SESSION['mfa_uid'], $_SESSION['mfa_until'], $_SESSION['mfa_tries']);
            self::login($uid);
            return true;
        }
        Db::insert('login_attempts', ['ip' => $ip, 'ts' => time()]);
        $_SESSION['mfa_tries'] = (int)($_SESSION['mfa_tries'] ?? 0) + 1;
        if ($_SESSION['mfa_tries'] >= 5) {
            unset($_SESSION['mfa_uid'], $_SESSION['mfa_until'], $_SESSION['mfa_tries']);
            return 'Too many wrong codes. Please log in again.';
        }
        return 'The code is not correct. Try again.';
    }

    public static function login(int $id, string $method = 'password'): void
    {
        rp_session();
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
        self::record($id, $method);
        Notify::login($id, $method);
    }

    public static function logout(): void
    {
        rp_session();
        if (!empty($_SESSION['sid'])) {
            try {
                Db::delete('login_sessions', 'token_hash = ?', [hash('sha256', (string)$_SESSION['sid'])]);
            } catch (Throwable) {
            }
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function create(string $email, string $password): int
    {
        return Db::insert('users', [
            'email' => strtolower(trim($email)),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => now(),
        ]);
    }

    /* ---------- where am I logged in ---------- */

    /** Remember this browser as a signed-in device. Never blocks a login if the table is missing. */
    private static function record(int $uid, string $method): void
    {
        try {
            $token = bin2hex(random_bytes(24));
            Db::delete('login_sessions', 'last_seen < ?', [date('Y-m-d H:i:s', time() - self::IDLE_DAYS * 86400)]);
            Db::insert('login_sessions', [
                'user_id' => $uid, 'token_hash' => hash('sha256', $token), 'ip' => client_ip(),
                'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'method' => $method, 'created_at' => now(), 'last_seen' => now(),
            ]);
            $_SESSION['sid'] = $token;
        } catch (Throwable) {
            unset($_SESSION['sid']);
        }
    }

    /**
     * False when this login was ended from another device or has been idle too long.
     * A login that started before this feature existed is adopted. If the table is missing
     * (right after an update, before the database change ran) the login simply stays valid.
     */
    private static function sessionStillValid(int $uid): bool
    {
        try {
            $token = (string)($_SESSION['sid'] ?? '');
            if ($token === '') {
                self::record($uid, 'password');
                return true;
            }
            $row = Db::row('SELECT id, user_id, last_seen FROM login_sessions WHERE token_hash = ?', [hash('sha256', $token)]);
            if (!$row || (int)$row['user_id'] !== $uid) {
                return false;
            }
            $seen = (int)strtotime((string)$row['last_seen']);
            if ($seen < time() - self::IDLE_DAYS * 86400) {
                Db::delete('login_sessions', 'id = ?', [(int)$row['id']]);
                return false;
            }
            if ($seen < time() - 60) {
                Db::update('login_sessions', [
                    'last_seen' => now(), 'ip' => client_ip(),
                    'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                ], 'id = ?', [(int)$row['id']]);
            }
            return true;
        } catch (Throwable) {
            return true;
        }
    }

    /** @return array<int,array> the user's logged-in devices, newest activity first; 'current' marks this browser */
    public static function sessionsFor(int $uid): array
    {
        $mine = !empty($_SESSION['sid']) ? hash('sha256', (string)$_SESSION['sid']) : '';
        $rows = Db::rows('SELECT * FROM login_sessions WHERE user_id = ? ORDER BY last_seen DESC, id DESC', [$uid]);
        foreach ($rows as &$r) {
            $r['current'] = $mine !== '' && hash_equals((string)$r['token_hash'], $mine);
            $r['device'] = self::device((string)$r['ua']);
        }
        unset($r);
        return $rows;
    }

    public static function revoke(int $uid, int $sessionId): void
    {
        Db::delete('login_sessions', 'id = ? AND user_id = ?', [$sessionId, $uid]);
    }

    /** End every login of the user except the one in this browser. */
    public static function revokeOthers(int $uid): void
    {
        $mine = !empty($_SESSION['sid']) ? hash('sha256', (string)$_SESSION['sid']) : '';
        Db::delete('login_sessions', 'user_id = ? AND token_hash <> ?', [$uid, $mine]);
    }

    /** End all logins of a user (used when an admin changes or removes that user). */
    public static function revokeAll(int $uid): void
    {
        try {
            Db::delete('login_sessions', 'user_id = ?', [$uid]);
        } catch (Throwable) {
        }
    }

    /** "Safari on iPhone" from a browser's user-agent text. */
    public static function device(string $ua): string
    {
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh'), str_contains($ua, 'Mac OS X') => 'Mac',
            str_contains($ua, 'CrOS') => 'Chromebook',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/'), str_contains($ua, 'EdgA/'), str_contains($ua, 'EdgiOS/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/'), str_contains($ua, 'FxiOS/') => 'Firefox',
            str_contains($ua, 'Chrome/'), str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => '',
        };
        if ($browser === '' && $os === '') {
            return 'Unknown device';
        }
        return ($browser !== '' ? $browser : 'Browser') . ($os !== '' ? ' on ' . $os : '');
    }
}
