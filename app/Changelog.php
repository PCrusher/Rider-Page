<?php
declare(strict_types=1);

/** The version history shown on Admin -> Updates. */
final class Changelog
{
    /**
     * The release package carries its own copy in app/changelog.md (so an update brings the new history
     * along); a checkout of the repository has CHANGELOG.md in the main folder.
     */
    public static function file(): ?string
    {
        foreach ([RP_APP . '/changelog.md', RP_ROOT . '/CHANGELOG.md'] as $f) {
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
    }

    /** @return array<int,array{version:string,html:string}> newest first, as written in the file */
    public static function entries(): array
    {
        $file = self::file();
        if ($file === null) {
            return [];
        }
        $text = str_replace(["\r\n", "\r"], "\n", (string)file_get_contents($file));
        preg_match_all('/^## (\d+\.\d+\.\d+)[ \t]*\n(.*?)(?=^## |\z)/ms', $text, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) {
            $out[] = ['version' => $x[1], 'html' => Markdown::render(trim($x[2]))];
        }
        return $out;
    }
}
