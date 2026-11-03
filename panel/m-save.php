<?php

require __DIR__ . '/../app/auth.php';

require __DIR__ . '/../app/modules.php';

require __DIR__ . '/../app/perm.php';

header('Content-Type: application/json; charset=utf-8');



function out(bool $ok, array $x = []): void { echo json_encode(['ok' => $ok] + $x, JSON_UNESCAPED_UNICODE); exit; }



$u = current_user();

if (!$u) { http_response_code(401); out(false, ['error' => 'Oturum süresi doldu']); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, ['error' => 'POST gerekli']);

csrf_check();



$slug = $_POST['m'] ?? '';

$M = modules()[$slug] ?? null;

if (!$M) out(false, ['error' => 'Modül yok']);

if (!mod_can($slug, 'edit')) out(false, ['error' => 'Bu işlem için yetkiniz yok']);

$t = $M['table'];

$kind = $_POST['kind'] ?? '';



if ($kind === 'add') {

  $c = ['updated_by']; $v = [$u['id']];

  foreach ($M['defaults'] ?? [] as $k => $d) {

    if (!isset($M['cols'][$k])) continue;

    if ($d === '@today') $d = date('Y-m-d');

    elseif ($d === '@next') $d = (int)db()->query("SELECT COALESCE(MAX($k),0)+1 FROM $t")->fetchColumn();

    $c[] = $k; $v[] = $d;

  }

  db()->prepare("INSERT INTO $t (" . implode(',', $c) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')')->execute($v);

  audit('m:' . $slug, 'Satır eklendi');

  out(true);

}



$id = (int)($_POST['id'] ?? 0);

if ($kind === 'del') {

  if (!mod_can($slug, 'del')) out(false, ['error' => 'Silme yetkiniz yok']);

  db()->prepare("DELETE FROM $t WHERE id=?")->execute([$id]);

  audit('m:' . $slug, "Satır silindi (#$id)");

  out(true);

}



if ($kind === 'set') {

  $col = $_POST['col'] ?? '';

  $def = $M['cols'][$col] ?? null;

  if (!$def) out(false, ['error' => 'Geçersiz sütun']);

  $raw = trim((string)($_POST['value'] ?? ''));

  switch ($def[1]) {

    case 'text':  $val = $raw === '' ? null : mb_substr($raw, 0, 255); break;

    case 'url':   $val = $raw === '' ? null : mb_substr($raw, 0, 500); break;

    case 'area':  $val = $raw === '' ? null : mb_substr($raw, 0, 5000); break;

    case 'num':   $val = (int)$raw; break;

    case 'money': $val = round((float)str_replace(',', '.', $raw), 2); break;

    case 'check': $val = $raw === '1' ? 1 : 0; break;

    case 'date':  $val = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : null; break;

    case 'unit': case 'user': case 'sponsor': $val = (int)$raw ?: null; break;

    case 'select':
      if ($raw === '') { $val = null; break; }
      if (!in_array($raw, $def[2], true)) out(false, ['error' => 'Geçersiz seçim']);
      $val = $raw; break;

    default: out(false, ['error' => 'Geçersiz tür']);

  }

  db()->prepare("UPDATE $t SET $col=?, updated_by=? WHERE id=?")->execute([$val, $u['id'], $id]);

  audit('m:' . $slug, "Satır #$id güncellendi: " . $def[0]);

  out(true);

}

out(false, ['error' => 'Bilinmeyen işlem']);
