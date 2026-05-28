/* validate.js — Validate All pending leads with progress UI */
(function () {
  let running = false;
  let stopped = false;
  let totalToValidate = 0;
  let validated = 0;
  let deleted = 0;
  let failed = 0;

  function showProgress(show) {
    const el = document.getElementById('validate-progress');
    if (el) el.classList.toggle('hidden', !show);
  }

  function updateUI(remaining, detail) {
    const done = totalToValidate - remaining;
    const pct = totalToValidate > 0 ? Math.round((done / totalToValidate) * 100) : 0;

    const bar = document.getElementById('validate-bar');
    const count = document.getElementById('validate-count');
    const status = document.getElementById('validate-status');
    const detailEl = document.getElementById('validate-detail');
    const stopBtn = document.getElementById('validate-stop');

    if (bar) bar.style.width = pct + '%';
    if (count) count.textContent = `${done}/${totalToValidate}`;
    if (status) status.textContent = `Validating... ${pct}%`;
    if (detailEl) detailEl.textContent = detail || `Valid: ${validated} | Deleted: ${deleted} | Failed: ${failed}`;
    if (stopBtn) stopBtn.classList.remove('hidden');
  }

  function finish(message) {
    const status = document.getElementById('validate-status');
    const stopBtn = document.getElementById('validate-stop');
    const bar = document.getElementById('validate-bar');

    if (status) status.textContent = message || 'Done!';
    if (stopBtn) stopBtn.classList.add('hidden');
    if (bar) bar.style.width = '100%';

    running = false;

    // Auto-hide after 5s
    setTimeout(() => {
      showProgress(false);
      // Reset
      const b = document.getElementById('validate-bar');
      if (b) b.style.width = '0%';
    }, 5000);

    // Refresh leads list
    if (window.LEADS) LEADS.load(false);
    if (window.STATS) STATS.refresh();
  }

  async function start() {
    if (running) {
      UI.toast('Validation already in progress', { kind: 'warn' });
      return;
    }

    running = true;
    stopped = false;
    validated = 0;
    deleted = 0;
    failed = 0;

    showProgress(true);

    // First call to get initial count
    try {
      const first = await API.post('/validate_next.php', {});
      if (first.done) {
        finish('All leads already validated!');
        UI.toast('No pending leads to validate');
        return;
      }
      // First one processed
      totalToValidate = (first.remaining || 0) + 1; // remaining + the one we just did
      if (first.status === 'valid') validated++;
      if (first.action === 'deleted') deleted++;
      updateUI(first.remaining, `${first.lead_name} → ${first.status}`);
    } catch (e) {
      // First call failed — show error and stop
      const errData = e.data || {};
      totalToValidate = errData.remaining || 0;
      failed++;
      updateUI(totalToValidate, '');
      finish(`Error: ${e.message}`);
      UI.toast(e.message || 'Validation failed — check backend', { kind: 'error', duration: 5000 });
      return;
    }

    // Loop until done
    while (!stopped) {
      try {
        const r = await API.post('/validate_next.php', {});
        if (r.done) {
          finish(`Done! Valid: ${validated} | Deleted: ${deleted} | Failed: ${failed}`);
          UI.toast(`Validation complete — ${validated} valid, ${deleted} removed`, { kind: 'success' });
          return;
        }
        if (r.status === 'valid') validated++;
        if (r.action === 'deleted') deleted++;
        updateUI(r.remaining, `${r.lead_name} → ${r.status}`);

        // Refresh lead list every 10 validations
        if ((validated + deleted + failed) % 10 === 0) {
          if (window.LEADS) LEADS.load(false);
        }
      } catch (e) {
        failed++;
        const errData = e.data || {};
        updateUI(errData.remaining || 0, `Error: ${e.message}`);

        // If backend is completely down, stop after 3 consecutive failures
        if (failed >= 3 && validated === 0 && deleted === 0) {
          finish(`Stopped — backend unreachable. ${e.message}`);
          UI.toast(e.message || 'Backend not reachable, stopping validation', { kind: 'error', duration: 5000 });
          return;
        }

        // Otherwise wait a bit and retry
        await new Promise(resolve => setTimeout(resolve, 2000));
      }
    }

    // Stopped by user
    finish(`Stopped. Valid: ${validated} | Deleted: ${deleted} | Failed: ${failed}`);
    UI.toast('Validation stopped by user');
  }

  function stop() {
    stopped = true;
  }

  function init() {
    // Bind Validate All button
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-action="validate-all"]')) {
        start();
      }
    });

    // Bind stop button
    const stopBtn = document.getElementById('validate-stop');
    if (stopBtn) stopBtn.addEventListener('click', stop);
  }

  window.VALIDATE = { init, start, stop };
})();
