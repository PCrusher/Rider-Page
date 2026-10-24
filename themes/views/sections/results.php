<section class="sec<?= $alt ? ' alt' : '' ?>" id="results">
  <div class="in">
    <div class="sec-h"><h2><?= e(t('sec.results')) ?></h2><span class="kicker"><?= e(t('kick.results')) ?></span></div>
    <div class="stats">
      <?php foreach ($stats as $s): ?>
        <div class="stat"><b><?= e($s['figure']) ?></b><span><?= e($s['label']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
