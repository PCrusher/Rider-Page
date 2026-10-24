<?php
/** Variables: $heading $intro $fields $values $errors $action $submit $extra $cancel */
?>
<h1><?= e($heading) ?></h1>
<?php if ($intro !== ''): ?><p class="muted"><?= e($intro) ?></p><?php endif; ?>
<?php if ($errors): ?><div class="box bad"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="box">
  <?= csrf_field() ?>
  <?= $extra ?>
  <?php foreach ($fields as $f): ?>
    <?php
    [$name, $label, $kind] = $f;
    $val = (string)($values[$name] ?? '');
    $id = 'f_' . $name;
    ?>
    <label class="f" for="<?= e($id) ?>"><?= e($label) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
    <?php if ($kind === 'textarea'): ?>
      <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int)($f['rows'] ?? 3) ?>"><?= e($val) ?></textarea>
    <?php elseif ($kind === 'select'): ?>
      <select id="<?= e($id) ?>" name="<?= e($name) ?>">
        <?php foreach ($f['options'] as $ov => $ol): ?>
          <option value="<?= e((string)$ov) ?>"<?= ($val === (string)$ov || ($val === '' && $ov === array_key_first($f['options']))) ? ' selected' : '' ?>><?= e((string)$ol) ?></option>
        <?php endforeach; ?>
      </select>
    <?php elseif ($kind === 'image'): ?>
      <?php if ($val !== ''): ?>
        <div class="preview"><img src="<?= e(url(Images::thumb($val))) ?>" alt=""><?php if (empty($f['required'])): ?><label class="check"><input type="checkbox" name="remove_<?= e($name) ?>" value="1"> Remove this image</label><?php endif; ?></div>
      <?php endif; ?>
      <input type="file" id="<?= e($id) ?>" name="<?= e($name) ?>" accept="image/jpeg,image/png,image/webp,image/gif">
    <?php else: ?>
      <input type="<?= e($kind) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= !empty($f['required']) ? ' required' : '' ?>>
    <?php endif; ?>
    <?php if (!empty($f['hint'])): ?><p class="hint"><?= e($f['hint']) ?></p><?php endif; ?>
  <?php endforeach; ?>
  <div class="actions">
    <button class="btn primary" type="submit"><?= e($submit) ?></button>
    <?php if ($cancel !== ''): ?><a class="btn" href="<?= e($cancel) ?>">Cancel</a><?php endif; ?>
  </div>
</form>
