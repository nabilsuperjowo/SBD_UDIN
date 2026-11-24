'use strict';
const assert = require('node:assert/strict');
const {PDFDocument, decodePDFRawStream, PDFArray} = require('pdf-lib');
const pdfMake = require('../public/assets/vendor/pdfmake/pdfmake.min.js');
pdfMake.addVirtualFileSystem(require('../public/assets/vendor/pdfmake/vfs_fonts.js'));
const {createDefinition} = require('../public/assets/reservation-pdf.js');
const themes = {
  dark: {background: '#090b09', text: '#f5f7f2', muted: '#b3bab0', heading: '#c6f14a', headBackground: '#1c2415', surface: '#141713', stripe: '#1a1f16', line: '#343c2c'},
  light: {background: '#ffffff', text: '#1b2115', muted: '#5a6551', heading: '#263319', headBackground: '#eaf2d9', surface: '#ffffff', stripe: '#f4f6ef', line: '#d7dece'},
};
const sample = {
  title: 'Daftar Reservasi Galaxy Futsal Centre',
  address: 'Jl. Raya Tajur No.37, Tajur, Bogor Timur, Kota Bogor · Futsal & Badminton',
  summary: 'Total reservasi: 1 | Total nilai reservasi aktif: Rp 150.000',
  filter: 'Tanggal: 25 November 2026 · Pencarian: Équipe',
  headers: ['No', 'Kode', 'Nama Pemesan', 'No. WhatsApp', 'Lapangan', 'Tanggal', 'Jam (WIB)', 'Durasi', 'Tarif/Jam', 'Total Bayar', 'Pembayaran', 'Status'],
  rows: [['1', 'GFC-261125-TEST01', 'René Équipe Olahraga', '6281234567890', 'Futsal 1', '25 Nov 2026', '17.00–18.00', '1 jam', 'Rp 150.000', 'Rp 150.000', 'QRIS', 'Menunggu Pembayaran']],
  emptyMessage: 'Belum ada reservasi yang sesuai.',
  footer: 'Dicetak 25/11/2026 09.00 WIB · Kasir Uji · Nilai aktif mengecualikan reservasi dibatalkan.',
};
function streams(document, page) {
  const contents = page.node.Contents();
  const refs = contents instanceof PDFArray ? contents.asArray() : [contents];
  return refs.map(ref => Buffer.from(decodePDFRawStream(document.context.lookup(ref)).decode()).toString('latin1')).join('\n');
}

(async () => {
  let checks = 0;
  for (const [theme, colors] of Object.entries(themes)) {
    for (const count of [0, 1, 120]) {
      const report = {...sample, rows: Array.from({length: count}, (_, index) => sample.rows[0].map((value, column) => column === 0 ? String(index + 1) : value))};
      const bytes = await pdfMake.createPdf(createDefinition(report, colors)).getBuffer();
      const pdf = await PDFDocument.load(bytes);
      assert.equal(pdf.getTitle(), sample.title);
      const pages = pdf.getPages();
      if (count < 2) assert.equal(pages.length, 1);
      else assert.ok(pages.length > 1, 'Daftar panjang harus menjadi beberapa halaman');
      for (const page of pages) {
        const {width, height} = page.getSize();
        assert.ok(width > height, 'PDF harus mendatar');
        assert.ok(Math.abs(width - 841.89) < 0.1 && Math.abs(height - 595.28) < 0.1, 'Ukuran kertas A4');
        assert.equal(page.getRotation().angle, 0, 'Halaman PDF tidak boleh berputar 90 derajat');
        const content = streams(pdf, page);
        assert.ok(content.includes('841.89 595.28 re'), 'Latar mencakup seluruh halaman');
        const rgb = theme === 'light' ? '1 1 1' : '0.03529411764705882 0.043137254901960784 0.03529411764705882';
        assert.ok(content.includes(rgb), 'Warna latar PDF harus mengikuti tema');
      }
      checks++;
      console.log(`LULUS ${checks} - PDF ${theme}, ${count} reservasi, ${pages.length} halaman: A4 mendatar, rotasi 0, warna sesuai.`);
    }
  }
  console.log(`BERHASIL: ${checks} skenario menghasilkan dan memeriksa berkas PDF asli.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
