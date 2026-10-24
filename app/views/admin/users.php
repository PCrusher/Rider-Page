<?php /** Variables: $users $me $posts $require2fa */ ?>
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
        <?php if (TwoFactor::enabled($u)): ?><div class="small"><span class="pill ok">Two-factor on</span></div><?php endif; ?>
        <?php if ((int)($u['must_change'] ?? 0) === 1): ?><div class="small"><span class="pill warn">Must choose a new password</span></div><?php endif; ?>
      </td>
      <td><span class="pill <?= $role === 'admin' ? 'ok' : '' ?>"><?= e(Roles::ALL[$role]) ?></span></td>
      <td class="muted small"><?= (int)($posts[$id] ?? 0) ?></td>
      <td class="act">
        <a class="btn sm" href="<?= e(url('/admin/users/edit?id=' . $id)) ?>">Edit</a>
        <?php if ($id !== $me && TwoFactor::enabled($u)): ?>
          <form method="post" action="<?= e(url('/admin/users/reset2fa')) ?>" data-confirm="Switch off two-factor login for this user? Use this when they lost their phone and their recovery codes."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn sm" type="submit">Reset 2FA</button></form>
        <?php endif; ?>
        <?php if ($id !== $me): ?>
          <form method="post" action="<?= e(url('/admin/users/delete')) ?>" data-confirm="Remove this user? Their posts stay and become yours."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn sm danger" type="submit">Remove</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>Two-factor login</h2>
<form method="post" action="<?= e(url('/admin/users/policy')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="check" style="margin-top:0"><input type="checkbox" name="require_2fa" value="1"<?= $require2fa ? ' checked' : '' ?>> Admins have to use two-factor login</label>
  <p class="hint">Every admin without two-factor login is taken to the Account page after the next login, and can not use anything else until it is set up. Make sure you have set it up for yourself first and saved your recovery codes. Editors and authors can switch it on if they like.</p>
  <div class="actions"><button class="btn" type="submit">Save</button></div>
</form>

<h2>What each level can do</h2>
<div class="box">
  <?php foreach (Roles::ALL as $key => $label): ?>
    <p style="margin:0 0 8px"><b><?= e($label) ?></b> <span class="muted">– <?= e(Roles::HELP[$key]) ?></span></p>
  <?php endforeach; ?>
</div>
