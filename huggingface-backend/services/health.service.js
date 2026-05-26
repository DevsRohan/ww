'use strict';

const os = require('os');
const whatsapp = require('./whatsapp.service');

function snapshot() {
  const mem = process.memoryUsage();
  return {
    ok: true,
    ts: Date.now(),
    uptime_s: Math.round(process.uptime()),
    node: process.version,
    pid: process.pid,
    rss_mb: Math.round(mem.rss / 1024 / 1024),
    heap_used_mb: Math.round(mem.heapUsed / 1024 / 1024),
    load_avg: os.loadavg(),
    whatsapp: whatsapp.getStatus(),
  };
}

module.exports = { snapshot };
