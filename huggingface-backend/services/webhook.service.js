'use strict';

const axios = require('axios');
const crypto = require('crypto');
const config = require('../config');
const logger = require('../utils/logger');
const { retry } = require('../utils/retry');

function sign(rawBody, secret) {
  return crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
}

async function dispatch(eventType, payload) {
  if (!config.hostingerWebhookUrl) {
    logger.debug('webhook url not set; skipping dispatch', { eventType });
    return { skipped: true };
  }
  // NOTE: Removed webhookSecret requirement — PHP side does no signature check
  // so blocking dispatch when secret is missing just silently breaks status updates.

  const eventId = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  const body = JSON.stringify({
    id: eventId,
    type: eventType,
    ts: Date.now(),
    payload,
  });
  const signature = config.webhookSecret ? sign(body, config.webhookSecret) : 'none';

  try {
    await retry(
      async () =>
        axios.post(config.hostingerWebhookUrl, body, {
          timeout: 15000,
          headers: {
            'Content-Type': 'application/json',
            'X-Webhook-Event': eventType,
            'X-Webhook-Id': eventId,
            'X-Webhook-Signature': signature,
          },
          validateStatus: (s) => s >= 200 && s < 300,
        }),
      {
        attempts: 3,
        baseMs: 1500,
        factor: 2,
        onError: (e, n) =>
          logger.warn('webhook attempt failed', {
            eventType,
            attempt: n,
            err: e.message,
          }),
      }
    );
    return { ok: true, eventId };
  } catch (err) {
    logger.error('webhook dispatch failed permanently', {
      eventType,
      err: err.message,
    });
    return { ok: false, error: err.message };
  }
}

module.exports = { dispatch, sign };
