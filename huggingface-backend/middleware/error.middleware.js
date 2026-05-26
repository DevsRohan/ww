'use strict';

const logger = require('../utils/logger');

function notFound(req, res) {
  res.status(404).json({ ok: false, error: 'not_found', path: req.path });
}

// eslint-disable-next-line no-unused-vars
function errorHandler(err, req, res, _next) {
  logger.error('Unhandled error', { message: err.message, stack: err.stack });
  const status = err.status || 500;
  res.status(status).json({
    ok: false,
    error: err.code || 'server_error',
    message: err.message || 'Internal server error',
  });
}

module.exports = { notFound, errorHandler };
