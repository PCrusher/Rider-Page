<?php /** Variables: $list $count $bytes $max $quality $keep $admin $gd */
$mb = static fn(int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
?>
<h1>Images</h1>
<p class="muted">Pictures you upload are made smaller for your page, so it loads fast. The untouched original is kept here, out of reach of visitors, and you can download it in full quality at any time.</p>
<?php if (!$gd): ?><div class="box warn">The PHP GD extension is not enabled on this server, so images can not be uploaded or resized.</div><?php endif; ?>

<h2>Originals</h2>
<p class="muted small"><?= (int)$count ?> original<?= $count === 1 ? '' : 's' ?>, <?= e($mb((int)$bytes)) ?> in total. Originals of pictures uploaded before version 0.18 were not kept. A downloaded original can contain the place where the photo was taken.</p>
<?php if (!$list): ?>
  <div class="box muted">No originals stored yet. Upload a picture (profile, photos, blog) and it appears here.</div>
<?php else: ?>
<div class="tbl-wrap"><table>
  <thead><tr><th></th><th>File</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $o): ?>
    <tr>
      <td class="sel"><?php if ($o['page'] !== ''): ?><img src="<?= e(url(Images::thumb($o['page']))) ?>" alt="" width="56" height="56" style="object-fit:cover;border-radius:6px"><?php endif; ?></td>
      <td><?= e($o['file']) ?><?php if ($o['page'] === ''): ?> <span class="muted small">(not used on the page any more)</span><?php endif; ?></td>
      <td class="muted small"><?= e($mb((int)$o['size'])) ?></td>
      <td class="muted small"><?= e(date('Y-m-d H:i', (int)$o['time'])) ?></td>
      <td class="act">
        <a class="btn sm" href="<?= e(url('/admin/images/get?f=' . $o['name'])) ?>">Download</a>
        <form method="post" action="<?= e(url('/admin/images/delete')) ?>" data-confirm="Delete this original? The picture on your page stays."><?= csrf_field() ?><input type="hidden" name="f" value="<?= e($o['name']) ?>"><button class="btn sm danger" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($count > 200): ?><p class="muted small">The newest 200 are shown.</p><?php endif; ?>
<?php if ($admin): ?>
<form method="post" action="<?= e(url('/admin/images/deleteall')) ?>" data-confirm="Delete all originals? This can not be undone. The pictures on your page stay."><?= csrf_field() ?><button class="btn danger" type="submit">Delete all originals</button></form>
<?php endif; ?>
<?php endif; ?>

<?php if ($admin): ?>
<h2>Settings</h2>
<form method="post" action="<?= e(url('/admin/images/settings')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="check" style="margin-top:0"><input type="checkbox" name="img_keep" value="1"<?= $keep ? ' checked' : '' ?>> Keep the original of every new upload</label>
  <p class="hint">Photos from a phone are often 5 to 10 MB each. Switch this off, or delete originals above, if your hosting has little space.</p>
  <label class="f" for="img_max">Longest side of pictures on the page</label>
  <select id="img_max" name="img_max" style="max-width:16rem">
    <?php foreach (Images::SIZES as $sz): ?><option value="<?= $sz ?>"<?= $max === $sz ? ' selected' : '' ?>><?= $sz ?> px<?= $sz === 1800 ? ' (default)' : '' ?></option><?php endforeach; ?>
  </select>
  <label class="f" for="img_quality">JPEG quality</label>
  <input id="img_quality" name="img_quality" type="number" min="60" max="95" value="<?= (int)$quality ?>" style="max-width:8rem">
  <p class="hint">60 is small and a little blurry, 95 is large and sharp, 84 is a good balance. Small previews (800 px) are always made as well. Changes apply to new uploads.</p>
  <div class="actions"><button class="btn primary" type="submit">Save</button></div>
</form>
<?php endif; ?>
