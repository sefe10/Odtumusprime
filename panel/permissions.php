<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/users.php';
$me = require_login(['mentor']);
$cat = perm_catalog(); $keys = perm_keys();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  csrf_check();
  $uid = (int)($_POST['user'] ?? 0);
  $st = db()->prepare("SELECT full_name FROM users WHERE id=? AND role<>'mentor'");
  $st->execute([$uid]);
  $nm = $st->fetchColumn();
  if (!$nm) { echo json_encode(['ok' => false, 'error' => 'Kullanıcı bulunamadı']); exit; }
  $act = $_POST['act'] ?? '';
  if ($act === 'set') {
    $p = $_POST['perm'] ?? '';
    if (!in_array($p, $keys, true)) { echo json_encode(['ok' => false, 'error' => 'Geçersiz yetki']); exit; }
    $on = !empty($_POST['val']);
    if ($on) db()->prepare('INSERT IGNORE INTO user_perms (user_id,perm,granted_by) VALUES (?,?,?)')->execute([$uid, $p, $me['id']]);
    else db()->prepare('DELETE FROM user_perms WHERE user_id=? AND perm=?')->execute([$uid, $p]);
    audit('perms', ($on ? 'Yetki verildi: ' : 'Yetki alındı: ') . $nm . ' — ' . (perm_labels()[$p]));
  } elseif ($act === 'row') {
    db()->prepare('DELETE FROM user_perms WHERE user_id=?')->execute([$uid]);
    $all = ($_POST['mode'] ?? '') === 'all';
    if ($all) { $ins = db()->prepare('INSERT INTO user_perms (user_id,perm,granted_by) VALUES (?,?,?)'); foreach ($keys as $k) $ins->execute([$uid, $k, $me['id']]); }
    audit('perms', ($all ? 'Tüm yetkiler verildi: ' : 'Tüm yetkiler alındı: ') . $nm);
  }
  echo json_encode(['ok' => true]); exit;
}

$users = db()->query("SELECT id, full_name, role, active FROM users ORDER BY FIELD(role,'mentor','youth_mentor','student'), full_name")->fetchAll();
$has = [];
foreach (db()->query('SELECT user_id, perm FROM user_perms') as $r) $has[$r['user_id']][$r['perm']] = 1;
$labels = perm_labels();

panel_head('Kullanıcılar ve Yetkiler'); ?>
<style>
main{max-width:1500px}
.vt{writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;font-size:12px;cursor:pointer;padding:6px 2px}
.pm th,.pm td{padding:5px 6px;text-align:center}
.pm td:first-child,.pm th:first-child{text-align:left;position:sticky;left:0;background:#060913;z-index:2;min-width:170px}
.pm td.gl{border-left:3px solid rgba(245,158,11,.5)}
.pm input[type=checkbox]{width:18px;height:18px;cursor:pointer;accent-color:#F59E0B}
.bar{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}
.bar input,.bar select{padding:8px 10px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px}
.mini{background:none;border:0;color:#9CC8FF;cursor:pointer;font-size:11px;text-decoration:underline}
</style>
<?php users_tabs('perms'); ?>
<h1>Kullanıcılar ve Yetkiler (<?= count($users) ?>)</h1>
<div class="note">Kutucuğa tıklayınca yetki anında verilir veya alınır. Mentörlerin tüm yetkileri vardır. Sütun başlığına tıklayarak görünen herkese toplu verebilirsiniz.
Kullanıcı yönetimi, denetim kaydı, FTC senkron, site sayfaları, evrak ve seyahat bilgileri güvenlik nedeniyle yalnızca mentörde kalır.</div>
<div class="bar">
  <input id="q" placeholder="Ad ara…" oninput="flt()">
  <select id="fr" onchange="flt()"><option value="">Tüm roller</option><option value="mentor">Mentor</option><option value="youth_mentor">Young Mentor</option><option value="student">Öğrenci</option></select>
</div>
<div style="overflow-x:auto"><table class="pm" id="tbl">
  <tr class="noexp"><th></th><th></th>
    <?php foreach ($cat as $g => $ps): ?><th colspan="<?= count($ps) ?>" style="border-left:3px solid rgba(245,158,11,.5);color:#F59E0B"><?= h($g) ?></th><?php endforeach; ?><th></th><th></th></tr>
  <tr><th>Ad Soyad</th><th>Rol</th>
    <?php foreach ($cat as $ps): $i = 0; foreach ($ps as $k => $l): ?><th data-perm="<?= h($k) ?>" class="<?= $i++ === 0 ? 'gl' : '' ?>"><span class="vt" title="Sütunu toplu seç"><?= h($l) ?></span></th><?php endforeach; endforeach; ?>
    <th>Toplu</th><th></th></tr>
  <?php foreach ($users as $x): $ment = $x['role'] === 'mentor'; ?>
    <tr data-user="<?= (int)$x['id'] ?>" data-s="<?= h(mb_strtolower($x['full_name'])) ?>" data-r="<?= h($x['role']) ?>" style="<?= $x['active'] ? '' : 'opacity:.5' ?>">
      <td><b><?= h($x['full_name']) ?></b><?= $x['active'] ? '' : ' <small>(pasif)</small>' ?></td>
      <td><?= h(ROLE_LABELS[$x['role']] ?? $x['role']) ?></td>
      <?php foreach ($cat as $ps): $i = 0; foreach ($ps as $k => $l): ?>
        <td class="<?= $i++ === 0 ? 'gl' : '' ?>"><input type="checkbox" data-perm="<?= h($k) ?>" data-user="<?= (int)$x['id'] ?>"
          <?= ($ment || !empty($has[$x['id']][$k])) ? 'checked' : '' ?> <?= $ment ? 'disabled' : '' ?>></td>
      <?php endforeach; endforeach; ?>
      <td><?php if (!$ment): ?><button class="mini" data-row="all">Hepsi</button> <button class="mini" data-row="none">Hiçbiri</button><?php else: ?><small>tümü</small><?php endif; ?></td>
      <td><a class="mini" style="color:#F59E0B" href="/panel/user-edit.php?id=<?= (int)$x['id'] ?>">düzenle</a></td>
    </tr>
  <?php endforeach; ?>
</table></div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
function post(p) {
  const fd = new FormData(); fd.append('csrf', CSRF);
  for (const k in p) fd.append(k, p[k]);
  return fetch('', {method: 'POST', body: fd}).then(function (r) { return r.json(); });
}
function flash(el) { el.style.outline = '2px solid #10B981'; setTimeout(function () { el.style.outline = ''; }, 700); }
function setOne(c, on) {
  return post({act: 'set', user: c.dataset.user, perm: c.dataset.perm, val: on ? 1 : 0}).then(function (j) {
    if (!j.ok) { c.checked = !on; alert(j.error || 'Kaydedilemedi'); } else { c.checked = on; flash(c); }
  }).catch(function () { c.checked = !on; });
}
document.querySelectorAll('input[data-perm]').forEach(function (c) {
  c.addEventListener('change', function () { setOne(c, c.checked); });
});
document.querySelectorAll('[data-row]').forEach(function (b) {
  b.addEventListener('click', function () {
    const tr = b.closest('tr'), all = b.dataset.row === 'all';
    post({act: 'row', user: tr.dataset.user, mode: b.dataset.row}).then(function (j) {
      if (j.ok) tr.querySelectorAll('input[data-perm]').forEach(function (c) { c.checked = all; });
    });
  });
});
document.querySelectorAll('th[data-perm]').forEach(function (th) {
  th.addEventListener('click', async function () {
    const cs = [].filter.call(document.querySelectorAll('input[data-perm="' + th.dataset.perm + '"]:not(:disabled)'), function (c) { return c.closest('tr').style.display !== 'none'; });
    const to = !cs.every(function (c) { return c.checked; });
    for (const c of cs) if (c.checked !== to) await setOne(c, to);
  });
});
function flt() {
  const q = document.getElementById('q').value.toLowerCase().trim(), r = document.getElementById('fr').value;
  document.querySelectorAll('#tbl tr[data-user]').forEach(function (tr) {
    tr.style.display = ((!q || tr.dataset.s.indexOf(q) > -1) && (!r || tr.dataset.r === r)) ? '' : 'none';
  });
}
</script>
<?php panel_foot();
