<?php
/** Variables: $content $title $section $flashes $user $version $entities */
$role = (string)$user['role'];
$items = [
    ['', 'Dashboard', '/admin', ''],
    ['statistics', 'Statistics', '/admin/statistics', ''],
    ['profile', 'Profile', '/admin/profile', 'Content'],
    ['socials', 'Social accounts', '/admin/socials', 'Content'],
    ['stats', 'Results', '/admin/stats', 'Content'],
    ['sponsors', 'Partners', '/admin/sponsors', 'Content'],
    ['media', 'Photos', '/admin/media', 'Content'],
    ['messages', 'Messages', '/admin/messages', 'Content'],
    ['posts', 'Blog', '/admin/posts', 'Content'],
    ['faqs', 'FAQ', '/admin/faqs', 'Content'],
    ['pages', 'Pages', '/admin/pages', 'Content'],
    ['legal', 'Legal pages', '/admin/legal', 'Content'],
    ['sections', 'Sections', '/admin/sections', 'Page'],
    ['appearance', 'Appearance', '/admin/appearance', 'Page'],
    ['maintenance', 'Maintenance', '/admin/maintenance', 'Page'],
    ['errorpage', '404 page', '/admin/errorpage', 'Page'],
    ['redirects', 'Redirects', '/admin/redirects', 'Page'],
    ['notice', 'Cookie notice', '/admin/notice', 'Page'],
    ['users', 'Users', '/admin/users', 'System'],
    ['email', 'Email', '/admin/email', 'System'],
    ['update', 'Updates', '/admin/update', 'System'],
    ['account', 'Account', '/admin/account', 'System'],
];
$items = array_values(array_filter($items, static fn($i) => Roles::can($role, $i[0])));
$lastGroup = '';
$current = 'Dashboard';
foreach ($items as $it) {
    if ($it[0] === $section) {
        $current = $it[1];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> – Rider Page</title>
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
<script src="<?= e(asset('admin.js')) ?>" defer></script>
<noscript><style>.side-nav { display: flex !important; } .menu-btn { display: none !important; }</style></noscript>
</head>
<body>
<div class="app">
  <aside class="side" id="side">
    <div class="side-top">
      <a class="brand" href="<?= e(url('/admin')) ?>">Rider Page</a>
      <span class="cur"><?= e($current) ?></span>
      <button class="menu-btn" type="button" aria-expanded="false" aria-controls="side-nav"><span class="bars" aria-hidden="true"></span><span>Menu</span></button>
    </div>
    <nav class="side-nav" id="side-nav" aria-label="Admin menu">
      <?php foreach ($items as [$key, $label, $href, $group]): ?>
        <?php if ($group !== '' && $group !== $lastGroup): $lastGroup = $group; ?><div class="grp"><?= e($group) ?></div><?php endif; ?>
        <a class="nav" href="<?= e(url($href)) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <div class="foot">
        <a class="nav" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View site ↗</a>
        <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="nav" type="submit">Log out</button></form>
        <div class="muted small" style="padding:8px 10px 0"><?= e(Users::label($user)) ?> &middot; <?= e(Roles::ALL[$role] ?? '') ?></div>
        <div class="muted small" style="padding:0 10px 8px">Version <?= e($version) ?></div>
      </div>
    </nav>
  </aside>
  <main class="main">
    <?php foreach ($flashes as $f): ?><div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
