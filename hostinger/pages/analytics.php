<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();

$stats = LeadRepository::stats();
$byState = DB::fetchAll("SELECT state, COUNT(*) AS c FROM leads WHERE state IS NOT NULL AND state <> '' GROUP BY state ORDER BY c DESC LIMIT 12");
$byPitch = DB::fetchAll("SELECT pitch_type, COUNT(*) AS c FROM leads GROUP BY pitch_type");
$last7   = DB::fetchAll("SELECT DATE(timestamp) d, direction, COUNT(*) c FROM messages WHERE timestamp >= (NOW() - INTERVAL 7 DAY) GROUP BY DATE(timestamp), direction ORDER BY d");
?>
<div class="p-8 max-w-7xl mx-auto">
  <div class="mb-6">
    <h1 class="text-[22px] font-semibold tracking-tight">Analytics</h1>
    <p class="text-sm text-ink-500 mt-1">Outreach health — leads, segmentation, conversation volume.</p>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
      ['Total leads',  $stats['total_leads'], ''],
      ['Valid',        $stats['valid_leads'],   'text-brand-600'],
      ['Invalid',      $stats['invalid_leads'], 'text-rose-600'],
      ['Sent',         $stats['sent_count'], ''],
      ['Replied',      $stats['replied_count'], 'text-brand-600'],
      ['Queued',       $stats['queued_count'], ''],
      ['Sent today',   $stats['sent_today'], ''],
      ['Unread',       $stats['unread_total'], ''],
    ];
    foreach ($cards as $card):
        $label = $card[0]; $value = $card[1]; $color = $card[2] ?? '';
    ?>
    <div class="bg-white rounded-xl2 border border-ink-200 p-4 shadow-soft">
      <div class="text-[11px] uppercase tracking-wider text-ink-500"><?= h($label) ?></div>
      <div class="text-2xl font-semibold mt-1 <?= h($color ?? '') ?>"><?= number_format((int)$value) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl2 border border-ink-200 p-5 shadow-soft">
      <h3 class="text-sm font-semibold mb-3">Leads by State</h3>
      <ul class="space-y-2">
        <?php $max = max(array_column($byState, 'c') ?: [1]); foreach ($byState as $s): ?>
          <li class="flex items-center gap-3 text-sm">
            <span class="w-32 truncate text-ink-500"><?= h($s['state']) ?></span>
            <div class="flex-1 h-2 rounded-full bg-ink-100 overflow-hidden">
              <div class="h-full bg-brand-500" style="width: <?= max(2, round((int)$s['c']/$max*100)) ?>%"></div>
            </div>
            <span class="w-10 text-right font-medium"><?= (int)$s['c'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="bg-white rounded-xl2 border border-ink-200 p-5 shadow-soft">
      <h3 class="text-sm font-semibold mb-3">Pitch Segmentation</h3>
      <ul class="space-y-2">
        <?php
        $labels = ['type_a' => 'Has Website', 'type_b' => 'No Website', 'unknown' => 'Unknown'];
        $totalP = max(1, array_sum(array_column($byPitch, 'c')));
        foreach ($byPitch as $p): ?>
          <li class="flex items-center gap-3 text-sm">
            <span class="w-32 text-ink-500"><?= h($labels[$p['pitch_type']] ?? $p['pitch_type']) ?></span>
            <div class="flex-1 h-2 rounded-full bg-ink-100 overflow-hidden">
              <div class="h-full bg-brand-500" style="width: <?= round((int)$p['c']/$totalP*100) ?>%"></div>
            </div>
            <span class="w-12 text-right font-medium"><?= (int)$p['c'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="bg-white rounded-xl2 border border-ink-200 p-5 shadow-soft md:col-span-2">
      <h3 class="text-sm font-semibold mb-3">Last 7 days — Message Volume</h3>
      <table class="w-full text-sm">
        <thead><tr class="text-ink-500 text-xs uppercase tracking-wider"><th class="text-left py-2">Date</th><th class="text-right">Outbound</th><th class="text-right">Inbound</th></tr></thead>
        <tbody>
          <?php
          $byDate = [];
          foreach ($last7 as $r) {
            $byDate[$r['d']][$r['direction']] = (int)$r['c'];
          }
          for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $out = $byDate[$d]['outbound'] ?? 0;
            $in  = $byDate[$d]['inbound']  ?? 0;
            echo '<tr class="border-t border-ink-100"><td class="py-2">'.h($d).'</td><td class="text-right">'.$out.'</td><td class="text-right text-brand-600">'.$in.'</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
