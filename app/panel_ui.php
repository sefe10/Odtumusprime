<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/perm.php';
require_once __DIR__ . '/site.php';

function panel_head(string $title): void {
  $u = current_user(); $mentor = is_mentor(); ?>
<!DOCTYPE html><html lang="tr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> | Panel</title>
<style>
body{margin:0;background:#060913;color:#F8FAFC;font:14px Montserrat,Arial,sans-serif}
header{display:flex;gap:16px;align-items:center;flex-wrap:wrap;padding:12px 20px;background:#0F172A;border-bottom:1px solid rgba(255,255,255,.1)}
header a{color:#94A3B8;text-decoration:none;font-weight:600}header a:hover{color:#F59E0B}
header .sp{flex:1}
main{max-width:1000px;margin:24px auto;padding:0 20px}
h1{font-size:20px;color:#F59E0B}
table{width:100%;border-collapse:collapse;margin-bottom:24px}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top}
input[type=text],input[type=email],input[type=number],input[type=datetime-local],textarea{width:100%;box-sizing:border-box;padding:9px 11px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.12);border-radius:6px;color:#fff;font:inherit}
textarea.code{font-family:Consolas,monospace;font-size:12px;min-height:420px}
label{display:block;margin:12px 0 4px;font-size:12px;font-weight:700;color:#CBD5E1}
.btn{display:inline-block;background:#F59E0B;color:#060913;border:0;border-radius:6px;padding:9px 16px;font-weight:800;cursor:pointer;text-decoration:none}
.btn.red{background:#E11D48;color:#fff}
.note{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);padding:10px 12px;border-radius:6px;margin:12px 0;font-size:13px}
.ok{background:rgba(16,185,129,.12);border:1px solid #10B981;color:#A7F3D0;padding:10px 12px;border-radius:6px;margin:12px 0}
.err{background:rgba(225,29,72,.12);border:1px solid #E11D48;color:#FDA4AF;padding:10px 12px;border-radius:6px;margin:12px 0}
.rtool{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 14px}
.pr-only{display:none}
@media print{
  header,.noprint,.rtool,.btn,.mini,button,#stat,.bar,details,#toast{display:none!important}
  body{background:#fff!important;color:#000!important;font-size:11px}
  main{max-width:none!important;margin:0!important;padding:0!important}
  .pr-only{display:block!important;border-bottom:2px solid #000;margin-bottom:10px;padding-bottom:4px;font-size:12px}
  h1,h2,h3{color:#000!important}
  th,td{color:#000!important;border-bottom:1px solid #999!important;padding:4px 6px!important}
  a{color:#000!important;text-decoration:none!important}
  .c,.card,.note,.ok,.err{background:#fff!important;color:#000!important;border:1px solid #bbb!important}
  tr{page-break-inside:avoid}
  *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style></head><body>
<header>
  <a href="/panel/">Pano</a>
  <a href="/scouting/">Scouting</a>
  <a href="/scouting/events.php">Maçlar</a>
  <a href="/scouting/picklist.php">Pick List</a>
  <a href="/panel/m.php?m=tasks">Görevler</a>
  <?php if (can('finance_view')): ?><a href="/panel/finance.php">Mali</a><?php endif; ?>
  <?php if ($mentor): ?>
    <a href="/panel/dashboard.php">Komuta Merkezi</a>
    <a href="/panel/permissions.php">Kullanıcılar</a>
    <a href="/panel/audit.php">Denetim</a>
    <a href="/panel/sync.php">FTC Senkron</a>
    <a href="/panel/pages.php">Site</a>
    <a href="/panel/messages.php">Mesajlar</a>
  <?php endif; ?>
  <span class="sp"></span>
  <a href="/panel/profile.php"><?= h($u['name'] ?? '') ?></a>
  <a href="/" target="_blank">Siteyi Gör</a>
  <a href="/logout.php">Çıkış</a>
</header><main>
<?php
}

function panel_foot(): void {
  $u = current_user(); ?>
</main>
<script>
(function () {
  const main = document.querySelector('main'); if (!main) return;
  const title = document.title.replace(/\s*\|\s*Panel\s*$/, '');
  const tools = [{id: 'rp-print', t: '🖨️ Yazdır / PDF'}, {id: 'rp-csv', t: '📊 Rapor al (Excel / CSV)'}];
  const bar = document.createElement('div'); bar.className = 'rtool';
  bar.innerHTML = tools.map(function (x) { return '<button type="button" class="btn" id="' + x.id + '">' + x.t + '</button>'; }).join('');
  const h1 = main.querySelector('h1');
  if (h1) h1.after(bar); else main.prepend(bar);
  const hd = document.createElement('div'); hd.className = 'pr-only';
  hd.innerHTML = '<b>ODTÜmus Prime #25601</b> — ' + title + ' — ' + new Date().toLocaleString('tr-TR') + ' — ' + <?= json_encode($u['name'] ?? '') ?>;
  main.prepend(hd);
  const tbls = function () { return [].filter.call(main.querySelectorAll('table'), function (t) { return !t.parentElement.closest('table'); }); };
  if (!tbls().length) document.getElementById('rp-csv').style.display = 'none';
  document.getElementById('rp-print').onclick = function () { window.print(); };

  function cell(td) {
    const c = td.querySelector('input[type=checkbox]'); if (c) return c.checked ? '✔' : '';
    const s = td.querySelector('select'); if (s) return (s.selectedIndex >= 0 && s.value !== '') ? s.options[s.selectedIndex].text : '';
    const i = td.querySelector('input:not([type=hidden]),textarea'); if (i) return i.value;
    return td.innerText.replace(/\s+/g, ' ').trim();
  }
  document.getElementById('rp-csv').onclick = function () {
    let out = '';
    tbls().forEach(function (t) {
      let p = t.previousElementSibling; while (p && !/^H[1-3]$/.test(p.tagName)) p = p.previousElementSibling;
      out += '"' + (p ? p.innerText : title).replace(/"/g, '""') + '"\r\n';
      [].forEach.call(t.rows, function (tr) {
        if (tr.offsetParent === null || tr.classList.contains('noexp')) return;
        out += [].map.call(tr.cells, function (td) { return '"' + cell(td).replace(/"/g, '""') + '"'; }).join(';') + '\r\n';
      });
      out += '\r\n';
    });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['\uFEFF' + out], {type: 'text/csv;charset=utf-8'}));
    a.download = title.replace(/[^\wÇçĞğİıÖöŞşÜü-]+/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
  };

  let swaps = [], pg = null;
  window.addEventListener('beforeprint', function () {
    main.querySelectorAll('input,select,textarea').forEach(function (el) {
      if (el.type === 'hidden' || el.offsetParent === null || el.closest('.bar,.noprint')) return;
      const s = document.createElement('span'); s.className = 'pv'; s.style.whiteSpace = 'pre-wrap';
      if (el.type === 'checkbox') s.textContent = el.checked ? '✔' : '☐';
      else if (el.tagName === 'SELECT') s.textContent = (el.selectedIndex >= 0 && el.value !== '') ? el.options[el.selectedIndex].text : '';
      else s.textContent = el.value;
      el.style.display = 'none'; el.parentNode.insertBefore(s, el); swaps.push([el, s]);
    });
    let max = 0; tbls().forEach(function (t) { if (t.rows[0]) max = Math.max(max, t.rows[t.rows.length > 1 ? 1 : 0].cells.length); });
    pg = document.createElement('style');
    pg.textContent = '@page{size:A4 ' + (max > 8 ? 'landscape' : 'portrait') + ';margin:10mm}';
    document.head.appendChild(pg);
  });
  window.addEventListener('afterprint', function () {
    swaps.forEach(function (p) { p[1].remove(); p[0].style.display = ''; }); swaps = [];
    if (pg) { pg.remove(); pg = null; }
  });
})();
</script>
</body></html>
<?php
}
