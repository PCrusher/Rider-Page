<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>New password – Rider Page</title>
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body>
<div class="login">
  <h1>Choose a new password</h1>
  <?php if ($error !== ''): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($token === ''): ?>
    <p><a class="btn" href="<?= e(url('/admin/forgot')) ?>">Ask for a new link</a></p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/admin/reset')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="t" value="<?= e($token) ?>">
      <label class="f" for="new">New password</label>
      <input type="password" id="new" name="new" autocomplete="new-password" required autofocus>
      <label class="f" for="again">Repeat the password</label>
      <input type="password" id="again" name="again" autocomplete="new-password" required>
      <p class="hint">At least 10 characters. All devices are logged out after the change. If you use two-factor login, you still need your code to log in.</p>
      <div class="actions"><button class="btn primary" type="submit">Save the password</button></div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
