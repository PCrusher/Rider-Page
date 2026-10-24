<?php /** Variables: $rows */ ?>
<h1>Blog</h1>
<p class="muted">Write posts with pictures, links and YouTube videos. Drafts are only visible to you.</p>
<p><a class="btn primary" href="<?= e(url('/admin/posts/edit')) ?>">New post</a></p>
<?php if (!$rows): ?>
  <div class="box muted">No posts yet.</div>
<?php else: ?>
<div class="tbl-wrap">
<table>
  <thead><tr><th>Title</th><th>Status</th><th>Date</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['title']) ?></td>
      <td><span class="pill <?= $r['status'] === 'published' ? 'ok' : 'warn' ?>"><?= $r['status'] === 'published' ? 'Published' : 'Draft' ?></span></td>
      <td class="muted small"><?= e(substr((string)$r['published_at'], 0, 10)) ?></td>
      <td class="act">
        <?php if ($r['status'] === 'published'): ?><a class="btn sm" href="<?= e(url('/blog/' . $r['slug'])) ?>" target="_blank" rel="noopener">View ↗</a><?php endif; ?>
        <a class="btn sm" href="<?= e(url('/admin/posts/edit?id=' . (int)$r['id'])) ?>">Edit</a>
        <form method="post" action="<?= e(url('/admin/posts/delete')) ?>" data-confirm="Delete this post?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
