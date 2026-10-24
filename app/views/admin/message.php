<?php /** Variables: $m */ ?>
<p><a href="<?= e(url('/admin/messages')) ?>">← All messages</a></p>
<h1>Message from <?= e($m['name']) ?></h1>
<p class="muted"><?= e($m['email']) ?> &middot; <?= e((string)$m['created_at']) ?></p>
<div class="box" style="white-space:pre-wrap"><?= e((string)$m['body']) ?></div>
<?php if ($m['mail_status'] !== 'sent'): ?>
  <div class="box warn">This message was <b>not emailed to you</b>: <?= e($m['mail_error'] !== '' ? (string)$m['mail_error'] : 'unknown reason') ?>. Check <a href="<?= e(url('/admin/email')) ?>">Email settings</a>.</div>
<?php endif; ?>
<div class="actions">
  <a class="btn primary" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: your message')) ?>">Reply by email</a>
  <form method="post" action="<?= e(url('/admin/messages/delete')) ?>" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn danger" type="submit">Delete</button></form>
</div>
