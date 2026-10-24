<?php
declare(strict_types=1);

return static function (): void {
    // Messages sent through the contact form. Stored so nothing is lost if the email fails.
    // The visitor's IP address is not stored, only a salted hash used for rate limiting.
    Db::createTable('messages', [
        Db::pk(),
        'name VARCHAR(190) NOT NULL',
        'email VARCHAR(190) NOT NULL',
        'body ' . Db::longText() . ' NOT NULL',
        'ip_hash VARCHAR(64) NOT NULL',
        'created_at VARCHAR(25) NOT NULL',
        "read_at VARCHAR(25) NOT NULL DEFAULT ''",
        "mail_status VARCHAR(12) NOT NULL DEFAULT 'pending'",
        "mail_error VARCHAR(255) NOT NULL DEFAULT ''",
    ]);
};
