'use strict';

require('dotenv').config();

const path = require('path');

const config = {
  env: process.env.NODE_ENV || 'production',
  port: parseInt(process.env.PORT || '7860', 10),

  apiKey: process.env.API_KEY || '',
  webhookSecret: process.env.WEBHOOK_SECRET || '',
  hostingerWebhookUrl: process.env.HOSTINGER_WEBHOOK_URL || '',

  allowedOrigins: (process.env.ALLOWED_ORIGINS || '*')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean),

  sessionPath:
    process.env.SESSION_PATH || path.resolve(__dirname, '..', 'data', 'wa_session'),

  rateLimit: {
    windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS || '60000', 10),
    max: parseInt(process.env.RATE_LIMIT_MAX || '120', 10),
  },

  queue: {
    minDelayMs: parseInt(process.env.DEFAULT_MIN_DELAY_MS || '120000', 10),
    maxDelayMs: parseInt(process.env.DEFAULT_MAX_DELAY_MS || '300000', 10),
  },

  whatsapp: {
    clientId: process.env.WA_CLIENT_ID || 'primary',
    puppeteerExecutable: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium',
  },

  logLevel: process.env.LOG_LEVEL || 'info',
};

module.exports = config;
