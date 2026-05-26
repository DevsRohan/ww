'use strict';

/**
 * Diagnostics — in-memory ring buffers for logs, errors, and events.
 * No DB dependency. Survives until process restart.
 */

const MAX_LOGS = 250;
const MAX_ERRORS = 80;
const MAX_EVENTS = 120;

class Diagnostics {
  constructor() {
    this.logs = [];
    this.errors = [];
    this.events = [];
    this.startedAt = Date.now();
    this.bootEnv = this._snapshotEnv();
  }

  pushLog(entry) {
    this.logs.push(entry);
    if (this.logs.length > MAX_LOGS) this.logs.shift();
  }

  pushError(entry) {
    this.errors.push(entry);
    if (this.errors.length > MAX_ERRORS) this.errors.shift();
  }

  pushEvent(name, data) {
    this.events.push({ ts: Date.now(), name, data: data || null });
    if (this.events.length > MAX_EVENTS) this.events.shift();
  }

  _snapshotEnv() {
    const present = (k) => (process.env[k] ? `set (${String(process.env[k]).length} chars)` : 'NOT SET');
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

  snapshot() {
    return {
      service: 'whatsapp-crm-engine',
      version: '1.0.0',
      ts: Date.now(),
      iso: new Date().toISOString(),
      startedAt: this.startedAt,
      uptime_s: Math.round((Date.now() - this.startedAt) / 1000),
      env: this.bootEnv,
      counts: {
        logs: this.logs.length,
        errors: this.errors.length,
        events: this.events.length,
      },
      recent_errors: this.errors.slice(-20).reverse(),
      recent_events: this.events.slice(-30).reverse(),
      recent_logs: this.logs.slice(-50).reverse(),
    };
  }
}

module.exports = new Diagnostics();
