'use strict';

const { Server } = require('socket.io');
const config = require('../config');
const logger = require('../utils/logger');
const constants = require('../config/constants');

let io = null;

function init(httpServer) {
  io = new Server(httpServer, {
    cors: {
      origin: (origin, cb) => {
        if (!origin) return cb(null, true);
        if (
          config.allowedOrigins.includes('*') ||
          config.allowedOrigins.includes(origin)
        ) {
          return cb(null, true);
        }
        return cb(new Error('CORS_NOT_ALLOWED'), false);
      },
      credentials: true,
    },
    pingInterval: 20000,
    pingTimeout: 25000,
    transports: ['websocket', 'polling'],
  });

  io.on('connection', (socket) => {
    const origin = socket.handshake.headers.origin || 'unknown';
    logger.info('socket connected', { id: socket.id, origin });

    socket.on('subscribe:lead', (leadId) => {
      if (!leadId) return;
      socket.join(`lead:${leadId}`);
    });
    socket.on('unsubscribe:lead', (leadId) => {
      if (!leadId) return;
      socket.leave(`lead:${leadId}`);
    });

    socket.on('disconnect', (reason) => {
      logger.debug('socket disconnected', { id: socket.id, reason });
    });
  });

  return io;
}

function emit(event, payload) {
  if (!io) return;
  try {
    io.emit(event, payload);
  } catch (e) {
    logger.error('socket emit failed', { event, err: e.message });
  }
}

function emitToLead(leadId, event, payload) {
  if (!io || !leadId) return;
  io.to(`lead:${leadId}`).emit(event, payload);
}

function startHeartbeat(getStatusFn) {
  setInterval(() => {
    if (!io) return;
    const status = typeof getStatusFn === 'function' ? getStatusFn() : {};
    emit(constants.EVENTS.SYSTEM_HEARTBEAT, {
      ts: Date.now(),
      whatsapp: status,
    });
  }, 15000);
}

module.exports = { init, emit, emitToLead, startHeartbeat };
