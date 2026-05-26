/* campaign.js — start/pause campaign actions */
(function () {
  async function start() {
    UI.modal({
      title: 'Start Campaign?',
      body: `
        <p class="text-sm text-ink-700">This will queue all <b>valid</b> leads with status <code>new</code> or <code>failed</code> for first outreach.</p>
        <ul class="text-xs text-ink-500 mt-3 space-y-1 list-disc pl-5">
          <li>Random delay <b>120–300s</b> between sends</li>
          <li>Daily cap protection</li>
          <li>Stops automatically when a lead replies</li>
          <li>You can pause anytime</li>
        </ul>`,
      confirmText: 'Start Campaign',
      onConfirm: async () => {
        try {
          const r = await API.post('/start_campaign.php', {});
          UI.toast(`Campaign started · ${r.queued} leads queued`);
          STATS.refresh();
        } catch (e) { UI.toast('Failed to start: ' + (e.message || ''), { kind: 'error' }); }
      }
    });
  }

  async function pause() {
    try {
      await API.post('/pause_campaign.php', {});
      UI.toast('Campaign paused');
    } catch (e) { UI.toast('Pause failed', { kind: 'error' }); }
  }

  function init() {
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-action="start-campaign"]')) start();
      if (e.target.closest('[data-action="pause-campaign"]')) pause();
    });
  }

  window.CAMPAIGN = { init, start, pause };
})();
