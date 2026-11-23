(() => {
  'use strict';
  const themeButton = document.querySelector('[data-theme-toggle]');
  const updateThemeButton = () => { if (themeButton) themeButton.textContent = document.documentElement.dataset.theme === 'light' ? 'Mode gelap' : 'Mode terang'; };
  updateThemeButton();
  themeButton?.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = theme;
    try { localStorage.setItem('ssr-kasir-theme', theme); } catch (_) {}
    updateThemeButton();
  });
  document.addEventListener('submit', event => {
    if (event.target.matches('form[data-confirm]') && !window.confirm(event.target.dataset.confirm)) event.preventDefault();
  });
  const form = document.getElementById('reservation-form');
  if (!form) return;
  const court = document.getElementById('court');
  const date = document.getElementById('date');
  const start = document.getElementById('start');
  const duration = document.getElementById('duration');
  const status = document.getElementById('status');
  const hint = document.getElementById('availability-hint');
  const paymentHint = document.getElementById('payment-hint');
  const timeHint = document.getElementById('time-hint');
  const clock = new window.SsrBookingClock(form.dataset.serverNow);
  const originalSchedule = {court: form.dataset.originalCourt, date: form.dataset.originalDate, time: form.dataset.originalTime, duration: form.dataset.originalDuration};
  const scheduleUnchanged = () => Boolean(form.dataset.editing) && window.SsrBookingClock.sameSchedule(originalSchedule, {court: court.value, date: date.value, time: start.value, duration: duration.value});
  let busy = new Set();
  let controller;
  let saving = false;
  let staleEdit = false;
  function update() {
    const unchanged = scheduleUnchanged();
    const expired = clock.expired(date.value, start.value) && !unchanged;
    date.min = unchanged && originalSchedule.date < clock.today() ? originalSchedule.date : clock.today();
    date.setCustomValidity(date.value && date.value < clock.today() && !unchanged ? 'Tanggal main sudah lewat. Pilih hari ini atau tanggal berikutnya.' : '');
    start.setCustomValidity(expired ? 'Jam mulai sudah lewat. Pilih waktu setelah jam server sekarang (WIB).' : '');
    timeHint.textContent = expired ? 'Tanggal/jam sudah lewat. Pilih waktu berikutnya yang belum dimulai.' : unchanged && clock.expired(date.value, start.value) ? 'Jadwal lama dipertahankan. Kamu masih dapat memperbarui status dan catatan.' : 'Reservasi baru hanya untuk tanggal dan jam yang belum lewat.';
    const selected = court.selectedOptions[0];
    const tariff = form.dataset.originalCourt === court.value && form.dataset.snapshot ? form.dataset.snapshot : selected?.dataset.price || '0';
    const [whole, fraction = ''] = tariff.split('.');
    const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    const amount = cents * Number(duration.value || 0);
    document.getElementById('estimate').textContent = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: amount % 100 ? 2 : 0, maximumFractionDigits: 2 }).format(amount / 100);
    const cancelled = status.value === 'DIBATALKAN';
    [...start.options].forEach(option => {
      if (!option.value) return;
      const taken = busy.has(option.value) && !cancelled;
      const passed = clock.expired(date.value, option.value) && !(unchanged && option.value === originalSchedule.time);
      option.disabled = taken || passed;
      const label = option.value.replace(':', '.') + (passed ? ' · Lewat' : taken ? ' · Terisi' : '');
      if (option.textContent !== label) option.textContent = label;
    });
    const hour = Number(start.value.split(':')[0]);
    [...duration.options].forEach(option => {
      if (!option.value) return;
      const count = Number(option.value);
      const taken = start.value && [...Array(count).keys()].some(i => busy.has(String(hour + i).padStart(2, '0') + ':00'));
      option.disabled = Boolean(start.value && (hour + count > 24 || (!cancelled && taken)));
    });
    const finished = [...status.options].find(option => option.value === 'SELESAI');
    if (finished) finished.disabled = !clock.ended(date.value, start.value, duration.value);
    paymentHint.textContent = ['TERKONFIRMASI', 'SELESAI'].includes(status.value)
      ? 'Status ini mencatat pembayaran sebagai lunas. Pastikan uang telah diterima.'
      : cancelled ? 'Reservasi dibatalkan tidak menahan slot. Dana yang sudah diterima tetap tercatat; refund dilakukan di luar aplikasi.' : 'Pembayaran dicatat menunggu dan seluruh jam yang dipilih akan ditahan sampai reservasi diubah atau dihapus.';
  }
  async function loadAvailability() {
    controller?.abort();
    busy = new Set();
    if (!court.value || !date.value) { hint.textContent = 'Pilih lapangan dan tanggal untuk melihat jam yang terisi.'; update(); return; }
    controller = new AbortController();
    hint.textContent = 'Memeriksa ketersediaan...';
    update();
    try {
      const url = new URL(form.dataset.availabilityUrl, window.location.origin);
      url.searchParams.set('id_lapangan', court.value);
      url.searchParams.set('tanggal', date.value);
      if (form.dataset.editing) url.searchParams.set('kecuali', form.dataset.editing);
      const response = await fetch(url, {signal: controller.signal, headers: {'Accept': 'application/json'}});
      if (!response.ok) throw new Error('Tidak tersedia');
      const data = await response.json();
      clock.sync(data.server_now);
      busy = new Set(data.terisi);
      hint.textContent = busy.size ? `${busy.size} slot jam terisi/ditutup. Jam yang bertabrakan tidak dapat dipilih.` : 'Semua jam tersedia pada tanggal ini. Jadwal diperiksa kembali saat disimpan.';
      update();
    } catch (error) {
      if (error.name === 'AbortError') return;
      hint.textContent = 'Pratinjau jadwal belum tersedia. Server tetap memeriksa bentrok saat penyimpanan.';
    }
  }
  court.addEventListener('change', loadAvailability);
  date.addEventListener('change', loadAvailability);
  [start, duration, status].forEach(input => input.addEventListener('change', update));
  form.addEventListener('submit', event => {
    if (saving || staleEdit) { event.preventDefault(); return; }
    update();
    if (!form.reportValidity()) { event.preventDefault(); return; }
    const selectedStart = start.selectedOptions[0];
    const selectedDuration = duration.selectedOptions[0];
    if (selectedStart?.disabled || selectedDuration?.disabled) {
      event.preventDefault();
      hint.textContent = 'Pilihan jam sudah lewat, bertabrakan, atau melewati 24.00. Pilih ulang sebelum menyimpan.';
      return;
    }
    if (['TERKONFIRMASI', 'SELESAI'].includes(status.value) && !window.confirm('Pembayaran sudah diterima penuh? Status ini akan mencatat transaksi sebagai lunas.')) { event.preventDefault(); return; }
    saving = true;
    const button = document.getElementById('save-button');
    button.disabled = true;
    button.textContent = 'Menyimpan...';
  });
  window.addEventListener('pageshow', () => { saving = false; const button = document.getElementById('save-button'); button.disabled = false; button.textContent = form.dataset.editing ? 'Simpan Perubahan' : 'Simpan Reservasi'; });
  loadAvailability();
  let lastMinute = -1;
  const tick = () => {
    const serverTime = clock.now();
    document.getElementById('server-clock').textContent = 'Waktu server: ' + new Intl.DateTimeFormat('id-ID', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Jakarta'}).format(new Date(serverTime)) + ' WIB';
    const minute = Math.floor(serverTime / 60000);
    if (minute !== lastMinute) { lastMinute = minute; update(); }
  };
  tick();
  setInterval(tick, 1000);

  const live = document.getElementById('live-reservations');
  if (!live) return;
  const liveStatus = document.getElementById('live-status');
  const list = document.getElementById('reservation-live-list');
  const initialRevision = form.elements.edit_revision.value;
  let known = '';
  let timer;
  let liveController;
  let inFlight = false;
  let failures = 0;
  const selectionKey = () => `${court.value}|${date.value}|${form.dataset.editing}`;
  const setStatus = (text, state = 'online') => { liveStatus.textContent = text; liveStatus.dataset.state = state; };
  const schedule = (delay = 3000) => {
    clearTimeout(timer);
    if (!document.hidden && !saving) timer = setTimeout(sync, delay);
  };
  async function sync() {
    if (inFlight || document.hidden || saving) return;
    if (navigator.onLine === false) { setStatus('Offline · menunggu koneksi', 'offline'); schedule(5000); return; }
    inFlight = true;
    liveController = new AbortController();
    const timeout = setTimeout(() => liveController?.abort(), 8000);
    const selection = selectionKey();
    let nextDelay = 3000;
    try {
      const url = new URL(live.dataset.liveUrl, window.location.origin);
      const current = new URL(window.location.href);
      ['q', 'tanggal', 'status', 'page'].forEach(key => { if (current.searchParams.has(key)) url.searchParams.set(key, current.searchParams.get(key)); });
      if (known) url.searchParams.set('known', known);
      if (form.dataset.editing) url.searchParams.set('editing', form.dataset.editing);
      if (court.value && date.value) {
        url.searchParams.set('slot_lapangan', court.value);
        url.searchParams.set('slot_tanggal', date.value);
      }
      const response = await fetch(url, {signal: liveController.signal, cache: 'no-store', headers: {'Accept': 'application/json'}});
      if ([401,403,419].includes(response.status) || response.redirected) {
        setStatus('Sesi berakhir · masuk ulang', 'offline');
        nextDelay = 30000;
        return;
      }
      if (!response.ok) throw new Error('Sinkronisasi gagal');
      const data = await response.json();
      clock.sync(data.server_now);
      tick();
      failures = 0;
      const interacting = list.contains(document.activeElement);
      if (data.changed && data.html !== null && !interacting) {
        const scroll = list.querySelector('.table-scroll')?.scrollLeft || 0;
        list.innerHTML = data.html;
        const scroller = list.querySelector('.table-scroll');
        if (scroller) scroller.scrollLeft = scroll;
        known = data.version;
      } else if (!data.changed) known = data.version;
      document.getElementById('reservation-count').textContent = data.count;
      document.getElementById('reservation-value').textContent = data.value;
      if (Number(current.searchParams.get('page') || 1) !== data.page) {
        if (data.page === 1) current.searchParams.delete('page'); else current.searchParams.set('page', data.page);
        window.history.replaceState({}, '', current);
      }
      if (selection === selectionKey() && Array.isArray(data.terisi)) {
        busy = new Set(data.terisi);
        hint.textContent = busy.size ? `${busy.size} slot jam terisi/ditutup. Jadwal diperbarui otomatis.` : 'Semua jam tersedia. Jadwal diperbarui otomatis.';
        update();
      }
      if (form.dataset.editing && data.editing_revision !== initialRevision) {
        staleEdit = true;
        document.getElementById('save-button').disabled = true;
        const warning = document.getElementById('editing-conflict');
        warning.hidden = false;
        warning.textContent = data.editing_revision === null
          ? 'Reservasi ini sudah dihapus dari perangkat lain. Isianmu tetap tersedia untuk disalin. Kembali ke daftar untuk membuat reservasi baru.'
          : 'Reservasi ini sudah berubah dari perangkat lain. Isianmu tetap tersedia untuk disalin. Muat ulang halaman sebelum menyimpan agar perubahan kasir lain tidak tertimpa.';
      }
      const time = new Intl.DateTimeFormat('id-ID', {hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'Asia/Jakarta'}).format(new Date(clock.now()));
      setStatus(interacting && data.changed ? 'Ada pembaruan · menunggu aksi selesai' : `Otomatis · ${time} WIB`);
    } catch (error) {
      if (document.hidden && error.name === 'AbortError') return;
      failures += 1;
      nextDelay = Math.min(30000, 3000 * 2 ** Math.min(failures, 3));
      setStatus('Koneksi terputus · mencoba lagi', 'offline');
    } finally {
      clearTimeout(timeout);
      inFlight = false;
      schedule(nextDelay);
    }
  }
  document.addEventListener('visibilitychange', () => {
    clearTimeout(timer);
    if (document.hidden) liveController?.abort(); else schedule(0);
  });
  window.addEventListener('online', () => schedule(0));
  window.addEventListener('offline', () => setStatus('Offline · menunggu koneksi', 'offline'));
  window.addEventListener('pageshow', () => { if (staleEdit) document.getElementById('save-button').disabled = true; schedule(0); });
  list.addEventListener('focusout', () => schedule(100));
  schedule(0);
})();
