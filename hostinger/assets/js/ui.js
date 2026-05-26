/* ui.js — toasts, modals, drawers, escape helpers */
(function () {
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function toast(msg, opts = {}) {
    const root = document.getElementById('toast-root');
    if (!root) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (opts.kind || '');
    el.innerHTML = `
      <span class="w-2 h-2 rounded-full ${opts.kind === 'error' ? 'bg-red-500' : opts.kind === 'warn' ? 'bg-amber-500' : 'bg-brand-500'}"></span>
      <span>${escapeHtml(msg)}</span>
    `;
    root.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateX(20px)';
      setTimeout(() => el.remove(), 250);
    }, opts.duration || 3500);
  }

  function modal({ title, body, confirmText = 'Confirm', cancelText = 'Cancel', onConfirm, danger = false }) {
    const root = document.getElementById('modal-root');
    if (!root) return;
    root.innerHTML = `
      <div class="modal-backdrop" data-close>
        <div class="modal" data-stop>
          <h3 class="text-base font-semibold tracking-tight mb-2">${escapeHtml(title)}</h3>
          <div class="text-sm text-ink-500 mb-5">${body}</div>
          <div class="flex gap-2 justify-end">
            <button class="btn-ghost text-sm" data-close>${escapeHtml(cancelText)}</button>
            <button class="${danger ? 'btn-primary !bg-red-500 !border-red-600' : 'btn-primary'} text-sm" data-confirm>${escapeHtml(confirmText)}</button>
          </div>
        </div>
      </div>
    `;
    root.querySelector('[data-stop]').addEventListener('click', e => e.stopPropagation());
    root.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', () => { root.innerHTML = ''; }));
    root.querySelector('[data-confirm]').addEventListener('click', async () => {
      try { await onConfirm?.(); } finally { root.innerHTML = ''; }
    });
  }

  function drawer(open) {
    const d = document.getElementById('details-drawer');
    if (!d) return;
    if (open) {
      d.classList.remove('hidden'); d.classList.add('flex');
    } else {
      d.classList.add('hidden'); d.classList.remove('flex');
    }
  }

  window.UI = { toast, modal, drawer, escapeHtml };
})();
