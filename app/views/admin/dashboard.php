<?php /** Variables: $steps $hidden $update $maintenance $notFound $pending */ ?>
<h1>Dashboard</h1>
<p class="muted">Welcome back. Here is where your page stands.</p>

<?php if ($maintenance): ?>
  <div class="box bad">
    <b>Maintenance mode is on.</b> Visitors see a maintenance page instead of your site.
    <a href="<?= e(url('/admin/maintenance')) ?>">Turn it off</a>
  </div>
<?php endif; ?>

<?php if ($pending > 0): ?>
  <div class="box warn">
    <b><?= (int)$pending ?> blog post<?= $pending === 1 ? ' is' : 's are' ?> waiting for review.</b>
    <a href="<?= e(url('/admin/posts')) ?>">Open the blog list</a>
  </div>
<?php endif; ?>

<?php if ($notFound > 0): ?>
  <div class="box">
    <b><?= (int)$notFound ?> address<?= $notFound === 1 ? ' was' : 'es were' ?> asked for but not found.</b>
    <a href="<?= e(url('/admin/redirects')) ?>">Review and add redirects</a>
  </div>
<?php endif; ?>

<?php if ($update && !empty($update['newer']) && !empty($update['ok'])): ?>
  <div class="box warn">
    <b>Version <?= e((string)$update['version']) ?> is available.</b>
    <a href="<?= e(url('/admin/update')) ?>">See what is new and update</a>
  </div>
<?php endif; ?>

<div class="box">
  <b>Getting started</b>
  <ul class="steps">
    <?php foreach ($steps as [$label, $done, $href]): ?>
      <li><span class="tick<?= $done ? ' done' : '' ?>"></span><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>

<?php if ($hidden): ?>
  <div class="box">
    <b>Sections not shown yet</b>
    <p class="muted small">These sections stay hidden on your page until you add something to them: <?= e(implode(', ', $hidden)) ?>.</p>
  </div>
<?php endif; ?>

<p><a class="btn primary" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Open my page ↗</a></p>
