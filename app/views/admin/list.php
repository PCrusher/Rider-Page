<?php
/** Variables: $key $cfg $rows */
$options = [];
foreach ($cfg['fields'] as $f) {
    if ($f[2] === 'select') {
        $options[$f[0]] = $f['options'];
    }
}
$n = count($rows);
?>
<h1><?= e($cfg['title']) ?></h1>
<p class="muted"><?= e($cfg['help']) ?></p>
<p><a class="btn primary" href="<?= e(url('/admin/' . $key . '/edit')) ?>">Add <?= e($cfg['one']) ?></a></p>

<?php if (!$rows): ?>
  <div class="box muted">Nothing here yet.</div>
<?php else: ?>
<div class="tbl-wrap">
<table>
  <tbody>
  <?php foreach ($rows as $i => $r): ?>
    <tr>
      <?php foreach ($cfg['list'] as $col): ?>
        <td>
          <?php if (in_array($col, ['logo', 'image'], true)): ?>
            <?php if ($r[$col] !== ''): ?><img class="thumb" src="<?= e(url(Images::thumb($r[$col]))) ?>" alt=""><?php endif; ?>
          <?php elseif (isset($options[$col])): ?>
            <span class="pill"><?= e((string)($options[$col][$r[$col]] ?? $r[$col])) ?></span>
          <?php else: ?>
            <?= e(mb_strimwidth((string)$r[$col], 0, 70, '…')) ?>
          <?php endif; ?>
        </td>
      <?php endforeach; ?>
      <td class="act">
        <form method="post" action="<?= e(url('/admin/' . $key . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="dir" value="up"><button class="btn sm" type="submit" aria-label="Move up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
        <form method="post" action="<?= e(url('/admin/' . $key . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="dir" value="down"><button class="btn sm" type="submit" aria-label="Move down"<?= $i === $n - 1 ? ' disabled' : '' ?>>↓</button></form>
        <a class="btn sm" href="<?= e(url('/admin/' . $key . '/edit?id=' . (int)$r['id'])) ?>">Edit</a>
        <form method="post" action="<?= e(url('/admin/' . $key . '/delete')) ?>" data-confirm="Delete this <?= e($cfg['one']) ?>?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
