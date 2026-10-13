<?php
// database/init_db.php - Inisialisasi Database dan Data Real dari Spreadsheet SiGuru
require_once __DIR__ . '/../config/database.php';

echo "Menginisialisasi Database SiGuru...\n";

try {
    $pdo = getDBConnection();

    // 1. Baca dan eksekusi schema SQL
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);
    echo "✔ Struktur tabel berhasil dibuat.\n";

    // 2. Jalankan sinkronisasi data dari spreadsheet
    require_once __DIR__ . '/sync_from_sheets.php';

} catch (Exception $e) {
    die("ERROR inisialisasi: " . $e->getMessage() . "\n");
}
