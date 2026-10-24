<?php
/**
 * Page shell: head, sticky menu, footer.
 * Variables: $title $description $canonical $image $ogType $jsonld $noindex $isHome $content
 *            $p (profile) $nav (section ids) $hasContact $hasBlog $design
 */
$homeUrl = url('/');
?>
<!doctype html>
<html lang="<?= e(Lang::code()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if ($noindex): ?><meta name="robots" content="noindex">
<?php endif; ?>
<?php if ($canonical !== ''): ?><link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<?php if ($description !== ''): ?><meta property="og:description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if ($image !== ''): ?><meta property="og:image" content="<?= e($image) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="<?= e(asset('fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('site.css')) ?>">
<style nonce="<?= e(rp_nonce()) ?>"><?= Presets::css() ?></style>
<?php if ($jsonld): ?><script type="application/ld+json"><?= json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<script src="<?= e(asset('site.js')) ?>" defer data-copied="<?= e(t('contact.copied')) ?>" data-load="<?= e(t('video.play')) ?>"></script>
</head>
<body>
<div class="site" data-design="<?= e($design) ?>" id="top-of-site">
  <a class="skip" href="#main"><?= e(t('skip')) ?></a>
  <?php if (!empty($adminBanner)): ?>
    <div class="maint-bar">Maintenance mode is on. Visitors see the maintenance page; you see the real site because you are logged in. <a href="<?= e(url('/admin/maintenance')) ?>">Turn it off</a></div>
  <?php endif; ?>
  <nav class="snav" aria-label="<?= e($p['name']) ?>">
    <div class="in">
      <a class="mark" href="<?= e($isHome ? '#top' : $homeUrl) ?>"><?= e($p['name']) ?></a>
      <?php if (empty($maintenance)): ?>
      <ul>
        <?php foreach ($nav as $id): ?>
          <?php $href = $id === 'blog' && !$isHome ? url('/blog') : ($isHome ? '' : $homeUrl) . '#' . $id; ?>
          <li><a class="l" href="<?= e($href) ?>" data-sec="<?= e($id) ?>"><?= e($titles[$id]['title']) ?></a></li>
        <?php endforeach; ?>
        <?php if ($hasContact): ?>
          <li><a class="btn primary" href="<?= e(($isHome ? '' : $homeUrl) . '#contact') ?>"><?= e(t('cta.inquiry')) ?></a></li>
        <?php endif; ?>
      </ul>
      <?php endif; ?>
    </div>
  </nav>
  <main id="main"><?= $content ?></main>
  <div class="in"><div class="foot"><span>© <?= date('Y') ?> <?= e($p['name']) ?></span><span><?= e(t('foot.built')) ?></span></div></div>
</div>
</body>
</html>
