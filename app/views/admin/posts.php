<?php /** Variables: $rows $staff */ ?>
<h1>Blog</h1>
<p class="muted"><?= $staff
    ? 'Write posts with pictures, links and YouTube videos. Drafts are only visible here. Posts waiting for review need your approval before they go live.'
    : 'Your posts. Submit a post for review when it is ready; an editor publishes it. You can change a post until it is published.' ?></p>
<p><a class="btn primary" href="<?= e(url('/admin/posts/edit')) ?>">New post</a></p>
<?php if (!$rows): ?>
  <div class="box muted">No posts yet.</div>
<?php else: ?>
<div class="tbl-wrap">
<table>
  <thead><tr><th>Title</th><th>Status</th><?php if ($staff): ?><th>Author</th><?php endif; ?><th>Date</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['title']) ?></td>
      <td>
        <?php if ($r['status'] === 'published'): ?><span class="pill ok">Published</span>
        <?php elseif ($r['status'] === 'pending'): ?><span class="pill warn">Waiting for review</span>
        <?php else: ?><span class="pill">Draft</span><?php endif; ?>
      </td>
      <?php if ($staff): ?><td class="muted small"><?= $r['author_email'] ? e(Users::label(['name' => $r['author_name'], 'email' => $r['author_email']])) : '–' ?></td><?php endif; ?>
      <td class="muted small"><?= e(substr((string)$r['published_at'], 0, 10)) ?></td>
      <td class="act">
        <?php if ($r['status'] === 'published'): ?><a class="btn sm" href="<?= e(url('/blog/' . $r['slug'])) ?>" target="_blank" rel="noopener">View ↗</a><?php endif; ?>
        <?php if ($r['can_edit']): ?>
          <a class="btn sm" href="<?= e(url('/admin/posts/edit?id=' . (int)$r['id'])) ?>">Edit</a>
          <form method="post" action="<?= e(url('/admin/posts/delete')) ?>" data-confirm="Delete this post?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
