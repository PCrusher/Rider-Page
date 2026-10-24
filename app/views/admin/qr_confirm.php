<?php /** Variables: $token $user $error */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Log in on this device – Rider Page</title>
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body>
<div class="login">
  <h1>Log in on this device?</h1>
  <?php if ($error !== '' || !$user): ?>
    <div class="box bad"><?= e($error) ?></div>
    <p><a class="btn" href="<?= e(url('/admin/login')) ?>">Go to the login page</a></p>
  <?php else: ?>
    <p class="muted">You scanned a code from the Account page of <b><?= e(Users::label($user)) ?></b>. If you did not scan it yourself, or if it was shown to you by someone else, do not continue.</p>
    <form method="post" action="<?= e(url('/admin/qr/login')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="t" value="<?= e($token) ?>">
      <div class="actions"><button class="btn primary" type="submit">Log in on this device</button></div>
    </form>
    <p class="hint">The code is valid for a few seconds only.</p>
  <?php endif; ?>
</div>
</body>
</html>
