'use strict';

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function randomBetween(minMs, maxMs) {
  const lo = Math.min(minMs, maxMs);
  const hi = Math.max(minMs, maxMs);
  return Math.floor(Math.random() * (hi - lo + 1)) + lo;
}

async function randomSleep(minMs, maxMs) {
  const ms = randomBetween(minMs, maxMs);
  await sleep(ms);
  return ms;
}

module.exports = { sleep, randomBetween, randomSleep };
