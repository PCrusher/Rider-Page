<?php /** Variables: $rows $hasEmail */ ?>
<h1>Messages</h1>
<p class="muted">Messages sent through the contact form on your page. Each one is also emailed to you; it is saved here so nothing gets lost if an email does not arrive.</p>
<?php if (!$hasEmail): ?><div class="box warn">No contact email is set, so messages are saved here but not emailed. Add one under <a href="<?= e(url('/admin/profile')) ?>">Profile</a>.</div><?php endif; ?>
<?php if (!$rows): ?>
  <div class="box muted">No messages yet.</div>
<?php else: ?>
<div class="tbl-wrap">
<table>
  <thead><tr><th>From</th><th>Message</th><th>Date</th><th>Email</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $m): ?>
    <tr>
      <td><?= $m['read_at'] === '' ? '<b>' . e($m['name']) . '</b>' : e($m['name']) ?><div class="muted small"><?= e($m['email']) ?></div></td>
      <td class="muted"><?= e(mb_strimwidth(str_replace("\n", ' ', (string)$m['body']), 0, 80, '…')) ?></td>
      <td class="muted small"><?= e(substr((string)$m['created_at'], 0, 16)) ?></td>
      <td><span class="pill <?= $m['mail_status'] === 'sent' ? 'ok' : 'warn' ?>"><?= $m['mail_status'] === 'sent' ? 'Sent' : 'Not sent' ?></span></td>
      <td class="act">
        <a class="btn sm" href="<?= e(url('/admin/messages/view?id=' . (int)$m['id'])) ?>">Open</a>
        <form method="post" action="<?= e(url('/admin/messages/delete')) ?>" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
