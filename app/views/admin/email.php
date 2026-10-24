<?php /** Variables: $v $hasPass $defaultFrom $recipient */ ?>
<h1>Email</h1>
<p class="muted">How the contact form sends messages to you<?= $recipient !== '' ? ' (to ' . e($recipient) . ')' : '' ?>. Without an SMTP server the page uses the server's own mail function, which many hosts block or send to spam. An SMTP account (your hosting's mail server, or a mail service) is the reliable way.</p>
<form method="post" action="<?= e(url('/admin/email')) ?>" class="box">
  <?= csrf_field() ?>
  <h2 style="margin-top:0">SMTP server</h2>
  <div class="row2">
    <div><label class="f" for="smtp_host" style="margin-top:0">Server</label><input type="text" id="smtp_host" name="smtp_host" value="<?= e($v['smtp_host']) ?>" placeholder="smtp.example.com" autocomplete="off"></div>
    <div><label class="f" for="smtp_port" style="margin-top:0">Port</label><input type="text" id="smtp_port" name="smtp_port" value="<?= e($v['smtp_port']) ?>" inputmode="numeric"></div>
  </div>
  <label class="f" for="smtp_secure">Encryption</label>
  <select id="smtp_secure" name="smtp_secure">
    <option value="tls"<?= $v['smtp_secure'] === 'tls' ? ' selected' : '' ?>>STARTTLS (usually port 587)</option>
    <option value="ssl"<?= $v['smtp_secure'] === 'ssl' ? ' selected' : '' ?>>SSL/TLS (usually port 465)</option>
    <option value="none"<?= $v['smtp_secure'] === 'none' ? ' selected' : '' ?>>None (port 25, not recommended)</option>
  </select>
  <div class="row2">
    <div><label class="f" for="smtp_user">User name</label><input type="text" id="smtp_user" name="smtp_user" value="<?= e($v['smtp_user']) ?>" autocomplete="off"></div>
    <div><label class="f" for="smtp_pass">Password</label><input type="password" id="smtp_pass" name="smtp_pass" autocomplete="new-password" placeholder="<?= $hasPass ? 'Saved. Leave empty to keep it.' : '' ?>"></div>
  </div>
  <?php if ($hasPass): ?><label class="check"><input type="checkbox" name="clear_pass" value="1"> Remove the saved password</label><?php endif; ?>
  <p class="hint">Leave the server empty to use the server's mail function instead. The password is stored in your database.</p>

  <h2>Sender</h2>
  <div class="row2">
    <div><label class="f" for="mail_from" style="margin-top:0">Sender address</label><input type="email" id="mail_from" name="mail_from" value="<?= e($v['mail_from']) ?>" placeholder="<?= e($defaultFrom) ?>"></div>
    <div><label class="f" for="mail_from_name" style="margin-top:0">Sender name</label><input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($v['mail_from_name']) ?>" placeholder="Your site name"></div>
  </div>
  <p class="hint">The address messages come from. Use an address on your own domain (the one your SMTP account allows), otherwise mail providers may reject it. When a visitor writes, their address is set as the reply-to, so you just press Reply.</p>

  <div class="actions">
    <button class="btn primary" type="submit" name="test" value="1">Save and send a test email to me</button>
    <button class="btn" type="submit">Save only</button>
  </div>
</form>
