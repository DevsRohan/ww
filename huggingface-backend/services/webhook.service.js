'use strict';

const axios = require('axios');
const crypto = require('crypto');
const config = require('../config');
const logger = require('../utils/logger');
const { retry } = require('../utils/retry');

function sign(rawBody, secret) {
  return crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
}

/**
 * Extract the most useful info we can out of an axios/Node error.
 * Helps a lot when debugging webhook failures (HTTP status, response body,
 * connection errors are all surfaced).
 */
function describeError(err) {
  if (!err) return 'unknown_error';
  if (err.response) {
    const body =
      typeof err.response.data === 'string'
        ? err.response.data
        : (() => {
            try { return JSON.stringify(err.response.data); } catch (_) { return ''; }
          })();
    return `http_${err.response.status} ${(body || '').slice(0, 200)}`.trim();
  }
  if (err.code) {
    // network errors: ENOTFOUND, ECONNREFUSED, ETIMEDOUT, ECONNRESET, etc.
    return `${err.code}${err.message ? ': ' + err.message : ''}`;
  }
  return err.message || String(err);
}

async function dispatch(eventType, payload) {
  if (!config.hostingerWebhookUrl) {
    logger.debug('webhook url not set; skipping dispatch', { eventType });
    logger.pushEvent('webhook_skipped', { eventType, reason: 'no_url' });
    return { skipped: true };
  }
  if (!config.webhookSecret) {
    logger.warn('webhook secret missing; not dispatching for safety', { eventType });
    logger.pushEvent('webhook_skipped', { eventType, reason: 'no_secret' });
    return { skipped: true };
  }

  const eventId = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  const body = JSON.stringify({
    id: eventId,
    type: eventType,
    ts: Date.now(),
    payload,
  });
  const signature = sign(body, config.webhookSecret);

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
            err: describeError(e),
          }),
      }
    );
    logger.pushEvent('webhook_dispatched', { eventType, eventId });
    return { ok: true, eventId };
  } catch (err) {
    const detail = describeError(err);
    logger.error('webhook dispatch failed permanently', {
      eventType,
      err: detail,
    });
    logger.pushEvent('webhook_failed', { eventType, eventId, err: detail });
    return { ok: false, error: detail };
  }
}

module.exports = { dispatch, sign };
