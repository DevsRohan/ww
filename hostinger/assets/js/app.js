/* app.js — bootstrap + simple SPA navigation for sub-pages */
(function () {
  function setActiveNav(name) {
    document.querySelectorAll('.nav-item').forEach(el => {
      el.classList.toggle('active', el.getAttribute('data-nav') === name);
    });
  }

  async function loadFragment(page) {
    // Replace chat pane content with the loaded page
    const container = document.getElementById('chat-pane');
    if (!container) return;
    try {
      const res = await fetch('pages/' + page + '.php', { credentials: 'same-origin' });
      const html = await res.text();
      container.innerHTML = html;
      // Special: settings page needs JS init
      if (page === 'settings') {
        SETTINGS_PAGE.init();
        SETTINGS_PAGE.load();
      } else if (page === 'logs') {
        initLogsPage();
      } else if (page === 'leads') {
        initLeadsPage();
      }
    } catch (e) {
      container.innerHTML = '<div class="p-10 text-center text-red-600 text-sm">Failed to load page.</div>';
    }
  }

  function showInbox() {
    location.reload(); // Simplest: full reload to dashboard.php for inbox
  }

  function bindNav() {
    document.addEventListener('click', (e) => {
      const a = e.target.closest('[data-nav]');
      if (!a) return;
      e.preventDefault();
      const nav = a.getAttribute('data-nav');
      setActiveNav(nav);
      if (nav === 'inbox') showInbox();
      else loadFragment(nav);
    });
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-action="open-qr"]')) {
        const url = (window.__APP__ && window.__APP__.socketUrl) || '';
        if (url) window.open(url.replace(/\/$/, '') + '/qr', '_blank');
      }
    });
  }

  function initLogsPage() {
    let timer = null;
    async function refresh() {
      const level = document.getElementById('logs-level')?.value || '';
      const source = document.getElementById('logs-source')?.value || '';
      try {
        const r = await API.get('/get_logs.php', { level, source, limit: 100 });
        const tbody = document.getElementById('logs-tbody');
        tbody.innerHTML = (r.logs || []).map(l => `
          <div class="px-5 py-2 grid grid-cols-[80px_70px_120px_1fr] gap-3 hover:bg-ink-50">
            <span class="text-xs text-ink-500">${UI.escapeHtml(l.created_at)}</span>
            <span class="text-xs font-medium ${l.level === 'error' ? 'text-red-600' : l.level === 'warn' ? 'text-amber-600' : l.level === 'info' ? 'text-brand-600' : 'text-ink-500'}">${UI.escapeHtml(l.level)}</span>
            <span class="text-xs text-ink-700">${UI.escapeHtml(l.source)}</span>
            <span class="text-xs">${UI.escapeHtml(l.message)} ${l.context ? '<span class="text-ink-500">' + UI.escapeHtml(JSON.stringify(l.context)) + '</span>' : ''}</span>
          </div>
        `).join('') || '<div class="p-8 text-center text-ink-500 text-sm">No logs.</div>';
      } catch (e) {}
    }
    document.getElementById('logs-refresh')?.addEventListener('click', refresh);
    document.getElementById('logs-level')?.addEventListener('change', refresh);
    document.getElementById('logs-source')?.addEventListener('change', refresh);
    refresh();
    if (timer) clearInterval(timer);
    timer = setInterval(refresh, 10000);
  }

  function initLeadsPage() {
    const tbody = document.getElementById('leads-page-tbody');
    const totalEl = document.getElementById('leads-page-total');
    let qTimer = null;
    async function refresh() {
      const q = document.getElementById('leads-page-q')?.value || '';
      const outreach = document.getElementById('leads-page-status')?.value || '';
      const wa = document.getElementById('leads-page-wa')?.value || '';
      const params = { q, limit: 200 };
      if (outreach) params.outreach_status = outreach;
      if (wa) params.whatsapp_status = wa;
      try {
        const r = await API.get('/get_leads.php', params);
        totalEl.textContent = r.total;
        tbody.innerHTML = (r.rows || []).map(l => `
          <tr class="border-t border-ink-200 hover:bg-ink-50">
            <td class="px-5 py-3 font-medium">${UI.escapeHtml(l.business_name)}</td>
            <td class="px-5 py-3 text-ink-500">${UI.escapeHtml((l.locality || '') + (l.locality && (l.city || l.state) ? ', ' : '') + (l.city || '') + (l.city && l.state ? ', ' : '') + (l.state || ''))}</td>
            <td class="px-5 py-3 font-mono text-xs">${UI.escapeHtml(l.phone_display)}</td>
            <td class="px-5 py-3"><span class="badge status-${UI.escapeHtml(l.whatsapp_status)}">${UI.escapeHtml(l.whatsapp_status)}</span></td>
            <td class="px-5 py-3"><span class="badge status-${UI.escapeHtml(l.outreach_status)}">${UI.escapeHtml(l.outreach_status)}</span></td>
            <td class="px-5 py-3"><span class="badge ${l.pitch_type === 'type_a' ? 'type-a' : l.pitch_type === 'type_b' ? 'type-b' : ''}">${UI.escapeHtml(l.pitch_type)}</span></td>
            <td class="px-5 py-3 text-right"><button class="btn-ghost text-xs" data-open-lead="${l.id}">Open</button></td>
          </tr>
        `).join('') || '<tr><td colspan="7" class="px-5 py-10 text-center text-ink-500">No leads found.</td></tr>';
      } catch (e) {}
    }
    document.getElementById('leads-page-q')?.addEventListener('input', () => { clearTimeout(qTimer); qTimer = setTimeout(refresh, 250); });
    document.getElementById('leads-page-status')?.addEventListener('change', refresh);
    document.getElementById('leads-page-wa')?.addEventListener('change', refresh);
    document.addEventListener('click', (e) => {
      const b = e.target.closest('[data-open-lead]');
      if (b) {
        const id = parseInt(b.getAttribute('data-open-lead'), 10);
        location.href = 'dashboard.php#lead-' + id;
      }
    });
    refresh();
  }

  function bootSocketStatus() {
    const cached = (window.__APP__ && window.__APP__.cachedEngine) || null;
    if (cached) {
      // initial pill
    }
    SOCK.on('socket:connected', () => UI.toast('Realtime connected'));
    SOCK.on('socket:disconnected', () => UI.toast('Realtime disconnected — reconnecting…', { kind: 'warn' }));
    SOCK.on('engine:ready', () => UI.toast('WhatsApp engine ready'));
    SOCK.on('engine:disconnected', () => UI.toast('WhatsApp engine disconnected', { kind: 'warn' }));
    SOCK.on('engine:qr', () => UI.toast('Scan QR to connect WhatsApp', { kind: 'warn' }));
  }

  document.addEventListener('DOMContentLoaded', () => {
    bindNav();
    SOCK.connect();
    NOTIFY.ensurePermission().catch(() => {});
    LEADS.init();
    CHAT.init();
    DETAILS.init();
    UPLOAD.init();
    CAMPAIGN.init();
    STATS.start();
    bootSocketStatus();

    document.getElementById('engine-refresh')?.addEventListener('click', async () => {
      try { const r = await API.get('/engine_status.php'); UI.toast('State: ' + (r.status?.status?.state || 'unknown')); }
      catch (e) { UI.toast('Failed', { kind: 'error' }); }
    });

    // Deep-link to a lead via #lead-<id>
    if (location.hash.startsWith('#lead-')) {
      const id = parseInt(location.hash.slice(6), 10);
      if (id) setTimeout(() => LEADS.selectLead(id), 400);
    }
  });
})();
