<?php /** Variables: $rows (id, label, off, empty) $texts $tiers */ $n = count($rows); ?>
<h1>Sections</h1>
<p class="muted">Choose the order of the sections on your page and switch sections on or off. The menu at the top follows this order. A section with nothing in it stays hidden automatically.</p>
<div class="tbl-wrap">
<table>
  <tbody>
  <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td><b><?= e($r['label']) ?></b></td>
      <td>
        <?php if ($r['off']): ?><span class="pill warn">Switched off</span>
        <?php elseif ($r['empty']): ?><span class="pill">Hidden, nothing added yet</span>
        <?php else: ?><span class="pill ok">Shown</span><?php endif; ?>
      </td>
      <td class="act">
        <form method="post" action="<?= e(url('/admin/sections/move')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><input type="hidden" name="dir" value="up"><button class="btn sm" type="submit" aria-label="Move <?= e($r['label']) ?> up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
        <form method="post" action="<?= e(url('/admin/sections/move')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><input type="hidden" name="dir" value="down"><button class="btn sm" type="submit" aria-label="Move <?= e($r['label']) ?> down"<?= $i === $n - 1 ? ' disabled' : '' ?>>↓</button></form>
        <form method="post" action="<?= e(url('/admin/sections/toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn sm" type="submit"><?= $r['off'] ? 'Switch on' : 'Switch off' ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="hint" style="margin-top:12px">The photo and introduction at the top are always first.</p>

<h2>Headings and labels</h2>
<p class="muted">Rename the sections and the partner levels, or switch the headings off. The heading is also the name in the menu. A heading you switch off is only hidden visually; it stays in the page for screen readers and search engines. Leave a text empty to use the default.</p>
<form method="post" action="<?= e(url('/admin/sections/texts')) ?>" class="box">
  <?= csrf_field() ?>
  <div class="tbl-wrap">
  <table>
    <thead><tr><th>Section</th><th>Heading</th><th>Small label</th><th>Show heading</th><th>Show label</th></tr></thead>
    <tbody>
    <?php foreach ($texts as $sid => $x): ?>
      <tr>
        <td><b><?= e($x['label']) ?></b></td>
        <td><input type="text" name="title_<?= e($sid) ?>" value="<?= e($x['title']) ?>" placeholder="<?= e($x['label']) ?>" maxlength="80" aria-label="Heading for <?= e($x['label']) ?>"></td>
        <?php if ($sid === 'blog'): ?>
          <td class="muted small">The link to all posts is shown here.</td>
          <td><input type="checkbox" name="show_title_<?= e($sid) ?>" value="1"<?= $x['show_title'] ? ' checked' : '' ?> aria-label="Show heading for <?= e($x['label']) ?>"></td>
          <td></td>
        <?php else: ?>
          <td><input type="text" name="kicker_<?= e($sid) ?>" value="<?= e($x['kicker']) ?>" placeholder="<?= e($x['defaultKicker']) ?>" maxlength="80" aria-label="Small label for <?= e($x['label']) ?>"></td>
          <td><input type="checkbox" name="show_title_<?= e($sid) ?>" value="1"<?= $x['show_title'] ? ' checked' : '' ?> aria-label="Show heading for <?= e($x['label']) ?>"></td>
          <td><input type="checkbox" name="show_kicker_<?= e($sid) ?>" value="1"<?= $x['show_kicker'] ? ' checked' : '' ?> aria-label="Show small label for <?= e($x['label']) ?>"></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <h2 style="margin-top:22px">Partner levels</h2>
  <p class="muted small">The names above each group of partners. Switch a label off to show the group without a name.</p>
  <div class="tbl-wrap">
  <table>
    <thead><tr><th>Level</th><th>Name</th><th>Show name</th></tr></thead>
    <tbody>
    <?php foreach ($tiers as $tier => $x): ?>
      <tr>
        <td><b><?= e(ucfirst($tier)) ?></b> <span class="muted small"><?= $tier === 'title' ? '(largest)' : ($tier === 'supporter' ? '(smallest)' : '') ?></span></td>
        <td><input type="text" name="tier_label_<?= e($tier) ?>" value="<?= e($x['label']) ?>" placeholder="<?= e($x['default']) ?>" maxlength="80" aria-label="Name of the <?= e($tier) ?> level"></td>
        <td><input type="checkbox" name="tier_show_<?= e($tier) ?>" value="1"<?= $x['show'] ? ' checked' : '' ?> aria-label="Show the name of the <?= e($tier) ?> level"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="actions"><button class="btn primary" type="submit">Save headings and labels</button></div>
</form>
