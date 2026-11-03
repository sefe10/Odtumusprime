<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/modules.php';
$u = require_login(['mentor']);

$q = fn(string $sql) => db()->query($sql)->fetchColumn();
$pct = fn($a, $b) => $b > 0 ? (int)round($a * 100 / $b) : 0;
$bar = fn(int $p) => '<div style="background:rgba(255,255,255,.1);border-radius:6px;height:8px;margin:4px 0 8px"><div style="width:' . $p . '%;height:8px;border-radius:6px;background:#F59E0B"></div></div>';

$ne = db()->query("SELECT e.name, e.code, e.event_date FROM events e
  JOIN team_events te ON te.event_code=e.code AND te.season=e.season AND te.team_no=25601
  WHERE e.event_date >= CURDATE() ORDER BY e.event_date LIMIT 1")->fetch();
$daysLeft = $ne ? (int)floor((strtotime($ne['event_date']) - strtotime(date('Y-m-d'))) / 86400) : null;

$tasksAll  = (int)$q('SELECT COUNT(*) FROM m_tasks');
$tasksDone = (int)$q("SELECT COUNT(*) FROM m_tasks WHERE status='Tamam'");
$overdue   = (int)$q("SELECT COUNT(*) FROM m_tasks WHERE status<>'Tamam' AND due_date < CURDATE()");
$units = db()->query("SELECT un.name, COUNT(t.id) total, COALESCE(SUM(t.status='Tamam'),0) done
  FROM units un LEFT JOIN m_tasks t ON t.unit_id=un.id GROUP BY un.id ORDER BY un.sort_order")->fetchAll();
$upcoming = db()->query("SELECT t.title, t.due_date, t.status, un.name AS unit FROM m_tasks t LEFT JOIN units un ON un.id=t.unit_id
  WHERE t.status<>'Tamam' AND t.due_date IS NOT NULL AND t.due_date <= CURDATE() + INTERVAL 14 DAY ORDER BY t.due_date LIMIT 12")->fetchAll();

$inc = (float)$q("SELECT COALESCE(SUM(amount),0) FROM m_budget WHERE kind='Gelir'");
$exp = (float)$q("SELECT COALESCE(SUM(amount),0) FROM m_budget WHERE kind='Gider'");
$spOk = (int)$q("SELECT COUNT(*) FROM m_sponsors WHERE status IN ('Onaylandı','Ödendi / teslim edildi')");
$spAll = (int)$q('SELECT COUNT(*) FROM m_sponsors');
$spSum = (float)$q("SELECT COALESCE(SUM(amount),0) FROM m_sponsors WHERE status IN ('Onaylandı','Ödendi / teslim edildi')");

$pfAll = (int)$q('SELECT COUNT(*) FROM m_portfolio');
$pfDone = (int)$q("SELECT COUNT(*) FROM m_portfolio WHERE status='Tamam'");
$students = (int)$q("SELECT COUNT(*) FROM users WHERE role='student' AND active=1");
$docsDone = (int)$q("SELECT COUNT(*) FROM student_docs d JOIN users u ON u.id=d.user_id WHERE d.done=1 AND u.role='student' AND u.active=1");
$docsAll = $students * count(doc_types());
$attP = (int)$q("SELECT COALESCE(SUM(status='P'),0) FROM attendance WHERE att_date >= CURDATE() - INTERVAL 28 DAY");
$attA = (int)$q("SELECT COALESCE(SUM(status='A'),0) FROM attendance WHERE att_date >= CURDATE() - INTERVAL 28 DAY");
$pitAll = (int)$q('SELECT COUNT(*) FROM m_pitcheck');
$pitOk = (int)$q('SELECT COUNT(*) FROM m_pitcheck WHERE packed=1');
$low = (int)$q('SELECT COUNT(*) FROM m_inventory WHERE min_qty>0 AND qty<=min_qty');
$orN = (int)$q('SELECT COUNT(*) FROM m_outreach');
$orR = (int)$q('SELECT COALESCE(SUM(reached),0) FROM m_outreach');
$unread = (int)$q('SELECT COUNT(*) FROM contact_messages WHERE is_read=0');
$nopin = (int)$q("SELECT COUNT(*) FROM users WHERE active=1 AND (pin_hash IS NULL OR pin_hash='')");

$alerts = [];
if ($overdue) $alerts[] = "$overdue görevin son tarihi geçti";
if ($nopin) $alerts[] = "$nopin aktif kullanıcının PIN'i yok";
if ($docsAll && $docsDone < $docsAll && $daysLeft !== null && $daysLeft <= 45) $alerts[] = ($docsAll - $docsDone) . ' evrak eksik, turnuvaya ' . $daysLeft . ' gün var';
if ($pitAll && $pitOk < $pitAll && $daysLeft !== null && $daysLeft <= 14) $alerts[] = ($pitAll - $pitOk) . ' pit malzemesi paketlenmedi';
if ($low) $alerts[] = "$low parça asgari stoğun altında";
if ($unread) $alerts[] = "$unread okunmamış iletişim mesajı";
if ($exp > $inc && ($inc + $exp) > 0) $alerts[] = 'Bütçe açığı: giderler gelirleri aşıyor';

$fm = fn($n) => number_format($n, 0, ',', '.');
panel_head('Komuta Merkezi'); ?>
<style>
main{max-width:1200px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin:14px 0}
.c{background:#0F172A;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:16px}
.c h3{margin:0 0 8px;font-size:13px;color:#F59E0B;text-transform:uppercase;letter-spacing:.5px}
.big{font-size:26px;font-weight:900}
.al{background:rgba(225,29,72,.12);border:1px solid #E11D48;color:#FDA4AF;padding:8px 12px;border-radius:6px;margin:4px 0}
</style>
<h1>Lead Mentör Komuta Merkezi</h1>
<?php foreach ($alerts as $a): ?><div class="al">⚠ <?= h($a) ?></div><?php endforeach; ?>
<?php if (!$alerts): ?><div class="ok">Acil uyarı yok.</div><?php endif; ?>
<div class="g">
  <div class="c"><h3>Sıradaki turnuva</h3>
    <?php if ($ne): ?><div class="big"><?= $daysLeft ?> gün</div><?= h($ne['name'] ?: $ne['code']) ?><br><span style="color:#94A3B8"><?= h(date('d.m.Y', strtotime($ne['event_date']))) ?></span>
    <?php else: ?><span style="color:#94A3B8">Takımınızın kayıtlı olduğu gelecek bir turnuva yok. FTC Senkron'u çalıştırın.</span><?php endif; ?></div>
  <div class="c"><h3>Görevler</h3><div class="big"><?= $pct($tasksDone, $tasksAll) ?>%</div><?= $bar($pct($tasksDone, $tasksAll)) ?>
    <?= $tasksDone ?> / <?= $tasksAll ?> tamam • <b style="color:<?= $overdue ? '#FDA4AF' : '#6EE7B7' ?>"><?= $overdue ?> geciken</b></div>
  <div class="c"><h3>Bütçe</h3>Gelir: <b style="color:#6EE7B7"><?= $fm($inc) ?> TL</b><br>Gider: <b style="color:#FDA4AF"><?= $fm($exp) ?> TL</b><br>
    <div class="big"><?= $fm($inc - $exp) ?> TL</div>kalan</div>
  <div class="c"><h3>Sponsorluk</h3><div class="big"><?= $spOk ?> / <?= $spAll ?></div>onaylanan görüşme<br>Onaylanan tutar: <b><?= $fm($spSum) ?> TL</b></div>
  <div class="c"><h3>Portfolyo</h3><div class="big"><?= $pfDone ?> / <?= $pfAll ?> sayfa</div><?= $bar($pct($pfDone, $pfAll)) ?></div>
  <div class="c"><h3>Evrak</h3><div class="big"><?= $pct($docsDone, $docsAll) ?>%</div><?= $bar($pct($docsDone, $docsAll)) ?><?= $docsDone ?> / <?= $docsAll ?> belge • <a style="color:#F59E0B" href="/panel/docs.php">aç</a></div>
  <div class="c"><h3>Devam (son 28 gün)</h3><div class="big"><?= ($attP + $attA) ? $pct($attP, $attP + $attA) . '%' : '—' ?></div><?= $bar($pct($attP, $attP + $attA)) ?><a style="color:#F59E0B" href="/panel/attendance.php">aç</a></div>
  <div class="c"><h3>Pit paketi</h3><div class="big"><?= $pitOk ?> / <?= $pitAll ?></div><?= $bar($pct($pitOk, $pitAll)) ?><a style="color:#F59E0B" href="/panel/m.php?m=pitcheck">aç</a></div>
  <div class="c"><h3>Outreach</h3><div class="big"><?= $orN ?> etkinlik</div><?= $fm($orR) ?> kişiye ulaşıldı</div>
</div>
<div class="g">
  <div class="c"><h3>Birim bazlı görev durumu</h3>
    <?php foreach ($units as $x): $p = $pct($x['done'], $x['total']); ?>
      <div><?= h($x['name']) ?> <span style="float:right;color:#94A3B8"><?= (int)$x['done'] ?>/<?= (int)$x['total'] ?></span></div><?= $bar($p) ?>
    <?php endforeach; ?></div>
  <div class="c"><h3>Yaklaşan ve geciken görevler</h3>
    <?php foreach ($upcoming as $t): $late = $t['due_date'] < date('Y-m-d'); ?>
      <div style="margin:4px 0"><b style="color:<?= $late ? '#FDA4AF' : '#FCD34D' ?>"><?= h(date('d.m', strtotime($t['due_date']))) ?></b> <?= h($t['title']) ?> <span style="color:#94A3B8">(<?= h($t['unit']) ?>)</span></div>
    <?php endforeach; ?>
    <?php if (!$upcoming): ?><span style="color:#94A3B8">14 gün içinde bekleyen görev yok.</span><?php endif; ?></div>
</div>
<h2 style="font-size:16px;color:#F59E0B">Son değişiklikler</h2>
<table>
  <?php foreach (db()->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 12') as $r): ?>
    <tr><td><?= h($r['created_at']) ?></td><td><?= h($r['user_name']) ?></td><td><?= h($r['action']) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php panel_foot();