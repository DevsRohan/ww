/* campaign.js — start/pause campaign actions + status indicator + status sync */
(function () {
  let campaignRunning = false;
  let syncInterval = null;

  function updateStatusBanner(running) {
    campaignRunning = running;
    let banner = document.getElementById('campaign-status-banner');
    if (!banner) {
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
      startSync();
    } else {
      banner.className = 'mt-3 px-3 py-2 rounded-lg text-xs font-medium text-center bg-ink-100 text-ink-500 border border-ink-200';
      banner.innerHTML = '<span class="inline-block w-2 h-2 rounded-full bg-ink-400 mr-1.5"></span>Campaign Paused';
      stopSync();
    }
  }

  // Sync function — polls Node queue size and marks sent messages
  async function doSync() {
    try {
      const r = await API.post('/refresh_sync.php', {});
      if (r.updated_to_sent > 0) {
        if (window.LEADS) LEADS.load(false);
        if (window.STATS) STATS.refresh();
      }
      // If queue empty and nothing left queued, campaign is done
      if (r.queue_size === 0 && r.total_queued === 0 && campaignRunning) {
        updateStatusBanner(false);
        UI.toast('Campaign complete — all messages sent!', { kind: 'success' });
      }
    } catch (e) { /* silent */ }
  }

  function startSync() {
    if (syncInterval) return;
    // Poll every 15 seconds
    syncInterval = setInterval(doSync, 15000);
    // First sync after 10s (give Node time to send first message)
    setTimeout(doSync, 10000);
  }

  function stopSync() {
    if (syncInterval) { clearInterval(syncInterval); syncInterval = null; }
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
          <li>Messages sent one-by-one with <b>2-5 min</b> gap</li>
          <li>Daily cap protection (60/day)</li>
          <li>Status updates every 15 seconds automatically</li>
          <li>You can pause anytime</li>
        </ul>`,
      confirmText: 'Start Campaign',
      onConfirm: async () => {
        try {
          const r = await API.post('/start_campaign.php', {});
          UI.toast(`Campaign started! ${r.queued} leads queued, ${r.sent_now} sent to engine.`);
          updateStatusBanner(true);
          STATS.refresh();
          if (window.LEADS) LEADS.load(false);
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
