<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();
?>
<div class="p-8 max-w-7xl mx-auto">
  <div class="mb-6">
    <h1 class="text-[22px] font-semibold tracking-tight">Logs</h1>
    <p class="text-sm text-ink-500 mt-1">Latest 100 entries. Auto-refreshes every 10s.</p>
  </div>
  <div class="bg-white border border-ink-200 rounded-xl2 shadow-soft overflow-hidden">
    <div class="px-5 py-3 border-b border-ink-200 flex items-center gap-3">
      <select id="logs-level" class="input text-sm w-36">
        <option value="">All levels</option>
        <option value="debug">Debug</option>
        <option value="info">Info</option>
        <option value="warn">Warn</option>
        <option value="error">Error</option>
        <option value="critical">Critical</option>
      </select>
      <select id="logs-source" class="input text-sm w-36">
        <option value="">All sources</option>
        <option value="app">app</option>
        <option value="auth">auth</option>
        <option value="webhook">webhook</option>
        <option value="campaign">campaign</option>
        <option value="retry">retry</option>
        <option value="validate">validate</option>
        <option value="settings">settings</option>
        <option value="groq">groq</option>
        <option value="node">node</option>
        <option value="send">send</option>
      </select>
      <button id="logs-refresh" class="btn-ghost text-sm">Refresh</button>
    </div>
    <div id="logs-tbody" class="divide-y divide-ink-200 text-sm font-mono"></div>
  </div>
</div>
