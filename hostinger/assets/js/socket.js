/* socket.js — Socket.io client with reconnect handling + event bus.
   Connects directly to the HF Spaces backend (window.__APP__.socketUrl). */

(function () {
  const url = (window.__APP__ && window.__APP__.socketUrl) || '';
  const handlers = {};
  let socket = null;
  let lastEngineState = null;

  function on(event, fn) {
    (handlers[event] = handlers[event] || []).push(fn);
  }
  function off(event, fn) {
    if (!handlers[event]) return;
    handlers[event] = handlers[event].filter(h => h !== fn);
  }
  function emit(event, payload) {
    (handlers[event] || []).forEach(fn => { try { fn(payload); } catch (e) { console.error(e); } });
  }

  function connect() {
    if (!url || typeof io === 'undefined') {
      console.warn('Socket URL not configured.');
      updateEnginePill({ state: 'unknown', ready: false });
      return;
    }
    socket = io(url, {
      transports: ['websocket', 'polling'],
      reconnection: true,
      reconnectionAttempts: Infinity,
      reconnectionDelay: 1500,
      reconnectionDelayMax: 30000,
      timeout: 20000,
    });

    socket.on('connect',     () => emit('socket:connected'));
    socket.on('disconnect',  (reason) => emit('socket:disconnected', reason));
    socket.on('connect_error', (err) => emit('socket:error', err.message));

    // Engine + system events
    socket.on('engine:qr',           (p) => { lastEngineState = { state: 'qr', hasQr: true }; updateEnginePill(lastEngineState); emit('engine:qr', p); });
    socket.on('engine:ready',        (p) => { lastEngineState = { state: 'ready', ready: true, info: p?.info }; updateEnginePill(lastEngineState); emit('engine:ready', p); });
    socket.on('engine:authenticated',(p) => { lastEngineState = { state: 'authenticated' }; updateEnginePill(lastEngineState); });
    socket.on('engine:loading',      (p) => emit('engine:loading', p));
    socket.on('engine:disconnected', (p) => { lastEngineState = { state: 'disconnected' }; updateEnginePill(lastEngineState); emit('engine:disconnected', p); });

    socket.on('message:inbound',  (p) => emit('message:inbound', p));
    socket.on('message:outbound', (p) => emit('message:outbound', p));
    socket.on('message:status',   (p) => emit('message:status', p));
    socket.on('lead:replied',     (p) => emit('lead:replied', p));
    socket.on('lead:validated',   (p) => emit('lead:validated', p));
    socket.on('queue:tick',       (p) => emit('queue:tick', p));
    socket.on('campaign:progress',(p) => emit('campaign:progress', p));
    socket.on('system:heartbeat', (p) => {
      if (p && p.whatsapp) {
        lastEngineState = p.whatsapp;
        updateEnginePill(p.whatsapp);
      }
      emit('system:heartbeat', p);
    });
  }

  function updateEnginePill(s) {
    const dot   = document.getElementById('engine-dot');
    const state = document.getElementById('engine-state');
    const meta  = document.getElementById('engine-meta');
    const actions = document.getElementById('engine-actions');
    if (!dot || !state) return;
    let color = 'bg-amber-400'; let label = 'Connecting…'; let metaText = 'Waiting for engine…';
    if (s) {
      if (s.ready || s.state === 'ready') { color = 'bg-brand-500 pulse-dot text-brand-500'; label = 'Connected'; metaText = s.info?.pushname || 'WhatsApp ready'; }
      else if (s.state === 'qr' || s.hasQr) { color = 'bg-amber-400'; label = 'Awaiting QR'; metaText = 'Open QR page and scan'; }
      else if (s.state === 'authenticated') { color = 'bg-brand-300'; label = 'Authenticated'; metaText = 'Loading WhatsApp…'; }
      else if (s.state === 'disconnected' || s.state === 'auth_failure') { color = 'bg-red-500'; label = 'Disconnected'; metaText = 'Auto-reconnecting…'; }
    }
    dot.className = 'w-2 h-2 rounded-full ' + color;
    state.textContent = label;
    if (meta) meta.textContent = metaText;
    if (actions) {
      if (s && (s.state === 'qr' || s.hasQr || !s.ready)) {
        actions.classList.remove('hidden');
      } else {
        actions.classList.add('hidden');
      }
    }
  }

  window.SOCK = { on, off, emit, connect, getEngineState: () => lastEngineState };
})();
