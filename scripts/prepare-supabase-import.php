<?php

// Menyiapkan SQL dari file ekspor privat. Tidak terhubung ke database.
$root = dirname(__DIR__);
$source = json_decode(file_get_contents($root.'/storage/app/private/supabase-export.json'), true, flags: JSON_THROW_ON_ERROR);
if (($source['format'] ?? '') !== 'ssr-kasir-7-data-v1' || ($source['source_database'] ?? '') !== 'ssr_kasir_7') {
    throw new RuntimeException('File bukan ekspor SSR Kasir yang diharapkan.');
}
$keys = ['pengguna'=>'id_pengguna','pelanggan'=>'id_pelanggan','kategori_lapangan'=>'id_kategori','lapangan'=>'id_lapangan','reservasi'=>'id_reservasi','pembayaran'=>'id_pembayaran','jadwal_lapangan'=>'id_slot'];
foreach ($keys as $table => $key) {
    if (!isset($source['tables'][$table]) || !is_array($source['tables'][$table]) || count($source['tables'][$table]) !== ($source['counts'][$table] ?? -1)) {
        throw new RuntimeException('Jumlah baris ekspor tidak konsisten: '.$table);
    }
    foreach ($source['tables'][$table] as $row) {
        if (!isset($row[$key]) || !preg_match('/^\d+$/D',(string)$row[$key])) { throw new RuntimeException('Primary key tidak valid: '.$table); }
        foreach ($row as $value) { if (is_array($value) || is_object($value)) throw new RuntimeException('Kolom bertingkat tidak diizinkan.'); }
    }
}
$tables = $source['tables'];
$ownersOmitted = 0;
$ownersArchived = 0;
$users = [];
foreach ($tables['pengguna'] as $user) {
    if ($user['peran'] === 'PEMILIK') {
        $used = false;
        foreach (['reservasi','pembayaran'] as $table) {
            foreach ($tables[$table] as $row) { if ((string)$row['id_petugas'] === (string)$user['id_pengguna']) $used = true; }
        }
        if (!$used) { $ownersOmitted++; continue; }
        $user['peran'] = 'KASIR';
        $user['status_aktif'] = false;
        $user['remember_token'] = null;
        $ownersArchived++;
    }
    $users[] = $user;
}
$tables['pengguna'] = $users;
$sql = "BEGIN;\nSELECT pg_advisory_xact_lock(735702);\n";
$sql .= "DO \$\$ BEGIN\n IF (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE') <> 7 THEN RAISE EXCEPTION 'Tujuan harus tepat 7 tabel'; END IF;\n";
foreach ($keys as $table => $key) {
    $sql .= " IF EXISTS (SELECT 1 FROM public.\"$table\") THEN RAISE EXCEPTION 'Tujuan tidak kosong: $table. Impor dibatalkan.'; END IF;\n";
}
$sql .= "END \$\$;\n";
$counts = [];
$recordsets = [];
foreach ($keys as $table => $key) {
    $json = json_encode($tables[$table], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    do { $tag = '$ssr_'.bin2hex(random_bytes(8)).'$'; } while (str_contains($json,$tag));
    $literal = $tag.$json.$tag;
    $recordsets[$table] = 'jsonb_populate_recordset(NULL::public."'.$table.'",'.$literal.'::jsonb)';
    $counts[$table] = count($tables[$table]);
    $sql .= 'INSERT INTO public."'.$table.'" SELECT * FROM '.$recordsets[$table].";\n";
}
$sql .= "DO \$\$ BEGIN\n";
foreach ($keys as $table => $key) {
    $n = $counts[$table];
    $recordset = $recordsets[$table];
    $sql .= " IF (SELECT COUNT(*) FROM public.\"$table\") <> $n OR EXISTS(SELECT * FROM public.\"$table\" EXCEPT SELECT * FROM $recordset) THEN RAISE EXCEPTION 'Verifikasi data gagal: $table'; END IF;\n";
}
$sql .= "END \$\$;\n";
foreach ($keys as $table => $key) {
    $sql .= "SELECT pg_catalog.setval(pg_get_serial_sequence('public.$table','$key'), GREATEST(COALESCE((SELECT MAX(\"$key\") FROM public.\"$table\"),1),1),EXISTS(SELECT 1 FROM public.\"$table\"));\n";
}
$sql .= "COMMIT;\nSELECT jsonb_build_object(";
$countSql = [];
foreach ($keys as $table => $key) { $countSql[] = "'$table',(SELECT COUNT(*) FROM public.\"$table\")"; }
$sql .= implode(',',$countSql).") AS imported_counts;\n";
file_put_contents($root.'/storage/app/private/supabase-import.sql',$sql,LOCK_EX);
file_put_contents($root.'/storage/app/private/supabase-import-manifest.json',json_encode([
    'project_id'=>'kbnbnvunyuwtilidinum','source_exported_at'=>$source['exported_at'],
    'original_counts'=>$source['counts'],'import_counts'=>$counts,
    'owners_omitted'=>$ownersOmitted,'owners_archived'=>$ownersArchived,
    'sql_sha256'=>hash('sha256',$sql),
],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),LOCK_EX);
echo json_encode(['ready'=>true,'counts'=>$counts,'owners_omitted'=>$ownersOmitted,'owners_archived'=>$ownersArchived,'sql_bytes'=>strlen($sql)],JSON_THROW_ON_ERROR).PHP_EOL;
