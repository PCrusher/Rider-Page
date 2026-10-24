<?php /** Variables: $error $email $qr */ ?>
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
  <?php if ($error !== ''): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/login')) ?>">
    <?= csrf_field() ?>
    <label class="f" for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    <label class="f" for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <div class="actions"><button class="btn primary" type="submit">Log in</button><a href="<?= e(url('/')) ?>">Back to the site</a></div>
  </form>

  <?php if ($qr): ?>
  <div id="qr-login" class="qr" hidden
       data-new="<?= e(url('/admin/qr/new')) ?>" data-status="<?= e(url('/admin/qr/status')) ?>"
       data-claim="<?= e(url('/admin/qr/claim')) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <div class="qr-or"><span>or</span></div>
    <button type="button" class="btn" id="qr-start">Log in with your phone</button>
    <p class="hint">No password needed. You must already be logged in on your phone and have switched on QR login under Account.</p>
    <div id="qr-box" hidden>
      <div id="qr-img" class="qr-img" role="img" aria-label="QR code for logging in"></div>
      <p class="qr-code">Number to type on your phone: <b id="qr-num"></b></p>
      <p class="muted small" id="qr-msg" aria-live="polite"></p>
      <button type="button" class="btn sm" id="qr-refresh" hidden>Show a new code</button>
    </div>
  </div>
  <script src="<?= e(asset('qrcode.js')) ?>" defer></script>
  <script src="<?= e(asset('qr-login.js')) ?>" defer></script>
  <?php endif; ?>
</div>
</body>
</html>
