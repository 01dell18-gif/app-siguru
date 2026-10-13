<?php
// master-guru.php - Kelola Master Data Guru
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

if (!hasAccess('master_guru')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke menu Data Guru.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$kelasList = getDaftarKelas();
$ekstraList = getDaftarEkstra();

// 1. Export Excel / Template / CSV (Dijalankan SEBELUM output header HTML dikirim)
$isTemplate = isset($_GET['template']) || (isset($_GET['export']) && strtolower($_GET['export']) === 'template');
if ($isTemplate || (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv']))) {
    $rows = $pdo->query("SELECT * FROM data_guru ORDER BY id ASC")->fetchAll();
    $data = [];
    $no = 1;

    // Jika unduh template, atau data di tabel masih kosong, sertakan data contoh (dummy) sebagai pedoman pengisian
    if ($isTemplate || empty($rows)) {
        $data = [
            [1, 'Drs. Budi Santoso, M.Pd.', '197503122000031002', 'Ya', 'X TKJ 1', 'Pramuka'],
            [2, 'Siti Nurhaliza, S.Pd.', '198506242008012005', 'Ya', 'XI RPL 2', '-'],
            [3, 'Ahmad Fauzi, S.Kom.', '199201152019031004', 'Tidak', '-', 'Futsal'],
            [4, 'Dewi Sartika, S.Pd.', '-', 'Tidak', '-', 'PMR'],
            [5, 'Eko Prasetyo, M.Kom.', '198911052015021003', 'Tidak', 'XII MM 1', 'Robotik']
        ];
        $filenamePrefix = 'Template_Import_Data_Guru_';
    } else {
        foreach ($rows as $r) {
            $data[] = [
                $no++,
                $r['nama_guru'],
                $r['nip'] ?: '-',
                $r['sebagai_guru_wali'],
                $r['sebagai_wali_kelas'] ?: '-',
                $r['pembina_ekstra'] ?: '-'
            ];
        }
        $filenamePrefix = 'Data_Guru_';
    }

    $format = strtolower($_GET['export'] ?? ($_GET['template'] ?? 'xlsx'));
    if ($format === 'csv') {
        exportToExcelCSV($filenamePrefix . date('Ymd_His') . '.csv', ['No', 'Nama Guru', 'NIP', 'Sebagai Guru Wali', 'Sebagai Wali Kelas', 'Pembina Ekstra'], $data);
    } else {
        exportToExcelXLS($filenamePrefix . date('Ymd_His') . '.xlsx', ['No', 'Nama Guru', 'NIP', 'Sebagai Guru Wali', 'Sebagai Wali Kelas', 'Pembina Ekstra'], $data);
    }
}

// 2. Aksi CRUD (Tambah, Edit, Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nama = trim($_POST['nama_guru'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $guruWali = $_POST['sebagai_guru_wali'] ?? 'Tidak';
        $waliKelas = $_POST['sebagai_wali_kelas'] ?? '-';
        $pembinaEkstra = trim($_POST['pembina_ekstra'] ?? '-');

        if (!empty($nama)) {
            $stmt = $pdo->prepare("INSERT INTO data_guru (nama_guru, nip, sebagai_guru_wali, sebagai_wali_kelas, pembina_ekstra) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $nip, $guruWali, $waliKelas, $pembinaEkstra]);
            setFlash('success', 'Data guru berhasil ditambahkan.');
        }
        header("Location: master-guru.php");
        exit;
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $nama = trim($_POST['nama_guru'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $guruWali = $_POST['sebagai_guru_wali'] ?? 'Tidak';
        $waliKelas = $_POST['sebagai_wali_kelas'] ?? '-';
        $pembinaEkstra = trim($_POST['pembina_ekstra'] ?? '-');

        $stmt = $pdo->prepare("UPDATE data_guru SET nama_guru = ?, nip = ?, sebagai_guru_wali = ?, sebagai_wali_kelas = ?, pembina_ekstra = ? WHERE id = ?");
        $stmt->execute([$nama, $nip, $guruWali, $waliKelas, $pembinaEkstra, $id]);
        setFlash('success', 'Data guru berhasil diperbarui.');
        header("Location: master-guru.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM data_guru WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Data guru berhasil dihapus.');
        header("Location: master-guru.php");
        exit;
    }

    // Import Excel (.xlsx / .xls) / CSV
    if ($action === 'import') {
        if (isset($_FILES['file_import']) && $_FILES['file_import']['error'] === UPLOAD_ERR_OK) {
            $rows = parseImportRows($_FILES['file_import']['tmp_name']);
            if (!empty($rows)) {
                $pdo->exec("DELETE FROM data_guru");
                $stmt = $pdo->prepare("INSERT INTO data_guru (nama_guru, nip, sebagai_guru_wali, sebagai_wali_kelas, pembina_ekstra) VALUES (?, ?, ?, ?, ?)");
                
                // Cari indeks kolom berdasarkan nama header (jika baris pertama adalah header)
                $firstRow = $rows[0];
                $colNama = 1;
                $colNip = 2;
                $colGw = 3;
                $colWk = 4;
                $colPe = 5;
                
                $hasHeader = false;
                foreach ($firstRow as $idx => $cell) {
                    $cLower = strtolower(trim((string)$cell));
                    if (strpos($cLower, 'nama') !== false) { $colNama = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'nip') !== false) { $colNip = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'guru wali') !== false) { $colGw = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'wali kelas') !== false) { $colWk = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'ekstra') !== false || strpos($cLower, 'pembina') !== false) { $colPe = $idx; $hasHeader = true; }
                }

                $inserted = 0;
                foreach ($rows as $idx => $data) {
                    if ($idx === 0 && $hasHeader) continue;
                    if (count($data) >= 2) {
                        $nama = trim($data[$colNama] ?? '');
                        if (empty($nama) && !empty($data[0]) && !is_numeric($data[0])) {
                            $nama = trim($data[0]);
                        }
                        
                        // Abaikan baris rusak atau artefak script
                        if (empty($nama) || in_array($nama, ['«', '»', '<', '>', '&lt;', '&gt;', '+', '"+"']) || stripos($nama, 'c_rgszSh') !== false) {
                            continue;
                        }

                        $nip = trim($data[$colNip] ?? '');
                        if (in_array($nip, ['«', '»', '<', '>', '&lt;', '&gt;'])) $nip = '';

                        $rawGw = strtolower(trim($data[$colGw] ?? ''));
                        $gw = in_array($rawGw, ['ya', 'iya', 'y', 'yes', 'true', '1']) ? 'Ya' : 'Tidak';

                        $wk = trim($data[$colWk] ?? '-');
                        if (in_array(strtolower($wk), ['tidak', 'bukan', 'no', 'false', '0', ''])) {
                            $wk = '-';
                        }

                        $pe = trim($data[$colPe] ?? '-');
                        if (in_array(strtolower($pe), ['tidak', 'bukan', 'no', 'false', '0', ''])) {
                            $pe = '-';
                        }

                        $stmt->execute([$nama, $nip, $gw, $wk, $pe]);
                        $inserted++;
                    }
                }
                setFlash('success', 'Import berhasil: ' . $inserted . ' data guru disimpan ke database.');
            } else {
                setFlash('danger', 'Format berkas tidak valid atau data kosong.');
            }
        }
        header("Location: master-guru.php");
        exit;
    }
}

$pageTitle = 'Master Data Guru';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

// 4. Pencarian & Pagination
$search = trim($_GET['q'] ?? '');
$rawLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($rawLimit, [10, 20, 50, 100, 250, 500]) ? $rawLimit : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sqlWhere = "";
$params = [];
if (!empty($search)) {
    $sqlWhere = "WHERE nama_guru LIKE ? OR nip LIKE ? OR sebagai_wali_kelas LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM data_guru $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM data_guru $sqlWhere ORDER BY id ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$guruList = $stmt->fetchAll();
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Master Data Guru</h4>
            <p class="text-muted small mb-0">Kelola informasi tenaga pendidik, guru wali, dan wali kelas.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <button class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddGuru">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Guru
            </button>
            <a href="master-guru.php?export=xlsx" class="btn btn-outline-success rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
            </a>
            <a href="master-guru.php?template=xlsx" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" title="Unduh template Excel dengan pedoman data contoh (dummy)">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template (.xlsx)
            </a>
            <button class="btn btn-outline-primary rounded-pill px-3 btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportGuru">
                <i class="bi bi-upload me-1"></i> Import Excel
            </button>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card card-table p-3 mb-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama guru, NIP, atau kelas..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 50, 100, 250, 500] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?> data / hal</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100">Filter</button>
            </div>
            <?php if (!empty($search)): ?>
            <div class="col-12 col-lg-3">
                <a href="master-guru.php" class="btn btn-link btn-sm text-decoration-none text-muted p-0">Reset Pencarian</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabel Data Guru -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">No</th>
                        <th>Nama Guru</th>
                        <th>NIP</th>
                        <th>Sebagai Guru Wali</th>
                        <th>Sebagai Wali Kelas</th>
                        <th>Pembina Ekstra</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guruList)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Data guru tidak ditemukan.</td></tr>
                    <?php else: 
                        $no = $offset + 1;
                        foreach ($guruList as $g): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($g['nama_guru']) ?></td>
                            <td><?= htmlspecialchars($g['nip'] ?: '-') ?></td>
                            <td>
                                <?php if ($g['sebagai_guru_wali'] === 'Ya'): ?>
                                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1"><i class="bi bi-check-circle me-1"></i> Ya</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Tidak</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($g['sebagai_wali_kelas'] && $g['sebagai_wali_kelas'] !== '-'): ?>
                                    <span class="badge bg-primary-subtle text-primary fw-bold px-2 py-1"><?= htmlspecialchars($g['sebagai_wali_kelas']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($g['pembina_ekstra']) && $g['pembina_ekstra'] !== '-'): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold px-2 py-1"><i class="bi bi-award me-1"></i><?= htmlspecialchars($g['pembina_ekstra']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" 
                                    onclick="editGuru(<?= htmlspecialchars(json_encode($g), ENT_QUOTES, 'UTF-8') ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 ms-1" 
                                    onclick="deleteGuru(<?= (int)$g['id'] ?>, <?= htmlspecialchars(json_encode($g['nama_guru']), ENT_QUOTES, 'UTF-8') ?>)" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <small class="text-muted">Menampilkan <?= min($totalRows, $offset + 1) ?> - <?= min($totalRows, $offset + $limit) ?> dari total <?= $totalRows ?> data</small>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&q=<?= urlencode($search) ?>">Sebelumnya</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&q=<?= urlencode($search) ?>">Selanjutnya</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Tambah Guru -->
<div class="modal fade" id="modalAddGuru" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Data Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-guru.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Guru Lengkap</label>
                        <input type="text" name="nama_guru" class="form-control" required placeholder="Contoh: Budi Prasetyo, M.Kom">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">NIP (Nomor Induk Pegawai)</label>
                        <input type="text" name="nip" class="form-control" placeholder="19800101 200501 1 003">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Sebagai Guru Wali</label>
                        <select name="sebagai_guru_wali" class="form-select">
                            <option value="Tidak">Tidak</option>
                            <option value="Ya">Ya</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Sebagai Wali Kelas (Pilih Kelas)</label>
                        <select name="sebagai_wali_kelas" class="form-select">
                            <option value="-">- Bukan Wali Kelas -</option>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pembina Ekstrakurikuler</label>
                        <select name="pembina_ekstra" class="form-select">
                            <option value="-">- Bukan Pembina Ekstra -</option>
                            <?php foreach ($ekstraList as $e): ?>
                                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Modal Edit Guru -->
<div class="modal fade" id="modalEditGuru" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Data Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-guru.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Guru Lengkap</label>
                        <input type="text" name="nama_guru" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">NIP</label>
                        <input type="text" name="nip" id="edit_nip" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Sebagai Guru Wali</label>
                        <select name="sebagai_guru_wali" id="edit_gw" class="form-select">
                            <option value="Tidak">Tidak</option>
                            <option value="Ya">Ya</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Sebagai Wali Kelas</label>
                        <select name="sebagai_wali_kelas" id="edit_wk" class="form-select">
                            <option value="-">- Bukan Wali Kelas -</option>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pembina Ekstrakurikuler</label>
                        <select name="pembina_ekstra" id="edit_pe" class="form-select">
                            <option value="-">- Bukan Pembina Ekstra -</option>
                            <?php foreach ($ekstraList as $e): ?>
                                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Modal Import Guru -->
<div class="modal fade" id="modalImportGuru" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Import Data Guru (Excel .xls / CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-guru.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Perhatian: Sesuai ketentuan, data lama akan dihapus dan digantikan data baru yang diimport.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pilih File Excel (.xlsx / .xls) atau CSV</label>
                        <input type="file" name="file_import" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <small class="text-muted d-block mb-2">Format kolom: No, Nama Guru, NIP, Sebagai Guru Wali (Ya/Tidak), Sebagai Wali Kelas (Nama Kelas), Pembina Ekstra</small>
                    <a href="master-guru.php?template=xlsx" class="btn btn-outline-success btn-sm rounded-pill px-3">
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

<!-- Hidden Delete Form -->
<form id="formDeleteGuru" method="POST" action="master-guru.php" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function editGuru(g) {
    document.getElementById('edit_id').value = g.id;
    document.getElementById('edit_nama').value = g.nama_guru;
    document.getElementById('edit_nip').value = g.nip;
    document.getElementById('edit_gw').value = g.sebagai_guru_wali;
    document.getElementById('edit_wk').value = g.sebagai_wali_kelas;
    document.getElementById('edit_pe').value = g.pembina_ekstra || '-';
    new bootstrap.Modal(document.getElementById('modalEditGuru')).show();
}

function deleteGuru(id, name) {
    Swal.fire({
        title: 'Hapus Guru?',
        text: 'Apakah Anda yakin ingin menghapus data ' + name + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_id').value = id;
            document.getElementById('formDeleteGuru').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
