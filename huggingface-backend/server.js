'use strict';

const http = require('http');
const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const compression = require('compression');
const morgan = require('morgan');

const config = require('./config');
const logger = require('./utils/logger');
const routes = require('./routes');
const rateLimiter = require('./middleware/ratelimit.middleware');
const { notFound, errorHandler } = require('./middleware/error.middleware');

const whatsapp = require('./services/whatsapp.service');
const socket = require('./services/socket.service');
const queue = require('./services/queue.service');

const app = express();

// Trust proxy (HF runs behind reverse proxy)
app.set('trust proxy', 1);

// Security
app.use(
  helmet({
    contentSecurityPolicy: false, // QR page injects inline styles
    crossOriginEmbedderPolicy: false,
  })
);

// CORS for REST
app.use(
  cors({
    origin: (origin, cb) => {
      if (!origin) return cb(null, true);
      if (
        config.allowedOrigins.includes('*') ||
        config.allowedOrigins.includes(origin)
      ) {
        return cb(null, true);
      }
      // Allow non-browser callers (PHP server) without origin
      return cb(null, true);
    },
    credentials: true,
  })
);

app.use(compression());
app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true, limit: '2mb' }));

// Logging
app.use(
  morgan(config.env === 'production' ? 'tiny' : 'dev', {
    stream: { write: (msg) => logger.info(msg.trim()) },
  })
);

// Rate limit on REST routes
app.use(rateLimiter);

// Routes
app.use(routes);

// 404 + Error
app.use(notFound);
app.use(errorHandler);

// HTTP server (so Socket.io can attach)
const server = http.createServer(app);
socket.init(server);
socket.startHeartbeat(() => whatsapp.getStatus());

// Boot WhatsApp client
whatsapp.init();
queue.start();

// Graceful shutdown
function shutdown(signal) {
  logger.warn(`Received ${signal}, shutting down...`);
  server.close(() => logger.info('http server closed'));
  whatsapp
    .shutdown()
    .catch(() => {})
    .finally(() => {
      setTimeout(() => process.exit(0), 1500);
    });
}
process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));

process.on('unhandledRejection', (reason) => {
  logger.error('unhandledRejection', { reason: String(reason) });
});
process.on('uncaughtException', (err) => {
  logger.error('uncaughtException', { err: err.message, stack: err.stack });
});

server.listen(config.port, '0.0.0.0', () => {
  logger.info(`WhatsApp Engine listening on :${config.port}`);
});
