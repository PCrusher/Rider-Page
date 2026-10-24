<?php
declare(strict_types=1);

return static function (): void {
    // One row per signed-in browser, so the account page can show where you are logged in
    // and sessions can be ended from another device. Only a hash of the session token is stored.
    Db::createTable('login_sessions', [
        Db::pk(),
        'user_id INT NOT NULL',
        'token_hash VARCHAR(64) NOT NULL UNIQUE',
        "ip VARCHAR(64) NOT NULL DEFAULT ''",
        "ua VARCHAR(255) NOT NULL DEFAULT ''",
        "method VARCHAR(12) NOT NULL DEFAULT 'password'",
        'created_at VARCHAR(25) NOT NULL',
        'last_seen VARCHAR(25) NOT NULL',
    ]);

    // Short-lived requests for "log in by scanning a QR code with a phone that is already logged in".
    Db::createTable('qr_logins', [
        Db::pk(),
        'token_hash VARCHAR(64) NOT NULL UNIQUE',
        'poll_hash VARCHAR(64) NOT NULL',
        'sess_hash VARCHAR(64) NOT NULL',
        'code VARCHAR(8) NOT NULL',
        'created INT NOT NULL',
        'opened INT NOT NULL DEFAULT 0',
        "status VARCHAR(12) NOT NULL DEFAULT 'pending'",
        'user_id INT NOT NULL DEFAULT 0',
        'tries INT NOT NULL DEFAULT 0',
        "ip VARCHAR(64) NOT NULL DEFAULT ''",
        "ua VARCHAR(255) NOT NULL DEFAULT ''",
        'approved INT NOT NULL DEFAULT 0',
    ]);

    // Off by default: every user decides for themselves whether QR login may sign them in.
    Db::addColumn('users', 'qr_enabled', 'INT NOT NULL DEFAULT 0');
};
