<?php
// laporan-guruwali.php - Laporan Bimbingan Guru Wali
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

$pdo = getDBConnection();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);
$taList = getDaftarTahunAjaran();

$filterTA = $_GET['ta'] ?? $settings['tahun_ajaran'];
if (!empty($filterTA) && !in_array($filterTA, $taList)) {
    array_unshift($taList, $filterTA);
}
$filterSemester = $_GET['semester'] ?? $settings['semester'];

// Filter Nama Guru Wali: Selain Super Admin, langsung terisi nama guru sesuai hak akses akun login
$currentUser = currentUser();
if (!isSuperAdmin()) {
    $filterGuru = $currentUser['nama'] ?? '';
} else {
    $filterGuru = $_GET['guru'] ?? '';
}

// Export CSV / Excel (Dijalankan SEBELUM output header HTML)
if (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv'])) {
    $sqlExp = "SELECT * FROM hasil_guru_wali WHERE tahun_ajaran = ? AND semester = ?";
    $paramsExp = [$filterTA, $filterSemester];
    if (!empty($filterGuru)) {
        $sqlExp .= " AND nama_guru_wali = ?";
        $paramsExp[] = $filterGuru;
    }
    $sqlExp .= " ORDER BY tanggal DESC";

    $stmtExp = $pdo->prepare($sqlExp);
    $stmtExp->execute($paramsExp);
    $allRows = $stmtExp->fetchAll();

    // Kop & Metadata
    $subTitle = "Tahun Ajaran: $filterTA | Semester: $filterSemester" 
        . (!empty($filterGuru) ? " | Guru Wali: $filterGuru" : "");
    $excelRows = buildExcelReportHeader('LAPORAN BIMBINGAN GURU WALI', $subTitle, $settings);

    // Tabel Catatan Bimbingan Guru Wali
    $excelRows[] = ['TABEL CATATAN BIMBINGAN & KONSELING GURU WALI'];
    $excelRows[] = ['No', 'Tanggal', 'Guru Wali', 'Siswa Bimbingan', 'Kategori', 'Topik Masalah & Konseling', 'Tindak Lanjut', 'Keterangan', 'Link Bukti / Berkas'];
    $noGW = 1;
    foreach ($allRows as $r) {
        $excelRows[] = [
            $noGW++,
            formatDateIndo($r['tanggal']),
            $r['nama_guru_wali'],
            $r['nama_murid'],
            $r['kategori'],
            $r['topik_masalah'],
            $r['tindak_lanjut'],
            $r['keterangan'],
            $r['file_bukti'] ?: '-'
        ];
    }

    // Tanda Tangan Resmi Pengesahan (Kepsek & Guru Wali)
    $ttdNama = !empty($filterGuru) ? $filterGuru : (!empty($currentUser['nama']) ? $currentUser['nama'] : 'Guru Wali');
    $ttdNip = !empty($currentUser['nip']) ? $currentUser['nip'] : '-';
    if (!empty($filterGuru)) {
        $stmtG = $pdo->prepare("SELECT nip FROM data_guru WHERE nama_guru = ? LIMIT 1");
        $stmtG->execute([$filterGuru]);
        $gNip = $stmtG->fetchColumn();
        if (!empty($gNip)) $ttdNip = $gNip;
    }
    $sigRows = buildExcelReportSignatures(9, $ttdNama, $ttdNip, 'Guru Wali', true, $settings);
    foreach ($sigRows as $sr) {
        $excelRows[] = $sr;
    }

    if (strtolower($_GET['export']) === 'csv') {
        exportToExcelCSV('Laporan_Guru_Wali_' . date('Ymd_His') . '.csv', [], $excelRows);
    } else {
        exportToExcelXLS('Laporan_Guru_Wali_' . date('Ymd_His') . '.xlsx', [], $excelRows);
    }
}

// CRUD Handler Laporan Guru Wali (Hanya Super Admin atau Guru Wali yang bersangkutan yang dapat melakukan perubahan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // Ambil data rekaman yang akan diubah/dihapus untuk validasi izin
    $existing = null;
    if ($id > 0) {
        $stmtChk = $pdo->prepare("SELECT * FROM hasil_guru_wali WHERE id = ?");
        $stmtChk->execute([$id]);
        $existing = $stmtChk->fetch();
    }

    if (!$existing || !canManageReport('guruwali', $existing)) {
        setFlash('danger', 'Akses ditolak: Anda tidak memiliki hak akses untuk mengubah atau menghapus data bimbingan guru wali ini.');
        header("Location: laporan-guruwali.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'update') {
        $tgl = trim($_POST['tanggal'] ?? '');
        $guru = trim($_POST['nama_guru_wali'] ?? '');
        $murid = trim($_POST['nama_murid'] ?? '');
        $kat = trim($_POST['kategori'] ?? '');
        $topik = trim($_POST['topik_masalah'] ?? '');
        $tindak = trim($_POST['tindak_lanjut'] ?? '');
        $ket = trim($_POST['keterangan'] ?? '');

        // Logika 2 opsi bukti (Upload File vs Link Drive - hanya salah satu)
        $proofMode = $_POST['proof_mode'] ?? 'file';
        $bukti = $existing['file_bukti'] ?? '';

        if ($proofMode === 'link') {
            $driveLink = trim($_POST['link_bukti_drive'] ?? '');
            deleteOldUploadedFiles($existing['file_bukti'] ?? '');
            $bukti = $driveLink;
        } else {
            $uploadedFiles = handleFileUpload('file_bukti', 'documents');
            if (!empty($uploadedFiles)) {
                deleteOldUploadedFiles($existing['file_bukti'] ?? '');
                $bukti = is_array($uploadedFiles) ? implode(', ', $uploadedFiles) : $uploadedFiles;
            } else {
                // Pertahankan berkas lokal lama, pastikan link drive lama tidak tercampur
                $oldParts = parseBuktiLinks($existing['file_bukti'] ?? '');
                $localFiles = array_filter($oldParts, function($p) {
                    return !preg_match('/^https?:\/\//i', $p);
                });
                $bukti = implode(', ', $localFiles);
            }
        }

        if (!empty($tgl)) {
            $stmtUp = $pdo->prepare("UPDATE hasil_guru_wali SET 
                tanggal = ?, nama_guru_wali = ?, nama_murid = ?, kategori = ?,
                topik_masalah = ?, tindak_lanjut = ?, keterangan = ?, file_bukti = ?
                WHERE id = ?");
            $stmtUp->execute([$tgl, $guru, $murid, $kat, $topik, $tindak, $ket, $bukti, $id]);
            setFlash('success', 'Data bimbingan guru wali berhasil diperbarui di database SQL.');
        }
        header("Location: laporan-guruwali.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'delete') {
        deleteOldUploadedFiles($existing['file_bukti'] ?? '');
        $stmtDel = $pdo->prepare("DELETE FROM hasil_guru_wali WHERE id = ?");
        $stmtDel->execute([$id]);
        setFlash('success', 'Data bimbingan guru wali berhasil dihapus dari database SQL.');
        header("Location: laporan-guruwali.php?" . http_build_query($_GET));
        exit;
    }
}

$pageTitle = 'Laporan Bimbingan Guru Wali';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

$rawLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($rawLimit, [10, 20, 50, 100, 250, 500]) ? $rawLimit : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$whereClauses = ["tahun_ajaran = ?", "semester = ?"];
$params = [$filterTA, $filterSemester];
if (!empty($filterGuru)) {
    $whereClauses[] = "nama_guru_wali = ?";
    $params[] = $filterGuru;
}

$sqlWhere = "WHERE " . implode(" AND ", $whereClauses);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_guru_wali $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM hasil_guru_wali $sqlWhere ORDER BY tanggal DESC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$gwRows = $stmt->fetchAll();

$printUrl = 'print-laporan.php?' . http_build_query([
    'type' => 'guruwali',
    'ta' => $filterTA,
    'semester' => $filterSemester,
    'guru' => $filterGuru
]);
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Laporan Bimbingan Guru Wali</h4>
            <p class="text-muted small mb-0">Rekap sesi konseling, penanganan siswa binaan, dan tindak lanjut pembinaan.</p>
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
            <div class="col-md-3 col-6">
                <label class="form-label small text-muted mb-0">Tahun Ajaran</label>
                <select name="ta" class="form-select form-select-sm">
                    <?php foreach ($taList as $taOpt): ?>
                        <option value="<?= htmlspecialchars($taOpt) ?>" <?= $filterTA === $taOpt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($taOpt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small text-muted mb-0">Semester</label>
                <select name="semester" class="form-select form-select-sm">
                    <option value="Ganjil" <?= $filterSemester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                    <option value="Genap" <?= $filterSemester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small text-muted mb-0">Guru Wali</label>
                <?php if (isSuperAdmin()): ?>
                    <select name="guru" class="form-select form-select-sm">
                        <option value="">Semua Guru Wali</option>
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
            <div class="col-md-3 col-6">
                <label class="form-label small text-muted mb-0">Per Halaman</label>
                <select name="limit" class="form-select form-select-sm">
                    <?php foreach ([10, 20, 50, 100, 250, 500] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 text-end mt-2 d-flex justify-content-end align-items-center gap-2">
                <a href="laporan-guruwali.php" class="btn btn-outline-secondary btn-sm rounded-circle p-1 px-2" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <!-- Tabel Data Bimbingan -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>Guru Wali</th>
                        <th>Siswa Bimbingan</th>
                        <th>Kategori</th>
                        <th>Topik Masalah</th>
                        <th>Tindak Lanjut</th>
                        <th>Bukti</th>
                        <th style="width: 100px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gwRows)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Data bimbingan guru wali tidak ditemukan.</td>
                        </tr>
                        <?php else:
                        $no = $offset + 1;
                        foreach ($gwRows as $gr):
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= formatDateIndo($gr['tanggal']) ?></td>
                                <td class="fw-semibold text-primary"><?= htmlspecialchars($gr['nama_guru_wali']) ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($gr['nama_murid']) ?></td>
                                <td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($gr['kategori']) ?></span></td>
                                <td style="max-width: 250px;"><?= nl2br(htmlspecialchars($gr['topik_masalah'])) ?></td>
                                <td style="max-width: 250px;"><?= nl2br(htmlspecialchars($gr['tindak_lanjut'])) ?></td>
                                <td>
                                    <?= renderBuktiButtons($gr['file_bukti'], 'Bukti Bimbingan') ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <?php if (canManageReport('guruwali', $gr)): ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-icon rounded-pill py-0 px-2"
                                            onclick="editGuruWali(<?= htmlspecialchars(json_encode($gr), ENT_QUOTES, 'UTF-8') ?>)" title="Edit Data">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-icon rounded-pill py-0 px-2 ms-1"
                                            onclick="confirmDeleteGuruWali(<?= (int)$gr['id'] ?>, '<?= htmlspecialchars(addslashes($gr['nama_murid']), ENT_QUOTES, 'UTF-8') ?>')" title="Hapus Data">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-1 px-2" title="Hanya guru wali bersangkutan (<?= htmlspecialchars($gr['nama_guru_wali']) ?>) atau Super Admin yang dapat mengubah data ini">
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

<!-- Modal Edit Bimbingan Guru Wali -->
<div class="modal fade" id="modalEditGuruWali" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="laporan-guruwali.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editGWId">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold fs-6">Edit Bimbingan Guru Wali</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Tanggal</label>
                            <input type="date" name="tanggal" id="editGWTanggal" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Kategori</label>
                            <select name="kategori" id="editGWKategori" class="form-select form-select-sm" required>
                                <option value="Akademik">Akademik</option>
                                <option value="Perilaku">Perilaku</option>
                                <option value="Kehadiran">Kehadiran</option>
                                <option value="Pribadi / Sosial">Pribadi / Sosial</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Guru Wali</label>
                            <input type="text" name="nama_guru_wali" id="editGWGuru" list="listGWGuru" class="form-control form-control-sm" required>
                            <datalist id="listGWGuru">
                                <?php foreach ($guruList as $g): ?>
                                    <option value="<?= htmlspecialchars($g) ?>">
                                    <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Siswa Bimbingan</label>
                            <input type="text" name="nama_murid" id="editGWMurid" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Topik Masalah</label>
                            <textarea name="topik_masalah" id="editGWTopik" class="form-control form-control-sm" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Tindak Lanjut Solusi</label>
                            <textarea name="tindak_lanjut" id="editGWTindak" class="form-control form-control-sm" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="card p-3 border rounded-3 bg-light-subtle">
                                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                    <label class="form-label fw-semibold small text-secondary mb-0">
                                        <i class="bi bi-paperclip me-1"></i> Bukti Dokumentasi Bimbingan Guru Wali
                                    </label>
                                    <!-- Minimalist Switch / Segmented Control -->
                                    <div class="btn-group btn-group-sm p-1 bg-white border rounded-pill shadow-xs" role="group">
                                        <input type="radio" class="btn-check" name="proof_mode" id="edit_proof_mode_file_gw_edit" value="file" checked onchange="toggleProofInput('gw_edit', 'file')">
                                        <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="edit_proof_mode_file_gw_edit">
                                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                        </label>

                                        <input type="radio" class="btn-check" name="proof_mode" id="edit_proof_mode_link_gw_edit" value="link" onchange="toggleProofInput('gw_edit', 'link')">
                                        <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="edit_proof_mode_link_gw_edit">
                                            <i class="bi bi-link-45deg me-1"></i> Link Drive
                                        </label>
                                    </div>
                                </div>

                                <!-- Preview Bukti Saat Ini -->
                                <div id="current_proof_display_gw_edit" class="mb-2 small" style="display: none;">
                                    <span class="text-muted fw-semibold">Bukti Saat Ini:</span>
                                    <div id="current_proof_badges_gw_edit" class="d-inline-flex flex-wrap gap-1 align-items-center ms-1"></div>
                                </div>

                                <!-- Wadah File Upload -->
                                <div id="proof_file_container_gw_edit">
                                    <input type="file" name="file_bukti[]" multiple class="form-control bg-white" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih berkas foto / dokumen pendukung bimbingan baru (Maks 10MB per file). Kosongkan jika tetap menggunakan bukti saat ini.</div>
                                </div>

                                <!-- Wadah Link Drive -->
                                <div id="proof_link_container_gw_edit" style="display: none;">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                        <input type="url" name="link_bukti_drive" id="edit_link_drive_gw_edit" class="form-control bg-white" placeholder="https://drive.google.com/...">
                                    </div>
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Salin tautan folder / file Google Drive bimbingan untuk menggantikan berkas lama.</div>
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

<!-- Form Delete Guru Wali (Hidden) -->
<form id="formDeleteGW" method="POST" action="laporan-guruwali.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteGWId">
</form>

<script>
    function editGuruWali(data) {
        document.getElementById('editGWId').value = data.id || '';
        document.getElementById('editGWTanggal').value = data.tanggal || '';
        document.getElementById('editGWGuru').value = data.nama_guru_wali || '';
        document.getElementById('editGWMurid').value = data.nama_murid || '';
        document.getElementById('editGWKategori').value = data.kategori || 'Akademik';
        document.getElementById('editGWTopik').value = data.topik_masalah || '';
        document.getElementById('editGWTindak').value = data.tindak_lanjut || '';
        setupEditProofModal('gw_edit', data.file_bukti || '');

        var modal = new bootstrap.Modal(document.getElementById('modalEditGuruWali'));
        modal.show();
    }

    function confirmDeleteGuruWali(id, murid) {
        Swal.fire({
            title: 'Hapus Data Bimbingan?',
            text: 'Data bimbingan siswa "' + murid + '" akan dihapus permanen dari database SQL.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus Data!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteGWId').value = id;
                document.getElementById('formDeleteGW').submit();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>