-- Pilih database ssr_kasir_7 di Query Tool pgAdmin.
-- Akun yang telah mencatat transaksi dipertahankan sebagai arsip nonaktif.
BEGIN;

DO $$ BEGIN
    IF current_database() <> 'ssr_kasir_7' THEN
        RAISE EXCEPTION 'Pilih database ssr_kasir_7';
    END IF;
END $$;

-- Kunci akun sasaran agar perubahan tidak berjalan bersamaan.
SELECT id_pengguna FROM pengguna WHERE peran='PEMILIK' FOR UPDATE;

DELETE FROM pengguna u
WHERE u.peran='PEMILIK'
  AND NOT EXISTS (SELECT 1 FROM reservasi r WHERE r.id_petugas=u.id_pengguna)
  AND NOT EXISTS (SELECT 1 FROM pembayaran p WHERE p.id_petugas=u.id_pengguna);

UPDATE pengguna
SET peran='KASIR',status_aktif=FALSE,remember_token=NULL
WHERE peran='PEMILIK';

COMMIT;

SELECT id_pengguna,nama,email,peran,status_aktif FROM pengguna ORDER BY id_pengguna;
