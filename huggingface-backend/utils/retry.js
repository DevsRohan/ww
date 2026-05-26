'use strict';

const { sleep } = require('./delay');

/**
 * Run an async fn with exponential backoff retry.
 */
async function retry(fn, { attempts = 3, baseMs = 1000, factor = 2, onError } = {}) {
  let lastErr;
  for (let i = 0; i < attempts; i += 1) {
    try {
      // eslint-disable-next-line no-await-in-loop
      return await fn(i + 1);
    } catch (err) {
      lastErr = err;
      if (typeof onError === 'function') onError(err, i + 1);
      if (i < attempts - 1) {
        // eslint-disable-next-line no-await-in-loop
        await sleep(baseMs * factor ** i);
      }
    }
  }
  throw lastErr;
}

module.exports = { retry };
