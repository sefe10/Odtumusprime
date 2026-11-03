function toggleMobileMenu() {
  document.querySelector('.nav-links').classList.toggle('open');
}

(function () {
  var target = new Date(document.body.dataset.countdown || '').getTime();
  var el = {
    d: document.getElementById('days'), h: document.getElementById('hours'),
    m: document.getElementById('minutes'), s: document.getElementById('seconds')
  };
  function pad(n) { return String(n).padStart(2, '0'); }
  function tick() {
    var diff = target - Date.now();
    if (!el.d || isNaN(diff) || diff <= 0) return;
    el.d.textContent = Math.floor(diff / 864e5);
    el.h.textContent = pad(Math.floor(diff % 864e5 / 36e5));
    el.m.textContent = pad(Math.floor(diff % 36e5 / 6e4));
    el.s.textContent = pad(Math.floor(diff % 6e4 / 1e3));
  }
  setInterval(tick, 1000); tick();

  document.querySelectorAll('.spec-item').forEach(function (item) {
    item.addEventListener('click', function () {
      document.querySelectorAll('.spec-item').forEach(function (i) { i.classList.remove('active'); });
      item.classList.add('active');
    });
  });
})();