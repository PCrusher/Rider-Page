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
            ['hero_credit', 'Main photo: photographer', 'text', 'hint' => 'Optional photo credit, shown on the photo. Leave empty for none.'],
            ['hero_credit_url', 'Main photo: photographer link', 'url', 'hint' => 'Optional link to their website or profile.'],
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
                'title' => 'Photos', 'one' => 'photo', 'list' => ['image', 'caption', 'credit'],
                'help' => 'The gallery in the media section.',
                'fields' => [
                    ['image', 'Photo', 'image', 'required' => true],
                    ['caption', 'Caption', 'text', 'hint' => 'Used as the image description for screen readers and search engines.'],
                    ['credit', 'Photographer', 'text', 'hint' => 'Optional photo credit, shown on the photo.'],
                    ['credit_url', 'Photographer link', 'url', 'hint' => 'Optional link to their website or profile.'],
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
        if ($path === '/admin/qr/login') {
            self::qrLogin($post);
            return;
        }
        if ($path === '/admin/forgot') {
            self::forgot($post);
            return;
        }
        if ($path === '/admin/reset') {
            self::resetPassword($post);
            return;
        }
        if ($path === '/admin/recover') {
            self::recover($post);
            return;
        }
        if ($path === '/admin/login/2fa') {
            self::login2fa($post);
            return;
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

        if (!Roles::can((string)self::$user['role'], $sec)) {
            http_response_code(403);
            self::view('forbidden', [], 'Not allowed');
            return;
        }
        if (Settings::get('require_2fa', '0') === '1' && self::$user['role'] === 'admin' && !self::$user['totp_enabled'] && $sec !== 'account') {
            flash('Admins have to use two-factor login on this site. Set it up here to continue.', 'error');
            redirect('/admin/account#2fa');
        }
        if ((int)self::$user['must_change'] === 1 && $sec !== 'account') {
            flash('Please choose your own password first.', 'error');
            redirect('/admin/account');
        }

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
        } elseif ($sec === 'qr') {
            self::qr($act, $post);
        } elseif ($sec === 'statistics') {
            self::statistics($act, $post);
        } elseif ($sec === 'notice') {
            self::notice($post);
        } elseif ($sec === 'pages') {
            self::pages($act, $post);
        } elseif ($sec === 'legal') {
            self::legal($post);
        } elseif ($sec === 'messages') {
            self::messages($act, $post);
        } elseif ($sec === 'email') {
            self::email($post);
        } elseif ($sec === 'errorpage') {
            self::errorPage($post);
        } elseif ($sec === 'redirects') {
            self::redirects($act, $post);
        } elseif ($sec === 'update') {
            self::update($act, $post);
        } elseif ($sec === 'users') {
            self::users($act, $post);
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
            if ($r === 'mfa') {
                redirect('/admin/login/2fa');
            }
            if ($r === true) {
                redirect('/admin');
            }
            $error = (string)$r;
        }
        echo tpl('app/views/admin/login', ['error' => $error, 'email' => $email, 'flashes' => flashes()]);
    }

    /* ---------- second step, forgot password, recovery ---------- */

    private static function login2fa(bool $post): void
    {
        if (Auth::user()) {
            redirect('/admin');
        }
        if (Auth::pendingMfa() === null) {
            redirect('/admin/login');
        }
        $error = '';
        if ($post) {
            csrf_check();
            $r = Auth::completeMfa((string)($_POST['code'] ?? ''));
            if ($r === true) {
                redirect('/admin');
            }
            $error = (string)$r;
            if (Auth::pendingMfa() === null) {
                flash($error, 'error');
                redirect('/admin/login');
            }
        }
        echo tpl('app/views/admin/login_2fa', ['error' => $error]);
    }

    private static function forgot(bool $post): void
    {
        if (Auth::user()) {
            redirect('/admin');
        }
        $sent = false;
        if ($post) {
            csrf_check();
            PasswordReset::request((string)($_POST['email'] ?? ''), client_ip());
            $sent = true;
        }
        echo tpl('app/views/admin/forgot', ['sent' => $sent, 'recovery' => Recovery::active()]);
    }

    private static function resetPassword(bool $post): void
    {
        $token = (string)($post ? ($_POST['t'] ?? '') : ($_GET['t'] ?? ''));
        $bad = 'This link is not valid any more. Ask for a new one on the login page.';
        $error = '';
        if ($post) {
            csrf_check();
            $new = (string)($_POST['new'] ?? '');
            $error = $new !== (string)($_POST['again'] ?? '') ? 'The two passwords are not the same.' : (string)PasswordReset::finish($token, $new);
            if ($error === '') {
                flash('Your password was changed. You can log in now.');
                redirect('/admin/login');
            }
        }
        $valid = PasswordReset::find($token) !== null;
        echo tpl('app/views/admin/reset', ['token' => $valid ? $token : '', 'error' => $valid ? $error : ($error !== '' && $error !== $bad ? $error : $bad)]);
    }

    private static function recover(bool $post): void
    {
        $error = '';
        if ($post) {
            csrf_check();
            $new = (string)($_POST['new'] ?? '');
            $error = $new !== (string)($_POST['again'] ?? '')
                ? 'The two passwords are not the same.'
                : (string)Recovery::run((string)($_POST['phrase'] ?? ''), (string)($_POST['email'] ?? ''), $new, client_ip());
            if ($error === '') {
                flash('The password was reset. You can log in now.');
                redirect('/admin/login');
            }
        }
        echo tpl('app/views/admin/recover', ['active' => Recovery::active(), 'error' => $error]);
    }

    /* ---------- login on a phone with a QR code ---------- */

    /** The phone: opens the address from the QR code, shows the account and asks for one tap. */
    private static function qrLogin(bool $post): void
    {
        header('Referrer-Policy: no-referrer');
        $token = (string)($post ? ($_POST['t'] ?? '') : ($_GET['t'] ?? ''));
        $invalid = 'This code is not valid any more. Codes only work for a few seconds. Show a new code on your computer and scan it again.';
        if ($post) {
            csrf_check();
            $uid = QrLogin::consume($token);
            if ($uid === null) {
                echo tpl('app/views/admin/qr_confirm', ['token' => '', 'user' => null, 'error' => $invalid]);
                return;
            }
            Auth::login($uid, 'qr');
            redirect('/admin');
        }
        $row = QrLogin::open($token);
        echo tpl('app/views/admin/qr_confirm', [
            'token' => $row ? $token : '',
            'user' => $row ? Users::find((int)$row['user_id']) : null,
            'error' => $row ? '' : $invalid,
        ]);
    }

    /** The computer (already logged in): asks for a code and watches what the phone does with it. */
    private static function qr(string $act, bool $post): void
    {
        $uid = (int)self::$user['id'];
        if ($act === 'new' && $post) {
            $u = Db::row('SELECT * FROM users WHERE id = ?', [$uid]);
            if (!$u || !password_verify((string)($_POST['current'] ?? ''), (string)$u['password_hash'])) {
                json_out(['ok' => false, 'error' => 'The password is not correct.'], 403);
            }
            if (TwoFactor::enabled($u) && !TwoFactor::check($uid, (string)($_POST['code'] ?? ''), false)) {
                json_out(['ok' => false, 'error' => 'The code from your authenticator app is not correct.'], 403);
            }
            $r = QrLogin::create($uid);
            if ($r === null) {
                json_out(['ok' => false, 'error' => 'QR login is off for your account, or you asked for too many codes. Wait a few minutes.'], 429);
            }
            json_out(['ok' => true] + $r);
        }
        if ($act === 'check' && !$post) {
            json_out(['state' => QrLogin::state((int)($_GET['id'] ?? 0), $uid)]);
        }
        redirect('/admin/account');
    }

    /* ---------- dashboard ---------- */

    private static function dashboard(): void
    {
        $uid = (int)self::$user['id'];
        if (self::$user['role'] === 'author') {
            $counts = ['draft' => 0, 'pending' => 0, 'published' => 0];
            foreach (Db::rows('SELECT status, COUNT(*) AS n FROM posts WHERE author_id = ? GROUP BY status', [$uid]) as $r) {
                $counts[$r['status']] = (int)$r['n'];
            }
            self::view('dashboard_author', ['counts' => $counts, 'name' => Users::label(self::$user)], 'Dashboard');
            return;
        }
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
            'notFound' => self::notFoundCount(),
            'pending' => (int)Db::val("SELECT COUNT(*) FROM posts WHERE status = 'pending'"),
            'unread' => self::unreadCount(),
            'week' => Analytics::enabled() ? self::weekStats() : null,
            'legalMissing' => self::$user['role'] === 'admin' && self::legalMissing(),
            'update' => Updater::cached(),
        ], 'Dashboard');
    }

    /** True when no published page was made from a legal template. */
    private static function legalMissing(): bool
    {
        try {
            return (int)Db::val("SELECT COUNT(*) FROM pages WHERE status = 'published' AND template <> ''") === 0;
        } catch (Throwable) {
            return false;
        }
    }

    private static function weekStats(): ?array
    {
        try {
            $r = Analytics::report(7);
            return ['views' => $r['views'], 'visitors' => $r['visitors']];
        } catch (Throwable) {
            return null;
        }
    }

    private static function unreadCount(): int
    {
        try {
            return (int)Db::val("SELECT COUNT(*) FROM messages WHERE read_at = ''");
        } catch (Throwable) {
            return 0;
        }
    }

    private static function notFoundCount(): int
    {
        try {
            return (int)Db::val('SELECT COUNT(*) FROM not_found');
        } catch (Throwable) {
            return 0;
        }
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
        $u = self::$user;
        $role = (string)$u['role'];
        $staff = Roles::isStaff($role);

        if ($act === '' && !$post) {
            $sql = 'SELECT p.id, p.slug, p.title, p.status, p.published_at, p.author_id, u.email AS author_email, u.name AS author_name '
                . 'FROM posts p LEFT JOIN users u ON u.id = p.author_id';
            $params = [];
            if (!$staff) {
                $sql .= ' WHERE p.author_id = ?';
                $params[] = (int)$u['id'];
            }
            $rows = Db::rows($sql . ' ORDER BY p.published_at DESC, p.id DESC', $params);
            foreach ($rows as &$r) {
                $r['can_edit'] = Roles::canEditPost($u, $r);
            }
            unset($r);
            self::view('posts', ['rows' => $rows, 'staff' => $staff], 'Blog');
            return;
        }
        if ($act === 'edit' && !$post) {
            $id = (int)($_GET['id'] ?? 0);
            $row = $id ? Db::row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
            if ($id && (!$row || !Roles::canEditPost($u, $row))) {
                flash('You can not edit this post.', 'error');
                redirect('/admin/posts');
            }
            $row ??= ['title' => '', 'slug' => '', 'summary' => '', 'cover' => '', 'cover_credit' => '', 'cover_credit_url' => '', 'body' => '', 'status' => 'draft', 'published_at' => now(), 'author_id' => (int)$u['id']];
            self::postForm($row, $id, []);
            return;
        }
        if ($act === 'save' && $post) {
            $id = (int)($_POST['id'] ?? 0);
            $old = $id ? Db::row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
            if ($id && (!$old || !Roles::canEditPost($u, $old))) {
                flash('You can not edit this post.', 'error');
                redirect('/admin/posts');
            }
            $old ??= ['cover' => '', 'status' => 'draft', 'author_id' => (int)$u['id'], 'published_at' => now()];
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
            $status = (string)($_POST['status'] ?? 'draft');
            if (!array_key_exists($status, Roles::postStatuses($role))) {
                $status = $role === 'author' ? 'draft' : (string)$old['status'];
            }
            // Only staff pick the date; an author's post gets the date it is written.
            $ts = $staff ? strtotime(str_replace('T', ' ', (string)($_POST['published_at'] ?? ''))) : strtotime((string)$old['published_at']);
            $data = [
                'title' => $title,
                'slug' => $slug,
                'summary' => mb_substr(trim((string)($_POST['summary'] ?? '')), 0, 500),
                'body' => str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')),
                'status' => $status,
                'published_at' => date('Y-m-d H:i:s', $ts ?: time()),
                'updated_at' => now(),
            ];
            [$img, $imgErrors, $remove] = self::collect([['cover', 'Cover image', 'image']], $old);
            $errors = array_merge($errors, $imgErrors);
            $data['cover'] = $img['cover'];
            $data['cover_credit'] = mb_substr(trim((string)($_POST['cover_credit'] ?? '')), 0, 190);
            $creditUrl = trim((string)($_POST['cover_credit_url'] ?? ''));
            $data['cover_credit_url'] = safe_url($creditUrl);
            if ($creditUrl !== '' && $data['cover_credit_url'] === '') {
                $errors[] = 'The photographer link must be a full web address starting with https://';
            }
            if ($errors) {
                self::cleanupNew([['cover', 'Cover image', 'image']], $img, $old);
                self::postForm($data + ['cover' => $old['cover'], 'author_id' => $old['author_id']], $id, $errors);
                return;
            }
            if ($id) {
                Db::update('posts', $data, 'id = ?', [$id]);
            } else {
                $data['created_at'] = now();
                $data['author_id'] = (int)$u['id'];
                Db::insert('posts', $data);
            }
            foreach ($remove as $r) {
                delete_upload($r);
            }
            flash(match ($status) {
                'published' => 'Saved and published.',
                'pending' => $staff ? 'Saved, waiting for review.' : 'Submitted. An editor will review it.',
                default => 'Saved as draft.',
            });
            redirect('/admin/posts');
        }
        if ($act === 'delete' && $post) {
            $id = (int)($_POST['id'] ?? 0);
            $row = Db::row('SELECT * FROM posts WHERE id = ?', [$id]);
            if ($row && Roles::canEditPost($u, $row)) {
                delete_upload((string)$row['cover']);
                Db::delete('posts', 'id = ?', [$id]);
                flash('Deleted.');
            } else {
                flash('You can not delete this post.', 'error');
            }
            redirect('/admin/posts');
        }
        redirect('/admin/posts');
    }

    private static function postForm(array $row, int $id, array $errors): void
    {
        $role = (string)self::$user['role'];
        self::view('post_edit', [
            'row' => $row, 'id' => $id, 'errors' => $errors,
            'statuses' => Roles::postStatuses($role), 'canDate' => Roles::isStaff($role),
        ], $id ? 'Edit post' : 'New post');
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
        if ($post && $act === 'texts') {
            foreach (['results', 'sponsors', 'media', 'blog', 'faq'] as $sid) {
                Settings::setMany([
                    'sec_' . $sid . '_title' => mb_substr(trim((string)($_POST['title_' . $sid] ?? '')), 0, 80),
                    'sec_' . $sid . '_kicker' => mb_substr(trim((string)($_POST['kicker_' . $sid] ?? '')), 0, 80),
                    'sec_' . $sid . '_show_title' => !empty($_POST['show_title_' . $sid]) ? '1' : '0',
                    'sec_' . $sid . '_show_kicker' => !empty($_POST['show_kicker_' . $sid]) ? '1' : '0',
                ]);
            }
            foreach (Site::TIERS as $tier) {
                Settings::setMany([
                    'tier_' . $tier . '_label' => mb_substr(trim((string)($_POST['tier_label_' . $tier] ?? '')), 0, 80),
                    'tier_' . $tier . '_show' => !empty($_POST['tier_show_' . $tier]) ? '1' : '0',
                ]);
            }
            flash('Saved.');
            redirect('/admin/sections');
        }
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
        $texts = [];
        foreach (['results', 'sponsors', 'media', 'blog', 'faq'] as $sid) {
            $texts[$sid] = [
                'label' => t('sec.' . $sid),
                'title' => (string)Settings::get('sec_' . $sid . '_title', ''),
                'kicker' => (string)Settings::get('sec_' . $sid . '_kicker', ''),
                'defaultKicker' => $sid === 'blog' ? '' : t('kick.' . $sid),
                'show_title' => Settings::get('sec_' . $sid . '_show_title', '1') !== '0',
                'show_kicker' => Settings::get('sec_' . $sid . '_show_kicker', '1') !== '0',
            ];
        }
        $tiers = [];
        foreach (Site::TIERS as $tier) {
            $tiers[$tier] = [
                'label' => (string)Settings::get('tier_' . $tier . '_label', ''),
                'default' => t('tier.' . $tier),
                'show' => Settings::get('tier_' . $tier . '_show', '1') !== '0',
            ];
        }
        self::view('sections', ['rows' => $rows, 'texts' => $texts, 'tiers' => $tiers], 'Sections');
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

    /* ---------- statistics and cookie notice ---------- */

    private static function statistics(string $act, bool $post): void
    {
        $admin = self::$user['role'] === 'admin';
        if ($post) {
            if (!$admin) {
                flash('Only admins can change the statistics settings.', 'error');
                redirect('/admin/statistics');
            }
            if ($act === 'settings') {
                $d = (int)($_POST['stats_days'] ?? 90);
                Settings::setMany(['stats_on' => !empty($_POST['stats_on']) ? '1' : '0', 'stats_days' => (string)(in_array($d, [30, 90, 365], true) ? $d : 90)]);
                flash('Saved.');
            } elseif ($act === 'wipe') {
                Analytics::wipe();
                flash('All statistics were deleted.');
            }
            redirect('/admin/statistics');
        }
        $days = (int)($_GET['d'] ?? 30);
        if ($act === 'csv') {
            $what = in_array($_GET['what'] ?? '', ['pages', 'clicks'], true) ? (string)$_GET['what'] : 'days';
            $head = ['pages' => ['page', 'views', 'visitors'], 'clicks' => ['target', 'clicks'], 'days' => ['day', 'views', 'visitors']][$what];
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="statistics-' . $what . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, $head);
            foreach (Analytics::csv($what, $days) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
            exit;
        }
        if (Analytics::enabled()) {
            Analytics::prune();
        }
        self::view('statistics', [
            'r' => Analytics::report($days), 'on' => Analytics::enabled(), 'keep' => Analytics::retentionDays(), 'admin' => $admin,
        ], 'Statistics');
    }

    private static function notice(bool $post): void
    {
        if ($post) {
            Settings::setMany([
                'notice_on' => !empty($_POST['on']) ? '1' : '0',
                'notice_text' => mb_substr(trim((string)($_POST['text'] ?? '')), 0, 500),
                'notice_ok' => mb_substr(trim((string)($_POST['ok'] ?? '')), 0, 40),
                'notice_link' => !empty($_POST['link']) ? '1' : '0',
            ]);
            flash('Saved.');
            redirect('/admin/notice');
        }
        $n = Site::notice();
        self::view('notice', [
            'on' => $n['on'], 'text' => (string)Settings::get('notice_text', ''), 'ok' => (string)Settings::get('notice_ok', ''),
            'link' => Settings::get('notice_link', '1') !== '0', 'defaultText' => $n['text'], 'defaultOk' => t('notice.ok'),
            'hasPrivacy' => $n['link'] !== null, 'stats' => Analytics::enabled(),
        ], 'Cookie notice');
    }

    /* ---------- pages and legal pages ---------- */

    private static function pages(string $act, bool $post): void
    {
        if ($act === '' && !$post) {
            self::view('pages', ['rows' => Db::rows('SELECT * FROM pages ORDER BY pos, id')], 'Pages');
            return;
        }
        if ($act === 'edit' && !$post) {
            $id = (int)($_GET['id'] ?? 0);
            $row = $id ? Db::row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
            if ($id && !$row) {
                redirect('/admin/pages');
            }
            self::pageForm($row ?? ['title' => '', 'slug' => '', 'body' => '', 'status' => 'draft', 'in_footer' => 1, 'template' => ''], $id, []);
            return;
        }
        if ($act === 'save' && $post) {
            $id = (int)($_POST['id'] ?? 0);
            $old = $id ? Db::row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
            if ($id && !$old) {
                redirect('/admin/pages');
            }
            $title = trim((string)($_POST['title'] ?? ''));
            $slug = slugify(trim((string)($_POST['slug'] ?? '')) ?: $title);
            $errors = [];
            if ($title === '') {
                $errors[] = 'Title is required.';
            }
            if ($slug !== '' || $title !== '') {
                $err = Legal::slugError($slug, $id);
                if ($err !== null) {
                    $errors[] = $err;
                }
            }
            $data = [
                'title' => mb_substr($title, 0, 190),
                'slug' => $slug,
                'body' => str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')),
                'status' => ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft',
                'in_footer' => !empty($_POST['in_footer']) ? 1 : 0,
                'updated_at' => now(),
            ];
            if ($errors) {
                self::pageForm($data + ['template' => (string)($old['template'] ?? '')], $id, $errors);
                return;
            }
            if ($id) {
                Db::update('pages', $data, 'id = ?', [$id]);
            } else {
                $data['pos'] = (int)Db::val('SELECT COALESCE(MAX(pos), 0) FROM pages') + 1;
                Db::insert('pages', $data);
            }
            flash($data['status'] === 'published' ? 'Saved and published.' : 'Saved as draft. It is not visible to visitors yet.');
            redirect('/admin/pages');
        }
        if ($act === 'delete' && $post) {
            Db::delete('pages', 'id = ?', [(int)($_POST['id'] ?? 0)]);
            flash('Page deleted.');
            redirect('/admin/pages');
        }
        if ($act === 'move' && $post) {
            self::move('pages', (int)($_POST['id'] ?? 0), (string)($_POST['dir'] ?? ''));
            redirect('/admin/pages');
        }
        redirect('/admin/pages');
    }

    private static function pageForm(array $row, int $id, array $errors): void
    {
        self::view('page_edit', ['row' => $row, 'id' => $id, 'errors' => $errors], $id ? 'Edit page' : 'New page');
    }

    private static function legal(bool $post): void
    {
        $regions = Legal::regions();
        if ($post) {
            $region = (string)($_POST['region'] ?? '');
            if (!isset($regions[$region])) {
                flash('Choose where you are based first.', 'error');
                redirect('/admin/legal');
            }
            $langs = Legal::langs($region);
            $lang = (string)($_POST['lang'] ?? '');
            if (!in_array($lang, $langs, true)) {
                $lang = $langs[0] ?? 'en';
            }
            $t = static fn(string $k, int $max = 190): string => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
            $email = $t('email');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('The email address is not valid.', 'error');
                redirect('/admin/legal');
            }
            Settings::setMany([
                'legal_region' => $region, 'legal_lang' => $lang,
                'legal_type' => ($_POST['type'] ?? '') === 'private' ? 'private' : 'commercial',
                'legal_form' => ($_POST['form'] ?? '') === 'company' ? 'company' : 'person',
                'legal_name' => $t('name'), 'legal_street' => $t('street'), 'legal_city' => $t('city'), 'legal_country' => $t('country'),
                'legal_email' => $email, 'legal_phone' => $t('phone'), 'legal_register' => $t('register'), 'legal_vat' => $t('vat'),
                'legal_responsible' => $t('responsible'), 'legal_hosting' => $t('hosting'), 'legal_retention' => $t('retention', 60),
                'legal_other_services' => $t('other_services', 2000),
                'legal_f_contact_form' => !empty($_POST['f_contact_form']) ? '1' : '0',
                'legal_f_youtube' => !empty($_POST['f_youtube']) ? '1' : '0',
                'legal_f_sponsors' => !empty($_POST['f_sponsors']) ? '1' : '0',
            ]);
            if (!empty($_POST['create'])) {
                $need = ['name' => 'name', 'email' => 'email'];
                if ($region !== 'us') {
                    $need += ['street' => 'street', 'city' => 'postcode and city'];
                }
                $missing = [];
                foreach ($need as $k => $label) {
                    if ($t($k) === '') {
                        $missing[] = $label;
                    }
                }
                if ($missing) {
                    flash('Saved. Fill in your ' . implode(', ', $missing) . ' before the pages are created.', 'error');
                    redirect('/admin/legal');
                }
                $res = Legal::create($region, $lang, !empty($_POST['replace']));
                $parts = [];
                foreach (['created' => 'Created', 'updated' => 'Updated', 'skipped' => 'Left as they were (already exist)'] as $key => $label) {
                    if ($res[$key]) {
                        $parts[] = $label . ': ' . implode(', ', $res[$key]);
                    }
                }
                flash(($parts ? implode('. ', $parts) . '. ' : '') . 'The new pages are drafts. Read them, fix anything that does not fit, then publish them.');
                redirect('/admin/pages');
            }
            flash('Saved.');
            redirect('/admin/legal');
        }

        $g = static fn(string $k, string $d = ''): string => (string)Settings::get('legal_' . $k, $d);
        $region = $g('region');
        $langs = isset($regions[$region]) ? Legal::langs($region) : [];
        $lang = in_array($g('lang', Lang::code()), $langs, true) ? $g('lang', Lang::code()) : ($langs[0] ?? 'en');
        $status = [];
        foreach (Legal::kinds($region) as $kind) {
            [$slug, $title] = Legal::pageInfo($kind, $lang);
            $row = Db::row('SELECT status, template FROM pages WHERE slug = ?', [$slug]);
            $status[] = ['slug' => $slug, 'title' => $title, 'row' => $row];
        }
        self::view('legal', [
            'regions' => $regions, 'region' => $region, 'langs' => $langs, 'lang' => $lang, 'status' => $status,
            'v' => [
                'type' => $g('type', 'commercial'), 'form' => $g('form', 'person'),
                'name' => $g('name') ?: (string)Settings::get('name', ''), 'street' => $g('street'), 'city' => $g('city'), 'country' => $g('country'),
                'email' => $g('email') ?: (string)Settings::get('email', ''), 'phone' => $g('phone'), 'register' => $g('register'), 'vat' => $g('vat'),
                'responsible' => $g('responsible'), 'hosting' => $g('hosting'), 'retention' => $g('retention'), 'other_services' => $g('other_services'),
                'f_contact_form' => Legal::feature('contact_form'), 'f_youtube' => Legal::feature('youtube'), 'f_sponsors' => Legal::feature('sponsors'),
            ],
        ], 'Legal pages');
    }

    /* ---------- contact messages and email settings ---------- */

    private static function messages(string $act, bool $post): void
    {
        if ($post && $act === 'delete') {
            Db::delete('messages', 'id = ?', [(int)($_POST['id'] ?? 0)]);
            flash('Message deleted.');
            redirect('/admin/messages');
        }
        if ($act === 'view') {
            $m = Db::row('SELECT * FROM messages WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
            if (!$m) {
                redirect('/admin/messages');
            }
            if ($m['read_at'] === '') {
                Db::update('messages', ['read_at' => now()], 'id = ?', [(int)$m['id']]);
            }
            self::view('message', ['m' => $m], 'Message');
            return;
        }
        self::view('messages', [
            'rows' => Db::rows('SELECT * FROM messages ORDER BY id DESC LIMIT 200'),
            'hasEmail' => trim((string)Settings::get('email', '')) !== '',
        ], 'Messages');
    }

    private static function email(bool $post): void
    {
        if ($post) {
            $from = trim((string)($_POST['mail_from'] ?? ''));
            $port = (int)($_POST['smtp_port'] ?? 587);
            $secure = (string)($_POST['smtp_secure'] ?? 'tls');
            $errors = [];
            if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'The sender address is not a valid email address.';
            }
            if ($port < 1 || $port > 65535) {
                $errors[] = 'The port must be a number between 1 and 65535.';
            }
            if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
                $secure = 'tls';
            }
            if ($errors) {
                flash(implode(' ', $errors), 'error');
                redirect('/admin/email');
            }
            Settings::setMany([
                'mail_from' => $from,
                'mail_from_name' => mb_substr(trim((string)($_POST['mail_from_name'] ?? '')), 0, 100),
                'smtp_host' => trim((string)($_POST['smtp_host'] ?? '')),
                'smtp_port' => (string)$port,
                'smtp_secure' => $secure,
                'smtp_user' => trim((string)($_POST['smtp_user'] ?? '')),
            ]);
            if (!empty($_POST['clear_pass'])) {
                Settings::set('smtp_pass', '');
            } elseif ((string)($_POST['smtp_pass'] ?? '') !== '') {
                Settings::set('smtp_pass', (string)$_POST['smtp_pass']);
            }
            if (!empty($_POST['test'])) {
                [$ok, $err] = Mailer::send((string)self::$user['email'], 'Test email from Rider Page',
                    "This is a test email from your website.\nIf you can read it, the contact form can reach you.\n");
                flash($ok ? 'Saved. A test email was sent to ' . self::$user['email'] . '. Check your inbox and spam folder.' : 'Saved, but the test email failed: ' . $err, $ok ? 'ok' : 'error');
            } else {
                flash('Saved.');
            }
            redirect('/admin/email');
        }
        $get = static fn(string $k, string $d = ''): string => (string)Settings::get($k, $d);
        self::view('email', [
            'v' => [
                'mail_from' => $get('mail_from'), 'mail_from_name' => $get('mail_from_name'), 'smtp_host' => $get('smtp_host'),
                'smtp_port' => $get('smtp_port', '587'), 'smtp_secure' => $get('smtp_secure', 'tls'), 'smtp_user' => $get('smtp_user'),
            ],
            'hasPass' => $get('smtp_pass') !== '',
            'defaultFrom' => Mailer::from()[0],
            'recipient' => $get('email'),
        ], 'Email');
    }

    /* ---------- 404 page and redirects ---------- */

    private static function errorPage(bool $post): void
    {
        if ($post) {
            Settings::setMany([
                'e404_title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120),
                'e404_text' => mb_substr(trim((string)($_POST['text'] ?? '')), 0, 1000),
                'e404_posts' => !empty($_POST['posts']) ? '1' : '0',
            ]);
            flash('Saved.');
            redirect('/admin/errorpage');
        }
        self::view('errorpage', [
            'title' => (string)Settings::get('e404_title', ''),
            'text' => (string)Settings::get('e404_text', ''),
            'posts' => Settings::get('e404_posts', '1') === '1',
            'defaultTitle' => t('e404.title'),
            'defaultText' => t('e404.text'),
        ], '404 page');
    }

    private static function redirects(string $act, bool $post): void
    {
        $errors = [];
        $form = ['id' => 0, 'from' => '', 'to' => '', 'code' => 301];

        if ($post) {
            if ($act === 'save') {
                $id = (int)($_POST['id'] ?? 0);
                $form = [
                    'id' => $id, 'from' => trim((string)($_POST['from'] ?? '')),
                    'to' => trim((string)($_POST['to'] ?? '')), 'code' => (int)($_POST['code'] ?? 301),
                ];
                [$data, $err] = Redirects::prepare($form['from'], $form['to'], $form['code'], $id);
                if ($err === null && $data !== null) {
                    if ($id) {
                        Db::update('redirects', $data, 'id = ?', [$id]);
                    } else {
                        Db::insert('redirects', $data + ['hits' => 0, 'created_at' => now()]);
                    }
                    Db::delete('not_found', 'path = ?', [$data['from_path']]);
                    flash('Redirect saved: ' . $data['from_path'] . ' → ' . $data['to_url']);
                    redirect('/admin/redirects');
                }
                $errors[] = (string)$err;
            } elseif ($act === 'delete') {
                Db::delete('redirects', 'id = ?', [(int)($_POST['id'] ?? 0)]);
                flash('Redirect deleted.');
                redirect('/admin/redirects');
            } elseif ($act === 'dismiss') {
                Db::delete('not_found', 'path = ?', [(string)($_POST['path'] ?? '')]);
                redirect('/admin/redirects');
            } elseif ($act === 'clearlog') {
                Db::run('DELETE FROM not_found');
                flash('The list was cleared.');
                redirect('/admin/redirects');
            } else {
                redirect('/admin/redirects');
            }
        } else {
            $edit = (int)($_GET['edit'] ?? 0);
            if ($edit && ($row = Db::row('SELECT * FROM redirects WHERE id = ?', [$edit]))) {
                $form = ['id' => $edit, 'from' => (string)$row['from_path'], 'to' => (string)$row['to_url'], 'code' => (int)$row['code']];
            } elseif (isset($_GET['from'])) {
                $form['from'] = Redirects::normalize((string)$_GET['from']);
            }
        }

        self::view('redirects', [
            'errors' => $errors,
            'form' => $form,
            'rows' => Db::rows('SELECT * FROM redirects ORDER BY id DESC'),
            'log' => Db::rows('SELECT * FROM not_found ORDER BY hits DESC, last_seen DESC LIMIT 100'),
        ], 'Redirects');
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
            'history' => Changelog::entries(),
        ], 'Updates');
    }

    /* ---------- users ---------- */

    private static function users(string $act, bool $post): void
    {
        $me = (int)self::$user['id'];

        if ($post && $act === 'reset2fa') {
            $target = (int)($_POST['id'] ?? 0);
            if ($target !== $me && Users::find($target)) {
                TwoFactor::disable($target);
                Notify::event($target, '2fa_reset');
                flash('Two-factor login was switched off for that user. They can set it up again under Account.');
            }
            redirect('/admin/users');
        }
        if ($post && $act === 'policy') {
            Settings::set('require_2fa', !empty($_POST['require_2fa']) ? '1' : '0');
            flash(!empty($_POST['require_2fa']) ? 'Admins now have to use two-factor login.' : 'Two-factor login is no longer required.');
            redirect('/admin/users');
        }
        if ($post && $act === 'delete') {
            $err = Users::delete((int)($_POST['id'] ?? 0), $me);
            flash($err ?? 'User removed. Their posts now belong to you.', $err ? 'error' : 'ok');
            redirect('/admin/users');
        }
        if ($act === 'edit' || ($post && $act === 'save')) {
            $id = (int)($post ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));
            $current = $id ? Users::find($id) : null;
            if ($id && !$current) {
                redirect('/admin/users');
            }
            $errors = [];
            $form = $current ?? ['email' => '', 'name' => '', 'role' => 'editor', 'must_change' => 1];
            if ($post) {
                $in = [
                    'email' => trim((string)($_POST['email'] ?? '')),
                    'name' => trim((string)($_POST['name'] ?? '')),
                    'role' => (string)($_POST['role'] ?? ''),
                    'password' => (string)($_POST['password'] ?? ''),
                    'must_change' => !empty($_POST['must_change']),
                ];
                $errors = Users::validate($in, $id);
                if ($id === $me && $in['role'] !== 'admin') {
                    $errors[] = 'You can not take away your own admin rights. Ask another admin to do it.';
                }
                if (!$errors) {
                    Users::save($in, $id);
                    flash($id ? 'Saved.' : 'User added. Give them the address of this site, their email and the first password.');
                    redirect('/admin/users');
                }
                $form = ['email' => $in['email'], 'name' => $in['name'], 'role' => $in['role'], 'must_change' => $in['must_change'] ? 1 : 0];
            }
            self::view('user_form', ['id' => $id, 'form' => $form, 'errors' => $errors, 'self' => $id === $me], $id ? 'Edit user' : 'Add user');
            return;
        }
        $posts = [];
        foreach (Db::rows('SELECT author_id, COUNT(*) AS n FROM posts GROUP BY author_id') as $r) {
            $posts[(int)$r['author_id']] = (int)$r['n'];
        }
        self::view('users', ['users' => Users::all(), 'me' => $me, 'posts' => $posts, 'require2fa' => Settings::get('require_2fa', '0') === '1'], 'Users');
    }

    /* ---------- account ---------- */

    private static function account(bool $post): void
    {
        $errors = [];
        $must = (int)self::$user['must_change'] === 1;
        $uid = (int)self::$user['id'];
        if ($post && in_array((string)($_POST['do'] ?? ''), ['notify', '2fa_start', '2fa_confirm', '2fa_off', '2fa_codes'], true)) {
            self::twoFactor((string)$_POST['do'], $uid);
        }
        if ($post && ($_POST['do'] ?? '') === 'qr') {
            $on = !empty($_POST['qr_enabled']);
            Db::update('users', ['qr_enabled' => $on ? 1 : 0], 'id = ?', [$uid]);
            if (!$on) {
                QrLogin::revokeAll($uid);
            }
            flash($on ? 'QR login is on. You can now show a code below.' : 'QR login is off.');
            redirect('/admin/account');
        }
        if ($post && ($_POST['do'] ?? '') === 'logout') {
            Auth::revoke($uid, (int)($_POST['id'] ?? 0));
            flash('That device was logged out.');
            redirect('/admin/account');
        }
        if ($post && ($_POST['do'] ?? '') === 'logout_others') {
            Auth::revokeOthers($uid);
            flash('All other devices were logged out.');
            redirect('/admin/account');
        }
        if ($post) {
            $u = Db::row('SELECT * FROM users WHERE id = ?', [$uid]);
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 190);
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
            if ($must && $new === '') {
                $errors[] = 'Please choose a new password of your own.';
            }
            if (!$errors && Db::val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, (int)$u['id']]) > 0) {
                $errors[] = 'This email address is already used.';
            }
            if (!$errors) {
                $data = ['email' => $email, 'name' => $name];
                if ($new !== '') {
                    $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
                    $data['must_change'] = 0;
                }
                Db::update('users', $data, 'id = ?', [(int)$u['id']]);
                if ($new !== '') {
                    Auth::revokeOthers($uid);
                    QrLogin::revokeAll($uid);
                    Notify::event($uid, 'password_changed');
                }
                flash($new !== '' ? 'Saved. Your other devices were logged out.' : 'Saved.');
                redirect('/admin/account');
            }
        }
        $newCodes = $_SESSION['new_codes'] ?? null;
        unset($_SESSION['new_codes']);
        self::view('account', [
            'errors' => $errors,
            'email' => $post ? ($_POST['email'] ?? '') : self::$user['email'],
            'name' => $post ? ($_POST['name'] ?? '') : self::$user['name'],
            'must' => $must,
            'role' => Roles::ALL[self::$user['role']] ?? '',
            'qr' => (int)self::$user['qr_enabled'] === 1,
            'qrAvailable' => is_file(RP_ROOT . '/assets/qrcode.js'),
            'sessions' => $must ? [] : Auth::sessionsFor($uid),
            'twofa' => (int)self::$user['totp_enabled'] === 1,
            'setup' => isset($_SESSION['totp_pending']) ? [
                'secret' => (string)$_SESSION['totp_pending'],
                'uri' => Totp::uri((string)self::$user['email'], (string)(Settings::get('name', '') ?: 'Rider Page'), (string)$_SESSION['totp_pending']),
            ] : null,
            'newCodes' => $newCodes,
            'codesLeft' => TwoFactor::codesLeft($uid),
            'notify' => (int)self::$user['notify'] === 1,
            'require2fa' => Settings::get('require_2fa', '0') === '1' && self::$user['role'] === 'admin',
        ], 'Account');
    }

    /** Account page actions for two-factor login and email preferences. Always ends with a redirect. */
    private static function twoFactor(string $do, int $uid): never
    {
        $back = '/admin/account#2fa';
        if ($do === 'notify') {
            Db::update('users', ['notify' => !empty($_POST['notify']) ? 1 : 0], 'id = ?', [$uid]);
            flash('Saved.');
            redirect('/admin/account');
        }
        $u = Db::row('SELECT * FROM users WHERE id = ?', [$uid]);
        $pwOk = $u && password_verify((string)($_POST['current'] ?? ''), (string)$u['password_hash']);
        if ($do === '2fa_confirm') {
            $secret = (string)($_SESSION['totp_pending'] ?? '');
            $step = $secret !== '' ? Totp::verify($secret, (string)($_POST['code'] ?? '')) : null;
            if ($step === null) {
                flash('The code is not correct. Check that the time on your phone is right and try the next code.', 'error');
                redirect($back);
            }
            $_SESSION['new_codes'] = TwoFactor::enable($uid, $secret, $step);
            unset($_SESSION['totp_pending']);
            Notify::event($uid, '2fa_on');
            flash('Two-factor login is on. Save your recovery codes now.');
            redirect($back);
        }
        if (!$pwOk) {
            flash('The password is not correct.', 'error');
            redirect($back);
        }
        if ($do === '2fa_start') {
            $_SESSION['totp_pending'] = Totp::secret();
            redirect($back);
        }
        // Turning off or renewing the codes also needs a current code (an app code or a recovery code).
        if (!TwoFactor::check($uid, (string)($_POST['code'] ?? ''), true)) {
            flash('The code is not correct.', 'error');
            redirect($back);
        }
        if ($do === '2fa_off') {
            if (Settings::get('require_2fa', '0') === '1' && ($u['role'] ?? '') === 'admin') {
                flash('This site requires two-factor login for admins, so it can not be switched off.', 'error');
                redirect($back);
            }
            TwoFactor::disable($uid);
            Notify::event($uid, '2fa_off');
            flash('Two-factor login is off.');
        } else {
            $_SESSION['new_codes'] = TwoFactor::newCodes($uid);
            flash('New recovery codes were made. The old ones no longer work.');
        }
        redirect($back);
    }
}
