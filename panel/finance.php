<?php
require __DIR__ . '/../app/panel_ui.php';
$u = require_perm('finance_view');
$q = fn(string $sql) => db()->query($sql)->fetchColumn();
$f = fn($n) => number_format((float)$n, 2, ',', '.');
$inc = (float)$q("SELECT COALESCE(SUM(amount),0) FROM m_budget WHERE kind='Gelir'");
$exp = (float)$q("SELECT COALESCE(SUM(amount),0) FROM m_budget WHERE kind='Gider'");
$planExp = (float)$q("SELECT COALESCE(SUM(planned),0) FROM m_budget_plan WHERE kind='Gider'");
$planInc = (float)$q("SELECT COALESCE(SUM(planned),0) FROM m_budget_plan WHERE kind='Gelir'");
$sp = db()->query("SELECT COUNT(*) n, COALESCE(SUM(amount),0) pledged, COALESCE(SUM(status IN ('Onaylandı','Ödendi / teslim edildi')),0) ok FROM m_sponsors")->fetch();
$recv = (float)$q("SELECT COALESCE(SUM(amount),0) FROM m_budget WHERE kind='Gelir' AND sponsor_id IS NOT NULL");
$last = db()->query('SELECT b.*, s.company FROM m_budget b LEFT JOIN m_sponsors s ON s.id=b.sponsor_id ORDER BY b.entry_date DESC, b.id DESC LIMIT 8')->fetchAll();
$next = db()->query("SELECT company, next_contact, status FROM m_sponsors WHERE next_contact IS NOT NULL AND next_contact <= CURDATE() + INTERVAL 14 DAY AND status NOT IN ('Ödendi / teslim edildi','Reddedildi') ORDER BY next_contact LIMIT 8")->fetchAll();
$pct = $planExp > 0 ? min(100, (int)round($exp * 100 / $planExp)) : 0;
$ro = !can('finance_edit') && !can('sponsors_edit') && !can('budget_edit');

panel_head('Mali Özet'); ?>
<style>
main{max-width:1200px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin:14px 0}
.c{background:#0F172A;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:16px}
.c h3{margin:0 0 8px;font-size:12px;color:#F59E0B;text-transform:uppercase}
.big{font-size:24px;font-weight:900}
.pb{background:rgba(255,255,255,.12);border-radius:6px;height:8px;margin:6px 0}.pb i{display:block;height:8px;border-radius:6px;background:#F59E0B}
</style>
<h1>Mali Özet</h1>
<?php if ($ro): ?><div class="note">Bu bölümü yalnızca görüntüleyebilirsiniz. Düzenleme yetkisi için mentörünüze başvurun.</div><?php endif; ?>
<p class="noprint">
  <a class="btn" href="/panel/m.php?m=budget">Gelir - Gider</a> <a class="btn" href="/panel/m.php?m=sponsors">Sponsor Takibi</a>
  <a class="btn" href="/panel/m.php?m=budget_plan">Bütçe Planı</a> <a class="btn" href="/panel/finance-report.php">Mali Rapor</a>
</p>
<div class="g">
  <div class="c"><h3>Toplam gelir</h3><div class="big" style="color:#6EE7B7"><?= $f($inc) ?> TL</div>Plan: <?= $f($planInc) ?> TL</div>
  <div class="c"><h3>Toplam gider</h3><div class="big" style="color:#FDA4AF"><?= $f($exp) ?> TL</div>Plan: <?= $f($planExp) ?> TL</div>
  <div class="c"><h3>Kalan bakiye</h3><div class="big" style="color:<?= $inc - $exp < 0 ? '#FDA4AF' : '#fff' ?>"><?= $f($inc - $exp) ?> TL</div></div>
  <div class="c"><h3>Bütçe kullanımı (gider)</h3><div class="big">%<?= $pct ?></div><div class="pb"><i style="width:<?= $pct ?>%"></i></div><?= $f($exp) ?> / <?= $f($planExp) ?> TL</div>
  <div class="c"><h3>Sponsorlar</h3><div class="big"><?= (int)$sp['ok'] ?> / <?= (int)$sp['n'] ?></div>onaylanan • Taahhüt: <?= $f($sp['pledged']) ?> TL<br>Sponsorlardan gelen: <b><?= $f($recv) ?> TL</b></div>
</div>
<h2 style="font-size:16px;color:#F59E0B">Son işlemler</h2>
<table>
  <tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Açıklama</th><th>Sponsor</th><th>Tutar (TL)</th></tr>
  <?php foreach ($last as $r): ?>
    <tr><td><?= h($r['entry_date']) ?></td><td style="color:<?= $r['kind'] === 'Gelir' ? '#6EE7B7' : '#FDA4AF' ?>"><?= h($r['kind']) ?></td><td><?= h($r['category']) ?></td><td><?= h($r['description']) ?></td><td><?= h($r['company']) ?></td><td><b><?= $f($r['amount']) ?></b></td></tr>
  <?php endforeach; ?>
  <?php if (!$last): ?><tr><td colspan="6" style="color:#94A3B8">Henüz kayıt yok.</td></tr><?php endif; ?>
</table>
<h2 style="font-size:16px;color:#F59E0B">Yaklaşan sponsor görüşmeleri (14 gün)</h2>
<table>
  <tr><th>Tarih</th><th>Firma</th><th>Durum</th></tr>
  <?php foreach ($next as $r): ?><tr><td style="color:<?= $r['next_contact'] < date('Y-m-d') ? '#FDA4AF' : '#FCD34D' ?>"><?= h($r['next_contact']) ?></td><td><?= h($r['company']) ?></td><td><?= h($r['status']) ?></td></tr><?php endforeach; ?>
  <?php if (!$next): ?><tr><td colspan="3" style="color:#94A3B8">Planlı görüşme yok.</td></tr><?php endif; ?>
</table>
<?php panel_foot();
