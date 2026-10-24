<?php /** Variables: $error $email $flashes */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Log in – Rider Page</title>
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body>
<div class="login">
  <h1>Log in</h1>
  <p class="muted">Rider Page admin</p>
  <?php foreach ($flashes as $f): ?><div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
  <?php if ($error !== ''): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/login')) ?>">
    <?= csrf_field() ?>
    <label class="f" for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    <label class="f" for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <div class="actions"><button class="btn primary" type="submit">Log in</button><a href="<?= e(url('/')) ?>">Back to the site</a></div>
    <p class="hint"><a href="<?= e(url('/admin/forgot')) ?>">Forgot your password?</a></p>
  </form>

</div>
</body>
</html>
