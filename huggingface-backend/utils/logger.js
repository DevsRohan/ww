'use strict';

/**
 * Self-contained logger + diagnostics ring buffer.
 *
 * Designed so the WHOLE diagnostics pipeline lives in ONE file —
 * removing any chance of a partial deploy crashing the engine.
 *
 * Exports:
 *   logger.debug/info/warn/error(msg, meta?)   — write a log line
 *   logger.pushEvent(name, data?)              — record a domain event
 *   logger.getLogs()/getErrors()/getEvents()   — recent ring buffers
 *   logger.getStartedAt()                      — process start ts (ms)
 *   logger.getEnvSnapshot()                    — redacted env summary
 */

const LEVELS = { debug: 10, info: 20, warn: 30, error: 40 };
const DEFAULT_LEVEL = (process.env.LOG_LEVEL || 'info').toLowerCase();
const currentLevel = LEVELS[DEFAULT_LEVEL] != null ? LEVELS[DEFAULT_LEVEL] : LEVELS.info;

const MAX_LOGS = 250;
const MAX_ERRORS = 80;
const MAX_EVENTS = 120;

const startedAt = Date.now();
const logs = [];
const errors = [];
const events = [];

function ts() {
  return new Date().toISOString();
}

function fmt(level, msg, meta) {
  const base = `[${ts()}] [${level.toUpperCase()}] ${msg}`;
  if (meta && Object.keys(meta).length > 0) {
    try {
      return `${base} ${JSON.stringify(meta)}`;
    } catch (_) {
      return `${base} [meta-unserializable]`;
    }
  }
  return base;
}

function pushRing(arr, max, item) {
  arr.push(item);
  if (arr.length > max) arr.shift();
}

function emit(level, msg, meta) {
  const entry = {
    ts: Date.now(),
    iso: ts(),
    level,
    msg: String(msg == null ? '' : msg),
    meta: meta || null,
  };

  // Always feed the ring buffer (regardless of console verbosity).
  try {
    pushRing(logs, MAX_LOGS, entry);
    if (level === 'error') {
      pushRing(errors, MAX_ERRORS, entry);
    }
  } catch (_) {
    /* never let ring buffer break logging */
  }

  if ((LEVELS[level] || 0) < currentLevel) return;

  const line = fmt(level, msg, meta);
  if (level === 'error' || level === 'warn') {
    // eslint-disable-next-line no-console
    console.error(line);
  } else {
    // eslint-disable-next-line no-console
    console.log(line);
  }
}

function pushEvent(name, data) {
  pushRing(events, MAX_EVENTS, {
    ts: Date.now(),
    iso: ts(),
    name: String(name || 'event'),
    data: data || null,
  });
}

function envSnapshot() {
  const present = (k) =>
    process.env[k] ? `set (${String(process.env[k]).length} chars)` : 'NOT SET';
  return {
    NODE_ENV: process.env.NODE_ENV || '(default)',
    PORT: process.env.PORT || '7860 (default)',
    SESSION_PATH: process.env.SESSION_PATH || '(default)',
    ALLOWED_ORIGINS: process.env.ALLOWED_ORIGINS || '(empty)',
    HOSTINGER_WEBHOOK_URL: process.env.HOSTINGER_WEBHOOK_URL || 'NOT SET',
    API_KEY: present('API_KEY'),
    WEBHOOK_SECRET: present('WEBHOOK_SECRET'),
    RATE_LIMIT_WINDOW_MS: process.env.RATE_LIMIT_WINDOW_MS || '60000 (default)',
    RATE_LIMIT_MAX: process.env.RATE_LIMIT_MAX || '120 (default)',
    DEFAULT_MIN_DELAY_MS: process.env.DEFAULT_MIN_DELAY_MS || '120000 (default)',
    DEFAULT_MAX_DELAY_MS: process.env.DEFAULT_MAX_DELAY_MS || '300000 (default)',
    LOG_LEVEL: process.env.LOG_LEVEL || 'info (default)',
  };
}

module.exports = {
  // logging
  debug: (m, x) => emit('debug', m, x),
  info: (m, x) => emit('info', m, x),
  warn: (m, x) => emit('warn', m, x),
  error: (m, x) => emit('error', m, x),

  // events + diagnostics (used by /debug)
  pushEvent,
  getLogs: () => logs.slice(),
  getErrors: () => errors.slice(),
  getEvents: () => events.slice(),
  getStartedAt: () => startedAt,
  getEnvSnapshot: envSnapshot,
};
