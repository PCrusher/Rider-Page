<?php
declare(strict_types=1);

/** What each user level may open and do. Checked on the server for every admin request. */
final class Roles
{
    public const ALL = ['admin' => 'Admin', 'editor' => 'Editor', 'author' => 'Author'];

    public const HELP = [
        'admin' => 'Everything, including design, updates, maintenance and users.',
        'editor' => 'All content, blog posts, messages, redirects and the 404 page. Publishes posts. No design, updates, maintenance or users.',
        'author' => 'Writes their own blog posts. A post goes live after an editor or admin approves it.',
    ];

    private const EVERYONE = ['admin', 'editor', 'author'];
    private const STAFF = ['admin', 'editor'];

    /** Admin area -> roles that may open it. Anything not listed is for admins only. */
    private const ACCESS = [
        '' => self::EVERYONE,
        'account' => self::EVERYONE,
        'posts' => self::EVERYONE,
        'qr' => self::EVERYONE,
        'upload' => self::EVERYONE,
        'profile' => self::STAFF,
        'socials' => self::STAFF,
        'stats' => self::STAFF,
        'sponsors' => self::STAFF,
        'media' => self::STAFF,
        'faqs' => self::STAFF,
        'sections' => self::STAFF,
        'errorpage' => self::STAFF,
        'redirects' => self::STAFF,
        'messages' => self::STAFF,
        'pages' => self::STAFF,
        'legal' => ['admin'],
        'email' => ['admin'],
        'appearance' => ['admin'],
        'maintenance' => ['admin'],
        'update' => ['admin'],
        'users' => ['admin'],
    ];

    public static function valid(string $role): bool
    {
        return isset(self::ALL[$role]);
    }

    public static function can(string $role, string $section): bool
    {
        return in_array($role, self::ACCESS[$section] ?? ['admin'], true);
    }

    public static function isStaff(string $role): bool
    {
        return in_array($role, self::STAFF, true);
    }

    /** Post statuses a role may pick in the editor. */
    public static function postStatuses(string $role): array
    {
        if ($role === 'author') {
            return ['draft' => 'Draft (only you)', 'pending' => 'Submit for review'];
        }
        return ['draft' => 'Draft (not public)', 'pending' => 'Waiting for review', 'published' => 'Published'];
    }

    /** Authors may change their own posts until a staff member has published them. */
    public static function canEditPost(array $user, array $post): bool
    {
        if (self::isStaff((string)$user['role'])) {
            return true;
        }
        return (string)$user['role'] === 'author'
            && (int)$post['author_id'] === (int)$user['id']
            && (string)$post['status'] !== 'published';
    }
}
