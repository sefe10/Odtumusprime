<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/users.php';
$me = require_login(['mentor']);
header('Cache-Control: no-store');

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM users WHERE id=?');
$st->execute([$id]);
$usr = $st->fetch();
if (!$usr) { http_response_code(404); exit('Kullanıcı bulunamadı.'); }

$self = ($id === (int)$me['id']);
$units = all_units();
$err = ''; $creds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $act = $_POST['act'] ?? '';
  $lastMentor = ($usr['role'] === 'mentor' && $usr['active'] && active_mentor_count() <= 1);
  try {
    if ($act === 'save') {
      $name  = trim($_POST['name'] ?? '');
      $role  = $_POST['role'] ?? '';
      $un    = trim($_POST['username'] ?? '');
      $grade = trim($_POST['grade'] ?? '');
      $active = isset($_POST['active']) ? 1 : 0;
      if ($name === '' || !isset(ROLE_LABELS[$role])) throw new RuntimeException('Ad soyad ve rol gerekli.');
      if (!preg_match('/^[a-z0-9._-]{3,40}$/', $un)) throw new RuntimeException('Kullanıcı adı 3-40 karakter; küçük harf, rakam, nokta, tire.');
      if ($self && ($role !== 'mentor' || !$active)) throw new RuntimeException('Kendi rolünüzü veya durumunuzu değiştiremezsiniz.');
      if ($lastMentor && ($role !== 'mentor' || !$active)) throw new RuntimeException('Son aktif mentörü pasifleştiremez veya rolünü değiştiremezsiniz.');
      $clear = ($role === 'mentor' && $usr['role'] !== 'mentor');   // yeni mentor güçlü şifre belirlesin
      db()->prepare('UPDATE users SET full_name=?, role=?, username=?, grade=?, active=?, pin_hash=IF(?, NULL, pin_hash) WHERE id=?')
        ->execute([$name, $role, $un, $grade ?: null, $active, $clear ? 1 : 0, $id]);
      save_units($id, $_POST['units'] ?? [], $_POST['leads'] ?? []);
      audit('users', "Kullanıcı güncellendi: $name (" . ROLE_LABELS[$role] . ')' . ($clear ? ' — yeni şifre gerekli' : ''));
      header('Location: /panel/user-edit.php?id=' . $id . '&ok=1'); exit;
    } elseif ($act === 'set_secret') {
      $s = (string)($_POST['secret'] ?? '');
      if ($e = secret_ok($usr['role'], $s)) throw new RuntimeException($e);
      set_secret($id, $s);
      audit('users', 'Şifre/PIN değiştirildi: ' . $usr['full_name']);
      header('Location: /panel/user-edit.php?id=' . $id . '&ok=1'); exit;
    } elseif ($act === 'gen_pin') {
      if ($usr['role'] === 'mentor') throw new RuntimeException('Mentor için şifreyi elle belirleyin.');
      $p = gen_pin();
      set_secret($id, $p);
      $creds[] = [$usr['full_name'], ROLE_LABELS[$usr['role']], $usr['username'], $p];
      audit('users', 'Yeni PIN üretildi: ' . $usr['full_name']);
    } elseif ($act === 'delete') {
      if ($self) throw new RuntimeException('Kendinizi silemezsiniz.');
      if ($lastMentor) throw new RuntimeException('Son aktif mentörü silemezsiniz.');
      db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
      audit('users', 'Kullanıcı silindi: ' . $usr['full_name']);
      header('Location: /panel/users.php?deleted=1'); exit;
    }
  } catch (PDOException $e) {
    $err = (($e->errorInfo[1] ?? 0) == 1062) ? 'Bu kullanıcı adı zaten kullanılıyor.' : 'Veritabanı hatası: ' . $e->getMessage();
  } catch (RuntimeException $e) {
    $err = $e->getMessage();
  }
  $st->execute([$id]); $usr = $st->fetch();
}

$mine = user_units($id);
panel_head('Kullanıcı: ' . $usr['full_name']); ?>
<style>
main{max-width:800px}
.f select,.f input[type=text],.f input[type=password]{padding:8px 10px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:6px;color:#fff;font:inherit}
option{color:#000}.f label{margin:12px 0 4px}
.ug{display:grid;grid-template-columns:1fr 80px;gap:4px 12px}
@media print{header,.btn,form{display:none!important}}
</style>
<p><a href="/panel/users.php" style="color:#F59E0B">← Kullanıcılar</a></p>
<h1><?= h($usr['full_name']) ?></h1>
<?php if (isset($_GET['ok'])): ?><div class="ok">Kaydedildi.</div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
<?php if ($creds) creds_box($creds); ?>
<?php if ($usr['role'] === 'mentor' && !$usr['pin_hash']): ?><div class="note">Bu mentör için henüz şifre yok. Aşağıdan belirleyin.</div><?php endif; ?>

<form method="post" class="f">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="act" value="save">
  <label>Ad Soyad</label><input type="text" name="name" value="<?= h($usr['full_name']) ?>" required style="width:100%">
  <label>Rol</label>
  <select name="role" <?= $self ? 'disabled' : '' ?>>
    <?php foreach (ROLE_LABELS as $k => $v): ?><option value="<?= $k ?>" <?= $usr['role'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
  </select>
  <?php if ($self): ?><input type="hidden" name="role" value="mentor"><?php endif; ?>
  <label>Sınıf</label><input type="text" name="grade" value="<?= h($usr['grade']) ?>" style="width:120px">
  <label>Kullanıcı adı</label><input type="text" name="username" value="<?= h($usr['username']) ?>" required style="width:260px">
  <label><input type="checkbox" name="active" <?= $usr['active'] ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>> Aktif (kapatılırsa giriş yapamaz)</label>
  <?php if ($self && $usr['active']): ?><input type="hidden" name="active" value="1"><?php endif; ?>
  <label>Birimler</label>
  <div class="ug">
    <?php foreach ($units as $un): $uid = (int)$un['id']; ?>
      <label style="margin:0"><input type="checkbox" name="units[]" value="<?= $uid ?>" <?= isset($mine[$uid]) ? 'checked' : '' ?>> <?= h($un['name']) ?></label>
      <label style="margin:0"><input type="checkbox" name="leads[]" value="<?= $uid ?>" <?= !empty($mine[$uid]) ? 'checked' : '' ?>> sorumlu</label>
    <?php endforeach; ?>
  </div>
  <p><button class="btn">Kaydet</button></p>
</form>

<h2 style="font-size:16px;color:#F59E0B;margin-top:30px">Şifre / PIN</h2>
<form method="post" class="f" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="act" value="set_secret">
  <label><?= $usr['role'] === 'mentor' ? 'Yeni şifre (en az 10 karakter)' : 'Yeni PIN (4-8 rakam)' ?></label>
  <input type="password" name="secret" autocomplete="new-password" style="width:260px" required>
  <button class="btn">Belirle</button>
</form>
<?php if ($usr['role'] !== 'mentor'): ?>
  <form method="post" style="margin-top:10px" onsubmit="return confirm('Yeni PIN üretilsin mi? Eski PIN geçersiz olur.')">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="act" value="gen_pin">
    <button class="btn">Rastgele yeni PIN üret (bir kez gösterilir)</button>
  </form>
<?php endif; ?>

<?php if (!$self): ?>
  <h2 style="font-size:16px;color:#FDA4AF;margin-top:30px">Tehlikeli bölge</h2>
  <p style="font-size:12px;color:#94A3B8">Silmek yerine "Aktif" kutusunu kapatarak girişi engelleyebilirsiniz; geçmiş kayıtlar korunur. Silinen kullanıcının daha önceki değişiklikleri denetim kaydında adıyla kalır, temsilci olduğu takımlar boşalır.</p>
  <form method="post" onsubmit="return confirm('<?= h(addslashes($usr['full_name'])) ?> kalıcı olarak silinsin mi?')">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="act" value="delete">
    <button class="btn red">Kullanıcıyı sil</button>
  </form>
<?php endif;
panel_foot();