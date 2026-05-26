/* details.js — right drawer for "Get Details" */
(function () {
  function escape(s) { return UI.escapeHtml(s); }

  function fmt(v, fb = '—') { return (v === null || v === undefined || v === '') ? fb : escape(String(v)); }

  async function open(leadId) {
    if (!leadId) return;
    UI.drawer(true);
    const body = document.getElementById('details-body');
    body.innerHTML = `<div class="space-y-3">${
      Array.from({ length: 5 }).map(() => '<div class="skeleton h-16 rounded-xl"></div>').join('')
    }</div>`;
    try {
      const r = await API.get('/get_lead_details.php', { lead_id: leadId });
      render(r);
    } catch (e) {
      body.innerHTML = `<div class="text-sm text-red-600">Failed to load details.</div>`;
    }
  }

  function render(r) {
    const l = r.lead;
    const c = r.counts || {};
    const tags = (l.tags || []).map(t => `<span class="tag">${escape(t)}<span class="x" data-remove-tag="${escape(t)}">×</span></span>`).join('');
    const last = r.last_message ? `<div class="text-xs text-ink-500">Last message: ${escape((r.last_message.message_text || '').slice(0, 80))}…</div>` : '';

    const ai = inferAiInsight(l);

    const html = `
      <div class="flex items-start gap-3">
        <div class="lead-avatar !w-12 !h-12 !text-base">${escape((l.business_name || '?').charAt(0).toUpperCase())}</div>
        <div class="flex-1 min-w-0">
          <div class="text-base font-semibold tracking-tight">${escape(l.business_name)}</div>
          <div class="text-[12px] text-ink-500">${escape(l.phone_display)}</div>
          <div class="flex items-center gap-1.5 mt-2 flex-wrap">
            <span class="badge status-${escape(l.outreach_status)}">${escape(l.outreach_status)}</span>
            <span class="badge status-${escape(l.whatsapp_status)}">${escape(l.whatsapp_status)}</span>
            ${l.pitch_type === 'type_a' ? '<span class="badge type-a">has site</span>' : l.pitch_type === 'type_b' ? '<span class="badge type-b">no site</span>' : ''}
          </div>
        </div>
      </div>

      <div class="detail-section">
        <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 mb-2">Business Info</div>
        <div class="grid grid-cols-2 gap-y-3">
          <div><div class="detail-label">Locality</div><div class="detail-value">${fmt(l.locality)}</div></div>
          <div><div class="detail-label">City</div><div class="detail-value">${fmt(l.city)}</div></div>
          <div><div class="detail-label">State</div><div class="detail-value">${fmt(l.state)}</div></div>
          <div><div class="detail-label">Source</div><div class="detail-value">${fmt(l.source)}</div></div>
          <div class="col-span-2"><div class="detail-label">Address</div><div class="detail-value">${fmt(l.address)}</div></div>
          <div><div class="detail-label">Rating</div><div class="detail-value">${fmt(l.rating)}</div></div>
          <div><div class="detail-label">Reviews</div><div class="detail-value">${fmt(l.review_count)}</div></div>
          <div class="col-span-2">
            <div class="detail-label">Website</div>
            <div class="detail-value">
              ${l.website_url ? `<a class="text-brand-600 hover:underline break-all" target="_blank" rel="noopener" href="${escape(l.website_url)}">${escape(l.website_url)}</a>` : '—'}
              <span class="ml-1 badge ${l.website_status === 'has_website' ? 'type-a' : l.website_status === 'no_website' ? 'type-b' : ''}">${fmt(l.website_status)}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="detail-section">
        <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 mb-2">Outreach</div>
        <div class="grid grid-cols-2 gap-y-3">
          <div><div class="detail-label">Pitch type</div><div class="detail-value">${fmt(l.pitch_type)}</div></div>
          <div><div class="detail-label">Language</div><div class="detail-value">${fmt(l.language_preference)}</div></div>
          <div><div class="detail-label">Sent</div><div class="detail-value">${fmt(c.sent)}</div></div>
          <div><div class="detail-label">Received</div><div class="detail-value">${fmt(c.received)}</div></div>
          <div><div class="detail-label">Last out</div><div class="detail-value">${fmt(c.last_outbound_at)}</div></div>
          <div><div class="detail-label">Last in</div><div class="detail-value">${fmt(c.last_inbound_at)}</div></div>
        </div>
        ${last}
      </div>

      <div class="detail-section">
        <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 mb-2">AI Reasoning</div>
        <div class="text-sm text-ink-700 leading-relaxed">${escape(ai)}</div>
        <button id="btn-preview-ai" class="btn-ghost text-xs mt-3">Preview generated message</button>
      </div>

      <div class="detail-section">
        <div class="flex items-center justify-between mb-2">
          <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500">Tags</div>
          <button id="btn-add-tag" class="text-xs text-brand-600 font-medium">+ Add</button>
        </div>
        <div class="flex flex-wrap gap-1.5">${tags || '<span class="text-xs text-ink-500">No tags</span>'}</div>
      </div>

      <div class="detail-section">
        <div class="flex items-center justify-between mb-2">
          <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500">Notes</div>
          <button id="btn-add-note" class="text-xs text-brand-600 font-medium">+ Add note</button>
        </div>
        <div class="text-xs text-ink-700 whitespace-pre-wrap">${fmt(l.notes, 'No notes yet.')}</div>
      </div>

      <div class="detail-section">
        <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 mb-2">Activity Timeline</div>
        <ul class="space-y-2">
          ${(r.activity || []).map(a => `
            <li class="flex gap-2 text-xs">
              <span class="w-1.5 h-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
              <div>
                <div class="font-medium">${escape(a.action.replace(/_/g, ' '))}</div>
                <div class="text-ink-500">${escape((a.description || '').slice(0, 120))}</div>
                <div class="text-ink-500 text-[10.5px]">${escape(a.created_at)} · ${escape(a.actor)}</div>
              </div>
            </li>
          `).join('') || '<li class="text-xs text-ink-500">No activity yet.</li>'}
        </ul>
      </div>

      <div class="detail-section">
        <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 mb-2">Actions</div>
        <div class="flex flex-wrap gap-2">
          <button class="btn-ghost text-xs" data-action="validate-lead" data-id="${l.id}">Re-validate WA</button>
          <button class="btn-ghost text-xs" data-action="send-first" data-id="${l.id}">Send first outreach</button>
          <button class="btn-ghost text-xs" data-action="toggle-pin" data-id="${l.id}">${l.is_pinned ? 'Unpin' : 'Pin'}</button>
        </div>
      </div>
    `;
    document.getElementById('details-body').innerHTML = html;
    bindActions(l);
  }

  function inferAiInsight(l) {
    const parts = [];
    if (l.pitch_type === 'type_a') {
      parts.push('Lead has a website — pitch angle is optimization, AI/CRM/automation, conversion improvements.');
    } else if (l.pitch_type === 'type_b') {
      parts.push('Lead has NO website — pitch angle is digital presence (landing page / business website / mobile-first site).');
    } else {
      parts.push('Pitch angle: general digital growth.');
    }
    parts.push(`Language preference: ${l.language_preference}.`);
    if (l.rating && l.rating >= 4) parts.push(`Trust signal: rating ${l.rating}` + (l.review_count ? ` over ${l.review_count} reviews.` : '.'));
    parts.push('Outreach is single-message + manual continuation (no chatbot).');
    return parts.join(' ');
  }

  function bindActions(l) {
    document.getElementById('btn-add-note')?.addEventListener('click', async () => {
      const note = prompt('Add a note for this lead:');
      if (!note) return;
      try {
        await API.post('/add_note.php', { lead_id: l.id, note });
        UI.toast('Note added');
        open(l.id);
      } catch (e) { UI.toast('Failed', { kind: 'error' }); }
    });
    document.getElementById('btn-add-tag')?.addEventListener('click', async () => {
      const tag = prompt('Tag name:');
      if (!tag) return;
      try {
        await API.post('/add_tag.php', { lead_id: l.id, tag, action: 'add' });
        open(l.id);
        LEADS.load(false);
      } catch (e) { UI.toast('Failed', { kind: 'error' }); }
    });
    document.querySelectorAll('[data-remove-tag]').forEach(el => {
      el.addEventListener('click', async () => {
        const tag = el.getAttribute('data-remove-tag');
        try {
          await API.post('/add_tag.php', { lead_id: l.id, tag, action: 'remove' });
          open(l.id);
          LEADS.load(false);
        } catch (e) { UI.toast('Failed', { kind: 'error' }); }
      });
    });
    document.getElementById('btn-preview-ai')?.addEventListener('click', async () => {
      try {
        const r = await API.post('/preview_message.php', { lead_id: l.id });
        UI.modal({
          title: 'Generated Outreach Preview',
          body: `<div class="text-sm whitespace-pre-wrap text-ink-700 max-h-[50vh] overflow-y-auto bg-ink-50 p-3 rounded-lg border border-ink-200">${UI.escapeHtml(r.message)}</div>
                 <div class="text-[11px] text-ink-500 mt-2">Pitch: ${UI.escapeHtml(r.pitch_type)} · Language: ${UI.escapeHtml(r.language)} ${r.used_fallback ? '· (fallback)' : ''}</div>`,
          confirmText: 'Send now',
          cancelText: 'Close',
          onConfirm: async () => {
            try { await API.post('/send_first_outreach.php', { lead_id: l.id }); UI.toast('Queued'); CHAT.openLead(l.id); }
            catch (e) { UI.toast('Failed: ' + (e.message || ''), { kind: 'error' }); }
          }
        });
      } catch (e) { UI.toast('Preview failed', { kind: 'error' }); }
    });

    document.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const action = btn.getAttribute('data-action');
        const id = btn.getAttribute('data-id');
        if (!id) return;
        if (action === 'validate-lead') {
          try { const r = await API.post('/validate_lead.php', { lead_id: id }); UI.toast('Status: ' + r.status); open(id); LEADS.load(false); }
          catch (e) { UI.toast('Failed', { kind: 'error' }); }
        }
        if (action === 'send-first') {
          try { await API.post('/send_first_outreach.php', { lead_id: id }); UI.toast('Queued'); CHAT.openLead(parseInt(id, 10)); open(id); }
          catch (e) { UI.toast('Failed: ' + (e.message || ''), { kind: 'error' }); }
        }
        if (action === 'toggle-pin') {
          try { await API.post('/update_lead.php', { lead_id: id, is_pinned: !l.is_pinned }); open(id); LEADS.load(false); }
          catch (e) { UI.toast('Failed', { kind: 'error' }); }
        }
      });
    });
  }

  function init() {
    const closeBtn = document.getElementById('btn-close-details');
    if (closeBtn) closeBtn.addEventListener('click', () => UI.drawer(false));
  }

  window.DETAILS = { open, init };
})();
