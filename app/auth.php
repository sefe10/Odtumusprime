<?php
require_once __DIR__ . '/db.php';
session_set_cookie_params([
  'lifetime' => 0, 'path' => '/', 'secure' => true,
  'httponly' => true, 'samesite' => 'Lax'
]);
session_start();

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(array $roles = []): array {
  $u = current_user();
  if (!$u) {
    header('Location: /login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
  }
  if ($roles && !in_array($u['role'], $roles, true)) {
    http_response_code(403);
    exit('Bu sayfa için yetkiniz yok.');
  }
  return $u;
}

function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit('Geçersiz istek.');
  }
}

function audit(string $page, string $action, ?int $team = null): void {
  $u = current_user();
  db()->prepare('INSERT INTO audit_log (user_id,user_name,page,team_no,action) VALUES (?,?,?,?,?)')
     ->execute([$u['id'] ?? null, $u['name'] ?? '-', $page, $team, $action]);
}