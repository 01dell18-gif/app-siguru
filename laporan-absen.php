<?php
// laporan-absen.php - Laporan Kehadiran Siswa
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

$pdo = getDBConnection();
$kelasList = getDaftarKelas();
$mapelList = getDaftarMapel();
$taList = getDaftarTahunAjaran();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);

// Filter params
$filterTA = $_GET['ta'] ?? $settings['tahun_ajaran'];
if (!empty($filterTA) && !in_array($filterTA, $taList)) {
    array_unshift($taList, $filterTA);
}
$filterSemester = $_GET['semester'] ?? $settings['semester'];
$filterKelas = $_GET['kelas'] ?? '';
$filterMapel = $_GET['mapel'] ?? '';
$filterMurid = $_GET['murid'] ?? '';

// Filter Nama Guru: Selain Super Admin, langsung terisi nama guru sesuai hak akses akun login
$currentUser = currentUser();
if (!isSuperAdmin()) {
    $filterGuru = $currentUser['nama'] ?? '';
} else {
    $filterGuru = $_GET['guru'] ?? '';
}

// Export CSV / Excel (Dijalankan SEBELUM output header HTML)
if (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv'])) {
    $sqlExp = "SELECT * FROM hasil_absen WHERE tahun_ajaran = ? AND semester = ?";
    $paramsExp = [$filterTA, $filterSemester];
    if (!empty($filterKelas)) { $sqlExp .= " AND kelas = ?"; $paramsExp[] = $filterKelas; }
    if (!empty($filterMapel)) { $sqlExp .= " AND mata_pelajaran = ?"; $paramsExp[] = $filterMapel; }
    if (!empty($filterGuru))  { $sqlExp .= " AND nama_guru = ?"; $paramsExp[] = $filterGuru; }
    if (!empty($filterMurid)) { $sqlExp .= " AND nama_murid = ?"; $paramsExp[] = $filterMurid; }
    $sqlExp .= " ORDER BY tanggal DESC, nama_murid ASC";

    $stmtExp = $pdo->prepare($sqlExp);
    $stmtExp->execute($paramsExp);
    $allRows = $stmtExp->fetchAll();

    // 1. Rekapitulasi Persentase Kehadiran Per Murid
    $rekapMurid = [];
    $totalAllH = 0; $totalAllS = 0; $totalAllI = 0; $totalAllA = 0; $totalAllD = 0; $totalAllTotal = 0;
    foreach ($allRows as $r) {
        $mKey = trim($r['nama_murid']);
        if (!isset($rekapMurid[$mKey])) {
            $rekapMurid[$mKey] = [
                'nisn' => $r['nisn'] ?? '-',
                'nama_murid' => $r['nama_murid'],
                'jenis_kelamin' => $r['jenis_kelamin'] ?? '-',
                'kelas' => $r['kelas'] ?? '-',
                'h' => 0, 's' => 0, 'i' => 0, 'a' => 0, 'd' => 0, 'total' => 0
            ];
        }
        $ket = strtoupper(trim($r['keterangan'] ?? ''));
        if (in_array($ket, ['H', 'HADIR'])) {
            $rekapMurid[$mKey]['h']++;
            $totalAllH++;
        } elseif (in_array($ket, ['S', 'SAKIT'])) {
            $rekapMurid[$mKey]['s']++;
            $totalAllS++;
        } elseif (in_array($ket, ['I', 'IZIN'])) {
            $rekapMurid[$mKey]['i']++;
            $totalAllI++;
        } elseif (in_array($ket, ['A', 'ALPA', 'ALPHA'])) {
            $rekapMurid[$mKey]['a']++;
            $totalAllA++;
        } elseif (in_array($ket, ['D', 'DISPEN', 'DISPENSASI'])) {
            $rekapMurid[$mKey]['d']++;
            $totalAllD++;
        }
        $rekapMurid[$mKey]['total']++;
        $totalAllTotal++;
    }
    ksort($rekapMurid, SORT_NATURAL | SORT_FLAG_CASE);
    $avgPersen = $totalAllTotal > 0 ? round(($totalAllH / $totalAllTotal) * 100) : 0;

    // Susun Struktur Lembar Ekspor Excel seperti Cetak PDF
    $subTitle = "Tahun Ajaran: $filterTA | Semester: $filterSemester" 
        . (!empty($filterKelas) ? " | Kelas: $filterKelas" : "") 
        . (!empty($filterMapel) ? " | Mapel: $filterMapel" : "") 
        . (!empty($filterGuru) ? " | Guru: $filterGuru" : "") 
        . (!empty($filterMurid) ? " | Siswa: $filterMurid" : "");
    $excelRows = buildExcelReportHeader('LAPORAN KEHADIRAN / PRESENSI SISWA', $subTitle, $settings);

    // Bagian 1: Rekapitulasi Persentase Kehadiran
    $excelRows[] = ['1. REKAPITULASI PERSENTASE KEHADIRAN PER SISWA'];
    $excelRows[] = ['No', 'NISN', 'Nama Murid', 'L/P', 'Kelas', 'Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Alpha (A)', 'Dispen (D)', 'Total Pertemuan', '% Hadir'];
    $noM = 1;
    foreach ($rekapMurid as $m) {
        $pct = $m['total'] > 0 ? round(($m['h'] / $m['total']) * 100) : 0;
        $excelRows[] = [
            $noM++,
            formatNISN($m['nisn']),
            $m['nama_murid'],
            $m['jenis_kelamin'],
            $m['kelas'],
            $m['h'],
            $m['s'],
            $m['i'],
            $m['a'],
            $m['d'],
            $m['total'],
            $pct . '%'
        ];
    }
    $excelRows[] = ['Total Keseluruhan', '', '', '', '', $totalAllH, $totalAllS, $totalAllI, $totalAllA, $totalAllD, $totalAllTotal, $avgPersen . '%'];
    $excelRows[] = []; // Baris pemisah

    // Bagian 2: Rincian Histori Absensi Harian
    $excelRows[] = ['2. RINCIAN HISTORI PRESENSI HARIAN'];
    $excelRows[] = ['No', 'Tanggal', 'NISN', 'Nama Murid', 'L/P', 'Kelas', 'Mata Pelajaran', 'Status Kehadiran', 'Guru Pengampu'];
    $noD = 1;
    foreach ($allRows as $r) {
        $excelRows[] = [
            $noD++,
            formatDateIndo($r['tanggal']),
            formatNISN($r['nisn']),
            $r['nama_murid'],
            $r['jenis_kelamin'],
            $r['kelas'],
            $r['mata_pelajaran'],
            $r['keterangan'],
            $r['nama_guru']
        ];
    }

    // Tanda Tangan Guru Pengampu (Sesuai PDF print-laporan.php)
    $ttdNama = !empty($filterGuru) ? $filterGuru : (!empty($currentUser['nama']) ? $currentUser['nama'] : 'Guru Pengampu');
    $ttdNip = !empty($currentUser['nip']) ? $currentUser['nip'] : '-';
    if (!empty($filterGuru)) {
        $stmtG = $pdo->prepare("SELECT nip FROM data_guru WHERE nama_guru = ? LIMIT 1");
        $stmtG->execute([$filterGuru]);
        $gNip = $stmtG->fetchColumn();
        if (!empty($gNip)) $ttdNip = $gNip;
    }
    $sigRows = buildExcelReportSignatures(9, $ttdNama, $ttdNip, 'Guru / Guru Mapel', false, $settings);
    foreach ($sigRows as $sr) {
        $excelRows[] = $sr;
    }

    if (strtolower($_GET['export']) === 'csv') {
        exportToExcelCSV('Laporan_Kehadiran_' . date('Ymd_His') . '.csv', [], $excelRows);
    } else {
        exportToExcelXLS('Laporan_Kehadiran_' . date('Ymd_His') . '.xlsx', [], $excelRows);
    }
}

// CRUD Handler Laporan Presensi (Hanya Super Admin atau Guru/Wali yang bersangkutan yang dapat melakukan perubahan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // Ambil data rekaman yang akan diubah/dihapus untuk validasi izin
    $existing = null;
    if ($id > 0) {
        $stmtChk = $pdo->prepare("SELECT * FROM hasil_absen WHERE id = ?");
        $stmtChk->execute([$id]);
        $existing = $stmtChk->fetch();
    }

    if (!$existing || !canManageReport('absen', $existing)) {
        setFlash('danger', 'Akses ditolak: Anda tidak memiliki hak akses untuk mengubah atau menghapus data presensi ini.');
        header("Location: laporan-absen.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'update') {
        $tgl = trim($_POST['tanggal'] ?? '');
        $kls = trim($_POST['kelas'] ?? '');
        $mapel = trim($_POST['mata_pelajaran'] ?? '');
        $ket = trim($_POST['keterangan'] ?? 'H');
        $guru = trim($_POST['nama_guru'] ?? '');

        if (!empty($tgl)) {
            $stmtUp = $pdo->prepare("UPDATE hasil_absen SET tanggal = ?, kelas = ?, mata_pelajaran = ?, keterangan = ?, nama_guru = ? WHERE id = ?");
            $stmtUp->execute([$tgl, $kls, $mapel, $ket, $guru, $id]);
            setFlash('success', 'Data presensi siswa berhasil diperbarui di database SQL.');
        }
        header("Location: laporan-absen.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'delete') {
        $stmtDel = $pdo->prepare("DELETE FROM hasil_absen WHERE id = ?");
        $stmtDel->execute([$id]);
        setFlash('success', 'Data presensi siswa berhasil dihapus dari database SQL.');
        header("Location: laporan-absen.php?" . http_build_query($_GET));
        exit;
    }
}

// Ambil daftar murid khusus jika kelas sudah dipilih Sesuai PRD
$muridListByKelas = [];
if (!empty($filterKelas)) {
    $stmtM = $pdo->prepare("SELECT nama_murid FROM data_murid WHERE kelas = ? ORDER BY nama_murid ASC");
    $stmtM->execute([$filterKelas]);
    $muridListByKelas = $stmtM->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = 'Laporan Kehadiran Siswa';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

// Pagination & Query
$rawLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($rawLimit, [10, 20, 50, 100, 250, 500]) ? $rawLimit : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$whereClauses = ["tahun_ajaran = ?", "semester = ?"];
$params = [$filterTA, $filterSemester];

if (!empty($filterKelas)) { $whereClauses[] = "kelas = ?"; $params[] = $filterKelas; }
if (!empty($filterMapel)) { $whereClauses[] = "mata_pelajaran = ?"; $params[] = $filterMapel; }
if (!empty($filterGuru))  { $whereClauses[] = "nama_guru = ?"; $params[] = $filterGuru; }
if (!empty($filterMurid)) { $whereClauses[] = "nama_murid = ?"; $params[] = $filterMurid; }

$sqlWhere = "WHERE " . implode(" AND ", $whereClauses);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_absen $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM hasil_absen $sqlWhere ORDER BY tanggal DESC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$laporanRows = $stmt->fetchAll();

// Hitung Ringkasan Kehadiran Sesuai Filter
$sumStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN UPPER(TRIM(keterangan)) IN ('H', 'HADIR') THEN 1 ELSE 0 END) as h_count,
        SUM(CASE WHEN UPPER(TRIM(keterangan)) IN ('S', 'SAKIT') THEN 1 ELSE 0 END) as s_count,
        SUM(CASE WHEN UPPER(TRIM(keterangan)) IN ('I', 'IZIN') THEN 1 ELSE 0 END) as i_count,
        SUM(CASE WHEN UPPER(TRIM(keterangan)) IN ('A', 'ALPA', 'ALPHA') THEN 1 ELSE 0 END) as a_count,
        SUM(CASE WHEN UPPER(TRIM(keterangan)) IN ('D', 'DISPEN', 'DISPENSASI') THEN 1 ELSE 0 END) as d_count
    FROM hasil_absen $sqlWhere
");
$sumStmt->execute($params);
$sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

$totalSum = (int)($sumRow['total'] ?? 0);
$hCount = (int)($sumRow['h_count'] ?? 0);
$sCount = (int)($sumRow['s_count'] ?? 0);
$iCount = (int)($sumRow['i_count'] ?? 0);
$aCount = (int)($sumRow['a_count'] ?? 0);
$dCount = (int)($sumRow['d_count'] ?? 0);

if ($totalSum > 0) {
    $pctHadir = round(($hCount / $totalSum) * 100);
    $pctSakit = round(($sCount / $totalSum) * 100);
    $pctIzin = round(($iCount / $totalSum) * 100);
    $pctAlpa = round(($aCount / $totalSum) * 100);
    $pctDispen = round(($dCount / $totalSum) * 100);
} else {
    $pctHadir = 0;
    $pctSakit = 0;
    $pctIzin = 0;
    $pctAlpa = 0;
    $pctDispen = 0;
}

// Link cetak PDF
$printUrl = 'print-laporan.php?' . http_build_query([
    'type' => 'absen',
    'ta' => $filterTA,
    'semester' => $filterSemester,
    'kelas' => $filterKelas,
    'mapel' => $filterMapel,
    'guru' => $filterGuru,
    'murid' => $filterMurid
]);
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Laporan Kehadiran Siswa</h4>
            <p class="text-muted small mb-0">Rekapitulasi presensi dengan filter terpadu, ekspor excel, dan preview cetak PDF dinas.</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <a href="<?= $printUrl ?>" target="_blank" class="btn btn-outline-danger rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-pdf me-1"></i> Preview / Cetak PDF
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'xlsx'])) ?>" class="btn btn-outline-success rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
            </a>
        </div>
    </div>

    <!-- 1. Card Ringkasan Kehadiran Sesuai Desain Foto -->
    <div class="card card-table p-3 mb-3">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-pie-chart-fill text-success fs-5"></i>
            <h6 class="fw-bold text-dark mb-0">Ringkasan Kehadiran</h6>
        </div>
        <div class="row g-2 g-md-3 row-cols-2 row-cols-sm-3 row-cols-md-5">
            <!-- Hadir -->
            <div class="col">
                <div class="p-3 text-center rounded-3 border shadow-xs" style="background-color: #f0fdf4; border-color: #bbf7d0 !important;">
                    <div class="fw-semibold small mb-1" style="color: #16a34a; font-size: 0.8rem;">Hadir</div>
                    <div class="fw-bold" style="color: #15803d; font-size: 1.6rem; line-height: 1.1;"><?= $pctHadir ?>%</div>
                </div>
            </div>
            <!-- Sakit -->
            <div class="col">
                <div class="p-3 text-center rounded-3 border shadow-xs" style="background-color: #fffbeb; border-color: #fde68a !important;">
                    <div class="fw-semibold small mb-1" style="color: #d97706; font-size: 0.8rem;">Sakit</div>
                    <div class="fw-bold" style="color: #b45309; font-size: 1.6rem; line-height: 1.1;"><?= $pctSakit ?>%</div>
                </div>
            </div>
            <!-- Izin -->
            <div class="col">
                <div class="p-3 text-center rounded-3 border shadow-xs" style="background-color: #eff6ff; border-color: #bfdbfe !important;">
                    <div class="fw-semibold small mb-1" style="color: #2563eb; font-size: 0.8rem;">Izin</div>
                    <div class="fw-bold" style="color: #1d4ed8; font-size: 1.6rem; line-height: 1.1;"><?= $pctIzin ?>%</div>
                </div>
            </div>
            <!-- Alpa -->
            <div class="col">
                <div class="p-3 text-center rounded-3 border shadow-xs" style="background-color: #fef2f2; border-color: #fecaca !important;">
                    <div class="fw-semibold small mb-1" style="color: #dc2626; font-size: 0.8rem;">Alpa</div>
                    <div class="fw-bold" style="color: #b91c1c; font-size: 1.6rem; line-height: 1.1;"><?= $pctAlpa ?>%</div>
                </div>
            </div>
            <!-- Dispen -->
            <div class="col">
                <div class="p-3 text-center rounded-3 border shadow-xs" style="background-color: #faf5ff; border-color: #e9d5ff !important;">
                    <div class="fw-semibold small mb-1" style="color: #7c3aed; font-size: 0.8rem;">Dispen</div>
                    <div class="fw-bold" style="color: #6d28d9; font-size: 1.6rem; line-height: 1.1;"><?= $pctDispen ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Filter Multi-Kriteria Sesuai PRD -->
    <div class="card card-table p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-0">Tahun Ajaran</label>
                <select name="ta" class="form-select form-select-sm">
                    <?php foreach ($taList as $taOpt): ?>
                        <option value="<?= htmlspecialchars($taOpt) ?>" <?= $filterTA === $taOpt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($taOpt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-0">Semester</label>
                <select name="semester" class="form-select form-select-sm">
                    <option value="Ganjil" <?= $filterSemester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                    <option value="Genap" <?= $filterSemester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-0">Kelas</label>
                <select name="kelas" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= $filterKelas === $k ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-0">Mata Pelajaran</label>
                <select name="mapel" class="form-select form-select-sm">
                    <option value="">Semua Mapel</option>
                    <?php foreach ($mapelList as $mp): ?>
                        <option value="<?= htmlspecialchars($mp) ?>" <?= $filterMapel === $mp ? 'selected' : '' ?>><?= htmlspecialchars($mp) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-0">Nama Guru</label>
                <?php if (isSuperAdmin()): ?>
                    <select name="guru" class="form-select form-select-sm">
                        <option value="">Semua Guru</option>
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>" <?= $filterGuru === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select name="guru" class="form-select form-select-sm bg-light text-dark fw-semibold" style="pointer-events: none; cursor: not-allowed;" tabindex="-1" title="Terkunci sesuai akun login Anda">
                        <option value="<?= htmlspecialchars($filterGuru) ?>" selected>🔒 <?= htmlspecialchars($filterGuru) ?></option>
                    </select>
                <?php endif; ?>
            </div>
            <!-- Khusus Nama Murid Muncul Saat Kelas Sudah Dipilih Sesuai Permintaan User -->
            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-0">Nama Siswa</label>
                <?php if (!empty($filterKelas)): ?>
                    <select name="murid" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Murid (<?= count($muridListByKelas) ?> Siswa)</option>
                        <?php foreach ($muridListByKelas as $nm): ?>
                            <option value="<?= htmlspecialchars($nm) ?>" <?= $filterMurid === $nm ? 'selected' : '' ?>><?= htmlspecialchars($nm) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select class="form-select form-select-sm bg-light text-muted" disabled title="Pilih kelas terlebih dahulu agar data siswa dapat dimuat">
                        <option value="">Pilih Kelas Dahulu</option>
                    </select>
                <?php endif; ?>
            </div>

            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-0">Per Halaman</label>
                <select name="limit" class="form-select form-select-sm">
                    <?php foreach ([10, 20, 50, 100, 250, 500] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-6 d-flex gap-1 align-self-end mt-2">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill flex-grow-1">Terapkan Filter</button>
                <a href="laporan-absen.php" class="btn btn-outline-secondary btn-sm rounded-circle d-inline-flex align-items-center justify-content-center shadow-xs" style="width: 31px; height: 31px; flex-shrink: 0;" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabel Hasil Laporan Kehadiran -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>NISN & Nama Murid</th>
                        <th>L/P</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th class="text-center">Status</th>
                        <th>Guru Pengampu</th>
                        <th class="text-center" style="width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporanRows)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Data rekapitulasi kehadiran tidak ditemukan.</td></tr>
                    <?php else: 
                        $no = $offset + 1;
                        foreach ($laporanRows as $lr): 
                            $ketBadge = 'bg-secondary';
                            if ($lr['keterangan'] === 'H') $ketBadge = 'bg-success';
                            if ($lr['keterangan'] === 'I') $ketBadge = 'bg-warning text-dark';
                            if ($lr['keterangan'] === 'S') $ketBadge = 'bg-primary';
                            if ($lr['keterangan'] === 'A') $ketBadge = 'bg-danger';
                            if ($lr['keterangan'] === 'D') $ketBadge = 'text-white" style="background:#8b5cf6;"';
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= formatDateIndo($lr['tanggal']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($lr['nama_murid']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($lr['nisn']) ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= $lr['jenis_kelamin'] ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($lr['kelas']) ?></span></td>
                            <td><?= htmlspecialchars($lr['mata_pelajaran']) ?></td>
                            <td class="text-center">
                                <span class="badge <?= $ketBadge ?> px-3 py-1 fw-bold"><?= $lr['keterangan'] ?></span>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($lr['nama_guru']) ?></small></td>
                            <td class="text-center nowrap">
                                <?php if (canManageReport('absen', $lr)): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" 
                                        onclick="editAbsen(<?= htmlspecialchars(json_encode($lr), ENT_QUOTES, 'UTF-8') ?>)" title="Edit Presensi">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 ms-1" 
                                        onclick="deleteAbsen(<?= (int)$lr['id'] ?>, <?= htmlspecialchars(json_encode($lr['nama_murid']), ENT_QUOTES, 'UTF-8') ?>)" title="Hapus Data">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border py-1 px-2" title="Hanya guru pencatat (<?= htmlspecialchars($lr['nama_guru']) ?>) atau Super Admin yang dapat mengubah data ini">
                                        <i class="bi bi-lock-fill text-secondary me-1"></i> Terkunci
                                    </span>
                                <?php endif; ?>
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
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Sebelumnya</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Selanjutnya</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Edit Presensi Siswa -->
<div class="modal fade" id="modalEditAbsen" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Data Presensi Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="laporan-absen.php?<?= htmlspecialchars(http_build_query($_GET)) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_absen_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Siswa</label>
                        <input type="text" id="edit_absen_nama" class="form-control bg-light" readonly>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Tanggal Presensi</label>
                            <input type="date" name="tanggal" id="edit_absen_tanggal" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Status Kehadiran</label>
                            <select name="keterangan" id="edit_absen_ket" class="form-select fw-bold">
                                <option value="H">H (Hadir)</option>
                                <option value="S">S (Sakit)</option>
                                <option value="I">I (Izin)</option>
                                <option value="A">A (Alpa)</option>
                                <option value="D">D (Dispensasi)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Kelas</label>
                        <select name="kelas" id="edit_absen_kelas" class="form-select">
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Mata Pelajaran</label>
                        <select name="mata_pelajaran" id="edit_absen_mapel" class="form-select">
                            <?php foreach ($mapelList as $mp): ?>
                                <option value="<?= htmlspecialchars($mp) ?>"><?= htmlspecialchars($mp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Guru Pengampu</label>
                        <select name="nama_guru" id="edit_absen_guru" class="form-select">
                            <?php foreach ($guruList as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form Hidden Hapus Presensi -->
<form id="formDeleteAbsen" method="POST" action="laporan-absen.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_absen_id">
</form>

<script>
function editAbsen(item) {
    document.getElementById('edit_absen_id').value = item.id;
    document.getElementById('edit_absen_nama').value = item.nama_murid + (item.nisn ? ' (' + item.nisn + ')' : '');
    document.getElementById('edit_absen_tanggal').value = item.tanggal;
    document.getElementById('edit_absen_ket').value = item.keterangan;
    document.getElementById('edit_absen_kelas').value = item.kelas;
    document.getElementById('edit_absen_mapel').value = item.mata_pelajaran;
    document.getElementById('edit_absen_guru').value = item.nama_guru;
    new bootstrap.Modal(document.getElementById('modalEditAbsen')).show();
}

function deleteAbsen(id, name) {
    Swal.fire({
        title: 'Hapus Data Presensi?',
        text: 'Apakah Anda yakin ingin menghapus data presensi siswa ' + name + ' dari database?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_absen_id').value = id;
            document.getElementById('formDeleteAbsen').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
