<?php
// database/sync_from_sheets.php - Sinkronisasi Data Spreadsheet Nyata ke Database SQL
require_once __DIR__ . '/../config/database.php';

echo "Memulai sinkronisasi data dari Spreadsheet Google...\n";

try {
    $pdo = getDBConnection();

    // 1. Update Pengaturan Sesuai Spreadsheet
    $pdo->exec("DELETE FROM pengaturan");
    $stmtPengaturan = $pdo->prepare("INSERT INTO pengaturan (
        id, logo, nama_sekolah, tahun_ajaran, semester, kelas, mata_pelajaran,
        folder_jurnal, folder_foto, folder_guru_wali, folder_wali, folder_ekstra,
        link_db_absen, link_db_jurnal, link_db_guru_wali, link_db_wali, link_db_ekstra,
        list_ekstrakurikuler, kepala_sekolah, nip_kepala_sekolah, kop_surat, kop_size,
        kabupaten, ttd_kepsek
    ) VALUES (
        1, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?
    )");
    $stmtPengaturan->execute([
        'assets/images/logo.png',
        'SMK FYJ Channel Ferryxz',
        '2026/2027',
        'Ganjil',
        'XI DKV 1, XI DKV 2, XII DKV, X DKV',
        'Perangkat Lunak Desain, Proses Produksi Desain, Desain Breif, Karya Desain, Kreativitas Inovasi dan Kewirausahaan, Seni Budaya, Seni Rupa',
        'https://drive.google.com/drive/folders/1g1iaALpfRo8oovkJNoPxy_MIDLv5axEB?usp=drive_link',
        'https://drive.google.com/drive/folders/1PmA4EHMEVfgbup9ZJuBrIlFzg37gsQO3?usp=sharing',
        'https://drive.google.com/drive/folders/12RUHlItCa8smp58-aixHOTWHld21md9_?usp=drive_link',
        'https://drive.google.com/drive/folders/1uNSsLv2rUUC1c_ykBhnkzQ7y43DRxaDj?usp=drive_link',
        'https://drive.google.com/drive/folders/1Jf8BcS1jHO1wUYCzk6dQi9_sy7BRyroM?usp=drive_link',
        'https://docs.google.com/spreadsheets/d/e/2PACX-1vSVumMpCd4rle_l0C4bWVJY_03gqNsHhzYxQyIP15NQSjTZqIIorQt1a9kTHObhkzLtFG1ZkedeZJMO/pubhtml?gid=1377594436&single=true',
        'https://docs.google.com/spreadsheets/d/e/2PACX-1vSVumMpCd4rle_l0C4bWVJY_03gqNsHhzYxQyIP15NQSjTZqIIorQt1a9kTHObhkzLtFG1ZkedeZJMO/pubhtml?gid=508968105&single=true',
        'https://docs.google.com/spreadsheets/d/e/2PACX-1vSVumMpCd4rle_l0C4bWVJY_03gqNsHhzYxQyIP15NQSjTZqIIorQt1a9kTHObhkzLtFG1ZkedeZJMO/pubhtml?gid=1383068267&single=true',
        'https://docs.google.com/spreadsheets/d/e/2PACX-1vSVumMpCd4rle_l0C4bWVJY_03gqNsHhzYxQyIP15NQSjTZqIIorQt1a9kTHObhkzLtFG1ZkedeZJMO/pubhtml?gid=849877341&single=true',
        'https://docs.google.com/spreadsheets/d/e/2PACX-1vSVumMpCd4rle_l0C4bWVJY_03gqNsHhzYxQyIP15NQSjTZqIIorQt1a9kTHObhkzLtFG1ZkedeZJMO/pubhtml?gid=854266261&single=true',
        'Silat, Atletik, Catur, Pramuka, PMR, Drumband',
        'Ferryxz Yamato',
        '1111111111111111111',
        'assets/images/kop_surat.png',
        '90',
        'Klungkung',
        'https://drive.google.com/thumbnail?id=1GUfUommgYjV-cnhU0tTB8kRJy8iXagkI'
    ]);
    echo "✔ Pengaturan disinkronkan ke 'SMK FYJ Channel Ferryxz'.\n";

    // 2. DataUser dari Spreadsheet
    $pdo->exec("DELETE FROM data_user");
    $users = [
        ['SAdmin', 'Pengelola@Admin@Super', 'Super Admin', 'Admin Pengelola', '-'],
        ['guru', 'guru123', 'Guru,Wali Kelas,Pembina Ekstra,Guru Wali', 'Rahmat Hidayat, S.Kom.', '198502152010011002'],
        ['wali', 'wali123', 'Wali Kelas', 'Budi Santoso, S.Pd.', '198807202014022001'],
        ['guruwali', 'gw123', 'Pembina Ekstra', 'Siti Aminah, M.Pd.', '199003112019031005'],
        ['viewer', 'view123', 'Pelihat', 'Pengawas Sekolah', '197509121999031002'],
        ['ferryxz', 'gurudkv', 'Super Admin', 'Ferryxz Yamato', '1111111111111111111']
    ];
    $stmtUser = $pdo->prepare("INSERT INTO data_user (username, password, hak_akses, nama, nip) VALUES (?, ?, ?, ?, ?)");
    foreach ($users as $u) {
        // Simpan password langsung agar Super Admin dapat melihat password tersimpan via tombol mata
        $stmtUser->execute([$u[0], $u[1], $u[2], $u[3], $u[4]]);
    }
    echo "✔ DataUser disinkronkan (" . count($users) . " akun dari spreadsheet).\n";

    // 3. DataGuru dari Spreadsheet
    $pdo->exec("DELETE FROM data_guru");
    $gurus = [
        ['Budi Santoso, S.Pd.', '198502152010011002', 'Ya', 'XI DKV 1'],
        ['Siti Aminah, M.Pd.', '198807202014022001', 'Tidak', 'XI DKV 2'],
        ['Rahmat Hidayat, S.Kom.', '199003112019031005', 'Ya', 'XII DKV'],
        ['guruku', '6666666666', 'Ya', 'XI DKV 2'],
        ['Ferryxz Yamato', '1111111111111111111', 'Ya', 'XI DKV 2']
    ];
    $stmtGuru = $pdo->prepare("INSERT INTO data_guru (nama_guru, nip, sebagai_guru_wali, sebagai_wali_kelas) VALUES (?, ?, ?, ?)");
    foreach ($gurus as $g) {
        $stmtGuru->execute($g);
    }
    echo "✔ DataGuru disinkronkan (" . count($gurus) . " guru).\n";

    // 4. DataMurid dari Spreadsheet
    $pdo->exec("DELETE FROM data_murid");
    $murids = [
        ['0061234501', 'Ahmad Fauzan', 'L', 'XI DKV 2', 'Murid, Guru Wali, Wali Kelas, Ekstra'],
        ['0061234502', 'Anisa Rahmawati', 'P', 'XI DKV 2', 'Murid, Guru Wali, Wali Kelas, Ekstra'],
        ['0061234503', 'Bagas Pratama', 'L', 'XII DKV', 'Murid, Guru Wali'],
        ['61234504', 'Citra Kirana', 'P', 'XI DKV 1', 'Murid, Guru Wali, Wali Kelas, Ekstra']
    ];
    $stmtMurid = $pdo->prepare("INSERT INTO data_murid (nisn, nama_murid, jenis_kelamin, kelas, status) VALUES (?, ?, ?, ?, ?)");
    foreach ($murids as $m) {
        $stmtMurid->execute($m);
    }
    echo "✔ DataMurid disinkronkan (" . count($murids) . " siswa).\n";

    // 5. DataMuridGuruWali dari Spreadsheet
    $pdo->exec("DELETE FROM data_murid_guru_wali");
    $bimbingans = [
        ['61234501', 'Ahmad Fauzan', 'XI DKV 2', 'L', 'uploads/photos/ahmad_fauzan.png', 'Administrator Sistem', '02255444', 'hhhhhhh', '0145698', ''],
        ['0061234504', 'Citra Kirana', 'XI DKV 1', 'P', 'uploads/photos/citra_kirana.png', 'Administrator Sistem', '09999999999999', 'hhhhhhh', '077788888', '-']
    ];
    $stmtGW = $pdo->prepare("INSERT INTO data_murid_guru_wali (nisn, nama_murid, kelas, jenis_kelamin, foto_murid, guru_wali, no_wa_murid, nama_ortu, kontak_ortu, catatan_khusus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($bimbingans as $b) {
        $stmtGW->execute($b);
    }
    echo "✔ DataMuridGuruWali disinkronkan.\n";

    // 6. DataMuridEkstra dari Spreadsheet
    $pdo->exec("DELETE FROM data_murid_ekstra");
    $ekstras = [
        ['0061234503', 'Bagas Pratama', 'L', 'XII DKV', 'Atletik, Pramuka'],
        ['0061234504', 'Citra Kirana', 'P', 'XI DKV 1', 'Silat, Catur'],
        ['0061234501', 'Ahmad Fauzan', 'L', 'XI DKV 2', 'Silat, Atletik']
    ];
    $stmtME = $pdo->prepare("INSERT INTO data_murid_ekstra (nisn, nama_murid, jenis_kelamin, kelas, nama_ekstra) VALUES (?, ?, ?, ?, ?)");
    foreach ($ekstras as $e) {
        $stmtME->execute($e);
    }
    echo "✔ DataMuridEkstra disinkronkan.\n";

    // 7. HasilAbsen dari Spreadsheet
    $pdo->exec("DELETE FROM hasil_absen");
    $absens = [
        ['2026-10-03', '2026/2027', 'Ganjil', '0061234504', 'Citra Kirana', 'P', 'XI DKV 1', 'H', 'Perangkat Lunak Desain', 'Rahmat Hidayat, S.Kom.'],
        ['2026-10-03', '2026/2027', 'Ganjil', '0061234501', 'Ahmad Fauzan', 'L', 'XI DKV 2', 'I', 'Desain Breif', 'Rahmat Hidayat, S.Kom.'],
        ['2026-10-03', '2026/2027', 'Ganjil', '0061234502', 'Anisa Rahmawati', 'P', 'XI DKV 2', 'S', 'Desain Breif', 'Rahmat Hidayat, S.Kom.'],
        ['2026-10-03', '2026/2027', 'Ganjil', '0061234501', 'Ahmad Fauzan', 'L', 'XI DKV 2', 'A', 'Kreativitas, Inovasi, dan Kewirausahaan', 'Rahmat Hidayat, S.Kom.'],
        ['2026-10-03', '2026/2027', 'Ganjil', '0061234502', 'Anisa Rahmawati', 'P', 'XI DKV 2', 'H', 'Kreativitas, Inovasi, dan Kewirausahaan', 'Rahmat Hidayat, S.Kom.']
    ];
    $stmtAbsen = $pdo->prepare("INSERT INTO hasil_absen (tanggal, tahun_ajaran, semester, nisn, nama_murid, jenis_kelamin, kelas, keterangan, mata_pelajaran, nama_guru) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($absens as $ab) {
        $stmtAbsen->execute($ab);
    }
    echo "✔ HasilAbsen disinkronkan.\n";

    // 8. HasilJurnal dari Spreadsheet
    $pdo->exec("DELETE FROM hasil_jurnal");
    $jurnals = [
        ['2026-10-02', '2026/2027', 'Ganjil', 'Rahmat Hidayat, S.Kom.', 'Perangkat Lunak Desain', '1-8', 'XI DKV 2', 1, 'Project Based Learning', 'Dasar Vektor & Desain Digital', 'Ahmad Fauzan, Anisa Rahmawati, test murid', 'tuntas', 'Selesai tepat waktu', 'https://drive.google.com/file/d/1ho10pJcvwXCvo2grDfcTVJ8vkGTvSl8S/view?usp=drivesdk'],
        ['2026-10-02', '2026/2027', 'Ganjil', 'Rahmat Hidayat, S.Kom.', 'Perangkat Lunak Desain', '1-8', 'XI DKV 2', 2, 'Discovery Learning', 'Pewarnaan dan Tipografi', 'Ahmad Fauzan (I), Anisa Rahmawati (S), test murid (D)', 'tuntas', 'Kompetensi tercapai', 'https://drive.google.com/file/d/1ivoFjCE-5j4oXYlbRkt7YWvoM2STCwfl/view?usp=drivesdk'],
        ['2026-10-03', '2026/2027', 'Ganjil', 'Rahmat Hidayat, S.Kom.', 'Perangkat Lunak Desain', '1-8', 'XI DKV 2', 3, 'Praktik Mandiri', 'Export Asset & Layout', 'Ahmad Fauzan (I), Anisa Rahmawati (S)', 'tuntas', 'Materi berlanjut sesi berikutnya', 'https://drive.google.com/file/d/1KliLCJO64mxH5XGCatMVNd1wlLwkk-Vm/view?usp=drivesdk'],
        ['2026-10-03', '2026/2027', 'Ganjil', 'Rahmat Hidayat, S.Kom.', 'Perangkat Lunak Desain', '1-8', 'XI DKV 1', 1, 'Pengenalan Tool Dasar', 'Interface Software & Tools', 'Citra Kirana (H)', 'tuntas', 'Peserta aktif praktik', 'https://drive.google.com/file/d/1RiHvFfNsB8vzX7V6JZoWvSPVxgm58izV/view?usp=drivesdk']
    ];
    $stmtJurnal = $pdo->prepare("INSERT INTO hasil_jurnal (tanggal, tahun_ajaran, semester, nama_guru, mata_pelajaran, jam_ke, kelas, pertemuan_ke, model_pembelajaran, materi_pembelajaran, murid_tidak_hadir, ketuntasan_materi, keterangan_tuntas, link_bukti_jurnal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($jurnals as $j) {
        $stmtJurnal->execute($j);
    }
    echo "✔ HasilJurnal disinkronkan.\n";

    // 9. HasilGuruWali dari Spreadsheet
    $pdo->exec("DELETE FROM hasil_guru_wali");
    $gwHasil = [
        ['2026-10-03', '2026/2027', 'Ganjil', 'Rahmat Hidayat, S.Kom.', 'Ahmad Fauzan, Citra Kirana', 'Kelompok', 'Pembinaan Kedisiplinan & Proyek Portofolio', 'Jadwal bimbingan intensif berkala', 'Tidak ada permasalahan yang signifikan', 'https://drive.google.com/file/d/14pdzomXFD2LaLjZf4jXyehfD6KsPe7PN/view?usp=drivesdk']
    ];
    $stmtGWH = $pdo->prepare("INSERT INTO hasil_guru_wali (tanggal, tahun_ajaran, semester, nama_guru_wali, nama_murid, kategori, topik_masalah, tindak_lanjut, keterangan, file_bukti) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($gwHasil as $gh) {
        $stmtGWH->execute($gh);
    }
    echo "✔ HasilGuruWali disinkronkan.\n";

    // 10. HasilWali dari Spreadsheet
    $pdo->exec("DELETE FROM hasil_wali");
    $waliHasil = [
        ['2026-10-03', '2026/2027', 'Ganjil', 'XI DKV 2', 'Budi Santoso, S.Pd.', 'Koordinasi Wali Kelas & Pembagian Tugas Piket', 'https://drive.google.com/file/d/1LwB7wgMeYfYdMWPGHjpxtVOj6nOc14kr/view?usp=drivesdk']
    ];
    $stmtWH = $pdo->prepare("INSERT INTO hasil_wali (tanggal, tahun_ajaran, semester, kelas, nama_wali_kelas, deskripsi_kegiatan, link_bukti_wali) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($waliHasil as $wh) {
        $stmtWH->execute($wh);
    }
    echo "✔ HasilWali disinkronkan.\n";

    echo "\n=== SINKRONISASI SELESAI DENGAN SUKSES! ===\n";

} catch (Exception $e) {
    die("Error sinkronisasi: " . $e->getMessage() . "\n");
}
