<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/scouting.php';
require __DIR__ . '/../app/opr.php';
$u = require_login();

$opts = []; $has = [];
foreach (db()->query('SELECT season, code, name FROM events WHERE season=' . FTC_SEASON . ' ORDER BY event_date') as $r) {
  $opts[$r['season'] . '|' . $r['code']] = [(int)$r['season'], $r['code'], $r['name'] ?: $r['code']];
}
foreach (db()->query('SELECT season, event_code, COUNT(*) n FROM event_matches GROUP BY season, event_code') as $r) {
  $k = $r['season'] . '|' . $r['event_code'];
  $has[$k] = (int)$r['n'];
  if (!isset($opts[$k])) $opts[$k] = [(int)$r['season'], $r['event_code'], $r['event_code']];
}
$sel = $_GET['ev'] ?? '';
if (!isset($opts[$sel])) {
  $sel = '';
  foreach ($opts as $k => $_) if (!empty($has[$k])) { $sel = $k; break; }
  if ($sel === '' && $opts) $sel = array_key_first($opts);
}
[$season, $code] = $sel !== '' ? [$opts[$sel][0], $opts[$sel][1]] : [FTC_SEASON, ''];
$stats = $code !== '' ? event_stats($season, $code) : [];

$nos = [];
if ($code !== '') {
  $q = db()->prepare('SELECT team_no FROM team_events WHERE season=? AND event_code=?');
  $q->execute([$season, $code]);
  foreach ($q as $r) $nos[(int)$r['team_no']] = 1;
}
foreach (array_keys($stats) as $t) $nos[$t] = 1;
if (!$nos) foreach (db()->query('SELECT team_no FROM teams') as $r) $nos[(int)$r['team_no']] = 1;
$in = implode(',', array_map('intval', array_keys($nos))) ?: '0';

$F = [];
foreach (db()->query("SELECT team_no, field_key, value FROM team_fields WHERE team_no IN ($in) AND field_key IN ('name','ratings','checks','role','cycle','pick')") as $r) {
  $F[$r['team_no']][$r['field_key']] = $r['value'];
}
$tn = [];
foreach (db()->query("SELECT team_no, name FROM teams WHERE team_no IN ($in)") as $r) $tn[$r['team_no']] = $r['name'];

$list = [];
$maxN = $maxA = 0.0;
foreach (array_keys($nos) as $t) {
  $s = $stats[$t] ?? null;
  $rt = json_decode($F[$t]['ratings'] ?? '{}', true) ?: [];
  $ck = json_decode($F[$t]['checks'] ?? '{}', true) ?: [];
  $avg = $rt ? array_sum($rt) / count($rt) : 0;
  $list[$t] = ['name' => $F[$t]['name'] ?? ($tn[$t] ?? ''), 's' => $s, 'r' => $avg, 'role' => $F[$t]['role'] ?? '',
               'cycle' => $F[$t]['cycle'] ?? '', 'ir' => !empty($ck['inspect_robot']), 'if' => !empty($ck['inspect_field']),
               'pick' => $F[$t]['pick'] ?? ''];
  if ($s) { $maxN = max($maxN, $s['np']); $maxA = max($maxA, $s['ao']); }
}

panel_head('Pick List'); ?>
<style>
main{max-width:1300px}
.bar{display:flex;gap:14px;flex-wrap:wrap;margin:12px 0;align-items:center}
.bar select{padding:8px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px}
.w{display:flex;align-items:center;gap:6px;font-size:12px}
.pk button{background:#0F172A;border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:4px;padding:3px 8px;cursor:pointer;margin-right:3px;font-size:11px}
.pk button.on[data-p="1"]{background:#10B981;color:#04130d}.pk button.on[data-p="2"]{background:#3B82F6}.pk button.on[data-p=dnp]{background:#64748B}
tr.dnp td{opacity:.45;text-decoration:line-through}
tr.me td{background:rgba(245,158,11,.1)}
option{color:#000}
</style>
<h1>Canlı İttifak Seçimi (Pick List)</h1>
<form class="bar" method="get">
  <select name="ev" onchange="this.form.submit()">
    <?php foreach ($opts as $k => [$s, $c, $n]): ?><option value="<?= h($k) ?>" <?= $k === $sel ? 'selected' : '' ?>><?= h($n) ?> (<?= h($c) ?>) — <?= $has[$k] ?? 0 ?> maç</option><?php endforeach; ?>
  </select>
  <label class="w">Faulsüz OPR <input type="range" id="wN" min="0" max="100" value="50"></label>
  <label class="w">Otonom OPR <input type="range" id="wA" min="0" max="100" value="15"></label>
  <label class="w">Puanlama <input type="range" id="wR" min="0" max="100" value="35"></label>
  <label class="w"><input type="checkbox" id="hide"> Etiketlenenleri gizle</label>
</form>
<?php if (!$stats): ?><div class="note">Bu turnuvada hesaplanabilir maç verisi yok, sıralama yalnızca 1-5 puanlamalara göre. Turnuva sayfasından maçları çekin.</div><?php endif; ?>
<table id="tbl">
  <thead><tr><th>#</th><th>Takım</th><th>Maç</th><th>Faulsüz OPR</th><th>Otonom OPR</th><th>Puan (1-5)</th><th>Rol</th><th>Cycle</th><th>Muayene R/F</th><th>Etiket</th><th>Skor</th></tr></thead>
  <tbody>
  <?php foreach ($list as $t => $x): $s = $x['s'];
    $n = ($s && $maxN > 0) ? max(0, $s['np']) / $maxN : 0; $a = ($s && $maxA > 0) ? max(0, $s['ao']) / $maxA : 0; $r = $x['r'] / 5; ?>
    <tr data-team="<?= (int)$t ?>" data-n="<?= round($n, 4) ?>" data-a="<?= round($a, 4) ?>" data-r="<?= round($r, 4) ?>" data-pick="<?= h($x['pick']) ?>"
        class="<?= $t == 25601 ? 'me ' : '' ?><?= $x['pick'] === 'dnp' ? 'dnp' : '' ?>">
      <td class="rk"></td>
      <td><a style="color:#F59E0B;font-weight:700" href="/scouting/team.php?no=<?= (int)$t ?>"><?= (int)$t ?></a> <?= h($x['name']) ?></td>
      <td><?= $s ? $s['m'] : '—' ?></td>
      <td><?= $s ? round($s['np'], 1) : '—' ?></td><td><?= $s ? round($s['ao'], 1) : '—' ?></td>
      <td><?= $x['r'] ? round($x['r'], 1) : '—' ?></td><td><?= h($x['role']) ?></td><td><?= h($x['cycle']) ?></td>
      <td><?= $x['ir'] ? '🟢' : '⚪' ?> <?= $x['if'] ? '🟢' : '⚪' ?></td>
      <td class="pk">
        <button data-p="1" class="<?= $x['pick'] === '1' ? 'on' : '' ?>">1. tercih</button>
        <button data-p="2" class="<?= $x['pick'] === '2' ? 'on' : '' ?>">Tamamlayıcı</button>
        <button data-p="dnp" class="<?= $x['pick'] === 'dnp' ? 'on' : '' ?>">DNP</button>
      </td>
      <td class="sc"><b></b></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p style="font-size:11px;color:#94A3B8">Skor = seçilen ağırlıklarla faulsüz OPR, otonom OPR ve puanlama ortalamasının ağırlıklı ortalaması (0-100). Kaydırıcıları oynayıp sıralamayı canlı değiştirebilirsiniz. DNP'ler en alta iner.</p>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const tb = document.querySelector('#tbl tbody');
const rows = [].slice.call(tb.querySelectorAll('tr'));
function recalc() {
  const wn = +wN.value, wa = +wA.value, wr = +wR.value, sum = (wn + wa + wr) || 1;
  rows.forEach(function (tr) {
    tr._sc = (wn * +tr.dataset.n + wa * +tr.dataset.a + wr * +tr.dataset.r) / sum * 100;
    tr.querySelector('.sc b').textContent = tr._sc.toFixed(0);
  });
  rows.sort(function (a, b) {
    const da = a.dataset.pick === 'dnp' ? 1 : 0, db = b.dataset.pick === 'dnp' ? 1 : 0;
    return da - db || b._sc - a._sc;
  });
  let i = 0;
  rows.forEach(function (tr) {
    tb.appendChild(tr);
    tr.style.display = (hide.checked && tr.dataset.pick) ? 'none' : '';
    tr.querySelector('.rk').textContent = tr.dataset.pick === 'dnp' ? '' : ++i;
  });
}
['wN', 'wA', 'wR', 'hide'].forEach(function (id) { document.getElementById(id).addEventListener('input', recalc); });
rows.forEach(function (tr) {
  tr.querySelectorAll('.pk button').forEach(function (b) {
    b.addEventListener('click', function () {
      const v = b.classList.contains('on') ? '' : b.dataset.p;
      const fd = new FormData(); fd.append('csrf', CSRF); fd.append('team', tr.dataset.team);
      fd.append('kind', 'field'); fd.append('key', 'pick'); fd.append('value', v);
      fetch('/scouting/save.php', {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) { alert(j.error || 'Kaydedilemedi'); return; }
        tr.dataset.pick = v;
        tr.classList.toggle('dnp', v === 'dnp');
        tr.querySelectorAll('.pk button').forEach(function (x) { x.classList.toggle('on', x.dataset.p === v); });
        recalc();
      });
    });
  });
});
recalc();
</script>
<?php panel_foot();