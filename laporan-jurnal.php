<?php
// laporan-jurnal.php - Laporan Jurnal Mengajar Guru
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
if (!hasAccess('laporan_jurnal')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke modul Laporan Jurnal.');
    header("Location: dashboard.php");
    exit;
}

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

// Filter Nama Guru: Super Admin & Pelihat dapat memilih guru, pengajar terkunci pada namanya
$currentUser = currentUser();
if (!isSuperAdmin() && !isPelihat()) {
    $filterGuru = $currentUser['nama'] ?? '';
} else {
    $filterGuru = $_GET['guru'] ?? '';
}

// Export CSV / Excel (Dijalankan SEBELUM output header HTML)
if (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv'])) {
    $sqlExp = "SELECT * FROM hasil_jurnal WHERE tahun_ajaran = ? AND semester = ?";
    $paramsExp = [$filterTA, $filterSemester];
    if (!empty($filterKelas)) { $sqlExp .= " AND kelas = ?"; $paramsExp[] = $filterKelas; }
    if (!empty($filterMapel)) { $sqlExp .= " AND mata_pelajaran = ?"; $paramsExp[] = $filterMapel; }
    if (!empty($filterGuru))  { $sqlExp .= " AND nama_guru = ?"; $paramsExp[] = $filterGuru; }
    $sqlExp .= " ORDER BY tanggal DESC, pertemuan_ke ASC";

    $stmtExp = $pdo->prepare($sqlExp);
    $stmtExp->execute($paramsExp);
    $allRows = $stmtExp->fetchAll();
    $totalJurnal = count($allRows);
    $targetEfektif = (int)($_GET['target_efektif'] ?? 16);
    if ($targetEfektif < 1) $targetEfektif = 16;
    $persenEfektif = round(($totalJurnal / $targetEfektif) * 100);

    // Kop & Metadata
    $subTitle = "Tahun Ajaran: $filterTA | Semester: $filterSemester" 
        . (!empty($filterKelas) ? " | Kelas: $filterKelas" : "") 
        . (!empty($filterMapel) ? " | Mapel: $filterMapel" : "") 
        . (!empty($filterGuru) ? " | Guru: $filterGuru" : "");
    $excelRows = buildExcelReportHeader('LAPORAN JURNAL MENGAJAR GURU', $subTitle, $settings);

    // Ringkasan Efektivitas Mengajar
    $excelRows[] = ['RINGKASAN EFEKTIVITAS MENGAJAR GURU'];
    $excelRows[] = ['Target Hari Efektif', $targetEfektif . ' Pertemuan'];
    $excelRows[] = ['Total Jurnal Terlaksana', $totalJurnal . ' Pertemuan'];
    $excelRows[] = ['Persentase Efektivitas', $persenEfektif . '%'];
    $excelRows[] = []; // Baris pemisah

    // Tabel Rincian Jurnal Mengajar
    $excelRows[] = ['TABEL CATATAN JURNAL MENGAJAR GURU'];
    $excelRows[] = ['No', 'Tanggal', 'Guru Pengampu', 'Mata Pelajaran (Jam Ke)', 'Kelas', 'Ke-', 'Model Pembelajaran', 'Materi Pembelajaran', 'Murid Tidak Hadir', 'Ketuntasan Materi', 'Link Bukti / Berkas'];
    $noJ = 1;
    foreach ($allRows as $r) {
        $excelRows[] = [
            $noJ++,
            formatDateIndo($r['tanggal']),
            $r['nama_guru'],
            $r['mata_pelajaran'] . (!empty($r['jam_ke']) ? ' (' . $r['jam_ke'] . ')' : ''),
            $r['kelas'],
            $r['pertemuan_ke'],
            $r['model_pembelajaran'] ?: '-',
            $r['materi_pembelajaran'],
            $r['murid_tidak_hadir'] ?: '-',
            $r['ketuntasan_materi'],
            $r['link_bukti_jurnal'] ?: '-'
        ];
    }

    // Tanda Tangan Resmi Pengesahan (Kepsek & Guru Pengampu)
    $ttdNama = !empty($filterGuru) ? $filterGuru : (!empty($currentUser['nama']) ? $currentUser['nama'] : 'Guru Pengampu');
    $ttdNip = !empty($currentUser['nip']) ? $currentUser['nip'] : '-';
    if (!empty($filterGuru)) {
        $stmtG = $pdo->prepare("SELECT nip FROM data_guru WHERE nama_guru = ? LIMIT 1");
        $stmtG->execute([$filterGuru]);
        $gNip = $stmtG->fetchColumn();
        if (!empty($gNip)) $ttdNip = $gNip;
    }
    $sigRows = buildExcelReportSignatures(11, $ttdNama, $ttdNip, 'Guru Mata Pelajaran', true, $settings);
    foreach ($sigRows as $sr) {
        $excelRows[] = $sr;
    }

    if (strtolower($_GET['export']) === 'csv') {
        exportToExcelCSV('Laporan_Jurnal_Guru_' . date('Ymd_His') . '.csv', [], $excelRows);
    } else {
        exportToExcelXLS('Laporan_Jurnal_Guru_' . date('Ymd_His') . '.xlsx', [], $excelRows);
    }
}

// CRUD Handler Laporan Jurnal (Hanya Super Admin atau Guru yang bersangkutan yang dapat melakukan perubahan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // Ambil data rekaman yang akan diubah/dihapus untuk validasi izin
    $existing = null;
    if ($id > 0) {
        $stmtChk = $pdo->prepare("SELECT * FROM hasil_jurnal WHERE id = ?");
        $stmtChk->execute([$id]);
        $existing = $stmtChk->fetch();
    }

    if (!$existing || !canManageReport('jurnal', $existing)) {
        setFlash('danger', 'Akses ditolak: Anda tidak memiliki hak akses untuk mengubah atau menghapus data jurnal ini.');
        header("Location: laporan-jurnal.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'update') {
        $tgl = trim($_POST['tanggal'] ?? '');
        $guru = trim($_POST['nama_guru'] ?? '');
        $mapel = trim($_POST['mata_pelajaran'] ?? '');
        $jamKe = trim($_POST['jam_ke'] ?? '');
        $kls = trim($_POST['kelas'] ?? '');
        $pertKe = (int)($_POST['pertemuan_ke'] ?? 1);
        $model = trim($_POST['model_pembelajaran'] ?? '');
        $materi = trim($_POST['materi_pembelajaran'] ?? '');
        $absen = trim($_POST['murid_tidak_hadir'] ?? '');
        $tuntas = trim($_POST['ketuntasan_materi'] ?? 'tuntas');

        // Logika 2 opsi bukti (Upload File vs Link Drive - hanya salah satu)
        $proofMode = $_POST['proof_mode'] ?? 'file';
        $bukti = $existing['link_bukti_jurnal'] ?? '';

        if ($proofMode === 'link') {
            $driveLink = trim($_POST['link_bukti_drive'] ?? '');
            deleteOldUploadedFiles($existing['link_bukti_jurnal'] ?? '');
            $bukti = $driveLink;
        } else {
            $uploadedFiles = handleFileUpload('file_bukti', 'documents');
            if (!empty($uploadedFiles)) {
                deleteOldUploadedFiles($existing['link_bukti_jurnal'] ?? '');
                $bukti = is_array($uploadedFiles) ? implode(', ', $uploadedFiles) : $uploadedFiles;
            } else {
                // Pertahankan berkas lokal lama, pastikan link drive lama tidak tercampur
                $oldParts = parseBuktiLinks($existing['link_bukti_jurnal'] ?? '');
                $localFiles = array_filter($oldParts, function($p) {
                    return !preg_match('/^https?:\/\//i', $p);
                });
                $bukti = implode(', ', $localFiles);
            }
        }

        if (!empty($tgl)) {
            $stmtUp = $pdo->prepare("UPDATE hasil_jurnal SET 
                tanggal = ?, nama_guru = ?, mata_pelajaran = ?, jam_ke = ?, kelas = ?,
                pertemuan_ke = ?, model_pembelajaran = ?, materi_pembelajaran = ?,
                murid_tidak_hadir = ?, ketuntasan_materi = ?, link_bukti_jurnal = ?
                WHERE id = ?");
            $stmtUp->execute([
                $tgl, $guru, $mapel, $jamKe, $kls,
                $pertKe, $model, $materi, $absen, $tuntas, $bukti, $id
            ]);
            setFlash('success', 'Data jurnal mengajar berhasil diperbarui di database SQL.');
        }
        header("Location: laporan-jurnal.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'delete') {
        deleteOldUploadedFiles($existing['link_bukti_jurnal'] ?? '');
        $stmtDel = $pdo->prepare("DELETE FROM hasil_jurnal WHERE id = ?");
        $stmtDel->execute([$id]);
        setFlash('success', 'Data jurnal mengajar berhasil dihapus dari database SQL.');
        header("Location: laporan-jurnal.php?" . http_build_query($_GET));
        exit;
    }
}

$pageTitle = 'Laporan Jurnal Mengajar';
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

$sqlWhere = "WHERE " . implode(" AND ", $whereClauses);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_jurnal $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM hasil_jurnal $sqlWhere ORDER BY tanggal DESC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$jurnalRows = $stmt->fetchAll();

// Target Hari Efektif & Efektivitas Mengajar Sesuai Foto
$targetEfektif = isset($_GET['target_efektif']) ? max(1, (int)$_GET['target_efektif']) : 16;
$totalJurnalCount = $totalRows;
$pctJurnal = $targetEfektif > 0 ? round(($totalJurnalCount / $targetEfektif) * 100) : 0;

$printUrl = 'print-laporan.php?' . http_build_query([
    'type' => 'jurnal',
    'ta' => $filterTA,
    'semester' => $filterSemester,
    'kelas' => $filterKelas,
    'mapel' => $filterMapel,
    'guru' => $filterGuru,
    'target_efektif' => $targetEfektif
]);
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Laporan Jurnal Mengajar Guru</h4>
            <p class="text-muted small mb-0">Rekapitulasi pelaksanaan KBM, progres materi ajar, dan presensi murid absen.</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <a href="<?= $printUrl ?>" target="_blank" id="btnPdfJurnal" class="btn btn-outline-danger rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-pdf me-1"></i> Preview / Cetak PDF
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'xlsx'])) ?>" class="btn btn-outline-success rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
            </a>
        </div>
    </div>

    <!-- 1. Card Efektivitas Mengajar Sesuai Contoh Foto -->
    <div class="card card-table p-3 mb-3">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-calendar-check-fill text-success fs-5"></i>
            <h6 class="fw-bold text-dark mb-0">Efektivitas Mengajar</h6>
        </div>
        <div class="row g-3 align-items-center">
            <!-- Target Hari Efektif Input Box -->
            <div class="col-md-3 col-sm-4">
                <label class="form-label small text-muted mb-1" for="targetEfektifInput">Target Hari Efektif</label>
                <input type="number" id="targetEfektifInput" class="form-control form-control-sm fw-bold" value="<?= $targetEfektif ?>" min="1" style="max-width: 150px; font-size: 0.95rem;">
                <small class="text-muted" style="font-size: 0.72rem;">Ubah angka untuk simulasi</small>
            </div>
            <!-- Total Jurnal Box -->
            <div class="col-md-4 col-sm-4">
                <div class="p-3 text-center rounded-3 border bg-white shadow-xs">
                    <div class="fw-semibold small text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">TOTAL JURNAL</div>
                    <div class="fw-bold fs-3" id="totalJurnalDisplay" style="color: #1d4ed8; line-height: 1.1;"><?= $totalJurnalCount ?></div>
                </div>
            </div>
            <!-- Persentase Box -->
            <div class="col-md-5 col-sm-4">
                <div class="p-3 text-center rounded-3 border shadow-xs" style="background-color: #f0fdf4; border-color: #bbf7d0 !important;">
                    <div class="fw-semibold small text-uppercase mb-1" style="color: #16a34a; font-size: 0.75rem; letter-spacing: 0.05em;">PERSENTASE</div>
                    <div class="fw-bold fs-3" id="persentaseDisplay" style="color: #15803d; line-height: 1.1;"><?= $pctJurnal ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Filter Bar -->
    <div class="card card-table p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="target_efektif" id="hiddenTargetEfektif" value="<?= $targetEfektif ?>">
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
                <select name="kelas" class="form-select form-select-sm">
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
                <?php if (isSuperAdmin() || isPelihat()): ?>
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
            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-0">Per Halaman</label>
                <select name="limit" class="form-select form-select-sm">
                    <?php foreach ([10, 20, 50, 100, 250, 500] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 text-end mt-2 d-flex justify-content-end align-items-center gap-1">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">Terapkan Filter</button>
                <a href="laporan-jurnal.php" class="btn btn-outline-secondary btn-sm rounded-circle d-inline-flex align-items-center justify-content-center shadow-xs" style="width: 31px; height: 31px;" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabel Data Jurnal -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th>Tanggal</th>
                        <th>Guru & Mapel</th>
                        <th>Kelas</th>
                        <th>Pertemuan</th>
                        <th>Materi Pembelajaran</th>
                        <th>Murid Tdk Hadir</th>
                        <th>Ketuntasan</th>
                        <th>Bukti</th>
                        <th class="text-center" style="width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jurnalRows)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Data jurnal mengajar tidak ditemukan.</td></tr>
                    <?php else: 
                        $no = $offset + 1;
                        foreach ($jurnalRows as $jr): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= formatDateIndo($jr['tanggal']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($jr['nama_guru']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($jr['mata_pelajaran']) ?> (<?= htmlspecialchars($jr['jam_ke']) ?>)</small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($jr['kelas']) ?></span></td>
                            <td><span class="badge bg-primary-subtle text-primary fw-bold">Ke-<?= $jr['pertemuan_ke'] ?></span></td>
                            <td>
                                <div style="max-width: 250px;">
                                    <strong><?= htmlspecialchars($jr['model_pembelajaran'] ?: '-') ?></strong><br>
                                    <small class="text-secondary"><?= nl2br(htmlspecialchars($jr['materi_pembelajaran'])) ?></small>
                                </div>
                            </td>
                            <td><small class="text-danger fw-semibold"><?= htmlspecialchars($jr['murid_tidak_hadir'] ?: '-') ?></small></td>
                            <td>
                                <span class="badge <?= $jr['ketuntasan_materi'] === 'tuntas' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
                                    <?= ucfirst($jr['ketuntasan_materi']) ?>
                                </span>
                            </td>
                            <td>
                                <?= renderBuktiButtons($jr['link_bukti_jurnal'], 'Bukti Jurnal') ?>
                            </td>
                            <td class="text-center nowrap">
                                <?php if (canManageReport('jurnal', $jr)): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" 
                                        onclick="editJurnal(<?= htmlspecialchars(json_encode($jr), ENT_QUOTES, 'UTF-8') ?>)" title="Edit Jurnal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 ms-1" 
                                        onclick="deleteJurnal(<?= (int)$jr['id'] ?>, 'Pertemuan Ke-<?= $jr['pertemuan_ke'] ?> (<?= htmlspecialchars($jr['mata_pelajaran']) ?>)')" title="Hapus Jurnal">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border py-1 px-2" title="Hanya guru bersangkutan (<?= htmlspecialchars($jr['nama_guru']) ?>) atau Super Admin yang dapat mengubah data ini">
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

<!-- Modal Edit Jurnal Mengajar -->
<div class="modal fade" id="modalEditJurnal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Jurnal Mengajar Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="laporan-jurnal.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_jurnal_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Tanggal KBM</label>
                            <input type="date" name="tanggal" id="edit_jurnal_tanggal" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Guru Pengampu</label>
                            <select name="nama_guru" id="edit_jurnal_guru" class="form-select">
                                <?php foreach ($guruList as $g): ?>
                                    <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Kelas</label>
                            <select name="kelas" id="edit_jurnal_kelas" class="form-select">
                                <?php foreach ($kelasList as $k): ?>
                                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Mata Pelajaran</label>
                            <select name="mata_pelajaran" id="edit_jurnal_mapel" class="form-select">
                                <?php foreach ($mapelList as $mp): ?>
                                    <option value="<?= htmlspecialchars($mp) ?>"><?= htmlspecialchars($mp) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Jam Ke-</label>
                            <input type="text" name="jam_ke" id="edit_jurnal_jam" class="form-control" placeholder="1-4">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Pertemuan Ke-</label>
                            <input type="number" name="pertemuan_ke" id="edit_jurnal_pertemuan" class="form-control" min="1" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Model Pembelajaran</label>
                            <input type="text" name="model_pembelajaran" id="edit_jurnal_model" class="form-control" placeholder="PBL / PJBL / Discovery">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Ketuntasan Materi</label>
                            <input type="text" name="ketuntasan_materi" id="edit_jurnal_ketuntasan" class="form-control" list="list_edit_ketuntasan" placeholder="Pilih atau ketik manual...">
                            <datalist id="list_edit_ketuntasan">
                                <option value="Tuntas">
                                <option value="Berlanjut">
                                <option value="Belum Tuntas">
                                <option value="Remedial">
                                <option value="-">
                            </datalist>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Materi Pembelajaran</label>
                            <textarea name="materi_pembelajaran" id="edit_jurnal_materi" class="form-control" rows="3" required></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Murid Tidak Hadir</label>
                            <input type="text" name="murid_tidak_hadir" id="edit_jurnal_absen" class="form-control" placeholder="Nama murid & keterangan absen">
                        </div>

                        <div class="col-12">
                            <div class="card p-3 border rounded-3 bg-light-subtle">
                                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                    <label class="form-label fw-semibold small text-secondary mb-0">
                                        <i class="bi bi-paperclip me-1"></i> Bukti Dokumentasi Jurnal Mengajar
                                    </label>
                                    <!-- Minimalist Switch / Segmented Control -->
                                    <div class="btn-group btn-group-sm p-1 bg-white border rounded-pill shadow-xs" role="group">
                                        <input type="radio" class="btn-check" name="proof_mode" id="edit_proof_mode_file_jurnal_edit" value="file" checked onchange="toggleProofInput('jurnal_edit', 'file')">
                                        <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="edit_proof_mode_file_jurnal_edit">
                                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                        </label>

                                        <input type="radio" class="btn-check" name="proof_mode" id="edit_proof_mode_link_jurnal_edit" value="link" onchange="toggleProofInput('jurnal_edit', 'link')">
                                        <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="edit_proof_mode_link_jurnal_edit">
                                            <i class="bi bi-link-45deg me-1"></i> Link Drive
                                        </label>
                                    </div>
                                </div>

                                <!-- Preview Bukti Saat Ini -->
                                <div id="current_proof_display_jurnal_edit" class="mb-2 small" style="display: none;">
                                    <span class="text-muted fw-semibold">Bukti Saat Ini:</span>
                                    <div id="current_proof_badges_jurnal_edit" class="d-inline-flex flex-wrap gap-1 align-items-center ms-1"></div>
                                </div>

                                <!-- Wadah File Upload -->
                                <div id="proof_file_container_jurnal_edit">
                                    <input type="file" name="file_bukti[]" multiple class="form-control bg-white" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih satu atau beberapa file foto / berkas baru (Maks 10MB per file). Kosongkan jika tetap menggunakan bukti saat ini.</div>
                                </div>

                                <!-- Wadah Link Drive -->
                                <div id="proof_link_container_jurnal_edit" style="display: none;">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                        <input type="url" name="link_bukti_drive" id="edit_link_drive_jurnal_edit" class="form-control bg-white" placeholder="https://drive.google.com/...">
                                    </div>
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Salin tautan folder / file Google Drive bukti mengajar untuk menggantikan berkas lama.</div>
                                </div>
                            </div>
                        </div>
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

<!-- Form Hidden Hapus Jurnal -->
<form id="formDeleteJurnal" method="POST" action="laporan-jurnal.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_jurnal_id">
</form>

<script>
function editJurnal(item) {
    document.getElementById('edit_jurnal_id').value = item.id;
    document.getElementById('edit_jurnal_tanggal').value = item.tanggal;
    document.getElementById('edit_jurnal_guru').value = item.nama_guru;
    document.getElementById('edit_jurnal_kelas').value = item.kelas;
    document.getElementById('edit_jurnal_mapel').value = item.mata_pelajaran;
    document.getElementById('edit_jurnal_jam').value = item.jam_ke;
    document.getElementById('edit_jurnal_pertemuan').value = item.pertemuan_ke;
    document.getElementById('edit_jurnal_model').value = item.model_pembelajaran || '';
    document.getElementById('edit_jurnal_ketuntasan').value = item.ketuntasan_materi || 'tuntas';
    document.getElementById('edit_jurnal_materi').value = item.materi_pembelajaran || '';
    document.getElementById('edit_jurnal_absen').value = item.murid_tidak_hadir || '';
    setupEditProofModal('jurnal_edit', item.link_bukti_jurnal || '');
    new bootstrap.Modal(document.getElementById('modalEditJurnal')).show();
}

function deleteJurnal(id, info) {
    Swal.fire({
        title: 'Hapus Jurnal Mengajar?',
        text: 'Apakah Anda yakin ingin menghapus data ' + info + ' dari database?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_jurnal_id').value = id;
            document.getElementById('formDeleteJurnal').submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const inputTarget = document.getElementById('targetEfektifInput');
    const persentaseEl = document.getElementById('persentaseDisplay');
    const hiddenTarget = document.getElementById('hiddenTargetEfektif');
    const btnPdf = document.getElementById('btnPdfJurnal');
    const totalJurnal = <?= (int)$totalJurnalCount ?>;

    if (inputTarget && persentaseEl) {
        inputTarget.addEventListener('input', function() {
            const val = parseFloat(this.value);
            if (val > 0) {
                const pct = Math.round((totalJurnal / val) * 100);
                persentaseEl.textContent = pct + '%';
            } else {
                persentaseEl.textContent = '0%';
            }
            if (hiddenTarget) {
                hiddenTarget.value = this.value;
            }
            if (btnPdf && val > 0) {
                try {
                    const u = new URL(btnPdf.href, window.location.origin);
                    u.searchParams.set('target_efektif', val);
                    btnPdf.href = u.toString();
                } catch(e) {}
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>

