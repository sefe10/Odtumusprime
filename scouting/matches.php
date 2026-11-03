<?php
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/matches.php';

$u = current_user();
if (!$u) { http_response_code(401); exit('Oturum süresi doldu, yeniden giriş yapın.'); }
$manage = in_array($u['role'], ['mentor', 'youth_mentor'], true);

$season = (int)($_GET['season'] ?? 0);
$code   = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['event'] ?? ''));
$no     = (int)($_GET['team'] ?? 0);
if ($season < 2015 || $code === '' || !$no) { http_response_code(400); exit('Geçersiz istek.'); }

@set_time_limit(60);
$force = $manage && !empty($_GET['force']);

$f = db()->prepare('SELECT UNIX_TIMESTAMP(fetched_at) FROM event_fetch WHERE season=? AND event_code=?');
$f->execute([$season, $code]);
$ts = $f->fetchColumn();

$need = $force || $ts === false;
if (!$need) {
  // Turnuva günlerinde 5 dakikada bir tazele
  $d = db()->prepare('SELECT event_date FROM team_history WHERE season=? AND event_code=? LIMIT 1');
  $d->execute([$season, $code]);
  $date = $d->fetchColumn();
  $recent = $date && abs(time() - strtotime($date)) < 4 * 86400;
  if ($recent && time() - (int)$ts > 300) $need = true;
}

$warn = [];
if ($need) {
  try {
    $res = sync_event_matches($season, $code);
    $warn = $res['warn'] ?? [];
  } catch (Throwable $e) {
    $warn[] = 'Hata: ' . $e->getMessage();
  }
}
foreach ($warn as $w) echo '<div class="note">' . h($w) . '</div>';
echo render_team_matches($season, $code, $no, $manage);