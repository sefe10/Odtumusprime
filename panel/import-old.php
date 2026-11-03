<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/scouting.php';
$u = require_login(['mentor']);
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $data = json_decode($_POST['json'] ?? '', true);
  if (!is_array($data)) {
    $err = 'JSON okunamadı. Metnin tamamını yapıştırdığınızdan emin olun.';
  } else {
    if (isset($data['teams']) && is_array($data['teams'])) $data = $data['teams'];
    $items = [];
    foreach ($data as $k => $v) {
      if (!is_array($v)) continue;
      if (!isset($v['num']) && is_numeric($k)) $v['num'] = $k;
      if (isset($v['num'])) $items[] = $v;
    }
    if (!$items) {
      $err = 'İçinde "num" alanı olan takım bulunamadı.';
    } else {
      $over = isset($_POST['overwrite']);
      $map = ['name' => 'name', 'city' => 'city', 'rookie' => 'rookie', 'status' => 'status', 'org' => 'org',
        'web' => 'web', 'ig' => 'ig', 'email' => 'email', 'sponsors' => 'sponsors', 'events2025' => 'events_prev',
        'awards' => 'awards', 'stats' => 'stats', 'chassis' => 'chassis', 'software' => 'software',
        'outreach' => 'outreach', 'collab' => 'collab', 'priority' => 'priority',
        'contactNote' => 'contactNote', 'notes1' => 'notes1', 'notes2' => 'notes2'];
      $clean = fn($s) => trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "\n", (string)$s)), ENT_QUOTES, 'UTF-8'));
      $sql = $over
        ? "INSERT INTO team_fields (team_no,field_key,value,source,updated_by) VALUES (?,?,?,'import',?)
           ON DUPLICATE KEY UPDATE value=VALUES(value), source='import', updated_by=VALUES(updated_by)"
        : "INSERT IGNORE INTO team_fields (team_no,field_key,value,source,updated_by) VALUES (?,?,?,'import',?)";
      $ins = db()->prepare($sql);
      $mk = db()->prepare('INSERT INTO teams (team_no,name,city) VALUES (?,?,?) ON DUPLICATE KEY UPDATE team_no=team_no');
      $teams = 0; $fields = 0;
      foreach ($items as $it) {
        $no = (int)$it['num'];
        if (!$no) continue;
        $mk->execute([$no, $clean($it['name'] ?? ''), $clean($it['city'] ?? '')]);
        $teams++;
        foreach ($map as $old => $new) {
          if (!isset($it[$old])) continue;
          $v = $clean($it[$old]);
          if ($v === '' || $v === '-') continue;
          $ins->execute([$no, $new, mb_substr($v, 0, 5000), $u['id']]);
          $fields += $ins->rowCount() ? 1 : 0;
        }
        foreach (['ratings', 'checks'] as $jk) {
          if (!empty($it[$jk]) && is_array($it[$jk])) {
            $ins->execute([$no, $jk, json_encode($it[$jk]), $u['id']]);
            $fields += $ins->rowCount() ? 1 : 0;
          }
        }
      }
      audit('import', "Eski analiz.html verisi aktarıldı: $teams takım, $fields alan");
      $msg = "$teams takım işlendi, $fields alan yazıldı.";
    }
  }
}
panel_head('Eski Veri Aktarımı'); ?>
<h1>Eski analiz.html Verisini Aktar</h1>
<?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
<div class="note">
  <b>Veriyi almak için:</b> <code>analiz.html</code> dosyasını kendi bilgisayarınızda tarayıcıda açın (şifre ekranında kalabilirsiniz),
  <b>F12 &gt; Console</b> sekmesine <code>copy(JSON.stringify(defaultTeams))</code> yazıp Enter'a basın, sonra aşağıya yapıştırın (Ctrl+V).<br>
  Eski sayfada kendi girdiğiniz not/puanlar varsa önce o sayfadaki <b>"Verileri İndir (.json)"</b> düğmesini kullanın ve indirilen dosyanın içeriğini yapıştırın.<br>
  Aktarılan alanlar <b>"Eski veri (doğrulanmadı)"</b> etiketiyle görünür. Düzenlediğinizde "Elle" olur.
</div>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <textarea name="json" class="code" style="min-height:240px" placeholder='[{"num":"25601", ...}]'></textarea>
  <label><input type="checkbox" name="overwrite"> Zaten dolu olan alanların (resmî veri dahil) üzerine yaz</label>
  <p><button class="btn">Aktar</button></p>
</form>
<?php panel_foot();