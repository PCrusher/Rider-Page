<?php
declare(strict_types=1);

/** The admin panel at /admin. */
final class Admin
{
    private static string $section = '';
    private static ?array $user = null;

    /* ---------- field definitions ---------- */

    public static function profileFields(): array
    {
        return [
            ['name', 'Name', 'text', 'required' => true, 'hint' => 'Shown in the menu and as the page title.'],
            ['tagline', 'Tagline', 'text', 'hint' => 'For example: Gravel · XC marathon · Gothenburg'],
            ['bio', 'Short introduction', 'textarea', 'rows' => 4],
            ['hero_image', 'Main photo', 'image', 'hint' => 'A portrait or action photo. JPG, PNG or WebP.'],
            ['sticker', 'Badge on the photo', 'text', 'hint' => 'Optional, for example "3rd Swedish Championships".'],
            ['email', 'Contact email', 'email', 'hint' => 'Shown in the contact section. Leave empty to hide that section.'],
            ['contact_title', 'Contact heading', 'text', 'hint' => 'Leave empty for the default text.'],
            ['contact_text', 'Contact text', 'textarea', 'rows' => 2],
            ['video_url', 'YouTube video link', 'url', 'hint' => 'Shown in the media section. Visitors load it only when they press play.'],
            ['video_caption', 'Video caption', 'text'],
            ['meta_description', 'Description for search engines', 'textarea', 'rows' => 2, 'hint' => 'About 150 characters. Leave empty to use the introduction.'],
            ['site_url', 'Site address', 'url', 'hint' => 'Only needed if the page has several addresses. Example: https://www.example.com'],
            ['lang', 'Language of the page texts', 'select', 'options' => Lang::AVAILABLE, 'hint' => 'Menu, headings and buttons. Your own texts stay as you write them.'],
        ];
    }

    public static function entities(): array
    {
        return [
            'socials' => [
                'title' => 'Social accounts', 'one' => 'account', 'list' => ['type', 'handle', 'url'],
                'help' => 'Shown as buttons under your introduction.',
                'fields' => [
                    ['type', 'Platform', 'select', 'options' => social_types()],
                    ['handle', 'Shown text', 'text', 'hint' => 'For example @mara.rides. Optional.'],
                    ['url', 'Link to your profile', 'url', 'required' => true],
                ],
            ],
            'stats' => [
                'title' => 'Results', 'one' => 'result', 'list' => ['figure', 'label'],
                'help' => 'Big numbers about your season or career. The section appears when there is at least one.',
                'fields' => [
                    ['figure', 'Number or short text', 'text', 'required' => true, 'hint' => 'For example 14 or 6,240 km'],
                    ['label', 'Description', 'text', 'required' => true, 'hint' => 'For example podiums this season'],
                ],
            ],
            'sponsors' => [
                'title' => 'Partners', 'one' => 'partner', 'list' => ['logo', 'name', 'tier'],
                'help' => 'Sponsors and supporters. Without a logo the name is shown as text.',
                'fields' => [
                    ['name', 'Name', 'text', 'required' => true],
                    ['tier', 'Level', 'select', 'options' => ['title' => 'Title partner (largest)', 'partner' => 'Partner', 'supporter' => 'Supporter (smallest)']],
                    ['url', 'Link', 'url', 'hint' => 'Optional.'],
                    ['logo', 'Logo', 'image', 'alpha' => true, 'hint' => 'PNG with transparent background works best.'],
                ],
            ],
            'media' => [
                'title' => 'Photos', 'one' => 'photo', 'list' => ['image', 'caption'],
                'help' => 'The gallery in the media section.',
                'fields' => [
                    ['image', 'Photo', 'image', 'required' => true],
                    ['caption', 'Caption', 'text', 'hint' => 'Used as the image description for screen readers and search engines.'],
                ],
            ],
            'faqs' => [
                'title' => 'FAQ', 'one' => 'question', 'list' => ['question'],
                'help' => 'Questions people often ask. Search engines can show these directly.',
                'fields' => [
                    ['question', 'Question', 'text', 'required' => true],
                    ['answer', 'Answer', 'textarea', 'required' => true, 'rows' => 4],
                ],
            ],
        ];
    }

    /* ---------- routing ---------- */

    public static function dispatch(string $path, string $method): void
    {
        rp_session();
        send_headers(true);
        $post = $method === 'POST';

        if ($path === '/admin/login') {
            self::login($post);
            return;
        }
        if ($path === '/admin/logout' && $post) {
            csrf_check();
            Auth::logout();
            redirect('/admin/login');
        }

        self::$user = Auth::gate();
        if ($post) {
            csrf_check();
        }

        $parts = explode('/', trim($path, '/'));
        $sec = $parts[1] ?? '';
        $act = $parts[2] ?? '';
        self::$section = $sec;
        $ents = self::entities();

        if ($sec === '') {
            self::dashboard();
        } elseif ($sec === 'profile') {
            self::profile($post);
        } elseif (isset($ents[$sec])) {
            self::entity($sec, $act, $post);
        } elseif ($sec === 'posts') {
            self::posts($act, $post);
        } elseif ($sec === 'sections') {
            self::sections($act, $post);
        } elseif ($sec === 'appearance') {
            self::appearance($post);
        } elseif ($sec === 'maintenance') {
            self::maintenance($post);
        } elseif ($sec === 'update') {
            self::update($act, $post);
        } elseif ($sec === 'account') {
            self::account($post);
        } elseif ($sec === 'upload' && $post) {
            self::upload();
        } else {
            http_response_code(404);
            self::view('notfound', [], 'Not found');
        }
    }

    private static function view(string $name, array $vars, string $title): void
    {
        $content = tpl('app/views/admin/' . $name, $vars);
        echo tpl('app/views/admin/layout', [
            'content' => $content,
            'title' => $title,
            'section' => self::$section,
            'flashes' => flashes(),
            'user' => self::$user,
            'version' => Updater::version(),
            'entities' => self::entities(),
        ]);
    }

    /* ---------- login ---------- */

    private static function login(bool $post): void
    {
        if (Auth::user()) {
            redirect('/admin');
        }
        $error = '';
        $email = '';
        if ($post) {
            csrf_check();
            $email = trim((string)($_POST['email'] ?? ''));
            $r = Auth::attempt($email, (string)($_POST['password'] ?? ''));
            if ($r === true) {
                redirect('/admin');
            }
            $error = (string)$r;
        }
        echo tpl('app/views/admin/login', ['error' => $error, 'email' => $email]);
    }

    /* ---------- dashboard ---------- */

    private static function dashboard(): void
    {
        $s = static fn(string $k): string => trim((string)Settings::get($k, ''));
        $count = static fn(string $t): int => (int)Db::val('SELECT COUNT(*) FROM ' . $t);
        $steps = [
            ['Add your name and introduction', $s('name') !== '' && $s('bio') !== '', '/admin/profile'],
            ['Upload a main photo', $s('hero_image') !== '', '/admin/profile'],
            ['Add your social accounts', $count('socials') > 0, '/admin/socials'],
            ['Add your partners', $count('sponsors') > 0, '/admin/sponsors'],
            ['Add a contact email', $s('email') !== '', '/admin/profile'],
            ['Pick a design', $s('preset') !== '', '/admin/appearance'],
        ];
        $hidden = [];
        foreach (Site::sectionOrder() as $id) {
            if (!in_array($id, Site::sectionsOff(), true) && !Site::hasContent($id)) {
                $hidden[] = t('sec.' . $id);
            }
        }
        self::view('dashboard', [
            'steps' => $steps,
            'hidden' => $hidden,
            'maintenance' => Site::maintenanceOn(),
            'update' => Updater::cached(),
        ], 'Dashboard');
    }

    /* ---------- profile ---------- */

    private static function profile(bool $post): void
    {
        $fields = self::profileFields();
        $current = Settings::all();
        $errors = [];
        $values = $current;
        if ($post) {
            [$data, $errors, $remove] = self::collect($fields, $current);
            $values = $data + $current;
            if (!$errors) {
                foreach ($data as $k => $v) {
                    Settings::set($k, (string)$v);
                }
                foreach ($remove as $old) {
                    delete_upload($old);
                }
                Lang::reset();
                flash('Saved.');
                redirect('/admin/profile');
            }
            self::cleanupNew($fields, $data, $current);
        }
        self::view('form', [
            'heading' => 'Profile', 'intro' => 'The main information shown at the top of your page.',
            'fields' => $fields, 'values' => $values, 'errors' => $errors, 'action' => url('/admin/profile'),
            'submit' => 'Save profile', 'extra' => '', 'cancel' => '',
        ], 'Profile');
    }

    /**
     * Read the submitted form for a list of field definitions.
     * @return array{0:array,1:string[],2:string[]} data, errors, old files to delete after saving
     */
    private static function collect(array $fields, array $current): array
    {
        $data = [];
        $errors = [];
        $remove = [];
        foreach ($fields as $f) {
            [$name, $label, $kind] = $f;
            $old = (string)($current[$name] ?? '');
            if ($kind === 'image') {
                $file = $_FILES[$name] ?? null;
                $data[$name] = $old;
                if (!empty($_POST['remove_' . $name]) && $old !== '') {
                    $remove[] = $old;
                    $data[$name] = '';
                }
                if ($file && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $new = Images::fromUpload($file, !empty($f['alpha']));
                        if ($old !== '' && !in_array($old, $remove, true)) {
                            $remove[] = $old;
                        }
                        $data[$name] = $new;
                    } catch (RuntimeException $e) {
                        $errors[] = $label . ': ' . $e->getMessage();
                    }
                }
                if (!empty($f['required']) && $data[$name] === '' && !$errors) {
                    $errors[] = $label . ' is required.';
                }
                continue;
            }
            $v = trim((string)($_POST[$name] ?? ''));
            if (!empty($f['required']) && $v === '') {
                $errors[] = $label . ' is required.';
            }
            if ($kind === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $label . ' is not a valid email address.';
            }
            if ($kind === 'url' && $v !== '') {
                $u = safe_url($v);
                if ($u === '') {
                    $errors[] = $label . ' must be a full web address starting with https://';
                }
                $v = $u !== '' ? $u : $v;
            }
            if ($kind === 'select' && !array_key_exists($v, $f['options'] ?? [])) {
                $errors[] = $label . ': please choose one of the options.';
            }
            $data[$name] = $v;
        }
        return [$data, $errors, $remove];
    }

    /** Delete images that were uploaded for a form that then failed validation. */
    private static function cleanupNew(array $fields, array $data, array $current): void
    {
        foreach ($fields as $f) {
            if ($f[2] === 'image') {
                $n = (string)($data[$f[0]] ?? '');
                if ($n !== '' && $n !== (string)($current[$f[0]] ?? '')) {
                    delete_upload($n);
                }
            }
        }
    }

    /* ---------- generic lists (socials, results, partners, photos, FAQ) ---------- */

    private static function entity(string $key, string $act, bool $post): void
    {
        $cfg = self::entities()[$key];
        $table = $key;

        if ($act === '' && !$post) {
            $rows = Db::rows('SELECT * FROM ' . $table . ' ORDER BY pos, id');
            self::view('list', ['key' => $key, 'cfg' => $cfg, 'rows' => $rows], $cfg['title']);
            return;
        }
        if ($act === 'edit' && !$post) {
            $id = (int)($_GET['id'] ?? 0);
            $row = $id ? Db::row('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]) : [];
            if ($id && !$row) {
                redirect('/admin/' . $key);
            }
            self::entityForm($key, $cfg, $row ?: [], [], $id);
            return;
        }
        if ($act === 'save' && $post) {
            $id = (int)($_POST['id'] ?? 0);
            $row = $id ? Db::row('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]) : [];
            if ($id && !$row) {
                redirect('/admin/' . $key);
            }
            [$data, $errors, $remove] = self::collect($cfg['fields'], $row ?: []);
            if ($errors) {
                self::cleanupNew($cfg['fields'], $data, $row ?: []);
                self::entityForm($key, $cfg, $data + ($row ?: []), $errors, $id);
                return;
            }
            if ($id) {
                Db::update($table, $data, 'id = ?', [$id]);
            } else {
                $data['pos'] = (int)Db::val('SELECT COALESCE(MAX(pos), 0) FROM ' . $table) + 1;
                Db::insert($table, $data);
            }
            foreach ($remove as $old) {
                delete_upload($old);
            }
            flash('Saved.');
            redirect('/admin/' . $key);
        }
        if ($act === 'delete' && $post) {
            $id = (int)($_POST['id'] ?? 0);
            $row = Db::row('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]);
            if ($row) {
                foreach ($cfg['fields'] as $f) {
                    if ($f[2] === 'image') {
                        delete_upload((string)($row[$f[0]] ?? ''));
                    }
                }
                Db::delete($table, 'id = ?', [$id]);
                flash('Deleted.');
            }
            redirect('/admin/' . $key);
        }
        if ($act === 'move' && $post) {
            self::move($table, (int)($_POST['id'] ?? 0), (string)($_POST['dir'] ?? ''));
            redirect('/admin/' . $key);
        }
        redirect('/admin/' . $key);
    }

    private static function entityForm(string $key, array $cfg, array $row, array $errors, int $id): void
    {
        self::view('form', [
            'heading' => ($id ? 'Edit ' : 'Add ') . $cfg['one'], 'intro' => '',
            'fields' => $cfg['fields'], 'values' => $row, 'errors' => $errors,
            'action' => url('/admin/' . $key . '/save'), 'submit' => 'Save',
            'extra' => '<input type="hidden" name="id" value="' . (int)$id . '">',
            'cancel' => url('/admin/' . $key),
        ], $cfg['title']);
    }

    private static function move(string $table, int $id, string $dir): void
    {
        $ids = array_map('intval', array_column(Db::rows('SELECT id FROM ' . $table . ' ORDER BY pos, id'), 'id'));
        $i = array_search($id, $ids, true);
        if ($i === false) {
            return;
        }
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $pos => $rid) {
            Db::update($table, ['pos' => $pos + 1], 'id = ?', [$rid]);
        }
    }

    /* ---------- blog posts ---------- */

    private static function posts(string $act, bool $post): void
    {
        if ($act === '' && !$post) {
            $rows = Db::rows('SELECT id, slug, title, status, published_at FROM posts ORDER BY published_at DESC, id DESC');
            self::view('posts', ['rows' => $rows], 'Blog');
            return;
        }
        if ($act === 'edit' && !$post) {
            $id = (int)($_GET['id'] ?? 0);
            $row = $id ? Db::row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
            if ($id && !$row) {
                redirect('/admin/posts');
            }
            $row ??= ['title' => '', 'slug' => '', 'summary' => '', 'cover' => '', 'body' => '', 'status' => 'draft', 'published_at' => now()];
            self::view('post_edit', ['row' => $row, 'id' => $id, 'errors' => []], $id ? 'Edit post' : 'New post');
            return;
        }
        if ($act === 'save' && $post) {
            $id = (int)($_POST['id'] ?? 0);
            $old = $id ? Db::row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
            if ($id && !$old) {
                redirect('/admin/posts');
            }
            $old ??= ['cover' => ''];
            $errors = [];
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') {
                $errors[] = 'Title is required.';
            }
            $slug = slugify(trim((string)($_POST['slug'] ?? '')) ?: $title) ?: 'post';
            $base = $slug;
            $n = 2;
            while (Db::val('SELECT COUNT(*) FROM posts WHERE slug = ? AND id <> ?', [$slug, $id]) > 0) {
                $slug = $base . '-' . $n++;
            }
            $ts = strtotime(str_replace('T', ' ', (string)($_POST['published_at'] ?? '')));
            $data = [
                'title' => $title,
                'slug' => $slug,
                'summary' => mb_substr(trim((string)($_POST['summary'] ?? '')), 0, 500),
                'body' => str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')),
                'status' => ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft',
                'published_at' => date('Y-m-d H:i:s', $ts ?: time()),
                'updated_at' => now(),
            ];
            [$img, $imgErrors, $remove] = self::collect([['cover', 'Cover image', 'image']], $old);
            $errors = array_merge($errors, $imgErrors);
            $data['cover'] = $img['cover'];
            if ($errors) {
                self::cleanupNew([['cover', 'Cover image', 'image']], $img, $old);
                self::view('post_edit', ['row' => $data + ['cover' => $old['cover']], 'id' => $id, 'errors' => $errors], $id ? 'Edit post' : 'New post');
                return;
            }
            if ($id) {
                Db::update('posts', $data, 'id = ?', [$id]);
            } else {
                $data['created_at'] = now();
                Db::insert('posts', $data);
            }
            foreach ($remove as $r) {
                delete_upload($r);
            }
            flash($data['status'] === 'published' ? 'Saved and published.' : 'Saved as draft.');
            redirect('/admin/posts');
        }
        if ($act === 'delete' && $post) {
            $id = (int)($_POST['id'] ?? 0);
            $row = Db::row('SELECT cover FROM posts WHERE id = ?', [$id]);
            if ($row) {
                delete_upload((string)$row['cover']);
                Db::delete('posts', 'id = ?', [$id]);
                flash('Deleted.');
            }
            redirect('/admin/posts');
        }
        redirect('/admin/posts');
    }

    private static function upload(): void
    {
        try {
            $file = $_FILES['file'] ?? null;
            if (!$file) {
                throw new RuntimeException('No file received.');
            }
            $rel = Images::fromUpload($file, false);
            json_out(['ok' => true, 'path' => $rel, 'url' => url($rel)]);
        } catch (RuntimeException $e) {
            json_out(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /* ---------- sections ---------- */

    private static function sections(string $act, bool $post): void
    {
        $id = (string)($_POST['id'] ?? '');
        if ($post && in_array($id, Site::SECTIONS, true)) {
            if ($act === 'move') {
                $order = Site::sectionOrder();
                $i = array_search($id, $order, true);
                $j = ($_POST['dir'] ?? '') === 'up' ? $i - 1 : $i + 1;
                if ($i !== false && $j >= 0 && $j < count($order)) {
                    [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
                    Settings::set('sections_order', implode(',', $order));
                }
            } elseif ($act === 'toggle') {
                $off = Site::sectionsOff();
                $off = in_array($id, $off, true) ? array_values(array_diff($off, [$id])) : [...$off, $id];
                Settings::set('sections_off', implode(',', $off));
            }
            redirect('/admin/sections');
        }
        $rows = [];
        foreach (Site::sectionOrder() as $sid) {
            $rows[] = [
                'id' => $sid, 'label' => t('sec.' . $sid),
                'off' => in_array($sid, Site::sectionsOff(), true),
                'empty' => !Site::hasContent($sid),
            ];
        }
        self::view('sections', ['rows' => $rows], 'Sections');
    }

    /* ---------- appearance ---------- */

    private static function appearance(bool $post): void
    {
        if ($post) {
            if (!empty($_POST['reset_colors'])) {
                foreach (['color_accent', 'color_bg', 'color_fg'] as $k) {
                    Settings::set($k, '');
                }
                flash('Custom colours removed.');
                redirect('/admin/appearance');
            }
            $preset = (string)($_POST['preset'] ?? '');
            if (isset(Presets::all()[$preset])) {
                Settings::set('preset', $preset);
            }
            foreach (['accent', 'bg', 'fg'] as $k) {
                $v = trim((string)($_POST['use_' . $k] ?? '') !== '' ? (string)($_POST['color_' . $k] ?? '') : '');
                Settings::set('color_' . $k, hex_valid($v) ? strtoupper($v) : '');
            }
            flash('Saved.');
            redirect('/admin/appearance');
        }
        $p = Presets::get(Presets::id());
        self::view('appearance', [
            'presets' => Presets::all(),
            'current' => Presets::id(),
            'colors' => [
                'accent' => ['Accent colour', 'Buttons, links and highlights', Settings::get('color_accent', ''), $p['accent']],
                'bg' => ['Background', 'The page background', Settings::get('color_bg', ''), $p['bg']],
                'fg' => ['Text', 'Headings and body text', Settings::get('color_fg', ''), $p['fg']],
            ],
            'warnings' => Presets::warnings(),
        ], 'Appearance');
    }

    /* ---------- maintenance mode ---------- */

    private static function maintenance(bool $post): void
    {
        if ($post) {
            Settings::setMany([
                'maintenance_title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120),
                'maintenance_text' => mb_substr(trim((string)($_POST['text'] ?? '')), 0, 1000),
            ]);
            $toggle = (string)($_POST['toggle'] ?? '');
            if ($toggle === 'on') {
                Settings::set('maintenance', '1');
                flash('Maintenance mode is on. Visitors now see the maintenance page.');
            } elseif ($toggle === 'off') {
                Settings::set('maintenance', '');
                flash('Maintenance mode is off. Your page is live again.');
            } else {
                flash('Saved.');
            }
            redirect('/admin/maintenance');
        }
        self::view('maintenance', [
            'on' => Site::maintenanceOn(),
            'title' => (string)Settings::get('maintenance_title', ''),
            'text' => (string)Settings::get('maintenance_text', ''),
            'defaultTitle' => t('maint.title'),
            'defaultText' => t('maint.text'),
        ], 'Maintenance');
    }

    /* ---------- updates ---------- */

    private static function update(string $act, bool $post): void
    {
        if ($act === 'check' && $post) {
            Updater::latest(true);
            redirect('/admin/update');
        }
        if ($act === 'run' && $post) {
            $r = Updater::run(!empty($_POST['confirm_breaking']));
            if ($r['ok']) {
                redirect('/admin/update/finish');
            }
            flash($r['error'], 'error');
            redirect('/admin/update');
        }
        if ($act === 'finish') {
            $r = Updater::finish();
            flash($r['message'], $r['ok'] ? 'ok' : 'error');
            redirect('/admin/update');
        }
        if ($act === 'repo' && $post) {
            $repo = trim((string)($_POST['repo'] ?? ''));
            if ($repo === '' || preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $repo)) {
                Settings::set('update_repo', $repo);
                Updater::latest(true);
                flash('Saved.');
            } else {
                flash('The repository must look like owner/name.', 'error');
            }
            redirect('/admin/update');
        }
        self::view('update', [
            'version' => Updater::version(),
            'manifest' => Updater::manifest(),
            'latest' => Updater::latest(false),
            'repo' => Updater::repo(),
            'check' => Updater::preflight(null),
            'backups' => Updater::backups(),
        ], 'Updates');
    }

    /* ---------- account ---------- */

    private static function account(bool $post): void
    {
        $errors = [];
        if ($post) {
            $u = Db::row('SELECT * FROM users WHERE id = ?', [(int)self::$user['id']]);
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $current = (string)($_POST['current'] ?? '');
            $new = (string)($_POST['new'] ?? '');
            if (!$u || !password_verify($current, (string)$u['password_hash'])) {
                $errors[] = 'The current password is not correct.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid email address.';
            }
            if ($new !== '' && strlen($new) < 10) {
                $errors[] = 'The new password needs at least 10 characters.';
            }
            if (!$errors && Db::val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, (int)$u['id']]) > 0) {
                $errors[] = 'This email address is already used.';
            }
            if (!$errors) {
                $data = ['email' => $email];
                if ($new !== '') {
                    $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
                }
                Db::update('users', $data, 'id = ?', [(int)$u['id']]);
                flash('Saved.');
                redirect('/admin/account');
            }
        }
        self::view('account', ['errors' => $errors, 'email' => $post ? ($_POST['email'] ?? '') : self::$user['email']], 'Account');
    }
}
