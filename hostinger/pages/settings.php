<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin(); // redirects to login if not authenticated (used as fragment)
?>
<div class="p-8 max-w-4xl mx-auto">
  <div class="mb-6">
    <h1 class="text-[22px] font-semibold tracking-tight">Settings</h1>
    <p class="text-sm text-ink-500 mt-1">Manage credentials, campaign behavior, and feature flags. Secrets are masked once saved.</p>
  </div>

  <div id="settings-form-host" class="space-y-6"></div>

  <div class="mt-8 flex items-center gap-3">
    <button id="settings-save" class="btn-primary">Save changes</button>
    <button id="settings-reload" class="btn-ghost">Reload</button>
    <span id="settings-status" class="text-xs text-ink-500"></span>
  </div>
</div>
