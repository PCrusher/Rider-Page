<?php /** Variables: $errors $email */ ?>
<h1>Account</h1>
<p class="muted">Your login details.</p>
<?php if ($errors): ?><div class="box bad"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/account')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="f" for="email" style="margin-top:0">Email</label>
  <input type="email" id="email" name="email" value="<?= e((string)$email) ?>" autocomplete="username" required>
  <label class="f" for="new">New password</label>
  <input type="password" id="new" name="new" autocomplete="new-password">
  <p class="hint">Leave empty to keep your current password. At least 10 characters.</p>
  <label class="f" for="current">Current password *</label>
  <input type="password" id="current" name="current" autocomplete="current-password" required>
  <p class="hint">Needed to confirm any change.</p>
  <div class="actions"><button class="btn primary" type="submit">Save</button></div>
</form>
