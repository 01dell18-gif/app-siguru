<?php
// laporan-ekstra.php - Laporan Kegiatan Ekstrakurikuler
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

$pdo = getDBConnection();
$ekstraList = getDaftarEkstra();
$taList = getDaftarTahunAjaran();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);

$filterTA = $_GET['ta'] ?? $settings['tahun_ajaran'];
if (!empty($filterTA) && !in_array($filterTA, $taList)) {
    array_unshift($taList, $filterTA);
}
$filterSemester = $_GET['semester'] ?? $settings['semester'];
$filterEkstra = $_GET['ekstra'] ?? '';

// Filter Nama Guru / Pembina: Selain Super Admin, langsung terisi nama guru sesuai hak akses akun login
$currentUser = currentUser();
if (!isSuperAdmin()) {
    $filterGuru = $currentUser['nama'] ?? '';
} else {
    $filterGuru = $_GET['guru'] ?? '';
}

// Export CSV / Excel (Dijalankan SEBELUM output header HTML)
if (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv'])) {
    $sqlExp = "SELECT * FROM hasil_ekstra WHERE tahun_ajaran = ? AND semester = ?";
    $paramsExp = [$filterTA, $filterSemester];
    if (!empty($filterEkstra)) {
        $sqlExp .= " AND kegiatan_ekstra LIKE ?";
        $paramsExp[] = "%$filterEkstra%";
    }
    if (!empty($filterGuru)) {
        $sqlExp .= " AND pembina_ekstra = ?";
        $paramsExp[] = $filterGuru;
    }
    $sqlExp .= " ORDER BY tanggal DESC";

    $stmtExp = $pdo->prepare($sqlExp);
    $stmtExp->execute($paramsExp);
    $allRows = $stmtExp->fetchAll();

    // Kop & Metadata
    $subTitle = "Tahun Ajaran: $filterTA | Semester: $filterSemester" 
        . (!empty($filterEkstra) ? " | Cabang Ekstra: $filterEkstra" : "") 
        . (!empty($filterGuru) ? " | Pembina: $filterGuru" : "");
    $excelRows = buildExcelReportHeader('LAPORAN KEGIATAN EKSTRAKURIKULER', $subTitle, $settings);

    // Tabel Catatan Ekstra
    $excelRows[] = ['TABEL CATATAN KEGIATAN EKSTRAKURIKULER'];
    $excelRows[] = ['No', 'Tanggal', 'Cabang Ekstrakurikuler', 'Pembina Ekstra', 'Peserta Hadir', 'Agenda Latihan / Kegiatan', 'Keterangan', 'Link Bukti / Berkas'];
    $noE = 1;
    foreach ($allRows as $r) {
        $excelRows[] = [
            $noE++,
            formatDateIndo($r['tanggal']),
            $r['kegiatan_ekstra'],
            $r['pembina_ekstra'],
            $r['nama_murid'],
            $r['kegiatan_ekstra'],
            $r['keterangan'] ?: '-',
            $r['link_bukti_ekstra'] ?: '-'
        ];
    }

    // Tanda Tangan Resmi Pengesahan (Kepsek & Pembina Ekstra)
    $ttdNama = !empty($filterGuru) ? $filterGuru : (!empty($currentUser['nama']) ? $currentUser['nama'] : 'Pembina Ekstra');
    $ttdNip = !empty($currentUser['nip']) ? $currentUser['nip'] : '-';
    if (!empty($filterGuru)) {
        $stmtG = $pdo->prepare("SELECT nip FROM data_guru WHERE nama_guru = ? LIMIT 1");
        $stmtG->execute([$filterGuru]);
        $gNip = $stmtG->fetchColumn();
        if (!empty($gNip)) $ttdNip = $gNip;
    }
    $sigRows = buildExcelReportSignatures(8, $ttdNama, $ttdNip, 'Pembina Ekstrakurikuler', true, $settings);
    foreach ($sigRows as $sr) {
        $excelRows[] = $sr;
    }

    if (strtolower($_GET['export']) === 'csv') {
        exportToExcelCSV('Laporan_Ekstrakurikuler_' . date('Ymd_His') . '.csv', [], $excelRows);
    } else {
        exportToExcelXLS('Laporan_Ekstrakurikuler_' . date('Ymd_His') . '.xlsx', [], $excelRows);
    }
}

// CRUD Handler Laporan Ekstrakurikuler (Hanya Super Admin atau Pembina Ekstra yang bersangkutan yang dapat melakukan perubahan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // Ambil data rekaman yang akan diubah/dihapus untuk validasi izin
    $existing = null;
    if ($id > 0) {
        $stmtChk = $pdo->prepare("SELECT * FROM hasil_ekstra WHERE id = ?");
        $stmtChk->execute([$id]);
        $existing = $stmtChk->fetch();
    }

    if (!$existing || !canManageReport('ekstra', $existing)) {
        setFlash('danger', 'Akses ditolak: Anda tidak memiliki hak akses untuk mengubah atau menghapus data kegiatan ekstrakurikuler ini.');
        header("Location: laporan-ekstra.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'update') {
        $tgl = trim($_POST['tanggal'] ?? '');
        $ekstra = trim($_POST['kegiatan_ekstra'] ?? '');
        $pembina = trim($_POST['pembina_ekstra'] ?? '');
        $murid = trim($_POST['nama_murid'] ?? '');
        $ket = trim($_POST['keterangan'] ?? '');

        // Logika 2 opsi bukti (Upload File vs Link Drive - hanya salah satu)
        $proofMode = $_POST['proof_mode'] ?? 'file';
        $bukti = $existing['link_bukti_ekstra'] ?? '';

        if ($proofMode === 'link') {
            $driveLink = trim($_POST['link_bukti_drive'] ?? '');
            deleteOldUploadedFiles($existing['link_bukti_ekstra'] ?? '');
            $bukti = $driveLink;
        } else {
            $uploadedFiles = handleFileUpload('file_bukti', 'documents');
            if (!empty($uploadedFiles)) {
                deleteOldUploadedFiles($existing['link_bukti_ekstra'] ?? '');
                $bukti = is_array($uploadedFiles) ? implode(', ', $uploadedFiles) : $uploadedFiles;
            } else {
                // Pertahankan berkas lokal lama, pastikan link drive lama tidak tercampur
                $oldParts = parseBuktiLinks($existing['link_bukti_ekstra'] ?? '');
                $localFiles = array_filter($oldParts, function($p) {
                    return !preg_match('/^https?:\/\//i', $p);
                });
                $bukti = implode(', ', $localFiles);
            }
        }

        if (!empty($tgl)) {
            $stmtUp = $pdo->prepare("UPDATE hasil_ekstra SET 
                tanggal = ?, kegiatan_ekstra = ?, pembina_ekstra = ?, nama_murid = ?,
                keterangan = ?, link_bukti_ekstra = ?
                WHERE id = ?");
            $stmtUp->execute([$tgl, $ekstra, $pembina, $murid, $ket, $bukti, $id]);
            setFlash('success', 'Data kegiatan ekstrakurikuler berhasil diperbarui di database SQL.');
        }
        header("Location: laporan-ekstra.php?" . http_build_query($_GET));
        exit;
    }

    if ($action === 'delete') {
        deleteOldUploadedFiles($existing['link_bukti_ekstra'] ?? '');
        $stmtDel = $pdo->prepare("DELETE FROM hasil_ekstra WHERE id = ?");
        $stmtDel->execute([$id]);
        setFlash('success', 'Data kegiatan ekstrakurikuler berhasil dihapus dari database SQL.');
        header("Location: laporan-ekstra.php?" . http_build_query($_GET));
        exit;
    }
}

$pageTitle = 'Laporan Ekstrakurikuler';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

$rawLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($rawLimit, [10, 20, 50, 100, 250, 500]) ? $rawLimit : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$whereClauses = ["tahun_ajaran = ?", "semester = ?"];
$params = [$filterTA, $filterSemester];
if (!empty($filterEkstra)) {
    $whereClauses[] = "kegiatan_ekstra LIKE ?";
    $params[] = "%$filterEkstra%";
}
if (!empty($filterGuru)) {
    $whereClauses[] = "pembina_ekstra = ?";
    $params[] = $filterGuru;
}

$sqlWhere = "WHERE " . implode(" AND ", $whereClauses);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_ekstra $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM hasil_ekstra $sqlWhere ORDER BY tanggal DESC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$ekstraRows = $stmt->fetchAll();

$printUrl = 'print-laporan.php?' . http_build_query([
    'type' => 'ekstra',
    'ta' => $filterTA,
    'semester' => $filterSemester,
    'ekstra' => $filterEkstra,
    'guru' => $filterGuru
]);
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Laporan Kegiatan Ekstrakurikuler</h4>
            <p class="text-muted small mb-0">Rekap kehadiran latihan mingguan dan materi pengembangan minat bakat siswa.</p>
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
            <div class="col-md-3 col-6">
                <label class="form-label small text-muted mb-0">Cabang Ekstrakurikuler</label>
                <select name="ekstra" class="form-select form-select-sm">
                    <option value="">Semua Ekstra</option>
                    <?php foreach ($ekstraList as $e): ?>
                        <option value="<?= htmlspecialchars($e) ?>" <?= $filterEkstra === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small text-muted mb-0">Nama Guru / Pembina</label>
                <?php if (isSuperAdmin()): ?>
                    <select name="guru" class="form-select form-select-sm">
                        <option value="">Semua Pembina</option>
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
                <a href="laporan-ekstra.php" class="btn btn-outline-secondary btn-sm rounded-circle p-1 px-2" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <!-- Tabel Data Ekstra -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>Agenda / Ekstra</th>
                        <th>Pembina</th>
                        <th>Peserta Hadir</th>
                        <th>Keterangan</th>
                        <th>Bukti</th>
                        <th style="width: 100px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ekstraRows)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Data kegiatan ekstra tidak ditemukan.</td>
                        </tr>
                        <?php else:
                        $no = $offset + 1;
                        foreach ($ekstraRows as $er):
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= formatDateIndo($er['tanggal']) ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1 mb-1 d-inline-block">
                                        <i class="bi bi-award me-1"></i><?= htmlspecialchars($er['kegiatan_ekstra']) ?>
                                    </span>
                                </td>
                                <td class="fw-semibold text-primary"><?= htmlspecialchars($er['pembina_ekstra'] ?: '-') ?></td>
                                <td style="max-width: 250px;"><small><?= htmlspecialchars($er['nama_murid']) ?></small></td>
                                <td style="max-width: 250px;"><small class="text-muted"><?= nl2br(htmlspecialchars($er['keterangan'] ?: '-')) ?></small></td>
                                <td>
                                    <?= renderBuktiButtons($er['link_bukti_ekstra'], 'Bukti Ekstrakurikuler') ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <?php if (canManageReport('ekstra', $er)): ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-icon rounded-pill py-0 px-2"
                                            onclick="editEkstra(<?= htmlspecialchars(json_encode($er), ENT_QUOTES, 'UTF-8') ?>)" title="Edit Data">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-icon rounded-pill py-0 px-2 ms-1"
                                            onclick="confirmDeleteEkstra(<?= (int)$er['id'] ?>, '<?= htmlspecialchars(addslashes($er['kegiatan_ekstra']), ENT_QUOTES, 'UTF-8') ?>')" title="Hapus Data">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-1 px-2" title="Hanya pembina ekstra bersangkutan (<?= htmlspecialchars($er['pembina_ekstra'] ?: $er['kegiatan_ekstra']) ?>) atau Super Admin yang dapat mengubah data ini">
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

<!-- Modal Edit Kegiatan Ekstra -->
<div class="modal fade" id="modalEditEkstra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="laporan-ekstra.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editEkstraId">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold fs-6">Edit Kegiatan Ekstrakurikuler</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Tanggal</label>
                            <input type="date" name="tanggal" id="editEkstraTanggal" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Cabang Ekstrakurikuler</label>
                            <select name="kegiatan_ekstra" id="editEkstraCabang" class="form-select form-select-sm" required>
                                <?php foreach ($ekstraList as $e): ?>
                                    <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Pembina Ekstrakurikuler</label>
                            <input type="text" name="pembina_ekstra" id="editEkstraPembina" list="listEkstraPembina" class="form-control form-control-sm" required>
                            <datalist id="listEkstraPembina">
                                <?php foreach ($guruList as $g): ?>
                                    <option value="<?= htmlspecialchars($g) ?>">
                                    <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Daftar Siswa Hadir (Pisahkan dengan koma)</label>
                            <textarea name="nama_murid" id="editEkstraMurid" class="form-control form-control-sm" rows="2" placeholder="Nama-nama siswa hadir..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Materi / Agenda Latihan</label>
                            <textarea name="keterangan" id="editEkstraKet" class="form-control form-control-sm" rows="2" placeholder="Agenda latihan..." required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="card p-3 border rounded-3 bg-light-subtle">
                                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                    <label class="form-label fw-semibold small text-secondary mb-0">
                                        <i class="bi bi-paperclip me-1"></i> Bukti Dokumentasi Kegiatan Ekstrakurikuler
                                    </label>
                                    <!-- Minimalist Switch / Segmented Control -->
                                    <div class="btn-group btn-group-sm p-1 bg-white border rounded-pill shadow-xs" role="group">
                                        <input type="radio" class="btn-check" name="proof_mode" id="edit_proof_mode_file_ekstra_edit" value="file" checked onchange="toggleProofInput('ekstra_edit', 'file')">
                                        <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="edit_proof_mode_file_ekstra_edit">
                                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                        </label>

                                        <input type="radio" class="btn-check" name="proof_mode" id="edit_proof_mode_link_ekstra_edit" value="link" onchange="toggleProofInput('ekstra_edit', 'link')">
                                        <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="edit_proof_mode_link_ekstra_edit">
                                            <i class="bi bi-link-45deg me-1"></i> Link Drive
                                        </label>
                                    </div>
                                </div>

                                <!-- Preview Bukti Saat Ini -->
                                <div id="current_proof_display_ekstra_edit" class="mb-2 small" style="display: none;">
                                    <span class="text-muted fw-semibold">Bukti Saat Ini:</span>
                                    <div id="current_proof_badges_ekstra_edit" class="d-inline-flex flex-wrap gap-1 align-items-center ms-1"></div>
                                </div>

                                <!-- Wadah File Upload -->
                                <div id="proof_file_container_ekstra_edit">
                                    <input type="file" name="file_bukti[]" multiple class="form-control bg-white" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih berkas foto / dokumen pendukung ekstra baru (Maks 10MB per file). Kosongkan jika tetap menggunakan bukti saat ini.</div>
                                </div>

                                <!-- Wadah Link Drive -->
                                <div id="proof_link_container_ekstra_edit" style="display: none;">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                        <input type="url" name="link_bukti_drive" id="edit_link_drive_ekstra_edit" class="form-control bg-white" placeholder="https://drive.google.com/...">
                                    </div>
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Salin tautan folder / file Google Drive dokumentasi ekstra untuk menggantikan berkas lama.</div>
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

<!-- Form Delete Ekstra (Hidden) -->
<form id="formDeleteEkstra" method="POST" action="laporan-ekstra.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteEkstraId">
</form>

<script>
    function editEkstra(data) {
        document.getElementById('editEkstraId').value = data.id || '';
        document.getElementById('editEkstraTanggal').value = data.tanggal || '';
        document.getElementById('editEkstraCabang').value = data.kegiatan_ekstra || '';
        document.getElementById('editEkstraPembina').value = data.pembina_ekstra || '';
        document.getElementById('editEkstraMurid').value = data.nama_murid || '';
        document.getElementById('editEkstraKet').value = data.keterangan || '';
        setupEditProofModal('ekstra_edit', data.link_bukti_ekstra || '');

        var modal = new bootstrap.Modal(document.getElementById('modalEditEkstra'));
        modal.show();
    }

    function confirmDeleteEkstra(id, info) {
        Swal.fire({
            title: 'Hapus Kegiatan Ekstra?',
            text: 'Data kegiatan "' + info + '" akan dihapus permanen dari database SQL.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus Data!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteEkstraId').value = id;
                document.getElementById('formDeleteEkstra').submit();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>