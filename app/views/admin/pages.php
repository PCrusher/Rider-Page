<?php /** Variables: $rows */ $n = count($rows); ?>
<h1>Pages</h1>
<p class="muted">Your own pages, such as the legal notice, the privacy policy or an "About" page. Published pages can be linked in the footer of every page. For the legal pages there is a guided set-up under <a href="<?= e(url('/admin/legal')) ?>">Legal pages</a>.</p>
<p><a class="btn primary" href="<?= e(url('/admin/pages/edit')) ?>">New page</a> <a class="btn" href="<?= e(url('/admin/legal')) ?>">Set up legal pages</a></p>
<?php if (!$rows): ?>
  <div class="box muted">No pages yet.</div>
<?php else: ?>
<div class="tbl-wrap">
<table>
  <thead><tr><th>Title</th><th>Address</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td><?= e($r['title']) ?><?= $r['template'] !== '' ? ' <span class="pill">from template</span>' : '' ?></td>
      <td class="muted small"><code>/<?= e($r['slug']) ?></code><?= (int)$r['in_footer'] === 1 ? ' · in footer' : '' ?></td>
      <td><span class="pill <?= $r['status'] === 'published' ? 'ok' : 'warn' ?>"><?= $r['status'] === 'published' ? 'Published' : 'Draft' ?></span></td>
      <td class="act">
        <form method="post" action="<?= e(url('/admin/pages/move')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="dir" value="up"><button class="btn sm" type="submit" aria-label="Move up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
        <form method="post" action="<?= e(url('/admin/pages/move')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="dir" value="down"><button class="btn sm" type="submit" aria-label="Move down"<?= $i === $n - 1 ? ' disabled' : '' ?>>↓</button></form>
        <?php if ($r['status'] === 'published'): ?><a class="btn sm" href="<?= e(url('/' . $r['slug'])) ?>" target="_blank" rel="noopener">View ↗</a><?php endif; ?>
        <a class="btn sm" href="<?= e(url('/admin/pages/edit?id=' . (int)$r['id'])) ?>">Edit</a>
        <form method="post" action="<?= e(url('/admin/pages/delete')) ?>" data-confirm="Delete this page?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
