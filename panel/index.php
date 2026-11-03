<?php
require __DIR__ . '/../app/panel_ui.php';
$u = require_login();
$mentor = is_mentor();
$groups = [
  'Scouting ve Maç' => [['/scouting/', 'Takım araştırma'], ['/scouting/events.php', 'Turnuvalar ve maçlar'],
                        ['/scouting/picklist.php', 'Pick list'], ['/scouting/links.php', 'Web / sosyal medya']],
  'Ekip İşleri' => [['/panel/m.php?m=tasks', 'Görevler'], ['/panel/m.php?m=notebook', 'Mühendislik defteri'],
                    ['/panel/m.php?m=portfolio', 'Portfolyo (15 sayfa)'], ['/panel/m.php?m=cad', 'CAD sürümleri'],
                    ['/panel/m.php?m=inventory', 'Parça envanteri'], ['/panel/m.php?m=outreach', 'Outreach'],
                    ['/panel/m.php?m=pitcheck', 'Pit paketi']],
];
if (can('attendance_edit')) $groups['Ekip İşleri'][] = ['/panel/attendance.php', 'Devam-devamsızlık'];
if (can('finance_view')) $groups['Mali Yönetim'] = [['/panel/finance.php', 'Mali özet'], ['/panel/m.php?m=budget', 'Gelir - gider'],
  ['/panel/m.php?m=sponsors', 'Sponsor takibi'], ['/panel/m.php?m=budget_plan', 'Bütçe planı'], ['/panel/finance-report.php', 'Mali rapor']];
if ($mentor) {
  $groups['Lojistik'] = [['/panel/docs.php', 'Evrak takibi'], ['/panel/m.php?m=travel', 'Seyahat ve konaklama']];
  $groups['Yönetim'] = [['/panel/dashboard.php', 'Komuta merkezi'], ['/panel/permissions.php', 'Kullanıcılar ve yetkiler'],
    ['/panel/audit.php', 'Denetim kaydı'], ['/panel/sync.php', 'FTC senkron'], ['/panel/pages.php', 'Site sayfaları'],
    ['/panel/messages.php', 'Mesajlar'], ['/panel/settings.php', 'Ayarlar']];
}
$groups['Hesap'] = [['/panel/profile.php', "PIN'imi değiştir"]];
$mine = $mentor ? [] : array_map(fn($k) => perm_labels()[$k] ?? $k, perm_ctx((int)$u['id'])['perms']);
panel_head('Pano'); ?>
<h1>Hoş geldiniz, <?= h($u['name']) ?></h1>
<?php if (!$mentor): ?><div class="note"><b>Düzenleme yetkileriniz:</b> <?= $mine ? h(implode(' • ', $mine)) : 'Henüz yok. Diğer bölümleri görüntüleyebilirsiniz, düzenleme için mentörünüzden yetki isteyin.' ?></div><?php endif; ?>
<?php foreach ($groups as $title => $links): ?>
  <h2 style="font-size:14px;color:#F59E0B;margin:22px 0 8px"><?= h($title) ?></h2>
  <p><?php foreach ($links as [$href, $label]): ?><a class="btn" style="margin:0 6px 8px 0" href="<?= h($href) ?>"><?= h($label) ?></a><?php endforeach; ?></p>
<?php endforeach;
panel_foot();
