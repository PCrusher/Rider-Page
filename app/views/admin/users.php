<?php /** Variables: $users $me $posts */ ?>
<h1>Users</h1>
<p class="muted">People who can log in to this admin area. Each person has a level that decides what they can do.</p>
<p><a class="btn primary" href="<?= e(url('/admin/users/edit')) ?>">Add user</a></p>

<div class="tbl-wrap">
<table>
  <thead><tr><th>Name</th><th>Level</th><th>Posts</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <?php $id = (int)$u['id']; $role = Roles::valid((string)$u['role']) ? $u['role'] : 'admin'; ?>
    <tr>
      <td>
        <b><?= e(Users::label($u)) ?></b><?= $id === $me ? ' <span class="muted small">(you)</span>' : '' ?>
        <?php if (trim((string)$u['name']) !== ''): ?><div class="muted small"><?= e($u['email']) ?></div><?php endif; ?>
        <?php if ((int)($u['must_change'] ?? 0) === 1): ?><div class="small"><span class="pill warn">Must choose a new password</span></div><?php endif; ?>
      </td>
      <td><span class="pill <?= $role === 'admin' ? 'ok' : '' ?>"><?= e(Roles::ALL[$role]) ?></span></td>
      <td class="muted small"><?= (int)($posts[$id] ?? 0) ?></td>
      <td class="act">
        <a class="btn sm" href="<?= e(url('/admin/users/edit?id=' . $id)) ?>">Edit</a>
        <?php if ($id !== $me): ?>
          <form method="post" action="<?= e(url('/admin/users/delete')) ?>" data-confirm="Remove this user? Their posts stay and become yours."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn sm danger" type="submit">Remove</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>What each level can do</h2>
<div class="box">
  <?php foreach (Roles::ALL as $key => $label): ?>
    <p style="margin:0 0 8px"><b><?= e($label) ?></b> <span class="muted">– <?= e(Roles::HELP[$key]) ?></span></p>
  <?php endforeach; ?>
</div>
