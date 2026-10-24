<?php /** Variables: $errors $form $rows $log */ ?>
<h1>Redirects</h1>
<p class="muted">Send an old address to a new one, for example after you renamed a blog post. Visitors and search engines then end up on the right page and keep their ranking.</p>

<?php if ($errors): ?><div class="box bad"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" action="<?= e(url('/admin/redirects/save')) ?>" class="box" id="add">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
  <b><?= $form['id'] ? 'Edit redirect' : 'Add a redirect' ?></b>
  <div class="row2">
    <div>
      <label class="f" for="from">Old address</label>
      <input type="text" id="from" name="from" value="<?= e($form['from']) ?>" placeholder="/old-page" required>
      <p class="hint">The part after your domain, for example <code>/blog/old-title</code>.</p>
    </div>
    <div>
      <label class="f" for="to">New address</label>
      <input type="text" id="to" name="to" value="<?= e($form['to']) ?>" placeholder="/blog/new-title" required>
      <p class="hint">A page on this site (<code>/blog/new-title</code>) or a full web address (<code>https://…</code>).</p>
    </div>
  </div>
  <label class="f" for="code">Type</label>
  <select id="code" name="code">
    <option value="301"<?= (int)$form['code'] !== 302 ? ' selected' : '' ?>>Permanent (301) – the page moved for good</option>
    <option value="302"<?= (int)$form['code'] === 302 ? ' selected' : '' ?>>Temporary (302) – for a short time only</option>
  </select>
  <div class="actions">
    <button class="btn primary" type="submit"><?= $form['id'] ? 'Save redirect' : 'Add redirect' ?></button>
    <?php if ($form['id']): ?><a class="btn" href="<?= e(url('/admin/redirects')) ?>">Cancel</a><?php endif; ?>
  </div>
</form>

<h2>Your redirects</h2>
<?php if (!$rows): ?>
  <div class="box muted">No redirects yet.</div>
<?php else: ?>
<div class="tbl-wrap">
<table>
  <thead><tr><th>Old address</th><th>New address</th><th>Used</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><code><?= e($r['from_path']) ?></code></td>
      <td><?= e(mb_strimwidth((string)$r['to_url'], 0, 60, '…')) ?> <span class="pill"><?= (int)$r['code'] ?></span></td>
      <td class="muted small"><?= (int)$r['hits'] ?>×</td>
      <td class="act">
        <a class="btn sm" href="<?= e(url('/admin/redirects?edit=' . (int)$r['id'])) ?>#add">Edit</a>
        <form method="post" action="<?= e(url('/admin/redirects/delete')) ?>" data-confirm="Delete this redirect?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<h2>Addresses that were not found</h2>
<p class="muted small">Addresses visitors or search engines asked for that do not exist, most requested first. Many of these are harmless bots. Only the address is saved, no personal data. At most 500 are kept.</p>
<?php if (!$log): ?>
  <div class="box muted">Nothing yet.</div>
<?php else: ?>
<div class="tbl-wrap">
<table>
  <thead><tr><th>Address</th><th>Asked</th><th>Last</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($log as $l): ?>
    <tr>
      <td><code><?= e($l['path']) ?></code></td>
      <td class="muted small"><?= (int)$l['hits'] ?>×</td>
      <td class="muted small"><?= e(substr((string)$l['last_seen'], 0, 10)) ?></td>
      <td class="act">
        <a class="btn sm" href="<?= e(url('/admin/redirects?from=' . rawurlencode((string)$l['path']))) ?>#add">Create redirect</a>
        <form method="post" action="<?= e(url('/admin/redirects/dismiss')) ?>"><?= csrf_field() ?><input type="hidden" name="path" value="<?= e($l['path']) ?>"><button class="btn sm" type="submit">Dismiss</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<form method="post" action="<?= e(url('/admin/redirects/clearlog')) ?>" data-confirm="Clear the whole list?" style="margin-top:12px"><?= csrf_field() ?><button class="btn sm" type="submit">Clear list</button></form>
<?php endif; ?>
