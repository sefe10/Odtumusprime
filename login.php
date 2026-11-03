<?php
require __DIR__ . '/app/auth.php';

$roles = ['mentor' => 'Mentor', 'youth_mentor' => 'Youth Mentor', 'student' => 'Öğrenci'];
$next = $_GET['next'] ?? '/panel/';
if (!preg_match('#^/(?!/)#', $next)) $next = '/panel/';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  db()->exec("DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY");
  $n = db()->prepare('SELECT COALESCE(SUM(ip=?),0) AS a, COALESCE(SUM(username=?),0) AS b
                      FROM login_attempts WHERE created_at > NOW() - INTERVAL 15 MINUTE');
  $n->execute([$ip, trim($_POST['username'] ?? '')]);
  $cnt = $n->fetch();
  if ($cnt['a'] >= 8 || $cnt['b'] >= 20) {
    $err = 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.';
  } else {
    $role = $_POST['role'] ?? '';
    $un   = trim($_POST['username'] ?? '');
    $pin  = $_POST['pin'] ?? '';
    $st = db()->prepare('SELECT * FROM users WHERE username=? AND role=? AND active=1');
    $st->execute([$un, $role]);
    $u = $st->fetch();
    if (isset($roles[$role]) && $u && $u['pin_hash'] && password_verify($pin, $u['pin_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['full_name'], 'role' => $u['role']];
      audit('login', 'Giriş yapıldı');
      header('Location: ' . $next);
      exit;
    }
    db()->prepare('INSERT INTO login_attempts (ip,username) VALUES (?,?)')->execute([$ip, $un]);
    $err = 'Kullanıcı adı, rol veya PIN hatalı.';
  }
}
?><!DOCTYPE html>
<html lang="tr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Giriş | ODTÜmus Prime #25601</title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#060913;color:#F8FAFC;font-family:Montserrat,Arial,sans-serif}
  .card{width:100%;max-width:380px;background:rgba(15,23,42,.85);border:1.5px solid rgba(255,255,255,.12);border-radius:14px;padding:28px}
  h1{font-size:18px;margin:0 0 4px;color:#F59E0B}
  p{font-size:12px;color:#94A3B8;margin:0 0 18px}
  .roles{display:flex;gap:6px;margin-bottom:14px}
  .roles label{flex:1;text-align:center;padding:9px 4px;border:1.5px solid rgba(255,255,255,.12);border-radius:6px;font-size:12px;font-weight:700;cursor:pointer}
  .roles input{display:none}
  .roles label:has(input:checked){border-color:#E11D48;background:rgba(225,29,72,.12);color:#E11D48}
  input.f{width:100%;box-sizing:border-box;margin-bottom:12px;padding:11px 12px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:6px;color:#fff;font-size:14px}
  button{width:100%;padding:12px;border:0;border-radius:6px;background:#E11D48;color:#fff;font-weight:800;font-size:14px;cursor:pointer}
  .err{color:#FDA4AF;font-size:12px;margin-bottom:12px}
</style></head><body>
<form class="card" method="post">
  <h1>ODTÜmus Prime #25601</h1>
  <p>Takım paneline giriş</p>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="roles">
    <?php foreach ($roles as $k => $v): ?>
      <label><input type="radio" name="role" value="<?= $k ?>" <?= ($k === 'student') ? 'checked' : '' ?>><span><?= h($v) ?></span></label>
    <?php endforeach; ?>
  </div>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <input class="f" name="username" placeholder="Kullanıcı adı" autocomplete="username" required>
  <input class="f" type="password" name="pin" placeholder="PIN / Şifre" autocomplete="current-password" required>
  <button>Giriş Yap</button>
</form></body></html>