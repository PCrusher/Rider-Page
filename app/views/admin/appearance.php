<?php /** Variables: $presets $current $colors $warnings */ ?>
<h1>Appearance</h1>
<p class="muted">Pick a design, then change the colours if you like. Your own colours stay when you switch designs.</p>
<?php if ($warnings): ?><div class="box warn"><ul><?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/appearance')) ?>">
  <?= csrf_field() ?>
  <h2>Design</h2>
  <div class="presets">
    <?php foreach ($presets as $id => $p): ?>
      <label class="preset">
        <input type="radio" name="preset" value="<?= e($id) ?>"<?= $id === $current ? ' checked' : '' ?>>
        <span class="card">
          <span class="sw"><i style="background:<?= e($p['bg']) ?>"></i><i style="background:<?= e($p['fg']) ?>"></i><i style="background:<?= e($p['accent']) ?>"></i><i style="background:<?= e($p['hl']) ?>"></i></span>
          <span class="meta"><b><?= e($p['name']) ?></b><span class="muted small"><?= e($p['note']) ?></span></span>
        </span>
      </label>
    <?php endforeach; ?>
  </div>

  <h2>Colours</h2>
  <div class="box">
    <?php foreach ($colors as $k => [$label, $hint, $custom, $default]): ?>
      <div class="colorrow">
        <div class="lbl"><b><?= e($label) ?></b><div class="hint"><?= e($hint) ?></div></div>
        <label class="check"><input type="checkbox" name="use_<?= e($k) ?>" value="1"<?= hex_valid($custom) ? ' checked' : '' ?>> Use my own</label>
        <input type="color" name="color_<?= e($k) ?>" value="<?= e(hex_valid($custom) ? strtolower($custom) : strtolower($default)) ?>" aria-label="<?= e($label) ?>">
      </div>
    <?php endforeach; ?>
    <p class="hint">Text and button colours on top of your accent are chosen automatically so they stay readable.</p>
  </div>
  <div class="actions">
    <button class="btn primary" type="submit">Save appearance</button>
    <button class="btn" type="submit" name="reset_colors" value="1">Remove my colours</button>
    <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Open my page ↗</a>
  </div>
</form>
