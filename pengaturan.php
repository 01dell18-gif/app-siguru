<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

if (!isSuperAdmin()) {
    setFlash('danger', 'Hanya Super Admin yang dapat mengakses Pengaturan Sistem.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();

// Export Master Data Pilihan (Tahun Ajaran, Kelas, Mapel, Ekstra) ke Excel (.xlsx / CSV)
$isMasterTemplate = (isset($_GET['export_master']) && in_array(strtolower((string)$_GET['export_master']), ['template', 'sample'])) || isset($_GET['template_master']);

if (isset($_GET['export_master']) || isset($_GET['template_master'])) {
    if ($isMasterTemplate) {
        $dummyTa = ['2025/2026', '2026/2027', '2027/2028'];
        $dummyKelas = ['X TKJ 1', 'X TKJ 2', 'X RPL 1', 'XI TKJ 1', 'XI RPL 1', 'XII TKJ 1', 'XII RPL 1'];
        $dummyMapel = [
            'Pendidikan Agama dan Budi Pekerti',
            'Pendidikan Pancasila',
            'Bahasa Indonesia',
            'Matematika',
            'Bahasa Inggris',
            'Informatika',
            'Projek IPAS',
            'Dasar-dasar Kejuruan',
            'Konsentrasi Keahlian',
            'Projek Kreatif dan Kewirausahaan'
        ];
        $dummyEkstra = ['Pramuka', 'PMR', 'Paskibra', 'Futsal', 'Basket', 'Voli', 'Rohis', 'Seni Tari', 'Musik / Band', 'Robotik'];

        $maxCount = max(count($dummyTa), count($dummyKelas), count($dummyMapel), count($dummyEkstra));
        $rows = [];
        for ($i = 0; $i < $maxCount; $i++) {
            $rows[] = [
                $i + 1,
                $dummyTa[$i] ?? '',
                $dummyKelas[$i] ?? '',
                $dummyMapel[$i] ?? '',
                $dummyEkstra[$i] ?? ''
            ];
        }
        $filenamePrefix = 'Template_Master_Pilihan_';
    } else {
        $taList = getDaftarTahunAjaran();
        $kelasList = getDaftarKelas();
        $mapelList = getDaftarMapel();
        $ekstraList = getDaftarEkstra();

        $maxCount = max(count($taList), count($kelasList), count($mapelList), count($ekstraList));
        if ($maxCount === 0) {
            $taList = ['2025/2026', '2026/2027'];
            $kelasList = ['X TKJ 1', 'XI RPL 1', 'XII MM 1'];
            $mapelList = ['Pendidikan Pancasila', 'Bahasa Indonesia', 'Matematika'];
            $ekstraList = ['Pramuka', 'PMR', 'Futsal'];
            $maxCount = 3;
        }

        $rows = [];
        for ($i = 0; $i < $maxCount; $i++) {
            $rows[] = [
                $i + 1,
                $taList[$i] ?? '',
                $kelasList[$i] ?? '',
                $mapelList[$i] ?? '',
                $ekstraList[$i] ?? ''
            ];
        }
        $filenamePrefix = 'Master_Pilihan_';
    }

    $reqVal = strtolower((string)($_GET['export_master'] ?? ($_GET['template_master'] ?? 'xlsx')));
    if ($reqVal === 'csv') {
        exportToExcelCSV($filenamePrefix . date('Ymd_His') . '.csv', ['No', 'Tahun Ajaran', 'Kelas / Rombel', 'Mata Pelajaran', 'Cabang Ekstrakurikuler'], $rows);
    } else {
        exportToExcelXLS($filenamePrefix . date('Ymd_His') . '.xlsx', ['No', 'Tahun Ajaran', 'Kelas / Rombel', 'Mata Pelajaran', 'Cabang Ekstrakurikuler'], $rows);
    }
}

// Import Master Data Pilihan (Tahun Ajaran, Kelas, Mapel, Ekstra) langsung ke database SQL
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'import_master') {
    if (isset($_FILES['master_file']) && $_FILES['master_file']['error'] === UPLOAD_ERR_OK) {
        $filePath = $_FILES['master_file']['tmp_name'];
        $rows = parseImportRows($filePath);
        $importMode = $_POST['import_mode'] ?? 'merge';

        if (!empty($rows)) {
            $header = array_shift($rows);
            $colTa = -1;
            $colKelas = -1;
            $colMapel = -1;
            $colEkstra = -1;
            $colKategori = -1;
            $colNama = -1;

            foreach ($header as $idx => $colName) {
                $cLower = strtolower(trim((string)$colName));
                if (strpos($cLower, 'tahun ajaran') !== false || $cLower === 'ta') {
                    $colTa = $idx;
                } elseif (strpos($cLower, 'kelas') !== false || strpos($cLower, 'rombel') !== false) {
                    $colKelas = $idx;
                } elseif (strpos($cLower, 'mata pelajaran') !== false || strpos($cLower, 'mapel') !== false) {
                    $colMapel = $idx;
                } elseif (strpos($cLower, 'ekstra') !== false) {
                    $colEkstra = $idx;
                } elseif (strpos($cLower, 'kategori') !== false) {
                    $colKategori = $idx;
                } elseif (strpos($cLower, 'nama') !== false || strpos($cLower, 'pilihan') !== false || strpos($cLower, 'item') !== false) {
                    $colNama = $idx;
                }
            }

            $importedTa = [];
            $importedKelas = [];
            $importedMapel = [];
            $importedEkstra = [];

            // Jika format 2 kolom: Kategori dan Nama Item
            if ($colKategori >= 0 && $colNama >= 0) {
                foreach ($rows as $r) {
                    $kat = strtolower(trim((string)($r[$colKategori] ?? '')));
                    $val = trim((string)($r[$colNama] ?? ''));
                    if ($val === '') continue;
                    if (strpos($kat, 'tahun') !== false || strpos($kat, 'ajaran') !== false) {
                        $importedTa[] = $val;
                    } elseif (strpos($kat, 'kelas') !== false || strpos($kat, 'rombel') !== false) {
                        $importedKelas[] = $val;
                    } elseif (strpos($kat, 'mapel') !== false || strpos($kat, 'pelajaran') !== false) {
                        $importedMapel[] = $val;
                    } elseif (strpos($kat, 'ekstra') !== false) {
                        $importedEkstra[] = $val;
                    }
                }
            } else {
                // Format tabel kolom per master
                foreach ($rows as $r) {
                    $sanitizeItem = function($val) {
                        $v = trim((string)$val);
                        if ($v === '' || $v === '[]' || in_array(strtolower($v), ['«', '»', '<', '>', '&lt;', '&gt;', '&lt', '&gt']) || stripos($v, 'c_rgszSh') !== false) {
                            return '';
                        }
                        return $v;
                    };
                    if ($colTa >= 0 && isset($r[$colTa])) {
                        $v = $sanitizeItem($r[$colTa]);
                        if ($v !== '') $importedTa[] = $v;
                    }
                    if ($colKelas >= 0 && isset($r[$colKelas])) {
                        $v = $sanitizeItem($r[$colKelas]);
                        if ($v !== '') $importedKelas[] = $v;
                    }
                    if ($colMapel >= 0 && isset($r[$colMapel])) {
                        $v = $sanitizeItem($r[$colMapel]);
                        if ($v !== '') $importedMapel[] = $v;
                    }
                    if ($colEkstra >= 0 && isset($r[$colEkstra])) {
                        $v = $sanitizeItem($r[$colEkstra]);
                        if ($v !== '') $importedEkstra[] = $v;
                    }
                }
            }

            if ($importMode === 'replace') {
                $finalTa = array_values(array_unique(array_filter($importedTa)));
                $finalKelas = array_values(array_unique(array_filter($importedKelas)));
                $finalMapel = array_values(array_unique(array_filter($importedMapel)));
                $finalEkstra = array_values(array_unique(array_filter($importedEkstra)));

                if (empty($finalTa)) $finalTa = getDaftarTahunAjaran();
                if (empty($finalKelas) && empty($importedKelas)) $finalKelas = getDaftarKelas();
                if (empty($finalMapel) && empty($importedMapel)) $finalMapel = getDaftarMapel();
                if (empty($finalEkstra) && empty($importedEkstra)) $finalEkstra = getDaftarEkstra();
            } else {
                $currTa = getDaftarTahunAjaran();
                $currKelas = getDaftarKelas();
                $currMapel = getDaftarMapel();
                $currEkstra = getDaftarEkstra();

                $finalTa = array_values(array_unique(array_filter(array_merge($currTa, $importedTa))));
                $finalKelas = array_values(array_unique(array_filter(array_merge($currKelas, $importedKelas))));
                $finalMapel = array_values(array_unique(array_filter(array_merge($currMapel, $importedMapel))));
                $finalEkstra = array_values(array_unique(array_filter(array_merge($currEkstra, $importedEkstra))));
            }

            // Simpan langsung ke database SQLite (TIDAK di lokal)
            $stmt = $pdo->prepare("UPDATE pengaturan SET list_tahun_ajaran = ?, kelas = ?, mata_pelajaran = ?, list_ekstrakurikuler = ? WHERE id = 1");
            $stmt->execute([
                json_encode($finalTa, JSON_UNESCAPED_UNICODE),
                json_encode($finalKelas, JSON_UNESCAPED_UNICODE),
                json_encode($finalMapel, JSON_UNESCAPED_UNICODE),
                json_encode($finalEkstra, JSON_UNESCAPED_UNICODE)
            ]);

            setFlash('success', 'Master data berhasil diimport dan disimpan permanen ke database SQL (' . count($finalTa) . ' Tahun Ajaran, ' . count($finalKelas) . ' Kelas, ' . count($finalMapel) . ' Mapel, ' . count($finalEkstra) . ' Ekstrakurikuler).');
        } else {
            setFlash('danger', 'Berkas import kosong atau format kolom tidak dikenali.');
        }
    } else {
        setFlash('danger', 'Gagal mengunggah berkas import.');
    }
    header("Location: pengaturan.php");
    exit;
}

// Tombol Sakti: Hapus / Kosongkan Database untuk Memulai Data Baru
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'clear_database') {
    if (!isSuperAdmin()) {
        setFlash('danger', 'Hanya Super Admin yang berhak mengosongkan database.');
        header("Location: dashboard.php");
        exit;
    }

    try {
        // A. Hapus seluruh berkas fisik bukti laporan & foto siswa dari folder uploads sebelum data dihapus
        $buktiTables = [
            ['hasil_jurnal', 'link_bukti_jurnal'],
            ['hasil_guru_wali', 'file_bukti'],
            ['hasil_wali', 'link_bukti_wali'],
            ['hasil_ekstra', 'link_bukti_ekstra'],
            ['data_murid_guru_wali', 'foto_murid']
        ];
        foreach ($buktiTables as [$tbl, $col]) {
            try {
                $rows = $pdo->query("SELECT $col FROM $tbl WHERE $col != '' AND $col IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($rows as $r) {
                    deleteOldUploadedFiles($r);
                }
            } catch (Exception $exBukti) {}
        }

        // B. Hapus berkas fisik logo, kop surat, dan TTD kepsek lama jika merupakan berkas upload lokal
        try {
            $curSettings = $pdo->query("SELECT logo, kop_surat, ttd_kepsek FROM pengaturan LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($curSettings) {
                if (!empty($curSettings['logo']) && strpos($curSettings['logo'], 'assets/') === false) {
                    deleteOldUploadedFiles($curSettings['logo']);
                }
                if (!empty($curSettings['kop_surat']) && strpos($curSettings['kop_surat'], 'assets/') === false) {
                    deleteOldUploadedFiles($curSettings['kop_surat']);
                }
                if (!empty($curSettings['ttd_kepsek']) && strpos($curSettings['ttd_kepsek'], 'assets/') === false) {
                    deleteOldUploadedFiles($curSettings['ttd_kepsek']);
                }
            }
        } catch (Exception $exSet) {}

        // C. Bersihkan seluruh file fisik yang pernah diunggah di folder uploads/ (foto profil, berkas dokumen, dll) agar bersih total
        clearAllUploadedFiles();

        $pdo->beginTransaction();

        // Nonaktifkan foreign keys sementara untuk operasi penghapusan massal
        if (DB_DRIVER === 'sqlite') {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
        }

        // 1. Hapus seluruh data transaksi & laporan kegiatan
        $pdo->exec("DELETE FROM hasil_absen");
        $pdo->exec("DELETE FROM hasil_jurnal");
        $pdo->exec("DELETE FROM hasil_guru_wali");
        $pdo->exec("DELETE FROM hasil_wali");
        $pdo->exec("DELETE FROM hasil_ekstra");

        // 2. Hapus seluruh master data siswa & guru
        $pdo->exec("DELETE FROM data_murid_ekstra");
        $pdo->exec("DELETE FROM data_murid_guru_wali");
        $pdo->exec("DELETE FROM data_murid");
        $pdo->exec("DELETE FROM data_guru");

        // 3. Hapus akun pengguna KECUALI Super Admin dan Pelihat (Hanya Laporan)
        // Akun Super Admin (misal SAdmin, ferryxz) dan Pelihat (viewer) tetap aman
        $pdo->exec("DELETE FROM data_user WHERE hak_akses NOT LIKE '%Super Admin%' AND hak_akses NOT LIKE '%Pelihat%' AND username NOT IN ('SAdmin', 'viewer')");

        // 4. Kembalikan Logo, Kop Surat, dan TTD Kepsek ke defaultnya, serta kosongkan daftar pilihan (Kelas, Mapel, Ekstra)
        $defaultLogo = 'assets/images/logo.png';
        $defaultKop  = 'assets/images/kop_surat.png';
        $defaultTtd  = 'https://drive.google.com/thumbnail?id=1GUfUommgYjV-cnhU0tTB8kRJy8iXagkI';
        
        $stmtReset = $pdo->prepare("UPDATE pengaturan SET 
            logo = ?, 
            kop_surat = ?, 
            kop_size = '85', 
            ttd_kepsek = ?, 
            kelas = '[]', 
            mata_pelajaran = '[]', 
            list_ekstrakurikuler = '[]'");
        $stmtReset->execute([$defaultLogo, $defaultKop, $defaultTtd]);

        // 5. Reset sequence auto-increment ID
        if (DB_DRIVER === 'sqlite') {
            try {
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('hasil_absen', 'hasil_jurnal', 'hasil_guru_wali', 'hasil_wali', 'hasil_ekstra', 'data_guru', 'data_murid', 'data_murid_guru_wali', 'data_murid_ekstra')");
            } catch (Exception $seqEx) {}
            $pdo->exec("PRAGMA foreign_keys = ON;");
        }

        $pdo->commit();

        $_SESSION['db_just_cleared'] = true;
        setFlash('success', 'Database berhasil dikosongkan! Silakan ikuti instruksi pengisian: lengkapi Pengaturan Sistem terlebih dahulu, kemudian Data Master.');
        header("Location: pengaturan.php?cleared=1");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('danger', 'Gagal membersihkan database: ' . $e->getMessage());
        header("Location: pengaturan.php");
        exit;
    }
}

// Update Pengaturan Sistem Sesuai PRD
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $namaSekolah = trim($_POST['nama_sekolah'] ?? '');
    $tahunAjaran = trim($_POST['tahun_ajaran'] ?? '');
    
    // Pastikan array list dibersihkan dari artefak korup
    $arrTa = getListOptions($_POST['list_tahun_ajaran'] ?? '');
    $arrKelas = getListOptions($_POST['kelas'] ?? '');
    $arrMapel = getListOptions($_POST['mata_pelajaran'] ?? '');
    $arrEkstra = getListOptions($_POST['list_ekstrakurikuler'] ?? '');

    $listTahunAjaran = json_encode($arrTa, JSON_UNESCAPED_UNICODE);
    $kelas = json_encode($arrKelas, JSON_UNESCAPED_UNICODE);
    $mapel = json_encode($arrMapel, JSON_UNESCAPED_UNICODE);
    $listEkstra = json_encode($arrEkstra, JSON_UNESCAPED_UNICODE);

    $semester = trim($_POST['semester'] ?? '');
    $kepsek = trim($_POST['kepala_sekolah'] ?? '');
    $nipKepsek = trim($_POST['nip_kepala_sekolah'] ?? '');

    // Ambil data identitas yang tersimpan di database saat ini agar akurat
    $sCurrent = $pdo->query("SELECT logo, kop_surat, ttd_kepsek FROM pengaturan LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $currentLogo = $sCurrent['logo'] ?? trim($_POST['logo_existing'] ?? '');
    $currentKop  = $sCurrent['kop_surat'] ?? trim($_POST['kop_surat_existing'] ?? '');
    $currentTtd  = $sCurrent['ttd_kepsek'] ?? trim($_POST['ttd_kepsek_existing'] ?? '');

    // 1. Upload Logo baru jika ada
    $uploadedLogo = handleFileUpload('logo_file', 'photos');
    if ($uploadedLogo) {
        // Hapus berkas fisik logo lama jika ada dan bukan berkas aset bawaan
        if (!empty($currentLogo) && strpos($currentLogo, 'assets/') === false) {
            deleteOldUploadedFiles($currentLogo);
        }
        $logo = $uploadedLogo;
    } else {
        $logo = !empty($currentLogo) ? $currentLogo : 'assets/images/logo.png';
    }

    // 2. Penanganan Kop Surat berdasarkan switch button (upload file vs tautan Google Drive)
    $kopMode = trim($_POST['kop_mode'] ?? 'upload');
    if ($kopMode === 'url') {
        $kopUrlInput = trim($_POST['kop_surat_url'] ?? '');
        if (!empty($kopUrlInput)) {
            // Jika beralih atau mengganti tautan, hapus berkas fisik kop lama jika ada
            if (!empty($currentKop) && strpos($currentKop, 'assets/') === false) {
                deleteOldUploadedFiles($currentKop);
            }
            $kopSurat = $kopUrlInput;
        } else {
            $kopSurat = !empty($currentKop) ? $currentKop : 'assets/images/kop_surat.png';
        }
    } else {
        $uploadedKop = handleFileUpload('kop_surat_file', 'photos');
        if ($uploadedKop) {
            // Berkas fisik kop surat baru berhasil diunggah: hapus berkas fisik kop lama
            if (!empty($currentKop) && strpos($currentKop, 'assets/') === false) {
                deleteOldUploadedFiles($currentKop);
            }
            $kopSurat = $uploadedKop;
        } else {
            $kopSurat = !empty($currentKop) ? $currentKop : 'assets/images/kop_surat.png';
        }
    }

    $kopSize = trim($_POST['kop_size'] ?? '85');
    if (!in_array($kopSize, ['70', '85', '105', '125'])) {
        $kopSize = '85';
    }

    $kabupaten = trim($_POST['kabupaten'] ?? 'Klungkung');

    // 3. Penanganan TTD Kepsek (upload file vs tautan Google Drive / hapus)
    if (!empty($_POST['hapus_ttd'])) {
        // Pengguna memilih mengosongkan TTD digital: hapus berkas fisik TTD lama jika ada
        if (!empty($currentTtd) && strpos($currentTtd, 'assets/') === false) {
            deleteOldUploadedFiles($currentTtd);
        }
        $ttdKepsek = '';
    } else {
        $ttdMode = trim($_POST['ttd_mode'] ?? 'upload');
        if ($ttdMode === 'url') {
            $ttdUrlInput = trim($_POST['ttd_kepsek_url'] ?? '');
            if (!empty($ttdUrlInput)) {
                // Memasukkan tautan link drive: hapus berkas fisik TTD lama jika ada
                if (!empty($currentTtd) && strpos($currentTtd, 'assets/') === false) {
                    deleteOldUploadedFiles($currentTtd);
                }
                $ttdKepsek = $ttdUrlInput;
            } else {
                $ttdKepsek = $currentTtd;
            }
        } else {
            $uploadedTtd = handleFileUpload('ttd_kepsek_file', 'photos');
            if ($uploadedTtd) {
                // Berkas fisik TTD baru berhasil diunggah: hapus berkas fisik TTD lama
                if (!empty($currentTtd) && strpos($currentTtd, 'assets/') === false) {
                    deleteOldUploadedFiles($currentTtd);
                }
                $ttdKepsek = $uploadedTtd;
            } else {
                $ttdKepsek = $currentTtd;
            }
        }
    }

    $existingId = $pdo->query("SELECT id FROM pengaturan LIMIT 1")->fetchColumn();
    if ($existingId) {
        $stmt = $pdo->prepare("UPDATE pengaturan SET 
            logo = ?, nama_sekolah = ?, tahun_ajaran = ?, list_tahun_ajaran = ?, semester = ?, kelas = ?, mata_pelajaran = ?,
            list_ekstrakurikuler = ?, kepala_sekolah = ?, nip_kepala_sekolah = ?, kop_surat = ?, kop_size = ?,
            kabupaten = ?, ttd_kepsek = ?
            WHERE id = ?");

        $stmt->execute([
            $logo, $namaSekolah, $tahunAjaran, $listTahunAjaran, $semester, $kelas, $mapel,
            $listEkstra, $kepsek, $nipKepsek, $kopSurat, $kopSize,
            $kabupaten, $ttdKepsek, $existingId
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pengaturan (
            id, logo, nama_sekolah, tahun_ajaran, list_tahun_ajaran, semester, kelas, mata_pelajaran,
            list_ekstrakurikuler, kepala_sekolah, nip_kepala_sekolah, kop_surat, kop_size,
            kabupaten, ttd_kepsek
        ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $logo, $namaSekolah, $tahunAjaran, $listTahunAjaran, $semester, $kelas, $mapel,
            $listEkstra, $kepsek, $nipKepsek, $kopSurat, $kopSize,
            $kabupaten, $ttdKepsek
        ]);
    }

    // Refresh global settings cache
    global $settings;
    $settings = getSettings();

    setFlash('success', 'Seluruh data Pengaturan Sistem berhasil disimpan ke database.');

    $redirectTo = trim($_POST['redirect_to'] ?? '');
    if (!empty($redirectTo) && !preg_match('/^(javascript|data):/i', $redirectTo) && strpos($redirectTo, 'logout.php') === false) {
        header("Location: " . $redirectTo);
    } else {
        header("Location: pengaturan.php");
    }
    exit;
}

$pageTitle = 'Pengaturan Sistem';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

$s = getSettings();
$taList = getListOptions($s['list_tahun_ajaran'] ?? '');
if (empty($taList)) {
    $taList = ['2025/2026', '2026/2027', '2027/2028'];
}
if (!empty($s['tahun_ajaran']) && !in_array($s['tahun_ajaran'], $taList)) {
    $taList[] = $s['tahun_ajaran'];
}
$kelasList = getListOptions($s['kelas'] ?? '');
$mapelList = getListOptions($s['mata_pelajaran'] ?? '');
$ekstraList = getListOptions($s['list_ekstrakurikuler'] ?? '');
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
            <h5 class="fw-bold text-dark mb-0">Pengaturan Sistem</h5>
            <small class="text-muted">Kelola identitas sekolah, periode akademik, kop surat, dan daftar pilihan master.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-xs fw-semibold d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalHapusData">
                <i class="bi bi-trash3-fill text-danger"></i>
                <span>Tombol Sakti: Hapus Data</span>
            </button>
        </div>
    </div>

    <?php if (isset($_GET['cleared']) || isset($_SESSION['db_just_cleared'])): ?>
    <?php unset($_SESSION['db_just_cleared']); ?>
    <!-- Instruksi Alur Pengisian Baru Pasca Pembersihan Database -->
    <div class="card border-0 shadow-sm mb-4 overflow-hidden" style="border-radius: 14px; background: linear-gradient(135deg, #f0fdf4 0%, #eff6ff 100%); border-left: 6px solid #16a34a !important;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-start gap-3">
                <div class="p-2.5 bg-success text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 44px; height: 44px;">
                    <i class="bi bi-check-lg fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h5 class="fw-bold text-success-emphasis mb-0 d-flex align-items-center gap-2">
                            <span>Database Berhasil Dikosongkan!</span>
                        </h5>
                        <span class="badge bg-success text-white px-2.5 py-1 rounded-pill" style="font-size: 0.75rem;">Status: Siap Data Baru</span>
                    </div>
                    <p class="text-dark small mb-3" style="font-size: 0.88rem;">
                        Seluruh rekaman laporan transaksi, data master lama, dan akun guru/wali telah dibersihkan. Akun <strong>Super Admin</strong>, <strong>Pelihat (Hanya Laporan)</strong>, serta konfigurasi identitas sekolah (<strong>Logo, Sekolah, Semester, TTD Kepsek</strong>) tetap aman dipertahankan.
                    </p>

                    <!-- Panduan Langkah Alur Pengisian Baru -->
                    <div class="bg-white rounded-3 p-3 border shadow-xs mb-0">
                        <h6 class="fw-bold text-dark mb-2.5 d-flex align-items-center gap-2" style="font-size: 0.92rem;">
                            <i class="bi bi-signpost-split-fill text-primary"></i> PANDUAN ALUR PENGISIAN DATA BARU (IKUTI URUTAN INI):
                        </h6>
                        <div class="row g-3">
                            <!-- Langkah 1 -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 border h-100" style="background-color: #f8fafc; border-left: 4px solid #2563eb !important;">
                                    <div class="d-flex align-items-center gap-2 mb-1.5">
                                        <span class="badge bg-primary rounded-pill px-2 py-0.5" style="font-size: 0.72rem;">LANGKAH 1 (Wajib Pertama)</span>
                                    </div>
                                    <h6 class="fw-bold text-primary mb-1" style="font-size: 0.9rem;">Isi & Simpan Pengaturan Terlebih Dahulu</h6>
                                    <p class="text-secondary small mb-2" style="font-size: 0.82rem; line-height: 1.45;">
                                        Sebelum mengisi data guru dan murid, lengkapi pengaturan dasar pada formulir di bawah ini:
                                    </p>
                                    <ul class="small text-secondary ps-3 mb-3" style="font-size: 0.82rem; line-height: 1.5;">
                                        <li>Periksa <strong>Tahun Ajaran</strong> & <strong>Semester</strong> baru.</li>
                                        <li>Tambahkan <strong>Daftar Rombel / Kelas</strong> baru.</li>
                                        <li>Tambahkan <strong>Daftar Mata Pelajaran</strong> baru.</li>
                                        <li>Tambahkan <strong>Cabang Ekstrakurikuler</strong> baru.</li>
                                        <li>Klik <strong>Simpan Semua Pengaturan</strong> di bawah.</li>
                                    </ul>
                                    <a href="#input_kelas" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs" style="font-size: 0.78rem;">
                                        <i class="bi bi-arrow-down-circle me-1"></i> Mulai Isi Pengaturan di Bawah
                                    </a>
                                </div>
                            </div>

                            <!-- Langkah 2 -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 border h-100" style="background-color: #f8fafc; border-left: 4px solid #16a34a !important;">
                                    <div class="d-flex align-items-center gap-2 mb-1.5">
                                        <span class="badge bg-success rounded-pill px-2 py-0.5" style="font-size: 0.72rem;">LANGKAH 2 (Setelah Pengaturan)</span>
                                    </div>
                                    <h6 class="fw-bold text-success mb-1" style="font-size: 0.9rem;">Kemudian Isi Seluruh Data Master</h6>
                                    <p class="text-secondary small mb-2" style="font-size: 0.82rem; line-height: 1.45;">
                                        Setelah Pengaturan Sistem tersimpan, lanjutkan mengisi data master secara berurutan:
                                    </p>
                                    <div class="d-flex flex-column gap-1.5 mb-2">
                                        <a href="master-guru.php" class="text-decoration-none small text-dark p-1.5 rounded border bg-white d-flex align-items-center justify-content-between hover-bg-light">
                                            <span><i class="bi bi-person-workspace text-primary me-2"></i><strong>1. Data Guru</strong> (Pengajar, Wali & Pembina)</span>
                                            <i class="bi bi-chevron-right text-muted small"></i>
                                        </a>
                                        <a href="master-murid.php" class="text-decoration-none small text-dark p-1.5 rounded border bg-white d-flex align-items-center justify-content-between hover-bg-light">
                                            <span><i class="bi bi-people text-success me-2"></i><strong>2. Data Siswa</strong> (Input / Import data siswa)</span>
                                            <i class="bi bi-chevron-right text-muted small"></i>
                                        </a>
                                        <a href="master-bimbingan.php" class="text-decoration-none small text-dark p-1.5 rounded border bg-white d-flex align-items-center justify-content-between hover-bg-light">
                                            <span><i class="bi bi-person-heart text-danger me-2"></i><strong>3. Murid Guru Wali</strong> (Distribusi binaan)</span>
                                            <i class="bi bi-chevron-right text-muted small"></i>
                                        </a>
                                        <a href="master-ekstra.php" class="text-decoration-none small text-dark p-1.5 rounded border bg-white d-flex align-items-center justify-content-between hover-bg-light">
                                            <span><i class="bi bi-trophy text-warning me-2"></i><strong>4. Murid Ekstrakurikuler</strong> (Pendaftaran anggota)</span>
                                            <i class="bi bi-chevron-right text-muted small"></i>
                                        </a>
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.74rem;">
                                        <i class="bi bi-info-circle me-1"></i>Akun login guru juga dapat dibuat di menu <a href="user-management.php" class="fw-semibold text-primary">Manajemen User</a>.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="pengaturan.php" enctype="multipart/form-data" id="formPengaturan">
        <input type="hidden" name="redirect_to" id="redirect_to" value="">
        <input type="hidden" name="logo_existing" value="<?= htmlspecialchars($s['logo'] ?? '') ?>">
        <input type="hidden" name="kop_surat_existing" value="<?= htmlspecialchars($s['kop_surat'] ?? 'assets/images/kop_surat.png') ?>">
        <input type="hidden" name="ttd_kepsek_existing" value="<?= htmlspecialchars($s['ttd_kepsek'] ?? '') ?>">

        <!-- 1. Identitas Sekolah & Akademik -->
        <div class="card card-table p-3 mb-3">
            <div class="card-header-clean">
                <h6 class="card-title-clean">
                    <i class="bi bi-building text-primary"></i> Identitas Sekolah & Periode Akademik
                </h6>
            </div>
            <div class="row g-3 align-items-center">
                <div class="col-md-3 col-lg-2 text-center border-end pe-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1 d-block">Logo Sekolah</label>
                    <div class="p-2 bg-light rounded-3 border d-inline-block mb-2 shadow-xs">
                        <img src="<?= htmlspecialchars($s['logo'] ?? 'assets/images/logo.png') ?>" alt="Logo" style="max-height: 65px; max-width: 65px; object-fit: contain;">
                    </div>
                    <div class="px-1">
                        <input type="file" name="logo_file" class="form-control form-control-sm" accept="image/*" title="Pilih berkas logo baru">
                    </div>
                </div>
                <div class="col-md-9 col-lg-10">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Nama Sekolah (Huruf Kapital)</label>
                            <input type="text" name="nama_sekolah" class="form-control fw-semibold" value="<?= htmlspecialchars($s['nama_sekolah'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kabupaten / Wilayah Titimangsa</label>
                            <input type="text" name="kabupaten" class="form-control" value="<?= htmlspecialchars($s['kabupaten'] ?? 'Klungkung') ?>" placeholder="Contoh: Klungkung" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-flex align-items-center justify-content-between">
                                <span>Tahun Ajaran Aktif</span>
                                <a href="#input_list_tahun_ajaran" class="text-primary small text-decoration-none" title="Kelola Daftar Tahun Ajaran" style="font-size: 0.72rem;">+ Kelola List</a>
                            </label>
                            <select name="tahun_ajaran" id="select_tahun_ajaran_aktif" class="form-select fw-semibold text-primary" required>
                                <?php foreach ($taList as $taItem): ?>
                                    <option value="<?= htmlspecialchars($taItem) ?>" <?= ($s['tahun_ajaran'] ?? '') === $taItem ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($taItem) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="Ganjil" <?= ($s['semester'] ?? '') === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                                <option value="Genap" <?= ($s['semester'] ?? '') === 'Genap' ? 'selected' : '' ?>>Genap</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nama Kepala Sekolah</label>
                            <input type="text" name="kepala_sekolah" class="form-control" value="<?= htmlspecialchars($s['kepala_sekolah'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">NIP Kepala Sekolah</label>
                            <input type="text" name="nip_kepala_sekolah" class="form-control" value="<?= htmlspecialchars($s['nip_kepala_sekolah'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Kop Surat Resmi Dokumen -->
        <div class="card card-table p-3 mb-3">
            <div class="card-header-clean">
                <h6 class="card-title-clean">
                    <i class="bi bi-file-earmark-image text-primary"></i> Kop Surat Resmi Dokumen (Preview & Cetak PDF)
                </h6>
            </div>

            <div class="p-2 bg-light rounded border mb-2 text-center">
                <img src="<?= htmlspecialchars(formatPhotoUrl($s['kop_surat'] ?? 'assets/images/kop_surat.png')) ?>" 
                     alt="Preview Kop Surat" 
                     class="img-fluid mx-auto d-block" 
                     style="max-height: <?= (int)($s['kop_size'] ?? 85) ?>px; max-width: min(100%, 500px); object-fit: contain;">
            </div>

            <?php $isUrlMode = (strpos($s['kop_surat'] ?? '', 'http') === 0); ?>
            <div class="row align-items-center mb-2 g-2">
                <div class="col-md-8">
                    <label class="form-label small mb-1">Metode Input Kop Surat</label>
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <input type="radio" class="btn-check" name="kop_mode" id="mode_upload" value="upload" autocomplete="off" <?= !$isUrlMode ? 'checked' : '' ?> onchange="switchKopMode('upload')">
                        <label class="btn <?= !$isUrlMode ? 'btn-primary' : 'btn-outline-primary' ?>" for="mode_upload" id="lbl_mode_upload">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Unggah File Gambar
                        </label>

                        <input type="radio" class="btn-check" name="kop_mode" id="mode_url" value="url" autocomplete="off" <?= $isUrlMode ? 'checked' : '' ?> onchange="switchKopMode('url')">
                        <label class="btn <?= $isUrlMode ? 'btn-primary' : 'btn-outline-primary' ?>" for="mode_url" id="lbl_mode_url">
                            <i class="bi bi-link-45deg me-1"></i> Tautan / Link Google Drive
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Tinggi Kop PDF</label>
                    <select name="kop_size" class="form-select form-select-sm">
                        <option value="70" <?= ($s['kop_size'] ?? '') === '70' ? 'selected' : '' ?>>Kecil (70px)</option>
                        <option value="85" <?= in_array($s['kop_size'] ?? '85', ['85', '90', '']) ? 'selected' : '' ?>>Proporsional (85px)</option>
                        <option value="105" <?= ($s['kop_size'] ?? '') === '105' ? 'selected' : '' ?>>Sedang (105px)</option>
                        <option value="125" <?= ($s['kop_size'] ?? '') === '125' ? 'selected' : '' ?>>Besar (125px)</option>
                    </select>
                </div>
            </div>

            <!-- Panel Upload Kop -->
            <div id="panel_upload" class="p-2 bg-light rounded border <?= $isUrlMode ? 'd-none' : '' ?>">
                <input type="file" name="kop_surat_file" class="form-control form-control-sm" accept="image/*">
                <small class="text-muted" style="font-size: 0.75rem;">Mendukung format PNG, JPG, WEBP.</small>
            </div>

            <!-- Panel URL Kop -->
            <div id="panel_url" class="p-2 bg-light rounded border <?= !$isUrlMode ? 'd-none' : '' ?>">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                    <input type="text" name="kop_surat_url" class="form-control" placeholder="https://drive.google.com/file/d/..." value="<?= $isUrlMode ? htmlspecialchars($s['kop_surat']) : '' ?>">
                </div>
                <small class="text-muted" style="font-size: 0.75rem;">Gunakan link file sharing publik Google Drive atau link gambar web.</small>
            </div>
        </div>

        <!-- 3. Tanda Tangan Digital Kepala Sekolah -->
        <div class="card card-table p-3 mb-3">
            <div class="card-header-clean">
                <h6 class="card-title-clean">
                    <i class="bi bi-pen text-primary"></i> Tanda Tangan Digital Kepala Sekolah (TTD Kepsek)
                </h6>
            </div>

            <div class="p-2 bg-light rounded border mb-2 text-center">
                <?php if (!empty($s['ttd_kepsek'])): ?>
                    <div class="p-2 bg-white rounded border d-inline-block shadow-xs" style="min-width: 200px;">
                        <img src="<?= htmlspecialchars(formatPhotoUrl($s['ttd_kepsek'])) ?>" 
                             alt="TTD Kepala Sekolah" 
                             class="img-fluid mx-auto d-block" 
                             style="max-height: 65px; object-fit: contain;">
                        <div class="border-top pt-1 mt-1 small">
                            <span class="fw-semibold text-dark d-block"><?= htmlspecialchars($s['kepala_sekolah'] ?? 'Kepala Sekolah') ?></span>
                            <span class="text-muted" style="font-size: 0.75rem;">NIP. <?= htmlspecialchars($s['nip_kepala_sekolah'] ?? '-') ?></span>
                        </div>
                    </div>
                    <div class="form-check d-flex justify-content-center mt-2 mb-0">
                        <input class="form-check-input me-1" type="checkbox" name="hapus_ttd" value="1" id="hapus_ttd">
                        <label class="form-check-label text-danger small" for="hapus_ttd">
                            Kosongkan TTD Digital (Gunakan TTD Basah)
                        </label>
                    </div>
                <?php else: ?>
                    <span class="text-muted small fst-italic py-1 d-block">Belum ada TTD Digital terpasang (kolom tanda tangan laporan akan dibiarkan kosong untuk tanda tangan basah).</span>
                <?php endif; ?>
            </div>

            <?php $isTtdUrlMode = (strpos($s['ttd_kepsek'] ?? '', 'http') === 0); ?>
            <div class="mb-2">
                <label class="form-label small mb-1">Metode Input TTD</label>
                <div class="btn-group btn-group-sm w-100" role="group">
                    <input type="radio" class="btn-check" name="ttd_mode" id="mode_ttd_upload" value="upload" autocomplete="off" <?= !$isTtdUrlMode ? 'checked' : '' ?> onchange="switchTtdMode('upload')">
                    <label class="btn <?= !$isTtdUrlMode ? 'btn-primary' : 'btn-outline-primary' ?>" for="mode_ttd_upload" id="lbl_mode_ttd_upload">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Unggah File Gambar TTD
                    </label>

                    <input type="radio" class="btn-check" name="ttd_mode" id="mode_ttd_url" value="url" autocomplete="off" <?= $isTtdUrlMode ? 'checked' : '' ?> onchange="switchTtdMode('url')">
                    <label class="btn <?= $isTtdUrlMode ? 'btn-primary' : 'btn-outline-primary' ?>" for="mode_ttd_url" id="lbl_mode_ttd_url">
                        <i class="bi bi-link-45deg me-1"></i> Link Google Drive TTD
                    </label>
                </div>
            </div>

            <!-- Panel Upload TTD -->
            <div id="panel_ttd_upload" class="p-2 bg-light rounded border <?= $isTtdUrlMode ? 'd-none' : '' ?>">
                <input type="file" name="ttd_kepsek_file" class="form-control form-control-sm" accept="image/*">
                <small class="text-muted" style="font-size: 0.75rem;">Disarankan format PNG transparan.</small>
            </div>

            <!-- Panel URL TTD -->
            <div id="panel_ttd_url" class="p-2 bg-light rounded border <?= !$isTtdUrlMode ? 'd-none' : '' ?>">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                    <input type="text" name="ttd_kepsek_url" class="form-control" placeholder="https://drive.google.com/..." value="<?= $isTtdUrlMode ? htmlspecialchars($s['ttd_kepsek']) : '' ?>">
                </div>
                <small class="text-muted" style="font-size: 0.75rem;">Link gambar file Google Drive yang dibagikan secara publik.</small>
            </div>
        </div>

        <!-- 4. Master Data Pilihan (Tahun Ajaran, Kelas, Mapel, Ekstra) -->
        <div class="card card-table p-3 mb-3">
            <div class="card-header-clean d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h6 class="card-title-clean mb-0">
                    <i class="bi bi-tags text-primary"></i> Daftar Master Pilihan (Tahun Ajaran, Kelas, Mapel & Ekstrakurikuler)
                </h6>
                <div class="d-flex gap-2">
                    <a href="?export_master=xlsx" class="btn btn-outline-success btn-sm rounded-pill px-3" data-no-warn="1">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export Master (.xlsx)
                    </a>
                    <a href="?export_master=template" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-no-warn="1" title="Unduh template Excel dengan contoh data dummy">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template (.xlsx)
                    </a>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalImportMaster">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Import Master
                    </button>
                </div>
            </div>

            <div class="row g-3">
                <!-- 0. Daftar Tahun Ajaran -->
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label small fw-semibold text-dark mb-0">
                            <i class="bi bi-calendar-range me-1 text-primary"></i> Daftar Pilihan Tahun Ajaran
                        </label>
                        <span class="badge bg-light text-secondary border fw-normal" id="count_list_tahun_ajaran" style="font-size: 0.72rem;">0 item</span>
                    </div>
                    <div class="input-group input-group-sm mb-1">
                        <input type="text" id="input_list_tahun_ajaran" class="form-control" placeholder="Ketik tahun ajaran (misal: 2026/2027) lalu tekan Enter atau klik Tambah...">
                        <button type="button" class="btn btn-primary px-3" onclick="addTag('list_tahun_ajaran')">
                            <i class="bi bi-plus-lg me-1"></i> Tambah
                        </button>
                    </div>
                    <div id="tags_list_tahun_ajaran" class="p-2 bg-light border rounded d-flex flex-wrap gap-1 align-items-center mb-1" style="min-height: 40px;"></div>
                    <input type="hidden" name="list_tahun_ajaran" id="val_list_tahun_ajaran" value="<?= htmlspecialchars(json_encode($taList)) ?>">
                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                        <i class="bi bi-info-circle me-1"></i>Daftar tahun ajaran di atas akan otomatis muncul sebagai dropdown pilihan pada semua filter laporan dan pengaturan. Klik tanda <strong>"x"</strong> merah pada badge untuk menghapus.
                    </small>
                </div>

                <!-- 1. Daftar Kelas -->
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label small fw-semibold text-dark mb-0">
                            <i class="bi bi-door-open me-1 text-primary"></i> Daftar Rombel / Kelas
                        </label>
                        <span class="badge bg-light text-secondary border fw-normal" id="count_kelas" style="font-size: 0.72rem;">0 item</span>
                    </div>
                    <div class="input-group input-group-sm mb-1">
                        <input type="text" id="input_kelas" class="form-control" placeholder="Ketik nama kelas (misal: X RPL 1) lalu tekan Enter atau klik Tambah...">
                        <button type="button" class="btn btn-primary px-3" onclick="addTag('kelas')">
                            <i class="bi bi-plus-lg me-1"></i> Tambah
                        </button>
                    </div>
                    <div id="tags_kelas" class="p-2 bg-light border rounded d-flex flex-wrap gap-1 align-items-center mb-1" style="min-height: 40px;"></div>
                    <input type="hidden" name="kelas" id="val_kelas" value="<?= htmlspecialchars(json_encode($kelasList)) ?>">
                </div>

                <!-- 2. Daftar Mata Pelajaran -->
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label small fw-semibold text-dark mb-0">
                            <i class="bi bi-book-half me-1 text-success"></i> Daftar Mata Pelajaran
                        </label>
                        <span class="badge bg-light text-secondary border fw-normal" id="count_mata_pelajaran" style="font-size: 0.72rem;">0 item</span>
                    </div>
                    <div class="input-group input-group-sm mb-1">
                        <input type="text" id="input_mata_pelajaran" class="form-control" placeholder="Ketik mata pelajaran lalu tekan Enter atau klik Tambah...">
                        <button type="button" class="btn btn-success px-3" onclick="addTag('mata_pelajaran')">
                            <i class="bi bi-plus-lg me-1"></i> Tambah
                        </button>
                    </div>
                    <div id="tags_mata_pelajaran" class="p-2 bg-light border rounded d-flex flex-wrap gap-1 align-items-center mb-1" style="min-height: 40px;"></div>
                    <input type="hidden" name="mata_pelajaran" id="val_mata_pelajaran" value="<?= htmlspecialchars(json_encode($mapelList)) ?>">
                </div>

                <!-- 3. Daftar Ekstrakurikuler -->
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label small fw-semibold text-dark mb-0">
                            <i class="bi bi-trophy me-1 text-warning"></i> Daftar Cabang Ekstrakurikuler
                        </label>
                        <span class="badge bg-light text-secondary border fw-normal" id="count_list_ekstrakurikuler" style="font-size: 0.72rem;">0 item</span>
                    </div>
                    <div class="input-group input-group-sm mb-1">
                        <input type="text" id="input_list_ekstrakurikuler" class="form-control" placeholder="Ketik cabang ekstra lalu tekan Enter atau klik Tambah...">
                        <button type="button" class="btn btn-primary px-3" onclick="addTag('list_ekstrakurikuler')">
                            <i class="bi bi-plus-lg me-1"></i> Tambah
                        </button>
                    </div>
                    <div id="tags_list_ekstrakurikuler" class="p-2 bg-light border rounded d-flex flex-wrap gap-1 align-items-center mb-1" style="min-height: 40px;"></div>
                    <input type="hidden" name="list_ekstrakurikuler" id="val_list_ekstrakurikuler" value="<?= htmlspecialchars(json_encode($ekstraList)) ?>">
                </div>
            </div>
        </div>

        <!-- Tombol Simpan Kompak -->
        <div class="d-flex justify-content-end align-items-center gap-2 mt-3 mb-4">
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm rounded-pill">
                <i class="bi bi-check2-circle me-1"></i> Simpan Semua Pengaturan
            </button>
        </div>
    </form>

    <!-- 5. Zona Bahaya: Tombol Sakti Hapus / Kosongkan Database -->
    <div class="card border-danger border-opacity-25 shadow-sm mb-4" style="background-color: #fffbfa; border-radius: 14px;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="p-2.5 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px;">
                        <i class="bi bi-trash3-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="fw-bold text-danger mb-0">Zona Pembersihan Database (Tombol Sakti "Hapus Data")</h6>
                            <span class="badge bg-danger text-white px-2 py-0.5 rounded-pill" style="font-size: 0.7rem;">Super Admin Only</span>
                        </div>
                        <p class="text-secondary small mb-1.5">
                            Gunakan tombol sakti ini untuk membersihkan dan mengosongkan database agar siap digunakan dengan data baru (misal pergantian tahun ajaran).
                        </p>
                        <div class="small d-flex flex-wrap align-items-center gap-3">
                            <span class="text-danger-emphasis"><i class="bi bi-trash3 me-1 text-danger"></i><strong>Dikosongkan:</strong> Presensi, Jurnal, Bimbingan Wali, Ekstra, Data Master, Akun Guru/Wali.</span>
                            <span class="text-success-emphasis"><i class="bi bi-shield-check me-1 text-success"></i><strong>Tetap Aman:</strong> Super Admin, Pelihat (Hanya Laporan), Logo, Sekolah, Semester, TTD Kepsek.</span>
                        </div>
                    </div>
                </div>
                <div class="flex-shrink-0 text-md-end">
                    <button type="button" class="btn btn-danger px-4 py-2 fw-semibold rounded-pill shadow-sm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalHapusData">
                        <i class="bi bi-trash3-fill"></i>
                        <span>Tombol Sakti: Hapus Data</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function switchKopMode(mode) {
    const pUpload = document.getElementById('panel_upload');
    const pUrl = document.getElementById('panel_url');
    const lblUpload = document.getElementById('lbl_mode_upload');
    const lblUrl = document.getElementById('lbl_mode_url');

    if (mode === 'upload') {
        pUpload.classList.remove('d-none');
        pUrl.classList.add('d-none');
        lblUpload.classList.add('btn-primary');
        lblUpload.classList.remove('btn-outline-primary');
        lblUrl.classList.remove('btn-primary');
        lblUrl.classList.add('btn-outline-primary');
    } else {
        pUpload.classList.add('d-none');
        pUrl.classList.remove('d-none');
        lblUrl.classList.add('btn-primary');
        lblUrl.classList.remove('btn-outline-primary');
        lblUpload.classList.remove('btn-primary');
        lblUpload.classList.add('btn-outline-primary');
    }
}

function switchTtdMode(mode) {
    const pUpload = document.getElementById('panel_ttd_upload');
    const pUrl = document.getElementById('panel_ttd_url');
    const lblUpload = document.getElementById('lbl_mode_ttd_upload');
    const lblUrl = document.getElementById('lbl_mode_ttd_url');

    if (mode === 'upload') {
        pUpload.classList.remove('d-none');
        pUrl.classList.add('d-none');
        lblUpload.classList.add('btn-primary');
        lblUpload.classList.remove('btn-outline-primary');
        lblUrl.classList.remove('btn-primary');
        lblUrl.classList.add('btn-outline-primary');
    } else {
        pUpload.classList.add('d-none');
        pUrl.classList.remove('d-none');
        lblUrl.classList.add('btn-primary');
        lblUrl.classList.remove('btn-outline-primary');
        lblUpload.classList.remove('btn-primary');
        lblUpload.classList.add('btn-outline-primary');
    }
}

// Data Master Pilihan (Tags / Chips Manager)
const masterData = {
    list_tahun_ajaran: <?= json_encode($taList) ?>,
    kelas: <?= json_encode($kelasList) ?>,
    mata_pelajaran: <?= json_encode($mapelList) ?>,
    list_ekstrakurikuler: <?= json_encode($ekstraList) ?>
};

function renderTags(category) {
    const container = document.getElementById('tags_' + category);
    const countBadge = document.getElementById('count_' + category);
    if (!container) return;

    container.innerHTML = '';
    const items = masterData[category] || [];

    if (countBadge) {
        countBadge.textContent = items.length + ' item';
    }

    if (items.length === 0) {
        container.innerHTML = '<span class="text-muted small fst-italic py-1" style="font-size: 0.78rem;">Belum ada item dalam daftar.</span>';
        return;
    }

    items.forEach((item, index) => {
        const tag = document.createElement('span');
        tag.className = 'badge bg-white text-dark border d-inline-flex align-items-center gap-1 px-2.5 py-1 rounded-pill fw-normal shadow-xs';
        tag.style.fontSize = '0.82rem';

        const textSpan = document.createElement('span');
        textSpan.textContent = item;
        tag.appendChild(textSpan);

        const btnRemove = document.createElement('button');
        btnRemove.type = 'button';
        btnRemove.className = 'btn btn-link text-danger p-0 ms-1 d-inline-flex align-items-center text-decoration-none';
        btnRemove.style.fontSize = '0.85rem';
        btnRemove.style.lineHeight = '1';
        btnRemove.title = 'Hapus "' + item + '"';
        btnRemove.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
        btnRemove.onclick = function() {
            removeTag(category, index);
        };
        tag.appendChild(btnRemove);

        container.appendChild(tag);
    });
}

function addTag(category) {
    const input = document.getElementById('input_' + category);
    if (!input) return;
    const rawVal = input.value.trim();
    if (!rawVal) return;

    // Lindungi frasa mapel resmi yang mengandung koma
    const placeholder = '___KREAT_MAPEL___';
    let safeVal = rawVal.replace(/Kreativitas,\s*Inovasi,\s*dan\s*Kewirausahaan/gi, placeholder);
    
    // Pecah jika pengguna mengetik beberapa item dengan koma, titik koma, atau baris baru
    const items = safeVal.split(/[\n,;]+/).map(s => s.trim().replace(placeholder, 'Kreativitas, Inovasi, dan Kewirausahaan')).filter(s => s !== '');

    items.forEach(val => {
        if (!val || val === '[]' || ['«', '»', '<', '>', '&lt;', '&gt;'].includes(val) || val.toLowerCase().indexOf('c_rgszsh') !== -1) return;
        const exists = masterData[category].some(x => x.toLowerCase() === val.toLowerCase());
        if (!exists) {
            masterData[category].push(val);
        }
    });

    input.value = '';
    renderTags(category);
    syncHiddenInput(category);
}

function removeTag(category, index) {
    masterData[category].splice(index, 1);
    renderTags(category);
    syncHiddenInput(category);
}

function syncTahunAjaranSelect() {
    const sel = document.getElementById('select_tahun_ajaran_aktif');
    if (!sel) return;
    const currentVal = sel.value;
    const items = masterData.list_tahun_ajaran || [];
    sel.innerHTML = '';
    if (items.length === 0) {
        const opt = document.createElement('option');
        opt.value = currentVal || '';
        opt.textContent = currentVal || '-- Belum Ada Tahun Ajaran --';
        sel.appendChild(opt);
        return;
    }
    items.forEach(ta => {
        const opt = document.createElement('option');
        opt.value = ta;
        opt.textContent = ta;
        if (ta === currentVal) {
            opt.selected = true;
        }
        sel.appendChild(opt);
    });
    if (!items.includes(currentVal) && items.length > 0) {
        sel.value = items[0];
    }
}

function syncHiddenInput(category) {
    const hidden = document.getElementById('val_' + category);
    const form = document.getElementById('formPengaturan');
    if (hidden) {
        hidden.value = JSON.stringify(masterData[category]);
        if (form) {
            form.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    if (category === 'list_tahun_ajaran') {
        syncTahunAjaranSelect();
    }
}

// Deteksi perubahan pada formulir pengaturan & penjaga navigasi (Unsaved Changes Guard)
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi tampilan tags / chips data master
    ['list_tahun_ajaran', 'kelas', 'mata_pelajaran', 'list_ekstrakurikuler'].forEach(function(cat) {
        renderTags(cat);
        syncHiddenInput(cat);
        const inp = document.getElementById('input_' + cat);
        if (inp) {
            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addTag(cat);
                }
            });
        }
    });

    let isFormChanged = false;
    let isFormSubmitting = false;

    const form = document.getElementById('formPengaturan');
    if (!form) return;

    // Tandai jika ada field yang diubah
    form.addEventListener('input', function() {
        isFormChanged = true;
    });
    form.addEventListener('change', function() {
        isFormChanged = true;
    });

    // Jika form disubmit via tombol Simpan:
    // Otomatis masukkan input yang masih tertulis di kolom sebelum submit!
    form.addEventListener('submit', function() {
        ['list_tahun_ajaran', 'kelas', 'mata_pelajaran', 'list_ekstrakurikuler'].forEach(function(cat) {
            const inp = document.getElementById('input_' + cat);
            if (inp && inp.value.trim() !== '') {
                addTag(cat);
            }
            syncHiddenInput(cat);
        });
        isFormSubmitting = true;
        window.removeEventListener('beforeunload', handleBeforeUnload);
    });

    // Peringatan native browser saat reload (F5) atau tutup tab
    function handleBeforeUnload(e) {
        if (window.__skipBeforeUnload) return;
        if (isFormChanged && !isFormSubmitting) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    }
    window.addEventListener('beforeunload', handleBeforeUnload);
    window.__pengaturanHandleBeforeUnload = handleBeforeUnload;

    // Tangkap klik pada seluruh tautan navigasi (sidebar, navbar, logo, breadcrumbs, tombol kembali, dll)
    document.addEventListener('click', function(e) {
        if (!isFormChanged || isFormSubmitting) return;

        // Cari elemen link <a> terdekat
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        const target = link.getAttribute('target');

        // Lewati jika link kosong, hash anchor modal, javascript, atau target tab baru (_blank)
        if (!href || href === '#' || href.startsWith('#') || href.startsWith('javascript:') || target === '_blank') return;
        if (link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-bs-dismiss') || link.hasAttribute('data-no-warn') || href.indexOf('export_master') !== -1) return;

        // Tahan perpindahan halaman sementara
        e.preventDefault();
        e.stopPropagation();

        const targetUrl = link.href;

        // Tampilkan modal SweetAlert2 konfirmasi penyimpanan
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Perubahan Belum Disimpan!',
                html: 'Anda telah melakukan perubahan pada pengaturan sistem.<br><br>Apakah Anda ingin <strong>menyimpan perubahan</strong> tersebut terlebih dahulu sebelum berpindah ke menu lain?',
                icon: 'warning',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: '#2563eb', // Tombol Simpan (Biru)
                denyButtonColor: '#ef4444',    // Tombol Buang (Merah)
                cancelButtonColor: '#64748b',  // Tombol Batal/Tetap (Abu-abu)
                confirmButtonText: '<i class="bi bi-check-circle-fill me-1"></i> Simpan & Lanjutkan',
                denyButtonText: '<i class="bi bi-trash3-fill me-1"></i> Buang Perubahan',
                cancelButtonText: 'Tetap di Halaman Ini',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // 1. Simpan perubahan dan redirect ke menu yang dipilih
                    isFormSubmitting = true;
                    window.removeEventListener('beforeunload', handleBeforeUnload);
                    document.getElementById('redirect_to').value = targetUrl;
                    form.submit();
                } else if (result.isDenied) {
                    // 2. Buang perubahan dan langsung pindah ke menu tujuan
                    isFormChanged = false;
                    window.removeEventListener('beforeunload', handleBeforeUnload);
                    window.location.href = targetUrl;
                }
                // 3. Jika cancel: tetap di halaman pengaturan untuk melanjutkan edit
            });
        } else {
            // Fallback native confirm
            const saveConfirm = confirm('Anda memiliki perubahan yang belum disimpan.\n\nKlik "OK" untuk Simpan & Lanjutkan, atau "Batal" untuk Membuang Perubahan.');
            if (saveConfirm) {
                isFormSubmitting = true;
                window.removeEventListener('beforeunload', handleBeforeUnload);
                document.getElementById('redirect_to').value = targetUrl;
                form.submit();
            } else {
                isFormChanged = false;
                window.removeEventListener('beforeunload', handleBeforeUnload);
                window.location.href = targetUrl;
            }
        }
    }, true); // useCapture = true agar mencegat event sebelum handler navigasi lainnya
});
</script>

<!-- Modal Import Master Data Pilihan -->
<div class="modal fade" id="modalImportMaster" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="pengaturan.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_master">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold fs-6">
                        <i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Import Master Pilihan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted small mb-3">
                        Unggah file Excel (<strong>.xls</strong>) atau CSV (<strong>.csv</strong>) untuk menambah atau memperbarui daftar Kelas, Mata Pelajaran, dan Ekstrakurikuler sekaligus. Semua data akan disimpan permanen ke database SQL.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Pilih File Master (.xls / .csv)</label>
                        <input type="file" name="master_file" class="form-control form-control-sm" accept=".xls,.xlsx,.csv" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Mode Import Data</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="import_mode" id="modeMerge" value="merge" checked>
                            <label class="form-check-label small" for="modeMerge">
                                <strong>Gabungkan (Merge)</strong> - Tambahkan data baru tanpa menghapus data pilihan yang sudah ada.
                            </label>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="radio" name="import_mode" id="modeReplace" value="replace">
                            <label class="form-check-label small" for="modeReplace">
                                <strong>Gantikan Semua (Replace)</strong> - Timpa seluruh data lama dengan data pilihan dari file ini.
                            </label>
                        </div>
                    </div>
                    <div class="alert alert-light border small text-muted py-2 mb-0">
                        <i class="bi bi-info-circle me-1 text-primary"></i> 
                        Format kolom disarankan: <code>Kelas / Rombel</code>, <code>Mata Pelajaran</code>, dan <code>Cabang Ekstrakurikuler</code>. 
                        <a href="?export_master=template" class="fw-semibold text-decoration-none ms-1" data-no-warn="1">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Contoh Template (.xlsx)
                        </a>
                        <div class="form-text mt-1 text-muted small"><i class="bi bi-info-circle me-1 text-primary"></i> Berkas template telah dilengkapi data contoh (dummy) sebagai pedoman pengisian.</div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-light btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Mulai Import (SQL)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Tombol Sakti Hapus Data -->
<div class="modal fade" id="modalHapusData" tabindex="-1" aria-labelledby="modalHapusDataLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-danger text-white px-4 py-3 border-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-1.5 bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bi bi-trash3-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold fs-6 mb-0" id="modalHapusDataLabel">Konfirmasi Pembersihan Database (Tombol Sakti)</h5>
                        <small class="text-white text-opacity-75" style="font-size: 0.75rem;">Mengosongkan database agar siap digunakan dengan data baru</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-danger bg-danger-subtle border-danger-subtle text-danger-emphasis d-flex align-items-center gap-2 mb-3 py-2 px-3 rounded-3" style="font-size: 0.85rem;">
                    <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
                    <div>
                        <strong>PERINGATAN SISTEM:</strong> Tindakan ini akan mengosongkan rekaman data laporan dan data master. Pastikan Anda telah mengunduh / membackup data jika laporan periode lama masih dibutuhkan.
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <!-- Kolom Yang Dihapus -->
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border border-danger-subtle bg-danger-subtle bg-opacity-10 h-100">
                            <h6 class="fw-bold text-danger mb-2 d-flex align-items-center gap-1.5" style="font-size: 0.85rem;">
                                <i class="bi bi-x-circle-fill"></i> DATA YANG AKAN DIHAPUS:
                            </h6>
                            <ul class="text-secondary small mb-0 ps-3" style="font-size: 0.8rem; line-height: 1.55;">
                                <li><strong>Seluruh Laporan & Transaksi:</strong>
                                    <ul>
                                        <li>Presensi Siswa (<code>hasil_absen</code>)</li>
                                        <li>Jurnal Mengajar Guru (<code>hasil_jurnal</code>)</li>
                                        <li>Bimbingan Guru Wali (<code>hasil_guru_wali</code>)</li>
                                        <li>Catatan Wali Kelas (<code>hasil_wali</code>)</li>
                                        <li>Kegiatan Ekstrakurikuler (<code>hasil_ekstra</code>)</li>
                                    </ul>
                                </li>
                                <li><strong>Seluruh Master Data:</strong> Data Guru, Data Siswa, Siswa Guru Wali, dan Anggota Ekstra.</li>
                                <li><strong>Akun Pengguna:</strong> Seluruh akun Guru, Wali Kelas, dan Pembina Ekstra.</li>
                                <li><strong>Daftar Pilihan:</strong> Kelas, Mata Pelajaran, dan Ekstrakurikuler (dikosongkan agar diisi baru).</li>
                                <li><strong>Seluruh Berkas/Foto Unggahan:</strong> Seluruh foto profil siswa, berkas dokumen bukti laporan, dan foto/gambar kustom dibersihkan total dari folder penyimpanan server.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Kolom Yang Tetap Aman (TIDAK Dihapus) -->
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border border-success-subtle bg-success-subtle bg-opacity-10 h-100">
                            <h6 class="fw-bold text-success mb-2 d-flex align-items-center gap-1.5" style="font-size: 0.85rem;">
                                <i class="bi bi-shield-check-fill"></i> DATA YANG TETAP AMAN (TIDAK DIHAPUS):
                            </h6>
                            <ul class="text-secondary small mb-0 ps-3" style="font-size: 0.8rem; line-height: 1.55;">
                                <li><strong class="text-success-emphasis">Akun Super Admin:</strong> Akun pengelola sistem (<code>SAdmin</code>, <code>ferryxz</code>, dll).</li>
                                <li><strong class="text-success-emphasis">Akun Pelihat:</strong> Akun monitoring (<code>viewer</code> / Hanya Laporan).</li>
                                <li><strong class="text-success-emphasis">Nama Sekolah & Kabupaten:</strong> Identitas sekolah tetap tersimpan.</li>
                                <li><strong class="text-success-emphasis">Semester & Tahun Ajaran:</strong> Konfigurasi periode akademik tetap terjaga.</li>
                                <li><strong class="text-success-emphasis">Nama & NIP Kepala Sekolah:</strong> Informasi pimpinan sekolah tetap terjaga.</li>
                                <li><strong class="text-primary-emphasis">Logo, Kop Surat & TTD Kepsek:</strong> Berkas fisik kustom dibersihkan dan otomatis dikembalikan ke template default bawaan sistem untuk dapat diunggah/diganti kembali.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Petunjuk Alur Setelah Dihapus -->
                <div class="p-3 rounded-3 bg-light border mb-3">
                    <div class="fw-semibold text-dark small mb-1">
                        <i class="bi bi-info-circle-fill text-primary me-1"></i> Setelah Database Dikosongkan:
                    </div>
                    <div class="text-secondary small" style="font-size: 0.82rem; line-height: 1.45;">
                        Sistem akan menginstruksikan Anda untuk <strong>mengisi Pengaturan Sistem terlebih dahulu</strong> (membuat daftar Kelas, Mata Pelajaran, dan Ekstrakurikuler baru), kemudian <strong>mengisi Data Master</strong> (Data Guru, Data Siswa, dll).
                    </div>
                </div>

                <!-- Konfirmasi Checkbox -->
                <form method="POST" action="pengaturan.php" id="formHapusDatabase">
                    <input type="hidden" name="action" value="clear_database">
                    <div class="form-check p-2.5 bg-warning-subtle bg-opacity-40 rounded border border-warning-subtle">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="checkKonfirmasiHapus" required>
                        <label class="form-check-label text-dark small fw-semibold" for="checkKonfirmasiHapus" style="font-size: 0.82rem;">
                            Ya, saya mengerti dan yakin ingin mengosongkan database ini untuk digunakan dengan data yang baru.
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light px-4 py-3 border-top d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Tidak, Batalkan
                </button>
                <button type="button" class="btn btn-danger btn-sm rounded-pill px-4 fw-semibold shadow-sm" id="btnSubmitHapusData" onclick="submitClearDatabase()">
                    <i class="bi bi-trash3-fill me-1"></i> Ya, Hapus & Kosongkan Data
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi Penanganan Tombol Sakti Hapus Data dengan Konfirmasi Ya / Tidak
function submitClearDatabase() {
    const chk = document.getElementById('checkKonfirmasiHapus');
    if (!chk || !chk.checked) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Konfirmasi Diperlukan',
                text: 'Harap centang kotak persetujuan sebelum melanjutkan pembersihan database.',
                confirmButtonColor: '#2563eb'
            });
        } else {
            alert('Harap centang kotak persetujuan sebelum melanjutkan pembersihan database.');
        }
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Apakah Anda Benar-benar Yakin?',
            html: '<div class="text-start small">' +
                  '<p class="mb-2">Tindakan ini akan <strong>mengosongkan seluruh database</strong> agar bersih dan siap untuk data baru.</p>' +
                  '<div class="p-2.5 rounded bg-light border mb-2">' +
                  '<span class="text-success fw-bold d-block mb-1"><i class="bi bi-shield-check me-1"></i>Data Tetap Terlindungi:</span>' +
                  'Super Admin, Pelihat (Hanya Laporan), Logo Sekolah, Nama Sekolah, Semester, TTD Kepsek.' +
                  '</div>' +
                  '<p class="text-danger fw-semibold mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>Seluruh rekaman absensi, jurnal, data murid, guru, dan akun guru akan dihapus permanen!</p>' +
                  '</div>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="bi bi-trash3-fill me-1"></i> Ya, Hapus & Kosongkan Sekarang',
            cancelButtonText: 'Tidak, Batalkan',
            reverseButtons: true
        }).then((res) => {
            if (res.isConfirmed) {
                executeClearDatabase();
            }
        });
    } else {
        if (confirm('Apakah Anda yakin ingin mengosongkan database?\n\nData transaksi dan master akan dihapus. Super Admin, Pelihat, Logo, Sekolah, Semester, dan TTD Kepsek tetap aman.\n\nKlik OK untuk "Ya" (Hapus) atau Batal untuk "Tidak".')) {
            executeClearDatabase();
        }
    }
}

function executeClearDatabase() {
    window.__skipBeforeUnload = true;
    if (typeof window.__pengaturanHandleBeforeUnload === 'function') {
        window.removeEventListener('beforeunload', window.__pengaturanHandleBeforeUnload);
    }
    const btn = document.getElementById('btnSubmitHapusData');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengosongkan...';
    }
    const formClear = document.getElementById('formHapusDatabase');
    if (formClear) {
        formClear.submit();
    }
}

<?php if (isset($_GET['cleared'])): ?>
// Notifikasi popup pembersihan berhasil dan instruksi langkah berikutnya
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: '🎉 Database Berhasil Dikosongkan!',
            html: '<div class="text-start small">' +
                  '<p class="mb-2">Database telah dibersihkan dan siap digunakan untuk tahun ajaran / periode baru.</p>' +
                  '<div class="p-2.5 rounded bg-light border mb-2">' +
                  '<strong class="text-primary d-block mb-1"><i class="bi bi-signpost-split me-1"></i>Instruksi Alur Pengisian Baru:</strong>' +
                  '1. <strong>Pengaturan Sistem</strong>: Lengkapi Rombel Kelas, Mapel, dan Ekstra di halaman ini, lalu klik Simpan.<br>' +
                  '2. <strong>Data Master</strong>: Isi Data Guru, Data Siswa, Guru Wali, dan Ekstra secara berurutan.' +
                  '</div>' +
                  '</div>',
            confirmButtonText: '<i class="bi bi-arrow-down-circle me-1"></i> Mulai Isi Pengaturan Sekarang',
            confirmButtonColor: '#2563eb'
        });
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
