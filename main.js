// DolanJogja — Main JS

// ── Service Worker Registration ──────────
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  });
}

// ── Page Switcher ────────────────────────
function switchPage(src, el) {
  const frame = document.getElementById('content-frame');
  frame.src = src;

  document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');

  // scroll reset
  frame.onload = () => {
    try { frame.contentWindow.scrollTo(0, 0); } catch(e) {}
  };
}

// ── Toast utility ────────────────────────
function showToast(msg) {
  let t = document.getElementById('dj-toast-el');
  if (!t) {
    t = document.createElement('div');
    t.id = 'dj-toast-el';
    t.className = 'dj-toast';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2200);
}
