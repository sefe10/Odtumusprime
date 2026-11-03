<?php
require __DIR__ . '/../app/panel_ui.php';
$u = require_login(['mentor']);
$err = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $cd    = trim($_POST['countdown_target'] ?? '');
  $email = trim($_POST['contact_notify_email'] ?? '');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $cd)) {
    $err = 'Geri sayım tarihi geçersiz.';
  } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'E-posta adresi geçersiz.';
  } else {
    $up = db()->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');
    $up->execute(['countdown_target', $cd . ':00+03:00']);
    $up->execute(['contact_notify_email', $email]);
    audit('settings', 'Ayarlar güncellendi');
    header('Location: /panel/settings.php?ok=1'); exit;
  }
}

panel_head('Ayarlar'); ?>
<h1>Ayarlar</h1>
<?php if (isset($_GET['ok'])): ?><div class="ok">Kaydedildi.</div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label>Ana sayfadaki geri sayımın hedef tarihi (Türkiye saati)</label>
  <input type="datetime-local" name="countdown_target" value="<?= h(substr(setting('countdown_target', '2026-12-19T09:00'), 0, 16)) ?>" required>
  <label>İletişim formu mesajlarının e-posta ile bildirileceği adres (boş bırakırsanız sadece panelde görünür)</label>
  <input type="email" name="contact_notify_email" value="<?= h(setting('contact_notify_email')) ?>">
  <p><button class="btn">Kaydet</button></p>
</form>
<?php panel_foot();