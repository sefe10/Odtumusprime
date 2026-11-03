<?php
require __DIR__ . '/../app/panel_ui.php';
require __DIR__ . '/../app/ftc.php';
$u = require_login(['mentor']);
panel_head('FTC Senkron'); ?>
<h1>FTC Events Senkronu</h1>
<div class="note">Önce <a href="/panel/ftc-test.php" style="color:#F59E0B">API testini</a> çalıştırın. Sayfayı işlem bitene kadar kapatmayın. İşlemleri tekrar çalıştırmak güvenlidir. Elle düzenlenen alanlar korunur.</div>
<p><button class="btn" id="b1" onclick="runBasic()">1) Etkinlikleri ve takımları güncelle</button>
   <button class="btn" id="b2" onclick="runHistory()">2) Tüm takımların geçmişini çek (birkaç dakika sürer)</button></p>
<p><input id="one" type="number" placeholder="Takım no" style="width:140px;padding:9px;background:#0F172A;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px">
   <button class="btn" onclick="addOne()">+ Tek takım ekle</button></p>
<pre id="log" style="background:#0F172A;padding:12px;border-radius:6px;height:340px;overflow:auto;font-size:12px;white-space:pre-wrap"></pre>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const log = function (m) { const l = document.getElementById('log'); l.textContent += m + '\n'; l.scrollTop = l.scrollHeight; };
async function call(p) {
  const fd = new FormData(); fd.append('csrf', CSRF);
  for (const k in p) fd.append(k, p[k]);
  const r = await fetch('/panel/sync-run.php', {method: 'POST', body: fd});
  const t = await r.text();
  try { return JSON.parse(t); } catch (e) { return {ok: false, error: 'Sunucu yanıtı okunamadı: ' + t.slice(0, 200)}; }
}
function lock(v) { document.getElementById('b1').disabled = v; document.getElementById('b2').disabled = v; }
async function runBasic() {
  lock(true);
  log('Etkinlikler alınıyor…');
  let r = await call({step: 'events'});
  log((r.ok ? '✓ ' : '✗ ') + (r.msg || r.error));
  if (r.ok) {
    log('Takımlar alınıyor…');
    r = await call({step: 'teams'});
    if (!r.ok) { log('✗ ' + r.error); }
    else {
      log('✓ Turnuva kayıtlarından ' + r.teams + ' takım alındı. Bölge sayfasında ' + r.region_count + ' takım var.');
      (r.warn || []).forEach(function (w) { log('! ' + w); });
      for (const n of r.missing) {
        const x = await call({step: 'team_one', team: n});
        log((x.ok ? '✓ ' : '✗ ') + '#' + n + ' ' + (x.msg || x.error || ''));
      }
      log('Bitti.');
    }
  }
  lock(false);
}
async function runHistory() {
  lock(true);
  const l = await call({step: 'list'});
  if (!l.ok || !l.teams.length) { log('Önce 1. adımı çalıştırın.'); lock(false); return; }
  for (let i = 0; i < l.teams.length; i++) {
    const r = await call({step: 'history', team: l.teams[i]});
    log('[' + (i + 1) + '/' + l.teams.length + '] #' + l.teams[i] + ': ' + (r.ok ? r.rows + ' kayıt' : '✗ ' + r.error));
    (r.warn || []).forEach(function (w) { log('    ! ' + w); });
  }
  log('Geçmiş senkronu bitti.');
  lock(false);
}
async function addOne() {
  const n = document.getElementById('one').value;
  if (!n) return;
  const x = await call({step: 'team_one', team: n});
  log((x.ok ? '✓ ' : '✗ ') + '#' + n + ' ' + (x.msg || x.error || ''));
}
</script>
<?php panel_foot();