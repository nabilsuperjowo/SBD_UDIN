-- Khusus Supabase, dijalankan sesudah tujuh tabel dipasang.
-- Laravel memakai koneksi PostgreSQL server-side milik pemilik tabel.
-- Tidak menggunakan Supabase Auth/Data API untuk login atau CRUD kasir.
BEGIN;
DO $$
DECLARE
    table_name TEXT;
    table_owner NAME;
    sequence_name TEXT;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='anon')
       OR NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='authenticated') THEN
        RAISE EXCEPTION 'Role Supabase tidak ditemukan. Jangan jalankan file ini pada database lokal.';
    END IF;
    FOREACH table_name IN ARRAY ARRAY[
        'pengguna','pelanggan','kategori_lapangan','lapangan','reservasi','pembayaran','jadwal_lapangan'
    ] LOOP
        SELECT pg_get_userbyid(c.relowner) INTO table_owner
        FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
        WHERE n.nspname='public' AND c.relname=table_name AND c.relkind='r';
        IF table_owner IS NULL THEN
            RAISE EXCEPTION 'Tabel public.% belum tersedia',table_name;
        END IF;
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY',table_name);
        EXECUTE format('REVOKE ALL PRIVILEGES ON TABLE public.%I FROM anon, authenticated',table_name);
        FOR sequence_name IN
            SELECT pg_get_serial_sequence(format('public.%I', table_name), a.attname)
            FROM pg_attribute a
            WHERE a.attrelid = format('public.%I',table_name)::regclass
              AND a.attnum>0 AND NOT a.attisdropped AND a.attidentity<>''
        LOOP
            IF sequence_name IS NOT NULL THEN
                EXECUTE format('REVOKE ALL PRIVILEGES ON SEQUENCE %s FROM anon, authenticated',sequence_name);
            END IF;
        END LOOP;
    END LOOP;
END $$;
COMMIT;

SELECT tablename,rowsecurity
FROM pg_tables
WHERE schemaname='public'
  AND tablename IN ('pengguna','pelanggan','kategori_lapangan','lapangan','reservasi','pembayaran','jadwal_lapangan')
ORDER BY tablename;
