<?php
$classes = ['a', 'b', 'c', 'd'];
$grids = ['title' => 'two', 'partner' => '', 'supporter' => 'four'];
$i = 0;
?>
<section class="sec<?= $alt ? ' alt' : '' ?>" id="sponsors">
  <div class="in">
    <div class="sec-h"><h2><?= e(t('sec.sponsors')) ?></h2><span class="kicker"><?= e(t('kick.sponsors')) ?></span></div>
    <?php foreach ($tiers as $tier => $list): ?>
      <?php if (!$list) { continue; } ?>
      <div>
        <p class="tier"><?= e(t('tier.' . $tier)) ?></p>
        <div class="sponsors <?= e($grids[$tier]) ?>">
          <?php foreach ($list as $s): ?>
            <?php
            $tag = $s['url'] !== '' ? 'a' : 'div';
            $attr = $s['url'] !== '' ? ' href="' . e($s['url']) . '" target="_blank" rel="noopener"' : '';
            ?>
            <<?= $tag ?> class="sp"<?= $attr ?>>
              <?php if ($s['logo'] !== ''): ?>
                <img class="logo" src="<?= e(url($s['logo'])) ?>" alt="<?= e($s['name']) ?>" loading="lazy">
              <?php else: ?>
                <span class="wm <?= $classes[$i++ % 4] ?>"><?= e($s['name']) ?></span>
              <?php endif; ?>
            </<?= $tag ?>>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
