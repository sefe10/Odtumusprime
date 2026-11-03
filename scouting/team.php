<?php

require __DIR__ . '/../app/panel_ui.php';

require __DIR__ . '/../app/scouting.php';

$u = require_login();



$no = (int)($_GET['no'] ?? 0);

$st = db()->prepare('SELECT * FROM teams WHERE team_no=?');

$st->execute([$no]);

$team = $st->fetch();

if (!$team) { http_response_code(404); exit('Takım bulunamadı.'); }



$f = sc_team_fields($no);

$val = fn($k) => $f[$k]['value'] ?? '';

$ratings = json_decode($val('ratings') ?: '{}', true) ?: [];

$checks  = json_decode($val('checks') ?: '{}', true) ?: [];

$manage = can('team_details');

$name    = $val('name') ?: $team['name'];



$hist = db()->prepare('SELECT * FROM team_history WHERE team_no=? ORDER BY season DESC, event_date DESC, id');

$hist->execute([$no]);

$hist = $hist->fetchAll();



$evs = db()->prepare('SELECT te.event_code, e.name FROM team_events te

  LEFT JOIN events e ON e.code=te.event_code AND e.season=te.season WHERE te.team_no=? AND te.season=? ORDER BY e.event_date');

$evs->execute([$no, FTC_SEASON]);

$evs = $evs->fetchAll();



$users = $manage ? db()->query("SELECT id, full_name, role FROM users WHERE active=1 AND role IN ('student','youth_mentor') ORDER BY role DESC, full_name")->fetchAll() : [];

$repName = '';

if ($team['rep_user_id']) {

  $q = db()->prepare('SELECT full_name FROM users WHERE id=?'); $q->execute([$team['rep_user_id']]);

  $repName = (string)$q->fetchColumn();

}



$log = db()->prepare('SELECT user_name, action, created_at FROM audit_log WHERE team_no=? ORDER BY id DESC LIMIT 10');

$log->execute([$no]);

$log = $log->fetchAll();



panel_head('#' . $no . ' ' . $name); ?>

<style>

main{max-width:1200px}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 16px}

.fld.area{grid-column:1/-1}

@media(max-width:800px){.grid{grid-template-columns:1fr}}

textarea.t{min-height:80px;resize:vertical}

.bd{font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:6px;background:rgba(255,255,255,.08);color:#94A3B8}

.bd-auto{background:rgba(59,130,246,.2);color:#93C5FD}

.bd-manual{background:rgba(16,185,129,.2);color:#6EE7B7}

.bd-import{background:rgba(245,158,11,.2);color:#FCD34D}

.pill{display:inline-block;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.4);color:#FCD34D;border-radius:4px;padding:2px 8px;margin:0 4px 4px 0;font-size:12px;text-decoration:none}

.rate{display:inline-flex;gap:4px}

.rate button{background:#0F172A;border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:4px;padding:4px 10px;cursor:pointer}

.rate button.on{background:#F59E0B;color:#060913;font-weight:800}

.sec{margin:28px 0 8px;color:#F59E0B;font-size:15px;border-bottom:1px solid rgba(255,255,255,.1);padding-bottom:4px}

td[contenteditable]:focus{outline:1px solid #F59E0B;background:rgba(245,158,11,.08)}

#stat{position:fixed;right:16px;bottom:16px;background:#0F172A;border:1px solid rgba(255,255,255,.2);padding:6px 12px;border-radius:6px;font-size:12px}

.mini{background:none;border:0;color:#94A3B8;cursor:pointer;font-size:11px;text-decoration:underline}

.rrow{display:flex;align-items:center;gap:12px;margin:6px 0}.rrow span{width:130px}

a.lnk{margin-left:8px;font-size:11px;color:#93C5FD;text-decoration:none}

.mbox{background:rgba(255,255,255,.03);padding:10px;border-radius:6px}

.mbox table{font-size:12px;margin:0}

.mbox td.num{text-align:center;min-width:34px}

.mbox th{text-align:center}

.mw td{background:rgba(16,185,129,.10)}.ml td{background:rgba(225,29,72,.10)}.mt td{background:rgba(148,163,184,.10)}

.tm{white-space:nowrap}.tm.me{font-weight:800;color:#F59E0B}.tm a{color:#F59E0B;text-decoration:none}

.dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px}.dot.r{background:#EF4444}.dot.b{background:#3B82F6}

</style>



<h1>#<?= (int)$no ?> <?= h($name) ?></h1>

<p>

  <a class="pill" target="_blank" rel="noopener" href="https://ftc-events.firstinspires.org/<?= FTC_SEASON ?>/team/<?= (int)$no ?>">Resmi takım sayfası ↗</a>

  <?php foreach ($evs as $e): ?>

    <a class="pill" target="_blank" rel="noopener" href="https://ftc-events.firstinspires.org/<?= FTC_SEASON ?>/<?= h($e['event_code']) ?>"><?= h($e['name'] ?: $e['event_code']) ?> ↗</a>

  <?php endforeach; ?>

</p>

<p>Temsilci:

  <?php if ($manage): ?>

    <select id="rep" style="padding:7px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px">

      <option value="0">— seçilmedi —</option>

      <?php foreach ($users as $x): ?>

        <option value="<?= (int)$x['id'] ?>" <?= $x['id'] == $team['rep_user_id'] ? 'selected' : '' ?>><?= h($x['full_name']) ?> (<?= $x['role'] === 'student' ? 'öğrenci' : 'young mentor' ?>)</option>

      <?php endforeach; ?>

    </select>

  <?php else: ?><b><?= h($repName ?: '—') ?></b><?php endif; ?>

</p>



<?php foreach (sc_sections() as $secTitle => $fields): ?>

  <h2 class="sec"><?= h($secTitle) ?></h2>

  <div class="grid">

    <?php foreach ($fields as $key => [$label, $type]): $row = $f[$key] ?? null; ?>

      <div class="fld <?= $type === 'area' ? 'area' : '' ?>">

        <label><?= h($label) ?> <?= sc_badge($row) ?>

          <?php if (in_array($key, sc_social_keys(), true)): ?>

            <span class="lk"><?php foreach (sc_links($key, (string)($row['value'] ?? '')) as $l): ?><a class="lnk" target="_blank" rel="noopener noreferrer" href="<?= h($l[1]) ?>"><?= h($l[0]) ?> ↗</a><?php endforeach; ?></span>

          <?php endif; ?>

          <?php if ($manage && in_array($key, sc_auto_keys(), true) && $row && $row['source'] !== 'auto'): ?>

            <button class="mini" type="button" data-reset="<?= h($key) ?>">↺ otomatiğe dön</button>

          <?php endif; ?>

        </label>

        <?php if ($type === 'area'): ?>

          <textarea class="t" data-key="<?= h($key) ?>"><?= h($row['value'] ?? '') ?></textarea>

        <?php else: ?>

          <input type="text" data-key="<?= h($key) ?>" value="<?= h($row['value'] ?? '') ?>">

        <?php endif; ?>

      </div>

    <?php endforeach; ?>

  </div>

<?php endforeach; ?>



<h2 class="sec">Puanlama ve Kontrol Listesi</h2>

<div class="grid">

  <div>

    <?php foreach (sc_ratings() as $k => $label): $cur = (int)($ratings[$k] ?? 0); ?>

      <div class="rrow"><span><?= h($label) ?></span>

        <div class="rate" data-key="<?= h($k) ?>">

          <?php for ($i = 1; $i <= 5; $i++): ?><button type="button" data-v="<?= $i ?>" class="<?= $cur === $i ? 'on' : '' ?>"><?= $i ?></button><?php endfor; ?>

        </div>

      </div>

    <?php endforeach; ?>

  </div>

  <div>

    <?php foreach (sc_checks() as $k => $label): ?>

      <label style="display:block;margin:8px 0"><input type="checkbox" class="chk" data-key="<?= h($k) ?>" <?= !empty($checks[$k]) ? 'checked' : '' ?>> <?= h($label) ?></label>

    <?php endforeach; ?>

  </div>

</div>



<h2 class="sec">Turnuva Geçmişi</h2>

<p style="color:#94A3B8;font-size:12px">Turnuva adına tıklayınca o turnuvadaki maçları (ittifak, rakip, skor, otonom / teleop / faul) altında görürsünüz. Sıra, ödül ve not hücrelerini düzenleyebilirsiniz.</p>

<table id="hist">

  <tr><th>Sezon</th><th>Turnuva</th><th>Tarih</th><th>Sıra</th><th>G-M-B</th><th>Ort.</th><th>Ödüller</th><th>Not</th><th>Kaynak</th><?= $manage ? '<th></th>' : '' ?></tr>

  <?php foreach ($hist as $r): $real = strpos($r['event_code'], 'manual-') !== 0; ?>

    <tr data-id="<?= (int)$r['id'] ?>">

      <td><?= (int)$r['season'] ?>-<?= substr((string)($r['season'] + 1), 2) ?></td>

      <?php if ($real): ?>

        <td><a href="#" class="evt" data-season="<?= (int)$r['season'] ?>" data-code="<?= h($r['event_code']) ?>" style="color:#F59E0B;font-weight:700;text-decoration:none"><?= h($r['event_name'] ?: $r['event_code']) ?> ▾</a></td>

      <?php else: ?>

        <td contenteditable data-col="event_name"><?= h($r['event_name']) ?></td>

      <?php endif; ?>

      <td><?= h($r['event_date']) ?><?php if ($real): ?> <a style="color:#F59E0B" target="_blank" rel="noopener" href="https://ftc-events.firstinspires.org/<?= (int)$r['season'] ?>/<?= h($r['event_code']) ?>">↗</a><?php endif; ?></td>

      <td contenteditable data-col="rank_no"><?= h($r['rank_no']) ?></td>

      <td contenteditable data-col="record_wlt"><?= h($r['record_wlt']) ?></td>

      <td contenteditable data-col="qual_avg"><?= h($r['qual_avg']) ?></td>

      <td contenteditable data-col="awards" style="white-space:pre-wrap"><?= h($r['awards']) ?></td>

      <td contenteditable data-col="notes" style="white-space:pre-wrap"><?= h($r['notes']) ?></td>

      <td><span class="bd bd-<?= $r['source'] === 'auto' ? 'auto' : 'manual' ?>"><?= $r['source'] === 'auto' ? 'Otomatik' : 'Elle' ?></span></td>

      <?php if ($manage): ?><td><button class="mini" data-del="<?= (int)$r['id'] ?>">sil</button></td><?php endif; ?>

    </tr>

  <?php endforeach; ?>

  <?php if (!$hist): ?><tr><td colspan="10" style="color:#94A3B8">Henüz geçmiş verisi yok. Mentör panelinde "FTC Senkron &gt; Geçmişi çek" çalıştırılınca dolacak.</td></tr><?php endif; ?>

</table>

<?php if ($manage): ?>

  <p><input id="hs" type="number" placeholder="Sezon (ör. 2025)" style="width:140px;padding:8px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px">

     <input id="hn" type="text" placeholder="Turnuva adı" style="width:260px;padding:8px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px">

     <button class="btn" id="hadd">+ Elle satır ekle</button></p>

<?php endif; ?>



<h2 class="sec">Son Değişiklikler</h2>

<table>

  <?php foreach ($log as $l): ?><tr><td><?= h($l['created_at']) ?></td><td><?= h($l['user_name']) ?></td><td><?= h($l['action']) ?></td></tr><?php endforeach; ?>

  <?php if (!$log): ?><tr><td style="color:#94A3B8">Henüz kayıt yok.</td></tr><?php endif; ?>

</table>

<div id="stat">Hazır</div>



<script>

const CSRF = <?= json_encode(csrf_token()) ?>, NO = <?= (int)$no ?>;

const stat = document.getElementById('stat');

const SOC = {ig:'https://www.instagram.com/', yt:'https://www.youtube.com/@', tiktok:'https://www.tiktok.com/@', x:'https://x.com/', fb:'https://www.facebook.com/'};



async function save(p) {

  stat.textContent = 'Kaydediliyor…';

  const fd = new FormData(); fd.append('csrf', CSRF); fd.append('team', NO);

  for (const k in p) fd.append(k, p[k]);

  try {

    const r = await fetch('/scouting/save.php', {method: 'POST', body: fd});

    const j = await r.json();

    stat.textContent = j.ok ? '✓ Kaydedildi' : ('⚠ ' + (j.error || 'Hata'));

    return j;

  } catch (e) { stat.textContent = '⚠ Kaydedilemedi'; return {ok: false}; }

}



function links(key, val) {

  const toks = (val || '').trim().split(/[\s,;]+/).filter(Boolean), out = [];

  toks.forEach(function (t) {

    if (t === '-') return;

    const h = t.replace(/^@/, ''); let u = null;

    if (/^https?:\/\/\S+$/i.test(t)) u = t;

    else if (key === 'web' && /^[a-z0-9-]+(\.[a-z0-9-]+)+(\/\S*)?$/i.test(t)) u = 'https://' + t;

    else if (SOC[key] && /^[A-Za-z0-9._-]{1,60}$/.test(h) && (toks.length === 1 || t[0] === '@')) u = SOC[key] + h;

    if (u) out.push([t, u]);

  });

  return out;

}

function renderLinks(lk, key, val) {

  lk.innerHTML = '';

  links(key, val).forEach(function (l) {

    const a = document.createElement('a');

    a.className = 'lnk'; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.href = l[1]; a.textContent = l[0] + ' ↗';

    lk.appendChild(a);

  });

}



document.querySelectorAll('.fld [data-key]').forEach(function (el) {

  let old = el.value;

  el.addEventListener('blur', function () {

    if (el.value === old) return;

    old = el.value;

    save({kind: 'field', key: el.dataset.key, value: el.value}).then(function (j) {

      if (!j.ok) return;

      const fld = el.closest('.fld');

      const b = fld.querySelector('.bd');

      if (b) { b.className = 'bd bd-manual'; b.textContent = 'Elle'; }

      const lk = fld.querySelector('.lk');

      if (lk) renderLinks(lk, el.dataset.key, el.value);

    });

  });

});

document.querySelectorAll('.rate').forEach(function (g) {

  g.querySelectorAll('button').forEach(function (b) {

    b.addEventListener('click', function () {

      const cur = g.querySelector('.on');

      const nv = (cur && cur.dataset.v === b.dataset.v) ? 0 : b.dataset.v;

      g.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x.dataset.v === String(nv)); });

      save({kind: 'rating', key: g.dataset.key, value: nv});

    });

  });

});

document.querySelectorAll('.chk').forEach(function (c) {

  c.addEventListener('change', function () { save({kind: 'check', key: c.dataset.key, value: c.checked ? 1 : 0}); });

});

document.querySelectorAll('#hist > tbody > tr > td[contenteditable]').forEach(function (td) {

  let old = td.innerText.trim();

  td.addEventListener('blur', function () {

    const v = td.innerText.trim();

    if (v === old) return;

    old = v;

    save({kind: 'history', id: td.parentNode.dataset.id, col: td.dataset.col, value: v}).then(function (j) {

      if (j.ok) { const b = td.parentNode.querySelector('.bd'); if (b) { b.className = 'bd bd-manual'; b.textContent = 'Elle'; } }

    });

  });

});

document.querySelectorAll('[data-reset]').forEach(function (b) {

  b.addEventListener('click', function () { save({kind: 'reset', key: b.dataset.reset}).then(function () { location.reload(); }); });

});

document.querySelectorAll('[data-del]').forEach(function (b) {

  b.addEventListener('click', function () {

    if (confirm('Bu satır silinsin mi?')) save({kind: 'history_delete', id: b.dataset.del}).then(function () { location.reload(); });

  });

});

const hadd = document.getElementById('hadd');

if (hadd) hadd.addEventListener('click', function () {

  save({kind: 'history_add', season: document.getElementById('hs').value, name: document.getElementById('hn').value})

    .then(function (j) { if (j.ok) location.reload(); });

});

const rep = document.getElementById('rep');

if (rep) rep.addEventListener('change', function () { save({kind: 'rep', user_id: rep.value}); });



// Turnuva maçları

async function loadMatches(a, box, force) {

  box.textContent = 'Maçlar yükleniyor…';

  try {

    const r = await fetch('/scouting/matches.php?season=' + a.dataset.season + '&event=' + encodeURIComponent(a.dataset.code) + '&team=' + NO + (force ? '&force=1' : ''));

    box.innerHTML = await r.text();

  } catch (e) { box.textContent = 'Maçlar alınamadı.'; }

}

document.querySelectorAll('.evt').forEach(function (a) {

  a.addEventListener('click', function (e) {

    e.preventDefault();

    const tr = a.closest('tr'); let nx = tr.nextElementSibling;

    if (nx && nx.classList.contains('mrow')) { nx.remove(); a.textContent = a.textContent.replace('▴', '▾'); return; }

    nx = document.createElement('tr'); nx.className = 'mrow';

    nx.innerHTML = '<td colspan="12"><div class="mbox"></div></td>';

    tr.after(nx);

    a.textContent = a.textContent.replace('▾', '▴');

    loadMatches(a, nx.querySelector('.mbox'), false);

  });

});

document.addEventListener('click', function (e) {

  const b = e.target.closest ? e.target.closest('[data-refresh]') : null;

  if (!b) return;

  const a = document.querySelector('.evt[data-season="' + b.dataset.season + '"][data-code="' + b.dataset.code + '"]');

  if (a) loadMatches(a, b.closest('.mbox'), true);

});

document.addEventListener('focusin', function (e) {

  const td = e.target.closest ? e.target.closest('td[data-mid]') : null;

  if (td) td.dataset.old = td.innerText.trim();

});

document.addEventListener('focusout', function (e) {

  const td = e.target.closest ? e.target.closest('td[data-mid]') : null;

  if (!td) return;

  const v = td.innerText.trim();

  if (v === td.dataset.old) return;

  save({kind: 'match', id: td.dataset.mid, col: td.dataset.col, value: v});

});
const CAN = <?= json_encode(['d' => can('team_details'), 'c' => can('team_contact'), 'r' => can('team_rating')]) ?>;
const CK = <?= json_encode(sc_contact_keys()) ?>;
document.querySelectorAll('.fld [data-key]').forEach(function (el) {
  const k = el.dataset.key, ok = k === 'pick' ? CAN.r : (CK.indexOf(k) > -1 ? CAN.c : CAN.d);
  if (!ok) { el.readOnly = true; el.style.opacity = '.7'; el.title = 'Bu alanı düzenleme yetkiniz yok'; }
});
if (!CAN.r) document.querySelectorAll('.rate button,.chk').forEach(function (b) { b.disabled = true; });
if (!CAN.d) document.querySelectorAll('#hist > tbody > tr > td[contenteditable]').forEach(function (t) { t.contentEditable = 'false'; });
</script>

<?php panel_foot();
