(() => {
  'use strict';
  const syncTheme = () => {
    try { document.documentElement.dataset.theme = localStorage.getItem('ssr-kasir-theme') === 'light' ? 'light' : 'dark'; } catch (_) {}
  };
  syncTheme();
  window.addEventListener('pageshow', syncTheme);
  window.addEventListener('storage', event => { if (event.key === 'ssr-kasir-theme' || event.key === null) syncTheme(); });
  document.getElementById('print-button')?.addEventListener('click', () => { syncTheme(); window.print(); });

  const button = document.getElementById('download-pdf');
  if (!button) return;
  const status = document.getElementById('pdf-status');
  let libraries;
  const loadScript = src => new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = () => { script.remove(); reject(new Error('Aset PDF belum dapat dimuat.')); };
    document.head.appendChild(script);
  });
  const loadLibraries = () => {
    if (!libraries) libraries = loadScript(button.dataset.pdfLibrary)
      .then(() => loadScript(button.dataset.pdfFonts))
      .catch(error => { libraries = null; throw error; });
    return libraries;
  };
  const text = element => (element?.textContent || '').replace(/\s+/g, ' ').trim();
  const readReport = () => {
    const table = document.querySelector('table');
    return {
      title: text(document.querySelector('h1')),
      address: text(document.querySelector('header p')),
      summary: text(document.querySelector('.summary')),
      filter: text(document.querySelector('.filter-note')),
      headers: [...table.querySelectorAll('thead th')].map(text),
      rows: [...table.querySelectorAll('tbody tr')].filter(row => !row.querySelector('.empty'))
        .map(row => [...row.querySelectorAll('td')].map(text)),
      emptyMessage: text(table.querySelector('.empty')),
      footer: text(document.querySelector('footer')),
    };
  };
  const readColors = () => {
    const styles = getComputedStyle(document.documentElement);
    const color = name => styles.getPropertyValue(name).trim();
    return {
      background: color('--bg'), text: color('--text'), muted: color('--muted'),
      heading: color('--heading'), headBackground: color('--head-bg'),
      surface: color('--surface'), stripe: color('--stripe'), line: color('--line'),
    };
  };
  button.addEventListener('click', async () => {
    if (button.disabled) return;
    button.disabled = true;
    button.textContent = 'Menyiapkan PDF...';
    status.textContent = 'Membuat PDF sesuai data dan tema daftar ini...';
    try {
      await loadLibraries();
      syncTheme();
      const definition = window.SsrReservationPdf.createDefinition(readReport(), readColors());
      await window.pdfMake.createPdf(definition).download(button.dataset.filename);
      status.textContent = 'PDF siap. Warna mengikuti tema daftar dan tulisan tersusun mendatar.';
    } catch (error) {
      status.textContent = 'PDF belum berhasil dibuat. Coba unduh lagi atau gunakan Cetak Halaman.';
      console.error('Pembuatan PDF gagal:', error);
    } finally {
      button.disabled = false;
      button.textContent = 'Unduh PDF';
    }
  });
})();
