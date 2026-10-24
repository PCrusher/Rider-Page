<?php /** Variables: $errors $email $name $must $role */ ?>
<h1>Account</h1>
<p class="muted">Your login details. Your level: <b><?= e($role) ?></b>.</p>
<?php if ($must): ?><div class="box warn">An admin set a first password for you. Please choose your own now. You can use the rest of the admin area afterwards.</div><?php endif; ?>
<?php if ($errors): ?><div class="box bad"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/account')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="f" for="email" style="margin-top:0">Email</label>
  <input type="email" id="email" name="email" value="<?= e((string)$email) ?>" autocomplete="username" required>
  <label class="f" for="name">Name</label>
  <input type="text" id="name" name="name" value="<?= e((string)$name) ?>" autocomplete="name">
  <p class="hint">Shown next to your blog posts. Optional.</p>
  <label class="f" for="new"><?= $must ? 'New password *' : 'New password' ?></label>
  <input type="password" id="new" name="new" autocomplete="new-password"<?= $must ? ' required' : '' ?>>
  <p class="hint"><?= $must ? '' : 'Leave empty to keep your current password. ' ?>At least 10 characters.</p>
  <label class="f" for="current">Current password *</label>
  <input type="password" id="current" name="current" autocomplete="current-password" required>
  <p class="hint"><?= $must ? 'The first password you were given.' : 'Needed to confirm any change.' ?></p>
  <div class="actions"><button class="btn primary" type="submit">Save</button></div>
</form>
