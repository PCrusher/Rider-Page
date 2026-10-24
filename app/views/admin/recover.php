<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Recovery – Rider Page</title>
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body>
<div class="login">
  <h1>Recovery</h1>
  <?php if ($error !== ''): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <?php if (!$active): ?>
    <div class="box">Recovery mode is not active. To switch it on, create the file <code>storage/RESET_ADMIN</code> on your server and write a secret phrase of at least 10 characters into it. It stays active for 15 minutes.</div>
    <p><a class="btn" href="<?= e(url('/admin/login')) ?>">Back to the login</a></p>
  <?php else: ?>
    <p class="muted">Recovery mode is on. Enter the phrase from the file, the email address of the account and a new password.</p>
    <form method="post" action="<?= e(url('/admin/recover')) ?>">
      <?= csrf_field() ?>
      <label class="f" for="phrase">Phrase from the file</label>
      <input type="password" id="phrase" name="phrase" autocomplete="off" required>
      <label class="f" for="email">Email of the account</label>
      <input type="email" id="email" name="email" autocomplete="off" required>
      <label class="f" for="new">New password</label>
      <input type="password" id="new" name="new" autocomplete="new-password" required>
      <label class="f" for="again">Repeat the password</label>
      <input type="password" id="again" name="again" autocomplete="new-password" required>
      <p class="hint">Two-factor login of this account is switched off, all its devices are logged out, and the file is deleted.</p>
      <div class="actions"><button class="btn primary" type="submit">Reset the password</button></div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
