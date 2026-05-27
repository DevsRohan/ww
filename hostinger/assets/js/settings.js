/* settings.js — render + save settings page */
(function () {
  let rows = [];

  function escape(s) { return UI.escapeHtml(s); }

  const groups = [
    { title: 'Brand', keys: ['app_name','app_tagline','owner_brand_name','owner_signature','owner_offerings'] },
    { title: 'Hugging Face Backend', keys: ['node_api_url','node_api_key','socket_url','webhook_secret'] },
    { title: 'AI (Groq)', keys: ['groq_api_key','groq_model'] },
    { title: 'Campaign', keys: ['campaign_min_delay','campaign_max_delay','campaign_daily_limit','campaign_batch_size','campaign_active_hours_start','campaign_active_hours_end'] },
    { title: 'Retry', keys: ['retry_max_attempts','retry_backoff_seconds'] },
    { title: 'Phone', keys: ['whatsapp_country_code'] },
    { title: 'Features', keys: ['feature_dark_mode','feature_notification_sound','feature_logging'] },
  ];

  function inputFor(r) {
    const key = r.setting_key;
    const type = r.setting_type;
    const val = r.setting_value || '';
    if (type === 'bool') {
      return `
        <label class="inline-flex items-center cursor-pointer gap-2">
          <input type="checkbox" data-key="${escape(key)}" data-type="bool" ${(val == 1 || val === '1' || val === true) ? 'checked' : ''} class="sr-only peer"/>
          <span class="w-9 h-5 rounded-full bg-ink-200 relative peer-checked:bg-brand-500 transition">
            <span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow-soft transition peer-checked:translate-x-4"></span>
          </span>
          <span class="text-xs text-ink-500">${(val == 1 || val === '1') ? 'On' : 'Off'}</span>
        </label>`;
    }
    if (type === 'int') {
      return `<input type="number" data-key="${escape(key)}" data-type="int" value="${escape(val)}" class="input text-sm w-40"/>`;
    }
    if (type === 'secret') {
      const len = r.secret_length || 0;
      const ph = len > 0 ? `${val} (${len} chars — leave blank to keep)` : 'unset';
      return `<input type="password" data-key="${escape(key)}" data-type="secret" placeholder="${escape(ph)}" class="input text-sm" autocomplete="new-password"/>`;
    }
    return `<input type="text" data-key="${escape(key)}" data-type="${escape(type)}" value="${escape(val)}" class="input text-sm"/>`;
  }

  function render() {
    const host = document.getElementById('settings-form-host');
    if (!host) return;
    const map = {};
    rows.forEach(r => { map[r.setting_key] = r; });

    host.innerHTML = groups.map(g => `
      <div class="bg-white border border-ink-200 rounded-xl2 shadow-soft">
        <div class="px-5 py-3 border-b border-ink-200">
          <h3 class="text-sm font-semibold tracking-tight">${escape(g.title)}</h3>
        </div>
        <div class="p-5 space-y-4">
          ${g.keys.map(k => {
            const r = map[k];
            if (!r) return '';
            return `
              <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
                <div class="md:w-64 shrink-0">
                  <div class="text-sm font-medium">${escape(k)}</div>
                  <div class="text-[11px] text-ink-500">${escape(r.description || '')}</div>
                </div>
                <div class="flex-1 max-w-md">${inputFor(r)}</div>
              </div>`;
          }).join('')}
        </div>
      </div>
    `).join('');
  }

  async function load() {
    document.getElementById('settings-status').textContent = 'Loading…';
    try {
      const r = await API.get('/settings.php');
      rows = r.settings || [];
      render();
      document.getElementById('settings-status').textContent = '';
    } catch (e) {
      document.getElementById('settings-status').textContent = 'Failed to load.';
    }
  }

  async function save() {
    const updates = {};
    document.querySelectorAll('#settings-form-host [data-key]').forEach(el => {
      const k = el.getAttribute('data-key');
      const t = el.getAttribute('data-type');
      let v;
      if (el.type === 'checkbox') v = el.checked ? 1 : 0;
      else v = el.value;
      // Skip empty secrets (preserve existing)
      if (t === 'secret' && (!v || v === '')) return;
      updates[k] = v;
    });
    document.getElementById('settings-status').textContent = 'Saving…';
    try {
      const r = await API.post('/update_settings.php', { updates });
      UI.toast(`Saved ${r.applied} setting(s)`);
      document.getElementById('settings-status').textContent = '';
      // Reload after a brief delay so server-side runtime config picks up new values
      setTimeout(load, 400);
    } catch (e) {
      document.getElementById('settings-status').textContent = 'Save failed.';
      UI.toast('Save failed: ' + (e.message || ''), { kind: 'error' });
    }
  }

  function init() {
    document.addEventListener('click', (e) => {
      if (e.target.id === 'settings-save') save();
      if (e.target.id === 'settings-reload') load();
    });
  }

  window.SETTINGS_PAGE = { init, load, save };
})();
