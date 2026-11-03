<?php
require __DIR__ . '/../app/panel_ui.php';
$u = require_perm('finance_view');

$sy = (int)date('n') >= 9 ? (int)date('Y') : (int)date('Y') - 1;
$from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$okd = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
if (!$okd($from) || !$okd($to)) {
  switch ($_GET['r'] ?? 'season') {
    case 'month': $from = date('Y-m-01'); $to = date('Y-m-t'); break;
    case '3m': $from = date('Y-m-d', strtotime('-3 months')); $to = date('Y-m-d'); break;
    case 'all': $from = '2000-01-01'; $to = '2100-12-31'; break;
    default: $from = "$sy-09-01"; $to = ($sy + 1) . '-08-31';
  }
}
$f = fn($n) => number_format((float)$n, 2, ',', '.');
$q = function (string $sql, array $p = []) { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); };
$rng = [$from, $to];

$inc = $exp = 0.0; $ni = $ne = 0;
foreach ($q('SELECT kind, COALESCE(SUM(amount),0) s, COUNT(*) n FROM m_budget WHERE entry_date BETWEEN ? AND ? GROUP BY kind', $rng) as $r) {
  if ($r['kind'] === 'Gelir') { $inc = (float)$r['s']; $ni = (int)$r['n']; } else { $exp = (float)$r['s']; $ne = (int)$r['n']; }
}
$act = []; $plan = [];
foreach ($q("SELECT kind, COALESCE(category,'(kategorisiz)') c, SUM(amount) s FROM m_budget WHERE entry_date BETWEEN ? AND ? GROUP BY kind, c", $rng) as $r) $act[$r['kind']][$r['c']] = (float)$r['s'];
foreach ($q("SELECT kind, COALESCE(category,'(kategorisiz)') c, SUM(planned) s FROM m_budget_plan GROUP BY kind, c") as $r) $plan[$r['kind']][$r['c']] = (float)$r['s'];
$mon = $q("SELECT DATE_FORMAT(entry_date,'%Y-%m') ym, COALESCE(SUM(CASE WHEN kind='Gelir' THEN amount END),0) i, COALESCE(SUM(CASE WHEN kind='Gider' THEN amount END),0) e FROM m_budget WHERE entry_date BETWEEN ? AND ? GROUP BY ym ORDER BY ym", $rng);
$mx = 1; foreach ($mon as $m) $mx = max($mx, (float)$m['i'], (float)$m['e']);
$sp = $q("SELECT s.company, s.kind, s.status, s.amount, s.next_contact, COALESCE(SUM(b.amount),0) rec FROM m_sponsors s LEFT JOIN m_budget b ON b.sponsor_id=s.id AND b.kind='Gelir' AND b.entry_date BETWEEN ? AND ? GROUP BY s.id ORDER BY s.status, s.company", $rng);
$byStatus = [];
foreach ($sp as $r) { $x = &$byStatus[$r['status']]; $x['n'] = ($x['n'] ?? 0) + 1; $x['p'] = ($x['p'] ?? 0) + $r['amount']; $x['r'] = ($x['r'] ?? 0) + $r['rec']; unset($x); }
$tx = $q('SELECT b.*, s.company FROM m_budget b LEFT JOIN m_sponsors s ON s.id=b.sponsor_id WHERE b.entry_date BETWEEN ? AND ? ORDER BY b.entry_date, b.id LIMIT 1000', $rng);

panel_head('Mali Rapor'); ?>
<style>
main{max-width:1200px}
.bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0}
.bar input{padding:8px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px}
.bg{display:inline-block;height:12px;border-radius:3px;vertical-align:middle}
.i{background:#10B981}.e{background:#E11D48}
h2.s{font-size:16px;color:#F59E0B;margin:24px 0 6px}
</style>
<h1>Mali Rapor</h1>
<form class="bar" method="get">
  <a class="btn" href="?r=season">Bu sezon</a> <a class="btn" href="?r=month">Bu ay</a> <a class="btn" href="?r=3m">Son 3 ay</a> <a class="btn" href="?r=all">Tümü</a>
  <input type="date" name="from" value="<?= h($from) ?>"> – <input type="date" name="to" value="<?= h($to) ?>"> <button class="btn">Uygula</button>
</form>
<p><b>Dönem:</b> <?= h(date('d.m.Y', strtotime($from))) ?> – <?= h(date('d.m.Y', strtotime($to))) ?></p>

<h2 class="s">Özet</h2>
<table>
  <tr><th>Gelir</th><th>Gider</th><th>Net (gelir − gider)</th><th>İşlem sayısı</th></tr>
  <tr><td><b style="color:#6EE7B7"><?= $f($inc) ?> TL</b></td><td><b style="color:#FDA4AF"><?= $f($exp) ?> TL</b></td><td><b><?= $f($inc - $exp) ?> TL</b></td><td><?= $ni + $ne ?> (<?= $ni ?> gelir, <?= $ne ?> gider)</td></tr>
</table>

<?php foreach (['Gider' => 'Gider: Bütçe Planı ve Gerçekleşen', 'Gelir' => 'Gelir: Bütçe Planı ve Gerçekleşen'] as $k => $ttl):
  $cats = array_unique(array_merge(array_keys($act[$k] ?? []), array_keys($plan[$k] ?? []))); sort($cats); ?>
  <h2 class="s"><?= h($ttl) ?></h2>
  <table>
    <tr><th>Kategori</th><th>Planlanan (TL)</th><th>Gerçekleşen (TL)</th><th>Fark (TL)</th><th>Kullanım %</th></tr>
    <?php $tp = $ta = 0; foreach ($cats as $c): $p = $plan[$k][$c] ?? 0; $a = $act[$k][$c] ?? 0; $tp += $p; $ta += $a; ?>
      <tr><td><?= h($c) ?></td><td><?= $f($p) ?></td><td><?= $f($a) ?></td><td style="color:<?= ($k === 'Gider' ? $a > $p : $a < $p) && $p > 0 ? '#FDA4AF' : '#6EE7B7' ?>"><?= $f($p - $a) ?></td><td><?= $p > 0 ? round($a * 100 / $p) . '%' : '—' ?></td></tr>
    <?php endforeach; ?>
    <tr><td><b>Toplam</b></td><td><b><?= $f($tp) ?></b></td><td><b><?= $f($ta) ?></b></td><td><b><?= $f($tp - $ta) ?></b></td><td><b><?= $tp > 0 ? round($ta * 100 / $tp) . '%' : '—' ?></b></td></tr>
    <?php if (!$cats): ?><tr><td colspan="5" style="color:#94A3B8">Kayıt yok.</td></tr><?php endif; ?>
  </table>
<?php endforeach; ?>

<h2 class="s">Aylık Gelir - Gider</h2>
<table>
  <tr><th>Ay</th><th>Gelir (TL)</th><th>Gider (TL)</th><th>Net (TL)</th><th>Kümülatif bakiye (TL)</th><th class="noprint">Grafik</th></tr>
  <?php $cum = 0; foreach ($mon as $m): $cum += $m['i'] - $m['e']; ?>
    <tr><td><?= h($m['ym']) ?></td><td><?= $f($m['i']) ?></td><td><?= $f($m['e']) ?></td><td><?= $f($m['i'] - $m['e']) ?></td><td><b><?= $f($cum) ?></b></td>
      <td class="noprint" style="min-width:200px"><span class="bg i" style="width:<?= round($m['i'] * 100 / $mx) ?>%"></span><br><span class="bg e" style="width:<?= round($m['e'] * 100 / $mx) ?>%"></span></td></tr>
  <?php endforeach; ?>
  <?php if (!$mon): ?><tr><td colspan="6" style="color:#94A3B8">Kayıt yok.</td></tr><?php endif; ?>
</table>

<h2 class="s">Sponsor Durumu</h2>
<table>
  <tr><th>Durum</th><th>Firma sayısı</th><th>Taahhüt (TL)</th><th>Dönemde gelen (TL)</th></tr>
  <?php foreach ($byStatus as $st => $x): ?><tr><td><?= h($st) ?></td><td><?= (int)$x['n'] ?></td><td><?= $f($x['p']) ?></td><td><?= $f($x['r']) ?></td></tr><?php endforeach; ?>
  <?php if (!$byStatus): ?><tr><td colspan="4" style="color:#94A3B8">Sponsor kaydı yok.</td></tr><?php endif; ?>
</table>
<h2 class="s">Sponsor Listesi</h2>
<table>
  <tr><th>Firma</th><th>Tür</th><th>Durum</th><th>Taahhüt (TL)</th><th>Dönemde gelen (TL)</th><th>Kalan (TL)</th><th>Sonraki görüşme</th></tr>
  <?php foreach ($sp as $r): ?><tr><td><?= h($r['company']) ?></td><td><?= h($r['kind']) ?></td><td><?= h($r['status']) ?></td><td><?= $f($r['amount']) ?></td><td><?= $f($r['rec']) ?></td><td><?= $f(max(0, $r['amount'] - $r['rec'])) ?></td><td><?= h($r['next_contact']) ?></td></tr><?php endforeach; ?>
</table>

<h2 class="s">İşlem Dökümü (<?= count($tx) ?>)</h2>
<table>
  <tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Açıklama</th><th>Sponsor</th><th>Ödeme</th><th>Tutar (TL)</th><th>Belge</th></tr>
  <?php foreach ($tx as $r): ?>
    <tr><td><?= h($r['entry_date']) ?></td><td><?= h($r['kind']) ?></td><td><?= h($r['category']) ?></td><td><?= h($r['description']) ?></td><td><?= h($r['company']) ?></td><td><?= h($r['pay_method']) ?></td><td><b><?= $f($r['amount']) ?></b></td>
      <td><?= preg_match('#^https?://#i', (string)$r['invoice_link']) ? '<a href="' . h($r['invoice_link']) . '" target="_blank" rel="noopener noreferrer">belge</a>' : h($r['invoice_link']) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php panel_foot();
