<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();
?>
<div class="p-8 max-w-5xl mx-auto" id="diag-root">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-[22px] font-semibold tracking-tight">Diagnostic</h1>
      <p class="text-sm text-ink-500 mt-1">PHP-side health snapshot. Compare lengths/values with the HF Space <code class="bg-ink-100 px-1.5 py-0.5 rounded text-[11px]">/debug</code> page.</p>
    </div>
    <div class="flex gap-2">
      <button id="diag-refresh" class="btn-ghost text-sm">Refresh</button>
      <button id="diag-validate" class="btn-primary text-sm">Validate ALL pending leads</button>
    </div>
  </div>
  <div id="diag-body" class="space-y-4 text-sm">
    <div class="text-ink-500">Loading…</div>
  </div>
</div>
<script>
(function() {
  const body = document.getElementById('diag-body');
  function esc(s){ return UI.escapeHtml(String(s == null ? '' : s)); }
  function pill(state) {
    const map = { healthy: 'bg-brand-50 text-brand-700', engine_not_ready: 'bg-amber-50 text-amber-700', engine_unreachable: 'bg-red-50 text-red-700', misconfigured: 'bg-red-50 text-red-700' };
    return `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold ${map[state] || 'bg-ink-100 text-ink-700'}">${esc(state)}</span>`;
  }
  function checkRow(k, v) {
    const ok = v === true; const bad = v === false;
    const icon = ok ? '✓' : bad ? '✗' : '·';
    const cls = ok ? 'text-brand-600' : bad ? 'text-red-600' : 'text-ink-500';
    return `<div class="flex items-center gap-2 py-1.5 border-b border-ink-100 last:border-0 text-[12.5px]">
      <span class="${cls} font-bold w-4">${icon}</span>
      <span class="font-mono text-ink-700">${esc(k)}</span>
      <span class="ml-auto font-mono text-ink-500">${esc(String(v))}</span>
    </div>`;
  }
  function kv(k, v) {
    return `<div class="flex justify-between py-1.5 border-b border-ink-100 last:border-0 text-[12.5px]">
      <span class="text-ink-500">${esc(k)}</span>
      <span class="font-mono text-ink-900 max-w-[60%] truncate text-right">${esc(typeof v === 'object' ? JSON.stringify(v) : String(v))}</span>
    </div>`;
  }
  function settingRow(k, v) {
    if (v && typeof v === 'object' && 'set' in v) {
      const tag = v.set
        ? `<span class="font-mono text-ink-700">${esc(v.preview)}</span> <span class="text-[10.5px] text-ink-500 ml-2">(${v.length} chars)</span>`
        : `<span class="text-red-600 text-[12px]">NOT SET</span>`;
      return `<div class="flex justify-between items-center py-1.5 border-b border-ink-100 last:border-0 text-[12.5px]">
        <span class="text-ink-500 font-mono">${esc(k)}</span>
        <span class="text-right">${tag}</span>
      </div>`;
    }
    return kv(k, v);
  }
  async function load() {
    body.innerHTML = '<div class="text-ink-500">Loading…</div>';
    try {
      const r = await API.get('/diagnostic.php');
      render(r);
    } catch (e) {
      body.innerHTML = `<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">Failed: ${esc(e.message)}</div>`;
    }
  }
  function render(r) {
    const sections = [];
    sections.push(`<div class="bg-white border border-ink-200 rounded-xl shadow-soft p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500">Overall</h2>
        ${pill(r.overall)}
      </div>
      <div class="grid grid-cols-2 gap-x-6">
        ${Object.entries(r.checks).map(([k, v]) => checkRow(k, v)).join('')}
      </div>
    </div>`);

    sections.push(`<div class="bg-white border border-ink-200 rounded-xl shadow-soft p-5">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500 mb-3">Settings (lengths verifiable against HF /debug)</h2>
      ${Object.entries(r.settings).map(([k, v]) => settingRow(k, v)).join('')}
    </div>`);

    sections.push(`<div class="bg-white border border-ink-200 rounded-xl shadow-soft p-5">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500 mb-3">HF Engine (live ping)</h2>
      ${Object.entries(r.engine).filter(([k, v]) => typeof v !== 'object' || v === null).map(([k, v]) => kv(k, v == null ? '—' : v)).join('')}
      ${r.engine.whatsapp ? `<div class="mt-3 pt-3 border-t border-ink-200">
        <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-2">WhatsApp</div>
        ${Object.entries(r.engine.whatsapp).filter(([k, v]) => typeof v !== 'object' || v === null).map(([k, v]) => kv(k, v == null ? '—' : v)).join('')}
      </div>` : ''}
    </div>`);

    sections.push(`<div class="bg-white border border-ink-200 rounded-xl shadow-soft p-5">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500 mb-3">Lead Breakdown</h2>
      <div class="grid grid-cols-2 gap-6">
        <div>
          <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-1">By WhatsApp Status</div>
          ${Object.entries(r.leads_by_whatsapp_status).map(([k, v]) => kv(k, v)).join('') || '<div class="text-ink-500 text-xs">No leads.</div>'}
        </div>
        <div>
          <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-1">By Outreach Status</div>
          ${Object.entries(r.leads_by_outreach_status).map(([k, v]) => kv(k, v)).join('') || '<div class="text-ink-500 text-xs">No leads.</div>'}
        </div>
      </div>
    </div>`);

    sections.push(`<div class="bg-white border border-ink-200 rounded-xl shadow-soft p-5">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500 mb-3">Recent Webhook Events Received (${r.recent_webhook_events.length})</h2>
      ${r.recent_webhook_events.length === 0 ? '<div class="text-ink-500 text-xs">None received yet.</div>' :
        r.recent_webhook_events.map(e => `<div class="text-[11.5px] font-mono py-1 border-b border-ink-100 last:border-0 flex justify-between gap-3">
          <span>${esc(e.created_at)} · ${esc(e.event_type)}</span>
          <span class="${e.processed == 1 ? 'text-brand-600' : 'text-red-600'}">${e.processed == 1 ? 'processed' : 'unprocessed'}${e.last_error ? ' · ' + esc(e.last_error) : ''}</span>
        </div>`).join('')}
    </div>`);

    sections.push(`<div class="bg-white border border-ink-200 rounded-xl shadow-soft p-5">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500 mb-3">Recent Logs (${r.recent_logs.length})</h2>
      <div class="font-mono text-[11.5px] space-y-1">
      ${r.recent_logs.map(L => `<div class="flex gap-3 py-1 border-b border-ink-100 last:border-0">
        <span class="${L.level === 'error' ? 'text-red-600' : L.level === 'warn' ? 'text-amber-600' : 'text-brand-600'} font-bold w-12">${esc(L.level)}</span>
        <span class="text-ink-500 w-32 shrink-0">${esc(L.created_at)}</span>
        <span class="text-ink-700 w-20 shrink-0">${esc(L.source)}</span>
        <span class="flex-1">${esc(L.message)}${L.context ? ` <span class="text-ink-500">${esc(JSON.stringify(L.context))}</span>` : ''}</span>
      </div>`).join('')}
      </div>
    </div>`);

    sections.push(`<div class="bg-white border border-ink-200 rounded-xl shadow-soft p-5">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500">Copy snapshot</h2>
        <button id="diag-copy" class="btn-ghost text-xs">Copy JSON</button>
      </div>
      <div class="text-[11px] text-ink-500">Use this to share with support if anything stays broken.</div>
    </div>`);

    body.innerHTML = sections.join('');
    document.getElementById('diag-copy')?.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(JSON.stringify(r, null, 2));
        UI.toast('Copied diagnostic JSON');
      } catch (e) { UI.toast('Copy failed', { kind: 'error' }); }
    });
  }
  document.getElementById('diag-refresh')?.addEventListener('click', load);
  document.getElementById('diag-validate')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true; btn.textContent = 'Validating…';
    try {
      const r = await API.post('/validate_all.php', {});
      UI.toast(`Validated: ${r.validated} valid · ${r.invalidated} not on WA · ${r.failed} failed · ${r.remaining} remaining`);
      load();
    } catch (err) {
      UI.toast('Failed: ' + (err.message || ''), { kind: 'error' });
    } finally {
      btn.disabled = false; btn.textContent = 'Validate ALL pending leads';
    }
  });
  load();
})();
</script>
