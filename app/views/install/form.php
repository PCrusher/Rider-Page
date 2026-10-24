<?php /** Variables: $checks $blocked $errors $v $presets $timezones $drivers */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Set up Rider Page</title>
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
<script src="<?= e(asset('admin.js')) ?>" defer></script>
</head>
<body>
<div class="install">
  <h1>Set up Rider Page</h1>
  <p class="muted">This takes about two minutes. You can change everything later in the admin area.</p>

  <div class="box">
    <b>Server check</b>
    <ul class="checks" style="list-style:none;padding:0;margin:10px 0 0">
      <?php foreach ($checks as $c): ?>
        <li><span class="dot<?= $c['ok'] ? '' : ($c['blocking'] ? ' bad' : ' warn') ?>"></span><span><?= e($c['label']) ?> <span class="muted small">&middot; <?= e($c['detail']) ?></span></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($blocked): ?><p style="margin-top:10px"><b>Fix the red items, then reload this page.</b></p><?php endif; ?>
  </div>

  <?php if ($errors): ?><div class="box bad"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <?php if (!$blocked): ?>
  <form method="post" action="<?= e(url('/install')) ?>">
    <?= csrf_field() ?>

    <h2>Your page</h2>
    <div class="box">
      <label class="f" for="site_name" style="margin-top:0">Your name *</label>
      <input type="text" id="site_name" name="site_name" value="<?= e($v['site_name']) ?>" required>
      <div class="row2">
        <div>
          <label class="f" for="lang">Language of the page texts</label>
          <select id="lang" name="lang">
            <?php foreach (Lang::AVAILABLE as $code => $label): ?><option value="<?= e($code) ?>"<?= $v['lang'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="f" for="timezone">Time zone</label>
          <select id="timezone" name="timezone">
            <?php foreach ($timezones as $tz): ?><option value="<?= e($tz) ?>"<?= $v['timezone'] === $tz ? ' selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <label class="f" for="preset">Design</label>
      <select id="preset" name="preset">
        <?php foreach ($presets as $id => $p): ?><option value="<?= e($id) ?>"<?= $v['preset'] === $id ? ' selected' : '' ?>><?= e($p['name'] . ' – ' . $p['note']) ?></option><?php endforeach; ?>
      </select>
      <p class="hint">You can see all designs with their colours in the admin area under Appearance.</p>
      <label class="check"><input type="checkbox" name="example" value="1"<?= $v['example'] === '1' ? ' checked' : '' ?>> Add example content so I can see how it looks</label>
    </div>

    <h2>Admin account</h2>
    <div class="box">
      <label class="f" for="admin_email" style="margin-top:0">Email *</label>
      <input type="email" id="admin_email" name="admin_email" value="<?= e($v['admin_email']) ?>" autocomplete="username" required>
      <div class="row2">
        <div><label class="f" for="admin_password">Password *</label><input type="password" id="admin_password" name="admin_password" autocomplete="new-password" required></div>
        <div><label class="f" for="admin_password2">Repeat password *</label><input type="password" id="admin_password2" name="admin_password2" autocomplete="new-password" required></div>
      </div>
      <p class="hint">At least 10 characters.</p>
    </div>

    <h2>Database</h2>
    <div class="box">
      <label class="f" for="db_driver" style="margin-top:0">Type</label>
      <select id="db_driver" name="db_driver">
        <?php if (in_array('sqlite', $drivers, true)): ?><option value="sqlite"<?= $v['db_driver'] === 'sqlite' ? ' selected' : '' ?>>SQLite – nothing to set up (recommended for small pages)</option><?php endif; ?>
        <?php if (in_array('mysql', $drivers, true)): ?><option value="mysql"<?= $v['db_driver'] === 'mysql' ? ' selected' : '' ?>>MySQL / MariaDB</option><?php endif; ?>
      </select>
      <div id="mysql_fields">
        <div class="row2">
          <div><label class="f" for="mysql_host">Host</label><input type="text" id="mysql_host" name="mysql_host" value="<?= e($v['mysql_host']) ?>"></div>
          <div><label class="f" for="mysql_port">Port</label><input type="text" id="mysql_port" name="mysql_port" value="<?= e($v['mysql_port']) ?>"></div>
        </div>
        <label class="f" for="mysql_name">Database name</label>
        <input type="text" id="mysql_name" name="mysql_name" value="<?= e($v['mysql_name']) ?>">
        <div class="row2">
          <div><label class="f" for="mysql_user">User</label><input type="text" id="mysql_user" name="mysql_user" value="<?= e($v['mysql_user']) ?>" autocomplete="off"></div>
          <div><label class="f" for="mysql_pass">Password</label><input type="password" id="mysql_pass" name="mysql_pass" autocomplete="off"></div>
        </div>
        <p class="hint">You find these details in your hosting control panel. The database must be empty.</p>
      </div>
    </div>

    <div class="actions"><button class="btn primary" type="submit">Install</button></div>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
