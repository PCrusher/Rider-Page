<?php
/** Variables: $r (report) $on $keep $admin */
$series = $r['series'];
$n = count($series);
$max = 1;
foreach ($series as $s) {
    $max = max($max, $s['views'], $s['visitors']);
}
$W = 640; $H = 170; $left = 34; $bottom = 22; $top = 8;
$plotW = $W - $left - 6; $plotH = $H - $bottom - $top;
$bw = $plotW / max($n, 1);
$dev = ['phone' => 'Phone', 'tablet' => 'Tablet', 'desktop' => 'Computer', '' => 'Unknown'];
$totalViews = max(1, $r['views']);
$pct = static fn(int $v): string => round($v * 100 / max(1, $totalViews)) . ' %';
?>
<h1>Statistics</h1>
<p class="muted">Visits to your page, counted on your own server without cookies and without storing IP addresses. Bots, logged-in admins and visitors who send "Do Not Track" are not counted. Numbers are close, not exact.</p>

<?php if (!$on): ?>
  <div class="box warn"><b>Statistics are switched off.</b> <?= $admin ? 'Switch them on below. Then update your privacy policy under <a href="' . e(url('/admin/legal')) . '">Legal pages</a> (tick "Replace pages I created from these templates") so that it mentions them.' : 'An admin can switch them on.' ?></div>
<?php endif; ?>

<p>
  <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $label): ?>
    <a class="btn sm<?= $r['days'] === $d ? ' primary' : '' ?>" href="<?= e(url('/admin/statistics?d=' . $d)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</p>

<div class="tiles">
  <div class="tile"><b><?= number_format($r['views']) ?></b><span>page views</span></div>
  <div class="tile"><b><?= number_format($r['visitors']) ?></b><span>visitors (counted per day)</span></div>
  <div class="tile"><b><?= $r['visitors'] > 0 ? number_format($r['views'] / $r['visitors'], 1) : '–' ?></b><span>pages per visit</span></div>
  <div class="tile"><b><?= number_format(array_sum(array_column($r['clicks'], 'n'))) ?></b><span>clicks on sponsor, social, video</span></div>
</div>

<h2>Per day</h2>
<div class="box">
  <svg class="chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Page views and visitors per day">
    <line class="grid" x1="<?= $left ?>" x2="<?= $W - 6 ?>" y1="<?= $top ?>" y2="<?= $top ?>"/>
    <line class="grid" x1="<?= $left ?>" x2="<?= $W - 6 ?>" y1="<?= $top + $plotH / 2 ?>" y2="<?= $top + $plotH / 2 ?>"/>
    <line class="axis" x1="<?= $left ?>" x2="<?= $W - 6 ?>" y1="<?= $top + $plotH ?>" y2="<?= $top + $plotH ?>"/>
    <text class="lbl" x="<?= $left - 4 ?>" y="<?= $top + 4 ?>" text-anchor="end"><?= (int)$max ?></text>
    <text class="lbl" x="<?= $left - 4 ?>" y="<?= $top + $plotH / 2 + 4 ?>" text-anchor="end"><?= (int)round($max / 2) ?></text>
    <text class="lbl" x="<?= $left - 4 ?>" y="<?= $top + $plotH + 4 ?>" text-anchor="end">0</text>
    <?php foreach ($series as $i => $s): ?>
      <?php
      $x = $left + $i * $bw;
      $hv = $s['views'] / $max * $plotH;
      $hu = $s['visitors'] / $max * $plotH;
      $w = max(1, $bw * 0.72);
      ?>
      <g>
        <title><?= e($s['day']) ?>: <?= (int)$s['views'] ?> views, <?= (int)$s['visitors'] ?> visitors</title>
        <rect class="v" x="<?= round($x + ($bw - $w) / 2, 2) ?>" y="<?= round($top + $plotH - $hv, 2) ?>" width="<?= round($w, 2) ?>" height="<?= round($hv, 2) ?>"/>
        <rect class="u" x="<?= round($x + ($bw - $w) / 2, 2) ?>" y="<?= round($top + $plotH - $hu, 2) ?>" width="<?= round($w, 2) ?>" height="<?= round($hu, 2) ?>"/>
      </g>
    <?php endforeach; ?>
    <text class="lbl" x="<?= $left ?>" y="<?= $H - 6 ?>"><?= e(substr($series[0]['day'], 5)) ?></text>
    <text class="lbl" x="<?= $W - 6 ?>" y="<?= $H - 6 ?>" text-anchor="end"><?= e(substr($series[$n - 1]['day'], 5)) ?></text>
  </svg>
  <p class="small muted" style="margin:6px 0 0"><span class="key v"></span> page views &nbsp; <span class="key u"></span> visitors</p>
  <details style="margin-top:8px"><summary class="small">Show as a table</summary>
    <div class="tbl-wrap"><table><thead><tr><th>Day</th><th>Views</th><th>Visitors</th></tr></thead><tbody>
      <?php foreach (array_reverse($series) as $s): ?><tr><td><?= e($s['day']) ?></td><td><?= (int)$s['views'] ?></td><td><?= (int)$s['visitors'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </details>
</div>

<div class="cols2">
  <div>
    <h2>Top pages</h2>
    <?php if (!$r['pages']): ?><div class="box muted">No visits yet.</div><?php else: ?>
    <div class="tbl-wrap"><table><thead><tr><th>Page</th><th>Views</th><th>Visitors</th></tr></thead><tbody>
      <?php foreach ($r['pages'] as $p): ?><tr><td><code><?= e($p['k']) ?></code></td><td><?= (int)$p['n'] ?></td><td><?= (int)$p['u'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
  <div>
    <h2>Where visitors came from</h2>
    <?php if (!$r['referrers']): ?><div class="box muted">No visits yet.</div><?php else: ?>
    <div class="tbl-wrap"><table><thead><tr><th>Website</th><th>Views</th><th></th></tr></thead><tbody>
      <?php foreach ($r['referrers'] as $p): ?><tr><td><?= $p['k'] === '' ? '<span class="muted">Direct or unknown</span>' : e($p['k']) ?></td><td><?= (int)$p['n'] ?></td><td class="muted small"><?= $pct((int)$p['n']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<div class="cols2">
  <div>
    <h2>Devices</h2>
    <?php if (!$r['devices']): ?><div class="box muted">No visits yet.</div><?php else: ?>
    <div class="tbl-wrap"><table><tbody>
      <?php foreach ($r['devices'] as $p): ?><tr><td><?= e($dev[$p['k']] ?? $p['k']) ?></td><td><?= (int)$p['n'] ?></td><td class="muted small"><?= $pct((int)$p['n']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
  <div>
    <h2>Browsers</h2>
    <?php if (!$r['browsers']): ?><div class="box muted">No visits yet.</div><?php else: ?>
    <div class="tbl-wrap"><table><tbody>
      <?php foreach ($r['browsers'] as $p): ?><tr><td><?= e($p['k']) ?></td><td><?= (int)$p['n'] ?></td><td class="muted small"><?= $pct((int)$p['n']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<h2>Clicks on partners, social accounts and video</h2>
<?php if (!$r['clicks']): ?><div class="box muted">No clicks counted in this period. Sponsors like to see these numbers, so they are worth showing in your media kit.</div><?php else: ?>
<div class="tbl-wrap"><table><thead><tr><th>Link</th><th>Clicks</th></tr></thead><tbody>
  <?php foreach ($r['clicks'] as $p): ?>
    <?php [$kind, $name] = explode(':', (string)$p['k'], 2) + ['', '']; ?>
    <tr><td><span class="pill"><?= e(['sponsor' => 'Partner', 'social' => 'Social', 'video' => 'Video'][$kind] ?? $kind) ?></span> <?= e($kind === 'social' ? (social_types()[$name] ?? $name) : ($kind === 'video' ? 'Play button' : $name)) ?></td><td><?= (int)$p['n'] ?></td></tr>
  <?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<p class="small" style="margin-top:14px">Download as CSV:
  <a href="<?= e(url('/admin/statistics/csv?what=days&d=' . $r['days'])) ?>">per day</a> ·
  <a href="<?= e(url('/admin/statistics/csv?what=pages&d=' . $r['days'])) ?>">top pages</a> ·
  <a href="<?= e(url('/admin/statistics/csv?what=clicks&d=' . $r['days'])) ?>">clicks</a></p>

<?php if ($admin): ?>
<h2>Settings</h2>
<form method="post" action="<?= e(url('/admin/statistics/settings')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="check" style="margin-top:0"><input type="checkbox" name="stats_on" value="1"<?= $on ? ' checked' : '' ?>> Count visits and clicks</label>
  <label class="f" for="stats_days">Keep the detailed data for</label>
  <select id="stats_days" name="stats_days" style="max-width:16rem">
    <?php foreach ([30 => '30 days', 90 => '90 days', 365 => 'one year'] as $d => $label): ?><option value="<?= $d ?>"<?= $keep === $d ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <p class="hint">After that time only the daily totals (views and visitors) remain. <?= number_format($r['rows']) ?> detail rows are stored now.</p>
  <div class="actions"><button class="btn primary" type="submit">Save</button></div>
</form>
<form method="post" action="<?= e(url('/admin/statistics/wipe')) ?>" data-confirm="Delete all statistics? This can not be undone."><?= csrf_field() ?><button class="btn danger" type="submit">Delete all statistics</button></form>
<?php endif; ?>
