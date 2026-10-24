<?php
declare(strict_types=1);

/** Example content so a fresh install shows something. Everything here is meant to be replaced. */
final class Seed
{
    public static function example(string $lang): void
    {
        $de = $lang === 'de';
        Settings::setMany([
            'tagline' => $de ? 'Deine Sportart · Deine Stadt' : 'Your sport · Your city',
            'bio' => $de
                ? 'Schreibe hier eine kurze Vorstellung: wer du bist, was du machst und wofür du stehst. Alle Beispieltexte kannst du im Admin-Bereich ändern oder löschen.'
                : 'Write a short introduction here: who you are, what you do and what you stand for. You can change or delete all example content in the admin area.',
            'sticker' => $de ? 'Beispiel-Erfolg' : 'Example result',
            'video_url' => '',
        ]);
        Db::insert('socials', ['type' => 'instagram', 'handle' => '@yourname', 'url' => 'https://www.instagram.com/', 'pos' => 1]);
        Db::insert('socials', ['type' => 'youtube', 'handle' => 'Your channel', 'url' => 'https://www.youtube.com/', 'pos' => 2]);

        foreach ([['12', $de ? 'Podestplätze' : 'podiums'], ['4,800 km', $de ? 'gefahren in dieser Saison' : 'raced this season'], ['28', $de ? 'Starts' : 'starts']] as $i => [$f, $l]) {
            Db::insert('stats', ['figure' => $f, 'label' => $l, 'pos' => $i + 1]);
        }
        foreach ([['Example Partner', 'title'], ['Sample Brand', 'partner'], ['Demo Supplies', 'partner'], ['Local Shop', 'supporter'], ['Friends & Family', 'supporter']] as $i => [$n, $t]) {
            Db::insert('sponsors', ['name' => $n, 'tier' => $t, 'url' => '', 'logo' => '', 'pos' => $i + 1]);
        }
        Db::insert('faqs', [
            'question' => $de ? 'Wie kann ich dich unterstützen?' : 'How can I support you?',
            'answer' => $de ? 'Schreib mir einfach eine Nachricht über das Kontaktformular weiter oben.' : 'Just send me a message using the contact section above.',
            'pos' => 1,
        ]);
        Db::insert('faqs', [
            'question' => $de ? 'Darf ich deine Fotos verwenden?' : 'Can I use your photos?',
            'answer' => $de ? 'Ja, mit Quellenangabe und Link zu dieser Seite. Für kommerzielle Nutzung frag bitte vorher.' : 'Yes, with credit and a link to this page. For commercial use, please ask first.',
            'pos' => 2,
        ]);
        $body = $de
            ? "Das ist ein Beispielbeitrag. Du kannst **fett** und *kursiv* schreiben, [Links](https://example.com) setzen und Bilder hochladen.\n\n## Eine Zwischenüberschrift\n\nFüge ein YouTube-Video ein, indem du den Link allein in eine Zeile schreibst. Es wird erst geladen, wenn Besucher auf Abspielen klicken.\n\n> Zitate sehen auch gut aus.\n\nLösche diesen Beitrag, wenn du deinen ersten eigenen schreibst."
            : "This is an example post. You can write **bold** and *italic* text, add [links](https://example.com) and upload images.\n\n## A subheading\n\nAdd a YouTube video by putting its link on a line of its own. It only loads when a visitor presses play.\n\n> Quotes look good too.\n\nDelete this post when you write your first own one.";
        Db::insert('posts', [
            'slug' => 'welcome', 'title' => $de ? 'Willkommen auf meiner neuen Seite' : 'Welcome to my new page',
            'summary' => $de ? 'Ein Beispielbeitrag, der zeigt, was im Blog möglich ist.' : 'An example post that shows what the blog can do.',
            'cover' => '', 'body' => $body, 'status' => 'published',
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
