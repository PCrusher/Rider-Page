<?php
/** Variables: $p $socials $hasContact $sectionsHtml */
$parts = preg_split('/\s+/', trim($p['name']), 2) ?: [$p['name']];
$types = social_types();
?>
<header class="hero" id="top">
  <div class="in hero-in">
    <div class="hero-copy">
      <?php if ($p['tagline'] !== ''): ?><span class="eyebrow"><?= e($p['tagline']) ?></span><?php endif; ?>
      <h1><span class="n1"><?= e($parts[0]) ?></span><?php if (!empty($parts[1])): ?> <span class="n2"><?= e($parts[1]) ?></span><?php endif; ?></h1>
      <?php if ($p['bio'] !== ''): ?><p class="lede"><?= nl2br(e($p['bio'])) ?></p><?php endif; ?>
      <?php if ($socials): ?>
        <div class="socials">
          <?php foreach ($socials as $s): ?>
            <a class="chip" href="<?= e($s['url']) ?>" target="_blank" rel="me noopener"><?= e($types[$s['type']] ?? ucfirst((string)$s['type'])) ?><?php if ($s['handle'] !== ''): ?> <small><?= e($s['handle']) ?></small><?php endif; ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($hasContact): ?><a class="btn primary" href="#contact"><?= e(t('cta.work')) ?></a><?php endif; ?>
    </div>
    <div class="ph hero-photo<?= $p['hero'] === '' ? ' empty' : '' ?>">
      <?php if ($p['hero'] !== ''): ?><img src="<?= e(url($p['hero'])) ?>" alt="<?= e($p['name']) ?>" fetchpriority="high"><?php endif; ?>
      <?php if ($p['sticker'] !== ''): ?><div class="sticker"><?= e($p['sticker']) ?></div><?php endif; ?>
    </div>
  </div>
</header>
<div class="sections"><?= $sectionsHtml ?></div>
