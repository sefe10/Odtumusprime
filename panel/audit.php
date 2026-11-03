<?php
require __DIR__ . '/../app/panel_ui.php';
$me = require_login(['mentor']);

$where = []; $p = [];
$user = trim($_GET['user'] ?? '');
$modul = trim($_GET['modul'] ?? '');
$team = (int)($_GET['team'] ?? 0);
if ($user !== '')  { $where[] = 'user_name LIKE ?'; $p[] = '%' . $user . '%'; }
if ($modul !== '') { $where[] = 'page = ?'; $p[] = $modul; }
if ($team)         { $where[] = 'team_no = ?'; $p[] = $team; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cnt = db()->prepare('SELECT COUNT(*) FROM audit_log' . $w);
$cnt->execute($p);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / 100));
$pg = max(1, min($pages, (int)($_GET['sayfa'] ?? 1)));

$st = db()->prepare('SELECT * FROM audit_log' . $w . ' ORDER BY id DESC LIMIT 100 OFFSET ' . (($pg - 1) * 100));
$st->execute($p);
$rows = $st->fetchAll();
$mods = db()->query('SELECT DISTINCT page FROM audit_log ORDER BY page')->fetchAll(PDO::FETCH_COLUMN);
$qs = fn(int $n) => '?' . http_build_query(['user' => $user, 'modul' => $modul, 'team' => $team ?: '', 'sayfa' => $n]);

panel_head('Denetim Kaydı'); ?>
<style>.bar{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}.bar input,.bar select{padding:8px 10px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:6px;color:#fff;font:inherit}option{color:#000}</style>
<h1>Denetim Kaydı (<?= $total ?>)</h1>
<form class="bar" method="get">
  <input name="user" placeholder="Kullanıcı adı…" value="<?= h($user) ?>">
  <select name="modul"><option value="">Tüm modüller</option>
    <?php foreach ($mods as $m): ?><option value="<?= h($m) ?>" <?= $m === $modul ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?></select>
  <input name="team" type="number" placeholder="Takım no" value="<?= $team ?: '' ?>" style="width:120px">
  <button class="btn">Filtrele</button>
</form>
<table>
  <tr><th>Zaman</th><th>Kullanıcı</th><th>Modül</th><th>Takım</th><th>İşlem</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= h($r['created_at']) ?></td><td><?= h($r['user_name']) ?></td><td><?= h($r['page']) ?></td>
      <td><?php if ($r['team_no']): ?><a style="color:#F59E0B" href="/scouting/team.php?no=<?= (int)$r['team_no'] ?>">#<?= (int)$r['team_no'] ?></a><?php endif; ?></td>
      <td><?= h($r['action']) ?></td></tr>
  <?php endforeach; ?>
</table>
<p>
  <?php if ($pg > 1): ?><a class="btn" href="<?= h($qs($pg - 1)) ?>">← Yeni</a><?php endif; ?>
  Sayfa <?= $pg ?> / <?= $pages ?>
  <?php if ($pg < $pages): ?><a class="btn" href="<?= h($qs($pg + 1)) ?>">Eski →</a><?php endif; ?>
</p>
<?php panel_foot();