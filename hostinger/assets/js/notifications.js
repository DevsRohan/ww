/* notifications.js — sound + browser notifications for inbound messages */
(function () {
  const features = (window.__APP__ && window.__APP__.features) || {};
  let audio = null;

  function ensureAudio() {
    if (audio) return audio;
    audio = new Audio('assets/sounds/notification.mp3');
    audio.volume = 0.55;
    return audio;
  }

  function ping() {
    if (!features.notification_sound) return;
    try { ensureAudio().play().catch(() => {}); } catch (_) {}
  }

  async function ensurePermission() {
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied') return false;
    try {
      const r = await Notification.requestPermission();
      return r === 'granted';
    } catch (_) { return false; }
  }

  async function notify(title, body, opts = {}) {
    ping();
    if (document.visibilityState === 'visible') return; // don't double-notify
    if (await ensurePermission()) {
      try {
        const n = new Notification(title, { body, icon: 'assets/img/logo.svg', tag: opts.tag || 'wcrm' });
        n.onclick = () => { window.focus(); n.close(); };
      } catch (_) {}
    }
  }

  window.NOTIFY = { ping, notify, ensurePermission };
})();
