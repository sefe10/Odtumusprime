<?php
function cfg(): array {
  static $c;
  if ($c) return $c;
  $root  = $_SERVER['DOCUMENT_ROOT'];
  $paths = [dirname($root) . '/odtu_config.php', dirname($root, 2) . '/odtu_config.php', __DIR__ . '/odtu_config.php'];
  foreach ($paths as $p) {
    if (is_file($p)) return $c = require $p;
  }
  http_response_code(500);
  exit('odtu_config.php bulunamadı.');
}

function db(): PDO {
  static $pdo;
  if (!$pdo) {
    $c = cfg();
    $pdo = new PDO(
      "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
      $c['db_user'], $c['db_pass'],
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
  }
  return $pdo;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }