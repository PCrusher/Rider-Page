<?php /** Variables: $row $id $errors $statuses $canDate */ ?>
<h1><?= $id ? 'Edit post' : 'New post' ?></h1>
<?php if ($errors): ?><div class="box bad"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/posts/save')) ?>" enctype="multipart/form-data" class="box" data-upload="<?= e(url('/admin/upload')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <label class="f" for="title">Title *</label>
  <input type="text" id="title" name="title" value="<?= e((string)$row['title']) ?>" required>

  <label class="f" for="summary">Short summary</label>
  <textarea id="summary" name="summary" rows="2"><?= e((string)$row['summary']) ?></textarea>
  <p class="hint">Shown in the post list and in search results. About 150 characters.</p>

  <label class="f" for="cover">Cover image</label>
  <?php if ($row['cover'] !== ''): ?>
    <div class="preview"><img src="<?= e(url(Images::thumb((string)$row['cover']))) ?>" alt=""><label class="check"><input type="checkbox" name="remove_cover" value="1"> Remove this image</label></div>
  <?php endif; ?>
  <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp,image/gif">
  <div class="row2">
    <div>
      <label class="f" for="cover_credit">Photographer</label>
      <input type="text" id="cover_credit" name="cover_credit" value="<?= e((string)($row['cover_credit'] ?? '')) ?>" maxlength="190">
      <p class="hint">Optional photo credit, shown on the cover image.</p>
    </div>
    <div>
      <label class="f" for="cover_credit_url">Photographer link</label>
      <input type="url" id="cover_credit_url" name="cover_credit_url" value="<?= e((string)($row['cover_credit_url'] ?? '')) ?>" placeholder="https://">
      <p class="hint">Optional link to their website or profile.</p>
    </div>
  </div>

  <label class="f" for="body">Text</label>
  <div class="toolbar">
    <button type="button" data-act="bold"><b>B</b></button>
    <button type="button" data-act="italic"><i>I</i></button>
    <button type="button" data-act="h2">Heading</button>
    <button type="button" data-act="quote">Quote</button>
    <button type="button" data-act="link">Link</button>
    <button type="button" data-act="image">Image…</button>
    <button type="button" data-act="video">YouTube…</button>
    <span class="status" aria-live="polite"></span>
  </div>
  <textarea id="body" name="body" class="editor"><?= e((string)$row['body']) ?></textarea>
  <p class="hint">Write plain text. Put a YouTube link on a line of its own to show a video. A photo credit can be added when you insert an image (shown under the picture). Use the buttons above or Markdown: <code>**bold**</code>, <code>*italic*</code>, <code>[text](https://…)</code>, <code>## Heading</code>.</p>

  <div class="row2">
    <div>
      <label class="f" for="status">Status</label>
      <select id="status" name="status">
        <?php foreach ($statuses as $sv => $sl): ?>
          <option value="<?= e($sv) ?>"<?= (string)$row['status'] === $sv ? ' selected' : '' ?>><?= e($sl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($canDate): ?>
    <div>
      <label class="f" for="published_at">Date</label>
      <input type="datetime-local" id="published_at" name="published_at" value="<?= e(date('Y-m-d\TH:i', (int)strtotime((string)$row['published_at']))) ?>">
      <p class="hint">A future date keeps a published post hidden until then.</p>
    </div>
    <?php endif; ?>
  </div>

  <label class="f" for="slug">Web address</label>
  <input type="text" id="slug" name="slug" value="<?= e((string)$row['slug']) ?>">
  <p class="hint">Leave empty to create it from the title. Example: <code>gravel-nationals-recap</code></p>

  <div class="actions">
    <button class="btn primary" type="submit">Save post</button>
    <a class="btn" href="<?= e(url('/admin/posts')) ?>">Cancel</a>
  </div>
</form>
