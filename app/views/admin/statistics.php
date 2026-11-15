<?php
/** Variables: $r (report) $on $keep $admin $geo $geoOn $geoAuto */
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
/** A bar chart of 24 values with an hour label under every third bar. */
$hourChart = static function (array $vals, array $labels, string $aria): string {
    $W = 640; $H = 150; $left = 34; $bottom = 20; $top = 8;
    $plotW = $W - $left - 6; $plotH = $H - $bottom - $top;
    $max = max(1, ...$vals);
    $n = count($vals);
    $bw = $plotW / max($n, 1);
    $o = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e($aria) . '">';
    $o .= '<line class="grid" x1="' . $left . '" x2="' . ($W - 6) . '" y1="' . $top . '" y2="' . $top . '"/>';
    $o .= '<line class="axis" x1="' . $left . '" x2="' . ($W - 6) . '" y1="' . ($top + $plotH) . '" y2="' . ($top + $plotH) . '"/>';
    $o .= '<text class="lbl" x="' . ($left - 4) . '" y="' . ($top + 4) . '" text-anchor="end">' . (int)$max . '</text>';
    $o .= '<text class="lbl" x="' . ($left - 4) . '" y="' . ($top + $plotH + 4) . '" text-anchor="end">0</text>';
    $w = max(1, $bw * 0.72);
    foreach ($vals as $i => $v) {
        $h = $v / $max * $plotH;
        $x = $left + $i * $bw + ($bw - $w) / 2;
        $o .= '<g><title>' . e($labels[$i] . ': ' . $v . ' views') . '</title><rect class="u" x="' . round($x, 2) . '" y="' . round($top + $plotH - $h, 2) . '" width="' . round($w, 2) . '" height="' . round($h, 2) . '"/></g>';
        if ($i % 3 === 0) {
            $o .= '<text class="lbl" x="' . round($left + $i * $bw + $bw / 2, 2) . '" y="' . ($H - 5) . '" text-anchor="middle">' . e(substr($labels[$i], 0, 2)) . '</text>';
        }
    }
    return $o . '</svg>';
};
$hourLabels = array_map(static fn($h) => sprintf('%02d:00', $h), range(0, 23));
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

<h2>By hour</h2>
<div class="cols2">
  <div class="box">
    <b class="small">Last 24 hours</b>
    <?= $hourChart(array_column($r['last24'], 'views'), array_map(static fn($h) => $h['day'] . ' ' . $h['label'] . ':00', $r['last24']), 'Page views in each of the last 24 hours') ?>
    <p class="small muted" style="margin:4px 0 0">Hour of the day, server time.</p>
  </div>
  <div class="box">
    <b class="small">Busiest hours (last <?= (int)$r['days'] ?> days)</b>
    <?= $hourChart(array_values($r['hours']), $hourLabels, 'Page views by hour of the day') ?>
    <p class="small muted" style="margin:4px 0 0">All days added together, by hour of the day, server time.</p>
  </div>
</div>

<h2>Location</h2>
<?php if (!$geo['installed'] || !$geoOn): ?>
  <div class="box muted">Country and city are not collected. <?= $admin ? 'Switch it on under <a href="#location">Settings</a> below.' : 'An admin can switch it on.' ?></div>
<?php elseif (!$r['countries']): ?>
  <div class="box muted">No located visits in this period yet. New visits are located from now on; earlier visits stay without a location.</div>
<?php else: ?>
<div class="cols2">
  <div>
    <h3>Countries</h3>
    <div class="tbl-wrap"><table><thead><tr><th>Country</th><th>Views</th><th>Visitors</th></tr></thead><tbody>
      <?php foreach ($r['countries'] as $p): ?><tr><td><span class="pill"><?= e($p['k']) ?></span> <?= e((string)$p['name']) ?></td><td><?= (int)$p['n'] ?></td><td><?= (int)$p['u'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div>
    <h3>Cities</h3>
    <?php if (!$r['cities']): ?><div class="box muted">No cities yet. Cities need the City database (see Settings).</div><?php else: ?>
    <div class="tbl-wrap"><table><thead><tr><th>City</th><th>Views</th></tr></thead><tbody>
      <?php foreach ($r['cities'] as $p): ?><tr><td><?= e($p['k']) ?> <span class="muted small"><?= e((string)$p['cc']) ?></span></td><td><?= (int)$p['n'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</div>
<p class="small muted">Location is a guess from the IP address and is often a few hundred kilometres off, especially for phones and company networks. <?= (int)$r['located'] ?> of <?= (int)$r['views'] ?> views could be located.</p>
<?php endif; ?>

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
  <a href="<?= e(url('/admin/statistics/csv?what=clicks&d=' . $r['days'])) ?>">clicks</a> ·
  <a href="<?= e(url('/admin/statistics/csv?what=hours&d=' . $r['days'])) ?>">hours</a> ·
  <a href="<?= e(url('/admin/statistics/csv?what=countries&d=' . $r['days'])) ?>">countries</a> ·
  <a href="<?= e(url('/admin/statistics/csv?what=cities&d=' . $r['days'])) ?>">cities</a></p>

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
<h3 id="location">Location (country and city)</h3>
<form method="post" action="<?= e(url('/admin/statistics/geo')) ?>" class="box">
  <?= csrf_field() ?>
  <label class="check" style="margin-top:0"><input type="checkbox" name="geo_on" value="1"<?= $geoOn ? ' checked' : '' ?>> Find the country and city of visitors</label>
  <p class="hint">The IP address is looked up in a database that lives on your server and is <b>not stored and not sent anywhere</b>; only the country and city are saved. Update your privacy policy afterwards (<a href="<?= e(url('/admin/legal')) ?>">Legal pages</a>).</p>
  <label class="f" for="geo_edition">Database</label>
  <select id="geo_edition" name="geo_edition" style="max-width:24rem">
    <option value="city"<?= $geo['edition'] === 'City' ? ' selected' : '' ?>>City (about 50 MB): country and city</option>
    <option value="country"<?= $geo['edition'] === 'Country' ? ' selected' : '' ?>>Country (about 3 MB): country only</option>
  </select>
  <label class="check"><input type="checkbox" name="geo_auto" value="1"<?= $geoAuto ? ' checked' : '' ?>> Keep it up to date automatically (checked once a week while you use the admin area)</label>
  <p class="hint">
    <?php if ($geo['installed']): ?>
      Installed: <b><?= e($geo['edition']) ?></b> database, release <?= e($geo['version'] !== '' ? $geo['version'] : 'unknown') ?>, <?= round($geo['size'] / 1048576, 1) ?> MB.
    <?php else: ?>
      No database installed yet. Save, then press <i>Download / update now</i>.
    <?php endif; ?>
    The data comes from the free <a href="https://github.com/P3TERX/GeoLite.mmdb" target="_blank" rel="noopener">GeoLite.mmdb</a> mirror of MaxMind's GeoLite2 and is downloaded by your server.
    This product includes GeoLite2 data created by <a href="https://www.maxmind.com" target="_blank" rel="noopener">MaxMind</a>; use is under the <a href="https://www.maxmind.com/en/geolite2/eula" target="_blank" rel="noopener">GeoLite2 EULA</a>.
  </p>
  <div class="actions"><button class="btn primary" type="submit">Save</button></div>
</form>
<form method="post" action="<?= e(url('/admin/statistics/geoupdate')) ?>" class="actions" style="margin:0 0 12px"><?= csrf_field() ?>
  <button class="btn" type="submit">Download / update now</button>
  <?php if ($geo['installed']): ?><button class="btn danger" type="submit" formaction="<?= e(url('/admin/statistics/georemove')) ?>" formnovalidate>Delete the database</button><?php endif; ?>
  <span class="muted small">The download can take up to a minute.</span>
</form>
<form method="post" action="<?= e(url('/admin/statistics/wipe')) ?>" data-confirm="Delete all statistics? This can not be undone."><?= csrf_field() ?><button class="btn danger" type="submit">Delete all statistics</button></form>
<?php endif; ?>
