<section class="sec<?= $alt ? ' alt' : '' ?>" id="blog">
  <div class="in">
    <?= Site::head($titles, 'blog', '<a class="more" href="' . e(url('/blog')) . '">' . e(t('blog.all')) . '</a>') ?>
    <div class="cards">
      <?php foreach ($latest as $post): ?>
        <a class="card" href="<?= e(url('/blog/' . $post['slug'])) ?>">
          <div class="ph<?= $post['cover'] === '' ? ' empty' : '' ?>">
            <?php if ($post['cover'] !== ''): ?><img src="<?= e(url(Images::thumb($post['cover']))) ?>" alt="" loading="lazy"><?php endif; ?>
          </div>
          <span class="date"><?= e(format_date($post['published_at'])) ?></span>
          <h3><?= e($post['title']) ?></h3>
          <?php if ($post['summary'] !== ''): ?><p><?= e($post['summary']) ?></p><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
