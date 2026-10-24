<?php /** Variables: $title $text $posts $defaultTitle $defaultText */ ?>
<h1>404 page</h1>
<p class="muted">What visitors see when they open an address that does not exist, for example a mistyped link. The menu and design of your site are used automatically.</p>
<form method="post" action="<?= e(url('/admin/errorpage')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="f" for="title" style="margin-top:0">Heading</label>
  <input type="text" id="title" name="title" value="<?= e($title) ?>" placeholder="<?= e($defaultTitle) ?>" maxlength="120">
  <label class="f" for="text">Message</label>
  <textarea id="text" name="text" rows="3" maxlength="1000" placeholder="<?= e($defaultText) ?>"><?= e($text) ?></textarea>
  <p class="hint">Leave a field empty to use the default text shown in grey.</p>
  <label class="check"><input type="checkbox" name="posts" value="1"<?= $posts ? ' checked' : '' ?>> Show the latest blog posts below the message</label>
  <div class="actions">
    <button class="btn primary" type="submit">Save</button>
    <a href="<?= e(url('/__preview-404')) ?>" target="_blank" rel="noopener">Preview the page ↗</a>
  </div>
</form>
<p class="hint">Visitors who land here are also collected under <a href="<?= e(url('/admin/redirects')) ?>">Redirects</a>, so you can send them to the right page next time.</p>
