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
        $waliKelas = trim($_POST['sebagai_wali_kelas'] ?? '-');
        if ($waliKelas === '') $waliKelas = '-';
        
        if (isset($_POST['pembina_ekstra']) && is_array($_POST['pembina_ekstra'])) {
            $cleanPe = array_values(array_filter(array_map('trim', $_POST['pembina_ekstra']), function($v) {
                return $v !== '' && $v !== '-';
            }));
            $pembinaEkstra = !empty($cleanPe) ? implode(', ', array_unique($cleanPe)) : '-';
        } else {
            $pembinaEkstra = trim($_POST['pembina_ekstra'] ?? '-');
            if (empty($pembinaEkstra)) $pembinaEkstra = '-';
        }

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
        $waliKelas = trim($_POST['sebagai_wali_kelas'] ?? '-');
        if ($waliKelas === '') $waliKelas = '-';

        if (isset($_POST['pembina_ekstra']) && is_array($_POST['pembina_ekstra'])) {
            $cleanPe = array_values(array_filter(array_map('trim', $_POST['pembina_ekstra']), function($v) {
                return $v !== '' && $v !== '-';
            }));
            $pembinaEkstra = !empty($cleanPe) ? implode(', ', array_unique($cleanPe)) : '-';
        } else {
            $pembinaEkstra = trim($_POST['pembina_ekstra'] ?? '-');
            if (empty($pembinaEkstra)) $pembinaEkstra = '-';
        }

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
                                <?php 
                                $peList = array_values(array_filter(array_map('trim', explode(',', $g['pembina_ekstra'] ?? ''))));
                                if (!empty($peList) && $peList[0] !== '-'): 
                                    foreach ($peList as $peItem): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle fw-semibold px-2 py-1 mb-1 me-1 d-inline-block">
                                            <i class="bi bi-award me-1"></i><?= htmlspecialchars($peItem) ?>
                                        </span>
                                    <?php endforeach;
                                else: ?>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Data Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-guru.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Nama Guru Lengkap</label>
                            <input type="text" name="nama_guru" class="form-control" required placeholder="Contoh: Budi Prasetyo, M.Kom">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">NIP (Nomor Induk Pegawai)</label>
                            <input type="text" name="nip" class="form-control" placeholder="19800101 200501 1 003">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Sebagai Guru Wali</label>
                            <select name="sebagai_guru_wali" class="form-select">
                                <option value="Tidak">Tidak</option>
                                <option value="Ya">Ya</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12 position-relative" id="comboboxWaliKelasAdd">
                            <label class="form-label fw-semibold small d-flex justify-content-between align-items-center mb-1">
                                <span>Sebagai Wali Kelas (Pilih / Ketik Kelas)</span>
                                <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.72rem;"><i class="bi bi-keyboard me-1"></i>Bisa Diketik</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" 
                                       name="sebagai_wali_kelas" 
                                       id="add_wali_kelas_input" 
                                       class="form-control" 
                                       placeholder="-- Ketik atau Pilih Kelas (- jika bukan wali) --" 
                                       value="-" 
                                       autocomplete="off"
                                       list="list_kelas_guru">
                                <button class="btn btn-outline-secondary" type="button" id="btnToggleWaliKelasAdd" title="Tampilkan semua kelas">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <button class="btn btn-outline-secondary d-none" type="button" id="btnClearWaliKelasAdd" title="Reset bukan wali kelas">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <div class="dropdown-menu shadow w-100 p-1" id="dropdownWaliKelasAdd" style="max-height: 250px; overflow-y: auto; display: none; position: absolute; z-index: 1055;"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold small mb-0">Pembina Ekstrakurikuler (Bisa Lebih Dari 1)</label>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2.5 py-0" style="font-size: 0.74rem;" onclick="addGuruEkstraRow('add')">
                                <i class="bi bi-plus-circle me-1"></i>Tambah Ekstra
                            </button>
                        </div>
                        <div id="containerGuruEkstraAdd" class="d-flex flex-column gap-2 p-2 border rounded-3 bg-light">
                            <!-- Rows dynamically generated -->
                        </div>
                        <div class="form-text text-muted small mt-1">
                            <i class="bi bi-info-circle me-1"></i>Pilih satu atau lebih cabang jika guru membina beberapa ekstrakurikuler.
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

<!-- Modal Edit Guru -->
<div class="modal fade" id="modalEditGuru" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Data Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-guru.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Nama Guru Lengkap</label>
                            <input type="text" name="nama_guru" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">NIP</label>
                            <input type="text" name="nip" id="edit_nip" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Sebagai Guru Wali</label>
                            <select name="sebagai_guru_wali" id="edit_gw" class="form-select">
                                <option value="Tidak">Tidak</option>
                                <option value="Ya">Ya</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12 position-relative" id="comboboxWaliKelasEdit">
                            <label class="form-label fw-semibold small d-flex justify-content-between align-items-center mb-1">
                                <span>Sebagai Wali Kelas (Pilih / Ketik Kelas)</span>
                                <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.72rem;"><i class="bi bi-keyboard me-1"></i>Bisa Diketik</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" 
                                       name="sebagai_wali_kelas" 
                                       id="edit_wk" 
                                       class="form-control" 
                                       placeholder="-- Ketik atau Pilih Kelas (- jika bukan wali) --" 
                                       autocomplete="off"
                                       list="list_kelas_guru">
                                <button class="btn btn-outline-secondary" type="button" id="btnToggleWaliKelasEdit" title="Tampilkan semua kelas">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <button class="btn btn-outline-secondary d-none" type="button" id="btnClearWaliKelasEdit" title="Reset bukan wali kelas">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <div class="dropdown-menu shadow w-100 p-1" id="dropdownWaliKelasEdit" style="max-height: 250px; overflow-y: auto; display: none; position: absolute; z-index: 1055;"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold small mb-0">Pembina Ekstrakurikuler (Bisa Lebih Dari 1)</label>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2.5 py-0" style="font-size: 0.74rem;" onclick="addGuruEkstraRow('edit')">
                                <i class="bi bi-plus-circle me-1"></i>Tambah Ekstra
                            </button>
                        </div>
                        <div id="containerGuruEkstraEdit" class="d-flex flex-column gap-2 p-2 border rounded-3 bg-light">
                            <!-- Rows dynamically populated via editGuru() -->
                        </div>
                        <div class="form-text text-muted small mt-1">
                            <i class="bi bi-info-circle me-1"></i>Pilih satu atau lebih cabang jika guru membina beberapa ekstrakurikuler.
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

<datalist id="list_kelas_guru">
    <option value="-">- Bukan Wali Kelas -</option>
    <?php foreach ($kelasList as $k): ?>
        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
    <?php endforeach; ?>
</datalist>

<script>
const ekstraOptionsMaster = <?= json_encode($ekstraList) ?>;
const kelasOptionsMaster = <?= json_encode(array_values($kelasList)) ?>;

function initSearchableKelasPicker(config) {
    const input = document.getElementById(config.inputId);
    const toggleBtn = document.getElementById(config.toggleBtnId);
    const clearBtn = document.getElementById(config.clearBtnId);
    const dropdown = document.getElementById(config.dropdownId);
    const container = document.getElementById(config.containerId);
    const items = config.items || [];

    if (!input || !dropdown || !container) return;

    function renderDropdown(filterText = '') {
        const q = filterText.trim().toLowerCase();
        const currentList = items.filter(it => {
            if (!q) return true;
            return it.toLowerCase().includes(q);
        });

        dropdown.innerHTML = '';

        if (config.allowNone) {
            const noneBtn = document.createElement('button');
            noneBtn.type = 'button';
            noneBtn.className = 'dropdown-item py-2 px-3 border-bottom text-muted fst-italic text-start w-100';
            noneBtn.innerHTML = '<i class="bi bi-dash-circle me-2 text-secondary"></i>- Bukan Wali Kelas -';
            noneBtn.addEventListener('click', () => {
                input.value = '-';
                if (clearBtn) clearBtn.classList.remove('d-none');
                closeList();
                if (typeof config.onSelect === 'function') config.onSelect('-');
            });
            dropdown.appendChild(noneBtn);
        }

        if (currentList.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'p-2 text-center text-muted small';
            emptyDiv.innerHTML = '<i class="bi bi-search me-1"></i> Tidak ada kelas yang cocok';
            dropdown.appendChild(emptyDiv);
        } else {
            currentList.forEach(k => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dropdown-item py-1.5 px-3 border-bottom text-start w-100 d-flex justify-content-between align-items-center';
                btn.innerHTML = `<span class="fw-semibold text-dark">${escapeHtml(k)}</span><i class="bi bi-chevron-right text-muted small"></i>`;
                btn.addEventListener('click', () => {
                    input.value = k;
                    if (clearBtn) clearBtn.classList.remove('d-none');
                    closeList();
                    if (typeof config.onSelect === 'function') config.onSelect(k);
                });
                dropdown.appendChild(btn);
            });
        }
        dropdown.style.display = 'block';
    }

    function openList() {
        renderDropdown(input.value === '-' ? '' : input.value);
    }

    function closeList() {
        dropdown.style.display = 'none';
    }

    input.addEventListener('focus', openList);
    input.addEventListener('input', function() {
        renderDropdown(this.value);
        if (clearBtn) {
            if (this.value && this.value !== '-') clearBtn.classList.remove('d-none');
            else clearBtn.classList.add('d-none');
        }
    });

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (dropdown.style.display === 'block') {
                closeList();
            } else {
                input.focus();
                renderDropdown('');
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            input.value = config.allowNone ? '-' : '';
            clearBtn.classList.add('d-none');
            input.focus();
            closeList();
            if (typeof config.onSelect === 'function') config.onSelect(input.value);
        });
    }

    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
            closeList();
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function createGuruEkstraRow(type, selectedVal = '') {
    const div = document.createElement('div');
    div.className = 'guru-ekstra-row d-flex align-items-center gap-2 bg-white p-2 border rounded-3 shadow-xs';
    let opts = '<option value="-">- Bukan Pembina Ekstra -</option>';
    ekstraOptionsMaster.forEach(e => {
        const isSel = (e === selectedVal) ? 'selected' : '';
        opts += `<option value="${escapeHtml(e)}" ${isSel}>${escapeHtml(e)}</option>`;
    });
    div.innerHTML = `
        <span class="text-primary ps-1"><i class="bi bi-award"></i></span>
        <select name="pembina_ekstra[]" class="form-select form-select-sm flex-grow-1">
            ${opts}
        </select>
        <button type="button" class="btn btn-outline-danger btn-sm rounded-circle p-0 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" onclick="removeGuruEkstraRow(this)" title="Hapus Baris">
            <i class="bi bi-trash small"></i>
        </button>
    `;
    return div;
}

function addGuruEkstraRow(type, selectedVal = '') {
    const containerId = type === 'add' ? 'containerGuruEkstraAdd' : 'containerGuruEkstraEdit';
    const container = document.getElementById(containerId);
    if (!container) return;
    container.appendChild(createGuruEkstraRow(type, selectedVal));
}

function removeGuruEkstraRow(btn) {
    const container = btn.closest('.d-flex.flex-column');
    const row = btn.closest('.guru-ekstra-row');
    if (row) row.remove();
    if (container && container.querySelectorAll('.guru-ekstra-row').length === 0) {
        const isAdd = container.id === 'containerGuruEkstraAdd';
        addGuruEkstraRow(isAdd ? 'add' : 'edit', '-');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    addGuruEkstraRow('add', '-');
    
    initSearchableKelasPicker({
        containerId: 'comboboxWaliKelasAdd',
        inputId: 'add_wali_kelas_input',
        toggleBtnId: 'btnToggleWaliKelasAdd',
        clearBtnId: 'btnClearWaliKelasAdd',
        dropdownId: 'dropdownWaliKelasAdd',
        items: kelasOptionsMaster,
        allowNone: true
    });
    initSearchableKelasPicker({
        containerId: 'comboboxWaliKelasEdit',
        inputId: 'edit_wk',
        toggleBtnId: 'btnToggleWaliKelasEdit',
        clearBtnId: 'btnClearWaliKelasEdit',
        dropdownId: 'dropdownWaliKelasEdit',
        items: kelasOptionsMaster,
        allowNone: true
    });

    document.getElementById('modalTambahGuru')?.addEventListener('show.bs.modal', function () {
        const container = document.getElementById('containerGuruEkstraAdd');
        if (container && container.querySelectorAll('.guru-ekstra-row').length === 0) {
            container.innerHTML = '';
            addGuruEkstraRow('add', '-');
        }
    });
});

function editGuru(g) {
    document.getElementById('edit_id').value = g.id;
    document.getElementById('edit_nama').value = g.nama_guru;
    document.getElementById('edit_nip').value = g.nip;
    document.getElementById('edit_gw').value = g.sebagai_guru_wali;
    document.getElementById('edit_wk').value = g.sebagai_wali_kelas || '-';
    
    const clearBtnEdit = document.getElementById('btnClearWaliKelasEdit');
    if (clearBtnEdit) {
        if (g.sebagai_wali_kelas && g.sebagai_wali_kelas !== '-') clearBtnEdit.classList.remove('d-none');
        else clearBtnEdit.classList.add('d-none');
    }
    
    const contEdit = document.getElementById('containerGuruEkstraEdit');
    if (contEdit) {
        contEdit.innerHTML = '';
        let currentEkstras = [];
        if (g.pembina_ekstra && g.pembina_ekstra !== '-') {
            currentEkstras = g.pembina_ekstra.split(',').map(s => s.trim()).filter(Boolean);
        }
        if (currentEkstras.length > 0) {
            currentEkstras.forEach(ek => addGuruEkstraRow('edit', ek));
        } else {
            addGuruEkstraRow('edit', '-');
        }
    }
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
