'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {JSDOM} = require('jsdom');
const root = path.join(__dirname, '..');
const markup = execFileSync(process.env.SSR_TEST_PHP || 'php', ['-r', "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo view('partials.confirmation')->render();"], {cwd: root, encoding: 'utf8'});
const script = fs.readFileSync(path.join(root, 'public/assets/confirm-action.js'), 'utf8');
const turn = () => new Promise(resolve => setImmediate(resolve));

(async () => {
  const dom = new JSDOM(`<!doctype html><html><body><form data-confirm="Hapus reservasi GFC-UJI? Riwayat tetap tersimpan."><button type="submit">Hapus</button></form>${markup}</body></html>`, {runScripts: 'outside-only', url: 'https://ssr.example.test/'});
  const {window} = dom;
  const {document} = window;
  // jsdom tidak menampilkan top layer browser: simulasikan hanya API native dialog.
  window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
  window.HTMLDialogElement.prototype.close = function (value = '') { this.returnValue = value; this.removeAttribute('open'); this.dispatchEvent(new window.Event('close')); };
  window.matchMedia = () => ({matches: true});
  window.confirm = () => { throw new Error('Konfirmasi bawaan browser tidak boleh dipakai'); };
  window.eval(script);
  const dialog = document.getElementById('action-confirmation');
  const cancel = dialog.querySelector('.confirm-actions [data-confirm-cancel]');
  const accept = document.getElementById('confirmation-accept');
  const form = document.querySelector('form');
  const trigger = form.querySelector('button');
  let submissions = 0;
  document.addEventListener('submit', event => { if (!event.defaultPrevented) submissions++; event.preventDefault(); });
  await turn();

  trigger.focus();
  trigger.click();
  assert.ok(dialog.open);
  assert.equal(document.activeElement, cancel);
  assert.equal(submissions, 0);
  cancel.click();
  await turn();
  assert.equal(submissions, 0);
  assert.equal(document.activeElement, trigger);
  assert.ok(!dialog.open);
  console.log('LULUS 1 - Modal terbuka, fokus di Batal, pembatalan tidak menghapus dan fokus kembali.');

  trigger.click();
  dialog.dispatchEvent(new window.Event('cancel', {cancelable: true}));
  await turn();
  assert.equal(submissions, 0);
  assert.ok(!dialog.open);
  console.log('LULUS 2 - Escape membatalkan tanpa mengirim formulir.');

  trigger.click();
  dialog.dispatchEvent(new window.MouseEvent('click', {clientX: -10, clientY: -10, bubbles: true}));
  await turn();
  assert.equal(submissions, 0);
  assert.ok(!dialog.open);
  console.log('LULUS 3 - Klik latar membatalkan tanpa menghapus.');

  trigger.click();
  accept.click();
  accept.click();
  await turn();
  assert.equal(submissions, 1);
  assert.ok(trigger.disabled);
  form.requestSubmit();
  assert.equal(submissions, 1);
  console.log('LULUS 4 - Hapus hanya mengirim sekali setelah disetujui; klik ganda diblokir.');

  window.dispatchEvent(new window.Event('pageshow'));
  assert.ok(!trigger.disabled);
  assert.equal(trigger.textContent, 'Hapus');
  console.log('LULUS 5 - Kembali melalui riwayat browser memulihkan tombol.');

  const dynamicForm = document.createElement('form');
  dynamicForm.dataset.confirm = 'Reservasi dari pembaruan otomatis';
  dynamicForm.innerHTML = '<button type="submit">Hapus</button>';
  document.body.appendChild(dynamicForm);
  dynamicForm.querySelector('button').click();
  assert.ok(dialog.open);
  assert.equal(document.getElementById('confirmation-description').textContent, dynamicForm.dataset.confirm);
  cancel.click();
  await turn();
  assert.equal(submissions, 1);
  console.log('LULUS 6 - Tombol dari pembaruan otomatis tetap memakai modal.');

  const payment = window.SsrConfirm.ask({kind: 'payment', title: 'Pembayaran lunas?', description: '<script>uji</script>', note: 'Pastikan uang diterima.', acceptLabel: 'Ya, Sudah Lunas'});
  assert.equal(document.getElementById('confirmation-title').textContent, 'Pembayaran lunas?');
  assert.equal(document.getElementById('confirmation-description').querySelector('script'), null);
  assert.ok(!dialog.querySelector('[data-confirm-icon="payment"]').hasAttribute('hidden'));
  assert.ok(dialog.querySelector('[data-confirm-icon="delete"]').hasAttribute('hidden'));
  assert.equal(await window.SsrConfirm.ask({}), false);
  accept.click();
  assert.equal(await payment, true);
  console.log('LULUS 7 - Konfirmasi pembayaran memakai ikon sendiri, teks aman, dan tidak menumpuk modal.');

  window.matchMedia = () => ({matches: false});
  const animated = window.SsrConfirm.ask({kind: 'delete', title: 'Hapus?', description: 'Uji animasi', note: '', acceptLabel: 'Hapus'});
  cancel.click();
  assert.ok(dialog.open);
  assert.equal(dialog.dataset.closing, 'true');
  dialog.dispatchEvent(new window.Event('animationend'));
  assert.equal(await animated, false);
  assert.ok(!dialog.open);
  assert.ok(!document.documentElement.classList.contains('has-confirm-modal'));
  console.log('LULUS 8 - Animasi tutup selesai sebelum modal ditutup dan gulir halaman dipulihkan.');

  window.matchMedia = () => ({matches: true});
  for (const kind of ['success', 'deleted', 'error']) {
    const result = window.SsrConfirm.ask({kind, result: true, title: kind === 'deleted' ? 'Reservasi berhasil dihapus' : kind, description: 'Hasil dari server', note: '', acceptLabel: 'Oke, Mengerti'});
    assert.ok(dialog.open);
    assert.equal(dialog.dataset.kind, kind);
    assert.equal(dialog.dataset.result, 'true');
    assert.ok(cancel.hidden);
    assert.equal(document.activeElement, accept);
    const symbol = kind === 'success' ? 'success' : 'error';
    assert.ok(!dialog.querySelector(`[data-confirm-icon="${symbol}"]`).hasAttribute('hidden'));
    accept.click();
    assert.equal(await result, true);
    assert.equal(submissions, 1, 'Menutup notifikasi hasil tidak boleh mengirim ulang transaksi');
    console.log(`LULUS - Notifikasi ${kind} memakai simbol yang tepat dan tidak mengirim ulang transaksi.`);
  }
  dom.window.close();

  const feedbackDom = new JSDOM(`<!doctype html><html><body>${markup}<div id="operation-feedback" data-kind="success" data-title="Berhasil!" data-message="Data tersimpan" data-note=""></div></body></html>`, {runScripts: 'outside-only'});
  feedbackDom.window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
  feedbackDom.window.HTMLDialogElement.prototype.close = function (value = '') { this.returnValue = value; this.removeAttribute('open'); this.dispatchEvent(new feedbackDom.window.Event('close')); };
  feedbackDom.window.matchMedia = () => ({matches: true});
  feedbackDom.window.eval(script);
  assert.ok(feedbackDom.window.document.getElementById('action-confirmation').open);
  assert.equal(feedbackDom.window.document.getElementById('confirmation-title').textContent, 'Berhasil!');
  feedbackDom.window.document.getElementById('confirmation-accept').click();
  feedbackDom.window.close();
  console.log('LULUS - Hasil server otomatis menampilkan notifikasi saat halaman dibuka.');
})().catch(error => { console.error(error); process.exitCode = 1; });
