<?php
require __DIR__ . '/../app/panel_ui.php';
$u = require_login(['mentor']);
$reserved = ['panel', 'assets', 'app'];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $act  = $_POST['act'] ?? '';
  $slug = strtolower(trim($_POST['slug'] ?? ''));
  if ($act === 'save') {
    $q = db()->prepare('SELECT 1 FROM site_pages WHERE slug=?'); $q->execute([$slug]);
    $isNew = !$q->fetchColumn();
    if (!preg_match('/^[a-z0-9-]{1,60}$/', $slug) || ($isNew && in_array($slug, $reserved, true))) {
      $err = 'Geçersiz veya ayrılmış adres (slug). Sadece küçük harf, rakam ve tire kullanın.';
    } else {
      $pub = ($slug === 'home' || isset($_POST['published'])) ? 1 : 0;
      db()->prepare('INSERT INTO site_pages (slug,title,menu_label,content,published,sort_order) VALUES (?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE title=VALUES(title), menu_label=VALUES(menu_label),
          content=VALUES(content), published=VALUES(published), sort_order=VALUES(sort_order)')
        ->execute([$slug, trim($_POST['title'] ?? ''), trim($_POST['menu_label'] ?? '') ?: null,
                   $_POST['content'] ?? '', $pub, (int)($_POST['sort_order'] ?? 0)]);
      audit('pages', 'Sayfa kaydedildi: ' . $slug);
      header('Location: /panel/pages.php?slug=' . urlencode($slug) . '&ok=1'); exit;
    }
  } elseif ($act === 'delete' && $slug !== 'home') {
    db()->prepare('DELETE FROM site_pages WHERE slug=?')->execute([$slug]);
    audit('pages', 'Sayfa silindi: ' . $slug);
    header('Location: /panel/pages.php'); exit;
  }
}

$slug = $_GET['slug'] ?? null;
$isNew = isset($_GET['new']);
$page = null;
if ($slug) { $st = db()->prepare('SELECT * FROM site_pages WHERE slug=?'); $st->execute([$slug]); $page = $st->fetch(); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $err) {
  $page = ['slug' => $_POST['slug'], 'title' => $_POST['title'], 'menu_label' => $_POST['menu_label'],
           'content' => $_POST['content'], 'published' => isset($_POST['published']), 'sort_order' => $_POST['sort_order']];
  $isNew = true;
}

panel_head('Site Sayfaları'); ?>
<h1>Site Sayfaları</h1>
<?php if (isset($_GET['ok'])): ?><div class="ok">Kaydedildi.</div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

<table>
  <tr><th>Adres</th><th>Başlık</th><th>Sıra</th><th>Durum</th><th>Son güncelleme</th><th></th></tr>
  <?php foreach (db()->query('SELECT slug,title,published,sort_order,updated_at FROM site_pages ORDER BY sort_order, slug') as $r): ?>
    <tr>
      <td>/<?= $r['slug'] === 'home' ? '' : h($r['slug']) ?></td>
      <td><?= h($r['title']) ?></td>
      <td><?= (int)$r['sort_order'] ?></td>
      <td><?= $r['published'] ? 'Yayında' : 'Gizli' ?></td>
      <td><?= h($r['updated_at']) ?></td>
      <td><a class="btn" href="/panel/pages.php?slug=<?= urlencode($r['slug']) ?>">Düzenle</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<p><a class="btn" href="/panel/pages.php?new=1">+ Yeni sayfa</a></p>

<?php if ($page || $isNew): $p = $page ?: ['slug'=>'','title'=>'','menu_label'=>'','content'=>'','published'=>1,'sort_order'=>100]; ?>
  <hr style="border-color:rgba(255,255,255,.1);margin:24px 0">
  <h2><?= $page && !$isNew ? 'Düzenle: ' . h($p['slug']) : 'Yeni sayfa' ?></h2>
  <div class="note">İçerik HTML'dir. Mevcut tasarım sınıflarını (<code>section-wrap</code>, <code>section-title</code>, <code>about-card</code> vb.) koruyarak metinleri değiştirin. Görseller için <code>/assets/dosya.png</code> yolunu kullanın. İletişim sayfasındaki <code>{{contact_form}}</code> etiketini silmeyin.</div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="act" value="save">
    <label>Adres (slug)</label>
    <input type="text" name="slug" value="<?= h($p['slug']) ?>" <?= ($page && !$isNew) ? 'readonly' : '' ?> required>
    <label>Başlık</label><input type="text" name="title" value="<?= h($p['title']) ?>" required>
    <label>Menüde görünecek ad (boşsa başlık kullanılır)</label>
    <input type="text" name="menu_label" value="<?= h($p['menu_label'] ?? '') ?>">
    <label>Menü sırası</label><input type="number" name="sort_order" value="<?= (int)$p['sort_order'] ?>">
    <label><input type="checkbox" name="published" <?= $p['published'] ? 'checked' : '' ?>> Yayında</label>
    <label>İçerik (HTML)</label>
    <textarea class="code" name="content"><?= h($p['content']) ?></textarea>
    <p><button class="btn">Kaydet</button>
       <?php if ($page && !$isNew): ?>
         <a class="btn" target="_blank" href="/<?= $p['slug'] === 'home' ? '' : h($p['slug']) ?>">Sayfayı aç</a>
       <?php endif; ?></p>
  </form>
  <?php if ($page && !$isNew && $p['slug'] !== 'home'): ?>
    <form method="post" onsubmit="return confirm('Bu sayfa silinsin mi?')">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="act" value="delete">
      <input type="hidden" name="slug" value="<?= h($p['slug']) ?>">
      <button class="btn red">Sayfayı sil</button>
    </form>
  <?php endif; ?>
<?php endif;
panel_foot();