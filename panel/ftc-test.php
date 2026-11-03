<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/ftc.php';
$u = require_login(['mentor']);

function box(string $title, array $r): void {
  echo '<h3>' . h($title) . '</h3><div class="note">HTTP ' . (int)$r['status'] . ($r['error'] ? ' • ' . h($r['error']) : '') . '</div>';
  echo '<pre style="white-space:pre-wrap;max-height:260px;overflow:auto;background:#0F172A;padding:10px;border-radius:6px;font-size:12px">'
     . h(mb_substr($r['raw'] ?? ($r['body'] ?? ''), 0, 2500)) . '</pre>';
}

panel_head('FTC API Testi');
echo '<h1>FTC API Testi</h1>';
$cf = cfg();
$un = (string)($cf['ftc_user'] ?? ''); $tk = (string)($cf['ftc_token'] ?? '');
echo '<div class="note">Kullanıcı adı uzunluğu: ' . strlen($un) . ' (beklenen: 9) • Anahtar uzunluğu: ' . strlen($tk)
   . ' (beklenen: 36) • Baş/son boşluk: ' . ((trim($un) !== $un || trim($tk) !== $tk) ? '<b>VAR</b>' : 'yok') . '</div>';
box('API kökü', ftc_get(''));
echo '<div class="note">cURL: ' . (function_exists('curl_init') ? 'açık' : '<b>KAPALI (hosting destek hattından açtırın)</b>') . '</div>';

$r = ftc_get(FTC_SEASON . '/events');
box(FTC_SEASON . '/events', $r);
$tr = 0;
echo '<h3>Bulunan TR etkinlikleri</h3>';
foreach (ci_get($r['data'], 'events', []) as $e) {
  $c = (string)ci_get($e, 'code', '');
  if (ci_get($e, 'regionCode') === 'TR' || preg_match('/^TR[A-Z0-9]/', $c)) {
    $tr++;
    echo '<div class="note">' . h($c) . ' • ' . h(ci_get($e, 'name', '')) . ' • ' . h(ci_get($e, 'typeName', ''))
       . ' • ' . h(substr((string)ci_get($e, 'dateStart', ''), 0, 10))
       . ' • region=' . h(ci_get($e, 'regionCode', '')) . ' • ' . h(ci_get($e, 'country', ''))
       . ' • published=' . json_encode(ci_get($e, 'published')) . '</div>';
  }
}
echo "<p>Toplam: <b>$tr</b></p>";
box(FTC_SEASON . '/teams?teamNumber=25601', ftc_get(FTC_SEASON . '/teams', ['teamNumber' => 25601]));

$reg = http_get('https://ftc-events.firstinspires.org/' . FTC_SEASON . '/region/TR');
preg_match_all('#/' . FTC_SEASON . '/team/(\d{1,6})#', $reg['body'], $m);
echo '<h3>Bölge sayfası</h3><div class="note">HTTP ' . (int)$reg['status'] . ' • bulunan takım numarası: <b>' . count(array_unique($m[1])) . '</b> (beklenen: ~80)</div>';

echo '<h3>Özel istek</h3><form method="get"><input type="text" name="path" placeholder="2025/awards/25601 veya 2025/rankings/TRXXXX?teamNumber=25601" value="' . h($_GET['path'] ?? '') . '"><p><button class="btn">Dene</button></p></form>';
$path = trim($_GET['path'] ?? '');
if ($path !== '') {
  if (!preg_match('#^[A-Za-z0-9/_.\-]+(\?[A-Za-z0-9=&_.\-]*)?$#', $path) || strpos($path, '..') !== false) {
    echo '<div class="err">Geçersiz yol.</div>';
  } else {
    $parts = explode('?', $path, 2);
    parse_str($parts[1] ?? '', $q);
    box($path, ftc_get($parts[0], $q));
  }
}
panel_foot();