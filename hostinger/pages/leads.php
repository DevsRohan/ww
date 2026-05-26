<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();
?>
<div class="p-8 max-w-7xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-[22px] font-semibold tracking-tight">Leads</h1>
      <p class="text-sm text-ink-500 mt-1">All imported leads with WhatsApp validation and outreach status.</p>
    </div>
    <div class="flex items-center gap-2">
      <button data-action="upload-csv" class="btn-primary text-sm">Upload CSV</button>
    </div>
  </div>
  <div class="bg-white border border-ink-200 rounded-xl2 shadow-soft overflow-hidden">
    <div class="px-5 py-3 border-b border-ink-200 flex items-center gap-3">
      <input id="leads-page-q" placeholder="Search…" class="input text-sm flex-1 max-w-xs"/>
      <select id="leads-page-status" class="input text-sm w-44">
        <option value="">All outreach statuses</option>
        <option value="new">New</option>
        <option value="queued">Queued</option>
        <option value="sent">Sent</option>
        <option value="delivered">Delivered</option>
        <option value="read">Read</option>
        <option value="replied">Replied</option>
        <option value="failed">Failed</option>
        <option value="skipped">Skipped</option>
      </select>
      <select id="leads-page-wa" class="input text-sm w-44">
        <option value="">All WA statuses</option>
        <option value="valid">Valid</option>
        <option value="not_on_whatsapp">Not on WhatsApp</option>
        <option value="pending">Pending</option>
        <option value="failed">Failed</option>
      </select>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-ink-50 text-ink-500 text-xs uppercase tracking-wider">
          <tr>
            <th class="text-left px-5 py-3">Business</th>
            <th class="text-left px-5 py-3">Location</th>
            <th class="text-left px-5 py-3">Phone</th>
            <th class="text-left px-5 py-3">WA</th>
            <th class="text-left px-5 py-3">Outreach</th>
            <th class="text-left px-5 py-3">Pitch</th>
            <th class="text-right px-5 py-3"></th>
          </tr>
        </thead>
        <tbody id="leads-page-tbody"></tbody>
      </table>
    </div>
    <div id="leads-page-footer" class="px-5 py-3 border-t border-ink-200 text-xs text-ink-500 flex items-center justify-between">
      <span><span id="leads-page-total">0</span> total</span>
    </div>
  </div>
</div>
