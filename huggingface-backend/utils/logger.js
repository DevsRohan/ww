'use strict';

const config = require('../config');
const diagnostics = require('../services/diagnostics.service');

const LEVELS = { debug: 10, info: 20, warn: 30, error: 40 };
const currentLevel = LEVELS[config.logLevel] || LEVELS.info;

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

function emit(level, msg, meta) {
  const line = fmt(level, msg, meta);

  // Always feed diagnostics ring buffer (regardless of console level)
  try {
    diagnostics.pushLog({
      ts: Date.now(),
      iso: new Date().toISOString(),
      level,
      msg,
      meta: meta || null,
    });
    if (level === 'error') {
      diagnostics.pushError({
        ts: Date.now(),
        iso: new Date().toISOString(),
        msg,
        meta: meta || null,
      });
    }
  } catch (_) {
    /* never let diagnostics break logging */
  }

  if ((LEVELS[level] || 0) < currentLevel) return;
  if (level === 'error' || level === 'warn') {
    // eslint-disable-next-line no-console
    console.error(line);
  } else {
    // eslint-disable-next-line no-console
    console.log(line);
  }
}

module.exports = {
  debug: (m, x) => emit('debug', m, x),
  info: (m, x) => emit('info', m, x),
  warn: (m, x) => emit('warn', m, x),
  error: (m, x) => emit('error', m, x),
};
