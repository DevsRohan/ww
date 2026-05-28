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
    if (bar && !message.startsWith('Error') && !message.startsWith('Stopped')) bar.style.width = '100%';

    running = false;

    // Auto-hide after 8s
    setTimeout(() => {
      showProgress(false);
      const b = document.getElementById('validate-bar');
      if (b) b.style.width = '0%';
    }, 8000);

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
    const status = document.getElementById('validate-status');
    if (status) status.textContent = 'Checking WhatsApp engine...';

    // ─── Step 1: Pre-flight engine check ───────────────────────────
    try {
      const check = await API.post('/validate_next.php', { action: 'check_engine' });
      if (!check.engine_ready) {
        finish('Engine not ready');
        UI.toast('WhatsApp engine is not ready', { kind: 'error' });
        running = false;
        return;
      }
      totalToValidate = check.pending_count || 0;
      if (totalToValidate === 0) {
        finish('No pending leads to validate!');
        UI.toast('All leads already validated', { kind: 'success' });
        return;
      }
      if (status) status.textContent = `Starting validation of ${totalToValidate} leads...`;
    } catch (e) {
      // Engine check failed — show descriptive error
      finish('Error: ' + (e.message || 'Cannot reach backend'));
      UI.toast(e.message || 'WhatsApp engine not reachable', { kind: 'error', duration: 8000 });
      running = false;
      return;
    }

    // ─── Step 2: Validate one-by-one ───────────────────────────────
    let consecutiveErrors = 0;

    while (!stopped) {
      try {
        const r = await API.post('/validate_next.php', {});
        consecutiveErrors = 0; // reset on success

        if (r.done) {
          finish(`Done! Valid: ${validated} | Deleted: ${deleted} | Failed: ${failed}`);
          UI.toast(`Validation complete — ${validated} valid, ${deleted} removed`, { kind: 'success' });
          return;
        }
        if (r.status === 'valid') validated++;
        if (r.action === 'deleted') deleted++;
        updateUI(r.remaining, `${r.lead_name} → ${r.status}`);

        // Refresh lead list every 10 validations
        if ((validated + deleted) % 10 === 0) {
          if (window.LEADS) LEADS.load(false);
        }

        // Small delay between calls (1s) to not overwhelm WhatsApp
        await new Promise(resolve => setTimeout(resolve, 1000));

      } catch (e) {
        failed++;
        consecutiveErrors++;
        const errData = e.data || {};
        const errorCode = errData.error_code || '';
        updateUI(errData.remaining || 0, `Error: ${e.message}`);

        // If engine_not_ready or server_error or puppeteer crash — stop immediately with clear message
        if (errorCode === 'engine_not_ready' || errorCode === 'server_error' || errorCode === 'puppeteer_error') {
          finish(`Stopped — ${e.message}`);
          UI.toast(e.message, { kind: 'error', duration: 8000 });
          return;
        }

        // If backend completely down, stop after 3 consecutive failures
        if (consecutiveErrors >= 3) {
          finish(`Stopped after ${consecutiveErrors} errors — ${e.message}`);
          UI.toast(e.message || 'Backend not responding, stopping', { kind: 'error', duration: 8000 });
          return;
        }

        // Wait longer before retry on error
        await new Promise(resolve => setTimeout(resolve, 3000));
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
