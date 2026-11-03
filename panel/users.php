<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/users.php';
$me = require_login(['mentor']);
header('Cache-Control: no-store');

$err = ''; $msg = ''; $creds = [];
$units = all_units();

function fill_usernames(): int {
  $rows = db()->query("SELECT id, full_name FROM users WHERE username IS NULL OR username=''")->fetchAll();
  $up = db()->prepare('UPDATE users SET username=? WHERE id=?');
  foreach ($rows as $r) $up->execute([make_username($r['full_name'], (int)$r['id']), $r['id']]);
  return count($rows);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $act = $_POST['act'] ?? '';
  try {
    if ($act === 'add') {
      $name  = trim($_POST['name'] ?? '');
      $role  = $_POST['role'] ?? '';
      $grade = trim($_POST['grade'] ?? '');
      if ($name === '' || !isset(ROLE_LABELS[$role])) throw new RuntimeException('Ad soyad ve rol gerekli.');
      $un = trim($_POST['username'] ?? '') ?: make_username($name);
      if (!preg_match('/^[a-z0-9._-]{3,40}$/', $un)) throw new RuntimeException('Kullanıcı adı 3-40 karakter; küçük harf, rakam, nokta, tire.');
      $secret = (string)($_POST['secret'] ?? '');
      $generated = false;
      if ($secret === '') {
        if ($role === 'mentor') throw new RuntimeException('Mentor için şifre girin (en az 10 karakter).');
        $secret = gen_pin(); $generated = true;
      }
      if ($e = secret_ok($role, $secret)) throw new RuntimeException($e);
      db()->prepare('INSERT INTO users (full_name,role,username,pin_hash,grade) VALUES (?,?,?,?,?)')
        ->execute([$name, $role, $un, password_hash($secret, PASSWORD_DEFAULT), $grade ?: null]);
      save_units((int)db()->lastInsertId(), $_POST['units'] ?? [], $_POST['leads'] ?? []);
      audit('users', "Kullanıcı eklendi: $name (" . ROLE_LABELS[$role] . ')');
      if ($generated) $creds[] = [$name, ROLE_LABELS[$role], $un, $secret];
      $msg = "$name eklendi.";
    } elseif ($act === 'gen_usernames') {
      $n = fill_usernames();
      audit('users', "$n kullanıcı adı üretildi");
      $msg = "$n kullanıcı için kullanıcı adı üretildi.";
    } elseif ($act === 'gen_pins') {
      fill_usernames();
      $rows = db()->query("SELECT id, full_name, role, username FROM users
        WHERE active=1 AND role<>'mentor' AND (pin_hash IS NULL OR pin_hash='') ORDER BY role DESC, full_name")->fetchAll();
      foreach ($rows as $r) {
        $p = gen_pin();
        set_secret((int)$r['id'], $p);
        $creds[] = [$r['full_name'], ROLE_LABELS[$r['role']], $r['username'], $p];
      }
      audit('users', count($rows) . ' kullanıcıya PIN üretildi');
      $msg = count($rows) . ' kullanıcıya PIN üretildi.';
    }
  } catch (PDOException $e) {
    $err = (($e->errorInfo[1] ?? 0) == 1062) ? 'Bu kullanıcı adı zaten kullanılıyor.' : 'Veritabanı hatası: ' . $e->getMessage();
  } catch (RuntimeException $e) {
    $err = $e->getMessage();
  }
}

$rows = db()->query("
  SELECT u.*, GROUP_CONCAT(un.code ORDER BY un.sort_order SEPARATOR ',') AS ucodes,
         GROUP_CONCAT(CASE WHEN um.is_lead=1 THEN un.code END SEPARATOR ',') AS lcodes
  FROM users u
  LEFT JOIN unit_members um ON um.user_id=u.id
  LEFT JOIN units un ON un.id=um.unit_id
  GROUP BY u.id
  ORDER BY FIELD(u.role,'mentor','youth_mentor','student'), u.full_name")->fetchAll();
$unitName = [];
foreach ($units as $un) $unitName[$un['code']] = $un['name'];

panel_head('Kullanıcılar'); ?>
<style>
main{max-width:1200px}
.bar{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;align-items:center}
.bar input,.bar select,.f select,.f input[type=text],.f input[type=password]{padding:8px 10px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:6px;color:#fff;font:inherit}
option{color:#000}
.chip{display:inline-block;background:rgba(59,130,246,.18);color:#93C5FD;border-radius:10px;padding:1px 8px;margin:0 3px 3px 0;font-size:11px}
.chip.lead{background:rgba(245,158,11,.2);color:#FCD34D}
.off{opacity:.5}
details.add{background:#0F172A;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:12px 16px;margin:14px 0}
details.add summary{cursor:pointer;font-weight:800;color:#F59E0B}
.f label{margin:10px 0 3px}
.ug{display:grid;grid-template-columns:1fr 70px;gap:4px 12px;max-width:520px}
@media print{header,.bar,.btn,details,table.list,h1{display:none!important}.ok{border:0!important}}
</style>
<h1>Kullanıcılar (<?= count($rows) ?>)</h1>
<?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
<?php if ($creds) creds_box($creds); ?>
<?php if (isset($_GET['deleted'])): ?><div class="ok">Kullanıcı silindi.</div><?php endif; ?>

<details class="add" <?= $err && ($_POST['act'] ?? '') === 'add' ? 'open' : '' ?>>
  <summary>+ Yeni kullanıcı ekle</summary>
  <form method="post" class="f">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="act" value="add">
    <label>Ad Soyad</label><input type="text" name="name" required style="width:100%;max-width:420px">
    <label>Rol</label>
    <select name="role" id="role" onchange="document.getElementById('sw').style.display=this.value==='mentor'?'block':'none'">
      <?php foreach (ROLE_LABELS as $k => $v): ?><option value="<?= $k ?>" <?= $k === 'student' ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
    </select>
    <label>Sınıf (isteğe bağlı)</label><input type="text" name="grade" style="width:120px">
    <label>Kullanıcı adı (boş bırakırsanız addan üretilir)</label><input type="text" name="username" style="width:260px" placeholder="ör. ada.akdogan">
    <div id="sw" style="display:none"><label>Şifre (mentor için zorunlu, en az 10 karakter)</label><input type="password" name="secret" autocomplete="new-password" style="width:260px"></div>
    <p style="font-size:12px;color:#94A3B8;margin:6px 0 0">Öğrenci ve Young Mentor için 6 haneli PIN otomatik üretilir ve eklenince bir kez gösterilir.</p>
    <label>Birimler (işaretleyin, sorumlu ise sağdaki kutuyu da)</label>
    <div class="ug">
      <?php foreach ($units as $un): ?>
        <label style="margin:0"><input type="checkbox" name="units[]" value="<?= (int)$un['id'] ?>"> <?= h($un['name']) ?></label>
        <label style="margin:0"><input type="checkbox" name="leads[]" value="<?= (int)$un['id'] ?>"> sorumlu</label>
      <?php endforeach; ?>
    </div>
    <p><button class="btn">Ekle</button></p>
  </form>
</details>

<div class="bar">
  <form method="post" style="display:inline">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <button class="btn" name="act" value="gen_usernames">Eksik kullanıcı adlarını üret</button>
  </form>
  <form method="post" style="display:inline" onsubmit="return confirm('PIN\'i olmayan tüm öğrenci ve Young Mentor\'lara yeni PIN üretilsin mi?')">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <button class="btn" name="act" value="gen_pins">PIN'i olmayanlara PIN üret</button>
  </form>
</div>
<div class="bar">
  <input id="q" placeholder="Ad veya kullanıcı adı ara…" oninput="flt()">
  <select id="fr" onchange="flt()"><option value="">Tüm roller</option>
    <?php foreach (ROLE_LABELS as $k => $v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?></select>
  <select id="fu" onchange="flt()"><option value="">Tüm birimler</option>
    <?php foreach ($units as $un): ?><option value="<?= h($un['code']) ?>"><?= h($un['name']) ?></option><?php endforeach; ?></select>
</div>

<table class="list" id="tbl">
  <tr><th>Ad Soyad</th><th>Rol</th><th>Sınıf</th><th>Kullanıcı adı</th><th>PIN</th><th>Birimler</th><th></th></tr>
  <?php foreach ($rows as $r):
    $uc = $r['ucodes'] ? explode(',', $r['ucodes']) : [];
    $lc = $r['lcodes'] ? explode(',', $r['lcodes']) : []; ?>
    <tr class="<?= $r['active'] ? '' : 'off' ?>" data-s="<?= h(mb_strtolower($r['full_name'] . ' ' . $r['username'])) ?>" data-r="<?= h($r['role']) ?>" data-u="<?= h(',' . ($r['ucodes'] ?? '') . ',') ?>">
      <td><b><?= h($r['full_name']) ?></b><?= $r['active'] ? '' : ' <span style="color:#FDA4AF">(pasif)</span>' ?></td>
      <td><?= h(ROLE_LABELS[$r['role']] ?? $r['role']) ?></td>
      <td><?= h($r['grade']) ?></td>
      <td><?= h($r['username']) ?: '<span style="color:#FDA4AF">yok</span>' ?></td>
      <td><?= $r['pin_hash'] ? 'var' : '<span style="color:#FDA4AF">yok</span>' ?></td>
      <td><?php foreach ($uc as $c): ?><span class="chip <?= in_array($c, $lc, true) ? 'lead' : '' ?>" title="<?= h($unitName[$c] ?? $c) ?>"><?= h($c) ?><?= in_array($c, $lc, true) ? ' ★' : '' ?></span><?php endforeach; ?></td>
      <td><a class="btn" href="/panel/user-edit.php?id=<?= (int)$r['id'] ?>">Düzenle</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<script>
function flt(){
  var q=document.getElementById('q').value.toLowerCase().trim(), r=document.getElementById('fr').value, u=document.getElementById('fu').value;
  document.querySelectorAll('#tbl tr[data-s]').forEach(function(tr){
    var ok=(!q||tr.dataset.s.indexOf(q)>-1)&&(!r||tr.dataset.r===r)&&(!u||tr.dataset.u.indexOf(','+u+',')>-1);
    tr.style.display=ok?'':'none';
  });
}
</script>
<?php panel_foot();