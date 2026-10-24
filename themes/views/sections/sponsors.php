<?php
$classes = ['a', 'b', 'c', 'd'];
$grids = ['title' => 'two', 'partner' => '', 'supporter' => 'four'];
$i = 0;
?>
<section class="sec<?= $alt ? ' alt' : '' ?>" id="sponsors">
  <div class="in">
    <?= Site::head($titles, 'sponsors') ?>
    <?php foreach ($tiers as $tier => $list): ?>
      <?php if (!$list) { continue; } ?>
      <div>
        <?php if ($tierLabels[$tier]['show']): ?><p class="tier"><?= e($tierLabels[$tier]['label']) ?></p><?php endif; ?>
        <div class="sponsors <?= e($grids[$tier]) ?>">
          <?php foreach ($list as $s): ?>
            <?php
            $tag = $s['url'] !== '' ? 'a' : 'div';
            $attr = $s['url'] !== '' ? ' href="' . e($s['url']) . '" target="_blank" rel="noopener" data-t="sponsor:' . e($s['name']) . '"' : '';
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
