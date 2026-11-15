<?php
declare(strict_types=1);

return static function (): void {
    // Contact form spam filter: suspicious messages are kept apart (spam = 1) and not emailed.
    Db::addColumn('messages', 'spam', 'INT NOT NULL DEFAULT 0');
    Db::addColumn('messages', 'spam_score', 'INT NOT NULL DEFAULT 0');
    Db::addColumn('messages', 'spam_reasons', "VARCHAR(255) NOT NULL DEFAULT ''");
};
