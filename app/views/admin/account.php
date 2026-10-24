<?php /** Variables: $errors $email $name $must $role $qr $qrAvailable $sessions $twofa $setup $newCodes $codesLeft $notify $require2fa */ ?>
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

<?php if (!$must): ?>
<h2 id="2fa">Two-factor login</h2>
<div class="box">
  <?php if ($newCodes): ?>
    <div class="box warn"><b>Your recovery codes.</b> Save them in a safe place, for example in a password manager. If you lose your phone, each code logs you in once. They are shown only now.</div>
    <pre class="codes"><?= e(implode("\n", $newCodes)) ?></pre>
  <?php endif; ?>

  <?php if ($twofa): ?>
    <p style="margin-top:0"><b>On.</b> After your password you also need a code from your authenticator app. <span class="muted small"><?= (int)$codesLeft ?> recovery codes left.</span></p>
    <div class="row2">
      <form method="post" action="<?= e(url('/admin/account')) ?>">
        <?= csrf_field() ?><input type="hidden" name="do" value="2fa_codes">
        <b class="small">Make new recovery codes</b>
        <label class="f" for="c1p" style="margin-top:6px">Password</label><input type="password" id="c1p" name="current" autocomplete="current-password" required>
        <label class="f" for="c1c">Current code</label><input type="text" id="c1c" name="code" autocomplete="one-time-code" required>
        <div class="actions"><button class="btn" type="submit">Make new codes</button></div>
      </form>
      <form method="post" action="<?= e(url('/admin/account')) ?>" data-confirm="Switch off two-factor login?">
        <?= csrf_field() ?><input type="hidden" name="do" value="2fa_off">
        <b class="small">Switch off</b>
        <label class="f" for="c2p" style="margin-top:6px">Password</label><input type="password" id="c2p" name="current" autocomplete="current-password" required>
        <label class="f" for="c2c">Current code</label><input type="text" id="c2c" name="code" autocomplete="one-time-code" required>
        <div class="actions"><button class="btn danger" type="submit"<?= $require2fa ? ' disabled' : '' ?>>Switch off</button></div>
        <?php if ($require2fa): ?><p class="hint">This site requires two-factor login for admins.</p><?php endif; ?>
      </form>
    </div>
  <?php elseif ($setup): ?>
    <p style="margin-top:0"><b>Step 1.</b> Open an authenticator app (for example Google Authenticator, Microsoft Authenticator, Aegis or 1Password) and scan this code.</p>
    <div id="totp-qr" class="qr-img" data-uri="<?= e($setup['uri']) ?>"></div>
    <p class="small muted">Cannot scan? Type this key into the app: <code><?= e(trim(chunk_split($setup['secret'], 4, ' '))) ?></code> (time-based, 6 digits).</p>
    <p><b>Step 2.</b> Type the 6-digit code that the app shows now.</p>
    <form method="post" action="<?= e(url('/admin/account')) ?>">
      <?= csrf_field() ?><input type="hidden" name="do" value="2fa_confirm">
      <input type="text" name="code" class="code-input" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required aria-label="Code from the app">
      <div class="actions"><button class="btn primary" type="submit">Turn on two-factor login</button></div>
    </form>
    <?php if ($qrAvailable): ?>
      <script src="<?= e(asset('qrcode.js')) ?>" defer></script>
      <script src="<?= e(asset('totp-setup.js')) ?>" defer></script>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted" style="margin-top:0">Adds a second step to the login: after your password you type a 6-digit code from an authenticator app on your phone. Even someone who knows your password can not get in without your phone.<?= $require2fa ? ' <b>This site requires it for admins.</b>' : '' ?></p>
    <form method="post" action="<?= e(url('/admin/account')) ?>">
      <?= csrf_field() ?><input type="hidden" name="do" value="2fa_start">
      <label class="f" for="sp" style="margin-top:0">Your password</label>
      <input type="password" id="sp" name="current" autocomplete="current-password" required style="max-width:20rem">
      <div class="actions"><button class="btn primary" type="submit">Set up two-factor login</button></div>
    </form>
  <?php endif; ?>
</div>

<h2>Security emails</h2>
<form method="post" action="<?= e(url('/admin/account')) ?>" class="box">
  <?= csrf_field() ?><input type="hidden" name="do" value="notify">
  <label class="check" style="margin-top:0"><input type="checkbox" name="notify" value="1"<?= $notify ? ' checked' : '' ?>> Email me when someone logs in from a new device</label>
  <p class="hint">You always get an email when someone logs in with a QR code, when your password or two-factor login changes, or when a recovery code is used. These can not be switched off. Emails are sent from the address in <i>Admin &gt; Email</i>.</p>
  <div class="actions"><button class="btn" type="submit">Save</button></div>
</form>

<h2>Log in on your phone with a QR code</h2>
<div class="box">
  <?php if (!$qrAvailable): ?>
    <p class="muted" style="margin:0">QR login is not available on this installation (the file <code>assets/qrcode.js</code> is missing).</p>
  <?php else: ?>
    <p class="muted" style="margin-top:0">Show a QR code here, scan it with your phone's camera, tap one button, and your phone is logged in without typing a password. The code works for 20 seconds, once, and has to be shown again by hand.</p>
    <form method="post" action="<?= e(url('/admin/account')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="qr">
      <label class="check" style="margin-top:0"><input type="checkbox" name="qr_enabled" value="1"<?= $qr ? ' checked' : '' ?>> Allow logging in with a QR code</label>
      <div class="actions" style="margin-top:12px"><button class="btn" type="submit">Save</button></div>
    </form>
    <?php if ($qr): ?>
      <div id="qr-account" class="qr-account" hidden
           data-new="<?= e(url('/admin/qr/new')) ?>" data-check="<?= e(url('/admin/qr/check')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-refresh="<?= e(url('/admin/account')) ?>">
        <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
        <div id="qr-ask">
          <label class="f" for="qr-pw" style="margin-top:0">Your password</label>
          <input type="password" id="qr-pw" autocomplete="current-password" style="max-width:20rem">
          <?php if ($twofa): ?>
            <label class="f" for="qr-totp">Code from your authenticator app</label>
            <input type="text" id="qr-totp" autocomplete="one-time-code" inputmode="numeric" maxlength="7" style="max-width:20rem">
            <p class="hint">Use a code you have not used in the last minute: wait for the next one if needed.</p>
          <?php endif; ?>
          <p class="hint">Asked every time, because the code logs in whoever scans it.</p>
          <div class="actions"><button type="button" class="btn primary" id="qr-show">Show QR code</button></div>
        </div>
        <div id="qr-shown" hidden>
          <div id="qr-img" class="qr-img" role="img" aria-label="QR code for logging in on your phone"></div>
          <p class="muted small" id="qr-msg" aria-live="polite"></p>
          <button type="button" class="btn sm" id="qr-again" hidden>Show a new code</button>
        </div>
      </div>
      <script src="<?= e(asset('qrcode.js')) ?>" defer></script>
      <script src="<?= e(asset('qr-account.js')) ?>" defer></script>
    <?php endif; ?>
    <p class="hint" style="margin-bottom:0">Use this only for your own phone, and not while someone can look at your screen. Anyone who scans the code in time can log in as you. Every login appears in the list below, where you can end it.</p>
  <?php endif; ?>
</div>

<h2>Where you are logged in</h2>
<div class="tbl-wrap">
<table>
  <thead><tr><th>Device</th><th>IP address</th><th>Logged in with</th><th>Since</th><th>Last active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($sessions as $s): ?>
    <tr>
      <td><b><?= e($s['device']) ?></b><?= $s['current'] ? ' <span class="pill ok">This device</span>' : '' ?></td>
      <td class="muted small"><?= e($s['ip']) ?></td>
      <td class="muted small"><?= $s['method'] === 'qr' ? 'QR code' : 'Password' ?></td>
      <td class="muted small"><?= e(substr((string)$s['created_at'], 0, 16)) ?></td>
      <td class="muted small"><?= e(substr((string)$s['last_seen'], 0, 16)) ?></td>
      <td class="act">
        <?php if (!$s['current']): ?>
          <form method="post" action="<?= e(url('/admin/account')) ?>" data-confirm="Log out this device?"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn sm danger" type="submit">Log out</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$sessions): ?><tr><td colspan="6" class="muted">No device information yet. It appears the next time you log in.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if (count($sessions) > 1): ?>
  <form method="post" action="<?= e(url('/admin/account')) ?>" data-confirm="Log out all other devices?" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="do" value="logout_others"><button class="btn" type="submit">Log out all other devices</button></form>
<?php endif; ?>
<p class="hint">IP address and browser are saved only for this list, are visible only to you, and are deleted when you log out or after 30 days without use. A place name would need an external lookup service, so only the IP address is shown.</p>
<?php endif; ?>
