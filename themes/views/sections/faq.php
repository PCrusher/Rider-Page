<section class="sec<?= $alt ? ' alt' : '' ?>" id="faq">
  <div class="in">
    <div class="sec-h"><h2><?= e(t('sec.faq')) ?></h2><span class="kicker"><?= e(t('kick.faq')) ?></span></div>
    <div class="faq">
      <?php foreach ($faqs as $i => $f): ?>
        <details<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($f['question']) ?></summary>
          <p><?= nl2br(e($f['answer'])) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
