<?php
declare(strict_types=1);

/** Creating, changing and removing admin-area users. */
final class Users
{
    public static function all(): array
    {
        return Db::rows('SELECT * FROM users ORDER BY id');
    }

    public static function find(int $id): ?array
    {
        return Db::row('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function adminCount(int $exceptId = 0): int
    {
        return (int)Db::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id <> ?", [$exceptId]);
    }

    /**
     * @param array{email:string,name:string,role:string,password:string} $in
     * @return string[] error messages, empty when the input is fine
     */
    public static function validate(array $in, int $id): array
    {
        $e = [];
        $email = strtolower(trim($in['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $e[] = 'Enter a valid email address.';
        } elseif (Db::val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $id]) > 0) {
            $e[] = 'Another user already has this email address.';
        }
        if (!Roles::valid($in['role'])) {
            $e[] = 'Choose a level for this user.';
        }
        if (mb_strlen(trim($in['name'])) > 190) {
            $e[] = 'The name is too long.';
        }
        if ($id === 0 && $in['password'] === '') {
            $e[] = 'Choose a first password for the new user.';
        }
        if ($in['password'] !== '' && strlen($in['password']) < 10) {
            $e[] = 'The password needs at least 10 characters.';
        }
        if ($id > 0 && Roles::valid($in['role']) && $in['role'] !== 'admin' && self::adminCount($id) === 0) {
            $e[] = 'There must always be at least one admin.';
        }
        return $e;
    }

    public static function save(array $in, int $id): int
    {
        $data = [
            'email' => strtolower(trim($in['email'])),
            'name' => trim($in['name']),
            'role' => $in['role'],
            'must_change' => !empty($in['must_change']) ? 1 : 0,
        ];
        if ($in['password'] !== '') {
            $data['password_hash'] = password_hash($in['password'], PASSWORD_DEFAULT);
        }
        if ($id > 0) {
            Db::update('users', $data, 'id = ?', [$id]);
            if ($in['password'] !== '') {
                // A new password ends the user's other logins; the browser that is changing it stays in.
                $id === (int)($_SESSION['uid'] ?? 0) ? Auth::revokeOthers($id) : Auth::revokeAll($id);
            }
            return $id;
        }
        $data['created_at'] = now();
        return Db::insert('users', $data);
    }

    /** @return ?string an error message, or null when the user was removed */
    public static function delete(int $id, int $actingId): ?string
    {
        $u = self::find($id);
        if (!$u) {
            return 'This user does not exist.';
        }
        if ($id === $actingId) {
            return 'You can not delete your own account.';
        }
        if ($u['role'] === 'admin' && self::adminCount($id) === 0) {
            return 'There must always be at least one admin.';
        }
        // The posts stay; they now belong to the person who removed the user.
        Db::update('posts', ['author_id' => $actingId], 'author_id = ?', [$id]);
        Auth::revokeAll($id);
        Db::delete('users', 'id = ?', [$id]);
        return null;
    }

    public static function label(array $u): string
    {
        $name = trim((string)($u['name'] ?? ''));
        return $name !== '' ? $name : (string)$u['email'];
    }
}
