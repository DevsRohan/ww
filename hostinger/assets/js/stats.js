/* stats.js — live KPI counters in sidebar */
(function () {
  let pollHandle = null;

  async function refresh() {
    try {
      const r = await API.get('/get_stats.php');
      apply(r);
    } catch (e) { /* silent */ }
  }

  function apply(r) {
    if (!r) return;
    const map = ['total_leads','valid_leads','invalid_leads','pending_leads','sent_count','replied_count','queued_count','sent_today','unread_total'];
    map.forEach(k => {
      document.querySelectorAll(`[data-stat="${k}"]`).forEach(el => {
        const from = parseInt(el.textContent, 10);
        animateNumber(el, isNaN(from) ? 0 : from, parseInt(r[k] ?? 0, 10));
      });
    });
    const unreadEl = document.querySelector('[data-stat="unread_total"]');
    if (unreadEl) {
      if ((r.unread_total | 0) > 0) unreadEl.classList.remove('hidden');
      else unreadEl.classList.add('hidden');
    }
  }

  function animateNumber(el, from, to) {
    if (from === to) { el.textContent = to.toLocaleString(); return; }
    const dur = 400;
    const start = performance.now();
    function step(t) {
      const p = Math.min(1, (t - start) / dur);
      const v = Math.round(from + (to - from) * p);
      el.textContent = v.toLocaleString();
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function start() {
    refresh();
    if (pollHandle) clearInterval(pollHandle);
    pollHandle = setInterval(refresh, 12000);
  }

  window.STATS = { start, refresh, apply };
})();
