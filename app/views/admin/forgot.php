<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Forgot password – Rider Page</title>
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body>
<div class="login">
  <h1>Forgot your password?</h1>
  <?php if ($sent): ?>
    <div class="box">If this email address belongs to an account, we have sent a link to it. It works once, for 60 minutes. Look in your spam folder if nothing arrives.</div>
    <p><a class="btn" href="<?= e(url('/admin/login')) ?>">Back to the login</a></p>
  <?php else: ?>
    <p class="muted">Enter the email address of your account. We send you a link to choose a new password.</p>
    <form method="post" action="<?= e(url('/admin/forgot')) ?>">
      <?= csrf_field() ?>
      <label class="f" for="email">Email</label>
      <input type="email" id="email" name="email" autocomplete="username" required autofocus>
      <div class="actions"><button class="btn primary" type="submit">Send the link</button><a href="<?= e(url('/admin/login')) ?>">Back</a></div>
    </form>
  <?php endif; ?>
  <details style="margin-top:22px"<?= $recovery ? ' open' : '' ?>>
    <summary class="small"><b>No access to that email?</b></summary>
    <p class="small muted">If you can upload files to your server, you can reset a password without email. Create a file named <code>RESET_ADMIN</code> in the <code>storage</code> folder. Write a secret phrase of at least 10 characters into it. For 15 minutes, the page <a href="<?= e(url('/admin/recover')) ?>">/admin/recover</a> then accepts that phrase, the email address of the account and a new password. The file is deleted afterwards. Two-factor login of that account is switched off.</p>
  </details>
</div>
</body>
</html>
