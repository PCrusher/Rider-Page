<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_FAILS = 8;
    private const WINDOW = 900;

    public static function user(): ?array
    {
        rp_session();
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        static $cache = [];
        return $cache[$id] ??= Db::row('SELECT id, email FROM users WHERE id = ?', [(int)$id]);
    }

    public static function gate(): array
    {
        $u = self::user();
        if ($u === null) {
            redirect('/admin/login');
        }
        return $u;
    }

    /** @return true|string true on success, otherwise a message for the form */
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
        self::login((int)$row['id']);
        return true;
    }

    public static function login(int $id): void
    {
        rp_session();
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
    }

    public static function logout(): void
    {
        rp_session();
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
}
