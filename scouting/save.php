<?php
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/scouting.php';
header('Content-Type: application/json; charset=utf-8');

function out(bool $ok, array $extra = []): void {
  echo json_encode(['ok' => $ok] + $extra, JSON_UNESCAPED_UNICODE);
  exit;
}

$u = current_user();
if (!$u) { http_response_code(401); out(false, ['error' => 'Oturum süresi doldu, yeniden giriş yapın']); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, ['error' => 'POST gerekli']);
csrf_check();

$no = (int)($_POST['team'] ?? 0);
$chk = db()->prepare('SELECT 1 FROM teams WHERE team_no=?');
$chk->execute([$no]);
if (!$chk->fetchColumn()) out(false, ['error' => 'Takım bulunamadı']);

$manage = in_array($u['role'], ['mentor', 'youth_mentor'], true);
$kind = $_POST['kind'] ?? '';

$put = function (string $key, string $value) use ($no, $u) {
  db()->prepare("INSERT INTO team_fields (team_no,field_key,value,source,updated_by) VALUES (?,?,?,'manual',?)
    ON DUPLICATE KEY UPDATE value=VALUES(value), source='manual', updated_by=VALUES(updated_by)")
    ->execute([$no, $key, $value, $u['id']]);
};
$getJson = function (string $key) use ($no): array {
  $q = db()->prepare('SELECT value FROM team_fields WHERE team_no=? AND field_key=?');
  $q->execute([$no, $key]);
  return json_decode((string)$q->fetchColumn(), true) ?: [];
};

switch ($kind) {
  case 'field':
    $key = $_POST['key'] ?? '';
    if (!in_array($key, sc_field_keys(), true)) out(false, ['error' => 'Geçersiz alan']);
    $put($key, mb_substr((string)($_POST['value'] ?? ''), 0, 5000));
    audit('scouting', "Alan güncellendi: $key", $no);
    out(true);

  case 'rating':
    $key = $_POST['key'] ?? '';
    if (!isset(sc_ratings()[$key])) out(false, ['error' => 'Geçersiz puan alanı']);
    $v = max(0, min(5, (int)($_POST['value'] ?? 0)));
    $d = $getJson('ratings');
    if ($v === 0) unset($d[$key]); else $d[$key] = $v;
    $put('ratings', json_encode($d));
    audit('scouting', "Puan: $key = $v", $no);
    out(true);

  case 'check':
    $key = $_POST['key'] ?? '';
    if (!isset(sc_checks()[$key])) out(false, ['error' => 'Geçersiz kontrol']);
    $v = !empty($_POST['value']) ? 1 : 0;
    $d = $getJson('checks');
    if ($v) $d[$key] = 1; else unset($d[$key]);
    $put('checks', json_encode($d));
    audit('scouting', "Kontrol: $key = $v", $no);
    out(true);

  case 'history':
    $cols = ['event_name', 'rank_no', 'record_wlt', 'qual_avg', 'awards', 'notes'];
    $col = $_POST['col'] ?? '';
    if (!in_array($col, $cols, true)) out(false, ['error' => 'Geçersiz sütun']);
    $v = mb_substr(trim((string)($_POST['value'] ?? '')), 0, 3000);
    if ($col === 'rank_no') $v = ($v === '') ? null : (int)$v;
    elseif ($v === '') $v = null;
    $q = db()->prepare("UPDATE team_history SET $col=?, source='manual', updated_by=? WHERE id=? AND team_no=?");
    $q->execute([$v, $u['id'], (int)($_POST['id'] ?? 0), $no]);
    audit('scouting', "Geçmiş satırı güncellendi: $col", $no);
    out(true);

  case 'history_add':
    if (!$manage) out(false, ['error' => 'Yetki yok']);
    $season = (int)($_POST['season'] ?? 0);
    $nm = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 200);
    if ($season < 2015 || $season > 2100 || $nm === '') out(false, ['error' => 'Sezon ve turnuva adı gerekli']);
    db()->prepare("INSERT INTO team_history (team_no,season,event_code,event_name,source,updated_by) VALUES (?,?,?,?,'manual',?)")
      ->execute([$no, $season, 'manual-' . bin2hex(random_bytes(4)), $nm, $u['id']]);
    audit('scouting', "Geçmiş satırı eklendi: $season $nm", $no);
    out(true);

  case 'history_delete':
    if (!$manage) out(false, ['error' => 'Yetki yok']);
    db()->prepare('DELETE FROM team_history WHERE id=? AND team_no=?')->execute([(int)($_POST['id'] ?? 0), $no]);
    audit('scouting', 'Geçmiş satırı silindi', $no);
    out(true);

  case 'match':
    $cols = ['red_auto','red_teleop','red_foul','red_total','blue_auto','blue_teleop','blue_foul','blue_total'];
    $col = $_POST['col'] ?? '';
    if (!in_array($col, $cols, true)) out(false, ['error' => 'Geçersiz sütun']);
    $v = trim((string)($_POST['value'] ?? ''));
    $v = ($v === '') ? null : (int)$v;
    $mid = (int)($_POST['id'] ?? 0);
    db()->prepare("UPDATE event_matches SET $col=?, source='manual', updated_by=? WHERE id=?")->execute([$v, $u['id'], $mid]);
    audit('scouting', "Maç skoru elle düzeltildi (#$mid $col)", $no);
    out(true);
    
  case 'rep':
    if (!$manage) out(false, ['error' => 'Yetki yok']);
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid) {
      $q = db()->prepare("SELECT full_name FROM users WHERE id=? AND active=1 AND role IN ('student','youth_mentor')");
      $q->execute([$uid]);
      $nm = $q->fetchColumn();
      if (!$nm) out(false, ['error' => 'Kullanıcı bulunamadı']);
    }
    db()->prepare('UPDATE teams SET rep_user_id=? WHERE team_no=?')->execute([$uid ?: null, $no]);
    audit('scouting', 'Temsilci: ' . ($uid ? $nm : 'kaldırıldı'), $no);
    out(true);

  case 'reset':
    if (!$manage) out(false, ['error' => 'Yetki yok']);
    $key = $_POST['key'] ?? '';
    if (!in_array($key, sc_auto_keys(), true)) out(false, ['error' => 'Bu alan otomatiğe döndürülemez']);
    db()->prepare('DELETE FROM team_fields WHERE team_no=? AND field_key=?')->execute([$no, $key]);
    audit('scouting', "Otomatiğe döndürüldü: $key (bir sonraki senkronda dolar)", $no);
    out(true);
}
out(false, ['error' => 'Bilinmeyen işlem']);