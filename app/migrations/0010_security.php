<?php
declare(strict_types=1);

return static function (): void {
    // Two-factor login (the secret is stored encrypted), email preferences.
    Db::addColumn('users', 'totp_secret', 'TEXT NULL');
    Db::addColumn('users', 'totp_enabled', 'INT NOT NULL DEFAULT 0');
    Db::addColumn('users', 'totp_step', 'INT NOT NULL DEFAULT 0');
    Db::addColumn('users', 'notify', 'INT NOT NULL DEFAULT 1');

    // One-time recovery codes for when the authenticator app is lost. Only hashes are stored.
    Db::createTable('recovery_codes', [
        Db::pk(),
        'user_id INT NOT NULL',
        'code_hash VARCHAR(64) NOT NULL',
        'used INT NOT NULL DEFAULT 0',
    ]);

    // Browsers a user logged in from before, to tell "new device" logins apart. Only a hash of the browser text.
    Db::createTable('login_devices', [
        Db::pk(),
        'user_id INT NOT NULL',
        'fp VARCHAR(64) NOT NULL',
        'first_seen VARCHAR(25) NOT NULL',
        'last_seen VARCHAR(25) NOT NULL',
        'UNIQUE (user_id, fp)',
    ]);

    // Links sent by "Forgot password". Only a hash of the token is stored.
    Db::createTable('password_resets', [
        Db::pk(),
        'user_id INT NOT NULL',
        'token_hash VARCHAR(64) NOT NULL UNIQUE',
        'created INT NOT NULL',
        'used INT NOT NULL DEFAULT 0',
        "ip VARCHAR(64) NOT NULL DEFAULT ''",
    ]);
};
