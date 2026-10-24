<?php /** Variables: $id $form $errors $self */ ?>
<h1><?= $id ? 'Edit user' : 'Add user' ?></h1>
<?php if ($errors): ?><div class="box bad"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/users/save')) ?>" class="box">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <label class="f" for="email" style="margin-top:0">Email *</label>
  <input type="email" id="email" name="email" value="<?= e((string)$form['email']) ?>" autocomplete="off" required>
  <p class="hint">Used to log in.</p>

  <label class="f" for="name">Name</label>
  <input type="text" id="name" name="name" value="<?= e((string)$form['name']) ?>" autocomplete="off">
  <p class="hint">Shown next to their posts. Optional.</p>

  <label class="f" for="role">Level</label>
  <select id="role" name="role"<?= $self ? ' aria-describedby="selfnote"' : '' ?>>
    <?php foreach (Roles::ALL as $key => $label): ?>
      <option value="<?= e($key) ?>"<?= (string)$form['role'] === $key ? ' selected' : '' ?>><?= e($label) ?> – <?= e(Roles::HELP[$key]) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($self): ?><p class="hint" id="selfnote">This is your own account. You can not lower your own level.</p><?php endif; ?>

  <label class="f" for="password"><?= $id ? 'New password' : 'First password *' ?></label>
  <input type="password" id="password" name="password" autocomplete="new-password"<?= $id ? '' : ' required' ?>>
  <p class="hint"><?= $id ? 'Leave empty to keep the current password. ' : '' ?>At least 10 characters. Tell the person this password in a safe way.</p>

  <label class="check"><input type="checkbox" name="must_change" value="1"<?= !empty($form['must_change']) ? ' checked' : '' ?>> The person must choose their own password at the next login</label>

  <div class="actions">
    <button class="btn primary" type="submit">Save user</button>
    <a class="btn" href="<?= e(url('/admin/users')) ?>">Cancel</a>
  </div>
</form>
