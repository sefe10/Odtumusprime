<?php
require __DIR__ . '/../app/panel_ui.php';
$u = require_login(['mentor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  if (($_POST['act'] ?? '') === 'read')   db()->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([$id]);
  if (($_POST['act'] ?? '') === 'delete') db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$id]);
  header('Location: /panel/messages.php'); exit;
}

panel_head('Mesajlar'); ?>
<h1>İletişim Formu Mesajları</h1>
<table>
  <tr><th>Tarih</th><th>Gönderen</th><th>Mesaj</th><th></th></tr>
  <?php foreach (db()->query('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 100') as $m): ?>
    <tr style="<?= $m['is_read'] ? 'opacity:.6' : '' ?>">
      <td><?= h($m['created_at']) ?></td>
      <td><b><?= h($m['name']) ?></b><br><?= h($m['company']) ?><br>
          <a style="color:#F59E0B" href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a></td>
      <td style="white-space:pre-wrap"><?= h($m['message']) ?></td>
      <td>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <?php if (!$m['is_read']): ?><button class="btn" name="act" value="read">Okundu</button><?php endif; ?>
          <button class="btn red" name="act" value="delete" onclick="return confirm('Silinsin mi?')">Sil</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php panel_foot();