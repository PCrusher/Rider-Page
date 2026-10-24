<section class="sec<?= $alt ? ' alt' : '' ?>" id="results">
  <div class="in">
    <?= Site::head($titles, 'results') ?>
    <div class="stats">
      <?php foreach ($stats as $s): ?>
        <div class="stat"><b><?= e($s['figure']) ?></b><span><?= e($s['label']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
