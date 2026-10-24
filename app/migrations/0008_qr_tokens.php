<?php
declare(strict_types=1);

return static function (): void {
    // Short-lived codes that a logged-in user shows as a QR code to log in on a phone.
    // Only a hash of the token is stored. (The table qr_logins from 0.9.0 is no longer used.)
    Db::createTable('qr_tokens', [
        Db::pk(),
        'token_hash VARCHAR(64) NOT NULL UNIQUE',
        'user_id INT NOT NULL',
        'created INT NOT NULL',
        'opened INT NOT NULL DEFAULT 0',
        'used INT NOT NULL DEFAULT 0',
        "ip VARCHAR(64) NOT NULL DEFAULT ''",
    ]);
};
