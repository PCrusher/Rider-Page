<?php
declare(strict_types=1);

return static function (): void {
    // Own pages such as Impressum and privacy policy. "template" is set for pages that were
    // created from a legal template (they are noindex and stay out of the sitemap).
    Db::createTable('pages', [
        Db::pk(),
        'slug VARCHAR(60) NOT NULL UNIQUE',
        'title VARCHAR(190) NOT NULL',
        'body ' . Db::longText() . ' NOT NULL',
        "status VARCHAR(12) NOT NULL DEFAULT 'draft'",
        'in_footer INT NOT NULL DEFAULT 1',
        'pos INT NOT NULL DEFAULT 0',
        "template VARCHAR(40) NOT NULL DEFAULT ''",
        'updated_at VARCHAR(25) NOT NULL',
    ]);
};
