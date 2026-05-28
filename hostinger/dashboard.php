<?php
require_once __DIR__ . '/config/bootstrap.php';
Auth::requireLogin();
$pageTitle = 'Inbox';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="flex min-h-screen">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <!-- Middle column: Lead list -->
  <section id="leads-pane" class="w-[360px] shrink-0 h-screen sticky top-0 bg-white border-r border-ink-200 flex flex-col">
    <div class="px-5 pt-5 pb-3 border-b border-ink-200">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-[15px] font-semibold tracking-tight">Leads</h2>
        <div class="flex items-center gap-1">
          <button data-action="validate-all" class="icon-btn" title="Validate All Numbers" id="btn-validate-all">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </button>
          <button data-action="upload-csv" class="icon-btn" title="Upload CSV">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          </button>
          <button data-action="refresh-leads" class="icon-btn" title="Refresh">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          </button>
        </div>
      </div>
      <div class="relative">
        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input id="lead-search" type="search" placeholder="Search business, phone, city..." class="input pl-9 text-sm"/>
      </div>
      <div id="lead-filters" class="flex flex-wrap gap-1.5 mt-3 text-[11px]">
        <button data-filter="all" class="chip chip-active">All</button>
        <button data-filter="unread" class="chip">Unread</button>
        <button data-filter="replied" class="chip">Replied</button>
        <button data-filter="queued" class="chip">Queued</button>
        <button data-filter="sent" class="chip">Sent</button>
        <button data-filter="valid" class="chip">Valid</button>
        <button data-filter="invalid" class="chip">Invalid</button>
        <button data-filter="pending" class="chip">Pending</button>
        <button data-filter="type_a" class="chip">Has site</button>
        <button data-filter="type_b" class="chip">No site</button>
      </div>
      <!-- Validate All Progress Bar -->
      <div id="validate-progress" class="hidden mt-3">
        <div class="flex items-center justify-between text-[11px] text-ink-600 mb-1">
          <span id="validate-status">Validating...</span>
          <span id="validate-count">0/0</span>
        </div>
        <div class="w-full bg-ink-200 rounded-full h-2 overflow-hidden">
          <div id="validate-bar" class="h-2 bg-brand-500 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <div class="flex items-center justify-between mt-1">
          <span id="validate-detail" class="text-[10px] text-ink-500"></span>
          <button id="validate-stop" class="text-[10px] text-red-600 font-medium hidden">Stop</button>
        </div>
      </div>
    </div>
    <div id="lead-list" class="flex-1 overflow-y-auto"></div>
    <div id="lead-list-footer" class="border-t border-ink-200 px-4 py-2 text-[11px] text-ink-500 flex items-center justify-between">
      <span><span id="lead-count">0</span> leads</span>
      <button id="load-more" class="text-brand-600 font-medium hidden">Load more</button>
    </div>
  </section>

  <!-- Right column: Chat + Details -->
  <main class="flex-1 flex min-w-0">
    <section id="chat-pane" class="flex-1 flex flex-col min-w-0 bg-ink-50">
      <header id="chat-header" class="h-[64px] px-5 border-b border-ink-200 bg-white flex items-center justify-between gap-3 shrink-0">
        <div class="flex items-center gap-3 min-w-0">
          <div id="chat-avatar" class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 font-semibold flex items-center justify-center text-sm shrink-0">·</div>
          <div class="min-w-0">
            <div id="chat-title" class="font-medium text-sm truncate">Select a lead to start</div>
            <div id="chat-subtitle" class="text-[11px] text-ink-500 truncate">No conversation selected</div>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <button id="btn-details" class="btn-ghost text-xs hidden">Get Details</button>
          <button id="btn-pin" class="icon-btn hidden" title="Pin">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/></svg>
          </button>
        </div>
      </header>

      <div id="chat-body" class="flex-1 overflow-y-auto px-6 py-5 space-y-3">
        <div id="chat-empty" class="h-full flex items-center justify-center">
          <div class="text-center max-w-xs">
            <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-white border border-ink-200 flex items-center justify-center text-brand-500">
              <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8z"/></svg>
            </div>
            <h3 class="text-sm font-medium mb-1">No conversation selected</h3>
            <p class="text-xs text-ink-500">Pick a lead from the left to view their conversation, or upload a CSV to start outreach.</p>
          </div>
        </div>
      </div>

      <footer id="chat-composer" class="hidden border-t border-ink-200 bg-white p-3">
        <form id="composer-form" class="flex items-end gap-2">
          <textarea id="composer-input" rows="1" placeholder="Reply manually…" class="composer-textarea"></textarea>
          <button type="submit" class="btn-primary !px-4 !py-2.5 shrink-0">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </button>
        </form>
        <div class="text-[10.5px] text-ink-500 mt-2 px-1 flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
          Manual replies bypass automation. Lead's outreach is paused after first reply.
        </div>
      </footer>
    </section>

    <!-- Right Drawer: Lead Details -->
    <aside id="details-drawer" class="hidden w-[360px] shrink-0 h-screen sticky top-0 bg-white border-l border-ink-200 flex-col">
      <header class="h-[64px] px-5 border-b border-ink-200 flex items-center justify-between shrink-0">
        <div class="text-sm font-medium tracking-tight">Lead Details</div>
        <button id="btn-close-details" class="icon-btn"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </header>
      <div id="details-body" class="flex-1 overflow-y-auto p-5 space-y-5"></div>
    </aside>
  </main>
</div>

<!-- Hidden CSV upload form -->
<input type="file" id="csv-file-input" accept=".csv" class="hidden"/>

<!-- Modal mount -->
<div id="modal-root"></div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
