<?php /** Variables: $token $error $info $enabled */ ?>
<h1>Approve a login?</h1>
<?php if ($error !== ''): ?><div class="box bad"><?= e($error) ?></div><?php endif; ?>
<?php if (!$enabled): ?>
  <div class="box warn">QR login is switched off for your account, so you can not approve it. Switch it on under <a href="<?= e(url('/admin/account')) ?>">Account</a> and then show a new code on the computer.</div>
<?php elseif ($info === null): ?>
  <div class="box">This code is not valid any more. Codes only work for a few seconds. Show a new code on the computer and scan it again.</div>
<?php else: ?>
  <p class="muted">A device is asking to log in to this admin area as <b><?= e((string)Users::label(Auth::user() ?? [])) ?></b>.</p>
  <div class="box">
    <div class="dev"><b><?= e($info['device']) ?></b><span class="muted small">IP address <?= e($info['ip']) ?> &middot; <?= e(max(0, time() - $info['time'])) ?> seconds ago</span></div>
  </div>
  <form method="post" action="<?= e(url('/admin/qr/approve')) ?>" class="box">
    <?= csrf_field() ?>
    <input type="hidden" name="t" value="<?= e($token) ?>">
    <label class="f" for="code" style="margin-top:0">Type the number from that screen</label>
    <input type="text" id="code" name="code" class="code-input" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="one-time-code" autofocus required>
    <p class="hint">Only approve if you started this login yourself and you can see the number on your own screen. If someone else sent you this code, press Deny.</p>
    <div class="actions">
      <button class="btn primary" type="submit">Approve</button>
      <button class="btn danger" type="submit" name="deny" value="1" formnovalidate>Deny</button>
    </div>
  </form>
<?php endif; ?>
