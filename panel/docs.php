<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/modules.php';
$u = require_login(['mentor']);
$types = doc_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  csrf_check();
  $user = (int)($_POST['user'] ?? 0); $key = $_POST['doc'] ?? ''; $done = !empty($_POST['done']) ? 1 : 0;
  $chk = db()->prepare("SELECT full_name FROM users WHERE id=? AND role='student'");
  $chk->execute([$user]);
  $nm = $chk->fetchColumn();
  if (!$nm || !isset($types[$key])) { echo json_encode(['ok' => false]); exit; }
  db()->prepare('INSERT INTO student_docs (user_id,doc_key,done,updated_by) VALUES (?,?,?,?)
    ON DUPLICATE KEY UPDATE done=VALUES(done), updated_by=VALUES(updated_by)')->execute([$user, $key, $done, $u['id']]);
  audit('docs', "$nm: " . $types[$key] . ' = ' . ($done ? 'tamam' : 'eksik'));
  echo json_encode(['ok' => true]); exit;
}

$students = db()->query("SELECT id, full_name, grade FROM users WHERE role='student' AND active=1 ORDER BY full_name")->fetchAll();
$done = [];
foreach (db()->query('SELECT user_id, doc_key FROM student_docs WHERE done=1') as $r) $done[$r['user_id']][$r['doc_key']] = true;
$total = count($students) * count($types); $have = 0;
foreach ($students as $s) foreach ($types as $k => $_) if (!empty($done[$s['id']][$k])) $have++;

panel_head('Evrak Takibi'); ?>
<h1>Evrak Takibi</h1>
<p>Tamamlanma: <b><?= $total ? round($have * 100 / $total) : 0 ?>%</b> (<?= $have ?> / <?= $total ?>)
  &nbsp; <label><input type="checkbox" id="miss" onchange="flt()"> Sadece eksiği olanlar</label></p>
<table id="tbl">
  <tr><th>Öğrenci</th><th>Sınıf</th><?php foreach ($types as $l): ?><th><?= h($l) ?></th><?php endforeach; ?></tr>
  <?php foreach ($students as $s):
    $all = true; foreach ($types as $k => $_) if (empty($done[$s['id']][$k])) $all = false; ?>
    <tr data-user="<?= (int)$s['id'] ?>" data-complete="<?= $all ? 1 : 0 ?>">
      <td><b><?= h($s['full_name']) ?></b></td><td><?= h($s['grade']) ?></td>
      <?php foreach ($types as $k => $_): ?>
        <td><input type="checkbox" data-doc="<?= h($k) ?>" <?= !empty($done[$s['id']][$k]) ? 'checked' : '' ?>></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
</table>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
function flt() {
  const on = document.getElementById('miss').checked;
  document.querySelectorAll('#tbl tr[data-user]').forEach(function (tr) { tr.style.display = (!on || tr.dataset.complete === '0') ? '' : 'none'; });
}
document.querySelectorAll('input[data-doc]').forEach(function (c) {
  c.addEventListener('change', function () {
    const tr = c.closest('tr');
    const fd = new FormData(); fd.append('csrf', CSRF); fd.append('user', tr.dataset.user);
    fd.append('doc', c.dataset.doc); fd.append('done', c.checked ? 1 : 0);
    fetch('', {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { c.checked = !c.checked; alert('Kaydedilemedi'); return; }
      tr.dataset.complete = [].every.call(tr.querySelectorAll('input[data-doc]'), function (x) { return x.checked; }) ? 1 : 0;
    });
  });
});
</script>
<?php panel_foot();