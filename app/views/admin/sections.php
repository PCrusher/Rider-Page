<?php /** Variables: $rows (id, label, off, empty) */ $n = count($rows); ?>
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
