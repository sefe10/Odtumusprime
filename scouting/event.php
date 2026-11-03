<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/scouting.php';
require __DIR__ . '/../app/matches.php';
require __DIR__ . '/../app/opr.php';
$u = require_login();
$manage = in_array($u['role'], ['mentor', 'youth_mentor'], true);
$isMentor = $u['role'] === 'mentor';

$season = (int)($_GET['season'] ?? FTC_SEASON);
$code = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['event'] ?? ''));
if ($code === '') { header('Location: /scouting/events.php'); exit; }

$ev = db()->prepare('SELECT name, event_date, city, venue FROM events WHERE code=? AND season=?');
$ev->execute([$code, $season]);
$ev = $ev->fetch() ?: [];
if (!$ev) {
  $h = db()->prepare('SELECT event_name AS name, event_date FROM team_history WHERE event_code=? AND season=? LIMIT 1');
  $h->execute([$code, $season]);
  $ev = $h->fetch() ?: ['name' => $code, 'event_date' => null];
}

$st = db()->prepare('SELECT * FROM event_matches WHERE season=? AND event_code=? ORDER BY sort_no, id');
$st->execute([$season, $code]);
$rows = $st->fetchAll();

$nos = [];
foreach ($rows as $r) foreach (explode(',', $r['red_teams'] . ',' . $r['blue_teams']) as $x) if ($x !== '') $nos[(int)$x] = 1;
$stats = event_stats($season, $code);
foreach (array_keys($stats) as $t) $nos[$t] = 1;
$names = team_name_map(array_keys($nos));
uasort($stats, fn($a, $b) => $b['opr'] <=> $a['opr']);

$tm = function (string $list) use ($names): string {
  $o = [];
  foreach (explode(',', $list) as $x) {
    if ($x === '') continue;
    $n = (int)$x;
    $o[] = '<div class="tm"><a href="/scouting/team.php?no=' . $n . '">' . $n . '</a> ' . h($names[$n] ?? '') . '</div>';
  }
  return implode('', $o);
};

panel_head(($ev['name'] ?? $code) . ' maçları'); ?>
<style>
main{max-width:1400px}
td.num{text-align:center;min-width:34px}
td[contenteditable]:focus{outline:1px solid #F59E0B;background:rgba(245,158,11,.08)}
.tm{white-space:nowrap}.tm a{color:#F59E0B;text-decoration:none;font-weight:700}
.rd td.c{background:rgba(239,68,68,.07)}.bl td.c{background:rgba(59,130,246,.07)}
.mini{background:none;border:0;color:#94A3B8;cursor:pointer;font-size:11px;text-decoration:underline}
.af input{padding:7px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px;width:90px}
</style>
<p><a href="/scouting/events.php" style="color:#F59E0B">← Turnuvalar</a></p>
<h1><?= h($ev['name'] ?? $code) ?> <span style="font-size:14px;color:#94A3B8"><?= $season ?>-<?= substr((string)($season + 1), 2) ?> • <?= h($code) ?></span></h1>
<p>
  <a class="btn" target="_blank" rel="noopener" href="https://ftc-events.firstinspires.org/<?= $season ?>/<?= h($code) ?>">Resmi sayfa ↗</a>
  <a class="btn" href="/scouting/picklist.php?ev=<?= $season ?>|<?= h($code) ?>">Pick list</a>
  <?php if ($manage): ?><button class="btn" id="sync">↻ Resmî fikstür ve skorları çek</button><?php endif; ?>
  <span id="msg" style="margin-left:10px;color:#94A3B8"></span>
</p>

<h2 class="sec" style="color:#F59E0B;font-size:15px">Maçlar (<?= count($rows) ?>)</h2>
<div style="overflow-x:auto"><table id="mt">
  <tr><th rowspan="2">Maç</th><th rowspan="2">Saat</th><th rowspan="2">Kırmızı ittifak</th><th rowspan="2">Mavi ittifak</th>
    <th colspan="2">Skor</th><th colspan="2">Otonom</th><th colspan="2">TeleOp</th><th colspan="2">Faul (aldığı)</th><?= $isMentor ? '<th rowspan="2"></th>' : '' ?></tr>
  <tr><th>K</th><th>M</th><th>K</th><th>M</th><th>K</th><th>M</th><th>K</th><th>M</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr data-id="<?= (int)$r['id'] ?>">
      <td><b><?= h($r['label']) ?></b><?= $r['source'] === 'manual' ? ' <span style="font-size:10px;color:#6EE7B7">elle</span>' : '' ?></td>
      <td><?= $r['start_time'] ? h(date('d.m H:i', strtotime($r['start_time']))) : '' ?></td>
      <td class="c" style="background:rgba(239,68,68,.07)"><?= $tm($r['red_teams']) ?></td>
      <td class="c" style="background:rgba(59,130,246,.07)"><?= $tm($r['blue_teams']) ?></td>
      <?php foreach (['total', 'auto', 'teleop', 'foul'] as $c): foreach (['red', 'blue'] as $side): ?>
        <td class="num" contenteditable data-col="<?= $side . '_' . $c ?>"><?= h($r[$side . '_' . $c]) ?></td>
      <?php endforeach; endforeach; ?>
      <?php if ($isMentor): ?><td><button class="mini" data-del="<?= (int)$r['id'] ?>">sil</button></td><?php endif; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="13" style="color:#94A3B8">Henüz maç yok. "Resmî fikstür ve skorları çek" düğmesini deneyin veya elle ekleyin.</td></tr><?php endif; ?>
</table></div>
<p style="font-size:11px;color:#94A3B8">Skor hücrelerine tıklayıp düzeltebilir veya turnuva günü canlı girebilirsiniz. Her iki toplam skor girilince maç "oynandı" sayılır. Faul = o ittifaka rakibin faullerinden eklenen puan. TeleOp endgame dahildir.</p>
<?php if ($manage): ?>
  <p class="af"><b>Elle maç ekle:</b>
    <input id="am_l" placeholder="Etiket (Q-12)"> <input id="am_r1" type="number" placeholder="Kırmızı 1"> <input id="am_r2" type="number" placeholder="Kırmızı 2">
    <input id="am_b1" type="number" placeholder="Mavi 1"> <input id="am_b2" type="number" placeholder="Mavi 2"> <button class="btn" id="am">+ Ekle</button></p>
<?php endif; ?>

<h2 class="sec" style="color:#F59E0B;font-size:15px;margin-top:30px">Takım İstatistikleri (eleme dışı maçlar)</h2>
<table>
  <tr><th>#</th><th>Takım</th><th>Maç</th><th>G-M-B</th><th>Ort. skor</th><th>Ort. otonom</th><th>Ort. teleop</th><th>Faul aldığı</th><th>Faul verdiği</th><th>OPR</th><th>Faulsüz OPR</th><th>Otonom OPR</th><th>TeleOp OPR</th></tr>
  <?php $i = 0; foreach ($stats as $t => $s): $i++; ?>
    <tr><td><?= $i ?></td><td><a style="color:#F59E0B" href="/scouting/team.php?no=<?= (int)$t ?>"><?= (int)$t ?></a> <?= h($names[$t] ?? '') ?></td>
      <td><?= $s['m'] ?></td><td><?= $s['w'] . '-' . $s['l'] . '-' . $s['t'] ?></td>
      <td><?= round($s['avg'], 1) ?></td><td><?= round($s['auto'], 1) ?></td><td><?= round($s['tele'], 1) ?></td>
      <td><?= round($s['fg'], 1) ?></td><td><?= round($s['fv'], 1) ?></td>
      <td><b><?= round($s['opr'], 1) ?></b></td><td><b><?= round($s['np'], 1) ?></b></td><td><?= round($s['ao'], 1) ?></td><td><?= round($s['to'], 1) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$stats): ?><tr><td colspan="13" style="color:#94A3B8">Oynanmış maç verisi olunca hesaplanır.</td></tr><?php endif; ?>
</table>
<p style="font-size:11px;color:#94A3B8">OPR, takım başına tahmini skor katkısıdır. Faulsüz OPR rakip faullerinin etkisini çıkarır. Turnuvanın başında az maçla hesaplanan değerler güvenilir değildir.</p>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>, SEASON = <?= $season ?>, CODE = <?= json_encode($code) ?>;
const msg = document.getElementById('msg');
function post(p) {
  const fd = new FormData(); fd.append('csrf', CSRF); fd.append('season', SEASON); fd.append('event', CODE);
  for (const k in p) fd.append(k, p[k]);
  return fetch('/scouting/event-save.php', {method: 'POST', body: fd}).then(function (r) { return r.json(); });
}
document.addEventListener('focusin', function (e) { const td = e.target.closest ? e.target.closest('td[data-col]') : null; if (td) td.dataset.old = td.innerText.trim(); });
document.addEventListener('focusout', function (e) {
  const td = e.target.closest ? e.target.closest('td[data-col]') : null;
  if (!td) return;
  const v = td.innerText.trim();
  if (v === td.dataset.old) return;
  post({kind: 'score', id: td.parentNode.dataset.id, col: td.dataset.col, value: v}).then(function (j) {
    td.style.outline = '2px solid ' + (j.ok ? '#10B981' : '#E11D48');
    setTimeout(function () { td.style.outline = ''; }, 900);
  });
});
const sync = document.getElementById('sync');
if (sync) sync.addEventListener('click', function () {
  sync.disabled = true; msg.textContent = 'Çekiliyor…';
  post({kind: 'sync'}).then(function (j) {
    msg.textContent = j.ok ? (j.count + ' maç güncellendi' + ((j.warn || []).length ? ' • ' + j.warn.join(' • ') : '')) : (j.error || 'Hata');
    if (j.ok) setTimeout(function () { location.reload(); }, 1200); else sync.disabled = false;
  });
});
const am = document.getElementById('am');
if (am) am.addEventListener('click', function () {
  post({kind: 'add_match', label: document.getElementById('am_l').value, r1: document.getElementById('am_r1').value, r2: document.getElementById('am_r2').value,
        b1: document.getElementById('am_b1').value, b2: document.getElementById('am_b2').value}).then(function (j) {
    if (j.ok) location.reload(); else alert(j.error || 'Eklenemedi');
  });
});
document.querySelectorAll('[data-del]').forEach(function (b) {
  b.addEventListener('click', function () {
    const pin = prompt('Maçı silmek için şifrenizi girin:');
    if (!pin) return;
    post({kind: 'del_match', id: b.dataset.del, pin: pin}).then(function (j) {
      if (j.ok) b.closest('tr').remove(); else alert(j.error || 'Silinemedi');
    });
  });
});
</script>
<?php panel_foot();