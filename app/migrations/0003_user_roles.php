<?php
declare(strict_types=1);

return static function (): void {
    // Existing users keep full rights: the default role is admin.
    Db::addColumn('users', 'role', "VARCHAR(12) NOT NULL DEFAULT 'admin'");
    Db::addColumn('users', 'name', "VARCHAR(190) NOT NULL DEFAULT ''");
    Db::addColumn('users', 'must_change', 'INT NOT NULL DEFAULT 0');
    // 0 = written before authors existed (or by a deleted user).
    Db::addColumn('posts', 'author_id', 'INT NOT NULL DEFAULT 0');
};
