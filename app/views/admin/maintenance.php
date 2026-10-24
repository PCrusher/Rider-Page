<?php /** Variables: $on $title $text $defaultTitle $defaultText */ ?>
<h1>Maintenance</h1>
<p class="muted">Take your page offline for visitors while you work on it. You stay logged in and still see the real site, with a yellow bar on top as a reminder.</p>

<div class="box <?= $on ? 'bad' : '' ?>">
  <b>Status: <?= $on ? 'Maintenance mode is ON' : 'Your page is live' ?></b>
  <p class="small" style="margin:6px 0 0"><?= $on
      ? 'Visitors see the maintenance page. Search engines are told to come back later (HTTP 503), so your ranking is not harmed by a short break.'
      : 'Turn it on when you do larger changes, or while a new design or version is being set up.' ?></p>
</div>

<form method="post" action="<?= e(url('/admin/maintenance')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="f" for="title" style="margin-top:0">Heading on the maintenance page</label>
  <input type="text" id="title" name="title" value="<?= e($title) ?>" placeholder="<?= e($defaultTitle) ?>" maxlength="120">
  <label class="f" for="text">Message</label>
  <textarea id="text" name="text" rows="3" maxlength="1000" placeholder="<?= e($defaultText) ?>"><?= e($text) ?></textarea>
  <p class="hint">Leave a field empty to use the default text shown in grey.</p>
  <div class="actions">
    <?php if ($on): ?>
      <button class="btn primary" type="submit" name="toggle" value="off">Turn maintenance mode off</button>
    <?php else: ?>
      <button class="btn danger" type="submit" name="toggle" value="on" data-confirm="Visitors will see the maintenance page until you turn this off. Continue?">Turn maintenance mode on</button>
    <?php endif; ?>
    <button class="btn" type="submit">Save texts only</button>
  </div>
</form>
