<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();
$campaigns = DB::fetchAll('SELECT * FROM campaigns ORDER BY created_at DESC LIMIT 50');
?>
<div class="p-8 max-w-7xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-[22px] font-semibold tracking-tight">Campaigns</h1>
      <p class="text-sm text-ink-500 mt-1">Outreach runs. Anti-ban: random 120–300s delay, daily caps, conservative pacing.</p>
    </div>
    <div class="flex gap-2">
      <button data-action="start-campaign" class="btn-primary text-sm">Start Campaign</button>
      <button data-action="pause-campaign" class="btn-ghost text-sm">Pause</button>
    </div>
  </div>
  <div class="bg-white border border-ink-200 rounded-xl2 shadow-soft overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-ink-50 text-ink-500 text-xs uppercase tracking-wider">
        <tr>
          <th class="text-left px-5 py-3">Name</th>
          <th class="text-left px-5 py-3">Status</th>
          <th class="text-right px-5 py-3">Total</th>
          <th class="text-right px-5 py-3">Sent</th>
          <th class="text-right px-5 py-3">Replied</th>
          <th class="text-right px-5 py-3">Failed</th>
          <th class="text-left px-5 py-3">Started</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($campaigns as $c): ?>
        <tr class="border-t border-ink-200">
          <td class="px-5 py-3 font-medium"><?= h($c['name']) ?></td>
          <td class="px-5 py-3"><span class="badge status-<?= h($c['status']) ?>"><?= h($c['status']) ?></span></td>
          <td class="px-5 py-3 text-right"><?= (int)$c['total_leads'] ?></td>
          <td class="px-5 py-3 text-right"><?= (int)$c['sent_count'] ?></td>
          <td class="px-5 py-3 text-right text-brand-600 font-medium"><?= (int)$c['replied_count'] ?></td>
          <td class="px-5 py-3 text-right"><?= (int)$c['failed_count'] ?></td>
          <td class="px-5 py-3 text-ink-500 text-xs"><?= h($c['started_at'] ?? '—') ?></td>
        </tr>
      <?php endforeach; if (!$campaigns): ?>
        <tr><td colspan="7" class="px-5 py-10 text-center text-ink-500">No campaigns yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
