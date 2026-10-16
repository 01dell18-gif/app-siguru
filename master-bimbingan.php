<?php
// master-bimbingan.php - Data Siswa Bimbingan (Guru Wali)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

if (!hasAccess('master_bimbingan')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke menu Siswa Bimbingan.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();

// 1. Export Excel / Template / CSV (Dijalankan SEBELUM output header HTML dikirim)
$isTemplate = isset($_GET['template']) || (isset($_GET['export']) && strtolower($_GET['export']) === 'template');
if ($isTemplate || (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv']))) {
    $rows = $pdo->query("SELECT * FROM data_murid_guru_wali ORDER BY nama_murid ASC")->fetchAll();
    $data = [];
    $no = 1;

    // Jika unduh template, atau data di tabel masih kosong, sertakan data contoh (dummy) sebagai pedoman pengisian
    if ($isTemplate || empty($rows)) {
        $data = [
            [1, '0081234567', 'Aditya Pratama Putra', 'X TKJ 1', 'L', '-', 'Drs. Budi Santoso, M.Pd.', '081234567890', 'Bambang Pratama', '081298765432', 'Perlu pendampingan peningkatan kehadiran dan motivasi belajar'],
            [2, '0087654321', 'Annisa Nurul Hidayah', 'X TKJ 1', 'P', '-', 'Siti Nurhaliza, S.Pd.', '085712345678', 'Nurul Aini', '085798765432', 'Aktif berprestasi dalam kegiatan OSIS dan minat sains'],
            [3, '0091122334', 'Bagas Dimas Saputra', 'XI RPL 2', 'L', '-', 'Drs. Budi Santoso, M.Pd.', '087812345678', 'Agus Saputra', '087898765432', 'Minat tinggi di bidang pemrograman web dan algoritma']
        ];
        $filenamePrefix = 'Template_Import_Siswa_Bimbingan_';
    } else {
        foreach ($rows as $r) {
            $data[] = [
                $no++,
                formatNISN($r['nisn']),
                $r['nama_murid'],
                $r['kelas'],
                $r['jenis_kelamin'],
                $r['foto_murid'] ?: '-',
                $r['guru_wali'],
                $r['no_wa_murid'] ?: '-',
                $r['nama_ortu'] ?: '-',
                $r['kontak_ortu'] ?: '-',
                $r['catatan_khusus'] ?: '-'
            ];
        }
        $filenamePrefix = 'Data_Siswa_Bimbingan_';
    }

    $format = strtolower($_GET['export'] ?? ($_GET['template'] ?? 'xlsx'));
    if ($format === 'csv') {
        exportToExcelCSV($filenamePrefix . date('Ymd_His') . '.csv', ['No', 'NISN', 'Nama Murid', 'Kelas', 'Jenis Kelamin', 'Foto Murid', 'Guru Wali', 'No WA Murid', 'Nama Ortu', 'Kontak Ortu', 'Catatan Khusus'], $data);
    } else {
        exportToExcelXLS($filenamePrefix . date('Ymd_His') . '.xlsx', ['No', 'NISN', 'Nama Murid', 'Kelas', 'Jenis Kelamin', 'Foto Murid', 'Guru Wali', 'No WA Murid', 'Nama Ortu', 'Kontak Ortu', 'Catatan Khusus'], $data);
    }
}

// Ambil daftar guru wali untuk dropdown
$guruWaliList = $pdo->query("SELECT nama_guru FROM data_guru WHERE sebagai_guru_wali = 'Ya' OR sebagai_guru_wali = 'ya' ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($guruWaliList)) {
    $guruWaliList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);
}

// Ambil daftar murid yang berstatus "Guru Wali" dari DataMurid yang BELUM terdaftar di data_murid_guru_wali
$eligibleStudents = $pdo->query("SELECT * FROM data_murid 
    WHERE (status LIKE '%Guru Wali%' OR status LIKE '%Bimbingan%')
    AND TRIM(nama_murid) NOT IN (SELECT TRIM(nama_murid) FROM data_murid_guru_wali WHERE nama_murid IS NOT NULL AND nama_murid != '')
    AND (nisn IS NULL OR nisn = '' OR nisn NOT IN (SELECT nisn FROM data_murid_guru_wali WHERE nisn IS NOT NULL AND nisn != ''))
    ORDER BY nama_murid ASC")->fetchAll();

// 2. Aksi CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nisn = formatNISN($_POST['nisn'] ?? '');
        $nama = trim($_POST['nama_murid'] ?? '');
        $kelas = trim($_POST['kelas'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? 'L';
        $guruWali = trim($_POST['guru_wali'] ?? '');
        $noWa = trim($_POST['no_wa_murid'] ?? '');
        $ortu = trim($_POST['nama_ortu'] ?? '');
        $kontakOrtu = trim($_POST['kontak_ortu'] ?? '');
        $catatan = trim($_POST['catatan_khusus'] ?? '');

        // Upload foto jika ada berdasarkan metode switch yang dipilih
        $fotoMethod = $_POST['foto_method'] ?? 'upload';
        if ($fotoMethod === 'link') {
            $fotoPath = trim($_POST['foto_link'] ?? '');
        } else {
            $fotoPath = handleFileUpload('foto_file', 'photos') ?: '';
        }

        if (!empty($nama)) {
            // Validasi duplikasi: Cek apakah nama atau NISN siswa sudah terdaftar dalam data_murid_guru_wali
            $chk = $pdo->prepare("SELECT COUNT(*) FROM data_murid_guru_wali WHERE TRIM(nama_murid) = ? OR (nisn != '' AND nisn = ?)");
            $chk->execute([$nama, $nisn]);
            if ($chk->fetchColumn() > 0) {
                setFlash('danger', 'Siswa "' . htmlspecialchars($nama) . '" sudah terdaftar dalam data siswa bimbingan!');
                header("Location: master-bimbingan.php");
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO data_murid_guru_wali (nisn, nama_murid, kelas, jenis_kelamin, foto_murid, guru_wali, no_wa_murid, nama_ortu, kontak_ortu, catatan_khusus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nisn, $nama, $kelas, $jk, $fotoPath, $guruWali, $noWa, $ortu, $kontakOrtu, $catatan]);
            setFlash('success', 'Data siswa bimbingan berhasil ditambahkan.');
        }
        header("Location: master-bimbingan.php");
        exit;
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $nisn = formatNISN($_POST['nisn'] ?? '');
        $nama = trim($_POST['nama_murid'] ?? '');
        $kelas = trim($_POST['kelas'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? 'L';
        $guruWali = trim($_POST['guru_wali'] ?? '');
        $noWa = trim($_POST['no_wa_murid'] ?? '');
        $ortu = trim($_POST['nama_ortu'] ?? '');
        $kontakOrtu = trim($_POST['kontak_ortu'] ?? '');
        $catatan = trim($_POST['catatan_khusus'] ?? '');

        // Ambil data murid sebelumnya dari database untuk penanganan berkas foto lama
        $stmtChk = $pdo->prepare("SELECT * FROM data_murid_guru_wali WHERE id = ?");
        $stmtChk->execute([$id]);
        $existing = $stmtChk->fetch();
        $oldFoto = $existing['foto_murid'] ?? '';

        $editFotoMethod = $_POST['edit_foto_method'] ?? 'keep';
        if ($editFotoMethod === 'link') {
            $fotoPath = trim($_POST['edit_foto_link'] ?? '');
            // Beralih ke Link Drive: hapus berkas fisik foto lokal lama jika ada
            if (!empty($oldFoto)) {
                deleteOldUploadedFiles($oldFoto);
            }
        } elseif ($editFotoMethod === 'upload') {
            $fotoUpload = handleFileUpload('foto_file', 'photos');
            if ($fotoUpload) {
                // Berkas foto baru berhasil diunggah: hapus berkas foto fisik sebelumnya dari server
                if (!empty($oldFoto)) {
                    deleteOldUploadedFiles($oldFoto);
                }
                $fotoPath = $fotoUpload;
            } else {
                // Tidak ada berkas baru dipilih saat klik simpan, pertahankan foto lama
                $fotoPath = trim($_POST['foto_existing'] ?? $oldFoto);
            }
        } else {
            // 'keep': tetap gunakan foto profil yang tersimpan
            $fotoPath = trim($_POST['foto_existing'] ?? $oldFoto);
        }

        $stmt = $pdo->prepare("UPDATE data_murid_guru_wali SET nisn = ?, nama_murid = ?, kelas = ?, jenis_kelamin = ?, foto_murid = ?, guru_wali = ?, no_wa_murid = ?, nama_ortu = ?, kontak_ortu = ?, catatan_khusus = ? WHERE id = ?");
        $stmt->execute([$nisn, $nama, $kelas, $jk, $fotoPath, $guruWali, $noWa, $ortu, $kontakOrtu, $catatan, $id]);
        setFlash('success', 'Data siswa bimbingan berhasil diperbarui.');
        header("Location: master-bimbingan.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Hapus berkas foto fisik lama jika ada sebelum menghapus data dari database
        $stmtChk = $pdo->prepare("SELECT * FROM data_murid_guru_wali WHERE id = ?");
        $stmtChk->execute([$id]);
        $existing = $stmtChk->fetch();
        if ($existing && !empty($existing['foto_murid'])) {
            deleteOldUploadedFiles($existing['foto_murid']);
        }

        $stmt = $pdo->prepare("DELETE FROM data_murid_guru_wali WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Data berhasil dihapus.');
        header("Location: master-bimbingan.php");
        exit;
    }

    if ($action === 'import') {
        // Import Excel (.xlsx / .xls) / CSV
        if (isset($_FILES['file_import']) && $_FILES['file_import']['error'] === UPLOAD_ERR_OK) {
            $rows = parseImportRows($_FILES['file_import']['tmp_name']);
            if (!empty($rows)) {
                // Hapus berkas fisik seluruh foto siswa bimbingan lama sebelum dikosongkan
                try {
                    $oldPhotos = $pdo->query("SELECT foto_murid FROM data_murid_guru_wali WHERE foto_murid != '' AND foto_murid IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($oldPhotos as $op) {
                        deleteOldUploadedFiles($op);
                    }
                } catch (Exception $e) {}

                $pdo->exec("DELETE FROM data_murid_guru_wali");
                $stmt = $pdo->prepare("INSERT INTO data_murid_guru_wali (nisn, nama_murid, kelas, jenis_kelamin, foto_murid, guru_wali, no_wa_murid, nama_ortu, kontak_ortu, catatan_khusus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $firstRow = $rows[0];
                $colNisn = 1;
                $colNama = 2;
                $colKls  = 3;
                $colJk   = 4;
                $colFoto = 5;
                $colGw   = 6;
                $colWa   = 7;
                $colOrtu = 8;
                $colKontak = 9;
                $colCatatan = 10;

                $hasHeader = false;
                foreach ($firstRow as $idx => $cell) {
                    $cLower = strtolower(trim((string)$cell));
                    if (strpos($cLower, 'nisn') !== false) { $colNisn = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'nama murid') !== false || $cLower === 'nama') { $colNama = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kelas') !== false) { $colKls = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kelamin') !== false || $cLower === 'jk' || $cLower === 'l/p') { $colJk = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'guru wali') !== false) { $colGw = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'foto') !== false) { $colFoto = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'wa') !== false) { $colWa = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'nama ortu') !== false) { $colOrtu = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kontak ortu') !== false || strpos($cLower, 'telp') !== false) { $colKontak = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'catatan') !== false) { $colCatatan = $idx; $hasHeader = true; }
                }

                $inserted = 0;
                foreach ($rows as $idx => $data) {
                    if ($idx === 0 && $hasHeader) continue;
                    if (count($data) >= 2) {
                        $nisn = formatNISN($data[$colNisn] ?? '');
                        $nama = trim($data[$colNama] ?? '');
                        if (empty($nama) && !empty($data[1]) && !is_numeric($data[1])) {
                            $nama = trim($data[1]);
                        }
                        
                        if (empty($nama) || in_array($nama, ['«', '»', '<', '>', '&lt;', '&gt;', '+', '"+"']) || stripos($nama, 'c_rgszSh') !== false) {
                            continue;
                        }

                        $kls = trim($data[$colKls] ?? '');
                        if (in_array(strtolower($kls), ['«', '»', '<', '>', '&lt;', '&gt;'])) $kls = '';

                        $jk = strtoupper(trim($data[$colJk] ?? 'L'));
                        $jk = (strpos($jk, 'P') === 0 || strpos($jk, 'PEREMPUAN') === 0) ? 'P' : 'L';

                        $foto = trim($data[$colFoto] ?? '');
                        $gw = trim($data[$colGw] ?? 'Belum Ditentukan');
                        if (empty($gw)) $gw = 'Belum Ditentukan';

                        $wa = trim($data[$colWa] ?? '');
                        $ortu = trim($data[$colOrtu] ?? '');
                        $kontak = trim($data[$colKontak] ?? '');
                        $cat = trim($data[$colCatatan] ?? '');

                        $stmt->execute([$nisn, $nama, $kls, $jk, $foto, $gw, $wa, $ortu, $kontak, $cat]);
                        $inserted++;
                    }
                }
                setFlash('success', 'Import berhasil: ' . $inserted . ' data siswa bimbingan disimpan ke database.');
            } else {
                setFlash('danger', 'Format berkas tidak valid atau data kosong.');
            }
        }
        header("Location: master-bimbingan.php");
        exit;
    }
}

$pageTitle = 'Data Siswa Bimbingan';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

// 3. Search & Pagination
$currentUser = currentUser();
$isSuper = isSuperAdmin();

// Filter Guru Wali: Selain Super Admin, nama guru terkunci sesuai akun login
if (!$isSuper) {
    $filterGW = $currentUser['nama'] ?? '';
} else {
    $filterGW = trim($_GET['gw'] ?? '');
}

$search = trim($_GET['q'] ?? '');
$rawLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($rawLimit, [10, 20, 50, 100, 250, 500]) ? $rawLimit : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];
if (!empty($search)) {
    $whereClauses[] = "(nama_murid LIKE ? OR nisn LIKE ? OR nama_ortu LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($filterGW)) {
    $whereClauses[] = "guru_wali = ?";
    $params[] = $filterGW;
}

$sqlWhere = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM data_murid_guru_wali $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM data_murid_guru_wali $sqlWhere ORDER BY nama_murid ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$bimbinganList = $stmt->fetchAll();
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Data Siswa Bimbingan (Guru Wali)</h4>
            <p class="text-muted small mb-0">Kelola profil siswa bimbingan, kontak orang tua, dan catatan khusus.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <button class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddBimbingan">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Siswa Bimbingan
            </button>
            <a href="master-bimbingan.php?export=xlsx" class="btn btn-outline-success rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
            </a>
            <a href="master-bimbingan.php?template=xlsx" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" title="Unduh template Excel dengan pedoman data contoh (dummy)">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template (.xlsx)
            </a>
            <button class="btn btn-outline-primary rounded-pill px-3 btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportBimbingan">
                <i class="bi bi-upload me-1"></i> Import Excel
            </button>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card card-table p-3 mb-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama siswa, NISN, atau orang tua..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <?php if ($isSuper): ?>
                    <select name="gw" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Guru Wali</option>
                        <?php foreach ($guruWaliList as $gw): ?>
                            <option value="<?= htmlspecialchars($gw) ?>" <?= $filterGW === $gw ? 'selected' : '' ?>><?= htmlspecialchars($gw) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" class="form-control form-control-sm bg-light fw-semibold text-dark" value="🔒 <?= htmlspecialchars($filterGW) ?>" readonly title="Terkunci sesuai akun Guru Wali Anda">
                    <input type="hidden" name="gw" value="<?= htmlspecialchars($filterGW) ?>">
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 50, 100, 250, 500] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?> data / hal</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Tabel Data Siswa Bimbingan -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 60px;">Foto</th>
                        <th>NISN & Nama Murid</th>
                        <th>Kelas</th>
                        <th>Guru Wali</th>
                        <th>Kontak Siswa & Ortu</th>
                        <th>Catatan Khusus</th>
                        <th class="text-center" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bimbinganList)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Data siswa bimbingan belum ada.</td></tr>
                    <?php else: 
                        $no = $offset + 1;
                        foreach ($bimbinganList as $b): 
                            $photo = formatPhotoUrl($b['foto_murid'] ?? '');
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <!-- Popup Foto Lightbox saat diklik Sesuai PRD -->
                                <img src="<?= htmlspecialchars($photo) ?>" 
                                    alt="Foto" 
                                    class="student-avatar" 
                                    onclick="showStudentPhoto('<?= htmlspecialchars($photo) ?>', '<?= addslashes($b['nama_murid']) ?>')" 
                                    title="Klik untuk melihat foto lebih besar">
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($b['nama_murid']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($b['nisn']) ?> &bull; <?= $b['jenis_kelamin'] ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($b['kelas']) ?></span></td>
                            <td class="fw-semibold text-primary"><?= htmlspecialchars($b['guru_wali']) ?></td>
                            <td>
                                <?php 
                                $waMuridUrl = formatWhatsAppUrl($b['no_wa_murid'] ?? ''); 
                                $waOrtuUrl = formatWhatsAppUrl($b['kontak_ortu'] ?? ''); 
                                ?>
                                <div class="small mb-1">
                                    <i class="bi bi-whatsapp text-success me-1"></i> WA: 
                                    <?php if ($waMuridUrl): ?>
                                        <a href="<?= htmlspecialchars($waMuridUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-success fw-semibold text-decoration-none" title="Kirim Pesan WhatsApp ke Siswa: <?= htmlspecialchars($waMuridUrl) ?>">
                                            <?= htmlspecialchars($b['no_wa_murid']) ?> <i class="bi bi-box-arrow-up-right" style="font-size: 0.65rem;"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><?= htmlspecialchars($b['no_wa_murid'] ?: '-') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-person me-1"></i> Ortu: <?= htmlspecialchars($b['nama_ortu'] ?: '-') ?> 
                                    <?php if (!empty($b['kontak_ortu']) && $b['kontak_ortu'] !== '-'): ?>
                                        (<?php if ($waOrtuUrl): ?><a href="<?= htmlspecialchars($waOrtuUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-success text-decoration-none fw-semibold" title="Kirim Pesan WhatsApp ke Orang Tua: <?= htmlspecialchars($waOrtuUrl) ?>"><i class="bi bi-whatsapp text-success" style="font-size: 0.72rem;"></i> <?= htmlspecialchars($b['kontak_ortu']) ?> <i class="bi bi-box-arrow-up-right" style="font-size: 0.65rem;"></i></a><?php else: ?><?= htmlspecialchars($b['kontak_ortu']) ?><?php endif; ?>)
                                    <?php else: ?>
                                        <span class="text-muted">(-)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="small text-secondary" style="max-width: 200px;">
                                <?= htmlspecialchars($b['catatan_khusus'] ?: '-') ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" 
                                    onclick='editBimbingan(<?= json_encode($b) ?>)' title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 ms-1" 
                                    onclick="deleteBimbingan(<?= $b['id'] ?>, '<?= addslashes($b['nama_murid']) ?>')" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <small class="text-muted">Menampilkan <?= min($totalRows, $offset + 1) ?> - <?= min($totalRows, $offset + $limit) ?> dari total <?= $totalRows ?> data</small>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&gw=<?= urlencode($filterGW) ?>&q=<?= urlencode($search) ?>">Sebelumnya</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&gw=<?= urlencode($filterGW) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&gw=<?= urlencode($filterGW) ?>&q=<?= urlencode($search) ?>">Selanjutnya</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Tambah Bimbingan -->
<div class="modal fade" id="modalAddBimbingan" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Data Siswa Bimbingan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-bimbingan.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <!-- Dropdown Cari Berdasarkan Status Murid dari DataMurid (Dapat Diketik Sesuai Permintaan) -->
                    <div class="mb-3 position-relative" id="comboboxMuridGW">
                        <label class="form-label fw-semibold small text-primary d-flex justify-content-between align-items-center mb-1">
                            <span><i class="bi bi-person-lines-fill me-1"></i> Pilih Siswa dari DataMurid (Status: Guru Wali)</span>
                            <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.72rem;"><i class="bi bi-keyboard me-1"></i>Bisa Diketik</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   id="searchMuridGWInput" 
                                   placeholder="-- Ketik Nama Siswa, NISN, atau Kelas --" 
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btnToggleMuridGW" title="Tampilkan semua siswa">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <button class="btn btn-outline-danger d-none" type="button" id="btnClearMuridGW" title="Reset pencarian">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div id="dropdownMuridGWList" class="dropdown-menu w-100 shadow-lg border mt-1 p-0 overflow-auto" style="max-height: 250px; z-index: 1060; display: none;">
                            <!-- Opsi siswa diisi dinamis oleh JS -->
                        </div>
                        <div class="form-text small text-muted">
                            <i class="bi bi-info-circle me-1"></i> Ketik nama atau NISN untuk mencari cepat. Klik pilihan untuk mengisi otomatis data siswa di bawah.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">NISN</label>
                            <input type="text" name="nisn" id="add_b_nisn" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nama Murid</label>
                            <input type="text" name="nama_murid" id="add_b_nama" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Kelas</label>
                            <input type="text" name="kelas" id="add_b_kelas" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Jenis Kelamin</label>
                            <select name="jenis_kelamin" id="add_b_jk" class="form-select">
                                <option value="L">Laki-Laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Guru Wali Pembimbing</label>
                            <?php if ($isSuper): ?>
                                <select name="guru_wali" class="form-select" required>
                                    <?php foreach ($guruWaliList as $gw): ?>
                                        <option value="<?= htmlspecialchars($gw) ?>" <?= $filterGW === $gw ? 'selected' : '' ?>><?= htmlspecialchars($gw) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control bg-light fw-semibold text-dark" value="<?= htmlspecialchars($currentUser['nama'] ?? '') ?>" readonly>
                                <input type="hidden" name="guru_wali" value="<?= htmlspecialchars($currentUser['nama'] ?? '') ?>">
                            <?php endif; ?>
                        </div>

                        <!-- Switch Metode Foto Murid: Upload Berkas ATAU Link Google Drive -->
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                    <label class="form-label fw-bold text-dark mb-0 small">
                                        <i class="bi bi-image text-primary me-1"></i> Foto Murid
                                    </label>
                                    <!-- Switch Pilihan Metode Upload Foto (Pilih salah satu) -->
                                    <div class="btn-group btn-group-sm rounded-pill p-1 bg-white border shadow-xs" role="group">
                                        <input type="radio" class="btn-check" name="foto_method" id="add_foto_method_upload" value="upload" checked onchange="switchFotoMethodAdd('upload')">
                                        <label class="btn btn-outline-primary rounded-pill px-3 py-1 fw-semibold btn-sm" for="add_foto_method_upload">
                                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload File Foto
                                        </label>

                                        <input type="radio" class="btn-check" name="foto_method" id="add_foto_method_link" value="link" onchange="switchFotoMethodAdd('link')">
                                        <label class="btn btn-outline-primary rounded-pill px-3 py-1 fw-semibold btn-sm" for="add_foto_method_link">
                                            <i class="bi bi-link-45deg me-1"></i> Link Google Drive
                                        </label>
                                    </div>
                                </div>

                                <!-- Opsi 1: Upload File Foto -->
                                <div id="box_add_foto_file">
                                    <label class="form-label fw-semibold small text-muted mb-1">Pilih File Foto Murid</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-file-earmark-image text-muted"></i></span>
                                        <input type="file" name="foto_file" id="input_add_foto_file" class="form-control" accept="image/*" onchange="previewAddFotoFile(this)">
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mt-1">
                                        <small class="text-muted" style="font-size: 0.75rem;">Mendukung format JPG, JPEG, PNG, WEBP (Maksimal 5MB).</small>
                                        <span id="badge_add_foto_file" class="badge bg-success-subtle text-success border d-none">File siap diupload</span>
                                    </div>
                                    <div id="preview_add_foto_file_wrap" class="mt-2 d-none text-center">
                                        <img id="preview_add_foto_file_img" src="" alt="Preview Foto" class="rounded-circle border shadow-sm" style="width: 70px; height: 70px; object-fit: cover;">
                                    </div>
                                </div>

                                <!-- Opsi 2: Link Google Drive Foto -->
                                <div id="box_add_foto_link" class="d-none">
                                    <label class="form-label fw-semibold small text-muted mb-1">Masukkan Link Foto Google Drive</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                        <input type="url" name="foto_link" id="input_add_foto_link" class="form-control" placeholder="https://drive.google.com/file/d/..." oninput="previewAddFotoLink(this.value)">
                                    </div>
                                    <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-info-circle me-1 text-primary"></i> Pastikan akses link Google Drive disetel ke <em>"Siapa saja yang memiliki link dapat melihat"</em>.
                                    </div>
                                    <div id="preview_add_foto_link_wrap" class="mt-2 d-none text-center">
                                        <img id="preview_add_foto_link_img" src="" alt="Preview Link Foto" class="rounded-circle border shadow-sm" style="width: 70px; height: 70px; object-fit: cover;" onerror="this.parentElement.classList.add('d-none')">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">No WA Murid</label>
                            <input type="text" name="no_wa_murid" class="form-control" placeholder="081234567890">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Nama Orang Tua / Wali</label>
                            <input type="text" name="nama_ortu" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Kontak Orang Tua</label>
                            <input type="text" name="kontak_ortu" class="form-control" placeholder="081234567890">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Catatan Khusus Bimbingan</label>
                            <textarea name="catatan_khusus" class="form-control" rows="2" placeholder="Catatan perilaku, minat, bakat atau kesehatan siswa..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Bimbingan -->
<div class="modal fade" id="modalEditBimbingan" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Data Siswa Bimbingan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-bimbingan.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_b_id">
                <input type="hidden" name="foto_existing" id="edit_b_foto_existing">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">NISN</label>
                            <input type="text" name="nisn" id="edit_b_nisn" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nama Murid</label>
                            <input type="text" name="nama_murid" id="edit_b_nama" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Kelas</label>
                            <input type="text" name="kelas" id="edit_b_kelas" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Jenis Kelamin</label>
                            <select name="jenis_kelamin" id="edit_b_jk" class="form-select">
                                <option value="L">Laki-Laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Guru Wali Pembimbing</label>
                            <?php if ($isSuper): ?>
                                <select name="guru_wali" id="edit_b_gw" class="form-select" required>
                                    <?php foreach ($guruWaliList as $gw): ?>
                                        <option value="<?= htmlspecialchars($gw) ?>"><?= htmlspecialchars($gw) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control bg-light fw-semibold text-dark" value="<?= htmlspecialchars($currentUser['nama'] ?? '') ?>" readonly>
                                <input type="hidden" name="guru_wali" id="edit_b_gw" value="<?= htmlspecialchars($currentUser['nama'] ?? '') ?>">
                            <?php endif; ?>
                        </div>

                        <!-- Switch Metode Edit Foto Murid -->
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                    <label class="form-label fw-bold text-dark mb-0 small">
                                        <i class="bi bi-image text-primary me-1"></i> Perbarui Foto Murid
                                    </label>
                                    <!-- Switch Pilihan Metode Edit Foto -->
                                    <div class="btn-group btn-group-sm rounded-pill p-1 bg-white border shadow-xs" role="group">
                                        <input type="radio" class="btn-check" name="edit_foto_method" id="edit_foto_method_keep" value="keep" checked onchange="switchFotoMethodEdit('keep')">
                                        <label class="btn btn-outline-secondary rounded-pill px-3 py-1 fw-semibold btn-sm" for="edit_foto_method_keep">
                                            Tetap Gunakan
                                        </label>

                                        <input type="radio" class="btn-check" name="edit_foto_method" id="edit_foto_method_upload" value="upload" onchange="switchFotoMethodEdit('upload')">
                                        <label class="btn btn-outline-primary rounded-pill px-3 py-1 fw-semibold btn-sm" for="edit_foto_method_upload">
                                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                        </label>

                                        <input type="radio" class="btn-check" name="edit_foto_method" id="edit_foto_method_link" value="link" onchange="switchFotoMethodEdit('link')">
                                        <label class="btn btn-outline-primary rounded-pill px-3 py-1 fw-semibold btn-sm" for="edit_foto_method_link">
                                            <i class="bi bi-link-45deg me-1"></i> Link Drive
                                        </label>
                                    </div>
                                </div>

                                <!-- Kontainer 0: Tampilan Foto Saat Ini -->
                                <div id="box_edit_foto_current" class="d-flex align-items-center gap-3">
                                    <img id="edit_preview_current_img" src="assets/images/logo.png" alt="Foto Saat Ini" class="rounded-circle border shadow-sm" style="width: 55px; height: 55px; object-fit: cover;">
                                    <div>
                                        <div class="small fw-semibold text-dark">Foto Profil Terpasang</div>
                                        <small id="edit_current_photo_label" class="text-muted d-block" style="font-size: 0.75rem;">Menggunakan foto saat ini</small>
                                    </div>
                                </div>

                                <!-- Kontainer 1: Upload File Baru -->
                                <div id="box_edit_foto_file" class="d-none">
                                    <label class="form-label fw-semibold small text-muted mb-1">Pilih File Foto Baru</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-file-earmark-image text-muted"></i></span>
                                        <input type="file" name="foto_file" id="input_edit_foto_file" class="form-control" accept="image/*" onchange="previewEditFotoFile(this)">
                                    </div>
                                    <div id="preview_edit_foto_file_wrap" class="mt-2 d-none text-center">
                                        <img id="preview_edit_foto_file_img" src="" alt="Preview Foto Baru" class="rounded-circle border shadow-sm" style="width: 65px; height: 65px; object-fit: cover;">
                                    </div>
                                </div>

                                <!-- Kontainer 2: Link Google Drive Baru -->
                                <div id="box_edit_foto_link" class="d-none">
                                    <label class="form-label fw-semibold small text-muted mb-1">Masukkan Link Google Drive Baru</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                        <input type="url" name="edit_foto_link" id="input_edit_foto_link" class="form-control" placeholder="https://drive.google.com/file/d/..." oninput="previewEditFotoLink(this.value)">
                                    </div>
                                    <div id="preview_edit_foto_link_wrap" class="mt-2 d-none text-center">
                                        <img id="preview_edit_foto_link_img" src="" alt="Preview Link Baru" class="rounded-circle border shadow-sm" style="width: 65px; height: 65px; object-fit: cover;" onerror="this.parentElement.classList.add('d-none')">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">No WA Murid</label>
                            <input type="text" name="no_wa_murid" id="edit_b_wa" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Nama Orang Tua</label>
                            <input type="text" name="nama_ortu" id="edit_b_ortu" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Kontak Orang Tua</label>
                            <input type="text" name="kontak_ortu" id="edit_b_kontak_ortu" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Catatan Khusus Bimbingan</label>
                            <textarea name="catatan_khusus" id="edit_b_catatan" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Perbarui Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Bimbingan -->
<div class="modal fade" id="modalImportBimbingan" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Import Data Bimbingan (Excel .xls / CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-bimbingan.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Data lama akan dihapus dan digantikan data baru yang diimport.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pilih File Excel (.xlsx / .xls) atau CSV</label>
                        <input type="file" name="file_import" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <small class="text-muted d-block mb-2">Format kolom: No, NISN, Nama Murid, Kelas, L/P, Foto, Guru Wali, No WA Murid, Nama Ortu, Kontak Ortu, Catatan Khusus</small>
                    <a href="master-bimbingan.php?template=xlsx" class="btn btn-outline-success btn-sm rounded-pill px-3">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template Excel (.xlsx)
                    </a>
                    <div class="form-text mt-1 text-muted small"><i class="bi bi-info-circle me-1 text-primary"></i> Berkas template telah dilengkapi data contoh (dummy) sebagai pedoman pengisian.</div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Unggah & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formDeleteBimbingan" method="POST" action="master-bimbingan.php" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_b_id">
</form>

<script>
const eligibleStudentsGW = <?= json_encode($eligibleStudents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function autoFillStudentData(data) {
    if (!data) return;
    if (data.value !== undefined) {
        if (!data.value) return;
        data = JSON.parse(data.value);
    }
    document.getElementById('add_b_nisn').value = data.nisn || '';
    document.getElementById('add_b_nama').value = data.nama_murid || '';
    document.getElementById('add_b_kelas').value = data.kelas || '';
    document.getElementById('add_b_jk').value = data.jenis_kelamin || 'L';
}

function initSearchableStudentPicker(config) {
    const input = document.getElementById(config.inputId);
    const toggleBtn = document.getElementById(config.toggleBtnId);
    const clearBtn = document.getElementById(config.clearBtnId);
    const dropdown = document.getElementById(config.dropdownId);
    const container = document.getElementById(config.containerId);
    const students = config.students || [];
    let activeIndex = -1;
    let currentList = [];

    if (!input || !dropdown || !container) return;

    function renderDropdown(filterText = '') {
        const q = filterText.trim().toLowerCase();
        currentList = students.filter(s => {
            if (!q) return true;
            const nm = (s.nama_murid || '').toLowerCase();
            const nsn = (s.nisn || '').toLowerCase();
            const kls = (s.kelas || '').toLowerCase();
            return nm.includes(q) || nsn.includes(q) || kls.includes(q);
        });

        dropdown.innerHTML = '';
        activeIndex = -1;

        // Opsi Manual di paling atas
        const manualBtn = document.createElement('button');
        manualBtn.type = 'button';
        manualBtn.className = 'dropdown-item py-2 px-3 border-bottom text-muted fst-italic bg-light text-start w-100';
        manualBtn.innerHTML = '<i class="bi bi-pencil-square me-2 text-primary"></i>-- Isi Manual di Bawah (Kosongkan Pilihan) --';
        manualBtn.addEventListener('click', () => {
            input.value = '';
            if (clearBtn) clearBtn.classList.add('d-none');
            if (toggleBtn) toggleBtn.classList.remove('d-none');
            closeList();
        });
        dropdown.appendChild(manualBtn);

        if (currentList.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'p-3 text-center text-muted small';
            if (students.length === 0) {
                emptyDiv.innerHTML = '<i class="bi bi-info-circle me-1 text-primary"></i> Semua siswa DataMurid berstatus Guru Wali sudah terdaftar dalam bimbingan.';
            } else {
                emptyDiv.innerHTML = '<i class="bi bi-search me-1"></i> Tidak ada siswa yang cocok dengan "<b>' + escapeHtml(filterText) + '</b>"';
            }
            dropdown.appendChild(emptyDiv);
        } else {
            currentList.forEach((st) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'dropdown-item py-2 px-3 border-bottom text-wrap text-start w-100';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="fw-bold text-dark"><i class="bi bi-person-fill text-primary me-1"></i>${escapeHtml(st.nama_murid)}</div>
                        <span class="badge bg-light text-primary border">${escapeHtml(st.kelas)}</span>
                    </div>
                    <div class="small text-muted mt-1">
                        <span class="font-monospace">NISN: ${escapeHtml(st.nisn)}</span> • JK: <span class="badge ${st.jenis_kelamin === 'P' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary'} px-1">${escapeHtml(st.jenis_kelamin || 'L')}</span>
                    </div>
                `;
                item.addEventListener('click', () => {
                    input.value = `${st.nama_murid} (NISN: ${st.nisn} - ${st.kelas})`;
                    if (clearBtn) clearBtn.classList.remove('d-none');
                    if (toggleBtn) toggleBtn.classList.add('d-none');
                    closeList();
                    if (typeof config.onSelect === 'function') {
                        config.onSelect(st);
                    }
                });
                dropdown.appendChild(item);
            });
        }
        dropdown.style.display = 'block';
    }

    function openList() {
        const val = input.value;
        const query = val.includes('(NISN:') ? '' : val;
        renderDropdown(query);
    }

    function closeList() {
        dropdown.style.display = 'none';
        activeIndex = -1;
    }

    input.addEventListener('focus', openList);
    input.addEventListener('input', function() {
        if (clearBtn) clearBtn.classList.toggle('d-none', !this.value);
        if (toggleBtn) toggleBtn.classList.toggle('d-none', !!this.value);
        renderDropdown(this.value);
    });

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (dropdown.style.display === 'block') {
                closeList();
            } else {
                input.focus();
                openList();
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            input.value = '';
            clearBtn.classList.add('d-none');
            if (toggleBtn) toggleBtn.classList.remove('d-none');
            input.focus();
            openList();
        });
    }

    input.addEventListener('keydown', function(e) {
        const items = dropdown.querySelectorAll('.dropdown-item');
        if (dropdown.style.display !== 'block') {
            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                openList();
                e.preventDefault();
            }
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, items.length - 1);
            updateActive(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            updateActive(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIndex >= 0 && items[activeIndex]) {
                items[activeIndex].click();
            }
        } else if (e.key === 'Escape') {
            closeList();
        }
    });

    function updateActive(items) {
        items.forEach((it, i) => {
            if (i === activeIndex) {
                it.classList.add('active');
                it.scrollIntoView({ block: 'nearest' });
            } else {
                it.classList.remove('active');
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
            closeList();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initSearchableStudentPicker({
        containerId: 'comboboxMuridGW',
        inputId: 'searchMuridGWInput',
        toggleBtnId: 'btnToggleMuridGW',
        clearBtnId: 'btnClearMuridGW',
        dropdownId: 'dropdownMuridGWList',
        students: eligibleStudentsGW,
        onSelect: autoFillStudentData
    });
});


// Handler Switch Metode Foto - Modal Tambah
function switchFotoMethodAdd(method) {
    const boxFile = document.getElementById('box_add_foto_file');
    const boxLink = document.getElementById('box_add_foto_link');
    const inputFile = document.getElementById('input_add_foto_file');
    const inputLink = document.getElementById('input_add_foto_link');

    if (method === 'upload') {
        boxFile.classList.remove('d-none');
        boxLink.classList.add('d-none');
        inputLink.value = '';
        document.getElementById('preview_add_foto_link_wrap').classList.add('d-none');
    } else {
        boxFile.classList.add('d-none');
        boxLink.classList.remove('d-none');
        inputFile.value = '';
        document.getElementById('preview_add_foto_file_wrap').classList.add('d-none');
        document.getElementById('badge_add_foto_file').classList.add('d-none');
    }
}

function previewAddFotoFile(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview_add_foto_file_img').src = e.target.result;
            document.getElementById('preview_add_foto_file_wrap').classList.remove('d-none');
            document.getElementById('badge_add_foto_file').classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        document.getElementById('preview_add_foto_file_wrap').classList.add('d-none');
        document.getElementById('badge_add_foto_file').classList.add('d-none');
    }
}

function previewAddFotoLink(url) {
    const wrap = document.getElementById('preview_add_foto_link_wrap');
    const img = document.getElementById('preview_add_foto_link_img');
    if (!url || !url.startsWith('http')) {
        wrap.classList.add('d-none');
        return;
    }
    let cleanUrl = url;
    const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
    if (match) {
        cleanUrl = 'https://drive.google.com/thumbnail?id=' + match[1] + '&sz=w500';
    }
    img.src = cleanUrl;
    wrap.classList.remove('d-none');
}

// Handler Switch Metode Foto - Modal Edit
function switchFotoMethodEdit(method) {
    const boxCurrent = document.getElementById('box_edit_foto_current');
    const boxFile = document.getElementById('box_edit_foto_file');
    const boxLink = document.getElementById('box_edit_foto_link');
    const inputFile = document.getElementById('input_edit_foto_file');
    const inputLink = document.getElementById('input_edit_foto_link');

    if (method === 'keep') {
        boxCurrent.classList.remove('d-none');
        boxFile.classList.add('d-none');
        boxLink.classList.add('d-none');
        inputFile.value = '';
        inputLink.value = '';
    } else if (method === 'upload') {
        boxCurrent.classList.add('d-none');
        boxFile.classList.remove('d-none');
        boxLink.classList.add('d-none');
        inputLink.value = '';
    } else {
        boxCurrent.classList.add('d-none');
        boxFile.classList.add('d-none');
        boxLink.classList.remove('d-none');
        inputFile.value = '';
    }
}

function previewEditFotoFile(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview_edit_foto_file_img').src = e.target.result;
            document.getElementById('preview_edit_foto_file_wrap').classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        document.getElementById('preview_edit_foto_file_wrap').classList.add('d-none');
    }
}

function previewEditFotoLink(url) {
    const wrap = document.getElementById('preview_edit_foto_link_wrap');
    const img = document.getElementById('preview_edit_foto_link_img');
    if (!url || !url.startsWith('http')) {
        wrap.classList.add('d-none');
        return;
    }
    let cleanUrl = url;
    const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
    if (match) {
        cleanUrl = 'https://drive.google.com/thumbnail?id=' + match[1] + '&sz=w500';
    }
    img.src = cleanUrl;
    wrap.classList.remove('d-none');
}

function editBimbingan(b) {
    document.getElementById('edit_b_id').value = b.id;
    document.getElementById('edit_b_foto_existing').value = b.foto_murid || '';
    document.getElementById('edit_b_nisn').value = b.nisn;
    document.getElementById('edit_b_nama').value = b.nama_murid;
    document.getElementById('edit_b_kelas').value = b.kelas;
    document.getElementById('edit_b_jk').value = b.jenis_kelamin;
    document.getElementById('edit_b_gw').value = b.guru_wali;
    document.getElementById('edit_b_wa').value = b.no_wa_murid;
    document.getElementById('edit_b_ortu').value = b.nama_ortu;
    document.getElementById('edit_b_kontak_ortu').value = b.kontak_ortu;
    document.getElementById('edit_b_catatan').value = b.catatan_khusus;

    // Reset switch edit foto ke "keep"
    document.getElementById('edit_foto_method_keep').checked = true;
    switchFotoMethodEdit('keep');

    const curImg = document.getElementById('edit_preview_current_img');
    const curLbl = document.getElementById('edit_current_photo_label');
    if (b.foto_murid && b.foto_murid !== '-') {
        let photoUrl = b.foto_murid;
        const match = photoUrl.match(/\/d\/([a-zA-Z0-9_-]+)/);
        if (match) {
            photoUrl = 'https://drive.google.com/thumbnail?id=' + match[1] + '&sz=w500';
        }
        curImg.src = photoUrl;
        curLbl.textContent = b.foto_murid.startsWith('http') ? 'Tautan Google Drive terpasang' : 'Berkas lokal terpasang (' + b.foto_murid + ')';
    } else {
        curImg.src = 'assets/images/logo.png';
        curLbl.textContent = 'Belum ada foto profil terpasang';
    }

    new bootstrap.Modal(document.getElementById('modalEditBimbingan')).show();
}

function deleteBimbingan(id, name) {
    Swal.fire({
        title: 'Hapus Siswa Bimbingan?',
        text: 'Apakah Anda yakin ingin menghapus data bimbingan ' + name + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_b_id').value = id;
            document.getElementById('formDeleteBimbingan').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
