<?php

require_once __DIR__ . '/db.php';



const ROLE_LABELS = ['mentor' => 'Mentor', 'youth_mentor' => 'Young Mentor', 'student' => 'Öğrenci'];



function tr_ascii(string $s): string {

  $s = strtr($s, ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o',

                  'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u', 'â' => 'a', 'Â' => 'a', 'î' => 'i', 'Î' => 'i', 'û' => 'u', 'Û' => 'u']);

  return strtolower($s);

}



// "Derin Fırtına Aksoy" -> derin.aksoy ; tek isim -> doruk ; çakışırsa sayı eklenir

function make_username(string $fullName, ?int $exceptId = null): string {

  $parts = preg_split('/\s+/', trim(tr_ascii($fullName)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

  $parts = array_values(array_filter(array_map(fn($p) => preg_replace('/[^a-z0-9]/', '', $p), $parts)));

  if (!$parts) $parts = ['kullanici'];

  $base = substr(count($parts) > 1 ? $parts[0] . '.' . end($parts) : $parts[0], 0, 36);

  $u = $base; $i = 2;

  $q = db()->prepare('SELECT 1 FROM users WHERE username=? AND id<>?');

  while (true) {

    $q->execute([$u, $exceptId ?? 0]);

    if (!$q->fetchColumn()) return $u;

    $u = $base . $i++;

  }

}



function gen_pin(int $len = 6): string {

  $s = '';

  for ($i = 0; $i < $len; $i++) $s .= random_int(0, 9);

  return $s;

}



// Hata mesajı döner, geçerliyse null

function secret_ok(string $role, string $s): ?string {

  if ($role === 'mentor') return strlen($s) >= 10 ? null : 'Mentor şifresi en az 10 karakter olmalı.';

  return preg_match('/^\d{4,8}$/', $s) ? null : 'PIN 4 ile 8 haneli rakamlardan oluşmalı.';

}



function set_secret(int $id, string $s): void {

  db()->prepare('UPDATE users SET pin_hash=? WHERE id=?')->execute([password_hash($s, PASSWORD_DEFAULT), $id]);

}



function all_units(): array {

  return db()->query('SELECT id, code, name FROM units ORDER BY sort_order, id')->fetchAll();

}



function user_units(int $uid): array {

  $q = db()->prepare('SELECT unit_id, is_lead FROM unit_members WHERE user_id=?');

  $q->execute([$uid]);

  $o = [];

  foreach ($q as $r) $o[(int)$r['unit_id']] = (int)$r['is_lead'];

  return $o;

}



function save_units(int $uid, array $unitIds, array $leadIds): void {

  $valid = db()->query('SELECT id FROM units')->fetchAll(PDO::FETCH_COLUMN);

  $leadIds = array_map('intval', $leadIds);

  db()->prepare('DELETE FROM unit_members WHERE user_id=?')->execute([$uid]);

  $ins = db()->prepare('INSERT INTO unit_members (unit_id,user_id,is_lead) VALUES (?,?,?)');

  foreach (array_unique(array_map('intval', $unitIds)) as $u) {

    if (in_array($u, array_map('intval', $valid), true)) $ins->execute([$u, $uid, in_array($u, $leadIds, true) ? 1 : 0]);

  }

}



function active_mentor_count(): int {

  return (int)db()->query("SELECT COUNT(*) FROM users WHERE role='mentor' AND active=1 AND pin_hash IS NOT NULL")->fetchColumn();

}



function creds_box(array $creds): void { ?>

  <div class="ok" style="background:rgba(245,158,11,.1);border-color:#F59E0B;color:#FDE68A">

    <b>Giriş bilgileri — bu liste bir daha gösterilmez.</b> PIN'ler şifrelenmiş saklanır, sonradan görülemez.

    Yazdırıp dağıtın veya bir yere kaydedin. <button class="btn" onclick="window.print()" type="button">Yazdır</button>

    <table style="margin-top:10px">

      <tr><th>Ad Soyad</th><th>Rol</th><th>Kullanıcı adı</th><th>PIN</th></tr>

      <?php foreach ($creds as $c): ?>

        <tr><td><?= h($c[0]) ?></td><td><?= h($c[1]) ?></td><td><b><?= h($c[2]) ?></b></td><td><b style="font-size:16px;letter-spacing:2px"><?= h($c[3]) ?></b></td></tr>

      <?php endforeach; ?>

    </table>

  </div>

<?php }

function users_tabs(string $active): void {
  $s = fn($k) => $active === $k ? '' : 'opacity:.6';
  echo '<p><a class="btn" style="' . $s('perms') . '" href="/panel/permissions.php">🔑 Kullanıcılar ve Yetkiler</a> '
     . '<a class="btn" style="' . $s('manage') . '" href="/panel/users.php">➕ Hesap yönetimi (ekle, PIN, birim)</a></p>';
}
