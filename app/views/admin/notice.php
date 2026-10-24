<?php /** Variables: $on $text $ok $link $defaultText $defaultOk $hasPrivacy $stats */ ?>
<h1>Cookie notice</h1>
<p class="muted">A small bar at the bottom of your site that tells visitors how the site treats cookies and data. Visitors close it with one click, and the browser remembers that (without a cookie).</p>
<div class="box">
  <b>Do you need it?</b>
  <p class="muted small" style="margin:6px 0 0">As this site is built, <b>no consent banner is required</b>: it sets no cookies for visitors, loads no fonts or scripts from other companies, and YouTube only loads after a click<?= $stats ? ', and the statistics use no cookies and no IP address' : '' ?>. This notice is therefore optional and only <i>informs</i>; it has no Accept or Decline choice because there is nothing to decide. If you later add something that does need consent (advertising, tracking tools, embeds that load before a click), this bar is not enough. You would need a real consent banner first.</p>
</div>

<form method="post" action="<?= e(url('/admin/notice')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="check" style="margin-top:0"><input type="checkbox" name="on" value="1"<?= $on ? ' checked' : '' ?>> Show the notice on the site</label>

  <label class="f" for="text">Text</label>
  <textarea id="text" name="text" rows="3" maxlength="500" placeholder="<?= e($defaultText) ?>"><?= e($text) ?></textarea>
  <p class="hint">Leave empty to use the standard text shown in grey. It follows the language of the site and mentions the statistics when they are switched on.</p>

  <label class="f" for="ok">Button</label>
  <input type="text" id="ok" name="ok" value="<?= e($ok) ?>" placeholder="<?= e($defaultOk) ?>" maxlength="40" style="max-width:16rem">

  <label class="check"><input type="checkbox" name="link" value="1"<?= $link ? ' checked' : '' ?>> Link to the privacy policy</label>
  <p class="hint"><?= $hasPrivacy ? 'The link goes to your published privacy policy.' : 'No privacy policy is published yet, so no link is shown. Create one under <a href="' . e(url('/admin/legal')) . '">Legal pages</a>.' ?></p>

  <div class="actions"><button class="btn primary" type="submit">Save</button> <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Open my site ↗</a></div>
  <p class="hint">After changing the text, the notice shows again to everyone who has closed the old one.</p>
</form>
