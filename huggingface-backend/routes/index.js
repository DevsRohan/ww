'use strict';

const express = require('express');
const healthRoutes = require('./health.routes');
const qrRoutes = require('./qr.routes');
const sendRoutes = require('./send.routes');
const checkRoutes = require('./check.routes');
const debugRoutes = require('./debug.routes');
const whatsapp = require('../services/whatsapp.service');

const router = express.Router();

router.use(healthRoutes);
router.use(qrRoutes);
router.use(sendRoutes);
router.use(checkRoutes);
router.use(debugRoutes);

// Friendly HTML landing so opening the Space URL in a browser
// gives operators a clear next-step (instead of just JSON).
router.get('/', (req, res) => {
  if ((req.headers.accept || '').includes('application/json')) {
    return res.json({
      ok: true,
      name: 'whatsapp-crm-engine',
      version: '1.0.0',
      docs: ['/health', '/qr', '/debug', '/status', 'POST /send-message', 'POST /check-number'],
    });
  }
  const s = whatsapp.getStatus();
  const dot = s.ready ? '#10B981' : (s.hasQr ? '#F59E0B' : '#6B7280');
  const html = `<!doctype html><html lang="en"><head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>WhatsApp CRM Engine</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,Segoe UI,Roboto,Inter,sans-serif;background:#F8FAFB;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;color:#0A1F1C}
.card{background:#fff;border:1px solid #E5E9E7;border-radius:18px;padding:36px;max-width:460px;width:100%;box-shadow:0 12px 32px rgba(16,24,40,.08)}
.dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:${dot};margin-right:6px;vertical-align:middle}
.pill{display:inline-flex;align-items:center;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;background:#F1F5F4;color:#2A3936;margin-bottom:16px}
h1{font-size:22px;font-weight:600;letter-spacing:-.02em;margin-bottom:6px}
p{color:#5C6B68;font-size:13.5px;line-height:1.55;margin-bottom:18px}
.btn{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:10px;border:1px solid #E5E9E7;text-decoration:none;color:#0A1F1C;font-size:13.5px;font-weight:500;background:#fff;margin-bottom:8px;transition:all .15s}
.btn:hover{background:#F1F5F4;border-color:#CBD2CF}
.btn.primary{background:#10B981;color:#fff;border-color:#059669}
.btn.primary:hover{background:#059669}
.btn .arrow{margin-left:auto;color:#5C6B68}
.btn.primary .arrow{color:rgba(255,255,255,.8)}
.foot{font-size:11px;color:#5C6B68;margin-top:18px;text-align:center}
code{background:#F1F5F4;padding:2px 6px;border-radius:4px;font-size:11px;font-family:ui-monospace,monospace}
</style></head><body>
<div class="card">
  <span class="pill"><span class="dot"></span> ${escapeHtml(s.state)}</span>
  <h1>WhatsApp CRM Engine</h1>
  <p>Backend service for the WhatsApp CRM dashboard. ${s.ready ? 'Connected and serving requests.' : (s.hasQr ? 'Open the QR page to authenticate.' : 'Initializing — refresh in a few seconds.')}</p>
  <a class="btn primary" href="/qr">Open QR Page <span class="arrow">→</span></a>
  <a class="btn" href="/debug">Debug Diagnostics <span class="arrow">→</span></a>
  <a class="btn" href="/health">Health Check (JSON) <span class="arrow">→</span></a>
  <div class="foot">v1.0.0 · API endpoints require <code>x-api-key</code> header</div>
</div>
</body></html>`;
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
