<?php
require __DIR__ . '/app/site.php';
$back = '/iletisim';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: $back"); exit; }

$t    = (int)($_POST['t'] ?? 0);
$sig  = (string)($_POST['sig'] ?? '');
$good = hash_equals(hash_hmac('sha256', (string)$t, cfg()['setup_key']), $sig)
        && time() - $t >= 3 && time() - $t <= 7200
        && ($_POST['website'] ?? '') === '';

$name    = mb_substr(trim($_POST['name'] ?? ''), 0, 120);
$company = mb_substr(trim($_POST['company'] ?? ''), 0, 120);
$email   = mb_substr(trim($_POST['email'] ?? ''), 0, 160);
$msg     = mb_substr(trim($_POST['message'] ?? ''), 0, 4000);

if (!$good || $name === '' || $msg === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header("Location: $back?hata=1#contact"); exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$n  = db()->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip=? AND created_at > NOW() - INTERVAL 1 HOUR');
$n->execute([$ip]);
if ($n->fetchColumn() >= 5) { header("Location: $back?hata=1#contact"); exit; }

db()->prepare('INSERT INTO contact_messages (name,company,email,message,ip) VALUES (?,?,?,?,?)')
   ->execute([$name, $company, $email, $msg, $ip]);

$to = setting('contact_notify_email');
if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
  $host    = preg_replace('/[^a-z0-9.-]/i', '', preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'odtumusprime.com'));
  $subject = 'Web sitesi mesajı: ' . preg_replace('/[\r\n]+/', ' ', $name);
  $body    = "Ad: $name\nKurum: $company\nE-posta: $email\n\n$msg";
  $headers = "From: noreply@$host\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
  @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
header("Location: $back?gonderildi=1#contact");