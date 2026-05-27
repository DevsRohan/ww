/* chat.js — Conversation panel */
(function () {
  let currentLead = null;
  let messages = [];
  let loadingLead = false;

  const $ = (id) => document.getElementById(id);

  function escape(s) { return UI.escapeHtml(s); }

  function statusTick(status) {
    const tickIcon = `<svg viewBox="0 0 16 11" fill="none"><path d="M11.071.653a.5.5 0 010 .707L4.354 8.077a.5.5 0 01-.707 0L.929 5.36a.5.5 0 01.707-.707l1.965 1.965L10.364.653a.5.5 0 01.707 0z" fill="currentColor"/></svg>`;
    const dbl = `<svg viewBox="0 0 16 11" fill="none"><path d="M11.071.653a.5.5 0 010 .707L4.354 8.077a.5.5 0 01-.707 0L.929 5.36a.5.5 0 01.707-.707l1.965 1.965L10.364.653a.5.5 0 01.707 0z" fill="currentColor"/><path d="M15.071.653a.5.5 0 010 .707L8.354 8.077a.5.5 0 01-.707 0l-.5-.5a.5.5 0 01.707-.707l.146.146L14.364.653a.5.5 0 01.707 0z" fill="currentColor"/></svg>`;
    if (status === 'sent')      return `<span class="tick sent">${tickIcon}</span>`;
    if (status === 'delivered') return `<span class="tick delivered">${dbl}</span>`;
    if (status === 'read')      return `<span class="tick read">${dbl}</span>`;
    if (status === 'failed')    return `<span class="tick failed">!</span>`;
    if (status === 'queued')    return `<span class="tick sent">…</span>`;
    return '';
  }

  function fmtTime(iso) {
    if (!iso) return '';
    const t = new Date(iso.replace(' ', 'T'));
    if (isNaN(t)) return '';
    return t.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  function fmtDate(iso) {
    if (!iso) return '';
    const t = new Date(iso.replace(' ', 'T'));
    if (isNaN(t)) return '';
    const today = new Date();
    if (t.toDateString() === today.toDateString()) return 'Today';
    const y = new Date(today); y.setDate(y.getDate() - 1);
    if (t.toDateString() === y.toDateString()) return 'Yesterday';
    return t.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function renderMessages() {
    const body = $('chat-body');
    if (!body) return;
    if (!currentLead) {
      $('chat-empty').classList.remove('hidden');
      body.innerHTML = ''; body.appendChild($('chat-empty'));
      return;
    }
    if (!messages.length) {
      body.innerHTML = `
        <div class="h-full flex items-center justify-center text-center">
          <div class="max-w-xs">
            <div class="ai-chip mb-3 mx-auto">
              <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8z"/></svg>
              No messages yet
            </div>
            <p class="text-xs text-ink-500">Send the first AI-personalized outreach or write a manual message below.</p>
            <button id="btn-first-outreach" class="btn-primary text-xs mt-3">Generate first outreach</button>
          </div>
        </div>`;
      return;
    }

    let lastDateKey = null;
    const html = messages.map(m => {
      const dateKey = (m.timestamp || '').slice(0, 10);
      let sep = '';
      if (dateKey !== lastDateKey) {
        sep = `<div class="date-sep">${escape(fmtDate(m.timestamp))}</div>`;
        lastDateKey = dateKey;
      }
      const direction = m.direction === 'outbound' ? 'outbound' : 'inbound';
      return sep + `
        <div class="msg ${direction}" data-msg-id="${m.id}">
          <div>
            <div class="msg-bubble">${escape(m.message_text)}</div>
            <div class="msg-meta">
              <span>${escape(fmtTime(m.timestamp))}</span>
              ${direction === 'outbound' ? statusTick(m.status) : ''}
              ${m.is_first_outreach ? '<span class="ai-chip" style="padding:1px 6px;font-size:9.5px">AI</span>' : ''}
            </div>
          </div>
        </div>`;
    }).join('');
    body.innerHTML = html;
    body.scrollTop = body.scrollHeight;
  }

  async function openLead(id) {
    // Prevent race condition — if already loading, abort silently
    if (loadingLead) return;
    loadingLead = true;
    try {
      const r = await API.get('/get_messages.php', { lead_id: id });
      currentLead = r.lead;
      messages = r.messages || [];

      $('chat-empty').classList.add('hidden');
      $('chat-title').textContent = currentLead.business_name;
      $('chat-subtitle').textContent = `${currentLead.phone_display} • ${currentLead.outreach_status}`;
      $('chat-avatar').textContent = (currentLead.business_name || '?').charAt(0).toUpperCase();
      $('chat-composer').classList.remove('hidden');
      $('btn-details').classList.remove('hidden');
      $('btn-pin').classList.remove('hidden');

      renderMessages();
      // Mark read
      try { await API.post('/mark_read.php', { lead_id: id }); } catch (_) {}
      STATS.refresh();
    } catch (e) {
      // Retry once automatically instead of showing error
      try {
        const r = await API.get('/get_messages.php', { lead_id: id });
        currentLead = r.lead;
        messages = r.messages || [];
        $('chat-empty').classList.add('hidden');
        $('chat-title').textContent = currentLead.business_name;
        $('chat-subtitle').textContent = `${currentLead.phone_display} • ${currentLead.outreach_status}`;
        $('chat-avatar').textContent = (currentLead.business_name || '?').charAt(0).toUpperCase();
        $('chat-composer').classList.remove('hidden');
        $('btn-details').classList.remove('hidden');
        $('btn-pin').classList.remove('hidden');
        renderMessages();
      } catch (e2) {
        UI.toast('Failed to load chat — try again', { kind: 'error' });
      }
    } finally {
      loadingLead = false;
    }
  }

  function appendMessage(m) {
    if (!currentLead) return;
    if (m.lead_id && currentLead.id !== m.lead_id) return;
    if (m.wa_message_id && messages.some(x => x.wa_message_id === m.wa_message_id)) return;
    messages.push(m);
    renderMessages();
  }

  function updateStatus(waId, status) {
    const idx = messages.findIndex(m => m.wa_message_id === waId);
    if (idx >= 0) {
      messages[idx].status = status;
      renderMessages();
    }
  }

  async function sendManual() {
    const ta = $('composer-input');
    const text = (ta.value || '').trim();
    if (!text || !currentLead) return;
    ta.disabled = true;
    try {
      const r = await API.post('/send_manual.php', { lead_id: currentLead.id, message: text });
      ta.value = '';
      ta.style.height = '42px';
      const optimistic = {
        id: r.message_id,
        lead_id: currentLead.id,
        direction: 'outbound',
        message_text: text,
        status: r.status || 'sent',
        wa_message_id: r.wa_message_id,
        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' '),
      };
      messages.push(optimistic);
      renderMessages();
    } catch (e) {
      UI.toast('Send failed: ' + (e.message || ''), { kind: 'error' });
    } finally {
      ta.disabled = false;
      ta.focus();
    }
  }

  async function sendFirstOutreach() {
    if (!currentLead) return;
    try {
      const r = await API.post('/send_first_outreach.php', { lead_id: currentLead.id });
      UI.toast('Queued first outreach (engine will send within delay window)');
      // Reload chat to show queued message
      openLead(currentLead.id);
    } catch (e) {
      UI.toast('Failed: ' + (e.message || ''), { kind: 'error' });
    }
  }

  async function togglePin() {
    if (!currentLead) return;
    try {
      const r = await API.post('/update_lead.php', { lead_id: currentLead.id, is_pinned: !currentLead.is_pinned });
      currentLead.is_pinned = !currentLead.is_pinned;
      UI.toast(currentLead.is_pinned ? 'Pinned' : 'Unpinned');
      LEADS.load(false);
    } catch (e) { UI.toast('Pin failed', { kind: 'error' }); }
  }

  function init() {
    const form = $('composer-form');
    if (form) form.addEventListener('submit', (e) => { e.preventDefault(); sendManual(); });
    const ta = $('composer-input');
    if (ta) {
      ta.addEventListener('input', () => {
        ta.style.height = '42px';
        ta.style.height = Math.min(160, ta.scrollHeight) + 'px';
      });
      ta.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendManual(); }
      });
    }
    document.addEventListener('click', (e) => {
      if (e.target.closest('#btn-first-outreach')) sendFirstOutreach();
      if (e.target.closest('#btn-pin')) togglePin();
      if (e.target.closest('#btn-details')) DETAILS.open(currentLead?.id);
    });

    SOCK.on('message:inbound', (p) => {
      if (!p) return;
      // Match by phone number (strip non-digits for comparison)
      const fromDigits = (p.from || '').replace(/\D/g, '');
      if (currentLead && currentLead.phone_e164 === fromDigits) {
        appendMessage({
          id: 'tmp_' + Date.now(),
          lead_id: currentLead.id,
          direction: 'inbound',
          message_text: p.body || '',
          status: 'received',
          wa_message_id: p.wa_message_id,
          timestamp: new Date(p.timestamp || Date.now()).toISOString().slice(0, 19).replace('T', ' '),
        });
        // Mark read since user is viewing
        API.post('/mark_read.php', { lead_id: currentLead.id }).catch(() => {});
      }
    });
    SOCK.on('message:outbound', (p) => {
      if (!p || !currentLead) return;
      const toDigits = (p.to || '').replace(/\D/g, '');
      if (currentLead.phone_e164 === toDigits) {
        appendMessage({
          id: 'tmp_' + Date.now(),
          lead_id: currentLead.id,
          direction: 'outbound',
          message_text: p.body || p.preview || '',
          status: p.status || 'sent',
          wa_message_id: p.wa_message_id,
          timestamp: new Date(p.timestamp || Date.now()).toISOString().slice(0, 19).replace('T', ' '),
        });
      }
    });
    SOCK.on('message:status', (p) => {
      if (p && p.wa_message_id && p.status) updateStatus(p.wa_message_id, p.status);
    });
  }

  window.CHAT = { init, openLead, appendMessage };
})();
