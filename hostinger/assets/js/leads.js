/* leads.js — Lead list rendering, filters, search, selection */
(function () {
  const listEl = () => document.getElementById('lead-list');
  const countEl = () => document.getElementById('lead-count');
  const loadMoreEl = () => document.getElementById('load-more');

  let state = {
    items: [],
    total: 0,
    offset: 0,
    limit: 60,
    filter: 'all',
    q: '',
    selectedId: null,
    loading: false,
  };

  function escape(s) { return UI.escapeHtml(s); }

  function avatarLetter(name) {
    return (name || '?').trim().charAt(0).toUpperCase() || '?';
  }

  function renderRow(l) {
    const tags = (l.tags || []).slice(0, 2).map(t => `<span class="tag">${escape(t)}</span>`).join('');
    const unread = (l.unread_count | 0) > 0;
    const subStatus = l.outreach_status || 'new';
    const waBadge = l.whatsapp_status === 'valid' ? '' :
      (l.whatsapp_status === 'not_on_whatsapp' ? '<span class="badge status-not_on_whatsapp">no WA</span>' :
       l.whatsapp_status === 'pending' ? '<span class="badge status-pending">pending</span>' :
       '<span class="badge status-invalid">invalid</span>');
    const pitchBadge = l.pitch_type === 'type_a' ? '<span class="badge type-a">site</span>' :
                      l.pitch_type === 'type_b' ? '<span class="badge type-b">no site</span>' : '';
    const last = l.last_inbound_at || l.last_outbound_at || l.created_at;
    const time = timeAgo(last);

    return `
      <div class="lead-row ${unread ? 'unread' : ''} ${state.selectedId === l.id ? 'active' : ''}" data-lead-id="${l.id}">
        <div class="lead-avatar">${escape(avatarLetter(l.business_name))}</div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[13.5px] font-medium truncate">${escape(l.business_name)} ${l.is_pinned ? '<svg class="w-3 h-3 inline -mt-0.5 text-ink-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>' : ''}</span>
            <span class="text-[10.5px] text-ink-500 shrink-0">${escape(time)}</span>
          </div>
          <div class="flex items-center justify-between gap-2 mt-0.5">
            <span class="text-[12px] text-ink-500 truncate">${escape(l.city || '')}${l.city && l.state ? ', ' : ''}${escape(l.state || '')}</span>
            ${unread ? `<span class="badge-counter">${l.unread_count}</span>` : ''}
          </div>
          <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
            <span class="badge status-${escape(subStatus)}">${escape(subStatus)}</span>
            ${waBadge}
            ${pitchBadge}
            ${tags}
          </div>
        </div>
      </div>
    `;
  }

  function timeAgo(iso) {
    if (!iso) return '';
    const t = new Date(iso.replace(' ', 'T'));
    if (isNaN(t)) return '';
    const diff = (Date.now() - t.getTime()) / 1000;
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff/60) + 'm';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    if (diff < 86400*7) return Math.floor(diff/86400) + 'd';
    return t.toLocaleDateString(undefined, { day: '2-digit', month: 'short' });
  }

  function render() {
    const root = listEl(); if (!root) return;
    if (state.loading && state.items.length === 0) {
      root.innerHTML = Array.from({ length: 6 }).map(() => `
        <div class="px-5 py-4 flex gap-3 border-b border-ink-100">
          <div class="skeleton w-9 h-9 rounded-full"></div>
          <div class="flex-1 space-y-2">
            <div class="skeleton h-3 w-2/3"></div>
            <div class="skeleton h-2 w-1/2"></div>
            <div class="skeleton h-2 w-1/3"></div>
          </div>
        </div>`).join('');
      return;
    }
    if (!state.items.length) {
      root.innerHTML = `
        <div class="p-10 text-center text-ink-500 text-sm">
          No leads found.<br/>
          <button data-action="upload-csv" class="text-brand-600 font-medium hover:underline mt-2 inline-block">Upload a CSV to get started</button>
        </div>`;
      return;
    }
    root.innerHTML = state.items.map(renderRow).join('');
    countEl().textContent = state.total;
    if (state.items.length < state.total) {
      loadMoreEl().classList.remove('hidden');
    } else {
      loadMoreEl().classList.add('hidden');
    }
  }

  async function load(append = false) {
    if (state.loading) return;
    state.loading = true;
    render();
    try {
      const params = { limit: state.limit, offset: append ? state.offset : 0, q: state.q };
      if (state.filter && state.filter !== 'all') params.filter = state.filter;
      const r = await API.get('/get_leads.php', params);
      state.total = r.total || 0;
      const rows = (r.rows || []);
      if (append) state.items = state.items.concat(rows);
      else { state.items = rows; state.offset = 0; }
      state.offset += rows.length;
    } catch (e) {
      UI.toast('Failed to load leads', { kind: 'error' });
    } finally {
      state.loading = false;
      render();
    }
  }

  function setFilter(f) {
    state.filter = f;
    document.querySelectorAll('#lead-filters .chip').forEach(b => {
      b.classList.toggle('chip-active', b.getAttribute('data-filter') === f);
    });
    load(false);
  }

  function setSearch(q) {
    state.q = q;
    load(false);
  }

  function selectLead(id) {
    state.selectedId = id;
    render();
    CHAT.openLead(id);
  }

  function bumpLeadByPhone(phone) {
    const idx = state.items.findIndex(l => (l.phone_e164 || '').endsWith(phone) || (l.phone_number || '').includes(phone));
    if (idx >= 0) {
      state.items[idx].unread_count = (state.items[idx].unread_count | 0) + 1;
      state.items[idx].last_inbound_at = new Date().toISOString();
      const item = state.items.splice(idx, 1)[0];
      state.items.unshift(item);
      render();
    } else {
      // unknown phone — refresh
      load(false);
    }
  }

  function init() {
    document.querySelectorAll('#lead-filters .chip').forEach(b => {
      b.addEventListener('click', () => setFilter(b.getAttribute('data-filter')));
    });
    let qTimer = null;
    const search = document.getElementById('lead-search');
    if (search) {
      search.addEventListener('input', () => {
        clearTimeout(qTimer);
        qTimer = setTimeout(() => setSearch(search.value.trim()), 250);
      });
    }
    document.addEventListener('click', (e) => {
      const row = e.target.closest('.lead-row');
      if (row) {
        const id = parseInt(row.getAttribute('data-lead-id'), 10);
        selectLead(id);
      }
      if (e.target.closest('[data-action="refresh-leads"]')) load(false);
      if (e.target.closest('#load-more')) load(true);
    });

    // Live updates
    SOCK.on('message:inbound', (p) => {
      bumpLeadByPhone((p && p.from) || '');
      NOTIFY.notify('New reply', (p && p.body) ? p.body.slice(0, 80) : 'New WhatsApp reply');
      STATS.refresh();
    });
    SOCK.on('lead:replied', () => { STATS.refresh(); });
    SOCK.on('message:status', () => { /* row text updates on next refresh */ });
    SOCK.on('lead:validated', () => { load(false); });

    load(false);
  }

  window.LEADS = { init, load, selectLead, setFilter, setSearch };
})();
