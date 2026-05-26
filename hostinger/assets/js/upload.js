/* upload.js — CSV upload flow */
(function () {
  function trigger() {
    const inp = document.getElementById('csv-file-input');
    if (inp) inp.click();
  }

  async function handle(file) {
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.csv')) {
      UI.toast('Only .csv files allowed', { kind: 'error' });
      return;
    }
    const fd = new FormData();
    fd.append('csv', file);
    UI.toast('Uploading & importing…');
    try {
      const r = await API.upload('/upload_csv.php', fd);
      UI.toast(`Imported ${r.inserted} new, ${r.duplicates} duplicate, ${r.failed} failed`);
      LEADS.load(false);
      STATS.refresh();
    } catch (e) {
      UI.toast('Upload failed: ' + (e.message || ''), { kind: 'error' });
    }
  }

  function init() {
    const inp = document.getElementById('csv-file-input');
    if (inp) {
      inp.addEventListener('change', () => {
        if (inp.files && inp.files[0]) handle(inp.files[0]);
        inp.value = '';
      });
    }
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-action="upload-csv"]')) trigger();
    });
  }

  window.UPLOAD = { init, trigger, handle };
})();
