<?php
require_once __DIR__ . '/auth.php';

function perm_catalog(): array {
  return [
    'Mali Yönetim' => [
      'finance_view' => 'Mali verileri görüntüle', 'finance_edit' => 'Gelir-gider girme / düzenleme',
      'finance_delete' => 'Gelir-gider kaydı silme', 'sponsors_edit' => 'Sponsor takibi düzenleme',
      'budget_edit' => 'Bütçe planı düzenleme'],
    'Scouting' => [
      'team_details' => 'Takım detaylarını düzenleme', 'team_contact' => 'Sosyal medya ve iletişim bilgileri',
      'team_rating' => 'Puanlama ve pick list etiketi', 'matches_edit' => 'Maç skoru girme / düzeltme'],
    'Ekip İşleri' => [
      'portfolio_edit' => 'Portfolyo düzenleme', 'tasks_edit' => 'Görevler', 'notebook_edit' => 'Mühendislik defteri',
      'cad_edit' => 'CAD sürümleri', 'inventory_edit' => 'Parça envanteri', 'outreach_edit' => 'Outreach',
      'pitcheck_edit' => 'Pit paketi', 'attendance_edit' => 'Devam-devamsızlık'],
  ];
}
function perm_labels(): array { $o = []; foreach (perm_catalog() as $g) foreach ($g as $k => $l) $o[$k] = $l; return $o; }
function perm_keys(): array { return array_keys(perm_labels()); }

// Rol, aktiflik ve yetkiler her istekte veritabanından okunur (oturumdaki eski bilgiye güvenilmez)
function perm_ctx(int $id): array {
  static $c = [];
  if (!isset($c[$id])) {
    $q = db()->prepare('SELECT u.role, u.active, p.perm FROM users u LEFT JOIN user_perms p ON p.user_id=u.id WHERE u.id=?');
    $q->execute([$id]);
    $x = ['role' => '', 'active' => 0, 'perms' => []];
    foreach ($q as $r) { $x['role'] = $r['role']; $x['active'] = (int)$r['active']; if ($r['perm']) $x['perms'][] = $r['perm']; }
    $c[$id] = $x;
  }
  return $c[$id];
}
function is_mentor(?array $u = null): bool {
  $u = $u ?? current_user(); if (!$u) return false;
  $x = perm_ctx((int)$u['id']); return $x['active'] && $x['role'] === 'mentor';
}
function can(string $perm, ?array $u = null): bool {
  $u = $u ?? current_user(); if (!$u) return false;
  $x = perm_ctx((int)$u['id']);
  return $x['active'] && ($x['role'] === 'mentor' || in_array($perm, $x['perms'], true));
}
function require_perm(string $perm): array {
  $u = require_login();
  if (!can($perm, $u)) { http_response_code(403); exit('Bu sayfa için yetkiniz yok. Yetki için mentörünüze başvurun.'); }
  return $u;
}

// Modül -> [görüntüleme, düzenleme, silme]. null = yalnızca mentör.
function mod_perms(string $slug): ?array {
  $m = [
    'tasks' => [null, 'tasks_edit', 'tasks_edit'], 'notebook' => [null, 'notebook_edit', 'notebook_edit'],
    'cad' => [null, 'cad_edit', 'cad_edit'], 'inventory' => [null, 'inventory_edit', 'inventory_edit'],
    'portfolio' => [null, 'portfolio_edit', 'portfolio_edit'], 'outreach' => [null, 'outreach_edit', 'outreach_edit'],
    'pitcheck' => [null, 'pitcheck_edit', 'pitcheck_edit'],
    'budget' => ['finance_view', 'finance_edit', 'finance_delete'],
    'sponsors' => ['finance_view', 'sponsors_edit', 'finance_delete'],
    'budget_plan' => ['finance_view', 'budget_edit', 'budget_edit'],
  ];
  return $m[$slug] ?? null;
}
function mod_can(string $slug, string $act): bool {
  $p = mod_perms($slug);
  if ($p === null) return is_mentor();
  if ($act === 'view') return $p[0] === null ? (bool)current_user() : (can($p[0]) || can($p[1]) || can($p[2]));
  return can($p[$act === 'edit' ? 1 : 2]);
}
