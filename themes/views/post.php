<?php /** Variables: $post (row plus html and minutes) */ ?>
<article class="in post">
  <a class="back" href="<?= e(url('/blog')) ?>"><?= e(t('blog.back')) ?></a>
  <h1><?= e($post['title']) ?></h1>
  <div class="byline">
    <span class="date"><?= e(format_date($post['published_at'])) ?></span>
    <span class="date"><?= e(t('blog.minutes', ['n' => $post['minutes']])) ?></span>
  </div>
  <?php if ($post['cover'] !== ''): ?>
    <div class="ph cover"><img src="<?= e(url($post['cover'])) ?>" alt=""></div>
  <?php endif; ?>
  <div class="prose"><?= $post['html'] ?></div>
</article>
