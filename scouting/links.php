<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/scouting.php';
$u = require_login();

$labels = ['web' => 'Web', 'ig' => 'Instagram', 'yt' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X', 'fb' => 'Facebook'];
$names = []; $vals = [];
$in = "'" . implode("','", array_merge(array_keys($labels), ['name'])) . "'";
foreach (db()->query("SELECT team_no, field_key, value, source FROM team_fields WHERE field_key IN ($in)") as $r) {
  if ($r['field_key'] === 'name') $names[$r['team_no']] = $r['value'];
  else $vals[$r['team_no']][$r['field_key']] = $r;
}
$teams = db()->query('SELECT team_no, name FROM teams ORDER BY team_no')->fetchAll();

panel_head('Bağlantılar'); ?>
<style>
main{max-width:1400px}
.lt input{width:100%;min-width:130px;box-sizing:border-box;padding:6px 8px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:5px;color:#fff;font:inherit;font-size:12px}
.lt th,.lt td{padding:5px 6px}
.dotv{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:4px;background:#475569}
.s-auto{background:#3B82F6}.s-manual{background:#10B981}.s-import{background:#F59E0B}
a.sr{color:#F59E0B;font-size:11px;text-decoration:none;white-space:nowrap}
</style>
<h1>Web ve Sosyal Medya Bağlantıları</h1>
<div class="note">Hücreye hesabı <b>@kullaniciadi</b> veya tam bağlantı olarak yazın, yazıp başka yere tıklayınca kaydedilir.
Rozet renkleri: <span class="dotv s-auto"></span>FTC'den otomatik <span class="dotv s-manual"></span>elle girildi <span class="dotv s-import"></span>eski veri (doğrulanmadı).
Doğrulamak için "ara" bağlantısını kullanın.</div>
<p><label><input type="checkbox" id="miss" onchange="flt()"> Sadece Web ve Instagram'ı eksik olanları göster</label></p>
<div style="overflow-x:auto"><table class="lt" id="tbl">
  <tr><th>No</th><th>Takım</th><?php foreach ($labels as $l): ?><th><?= h($l) ?></th><?php endforeach; ?><th>Ara</th></tr>
  <?php foreach ($teams as $t):
    $no = (int)$t['team_no']; $nm = $names[$no] ?? $t['name'];
    $miss = empty($vals[$no]['web']['value']) && empty($vals[$no]['ig']['value']); ?>
    <tr data-miss="<?= $miss ? 1 : 0 ?>">
      <td><a style="color:#F59E0B" href="/scouting/team.php?no=<?= $no ?>">#<?= $no ?></a></td>
      <td><?= h($nm) ?></td>
      <?php foreach ($labels as $k => $l): $row = $vals[$no][$k] ?? null; ?>
        <td><span class="dotv s-<?= h($row['source'] ?? 'none') ?>"></span>
          <input data-team="<?= $no ?>" data-key="<?= h($k) ?>" value="<?= h($row['value'] ?? '') ?>"></td>
      <?php endforeach; ?>
      <td><a class="sr" target="_blank" rel="noopener" href="https://www.google.com/search?q=<?= rawurlencode($nm . ' FTC ' . $no . ' instagram') ?>">instagram ara</a><br>
          <a class="sr" target="_blank" rel="noopener" href="https://www.google.com/search?q=<?= rawurlencode($nm . ' FTC ' . $no . ' web sitesi') ?>">site ara</a></td>
    </tr>
  <?php endforeach; ?>
</table></div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
function flt() {
  const on = document.getElementById('miss').checked;
  document.querySelectorAll('#tbl tr[data-miss]').forEach(function (tr) { tr.style.display = (!on || tr.dataset.miss === '1') ? '' : 'none'; });
}
document.querySelectorAll('input[data-team]').forEach(function (el) {
  let old = el.value;
  el.addEventListener('blur', function () {
    if (el.value === old) return;
    old = el.value;
    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('kind', 'field'); fd.append('team', el.dataset.team);
    fd.append('key', el.dataset.key); fd.append('value', el.value);
    fetch('/scouting/save.php', {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) {
      el.style.borderColor = j.ok ? '#10B981' : '#E11D48';
      if (j.ok) el.previousElementSibling.className = 'dotv s-manual';
      setTimeout(function () { el.style.borderColor = ''; }, 1200);
    }).catch(function () { el.style.borderColor = '#E11D48'; });
  });
});
</script>
<?php panel_foot();