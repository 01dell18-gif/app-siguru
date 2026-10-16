<?php
// laporan-wali.php - Laporan Kegiatan Wali Kelas
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
if (!hasAccess('laporan_wali')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke modul Laporan Wali Kelas.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$kelasList = getDaftarKelas();
$taList = getDaftarTahunAjaran();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);

$filterTA = $_GET['ta'] ?? $settings['tahun_ajaran'];
if (!empty($filterTA) && !in_array($filterTA, $taList)) {
    array_unshift($taList, $filterTA);
}
$filterSemester = $_GET['semester'] ?? $settings['semester'];
$filterKelas = $_GET['kelas'] ?? '';

// Filter Nama Guru / Wali: Super Admin & Pelihat dapat memilih guru, selain itu terkunci
$currentUser = currentUser();
if (!isSuperAdmin() && !isPelihat()) {
    $filterGuru = $currentUser['nama'] ?? '';
} else {
    $filterGuru = $_GET['guru'] ?? '';
}

// Export CSV / Excel (Dijalankan SEBELUM output header HTML)
if (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv'])) {
    $sqlExp = "SELECT * FROM hasil_wali WHERE tahun_ajaran = ? AND semester = ?";
    $paramsExp = [$filterTA, $filterSemester];
    if (!empty($filterKelas)) {
        $sqlExp .= " AND kelas = ?";
        $paramsExp[] = $filterKelas;
    }
    if (!empty($filterGuru)) {
        $sqlExp .= " AND nama_wali_kelas = ?";
        $paramsExp[] = $filterGuru;
    }
    $sqlExp .= " ORDER BY tanggal DESC";

    $stmtExp = $pdo->prepare($sqlExp);
    $stmtExp->execute($paramsExp);
    $allRows = $stmtExp->fetchAll();

    // Kop & Metadata
    $subTitle = "Tahun Ajaran: $filterTA | Semester: $filterSemester" 
        . (!empty($filterKelas) ? " | Kelas: $filterKelas" : "") 
        . (!empty($filterGuru) ? " | Wali: $filterGuru" : "");
    $excelRows = buildExcelReportHeader('LAPORAN KEGIATAN WALI KELAS', $subTitle, $settings);

    // Tabel Catatan Kegiatan Wali Kelas
    $excelRows[] = ['TABEL CATATAN AGENDA KEGIATAN WALI KELAS'];
    $excelRows[] = ['No', 'Tanggal', 'Kelas', 'Wali Kelas', 'Deskripsi Agenda Kegiatan', 'Link Bukti / Berkas'];
    $noW = 1;
    foreach ($allRows as $r) {
        $excelRows[] = [
            $noW++,
            formatDateIndo($r['tanggal']),
            $r['kelas'],
            $r['nama_wali_kelas'],
            $r['deskripsi_kegiatan'],
            $r['link_bukti_wali'] ?: '-'
        ];
    }

    // Tanda Tangan Resmi Pengesahan (Kepsek & Wali Kelas)
    $ttdNama = !empty($filterGuru) ? $filterGuru : (!empty($currentUser['nama']) ? $currentUser['nama'] : 'Wali Kelas');
    $ttdNip = !empty($currentUser['nip']) ? $currentUser['nip'] : '-';
    if (!empty($filterGuru)) {
        $stmtG = $pdo->prepare("SELECT nip FROM data_guru WHERE nama_guru = ? LIMIT 1");
        $stmtG->execute([$filterGuru]);
        $gNip = $stmtG->fetchColumn();
        if (!empty($gNip)) $ttdNip = $gNip;
    }
    $sigRows = buildExcelReportSignatures(6, $ttdNama, $ttdNip, 'Wali Kelas', true, $settings);
    foreach ($sigRows as $sr) {
        $excelRows[] = $sr;
    }

    if (strtolower($_GET['export']) === 'csv') {
        exportToExcelCSV('Laporan_Wali_Kelas_' . date('Ymd_His') . '.csv', [], $excelRows);
    } else {
        exportToExcelXLS('Laporan_Wali_Kelas_' . date('Ymd_His') . '.xlsx', [], $excelRows);
    }
}

// CRUD Handler Laporan Wali Kelas (Hanya Super Admin atau Wali Kelas yang bersangkutan yang dapat melakukan perubahan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // Ambil data rekaman yang akan diubah/dihapus untuk validasi izin
    $existing = null;
    if ($id > 0) {
        $stmtChk = $pdo->prepare("SELECT * FROM hasil_wali WHERE id = ?");
        $stmtChk->execute([$id]);
        $existing = $stmtChk->fetch();
    }

    if (!$existing || !canManageReport('wali', $existing)) {
        setFlash('danger', 'Akses ditolak: Anda tidak memiliki hak akses untuk mengubah atau menghapus data kegiatan wali kelas ini.');
        header("Location: laporan-wali.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'update') {
        $tgl = trim($_POST['tanggal'] ?? '');
        $kls = trim($_POST['kelas'] ?? '');
        $wali = trim($_POST['nama_wali_kelas'] ?? '');
        $deskripsi = trim($_POST['deskripsi_kegiatan'] ?? '');

        // Logika 2 opsi bukti (Upload File vs Link Drive - hanya salah satu)
        $proofMode = $_POST['proof_mode'] ?? 'file';
        $bukti = $existing['link_bukti_wali'] ?? '';

        if ($proofMode === 'link') {
            $driveLink = trim($_POST['link_bukti_drive'] ?? '');
            deleteOldUploadedFiles($existing['link_bukti_wali'] ?? '');
            $bukti = $driveLink;
        } else {
            $uploadedFiles = handleFileUpload('file_bukti', 'documents');
            if (!empty($uploadedFiles)) {
                deleteOldUploadedFiles($existing['link_bukti_wali'] ?? '');
                $bukti = is_array($uploadedFiles) ? implode(', ', $uploadedFiles) : $uploadedFiles;
            } else {
                // Pertahankan berkas lokal lama, pastikan link drive lama tidak tercampur
                $oldParts = parseBuktiLinks($existing['link_bukti_wali'] ?? '');
                $localFiles = array_filter($oldParts, function($p) {
                    return !preg_match('/^https?:\/\//i', $p);
                });
                $bukti = implode(', ', $localFiles);
            }
        }

        if (!empty($tgl)) {
            $stmtUp = $pdo->prepare("UPDATE hasil_wali SET 
                tanggal = ?, kelas = ?, nama_wali_kelas = ?, deskripsi_kegiatan = ?, link_bukti_wali = ?
                WHERE id = ?");
            $stmtUp->execute([$tgl, $kls, $wali, $deskripsi, $bukti, $id]);
            setFlash('success', 'Data kegiatan wali kelas berhasil diperbarui di database SQL.');
        }
        header("Location: laporan-wali.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'delete') {
        deleteOldUploadedFiles($existing['link_bukti_wali'] ?? '');
        $stmtDel = $pdo->prepare("DELETE FROM hasil_wali WHERE id = ?");
        $stmtDel->execute([$id]);
        setFlash('success', 'Data kegiatan wali kelas berhasil dihapus dari database SQL.');
        header("Location: laporan-wali.php?" . http_build_query($_GET));
        exit;
    }
}

$pageTitle = 'Laporan Kegiatan Wali Kelas';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

$rawLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($rawLimit, [10, 20, 50, 100, 250, 500]) ? $rawLimit : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$whereClauses = ["tahun_ajaran = ?", "semester = ?"];
$params = [$filterTA, $filterSemester];
if (!empty($filterKelas)) {
    $whereClauses[] = "kelas = ?";
    $params[] = $filterKelas;
}
if (!empty($filterGuru)) {
    $whereClauses[] = "nama_wali_kelas = ?";
    $params[] = $filterGuru;
}

$sqlWhere = "WHERE " . implode(" AND ", $whereClauses);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_wali $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM hasil_wali $sqlWhere ORDER BY tanggal DESC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$waliRows = $stmt->fetchAll();

$printUrl = 'print-laporan.php?' . http_build_query([
    'type' => 'wali',
    'ta' => $filterTA,
    'semester' => $filterSemester,
    'kelas' => $filterKelas,
    'guru' => $filterGuru
]);
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Laporan Kegiatan Wali Kelas</h4>
            <p class="text-muted small mb-0">Rekap agenda bimbingan kelas, koordinasi orang tua, dan pembinaan siswa.</p>
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

    <!-- Filter Bar -->
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
                <select name="kelas" class="form-select form-select-sm">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= $filterKelas === $k ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-6">
                <label class="form-label small text-muted mb-0">Nama Guru / Wali</label>
                <?php if (isSuperAdmin() || isPelihat()): ?>
                    <select name="guru" class="form-select form-select-sm">
                        <option value="">Semua Wali Kelas</option>
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
            <div class="col-12 text-end mt-2 d-flex justify-content-end align-items-center gap-2">
                <a href="laporan-wali.php" class="btn btn-outline-secondary btn-sm rounded-circle p-1 px-2" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <!-- Tabel Data Wali -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Wali Kelas</th>
                        <th>Deskripsi Agenda Kegiatan</th>
                        <th>Bukti Kegiatan</th>
                        <th style="width: 100px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($waliRows)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Data kegiatan wali kelas tidak ditemukan.</td>
                        </tr>
                        <?php else:
                        $no = $offset + 1;
                        foreach ($waliRows as $wr):
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= formatDateIndo($wr['tanggal']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($wr['kelas']) ?></span></td>
                                <td class="fw-semibold text-primary"><?= htmlspecialchars($wr['nama_wali_kelas'] ?: '-') ?></td>
                                <td><?= nl2br(htmlspecialchars($wr['deskripsi_kegiatan'])) ?></td>
                                <td>
                                    <?= renderBuktiButtons($wr['link_bukti_wali'], 'Bukti Kegiatan Wali') ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <?php if (canManageReport('wali', $wr)): ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-icon rounded-pill py-0 px-2"
                                            onclick="editWali(<?= htmlspecialchars(json_encode($wr), ENT_QUOTES, 'UTF-8') ?>)" title="Edit Data">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-icon rounded-pill py-0 px-2 ms-1"
                                            onclick="confirmDeleteWali(<?= (int)$wr['id'] ?>, '<?= htmlspecialchars(addslashes($wr['nama_wali_kelas'] ?: $wr['kelas']), ENT_QUOTES, 'UTF-8') ?>')" title="Hapus Data">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-1 px-2" title="Hanya wali kelas bersangkutan (<?= htmlspecialchars($wr['nama_wali_kelas'] ?: $wr['kelas']) ?>) atau Super Admin yang dapat mengubah data ini">
                                            <i class="bi bi-lock-fill text-secondary me-1"></i> Terkunci
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>

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

<!-- Modal Edit Kegiatan Wali -->
<div class="modal fade" id="modalEditWali" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="laporan-wali.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editWaliId">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold fs-6">Edit Kegiatan Wali Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Tanggal</label>
                            <input type="date" name="tanggal" id="editWaliTanggal" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Kelas</label>
                            <select name="kelas" id="editWaliKelas" class="form-select form-select-sm" required>
                                <?php foreach ($kelasList as $k): ?>
                                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Nama Wali Kelas</label>
                            <input type="text" name="nama_wali_kelas" id="editWaliNama" list="listGuruWali" class="form-control form-control-sm" required>
                            <datalist id="listGuruWali">
                                <?php foreach ($guruList as $g): ?>
                                    <option value="<?= htmlspecialchars($g) ?>">
                                    <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Deskripsi Agenda Kegiatan</label>
                            <textarea name="deskripsi_kegiatan" id="editWaliDeskripsi" class="form-control form-control-sm" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="card p-3 border rounded-3 bg-light-subtle">
                                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                    <label class="form-label fw-semibold small text-secondary mb-0">
                                        <i class="bi bi-paperclip me-1"></i> Bukti Dokumentasi Kegiatan Wali
                                    </label>
                                    <!-- Minimalist Switch / Segmented Control -->
                                    <div class="btn-group btn-group-sm p-1 bg-white border rounded-pill shadow-xs" role="group">
                                        <input type="radio" class="btn-check" name="proof_mode" id="edit_proof_mode_file_wali_edit" value="file" checked onchange="toggleProofInput('wali_edit', 'file')">
                                        <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="edit_proof_mode_file_wali_edit">
                                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                        </label>

                                        <input type="radio" class="btn-check" name="proof_mode" id="edit_proof_mode_link_wali_edit" value="link" onchange="toggleProofInput('wali_edit', 'link')">
                                        <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="edit_proof_mode_link_wali_edit">
                                            <i class="bi bi-link-45deg me-1"></i> Link Drive
                                        </label>
                                    </div>
                                </div>

                                <!-- Preview Bukti Saat Ini -->
                                <div id="current_proof_display_wali_edit" class="mb-2 small" style="display: none;">
                                    <span class="text-muted fw-semibold">Bukti Saat Ini:</span>
                                    <div id="current_proof_badges_wali_edit" class="d-inline-flex flex-wrap gap-1 align-items-center ms-1"></div>
                                </div>

                                <!-- Wadah File Upload -->
                                <div id="proof_file_container_wali_edit">
                                    <input type="file" name="file_bukti[]" multiple class="form-control bg-white" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih berkas foto / dokumen pendukung baru (Maks 10MB per file). Kosongkan jika tetap menggunakan bukti saat ini.</div>
                                </div>

                                <!-- Wadah Link Drive -->
                                <div id="proof_link_container_wali_edit" style="display: none;">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                        <input type="url" name="link_bukti_drive" id="edit_link_drive_wali_edit" class="form-control bg-white" placeholder="https://drive.google.com/...">
                                    </div>
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Salin tautan folder / file Google Drive kegiatan wali kelas untuk menggantikan berkas lama.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-light btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form Delete Wali (Hidden) -->
<form id="formDeleteWali" method="POST" action="laporan-wali.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteWaliId">
</form>

<script>
    function editWali(data) {
        document.getElementById('editWaliId').value = data.id || '';
        document.getElementById('editWaliTanggal').value = data.tanggal || '';
        document.getElementById('editWaliKelas').value = data.kelas || '';
        document.getElementById('editWaliNama').value = data.nama_wali_kelas || '';
        document.getElementById('editWaliDeskripsi').value = data.deskripsi_kegiatan || '';
        setupEditProofModal('wali_edit', data.link_bukti_wali || '');

        var modal = new bootstrap.Modal(document.getElementById('modalEditWali'));
        modal.show();
    }

    function confirmDeleteWali(id, info) {
        Swal.fire({
            title: 'Hapus Kegiatan Wali Kelas?',
            text: 'Data "' + info + '" akan dihapus permanen dari database SQL.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus Data!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteWaliId').value = id;
                document.getElementById('formDeleteWali').submit();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>