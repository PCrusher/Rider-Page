<?php
/** Variables: $content $title $section $flashes $user $version $entities */
$items = [
    ['', 'Dashboard', '/admin', null],
    ['profile', 'Profile', '/admin/profile', 'Content'],
    ['socials', 'Social accounts', '/admin/socials', null],
    ['stats', 'Results', '/admin/stats', null],
    ['sponsors', 'Partners', '/admin/sponsors', null],
    ['media', 'Photos', '/admin/media', null],
    ['posts', 'Blog', '/admin/posts', null],
    ['faqs', 'FAQ', '/admin/faqs', null],
    ['sections', 'Sections', '/admin/sections', 'Page'],
    ['appearance', 'Appearance', '/admin/appearance', null],
    ['update', 'Updates', '/admin/update', 'System'],
    ['account', 'Account', '/admin/account', null],
];
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
      <?php if ($group): ?><div class="grp"><?= e($group) ?></div><?php endif; ?>
      <a class="nav" href="<?= e(url($href)) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="foot">
      <a class="nav" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View site ↗</a>
      <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="nav" type="submit">Log out</button></form>
      <div class="muted small" style="padding:8px 10px">Version <?= e($version) ?></div>
    </div>
  </aside>
  <main class="main">
    <?php foreach ($flashes as $f): ?><div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
