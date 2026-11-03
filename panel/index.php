<?php
require __DIR__ . '/../app/panel_ui.php';
$u = require_login();
$mentor = $u['role'] === 'mentor';
$mng = in_array($u['role'], ['mentor', 'youth_mentor'], true);
$groups = [
  'Scouting ve Maç' => [['/scouting/', 'Takım araştırma'], ['/scouting/events.php', 'Turnuvalar ve maçlar'],
                        ['/scouting/picklist.php', 'Pick list'], ['/scouting/links.php', 'Web / sosyal medya']],
  'Ekip İşleri' => [['/panel/m.php?m=tasks', 'Görevler'], ['/panel/m.php?m=notebook', 'Mühendislik defteri'],
                    ['/panel/m.php?m=portfolio', 'Portfolyo (15 sayfa)'], ['/panel/m.php?m=cad', 'CAD sürümleri'],
                    ['/panel/m.php?m=inventory', 'Parça envanteri'], ['/panel/m.php?m=outreach', 'Outreach'],
                    ['/panel/m.php?m=pitcheck', 'Pit paketi']],
];
if ($mng) $groups['Ekip İşleri'][] = ['/panel/attendance.php', 'Devam-devamsızlık'];
if ($mentor) {
  $groups['Lojistik ve Finans'] = [['/panel/docs.php', 'Evrak takibi'], ['/panel/m.php?m=travel', 'Seyahat ve konaklama'],
                                   ['/panel/m.php?m=budget', 'Bütçe'], ['/panel/m.php?m=sponsors', 'Sponsorluk']];
  $groups['Yönetim'] = [['/panel/dashboard.php', 'Komuta merkezi'], ['/panel/users.php', 'Kullanıcılar'],
                        ['/panel/audit.php', 'Denetim kaydı'], ['/panel/sync.php', 'FTC senkron'],
                        ['/panel/pages.php', 'Site sayfaları'], ['/panel/messages.php', 'Mesajlar'], ['/panel/settings.php', 'Ayarlar']];
}
$groups['Hesap'] = [['/panel/profile.php', "PIN'imi değiştir"]];
panel_head('Pano'); ?>
<h1>Hoş geldiniz, <?= h($u['name']) ?></h1>
<?php foreach ($groups as $title => $links): ?>
  <h2 style="font-size:14px;color:#F59E0B;margin:22px 0 8px"><?= h($title) ?></h2>
  <p><?php foreach ($links as [$href, $label]): ?><a class="btn" style="margin:0 6px 8px 0" href="<?= h($href) ?>"><?= h($label) ?></a><?php endforeach; ?></p>
<?php endforeach;
panel_foot();