-- Jalankan melalui Query Tool database ssr_kasir_7.
SELECT current_database() AS database_aktif, COUNT(*) AS jumlah_tabel
FROM information_schema.tables
WHERE table_schema='public' AND table_type='BASE TABLE';

SELECT table_name,column_name,data_type,is_nullable
FROM information_schema.columns WHERE table_schema='public'
ORDER BY table_name,ordinal_position;

SELECT conrelid::regclass AS tabel,conname AS nama_constraint,
       pg_get_constraintdef(oid) AS definisi
FROM pg_constraint WHERE connamespace='public'::regnamespace
ORDER BY conrelid::regclass::TEXT,conname;

SELECT r.kode,r.nama_snapshot,l.nama,r.tanggal,r.jam_mulai,r.durasi_jam,
       r.tarif_snapshot,(r.tarif_snapshot*r.durasi_jam) AS total_bayar,
       p.metode,p.status AS status_pembayaran,r.status AS status_reservasi
FROM reservasi r JOIN lapangan l USING(id_lapangan)
JOIN pembayaran p USING(id_reservasi)
WHERE r.dihapus_pada IS NULL ORDER BY r.tanggal,r.jam_mulai;

-- Uang yang benar-benar dicatat diterima, termasuk reservasi diarsipkan.
SELECT metode,SUM(jumlah_bayar) AS bruto_diterima
FROM pembayaran WHERE status='BERHASIL' GROUP BY metode;

-- Slot kosong / ditutup / dikuasai sebuah reservasi.
SELECT l.nama,j.tanggal,j.jam_mulai,
CASE WHEN j.ditutup THEN 'DITUTUP'
     WHEN j.id_reservasi IS NULL THEN 'TERSEDIA'
     ELSE 'DIPESAN' END AS ketersediaan
FROM jadwal_lapangan j JOIN lapangan l USING(id_lapangan)
ORDER BY j.tanggal,l.nama,j.jam_mulai;
