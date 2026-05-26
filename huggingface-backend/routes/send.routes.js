'use strict';

const express = require('express');
const { apiKeyAuth } = require('../middleware/auth.middleware');
const whatsapp = require('../services/whatsapp.service');
const queue = require('../services/queue.service');
const logger = require('../utils/logger');

const router = express.Router();

/**
 * POST /send-message
 * Body: { phone, message, immediate?: bool, meta?: object }
 *  - immediate=true → bypass queue, send now (used for manual replies)
 *  - else → enqueue with random delay (used for campaign first-outreach)
 */
router.post('/send-message', apiKeyAuth, async (req, res, next) => {
  try {
    const { phone, message, immediate, meta, jobId } = req.body || {};
    if (!phone || !message) {
      return res.status(400).json({ ok: false, error: 'phone_and_message_required' });
    }

    if (immediate) {
      const result = await whatsapp.sendMessage(phone, message, { meta });
      return res.json({ ok: true, result });
    }

    queue.enqueue({ id: jobId, phone, message, meta });
    return res.json({ ok: true, queued: true, snapshot: queue.snapshot() });
  } catch (err) {
    if (err.code) {
      logger.warn('send-message failed', { code: err.code });
      return res.status(err.status || 500).json({ ok: false, error: err.code });
    }
    return next(err);
  }
});

/**
 * POST /queue/clear
 */
router.post('/queue/clear', apiKeyAuth, (req, res) => {
  queue.clear();
  res.json({ ok: true, snapshot: queue.snapshot() });
});

/**
 * POST /queue/pause | /queue/resume
 */
router.post('/queue/pause', apiKeyAuth, (req, res) => {
  queue.pause();
  res.json({ ok: true, snapshot: queue.snapshot() });
});
router.post('/queue/resume', apiKeyAuth, (req, res) => {
  queue.start();
  res.json({ ok: true, snapshot: queue.snapshot() });
});

/**
 * POST /queue/delays
 * Body: { minMs, maxMs }
 */
router.post('/queue/delays', apiKeyAuth, (req, res) => {
  const { minMs, maxMs } = req.body || {};
  queue.setDelays(parseInt(minMs, 10), parseInt(maxMs, 10));
  res.json({ ok: true, snapshot: queue.snapshot() });
});

module.exports = router;
