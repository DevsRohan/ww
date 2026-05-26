'use strict';

const express = require('express');
const healthRoutes = require('./health.routes');
const qrRoutes = require('./qr.routes');
const sendRoutes = require('./send.routes');
const checkRoutes = require('./check.routes');

const router = express.Router();

router.use(healthRoutes);
router.use(qrRoutes);
router.use(sendRoutes);
router.use(checkRoutes);

router.get('/', (req, res) => {
  res.json({
    ok: true,
    name: 'whatsapp-crm-engine',
    version: '1.0.0',
    docs: ['/health', '/qr', '/status', 'POST /send-message', 'POST /check-number'],
  });
});

module.exports = router;
