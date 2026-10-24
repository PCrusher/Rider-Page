<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Second step – Rider Page</title>
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body>
<div class="login">
  <h1>Second step</h1>
  <p class="muted">Enter the 6-digit code from your authenticator app. If you lost your phone, use one of your recovery codes instead.</p>
  <?php if ($error !== ''): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/login/2fa')) ?>">
    <?= csrf_field() ?>
    <label class="f" for="code">Code</label>
    <input type="text" id="code" name="code" class="code-input" style="max-width:none;letter-spacing:.2em" inputmode="text" autocomplete="one-time-code" autofocus required>
    <div class="actions"><button class="btn primary" type="submit">Log in</button><a href="<?= e(url('/admin/login')) ?>">Start again</a></div>
  </form>
</div>
</body>
</html>
