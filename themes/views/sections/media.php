<section class="sec<?= $alt ? ' alt' : '' ?>" id="media">
  <div class="in">
    <div class="sec-h"><h2><?= e(t('sec.media')) ?></h2><span class="kicker"><?= e(t('kick.media')) ?></span></div>
    <?php if ($p['video_id']): ?>
      <div>
        <?= Markdown::video($p['video_id']) ?>
        <p class="cap">
          <?php if ($p['video_caption'] !== ''): ?><?= e($p['video_caption']) ?> &middot; <?php endif; ?>
          <a href="https://www.youtube.com/watch?v=<?= e($p['video_id']) ?>" target="_blank" rel="noopener"><?= e(t('video.open')) ?></a>
        </p>
      </div>
    <?php endif; ?>
    <?php if ($gallery): ?>
      <div class="gallery">
        <?php foreach ($gallery as $g): ?>
          <figure class="gal">
            <a class="ph" href="<?= e(url($g['image'])) ?>" target="_blank" rel="noopener">
              <img src="<?= e(url(Images::thumb($g['image']))) ?>" alt="<?= e($g['caption'] !== '' ? $g['caption'] : $p['name']) ?>" loading="lazy">
            </a>
            <?= credit_html((string)($g['credit'] ?? ''), (string)($g['credit_url'] ?? ''), 'credit-tag') ?>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
