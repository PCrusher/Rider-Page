<?php /** Variables: $p $alt $contactState $sent $formToken */ ?>
<section class="sec<?= $alt ? ' alt' : '' ?>" id="contact">
  <div class="in">
    <div class="contact">
      <div class="c-text">
        <h2><?= e($p['contact_title']) ?></h2>
        <p><?= e($p['contact_text']) ?></p>
      </div>
      <form class="c-form" method="post" action="<?= e(url('/contact')) ?>">
        <?php if ($sent): ?><p class="form-note" role="status"><?= e(t('contact.sent')) ?></p><?php endif; ?>
        <?php if ($contactState['error'] !== ''): ?><p class="form-note err" role="alert"><?= e(t('contact.err.' . $contactState['error'])) ?></p><?php endif; ?>
        <label for="c-name"><?= e(t('contact.name')) ?></label>
        <input type="text" id="c-name" name="name" value="<?= e($contactState['values']['name']) ?>" maxlength="100" autocomplete="name" required>
        <label for="c-email"><?= e(t('contact.email')) ?></label>
        <input type="email" id="c-email" name="email" value="<?= e($contactState['values']['email']) ?>" maxlength="190" autocomplete="email" required>
        <label for="c-message"><?= e(t('contact.message')) ?></label>
        <textarea id="c-message" name="message" rows="5" minlength="10" maxlength="5000" required><?= e($contactState['values']['message']) ?></textarea>
        <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <input type="hidden" name="t" value="<?= e($formToken[0]) ?>">
        <input type="hidden" name="s" value="<?= e($formToken[1]) ?>">
        <button class="btn" type="submit"><?= e(t('contact.send')) ?></button>
        <p class="c-note"><?= e(t('contact.note')) ?></p>
      </form>
    </div>
  </div>
</section>
