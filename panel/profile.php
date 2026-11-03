<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/users.php';
$me = require_login();
$err = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $q = db()->prepare('SELECT pin_hash FROM users WHERE id=?');
  $q->execute([$me['id']]);
  $hash = (string)$q->fetchColumn();
  $cur = (string)($_POST['current'] ?? '');
  $new = (string)($_POST['new'] ?? '');
  if (!$hash || !password_verify($cur, $hash)) $err = 'Mevcut PIN/şifre hatalı.';
  elseif ($new !== ($_POST['again'] ?? '')) $err = 'Yeni değerler aynı değil.';
  elseif ($e = secret_ok($me['role'], $new)) $err = $e;
  else {
    set_secret((int)$me['id'], $new);
    audit('profile', 'Kendi PIN/şifresini değiştirdi');
    $ok = true;
  }
}
panel_head('Profilim'); ?>
<style>.f input{padding:9px 11px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:6px;color:#fff;font:inherit;width:260px}.f label{margin:12px 0 4px}</style>
<h1><?= h($me['name']) ?></h1>
<p style="color:#94A3B8">Rol: <?= h(ROLE_LABELS[$me['role']] ?? $me['role']) ?></p>
<?php if ($ok): ?><div class="ok">PIN/şifre değiştirildi.</div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
<form method="post" class="f" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label>Mevcut PIN / şifre</label><input type="password" name="current" required autocomplete="current-password">
  <label>Yeni <?= $me['role'] === 'mentor' ? 'şifre (en az 10 karakter)' : 'PIN (4-8 rakam)' ?></label><input type="password" name="new" required autocomplete="new-password">
  <label>Yeni değer (tekrar)</label><input type="password" name="again" required autocomplete="new-password">
  <p><button class="btn">Değiştir</button></p>
</form>
<?php panel_foot();