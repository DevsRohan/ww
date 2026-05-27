/* campaign.js — start/pause campaign actions + status indicator */
(function () {
  let campaignRunning = false;

  function updateStatusBanner(running) {
    campaignRunning = running;
    let banner = document.getElementById('campaign-status-banner');
    if (!banner) {
      // Create banner element after Quick Actions section
      const quickActions = document.querySelector('[data-action="pause-campaign"]');
      if (quickActions && quickActions.parentElement) {
        banner = document.createElement('div');
        banner.id = 'campaign-status-banner';
        banner.className = 'mt-3 px-3 py-2 rounded-lg text-xs font-medium text-center';
        quickActions.parentElement.appendChild(banner);
      }
    }
    if (!banner) return;
    if (running) {
      banner.className = 'mt-3 px-3 py-2 rounded-lg text-xs font-medium text-center bg-brand-100 text-brand-700 border border-brand-200';
      banner.innerHTML = '<span class="inline-block w-2 h-2 rounded-full bg-brand-500 animate-pulse mr-1.5"></span>Campaign Running';
    } else {
      banner.className = 'mt-3 px-3 py-2 rounded-lg text-xs font-medium text-center bg-ink-100 text-ink-500 border border-ink-200';
      banner.innerHTML = '<span class="inline-block w-2 h-2 rounded-full bg-ink-400 mr-1.5"></span>Campaign Paused';
    }
  }

  async function checkStatus() {
    try {
      const r = await API.get('/get_logs.php', { source: 'campaign', limit: 1 });
      const last = (r.logs && r.logs[0]) || null;
      if (last) {
        const isRunning = last.message === 'campaign_started' || last.message === 'campaign_tick';
        updateStatusBanner(isRunning);
      }
    } catch (e) { /* silent */ }
  }

  async function start() {
    UI.modal({
      title: 'Start Campaign?',
      body: `
        <p class="text-sm text-ink-700">This will queue all <b>valid</b> leads for first outreach.</p>
        <ul class="text-xs text-ink-500 mt-3 space-y-1 list-disc pl-5">
          <li>Random delay <b>120-300s</b> between sends</li>
          <li>Daily cap protection (60/day)</li>
          <li>Cron sends 5 leads every 2 minutes</li>
          <li>You can pause anytime</li>
        </ul>`,
      confirmText: 'Start Campaign',
      onConfirm: async () => {
        try {
          const r = await API.post('/start_campaign.php', {});
          UI.toast(`Campaign started! ${r.queued} leads queued. Messages will send via cron every 2 min.`);
          updateStatusBanner(true);
          STATS.refresh();
        } catch (e) { UI.toast('Failed to start: ' + (e.message || ''), { kind: 'error' }); }
      }
    });
  }

  async function pause() {
    try {
      await API.post('/pause_campaign.php', {});
      UI.toast('Campaign paused');
      updateStatusBanner(false);
    } catch (e) { UI.toast('Pause failed', { kind: 'error' }); }
  }

  function init() {
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-action="start-campaign"]')) start();
      if (e.target.closest('[data-action="pause-campaign"]')) pause();
    });
    // Check campaign status on load
    setTimeout(checkStatus, 2000);
  }

  window.CAMPAIGN = { init, start, pause, updateStatusBanner };
})();
