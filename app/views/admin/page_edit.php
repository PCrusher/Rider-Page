<?php /** Variables: $row $id $errors */ ?>
<h1><?= $id ? 'Edit page' : 'New page' ?></h1>
<?php if ($errors): ?><div class="box bad"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ((string)($row['template'] ?? '') !== ''): ?><div class="box warn">This page was created from a template. Read it carefully and make sure it is true for your site before you publish it. Texts like this are a starting point, not legal advice.</div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/pages/save')) ?>" class="box" data-upload="<?= e(url('/admin/upload')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <label class="f" for="title" style="margin-top:0">Title *</label>
  <input type="text" id="title" name="title" value="<?= e((string)$row['title']) ?>" required>

  <label class="f" for="body">Text</label>
  <div class="toolbar">
    <button type="button" data-act="bold"><b>B</b></button>
    <button type="button" data-act="italic"><i>I</i></button>
    <button type="button" data-act="h2">Heading</button>
    <button type="button" data-act="quote">Quote</button>
    <button type="button" data-act="link">Link</button>
    <button type="button" data-act="image">Image…</button>
    <span class="status" aria-live="polite"></span>
  </div>
  <textarea id="body" name="body" class="editor"><?= e((string)$row['body']) ?></textarea>
  <p class="hint">Plain text with simple Markdown: <code>## Heading</code>, <code>**bold**</code>, <code>[text](https://…)</code>, <code>- list item</code>. Lines under each other stay together, which suits addresses.</p>

  <div class="row2">
    <div>
      <label class="f" for="status">Status</label>
      <select id="status" name="status">
        <option value="draft"<?= $row['status'] !== 'published' ? ' selected' : '' ?>>Draft (not public)</option>
        <option value="published"<?= $row['status'] === 'published' ? ' selected' : '' ?>>Published</option>
      </select>
    </div>
    <div>
      <label class="f" for="slug">Web address</label>
      <input type="text" id="slug" name="slug" value="<?= e((string)$row['slug']) ?>" placeholder="impressum">
      <p class="hint">Leave empty to create it from the title.</p>
    </div>
  </div>
  <label class="check"><input type="checkbox" name="in_footer" value="1"<?= !empty($row['in_footer']) ? ' checked' : '' ?>> Link this page in the footer of every page</label>

  <div class="actions">
    <button class="btn primary" type="submit">Save page</button>
    <a class="btn" href="<?= e(url('/admin/pages')) ?>">Cancel</a>
  </div>
</form>
