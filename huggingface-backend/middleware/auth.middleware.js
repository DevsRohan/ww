'use strict';

const config = require('../config');
const logger = require('../utils/logger');

/**
 * API Key authentication via x-api-key header (or Authorization: Bearer <key>)
 */
function apiKeyAuth(req, res, next) {
  if (!config.apiKey) {
    logger.error('API_KEY not configured');
    return res.status(500).json({ ok: false, error: 'server_misconfigured' });
  }

  let provided = req.headers['x-api-key'];
  if (!provided && req.headers.authorization) {
    const parts = String(req.headers.authorization).split(' ');
    if (parts.length === 2 && /^Bearer$/i.test(parts[0])) {
      provided = parts[1];
    }
  }

  if (!provided || provided !== config.apiKey) {
    return res.status(401).json({ ok: false, error: 'unauthorized' });
  }
  return next();
}

module.exports = { apiKeyAuth };
