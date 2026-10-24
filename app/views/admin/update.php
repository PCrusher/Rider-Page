<?php
/** Variables: $version $manifest $latest $repo $check $backups $history */
$can = $latest['ok'] && $latest['newer'];
$m = $latest['manifest'] ?? [];
$blocked = (bool)array_filter($check, static fn($c) => !$c['ok']);
?>
<h1>Updates</h1>
<p class="muted">You have version <b><?= e($version) ?></b>.</p>

<div class="box">
  <?php if (!$latest['ok']): ?>
    <p><?= e($latest['error']) ?></p>
  <?php elseif (!$latest['newer']): ?>
    <p><b>You are up to date.</b> <span class="muted">Latest release: <?= e((string)$latest['version']) ?>.</span></p>
  <?php else: ?>
    <p><b>Version <?= e((string)$latest['version']) ?> is available.</b></p>
    <?php if (!empty($latest['notes'])): ?>
      <h2 style="margin-top:14px">What is new in <?= e((string)$latest['version']) ?></h2>
      <div class="prose-admin"><?= Markdown::render((string)$latest['notes']) ?></div>
    <?php elseif (!empty($m['changelog'])): ?><p style="white-space:pre-line"><?= e((string)$m['changelog']) ?></p><?php endif; ?>
    <?php foreach ((array)($m['warnings'] ?? []) as $w): ?><div class="box warn"><?= e((string)$w) ?></div><?php endforeach; ?>
    <?php if (!empty($m['breaking'])): ?>
      <div class="box bad"><b>This update contains changes that can affect customisations.</b> Read the notes above and check the release page before continuing.</div>
    <?php endif; ?>
    <?php if (!empty($latest['page'])): ?><p class="small"><a href="<?= e((string)$latest['page']) ?>" target="_blank" rel="noopener">Release page on GitHub ↗</a></p><?php endif; ?>
  <?php endif; ?>

  <div class="actions">
    <form method="post" action="<?= e(url('/admin/update/check')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Check for updates</button></form>
  </div>
</div>

<?php if ($can): ?>
<form method="post" action="<?= e(url('/admin/update/run')) ?>" class="box" data-confirm="Back up the site and update to version <?= e((string)$latest['version']) ?>?">
  <?= csrf_field() ?>
  <b>Install version <?= e((string)$latest['version']) ?></b>
  <p class="muted small">Before anything changes, the site makes a backup of its code and database. Your content, images and settings are kept. If something goes wrong during the update, the previous version is restored automatically. The page shows a short maintenance notice while this runs.</p>
  <ul class="checks" style="list-style:none;padding:0;margin:12px 0">
    <?php foreach ($check as $c): ?>
      <li><span class="dot<?= $c['ok'] ? '' : ' bad' ?>"></span><span><?= e($c['label']) ?> <span class="muted small">&middot; <?= e($c['detail']) ?></span></span></li>
    <?php endforeach; ?>
  </ul>
  <?php if (!empty($m['breaking'])): ?>
    <label class="check"><input type="checkbox" name="confirm_breaking" value="1"> I have read the notes and want to continue</label>
  <?php endif; ?>
  <div class="actions">
    <button class="btn primary" type="submit"<?= $blocked ? ' disabled' : '' ?>>Back up and update</button>
    <?php if ($blocked): ?><span class="muted small">Fix the red items first, or update by hand (see the README).</span><?php endif; ?>
  </div>
</form>
<?php endif; ?>

<?php if ($history): ?>
<h2>Version history</h2>
<p class="muted small">What changed in each version, newest first. Your version is marked.</p>
<?php foreach ($history as $i => $h): ?>
  <details class="box hist"<?= $i === 0 ? ' open' : '' ?>>
    <summary><b><?= e($h['version']) ?></b><?= $h['version'] === $version ? ' <span class="pill ok">installed</span>' : '' ?></summary>
    <div class="prose-admin"><?= $h['html'] ?></div>
  </details>
<?php endforeach; ?>
<?php endif; ?>

<h2>Update source</h2>
<form method="post" action="<?= e(url('/admin/update/repo')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="f" for="repo" style="margin-top:0">GitHub repository</label>
  <input type="text" id="repo" name="repo" value="<?= e($repo) ?>" placeholder="owner/rider-page">
  <p class="hint">Where new versions are published, written as <code>owner/name</code>. Only change this if you know why.</p>
  <div class="actions"><button class="btn" type="submit">Save</button></div>
</form>

<?php if ($backups): ?>
<h2>Backups</h2>
<div class="box">
  <p class="muted small">Made automatically before each update, stored in <code>storage/backups</code> on your server. The three newest are kept.</p>
  <ul>
    <?php foreach ($backups as $b): ?><li><?= e($b['name']) ?> <span class="muted small">&middot; <?= e(date('Y-m-d H:i', $b['time'])) ?> &middot; <?= e(number_format($b['size'] / 1024, 0)) ?> KB</span></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
