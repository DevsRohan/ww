'use strict';

const config = require('../config');
const logger = require('../utils/logger');
const { randomSleep } = require('../utils/delay');
const whatsapp = require('./whatsapp.service');
const socket = require('./socket.service');
const webhook = require('./webhook.service');
const constants = require('../config/constants');

class QueueService {
  constructor() {
    this.queue = [];
    this.running = false;
    this.processing = false;
    this.minDelayMs = config.queue.minDelayMs;
    this.maxDelayMs = config.queue.maxDelayMs;
  }

  setDelays(minMs, maxMs) {
    if (Number.isFinite(minMs) && minMs > 0) this.minDelayMs = minMs;
    if (Number.isFinite(maxMs) && maxMs > 0) this.maxDelayMs = maxMs;
  }

  enqueue(job) {
    // job: { id, phone, message, meta }
    if (!job || !job.phone || !job.message) return false;
    this.queue.push({
      id: job.id || `j_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
      phone: job.phone,
      message: job.message,
      meta: job.meta || null,
      enqueuedAt: Date.now(),
    });
    socket.emit(constants.EVENTS.QUEUE_TICK, this.snapshot());
    if (!this.processing) this._tick();
    return true;
  }

  start() {
    this.running = true;
    if (!this.processing) this._tick();
  }

  pause() {
    this.running = false;
  }

  clear() {
    this.queue = [];
    socket.emit(constants.EVENTS.QUEUE_TICK, this.snapshot());
  }

  snapshot() {
    return {
      size: this.queue.length,
      running: this.running,
      processing: this.processing,
      next: this.queue[0]
        ? { phone: this.queue[0].phone, enqueuedAt: this.queue[0].enqueuedAt }
        : null,
    };
  }

  async _tick() {
    this.processing = true;
    try {
      while (this.queue.length > 0) {
        const job = this.queue.shift();
        socket.emit(constants.EVENTS.QUEUE_TICK, this.snapshot());

        try {
          const result = await whatsapp.sendMessage(job.phone, job.message, {
            meta: job.meta,
          });
          socket.emit(constants.EVENTS.MESSAGE_OUTBOUND, {
            ...result,
            jobId: job.id,
          });
          // Notify Hostinger so it can link wa_message_id to its queued row
          webhook.dispatch(constants.WEBHOOK_TYPES.OUTBOUND_STATUS, {
            wa_message_id: result.wa_message_id,
            to: result.to,
            status: 'sent',
            jobId: job.id,
            meta: job.meta,
            timestamp: result.timestamp,
          });
          logger.info('queue: sent', { jobId: job.id, to: job.phone });
        } catch (err) {
          logger.warn('queue: send failed', {
            jobId: job.id,
            to: job.phone,
            err: err.message,
          });
          socket.emit(constants.EVENTS.MESSAGE_STATUS, {
            jobId: job.id,
            to: job.phone,
            status: 'failed',
            error: err.code || err.message,
          });
          // Tell Hostinger about the failure so it can mark the row + lead status
          webhook.dispatch(constants.WEBHOOK_TYPES.OUTBOUND_STATUS, {
            wa_message_id: null,
            to: job.phone,
            status: 'failed',
            jobId: job.id,
            meta: job.meta,
            error: err.code || err.message,
            timestamp: Date.now(),
          });
        }

        // human-like random delay
        const slept = await randomSleep(this.minDelayMs, this.maxDelayMs);
        logger.debug('queue: slept', { ms: slept });
      }
    } finally {
      this.processing = false;
      socket.emit(constants.EVENTS.QUEUE_TICK, this.snapshot());
    }
  }
}

module.exports = new QueueService();
