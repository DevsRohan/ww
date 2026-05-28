'use strict';

const express = require('express');
const { apiKeyAuth } = require('../middleware/auth.middleware');
const validator = require('../services/validator.service');
const whatsapp = require('../services/whatsapp.service');

const router = express.Router();

/**
 * POST /check-number
 * Body: { phone } | { phones: [] }
 */
router.post('/check-number', apiKeyAuth, async (req, res, next) => {
  try {
    const { phone, phones } = req.body || {};
    if (Array.isArray(phones) && phones.length > 0) {
      const results = await validator.validateBatch(phones);
      return res.json({ ok: true, results });
    }
    if (!phone) {
      return res.status(400).json({ ok: false, error: 'phone_required' });
    }
    const result = await validator.validate(phone);
    return res.json({ ok: true, result });
  } catch (err) {
    if (err.code) {
      return res.status(err.status || 500).json({
        ok: false,
        error: err.code,
        message: err.message || err.code,
      });
    }
    return next(err);
  }
});

/**
 * GET /status
 */
router.get('/status', apiKeyAuth, (req, res) => {
  const queue = require('../services/queue.service');
  res.json({ ok: true, status: { ...whatsapp.getStatus(), queue: queue.snapshot() } });
});

/**
 * POST /restart
 */
router.post('/restart', apiKeyAuth, async (req, res) => {
  await whatsapp.restart();
  res.json({ ok: true });
});

/**
 * POST /logout (wipe session, force re-QR)
 */
router.post('/logout', apiKeyAuth, async (req, res) => {
  await whatsapp.logout();
  res.json({ ok: true });
});

module.exports = router;
