'use strict';

/**
 * Normalize a phone number to E.164 (digits only) and to WhatsApp chat-id form.
 * Defaults to India country code (91) if no country code present.
 */
function normalize(input, defaultCountry = '91') {
  if (!input) return null;
  let raw = String(input).trim();
  // Remove all non-digit characters
  raw = raw.replace(/[^\d]/g, '');
  if (!raw) return null;

  // If it starts with 0, drop the 0 and prefix country code
  if (raw.startsWith('0')) {
    raw = defaultCountry + raw.replace(/^0+/, '');
  }
  // If length is 10 (Indian mobile), prefix country code
  else if (raw.length === 10) {
    raw = defaultCountry + raw;
  }
  // If number begins with country code without +, leave as-is

  // Final sanity: must be 11-15 digits per E.164
  if (raw.length < 11 || raw.length > 15) return null;
  return raw;
}

function toChatId(e164) {
  const digits = normalize(e164);
  if (!digits) return null;
  return `${digits}@c.us`;
}

function fromChatId(chatId) {
  if (!chatId) return null;
  return String(chatId).split('@')[0];
}

module.exports = { normalize, toChatId, fromChatId };
