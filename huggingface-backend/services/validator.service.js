'use strict';

const whatsapp = require('./whatsapp.service');
const socket = require('./socket.service');
const webhook = require('./webhook.service');
const constants = require('../config/constants');
const { sleep } = require('../utils/delay');

async function validate(toPhone) {
  const result = await whatsapp.checkNumber(toPhone);
  socket.emit(constants.EVENTS.LEAD_VALIDATED, result);
  webhook.dispatch(constants.WEBHOOK_TYPES.NUMBER_VALIDATED, result);
  return result;
}

async function validateBatch(phones = []) {
  const out = [];
  for (const p of phones) {
    try {
      // eslint-disable-next-line no-await-in-loop
      const r = await validate(p);
      out.push(r);
    } catch (err) {
      out.push({ phone: p, status: 'failed', error: err.message });
    }
    // small spacing to avoid hammering
    // eslint-disable-next-line no-await-in-loop
    await sleep(800);
  }
  return out;
}

module.exports = { validate, validateBatch };
