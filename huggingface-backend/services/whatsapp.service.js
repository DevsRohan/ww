'use strict';

const { Client, LocalAuth } = require('whatsapp-web.js');
const fs = require('fs');
const path = require('path');
const QRCode = require('qrcode');

const config = require('../config');
const constants = require('../config/constants');
const logger = require('../utils/logger');
const phone = require('../utils/phone');
const webhookService = require('./webhook.service');
const socketService = require('./socket.service');

const { EVENTS, WEBHOOK_TYPES, CONNECTION_STATES } = constants;

class WhatsAppService {
  constructor() {
    this.client = null;
    this.state = CONNECTION_STATES.INITIALIZING;
    this.lastQr = null;
    this.lastQrDataUrl = null;
    this.lastReadyAt = null;
    this.lastSeenAt = null;
    this.info = null;
    this.bootAttempts = 0;
    this.isShuttingDown = false;
  }

  ensureSessionDir() {
    try {
      if (!fs.existsSync(config.sessionPath)) {
        fs.mkdirSync(config.sessionPath, { recursive: true });
      }
    } catch (err) {
      logger.error('Could not create session dir', { err: err.message });
    }
  }

  init() {
    this.ensureSessionDir();
    this.bootAttempts += 1;

    logger.info('Initializing WhatsApp client', {
      sessionPath: config.sessionPath,
      attempt: this.bootAttempts,
    });

    this.client = new Client({
      authStrategy: new LocalAuth({
        clientId: config.whatsapp.clientId,
        dataPath: config.sessionPath,
      }),
      puppeteer: {
        headless: true,
        executablePath: config.whatsapp.puppeteerExecutable,
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-accelerated-2d-canvas',
          '--no-first-run',
          '--no-zygote',
          '--single-process',
          '--disable-gpu',
        ],
      },
      webVersionCache: {
        type: 'remote',
        remotePath:
          'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.2412.54.html',
      },
    });

    this._bindEvents();
    this.client.initialize().catch((err) => {
      logger.error('client.initialize failed', { err: err.message });
      this._scheduleRecovery();
    });
  }

  _bindEvents() {
    this.client.on('qr', async (qr) => {
      this.state = CONNECTION_STATES.QR;
      this.lastQr = qr;
      try {
        this.lastQrDataUrl = await QRCode.toDataURL(qr, { width: 320, margin: 2 });
      } catch (e) {
        this.lastQrDataUrl = null;
      }
      logger.info('QR generated');
      socketService.emit(EVENTS.ENGINE_QR, { qr: this.lastQrDataUrl });
      webhookService.dispatch(WEBHOOK_TYPES.QR_UPDATED, { state: this.state });
    });

    this.client.on('loading_screen', (percent, message) => {
      socketService.emit(EVENTS.ENGINE_LOADING, { percent, message });
    });

    this.client.on('authenticated', () => {
      this.state = CONNECTION_STATES.AUTHENTICATED;
      logger.info('WhatsApp authenticated');
      socketService.emit(EVENTS.ENGINE_AUTHENTICATED, { state: this.state });
    });

    this.client.on('auth_failure', (msg) => {
      this.state = CONNECTION_STATES.AUTH_FAILURE;
      logger.error('Auth failure', { msg });
      socketService.emit(EVENTS.ENGINE_DISCONNECTED, {
        state: this.state,
        reason: 'auth_failure',
      });
      webhookService.dispatch(WEBHOOK_TYPES.CONNECTION_STATE, {
        state: this.state,
        reason: 'auth_failure',
      });
      this._scheduleRecovery(15000);
    });

    this.client.on('ready', async () => {
      this.state = CONNECTION_STATES.READY;
      this.lastReadyAt = new Date().toISOString();
      this.lastQr = null;
      this.lastQrDataUrl = null;
      try {
        this.info = this.client.info ? { ...this.client.info } : null;
      } catch (_) {
        this.info = null;
      }
      logger.info('WhatsApp ready', { wid: this.info?.wid?._serialized });
      socketService.emit(EVENTS.ENGINE_READY, {
        state: this.state,
        info: this.info,
      });
      webhookService.dispatch(WEBHOOK_TYPES.CONNECTION_STATE, {
        state: this.state,
        info: this.info,
      });
    });

    this.client.on('disconnected', (reason) => {
      this.state = CONNECTION_STATES.DISCONNECTED;
      logger.warn('WhatsApp disconnected', { reason });
      socketService.emit(EVENTS.ENGINE_DISCONNECTED, { reason });
      webhookService.dispatch(WEBHOOK_TYPES.CONNECTION_STATE, {
        state: this.state,
        reason,
      });
      this._scheduleRecovery(10000);
    });

    this.client.on('change_state', (state) => {
      logger.debug('change_state', { state });
    });

    // Inbound message
    this.client.on('message', async (msg) => {
      try {
        if (!msg || !msg.from || msg.from.endsWith('@g.us')) return; // skip groups
        if (msg.from === 'status@broadcast') return;

        const from = phone.fromChatId(msg.from);
        const payload = {
          wa_message_id: msg.id?._serialized || null,
          from,
          to: phone.fromChatId(msg.to || ''),
          body: msg.body || '',
          type: msg.type || 'chat',
          timestamp: msg.timestamp ? msg.timestamp * 1000 : Date.now(),
          has_media: !!msg.hasMedia,
          is_forwarded: !!msg.isForwarded,
          ack: msg.ack ?? null,
        };
        socketService.emit(EVENTS.MESSAGE_INBOUND, payload);
        socketService.emit(EVENTS.LEAD_REPLIED, { phone: from });
        await webhookService.dispatch(WEBHOOK_TYPES.INBOUND_MESSAGE, payload);
      } catch (err) {
        logger.error('inbound handler failed', { err: err.message });
      }
    });

    // Outbound created (when this client sends a message, including manual)
    this.client.on('message_create', async (msg) => {
      try {
        if (!msg.fromMe) return;
        if (!msg.to || msg.to.endsWith('@g.us')) return;
        const to = phone.fromChatId(msg.to);
        const payload = {
          wa_message_id: msg.id?._serialized || null,
          to,
          body: msg.body || '',
          timestamp: msg.timestamp ? msg.timestamp * 1000 : Date.now(),
          ack: msg.ack ?? null,
        };
        socketService.emit(EVENTS.MESSAGE_OUTBOUND, payload);
        // We do NOT webhook every fromMe to avoid duplicates with sendMessage path,
        // but we do ack updates below.
      } catch (err) {
        logger.error('outbound create handler failed', { err: err.message });
      }
    });

    this.client.on('message_ack', async (msg, ack) => {
      try {
        if (!msg.fromMe) return;
        const to = phone.fromChatId(msg.to);
        const status = mapAck(ack);
        const payload = {
          wa_message_id: msg.id?._serialized || null,
          to,
          ack,
          status,
          timestamp: Date.now(),
        };
        socketService.emit(EVENTS.MESSAGE_STATUS, payload);
        await webhookService.dispatch(WEBHOOK_TYPES.OUTBOUND_STATUS, payload);
      } catch (err) {
        logger.error('ack handler failed', { err: err.message });
      }
    });
  }

  _scheduleRecovery(delay = 15000) {
    if (this.isShuttingDown) return;
    setTimeout(() => {
      try {
        if (this.client) {
          this.client.destroy().catch(() => {});
        }
      } catch (_) {}
      logger.info('Recovering WhatsApp client...');
      this.init();
    }, delay);
  }

  /**
   * Send a text message.
   */
  async sendMessage(toPhone, message, opts = {}) {
    if (this.state !== CONNECTION_STATES.READY) {
      const err = new Error('engine_not_ready');
      err.code = 'engine_not_ready';
      err.status = 503;
      throw err;
    }
    const chatId = phone.toChatId(toPhone);
    if (!chatId) {
      const err = new Error('invalid_phone');
      err.code = 'invalid_phone';
      err.status = 400;
      throw err;
    }

    // Validate the number is on WhatsApp first (cheap call, prevents wasted send)
    let registered = true;
    try {
      registered = await this.client.isRegisteredUser(chatId);
    } catch (_) {
      registered = true; // fail-open: try anyway
    }
    if (!registered) {
      const err = new Error('not_on_whatsapp');
      err.code = 'not_on_whatsapp';
      err.status = 422;
      throw err;
    }

    const sent = await this.client.sendMessage(chatId, message);
    return {
      wa_message_id: sent.id?._serialized || null,
      to: phone.fromChatId(chatId),
      timestamp: sent.timestamp ? sent.timestamp * 1000 : Date.now(),
      status: 'sent',
      preview: (message || '').slice(0, 80),
      meta: opts.meta || null,
    };
  }

  /**
   * Check if a phone is registered on WhatsApp.
   */
  async checkNumber(toPhone) {
    if (this.state !== CONNECTION_STATES.READY) {
      const err = new Error('engine_not_ready');
      err.code = 'engine_not_ready';
      err.status = 503;
      throw err;
    }
    const chatId = phone.toChatId(toPhone);
    if (!chatId) {
      const err = new Error('invalid_phone');
      err.code = 'invalid_phone';
      err.status = 400;
      throw err;
    }
    const registered = await this.client.isRegisteredUser(chatId);
    return {
      phone: phone.fromChatId(chatId),
      registered,
      status: registered ? 'valid' : 'not_on_whatsapp',
    };
  }

  getStatus() {
    return {
      state: this.state,
      ready: this.state === CONNECTION_STATES.READY,
      hasQr: !!this.lastQrDataUrl,
      info: this.info,
      lastReadyAt: this.lastReadyAt,
      lastSeenAt: this.lastSeenAt,
      bootAttempts: this.bootAttempts,
    };
  }

  getQrDataUrl() {
    return this.lastQrDataUrl;
  }

  async restart() {
    logger.warn('Manual restart requested');
    try {
      if (this.client) await this.client.destroy();
    } catch (_) {}
    this.state = CONNECTION_STATES.INITIALIZING;
    this.init();
  }

  async logout() {
    try {
      if (this.client) await this.client.logout();
    } catch (e) {
      logger.warn('logout error', { err: e.message });
    }
    // wipe session
    try {
      const dir = path.join(config.sessionPath, `session-${config.whatsapp.clientId}`);
      if (fs.existsSync(dir)) fs.rmSync(dir, { recursive: true, force: true });
    } catch (_) {}
    await this.restart();
  }

  async shutdown() {
    this.isShuttingDown = true;
    try {
      if (this.client) await this.client.destroy();
    } catch (_) {}
  }

  touchSeen() {
    this.lastSeenAt = new Date().toISOString();
  }
}

function mapAck(ack) {
  // -1 error, 0 pending, 1 server, 2 device, 3 read, 4 played
  switch (ack) {
    case -1:
      return 'failed';
    case 0:
      return 'queued';
    case 1:
      return 'sent';
    case 2:
      return 'delivered';
    case 3:
      return 'read';
    case 4:
      return 'played';
    default:
      return 'unknown';
  }
}

module.exports = new WhatsAppService();
