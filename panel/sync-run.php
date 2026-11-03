<?php
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/ftc.php';
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120);

$u = current_user();
if (!$u || $u['role'] !== 'mentor') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Yetki yok']);
  exit;
}
csrf_check();

try {
  switch ($_POST['step'] ?? '') {
    case 'events':
      $r = sync_events();
      audit('sync', $r['msg']);
      break;
    case 'teams':
      $r = sync_teams();
      audit('sync', 'Takımlar güncellendi: ' . $r['teams']);
      break;
    case 'team_one':
      $r = sync_one_team((int)($_POST['team'] ?? 0));
      break;
    case 'list':
      $r = ['ok' => true, 'teams' => array_map('intval', db()->query('SELECT team_no FROM teams ORDER BY team_no')->fetchAll(PDO::FETCH_COLUMN))];
      break;
    case 'history':
      $r = sync_history((int)($_POST['team'] ?? 0));
      break;
    default:
      $r = ['ok' => false, 'error' => 'Bilinmeyen adım'];
  }
} catch (Throwable $e) {
  $r = ['ok' => false, 'error' => $e->getMessage()];
}
echo json_encode($r, JSON_UNESCAPED_UNICODE);