/* api.js — Fetch wrapper with JSON, errors, and auth-friendly handling. */
(function () {
  const API_BASE = (window.__APP__ && window.__APP__.apiBase) || '/api';

  async function request(path, opts = {}) {
    const url = path.startsWith('http') ? path : API_BASE + path;
    const init = {
      method: opts.method || 'GET',
      credentials: 'same-origin',
      headers: Object.assign({ 'Accept': 'application/json' }, opts.headers || {}),
    };
    if (opts.json) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opts.json);
    } else if (opts.body) {
      init.body = opts.body;
    }
    const res = await fetch(url, init);
    let data = null;
    try { data = await res.json(); } catch (_) { data = null; }
    if (!res.ok) {
      const err = new Error((data && data.error) || ('http_' + res.status));
      err.status = res.status;
      err.data = data;
      throw err;
    }
    if (data && data.ok === false) {
      const err = new Error(data.error || 'request_failed');
      err.data = data;
      throw err;
    }
    return data;
  }

  window.API = {
    get:  (p, q)  => request(p + (q ? ('?' + new URLSearchParams(q).toString()) : '')),
    post: (p, j)  => request(p, { method: 'POST', json: j || {} }),
    upload: (p, formData) => request(p, { method: 'POST', body: formData }),
    raw: (p, init) => request(p, init || {}),
  };
})();
