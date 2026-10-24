<?php /** Variables: $posts */ ?>
<div class="in pagehead">
  <span class="eyebrow"><?= e($p['name']) ?></span>
  <h1><?= e(t('blog.title')) ?></h1>
  <p><?= e(t('blog.intro')) ?></p>
</div>
<div class="in">
  <div class="list">
    <?php foreach ($posts as $post): ?>
      <a class="row" href="<?= e(url('/blog/' . $post['slug'])) ?>">
        <div class="ph<?= $post['cover'] === '' ? ' empty' : '' ?>">
          <?php if ($post['cover'] !== ''): ?><img src="<?= e(url(Images::thumb($post['cover']))) ?>" alt="" loading="lazy"><?php endif; ?>
        </div>
        <div>
          <span class="date"><?= e(format_date($post['published_at'])) ?></span>
          <h2><?= e($post['title']) ?></h2>
          <?php if ($post['summary'] !== ''): ?><p><?= e($post['summary']) ?></p><?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if (!$posts): ?><p class="empty-note"><?= e(t('blog.empty')) ?></p><?php endif; ?>
  </div>
</div>
