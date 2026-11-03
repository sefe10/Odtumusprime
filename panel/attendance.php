<?php

require __DIR__ . '/../app/panel_ui.php';

require __DIR__ . '/../app/users.php';

$u = require_perm('attendance_edit');



$units = all_units();

$uid  = (int)($_GET['unit'] ?? ($units[0]['id'] ?? 0));

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  header('Content-Type: application/json; charset=utf-8');

  csrf_check();

  $unit = (int)($_POST['unit'] ?? 0); $user = (int)($_POST['user'] ?? 0);

  $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'] ?? '') ? $_POST['date'] : null;

  $s = $_POST['status'] ?? '';

  $chk = db()->prepare('SELECT 1 FROM unit_members WHERE unit_id=? AND user_id=?');

  $chk->execute([$unit, $user]);

  if (!$d || !$chk->fetchColumn() || !in_array($s, ['P', 'A', 'E', ''], true)) { echo json_encode(['ok' => false]); exit; }

  if ($s === '') {

    db()->prepare('DELETE FROM attendance WHERE user_id=? AND unit_id=? AND att_date=?')->execute([$user, $unit, $d]);

  } else {

    db()->prepare('INSERT INTO attendance (user_id,unit_id,att_date,status,updated_by) VALUES (?,?,?,?,?)

      ON DUPLICATE KEY UPDATE status=VALUES(status), updated_by=VALUES(updated_by)')->execute([$user, $unit, $d, $s, $u['id']]);

  }

  audit('attendance', "Devam: kullanıcı #$user $d = " . ($s ?: 'silindi'));

  echo json_encode(['ok' => true]); exit;

}



$members = db()->prepare("SELECT u.id, u.full_name, u.role FROM unit_members um JOIN users u ON u.id=um.user_id

  WHERE um.unit_id=? AND u.active=1 ORDER BY u.role DESC, u.full_name");

$members->execute([$uid]);

$members = $members->fetchAll();



$cur = [];

$q = db()->prepare('SELECT user_id, status FROM attendance WHERE unit_id=? AND att_date=?');

$q->execute([$uid, $date]);

foreach ($q as $r) $cur[$r['user_id']] = $r['status'];



$stat = [];

$q = db()->prepare("SELECT user_id, SUM(status='P') p, SUM(status='A') a, SUM(status='E') e FROM attendance

  WHERE unit_id=? AND att_date >= CURDATE() - INTERVAL 90 DAY GROUP BY user_id");

$q->execute([$uid]);

foreach ($q as $r) $stat[$r['user_id']] = $r;



panel_head('Devam-Devamsızlık'); ?>

<style>

.st button{background:#0F172A;border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:4px;padding:5px 12px;cursor:pointer;margin-right:4px}

.st button.on[data-s=P]{background:#10B981;color:#04130d}.st button.on[data-s=A]{background:#E11D48}.st button.on[data-s=E]{background:#F59E0B;color:#060913}

.bar{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}.bar select,.bar input{padding:8px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px}

</style>

<h1>Devam-Devamsızlık</h1>

<form class="bar" method="get">

  <select name="unit" onchange="this.form.submit()">

    <?php foreach ($units as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $x['id'] == $uid ? 'selected' : '' ?>><?= h($x['name']) ?></option><?php endforeach; ?>

  </select>

  <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">

</form>

<table>

  <tr><th>Ad Soyad</th><th>Rol</th><th><?= h(date('d.m.Y', strtotime($date))) ?></th><th>Son 90 gün</th></tr>

  <?php foreach ($members as $m): $s = $stat[$m['id']] ?? null; $p = (int)($s['p'] ?? 0); $a = (int)($s['a'] ?? 0); $e = (int)($s['e'] ?? 0); ?>

    <tr data-user="<?= (int)$m['id'] ?>">

      <td><b><?= h($m['full_name']) ?></b></td>

      <td><?= $m['role'] === 'youth_mentor' ? 'Young Mentor' : 'Öğrenci' ?></td>

      <td class="st">

        <?php foreach (['P' => 'Var', 'A' => 'Yok', 'E' => 'İzinli'] as $k => $l): ?>

          <button type="button" data-s="<?= $k ?>" class="<?= ($cur[$m['id']] ?? '') === $k ? 'on' : '' ?>"><?= $l ?></button>

        <?php endforeach; ?>

      </td>

      <td><?= ($p + $a) ? round($p * 100 / ($p + $a)) . '%' : '—' ?> <span style="color:#94A3B8;font-size:11px">(<?= $p ?> var, <?= $a ?> yok, <?= $e ?> izinli)</span></td>

    </tr>

  <?php endforeach; ?>

  <?php if (!$members): ?><tr><td colspan="4" style="color:#94A3B8">Bu birimde üye yok. Kullanıcılar sayfasından birim atayın.</td></tr><?php endif; ?>

</table>

<script>

const CSRF = <?= json_encode(csrf_token()) ?>, UNIT = <?= (int)$uid ?>, DATE = <?= json_encode($date) ?>;

document.querySelectorAll('.st').forEach(function (td) {

  td.querySelectorAll('button').forEach(function (b) {

    b.addEventListener('click', function () {

      const on = b.classList.contains('on');

      const s = on ? '' : b.dataset.s;

      const fd = new FormData(); fd.append('csrf', CSRF); fd.append('unit', UNIT); fd.append('date', DATE);

      fd.append('user', td.parentNode.dataset.user); fd.append('status', s);

      fetch('', {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) {

        if (!j.ok) { alert('Kaydedilemedi'); return; }

        td.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x.dataset.s === s); });

      });

    });

  });

});

</script>

<?php panel_foot();
