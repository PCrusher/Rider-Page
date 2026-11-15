<?php /** Variables: $rows $hasEmail $tab $spamCount */ ?>
<h1>Messages</h1>
<div class="actions">
  <a class="btn<?= $tab === 'inbox' ? ' primary' : '' ?>" href="<?= e(url('/admin/messages')) ?>">Inbox</a>
  <a class="btn<?= $tab === 'spam' ? ' primary' : '' ?>" href="<?= e(url('/admin/messages?tab=spam')) ?>">Spam<?= $spamCount > 0 ? ' (' . (int)$spamCount . ')' : '' ?></a>
</div>
<?php if ($tab === 'spam'): ?>
<p class="muted">Messages that looked like spam (sales wording, links, throwaway addresses, the same text from several senders, or a form that was not filled in by a browser). They were <b>not emailed</b> to you and are deleted after 30 days. If one is real, press <i>Not spam</i>: it moves to the inbox and is emailed to you.</p>
<?php else: ?>
<p class="muted">Messages sent through the contact form on your page. Each one is also emailed to you; it is saved here so nothing gets lost if an email does not arrive.</p>
<?php if (!$hasEmail): ?><div class="box warn">No contact email is set, so messages are saved here but not emailed. Add one under <a href="<?= e(url('/admin/profile')) ?>">Profile</a>.</div><?php endif; ?>
<?php endif; ?>
<?php if (!$rows): ?>
  <div class="box muted"><?= $tab === 'spam' ? 'No spam caught.' : 'No messages yet.' ?></div>
<?php else: ?>
<?php if ($tab === 'spam'): ?>
  <form method="post" action="<?= e(url('/admin/messages/deletespam')) ?>" data-confirm="Delete all spam messages?"><?= csrf_field() ?><button class="btn danger" type="submit">Delete all spam</button></form>
<?php endif; ?>
<div class="tbl-wrap">
<table>
  <thead><tr><th>From</th><th>Message</th><th>Date</th><th><?= $tab === 'spam' ? 'Why' : 'Email' ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $m): ?>
    <tr>
      <td><?= $m['read_at'] === '' && $tab === 'inbox' ? '<b>' . e($m['name']) . '</b>' : e($m['name']) ?><div class="muted small"><?= e($m['email']) ?></div></td>
      <td class="muted"><?= e(mb_strimwidth(str_replace("\n", ' ', (string)$m['body']), 0, 80, '…')) ?></td>
      <td class="muted small"><?= e(substr((string)$m['created_at'], 0, 16)) ?></td>
      <?php if ($tab === 'spam'): ?>
        <td class="muted small"><?= e((string)$m['spam_reasons']) ?></td>
      <?php else: ?>
        <td><span class="pill <?= $m['mail_status'] === 'sent' ? 'ok' : 'warn' ?>"><?= $m['mail_status'] === 'sent' ? 'Sent' : 'Not sent' ?></span></td>
      <?php endif; ?>
      <td class="act">
        <a class="btn sm" href="<?= e(url('/admin/messages/view?id=' . (int)$m['id'])) ?>">Open</a>
        <?php if ($tab === 'spam'): ?>
        <form method="post" action="<?= e(url('/admin/messages/notspam')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn sm" type="submit">Not spam</button></form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/admin/messages/delete')) ?>" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
