'use strict';

const express = require('express');
const whatsapp = require('../services/whatsapp.service');

const router = express.Router();

/**
 * Public QR page (HTML). Operators scan this from a browser.
 * Auto-refreshes every 5s and shows current connection state.
 */
router.get('/qr', (req, res) => {
  const status = whatsapp.getStatus();
  const qr = whatsapp.getQrDataUrl();

  const html = `<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"/>
<title>WhatsApp Engine — QR</title>
<meta http-equiv="refresh" content="5"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#F8FAFB;color:#0A1F1C;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border:1px solid #E5E9E7;border-radius:18px;padding:32px;max-width:420px;width:100%;box-shadow:0 12px 32px rgba(16,24,40,.08)}
  h1{font-size:20px;font-weight:600;margin-bottom:6px;letter-spacing:-.02em}
  p{color:#5C6B68;font-size:14px;margin-bottom:20px}
  .qr{background:#fff;border:1px solid #E5E9E7;border-radius:12px;padding:16px;display:flex;justify-content:center}
  .qr img{width:100%;max-width:300px;height:auto}
  .badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:500;margin-bottom:14px}
  .badge.ready{background:#D1FAE5;color:#065F46}
  .badge.qr{background:#FEF3C7;color:#92400E}
  .badge.off{background:#FEE2E2;color:#991B1B}
  .dot{width:6px;height:6px;border-radius:50%;background:currentColor}
  .meta{margin-top:16px;font-size:12px;color:#5C6B68;line-height:1.5}
  code{background:#F1F5F4;padding:2px 6px;border-radius:4px;font-size:11px}
</style></head><body>
<div class="card">
  <span class="badge ${status.ready ? 'ready' : status.hasQr ? 'qr' : 'off'}">
    <span class="dot"></span> ${escapeHtml(status.state)}
  </span>
  <h1>WhatsApp Engine</h1>
  <p>${
    status.ready
      ? 'Connected & ready. You can close this tab.'
      : status.hasQr
      ? 'Scan this QR with WhatsApp on your phone.'
      : 'Initializing engine, please wait…'
  }</p>
  ${
    status.hasQr && qr
      ? `<div class="qr"><img src="${qr}" alt="QR Code"/></div>`
      : status.ready
      ? `<div class="qr" style="color:#10B981;font-weight:600;padding:40px">✓ Connected</div>`
      : `<div class="qr" style="color:#5C6B68;padding:40px">Booting…</div>`
  }
  <div class="meta">
    Last ready: <code>${escapeHtml(status.lastReadyAt || '—')}</code><br/>
    Boot attempts: <code>${status.bootAttempts}</code><br/>
    Auto-refresh: every 5s
  </div>
</div></body></html>`;
  res.set('Content-Type', 'text/html; charset=utf-8').send(html);
});

function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

module.exports = router;
