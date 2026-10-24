<?php /** Variables: $counts $name */ ?>
<h1>Welcome, <?= e($name) ?></h1>
<p class="muted">You write blog posts. When a post is ready, submit it for review and an editor will publish it.</p>
<div class="box">
  <ul class="steps">
    <li><span class="tick<?= $counts['draft'] ? ' done' : '' ?>"></span><span><b><?= (int)$counts['draft'] ?></b> draft<?= $counts['draft'] === 1 ? '' : 's' ?></span></li>
    <li><span class="tick<?= $counts['pending'] ? ' done' : '' ?>"></span><span><b><?= (int)$counts['pending'] ?></b> waiting for review</span></li>
    <li><span class="tick<?= $counts['published'] ? ' done' : '' ?>"></span><span><b><?= (int)$counts['published'] ?></b> published</span></li>
  </ul>
</div>
<p><a class="btn primary" href="<?= e(url('/admin/posts/edit')) ?>">Write a new post</a> <a class="btn" href="<?= e(url('/admin/posts')) ?>">My posts</a></p>
