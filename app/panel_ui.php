<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site.php';

function panel_head(string $title): void {
  $u = current_user(); ?>
<!DOCTYPE html><html lang="tr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> | Panel</title>
<style>
body{margin:0;background:#060913;color:#F8FAFC;font:14px Montserrat,Arial,sans-serif}
header{display:flex;gap:16px;align-items:center;flex-wrap:wrap;padding:12px 20px;background:#0F172A;border-bottom:1px solid rgba(255,255,255,.1)}
header a{color:#94A3B8;text-decoration:none;font-weight:600}header a:hover{color:#F59E0B}
header .sp{flex:1}
main{max-width:1000px;margin:24px auto;padding:0 20px}
h1{font-size:20px;color:#F59E0B}
table{width:100%;border-collapse:collapse;margin-bottom:24px}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top}
input[type=text],input[type=email],input[type=number],input[type=datetime-local],textarea{width:100%;box-sizing:border-box;padding:9px 11px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:6px;color:#fff;font:inherit}
textarea.code{font-family:Consolas,monospace;font-size:12px;min-height:420px}
label{display:block;margin:12px 0 4px;font-size:12px;font-weight:700;color:#CBD5E1}
.btn{display:inline-block;background:#F59E0B;color:#060913;border:0;border-radius:6px;padding:9px 16px;font-weight:800;cursor:pointer;text-decoration:none}
.btn.red{background:#E11D48;color:#fff}
.note{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);padding:10px 12px;border-radius:6px;margin:12px 0;font-size:13px}
.ok{background:rgba(16,185,129,.12);border:1px solid #10B981;color:#A7F3D0;padding:10px 12px;border-radius:6px;margin:12px 0}
.err{background:rgba(225,29,72,.12);border:1px solid #E11D48;color:#FDA4AF;padding:10px 12px;border-radius:6px;margin:12px 0}
</style></head><body>
<header>
  <a href="/panel/">Pano</a>
  <a href="/scouting/">Scouting</a>
  <a href="/scouting/events.php">Maçlar</a>
  <a href="/scouting/picklist.php">Pick List</a>
  <a href="/panel/m.php?m=tasks">Görevler</a>
  <?php if (($u['role'] ?? '') === 'mentor'): ?>
    <a href="/panel/dashboard.php">Komuta Merkezi</a>
    <a href="/panel/users.php">Kullanıcılar</a>
    <a href="/panel/audit.php">Denetim</a>
    <a href="/panel/sync.php">FTC Senkron</a>
    <a href="/panel/pages.php">Site</a>
    <a href="/panel/messages.php">Mesajlar</a>
  <?php endif; ?>
  <span class="sp"></span>
  <a href="/panel/profile.php"><?= h($u['name'] ?? '') ?></a>
  <a href="/" target="_blank">Siteyi Gör</a>
  <a href="/logout.php">Çıkış</a>
</header><main>
<?php
}
function panel_foot(): void { echo '</main></body></html>'; }