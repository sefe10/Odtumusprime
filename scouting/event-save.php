<?php
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/matches.php';
header('Content-Type: application/json; charset=utf-8');

function out(bool $ok, array $x = []): void { echo json_encode(['ok' => $ok] + $x, JSON_UNESCAPED_UNICODE); exit; }

$u = current_user();
if (!$u) { http_response_code(401); out(false, ['error' => 'Oturum süresi doldu']); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, ['error' => 'POST gerekli']);
csrf_check();
@set_time_limit(60);

$manage = in_array($u['role'], ['mentor', 'youth_mentor'], true);
$season = (int)($_POST['season'] ?? 0);
$code = preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['event'] ?? ''));
$kind = $_POST['kind'] ?? '';

switch ($kind) {
  case 'sync':
    if (!$manage) out(false, ['error' => 'Yetki yok']);
    $r = sync_event_matches($season, $code);
    audit('events', "Resmî veri çekildi: $season $code (" . $r['count'] . ' maç)');
    out($r['ok'], ['count' => $r['count'], 'warn' => $r['warn'], 'error' => $r['warn'][0] ?? '']);

  case 'score':
    $cols = [];
    foreach (['total', 'auto', 'teleop', 'foul'] as $c) foreach (['red', 'blue'] as $s) $cols[] = $s . '_' . $c;
    $col = $_POST['col'] ?? '';
    if (!in_array($col, $cols, true)) out(false, ['error' => 'Geçersiz sütun']);
    $v = trim((string)($_POST['value'] ?? ''));
    $v = ($v === '') ? null : (int)$v;
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare("UPDATE event_matches SET $col=?, source='manual', updated_by=? WHERE id=?")->execute([$v, $u['id'], $id]);
    db()->prepare('UPDATE event_matches SET played=1 WHERE id=? AND red_total IS NOT NULL AND blue_total IS NOT NULL')->execute([$id]);
    audit('events', "Maç #$id skoru girildi/düzeltildi: $col");
    out(true);

  case 'add_match':
    if (!$manage) out(false, ['error' => 'Yetki yok']);
    $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 60);
    $red = array_values(array_filter([(int)($_POST['r1'] ?? 0), (int)($_POST['r2'] ?? 0)]));
    $blue = array_values(array_filter([(int)($_POST['b1'] ?? 0), (int)($_POST['b2'] ?? 0)]));
    if ($label === '' || !$red || !$blue || $season < 2015 || $code === '') out(false, ['error' => 'Etiket ve her iki ittifaktan en az bir takım gerekli']);
    $mx = db()->prepare('SELECT COALESCE(MAX(sort_no),0)+1 FROM event_matches WHERE season=? AND event_code=?');
    $mx->execute([$season, $code]);
    db()->prepare("INSERT INTO event_matches (season,event_code,mkey,label,sort_no,red_teams,blue_teams,played,source,updated_by)
      VALUES (?,?,?,?,?,?,?,0,'manual',?)")
      ->execute([$season, $code, 'M:' . bin2hex(random_bytes(4)), $label, (int)$mx->fetchColumn(), implode(',', $red), implode(',', $blue), $u['id']]);
    audit('events', "Maç eklendi: $code $label");
    out(true);

  case 'del_match':
    if ($u['role'] !== 'mentor') out(false, ['error' => 'Yalnızca mentör silebilir']);
    $h = db()->prepare('SELECT pin_hash FROM users WHERE id=?');
    $h->execute([$u['id']]);
    if (!password_verify((string)($_POST['pin'] ?? ''), (string)$h->fetchColumn())) out(false, ['error' => 'Şifre hatalı']);
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM event_matches WHERE id=?')->execute([$id]);
    audit('events', "Maç silindi (#$id)");
    out(true);
}
out(false, ['error' => 'Bilinmeyen işlem']);