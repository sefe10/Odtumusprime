<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/scouting.php';
$u = require_login();

$rows = db()->query("
  SELECT t.team_no,
    COALESCE(NULLIF(fn.value,''), t.name, '') AS name,
    COALESCE(NULLIF(fc.value,''), t.city, '') AS city,
    ur.full_name AS rep,
    (SELECT GROUP_CONCAT(te.event_code ORDER BY te.event_code SEPARATOR ',')
       FROM team_events te WHERE te.team_no=t.team_no AND te.season=" . FTC_SEASON . ") AS evs,
    (SELECT MAX(updated_at) FROM team_fields WHERE team_no=t.team_no AND source='manual') AS last_edit
  FROM teams t
  LEFT JOIN team_fields fn ON fn.team_no=t.team_no AND fn.field_key='name'
  LEFT JOIN team_fields fc ON fc.team_no=t.team_no AND fc.field_key='city'
  LEFT JOIN users ur ON ur.id=t.rep_user_id
  ORDER BY t.team_no")->fetchAll();
$events = db()->query("SELECT code, name FROM events WHERE season=" . FTC_SEASON . " AND (region_code='TR' OR code LIKE 'TR%') ORDER BY event_date, code")->fetchAll();

$soc = [];
foreach (db()->query("SELECT team_no, field_key, value FROM team_fields WHERE field_key IN ('web','ig') AND value<>''") as $r) {
  $soc[$r['team_no']][$r['field_key']] = $r['value'];
}

panel_head('Scouting'); ?>
<style>
main{max-width:1200px}
.pill{display:inline-block;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.4);color:#FCD34D;border-radius:4px;padding:1px 7px;margin:0 4px 2px 0;font-size:11px}
tr.me td{background:rgba(245,158,11,.08)}
.bar{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;align-items:center}
.bar input,.bar select{padding:9px 11px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:6px;color:#fff;font:inherit}
.bar option{color:#000}
a.tl{color:#F59E0B;text-decoration:none;font-weight:700}
a.lnk{color:#93C5FD;font-size:12px;margin-right:8px;text-decoration:none;white-space:nowrap}
</style>
<h1>Takım Araştırma (<?= count($rows) ?> takım)</h1>
<?php if (!$rows): ?>
  <div class="note">Henüz takım yok. Mentör hesabıyla <a href="/panel/sync.php" style="color:#F59E0B">FTC Senkron</a> sayfasından takımları çekin.</div>
<?php endif; ?>
<div class="bar">
  <input id="q" placeholder="Takım no, ad veya şehir ara…" oninput="flt()">
  <select id="ev" onchange="flt()">
    <option value="">Tüm turnuvalar</option>
    <?php foreach ($events as $e): ?><option value="<?= h($e['code']) ?>"><?= h($e['name'] ?: $e['code']) ?></option><?php endforeach; ?>
  </select>
  <a class="btn" href="/scouting/links.php">Web / sosyal medya bağlantılarını düzenle</a>
</div>
<table id="tbl">
  <tr><th>No</th><th>Takım</th><th>Şehir</th><th>Bağlantılar</th><th><?= FTC_SEASON ?> turnuvaları</th><th>Temsilci</th><th>Son elle düzenleme</th></tr>
  <?php foreach ($rows as $r): $codes = $r['evs'] ? explode(',', $r['evs']) : []; $no = (int)$r['team_no']; ?>
    <tr class="<?= $no == 25601 ? 'me' : '' ?>" data-s="<?= h(mb_strtolower($no . ' ' . $r['name'] . ' ' . $r['city'])) ?>" data-e="<?= h(',' . $r['evs'] . ',') ?>">
      <td><a class="tl" href="/scouting/team.php?no=<?= $no ?>">#<?= $no ?></a></td>
      <td><a class="tl" href="/scouting/team.php?no=<?= $no ?>"><?= h($r['name']) ?></a></td>
      <td><?= h($r['city']) ?></td>
      <td><?php foreach (['web', 'ig'] as $k) foreach (sc_links($k, $soc[$no][$k] ?? '') as $l): ?>
        <a class="lnk" target="_blank" rel="noopener noreferrer" href="<?= h($l[1]) ?>"><?= $k === 'web' ? 'Web' : h($l[0]) ?> ↗</a>
      <?php endforeach; ?></td>
      <td><?php foreach ($codes as $c): ?><span class="pill"><?= h($c) ?></span><?php endforeach; ?></td>
      <td><?= h($r['rep']) ?></td>
      <td><?= h($r['last_edit']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<script>
function flt(){
  var q=document.getElementById('q').value.toLowerCase().trim(), e=document.getElementById('ev').value;
  document.querySelectorAll('#tbl tr[data-s]').forEach(function(tr){
    var ok=(!q||tr.dataset.s.indexOf(q)>-1)&&(!e||tr.dataset.e.indexOf(','+e+',')>-1);
    tr.style.display=ok?'':'none';
  });
}
</script>
<?php panel_foot();