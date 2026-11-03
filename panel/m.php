<?php

require __DIR__ . '/../app/panel_ui.php';

require __DIR__ . '/../app/modules.php';

require __DIR__ . '/../app/users.php';

$u = require_login();



$slug = $_GET['m'] ?? '';

$M = modules()[$slug] ?? null;

if (!$M) { http_response_code(404); exit('Modül bulunamadı.'); }

if (!mod_can($slug, 'view')) { http_response_code(403); exit('Bu sayfa için yetkiniz yok. Yetki için mentörünüze başvurun.'); }
$edit = mod_can($slug, 'edit');
$del  = mod_can($slug, 'del');



$units  = all_units();

$people = db()->query('SELECT id, full_name FROM users WHERE active=1 ORDER BY full_name')->fetchAll();

$rows   = db()->query("SELECT {$M['table']}.*" . ($M['sel'] ?? '') . " FROM {$M['table']} ORDER BY {$M['order']}")->fetchAll();



function opts_for(array $def, array $units, array $people): array {

  if ($def[1] === 'sponsor') return array_map(fn($x) => [$x['id'], $x['company']], db()->query('SELECT id, company FROM m_sponsors ORDER BY company')->fetchAll());
  if ($def[1] === 'unit') return array_map(fn($x) => [$x['id'], $x['name']], $units);

  if ($def[1] === 'user') return array_map(fn($x) => [$x['id'], $x['full_name']], $people);

  if ($def[1] === 'select') return array_map(fn($x) => [$x, $x], $def[2]);

  return [];

}



function cell_html(string $k, array $def, $v, bool $edit, array $opts): string {

  $dis = $edit ? '' : ' disabled';

  $a = ' data-col="' . h($k) . '"';

  switch ($def[1]) {

    case 'area':  return '<textarea rows="2"' . $a . $dis . '>' . h($v) . '</textarea>';

    case 'num':   return '<input type="number" step="1"' . $a . $dis . ' value="' . h($v) . '" style="width:80px">';

    case 'money': return '<input type="number" step="0.01"' . $a . $dis . ' value="' . h($v) . '" style="width:110px">';

    case 'date':  return '<input type="date"' . $a . $dis . ' value="' . h($v) . '">';

    case 'check': return '<input type="checkbox"' . $a . $dis . ($v ? ' checked' : '') . '>';

    case 'url':

      $l = (is_string($v) && preg_match('#^https?://\S+$#i', $v)) ? ' <a style="color:#F59E0B" href="' . h($v) . '" target="_blank" rel="noopener noreferrer">↗</a>' : '';

      return '<input type="text"' . $a . $dis . ' value="' . h($v) . '">' . $l;

    case 'ro': return '<b>' . number_format((float)$v, 2, ',', '.') . '</b>';
    case 'select': case 'unit': case 'user': case 'sponsor':
      $o = ($def[1] === 'select' && (string)$v !== '') ? '' : '<option value="">—</option>';

      foreach ($opts as [$val, $label]) {

        $o .= '<option value="' . h($val) . '"' . ((string)$v === (string)$val ? ' selected' : '') . '>' . h($label) . '</option>';

      }

      return '<select' . $a . $dis . '>' . $o . '</select>';

  }

  return '<input type="text"' . $a . $dis . ' value="' . h($v) . '">';

}



$sums = [];

foreach ($M['sum'] ?? [] as $c) $sums[$c] = array_sum(array_map(fn($r) => (float)$r[$c], $rows));

$inc = $exp = 0;

if ($slug === 'budget') foreach ($rows as $r) { if ($r['kind'] === 'Gelir') $inc += $r['amount']; else $exp += $r['amount']; }



panel_head($M['title']); ?>

<style>

main{max-width:1400px}

.mt td input[type=text],.mt td input[type=number],.mt td input[type=date],.mt td select,.mt td textarea{width:100%;min-width:90px;box-sizing:border-box;padding:6px 8px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:5px;color:#fff;font:inherit;font-size:12px}

.mt td textarea{min-width:200px;resize:vertical}

option{color:#000}

tr.warn td{background:rgba(225,29,72,.12)}

.bar{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;align-items:center}

.bar select{padding:8px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px}

</style>

<h1><?= h($M['title']) ?> (<?= count($rows) ?>)</h1>

<div class="bar">

  <?php if ($edit): ?><button class="btn" id="add">+ Yeni satır</button><?php endif; ?>

  <?php foreach ($M['filter'] ?? [] as $fk): $d = $M['cols'][$fk]; ?>

    <select data-filter="<?= h($fk) ?>"><option value=""><?= h($d[0]) ?>: tümü</option>

      <?php foreach (opts_for($d, $units, $people) as [$v, $l]): ?><option value="<?= h($v) ?>"><?= h($l) ?></option><?php endforeach; ?>

    </select>

  <?php endforeach; ?>

  <?php foreach ($sums as $c => $t): ?><span>Toplam <?= h($M['cols'][$c][0]) ?>: <b><?= number_format($t, 0, ',', '.') ?></b></span><?php endforeach; ?>

  <?php if ($slug === 'budget'): ?>

    <span>Gelir: <b style="color:#6EE7B7"><?= number_format($inc, 2, ',', '.') ?></b></span>

    <span>Gider: <b style="color:#FDA4AF"><?= number_format($exp, 2, ',', '.') ?></b></span>

    <span>Kalan: <b><?= number_format($inc - $exp, 2, ',', '.') ?></b></span>

  <?php endif; ?>

</div>

<div style="overflow-x:auto"><table class="mt" id="tbl">

  <tr><?php foreach ($M['cols'] as $d): ?><th><?= h($d[0]) ?></th><?php endforeach; ?><?= $del ? '<th></th>' : '' ?></tr>

  <?php foreach ($rows as $r):

    $attrs = '';

    foreach ($M['filter'] ?? [] as $fk) $attrs .= ' data-f-' . h($fk) . '="' . h($r[$fk]) . '"';

    $warn = isset($M['warn']) && ($M['warn'])($r); ?>

    <tr data-id="<?= (int)$r['id'] ?>" class="<?= $warn ? 'warn' : '' ?>"<?= $attrs ?>>

      <?php foreach ($M['cols'] as $k => $d): ?><td><?= cell_html($k, $d, $r[$k], $edit, opts_for($d, $units, $people)) ?></td><?php endforeach; ?>

      <?php if ($del): ?><td><button class="btn red" data-del="<?= (int)$r['id'] ?>" style="padding:5px 9px">sil</button></td><?php endif; ?>

    </tr>

  <?php endforeach; ?>

</table></div>

<script>

const CSRF = <?= json_encode(csrf_token()) ?>, SLUG = <?= json_encode($slug) ?>;

function post(p) {

  const fd = new FormData(); fd.append('csrf', CSRF); fd.append('m', SLUG);

  for (const k in p) fd.append(k, p[k]);

  return fetch('/panel/m-save.php', {method: 'POST', body: fd}).then(function (r) { return r.json(); });

}

document.querySelectorAll('tr[data-id] [data-col]').forEach(function (el) {

  const ev = (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'date') ? 'change' : 'blur';

  let old = el.type === 'checkbox' ? el.checked : el.value;

  el.addEventListener(ev, function () {

    const v = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;

    if (ev === 'blur' && v === old) return;

    old = el.type === 'checkbox' ? el.checked : el.value;

    const tr = el.closest('tr');

    post({kind: 'set', id: tr.dataset.id, col: el.dataset.col, value: v}).then(function (j) {

      el.style.outline = '2px solid ' + (j.ok ? '#10B981' : '#E11D48');

      setTimeout(function () { el.style.outline = ''; }, 1000);

      if (!j.ok) alert(j.error || 'Kaydedilemedi');

      else if (tr.dataset['f' + el.dataset.col.charAt(0).toUpperCase() + el.dataset.col.slice(1)] !== undefined) {

        tr.setAttribute('data-f-' + el.dataset.col, v);

      }

    });

  });

});

const add = document.getElementById('add');

if (add) add.addEventListener('click', function () {

  post({kind: 'add'}).then(function (j) { if (j.ok) location.reload(); else alert(j.error || 'Eklenemedi'); });

});

document.querySelectorAll('[data-del]').forEach(function (b) {

  b.addEventListener('click', function () {

    if (!confirm('Bu satır silinsin mi?')) return;

    post({kind: 'del', id: b.dataset.del}).then(function (j) { if (j.ok) b.closest('tr').remove(); else alert(j.error || 'Silinemedi'); });

  });

});

function flt() {

  const f = {};

  document.querySelectorAll('[data-filter]').forEach(function (s) { if (s.value !== '') f[s.dataset.filter] = s.value; });

  document.querySelectorAll('#tbl tr[data-id]').forEach(function (tr) {

    let ok = true;

    for (const k in f) if (tr.getAttribute('data-f-' + k) !== f[k]) ok = false;

    tr.style.display = ok ? '' : 'none';

  });

}

document.querySelectorAll('[data-filter]').forEach(function (s) { s.addEventListener('change', flt); });

</script>

<?php panel_foot();
