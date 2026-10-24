<?php
/** Variables: $content $title $section $flashes $user $version $entities */
$role = (string)$user['role'];
$items = [
    ['', 'Dashboard', '/admin', ''],
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
    ['users', 'Users', '/admin/users', 'System'],
    ['email', 'Email', '/admin/email', 'System'],
    ['update', 'Updates', '/admin/update', 'System'],
    ['account', 'Account', '/admin/account', 'System'],
];
$items = array_values(array_filter($items, static fn($i) => Roles::can($role, $i[0])));
$lastGroup = '';
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
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="brand">Rider Page</div>
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
  </aside>
  <main class="main">
    <?php foreach ($flashes as $f): ?><div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
