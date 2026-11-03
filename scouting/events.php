<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/scouting.php';
$u = require_login();

$list = [];
foreach (db()->query('SELECT season, code AS c, name, event_date FROM events') as $r) $list[$r['season'] . '|' . $r['c']] = $r;
foreach (db()->query("SELECT DISTINCT season, event_code AS c, event_name AS name, event_date FROM team_history WHERE event_code NOT LIKE 'manual-%'") as $r) {
  $k = $r['season'] . '|' . $r['c'];
  if (!isset($list[$k])) $list[$k] = $r;
}
$cnt = [];
foreach (db()->query('SELECT season, event_code, COUNT(*) n FROM event_matches GROUP BY season, event_code') as $r) $cnt[$r['season'] . '|' . $r['event_code']] = (int)$r['n'];
usort($list, fn($a, $b) => [$b['season'], (string)$b['event_date']] <=> [$a['season'], (string)$a['event_date']]);

panel_head('Turnuvalar'); ?>
<h1>Turnuvalar ve Maç Arşivi</h1>
<table>
  <tr><th>Sezon</th><th>Turnuva</th><th>Tarih</th><th>Maç</th><th></th></tr>
  <?php foreach ($list as $e): $k = $e['season'] . '|' . $e['c']; ?>
    <tr><td><?= (int)$e['season'] ?>-<?= substr((string)($e['season'] + 1), 2) ?></td>
      <td><?= h($e['name'] ?: $e['c']) ?> <span style="color:#94A3B8">(<?= h($e['c']) ?>)</span></td>
      <td><?= h($e['event_date']) ?></td><td><?= $cnt[$k] ?? 0 ?></td>
      <td><a class="btn" href="/scouting/event.php?season=<?= (int)$e['season'] ?>&event=<?= h(urlencode($e['c'])) ?>">Aç</a></td></tr>
  <?php endforeach; ?>
</table>
<?php panel_foot();