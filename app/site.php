<?php
require_once __DIR__ . '/db.php';

function setting(string $k, string $d = ''): string {
  static $all = null;
  if ($all === null) {
    $all = [];
    foreach (db()->query('SELECT k, v FROM settings') as $r) $all[$r['k']] = $r['v'];
  }
  return $all[$k] ?? $d;
}

function menu_items(): array {
  return db()->query("SELECT slug, COALESCE(menu_label, title) AS label
                      FROM site_pages WHERE published=1 AND slug<>'home'
                      ORDER BY sort_order, slug")->fetchAll();
}

function page_head(string $title, string $desc = '', bool $home = false): void {
  $menu = menu_items();
  $css  = '/assets/site.css?v=' . (int)@filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/site.css');
  ?><!DOCTYPE html>
<html lang="tr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?></title>
<meta name="description" content="<?= h($desc) ?>">
<link rel="icon" href="/assets/logo.png">
<link rel="stylesheet" href="<?= h($css) ?>">
<style>
.page-main.inner{padding-top:84px}
@media (max-width:768px){
  .nav-links.open{display:flex;flex-direction:column;position:absolute;top:65px;left:0;right:0;
    background:#0A1128;padding:20px;border-bottom:1px solid var(--border-color)}
}
</style>
</head>
<body data-countdown="<?= h(setting('countdown_target', '2026-12-19T09:00:00+03:00')) ?>">
<nav class="navbar"><div class="nav-container">
  <a href="/" class="nav-brand">
    <div class="nav-logo-circle"><img src="/assets/logo.png" alt="ODTÜmus Prime"></div>
    <div class="brand-text"><span class="brand-title">ODTÜMUS PRIME</span><span class="brand-subtitle">TEAM #25601 • FTC</span></div>
  </a>
  <ul class="nav-links">
    <?php foreach ($menu as $m): ?>
      <li><a href="/<?= h($m['slug']) ?>" class="nav-link"><?= h($m['label']) ?></a></li>
    <?php endforeach; ?>
    <li><a href="/sponsorluk" class="nav-cta">Sponsor Olun</a></li>
  </ul>
  <button class="mobile-toggle" onclick="toggleMobileMenu()" aria-label="Menü">☰</button>
</div></nav>
<main class="page-main<?= $home ? '' : ' inner' ?>">
<?php
}

function page_foot(): void {
  ?></main>
<footer class="site-footer">
  <div class="footer-socials">
    <a href="https://www.instagram.com/odtumusprime25601/" target="_blank" rel="noopener" class="social-link">Instagram (@odtumusprime25601)</a>
    <?php foreach (menu_items() as $m): ?>
      <a href="/<?= h($m['slug']) ?>" class="social-link"><?= h($m['label']) ?></a>
    <?php endforeach; ?>
    <a href="https://www.odtugvo.k12.tr/izmir" target="_blank" rel="noopener" class="social-link">ODTÜ GVO İzmir</a>
    <a href="/login.php" class="social-link">Takım Girişi</a>
  </div>
  <p style="margin-bottom:8px">
    <b>ODTÜmus Prime (#25601)</b> • ODTÜ Geliştirme Vakfı Özel İzmir Okulları, Güzelbahçe Yelki Kampüsü<br>
    Lead Mentör: <b>Sercan EFE</b> • Mentör: <b>Mesut AKTÜRK</b>
  </p>
  <p style="color:#64748B;font-size:11px">
    © 2026-2027 ODTÜmus Prime (#25601) • FIRST® ve FIRST® Tech Challenge, For Inspiration and Recognition of Science and Technology (FIRST)'nin tescilli ticari markalarıdır.
  </p>
</footer>
<script src="/assets/site.js"></script>
</body></html>
<?php
}

function contact_form_html(): string {
  $t   = time();
  $sig = hash_hmac('sha256', (string)$t, cfg()['setup_key']);
  ob_start(); ?>
<form class="contact-form" method="post" action="/iletisim-gonder.php">
  <input type="hidden" name="t" value="<?= $t ?>">
  <input type="hidden" name="sig" value="<?= h($sig) ?>">
  <div style="position:absolute;left:-9999px" aria-hidden="true">
    <input name="website" tabindex="-1" autocomplete="off">
  </div>
  <?php if (isset($_GET['gonderildi'])): ?>
    <div style="margin-bottom:14px;font-size:13px;color:var(--accent-emerald);font-weight:700;text-align:center;background:rgba(16,185,129,.1);padding:10px;border-radius:6px;border:1px solid var(--accent-emerald)">
      ✔ Teşekkür ederiz! Mesajınız takım mentorlarımıza iletildi.
    </div>
  <?php elseif (isset($_GET['hata'])): ?>
    <div style="margin-bottom:14px;font-size:13px;color:#FDA4AF;font-weight:700;text-align:center;background:rgba(225,29,72,.1);padding:10px;border-radius:6px;border:1px solid #E11D48">
      Mesaj gönderilemedi. Alanları kontrol edip biraz sonra tekrar deneyin.
    </div>
  <?php endif; ?>
  <div class="form-group"><label class="form-label" for="f-name">Adınız Soyadınız / Yetkili</label>
    <input class="form-input" id="f-name" name="name" maxlength="120" required></div>
  <div class="form-group"><label class="form-label" for="f-company">Şirket / Kurum</label>
    <input class="form-input" id="f-company" name="company" maxlength="120"></div>
  <div class="form-group"><label class="form-label" for="f-email">E-Posta</label>
    <input class="form-input" type="email" id="f-email" name="email" maxlength="160" required></div>
  <div class="form-group"><label class="form-label" for="f-msg">Mesajınız</label>
    <textarea class="form-textarea" id="f-msg" name="message" maxlength="4000" required></textarea></div>
  <button type="submit" class="btn-primary" style="border:none;cursor:pointer;justify-content:center;width:100%">
    <span>✉️</span> Mesajı Gönder
  </button>
</form>
<?php
  return ob_get_clean();
}

function show_page(string $slug): void {
  $st = db()->prepare('SELECT * FROM site_pages WHERE slug=? AND published=1');
  $st->execute([$slug]);
  $p = $st->fetch();
  if (!$p) {
    http_response_code(404);
    page_head('Sayfa bulunamadı | ODTÜmus Prime');
    echo '<section class="section-wrap"><h1 class="section-title">404</h1><p class="section-desc">Aradığınız sayfa bulunamadı.</p></section>';
    page_foot();
    return;
  }
  $content = $p['content'];
  if (strpos($content, '{{contact_form}}') !== false) {
    $content = str_replace('{{contact_form}}', contact_form_html(), $content);
  }
  $home = ($slug === 'home');
  $title = $home ? 'ODTÜmus Prime (#25601) | Resmi FIRST® Tech Challenge Robotik Takımı'
                 : $p['title'] . ' | ODTÜmus Prime (#25601)';
  page_head($title, 'ODTÜ Geliştirme Vakfı Özel İzmir Ortaokulu FIRST Tech Challenge Robotik Takımı ODTÜmus Prime (#25601).', $home);
  echo $content;
  page_foot();
}