<section class="sec<?= $alt ? ' alt' : '' ?>" id="contact">
  <div class="in">
    <div class="contact">
      <div>
        <h2><?= e($p['contact_title']) ?></h2>
        <p><?= e($p['contact_text']) ?></p>
      </div>
      <div class="mail">
        <code><?= e($p['email']) ?></code>
        <button type="button" class="btn" data-copy="<?= e($p['email']) ?>"><?= e(t('contact.copy')) ?></button>
        <a class="btn" href="mailto:<?= e($p['email']) ?>"><?= e(t('contact.write')) ?></a>
      </div>
    </div>
  </div>
</section>
