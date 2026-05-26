'use strict';

/**
 * Public debug page + JSON dump.
 *
 * Designed for: copy-paste-and-share when something breaks.
 * Secrets are masked automatically. Safe to leave on in production.
 *
 * IMPORTANT: this file ONLY depends on logger.js (which is self-contained)
 * and lazy-requires whatsapp/queue services. If any optional service is
 * missing, the page still renders with whatever info is available.
 */

const express = require('express');
const fs = require('fs');
const path = require('path');
const os = require('os');

const config = require('../config');
const constants = require('../config/constants');
const logger = require('../utils/logger');

const router = express.Router();

function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function safeRequire(modPath, fallback) {
  try {
    return require(modPath);
  } catch (e) {
    return fallback || { __error: e.message };
  }
}

function checkSessionFiles() {
  try {
    if (!fs.existsSync(config.sessionPath)) {
      return { exists: false, path: config.sessionPath };
    }
    const inner = path.join(config.sessionPath, `session-${config.whatsapp.clientId}`);
    const stat = fs.existsSync(inner);
    let fileCount = 0;
    if (stat) {
      try {
        const walk = (dir) => {
          for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
            if (e.isDirectory()) walk(path.join(dir, e.name));
            else fileCount += 1;
          }
        };
        walk(inner);
      } catch (_) {
        /* ignore */
      }
    }
    return {
      exists: true,
      path: config.sessionPath,
      session_dir: inner,
      session_exists: stat,
      file_count: fileCount,
    };
  } catch (err) {
    return { exists: false, error: err.message };
  }
}

function fullSnapshot() {
  const mem = process.memoryUsage();

  // Lazy-require so the debug page still works even if a service file
  // is somehow missing in a partial deploy.
  const whatsapp = safeRequire('../services/whatsapp.service', {
    getStatus: () => ({ state: 'unavailable', ready: false, hasQr: false }),
  });
  const queue = safeRequire('../services/queue.service', {
    snapshot: () => ({ size: 0, running: false, processing: false, next: null }),
  });

  const wa = (typeof whatsapp.getStatus === 'function')
    ? whatsapp.getStatus()
    : { state: 'unavailable', ready: false, hasQr: false };
  const q = (typeof queue.snapshot === 'function')
    ? queue.snapshot()
    : { size: 0, running: false, processing: false, next: null };

  const sessionInfo = checkSessionFiles();
  const startedAt = logger.getStartedAt();
  const env = logger.getEnvSnapshot();

  // Health checks
  const checks = {
    api_key_configured: !!config.apiKey,
    webhook_secret_configured: !!config.webhookSecret,
    hostinger_webhook_url_configured: !!config.hostingerWebhookUrl,
    session_dir_writable: sessionInfo.exists,
    chromium_path_set: !!config.whatsapp.puppeteerExecutable,
    chromium_path_exists: false,
    whatsapp_state: wa.state,
    whatsapp_ready: wa.ready,
  };
  try {
    checks.chromium_path_exists = fs.existsSync(config.whatsapp.puppeteerExecutable);
  } catch (_) { /* keep false */ }

  // Determine overall health
  const overall = checks.api_key_configured && checks.chromium_path_exists
    ? (wa.ready ? 'healthy' : (wa.hasQr ? 'awaiting_qr' : 'initializing'))
    : 'misconfigured';

  const recentLogs = logger.getLogs().slice(-50).reverse();
  const recentErrors = logger.getErrors().slice(-20).reverse();
  const recentEvents = logger.getEvents().slice(-30).reverse();

  return {
    overall,
    service: 'whatsapp-crm-engine',
    version: '1.0.0',
    ts: Date.now(),
    iso: new Date().toISOString(),
    uptime_s: Math.round(process.uptime()),
    started_at: startedAt ? new Date(startedAt).toISOString() : null,

    runtime: {
      node: process.version,
      platform: process.platform,
      arch: process.arch,
      pid: process.pid,
      hostname: os.hostname(),
      cpus: os.cpus().length,
      load_avg: os.loadavg(),
      memory_total_mb: Math.round(os.totalmem() / 1048576),
      memory_free_mb: Math.round(os.freemem() / 1048576),
      rss_mb: Math.round(mem.rss / 1048576),
      heap_used_mb: Math.round(mem.heapUsed / 1048576),
      heap_total_mb: Math.round(mem.heapTotal / 1048576),
    },

    env,
    checks,
    whatsapp: wa,
    queue: q,
    constants: {
      events: Object.values(constants.EVENTS),
      webhook_types: Object.values(constants.WEBHOOK_TYPES),
      connection_states: Object.values(constants.CONNECTION_STATES),
    },
    session: sessionInfo,
    counts: {
      logs: recentLogs.length,
      errors: recentErrors.length,
      events: recentEvents.length,
    },
    recent_errors: recentErrors,
    recent_events: recentEvents,
    recent_logs: recentLogs,
  };
}

// JSON dump (machine-readable)
router.get('/debug.json', (req, res) => {
  try {
    res.json(fullSnapshot());
  } catch (e) {
    res.status(500).json({ ok: false, error: 'snapshot_failed', message: e.message, stack: e.stack });
  }
});

// HTML dashboard
router.get('/debug', (req, res) => {
  let s;
  try {
    s = fullSnapshot();
  } catch (e) {
    return res
      .status(500)
      .set('Content-Type', 'text/html; charset=utf-8')
      .send(`<pre style="font-family:monospace;padding:20px;color:#991B1B">Snapshot failed: ${escapeHtml(e.message)}\n\n${escapeHtml(e.stack || '')}</pre>`);
  }
  const dot = s.overall === 'healthy' ? '#10B981'
    : s.overall === 'awaiting_qr' ? '#F59E0B'
    : s.overall === 'initializing' ? '#6B7280'
    : '#EF4444';

  const html = `<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Engine Debug — ${escapeHtml(s.overall)}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,Segoe UI,Roboto,Inter,sans-serif;background:#F8FAFB;color:#0A1F1C;padding:24px;font-size:13.5px;line-height:1.5}
  .wrap{max-width:980px;margin:0 auto}
  h1{font-size:22px;font-weight:600;letter-spacing:-.02em;margin-bottom:4px}
  h2{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#5C6B68;margin:24px 0 10px}
  .card{background:#fff;border:1px solid #E5E9E7;border-radius:12px;padding:16px 18px;box-shadow:0 1px 2px rgba(16,24,40,.04);margin-bottom:14px}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
  .kv{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid #F1F5F4;font-size:12.5px}
  .kv:last-child{border-bottom:none}
  .kv .k{color:#5C6B68}
  .kv .v{font-family:ui-monospace,SFMono-Regular,monospace;color:#0A1F1C;text-align:right;word-break:break-all}
  .pill{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-weight:600;font-size:12px}
  .pill.green{background:#D1FAE5;color:#065F46}
  .pill.amber{background:#FEF3C7;color:#92400E}
  .pill.red{background:#FEE2E2;color:#991B1B}
  .pill.gray{background:#F1F5F4;color:#2A3936}
  .dot{width:8px;height:8px;border-radius:50%;background:${dot}}
  .err{background:#FEF2F2;border-left:3px solid #EF4444;padding:8px 12px;border-radius:4px;margin-bottom:8px;font-size:12px}
  .err .ts{color:#5C6B68;font-size:10.5px}
  .ev{font-family:ui-monospace,SFMono-Regular,monospace;font-size:11.5px;padding:5px 10px;background:#F1F5F4;border-radius:4px;margin-bottom:4px}
  .row{display:flex;gap:10px;align-items:center;margin-bottom:6px;font-size:12px}
  .row .level{font-weight:600;width:60px;display:inline-block;text-align:center;padding:1px 6px;border-radius:4px;font-size:10px}
  .row .level.info{background:#D1FAE5;color:#065F46}
  .row .level.warn{background:#FEF3C7;color:#92400E}
  .row .level.error{background:#FEE2E2;color:#991B1B}
  .row .level.debug{background:#F1F5F4;color:#5C6B68}
  .row .ts{color:#5C6B68;font-family:ui-monospace,monospace;font-size:10.5px;width:100px;flex-shrink:0}
  .row .msg{flex:1;font-family:ui-monospace,monospace;font-size:11.5px;word-break:break-word}
  .check{display:flex;align-items:center;gap:8px;padding:6px 0;font-size:12.5px}
  .check.ok{color:#065F46}.check.no{color:#991B1B}
  .toolbar{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center}
  button{background:#fff;border:1px solid #E5E9E7;padding:7px 13px;border-radius:8px;font-size:12px;cursor:pointer;font-weight:500}
  button:hover{background:#F1F5F4}
  button.primary{background:#10B981;color:#fff;border-color:#059669}
  button.primary:hover{background:#059669}
  .meta-note{font-size:10.5px;color:#5C6B68;margin-top:4px}
  code{background:#F1F5F4;padding:1px 6px;border-radius:4px;font-size:11px}
</style>
</head>
<body>
<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:12px">
    <div>
      <span class="pill ${s.overall === 'healthy' ? 'green' : s.overall === 'awaiting_qr' ? 'amber' : s.overall === 'initializing' ? 'gray' : 'red'}">
        <span class="dot"></span> ${escapeHtml(s.overall)}
      </span>
      <h1 style="margin-top:6px">Engine Debug</h1>
      <div class="meta-note">v${escapeHtml(s.version)} · pid ${s.runtime.pid} · uptime ${s.uptime_s}s · ${escapeHtml(s.iso)}</div>
    </div>
    <div class="toolbar">
      <button onclick="location.reload()">Refresh</button>
      <button class="primary" onclick="copyAll()">Copy ALL diagnostics</button>
      <a href="/debug.json" target="_blank"><button>JSON</button></a>
      <a href="/qr" target="_blank"><button>QR</button></a>
      <a href="/health" target="_blank"><button>Health</button></a>
    </div>
  </div>

  <h2>Health Checks</h2>
  <div class="card">
    ${Object.entries(s.checks).map(([k, v]) => `
      <div class="check ${typeof v === 'boolean' ? (v ? 'ok' : 'no') : ''}">
        ${typeof v === 'boolean' ? (v ? '✓' : '✗') : '·'}
        <span style="font-family:ui-monospace,monospace">${escapeHtml(k)}</span>
        <span style="margin-left:auto;color:#5C6B68;font-family:ui-monospace,monospace">${escapeHtml(String(v))}</span>
      </div>
    `).join('')}
  </div>

  <h2>Environment Configuration</h2>
  <div class="card">
    ${Object.entries(s.env).map(([k, v]) => `
      <div class="kv"><span class="k">${escapeHtml(k)}</span><span class="v">${escapeHtml(v)}</span></div>
    `).join('')}
  </div>

  <h2>Runtime</h2>
  <div class="card grid">
    ${Object.entries(s.runtime).map(([k, v]) => `
      <div class="kv"><span class="k">${escapeHtml(k)}</span><span class="v">${escapeHtml(Array.isArray(v) ? v.join(', ') : String(v))}</span></div>
    `).join('')}
  </div>

  <h2>WhatsApp Engine</h2>
  <div class="card grid">
    <div class="kv"><span class="k">state</span><span class="v">${escapeHtml(s.whatsapp.state)}</span></div>
    <div class="kv"><span class="k">ready</span><span class="v">${s.whatsapp.ready}</span></div>
    <div class="kv"><span class="k">hasQr</span><span class="v">${s.whatsapp.hasQr}</span></div>
    <div class="kv"><span class="k">bootAttempts</span><span class="v">${s.whatsapp.bootAttempts || 0}</span></div>
    <div class="kv"><span class="k">lastReadyAt</span><span class="v">${escapeHtml(s.whatsapp.lastReadyAt || '—')}</span></div>
    <div class="kv"><span class="k">phone</span><span class="v">${escapeHtml(s.whatsapp.info?.wid?._serialized || s.whatsapp.info?.wid?.user || '—')}</span></div>
    <div class="kv"><span class="k">pushname</span><span class="v">${escapeHtml(s.whatsapp.info?.pushname || '—')}</span></div>
    <div class="kv"><span class="k">platform</span><span class="v">${escapeHtml(s.whatsapp.info?.platform || '—')}</span></div>
  </div>

  <h2>Outbound Queue</h2>
  <div class="card grid">
    <div class="kv"><span class="k">size</span><span class="v">${s.queue.size}</span></div>
    <div class="kv"><span class="k">running</span><span class="v">${s.queue.running}</span></div>
    <div class="kv"><span class="k">processing</span><span class="v">${s.queue.processing}</span></div>
    <div class="kv"><span class="k">next</span><span class="v">${escapeHtml(JSON.stringify(s.queue.next || null))}</span></div>
  </div>

  <h2>Session Storage</h2>
  <div class="card">
    ${Object.entries(s.session).map(([k, v]) => `
      <div class="kv"><span class="k">${escapeHtml(k)}</span><span class="v">${escapeHtml(String(v))}</span></div>
    `).join('')}
    <div class="meta-note">On HF Spaces free tier the filesystem is ephemeral — session resets on Space restart.</div>
  </div>

  <h2>Recent Errors (${s.recent_errors.length})</h2>
  <div class="card">
    ${s.recent_errors.length === 0
      ? '<div style="color:#5C6B68;font-size:12px">No errors recorded.</div>'
      : s.recent_errors.map(e => `
          <div class="err">
            <div class="ts">${escapeHtml(e.iso)}</div>
            <div><strong>${escapeHtml(e.msg)}</strong></div>
            ${e.meta ? `<pre style="background:#fff;color:#0A1F1C;padding:6px 0 0;font-size:10.5px;max-height:none;font-family:ui-monospace,monospace;white-space:pre-wrap;word-break:break-word">${escapeHtml(JSON.stringify(e.meta, null, 2))}</pre>` : ''}
          </div>
        `).join('')}
  </div>

  <h2>Recent Events (${s.recent_events.length})</h2>
  <div class="card">
    ${s.recent_events.length === 0
      ? '<div style="color:#5C6B68;font-size:12px">No events recorded yet.</div>'
      : s.recent_events.slice(0, 30).map(e => `
          <div class="ev">
            <span style="color:#5C6B68">${escapeHtml(e.iso || new Date(e.ts).toISOString())}</span>
            ·
            <strong>${escapeHtml(e.name)}</strong>
            ${e.data ? `<span style="color:#5C6B68"> · ${escapeHtml(JSON.stringify(e.data))}</span>` : ''}
          </div>
        `).join('')}
  </div>

  <h2>Recent Logs (${s.recent_logs.length})</h2>
  <div class="card" style="padding:10px 14px">
    ${s.recent_logs.slice(0, 50).map(l => `
      <div class="row">
        <span class="level ${escapeHtml(l.level)}">${escapeHtml(l.level)}</span>
        <span class="ts">${escapeHtml((l.iso || '').slice(11, 19))}</span>
        <span class="msg">${escapeHtml(l.msg)}${l.meta ? ' <span style="color:#5C6B68">' + escapeHtml(JSON.stringify(l.meta)) + '</span>' : ''}</span>
      </div>
    `).join('')}
  </div>

  <div class="meta-note" style="text-align:center;margin-top:24px">
    Use <code>Copy ALL diagnostics</code> to grab a complete JSON snapshot for sharing.
  </div>
</div>

<script>
async function copyAll() {
  try {
    const r = await fetch('/debug.json');
    const j = await r.json();
    const txt = JSON.stringify(j, null, 2);
    await navigator.clipboard.writeText(txt);
    const btn = document.querySelector('.primary');
    const old = btn.textContent;
    btn.textContent = '✓ Copied (' + Math.round(txt.length / 1024) + ' KB)';
    setTimeout(() => { btn.textContent = old; }, 2000);
  } catch (e) {
    alert('Copy failed: ' + e.message);
  }
}
</script>
</body></html>`;
  res.set('Content-Type', 'text/html; charset=utf-8').send(html);
});

module.exports = router;
