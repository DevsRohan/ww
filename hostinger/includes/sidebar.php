<?php
$user = Auth::user();
?>
<aside class="w-[260px] shrink-0 h-screen sticky top-0 bg-white border-r border-ink-200 flex flex-col">
  <div class="px-5 py-5 border-b border-ink-200 flex items-center gap-3">
    <div class="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center shadow-soft">
      <svg viewBox="0 0 24 24" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>
    </div>
    <div>
      <div class="font-semibold tracking-tight text-[15px]"><?= h($GLOBALS['APP']['app_name']) ?></div>
      <div class="text-[11px] text-ink-500 -mt-0.5"><?= h($GLOBALS['APP']['app_tagline']) ?></div>
    </div>
  </div>

  <nav class="px-3 py-4 flex-1 overflow-y-auto">
    <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 px-3 mb-2">Workspace</div>
    <a data-nav="inbox" class="nav-item active group" href="#inbox">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
      Inbox
      <span data-stat="unread_total" class="ml-auto badge-counter hidden">0</span>
    </a>
    <a data-nav="leads" class="nav-item group" href="#leads">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      Leads
    </a>
    <a data-nav="campaigns" class="nav-item group" href="#campaigns">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
      Campaigns
    </a>
    <a data-nav="analytics" class="nav-item group" href="#analytics">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Analytics
    </a>
    <a data-nav="logs" class="nav-item group" href="#logs">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Logs
    </a>
    <a data-nav="settings" class="nav-item group" href="#settings">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09A1.65 1.65 0 0015 4.6a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      Settings
    </a>
    <a data-nav="diagnostic" class="nav-item group" href="#diagnostic">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      Diagnostic
    </a>

    <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 px-3 mt-6 mb-2">Live KPIs</div>
    <div class="px-3 grid grid-cols-2 gap-2">
      <div class="kpi"><span class="kpi-label">Leads</span><span class="kpi-value" data-stat="total_leads">—</span></div>
      <div class="kpi"><span class="kpi-label">Valid</span><span class="kpi-value text-brand-600" data-stat="valid_leads">—</span></div>
      <div class="kpi"><span class="kpi-label">Sent</span><span class="kpi-value" data-stat="sent_count">—</span></div>
      <div class="kpi"><span class="kpi-label">Replied</span><span class="kpi-value text-brand-600" data-stat="replied_count">—</span></div>
      <div class="kpi col-span-2"><span class="kpi-label">Sent today</span><span class="kpi-value" data-stat="sent_today">—</span></div>
    </div>

    <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 px-3 mt-6 mb-2">Engine</div>
    <div class="px-3">
      <div id="engine-card" class="rounded-xl border border-ink-200 bg-ink-50 p-3 text-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="flex items-center gap-2">
            <span id="engine-dot" class="w-2 h-2 rounded-full bg-amber-400"></span>
            <span id="engine-state" class="font-medium">Connecting…</span>
          </span>
          <button id="engine-refresh" title="Refresh" class="text-ink-500 hover:text-ink-900">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          </button>
        </div>
        <div class="text-ink-500" id="engine-meta">—</div>
        <div id="engine-actions" class="mt-3 flex gap-2 hidden">
          <button data-action="open-qr" class="btn-ghost text-xs flex-1">Open QR</button>
        </div>
      </div>
    </div>

    <div class="text-[11px] font-medium uppercase tracking-wider text-ink-500 px-3 mt-6 mb-2">Quick Actions</div>
    <div class="px-3 space-y-2">
      <button data-action="upload-csv" class="btn-primary w-full justify-center text-xs">Upload CSV</button>
      <button data-action="validate-all" class="btn-ghost w-full justify-center text-xs">Validate All Pending</button>
      <button data-action="start-campaign" class="btn-ghost w-full justify-center text-xs">Start Campaign</button>
      <button data-action="pause-campaign" class="btn-ghost w-full justify-center text-xs">Pause Campaign</button>
    </div>
  </nav>

  <div class="px-3 py-3 border-t border-ink-200 flex items-center justify-between">
    <div class="flex items-center gap-2 min-w-0">
      <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 font-semibold flex items-center justify-center text-sm shrink-0"><?= h(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></div>
      <div class="min-w-0">
        <div class="text-sm font-medium truncate"><?= h($user['name'] ?? 'User') ?></div>
        <div class="text-[11px] text-ink-500 truncate"><?= h($user['email'] ?? '') ?></div>
      </div>
    </div>
    <a href="logout.php" class="text-ink-500 hover:text-ink-900" title="Logout">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</aside>
