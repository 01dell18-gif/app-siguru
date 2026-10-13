<?php
// master-ekstra.php - Master Data Siswa Ekstrakurikuler
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

if (!hasAccess('master_ekstra')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke menu Siswa Ekstrakurikuler.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$ekstraList = getDaftarEkstra();
$kelasList = getDaftarKelas();

// 1. Export Excel / Template / CSV (Dijalankan SEBELUM output header HTML dikirim)
$isTemplate = isset($_GET['template']) || (isset($_GET['export']) && strtolower($_GET['export']) === 'template');
if ($isTemplate || (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv']))) {
    $rows = $pdo->query("SELECT * FROM data_murid_ekstra ORDER BY nama_ekstra ASC, nama_murid ASC")->fetchAll();
    $data = [];
    $no = 1;

    // Jika unduh template, atau data di tabel masih kosong, sertakan data contoh (dummy) sebagai pedoman pengisian
    if ($isTemplate || empty($rows)) {
        $data = [
            [1, '0081234567', 'Aditya Pratama Putra', 'L', 'X TKJ 1', 'Pramuka, Futsal'],
            [2, '0087654321', 'Annisa Nurul Hidayah', 'P', 'X TKJ 1', 'PMR'],
            [3, '0091122334', 'Bagas Dimas Saputra', 'L', 'XI RPL 2', 'Basket'],
            [4, '0095566778', 'Cantika Putri Lestari', 'P', 'XI RPL 2', 'Paskibra, Tari'],
            [5, '0079988776', 'Dimas Arya Nugraha', 'L', 'XII MM 1', 'Robotik']
        ];
        $filenamePrefix = 'Template_Import_Data_Ekstra_';
    } else {
        foreach ($rows as $r) {
            $data[] = [
                $no++,
                formatNISN($r['nisn']),
                $r['nama_murid'],
                $r['jenis_kelamin'],
                $r['kelas'],
                $r['nama_ekstra']
            ];
        }
        $filenamePrefix = 'Data_Murid_Ekstra_';
    }

    $format = strtolower($_GET['export'] ?? ($_GET['template'] ?? 'xlsx'));
    if ($format === 'csv') {
        exportToExcelCSV($filenamePrefix . date('Ymd_His') . '.csv', ['No', 'NISN', 'Nama Murid', 'Jenis Kelamin', 'Kelas', 'Nama Ekstrakurikuler'], $data);
    } else {
        exportToExcelXLS($filenamePrefix . date('Ymd_His') . '.xlsx', ['No', 'NISN', 'Nama Murid', 'Jenis Kelamin', 'Kelas', 'Nama Ekstrakurikuler'], $data);
    }
}

// Ambil daftar murid yang berstatus "Ekstra" dari DataMurid untuk autofill saat tambah
$eligibleStudents = $pdo->query("SELECT * FROM data_murid WHERE status LIKE '%Ekstra%' ORDER BY nama_murid ASC")->fetchAll();

// 2. Aksi CRUD POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nisn = formatNISN($_POST['nisn'] ?? '');
        $nama = trim($_POST['nama_murid'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? 'L';
        $kelas = trim($_POST['kelas'] ?? '');
        
        $postEkstra = '';
        if (isset($_POST['nama_ekstra']) && is_array($_POST['nama_ekstra'])) {
            $postEkstra = implode(', ', array_filter(array_map('trim', $_POST['nama_ekstra'])));
        } else {
            $postEkstra = trim($_POST['nama_ekstra'] ?? '');
        }

        if (!empty($nama) && !empty($postEkstra)) {
            $stmt = $pdo->prepare("INSERT INTO data_murid_ekstra (nisn, nama_murid, jenis_kelamin, kelas, nama_ekstra) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nisn, $nama, $jk, $kelas, $postEkstra]);
            setFlash('success', 'Siswa berhasil didaftarkan ke kegiatan ekstrakurikuler.');
        }
        header("Location: master-ekstra.php");
        exit;
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $nisn = formatNISN($_POST['nisn'] ?? '');
        $nama = trim($_POST['nama_murid'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? 'L';
        $kelas = trim($_POST['kelas'] ?? '');
        
        $postEkstra = '';
        if (isset($_POST['nama_ekstra']) && is_array($_POST['nama_ekstra'])) {
            $postEkstra = implode(', ', array_filter(array_map('trim', $_POST['nama_ekstra'])));
        } else {
            $postEkstra = trim($_POST['nama_ekstra'] ?? '');
        }

        $stmt = $pdo->prepare("UPDATE data_murid_ekstra SET nisn = ?, nama_murid = ?, jenis_kelamin = ?, kelas = ?, nama_ekstra = ? WHERE id = ?");
        $stmt->execute([$nisn, $nama, $jk, $kelas, $postEkstra, $id]);
        setFlash('success', 'Data siswa ekstrakurikuler diperbarui.');
        header("Location: master-ekstra.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM data_murid_ekstra WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Data berhasil dihapus.');
        header("Location: master-ekstra.php");
        exit;
    }

    // Import Excel (.xlsx / .xls) / CSV
    if ($action === 'import') {
        if (isset($_FILES['file_import']) && $_FILES['file_import']['error'] === UPLOAD_ERR_OK) {
            $rows = parseImportRows($_FILES['file_import']['tmp_name']);
            if (!empty($rows)) {
                $pdo->exec("DELETE FROM data_murid_ekstra");
                $stmt = $pdo->prepare("INSERT INTO data_murid_ekstra (nisn, nama_murid, jenis_kelamin, kelas, nama_ekstra) VALUES (?, ?, ?, ?, ?)");
                
                $firstRow = $rows[0];
                $colNisn = 1;
                $colNama = 2;
                $colJk   = 3;
                $colKls  = 4;
                $colEks  = 5;

                $hasHeader = false;
                foreach ($firstRow as $idx => $cell) {
                    $cLower = strtolower(trim((string)$cell));
                    if (strpos($cLower, 'nisn') !== false) { $colNisn = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'nama') !== false) { $colNama = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kelamin') !== false || $cLower === 'jk' || $cLower === 'l/p') { $colJk = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kelas') !== false || strpos($cLower, 'rombel') !== false) { $colKls = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'ekstra') !== false) { $colEks = $idx; $hasHeader = true; }
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

                        $jk = strtoupper(trim($data[$colJk] ?? 'L'));
                        $jk = (strpos($jk, 'P') === 0 || strpos($jk, 'PEREMPUAN') === 0) ? 'P' : 'L';

                        $kls = trim($data[$colKls] ?? '');
                        if (in_array(strtolower($kls), ['«', '»', '<', '>', '&lt;', '&gt;'])) $kls = '';

                        $eks = trim($data[$colEks] ?? '');
                        if (in_array(strtolower($eks), ['«', '»', '<', '>', '&lt;', '&gt;'])) $eks = '';

                        $stmt->execute([$nisn, $nama, $jk, $kls, $eks]);
                        $inserted++;
                    }
                }
                setFlash('success', 'Import berhasil: ' . $inserted . ' data siswa ekstrakurikuler disimpan ke database.');
            } else {
                setFlash('danger', 'Format berkas tidak valid atau data kosong.');
            }
        }
        header("Location: master-ekstra.php");
        exit;
    }
}

$pageTitle = 'Data Siswa Ekstrakurikuler';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

// 3. Search & Pagination
$search = trim($_GET['q'] ?? '');
$filterEkstra = trim($_GET['ekstra'] ?? '');
$rawLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($rawLimit, [10, 20, 50, 100, 250, 500]) ? $rawLimit : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];
if (!empty($search)) {
    $whereClauses[] = "(nama_murid LIKE ? OR nisn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($filterEkstra)) {
    $whereClauses[] = "(nama_ekstra = ? OR nama_ekstra LIKE ? OR nama_ekstra LIKE ? OR nama_ekstra LIKE ?)";
    $params[] = $filterEkstra;
    $params[] = "$filterEkstra, %";
    $params[] = "%, $filterEkstra, %";
    $params[] = "%, $filterEkstra";
}

$sqlWhere = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM data_murid_ekstra $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM data_murid_ekstra $sqlWhere ORDER BY nama_ekstra ASC, nama_murid ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$siswaEkstraList = $stmt->fetchAll();
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Data Siswa Ekstrakurikuler</h4>
            <p class="text-muted small mb-0">Kelola anggota tiap cabang ekstrakurikuler sekolah.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <button class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddEkstra">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Anggota Ekstra
            </button>
            <a href="master-ekstra.php?export=xlsx" class="btn btn-outline-success rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
            </a>
            <a href="master-ekstra.php?template=xlsx" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" title="Unduh template Excel dengan pedoman data contoh (dummy)">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template (.xlsx)
            </a>
            <button class="btn btn-outline-primary rounded-pill px-3 btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportEkstra">
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
                    <input type="text" name="q" class="form-control" placeholder="Cari nama siswa atau NISN..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="ekstra" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Ekstrakurikuler</option>
                    <?php foreach ($ekstraList as $e): ?>
                        <option value="<?= htmlspecialchars($e) ?>" <?= $filterEkstra === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                    <?php endforeach; ?>
                </select>
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

    <!-- Tabel Data Siswa Ekstrakurikuler -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>NISN</th>
                        <th>Nama Siswa</th>
                        <th>L/P</th>
                        <th>Kelas</th>
                        <th>Nama Ekstrakurikuler</th>
                        <th class="text-center" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswaEkstraList)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Data anggota ekstrakurikuler belum ada.</td></tr>
                    <?php else: 
                        $no = $offset + 1;
                        foreach ($siswaEkstraList as $se): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($se['nisn']) ?></span></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($se['nama_murid']) ?></td>
                            <td>
                                <span class="badge <?= $se['jenis_kelamin'] === 'L' ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' ?>">
                                    <?= $se['jenis_kelamin'] ?>
                                </span>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($se['kelas']) ?></span></td>
                            <td>
                                <?php 
                                $items = array_map('trim', explode(',', $se['nama_ekstra'] ?? ''));
                                foreach ($items as $it):
                                    if (empty($it)) continue;
                                ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1 me-1 mb-1">
                                        <i class="bi bi-award me-1"></i><?= htmlspecialchars($it) ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" 
                                    onclick='editEkstra(<?= json_encode($se) ?>)' title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 ms-1" 
                                    onclick="deleteEkstra(<?= $se['id'] ?>, '<?= addslashes($se['nama_murid']) ?>')" title="Hapus">
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
                    <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&ekstra=<?= urlencode($filterEkstra) ?>&q=<?= urlencode($search) ?>">Sebelumnya</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&ekstra=<?= urlencode($filterEkstra) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&ekstra=<?= urlencode($filterEkstra) ?>&q=<?= urlencode($search) ?>">Selanjutnya</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Tambah Anggota Ekstra -->
<div class="modal fade" id="modalAddEkstra" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Anggota Ekstra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-ekstra.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <!-- Dropdown Cari Berdasarkan Status Murid dari DataMurid Sesuai PRD -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-primary">Pilih Siswa dari DataMurid (Status: Ekstra)</label>
                        <select class="form-select" id="selectMuridEkstra" onchange="autoFillEkstraStudent(this)">
                            <option value="">-- Pilih Siswa atau Isi Manual di Bawah --</option>
                            <?php foreach ($eligibleStudents as $es): ?>
                                <option value="<?= htmlspecialchars(json_encode($es)) ?>">
                                    <?= htmlspecialchars($es['nama_murid']) ?> (NISN: <?= htmlspecialchars($es['nisn']) ?> - <?= htmlspecialchars($es['kelas']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">NISN</label>
                        <input type="text" name="nisn" id="add_e_nisn" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Siswa</label>
                        <input type="text" name="nama_murid" id="add_e_nama" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Jenis Kelamin</label>
                            <select name="jenis_kelamin" id="add_e_jk" class="form-select">
                                <option value="L">Laki-Laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Kelas</label>
                            <input type="text" name="kelas" id="add_e_kelas" list="list_kelas" class="form-control" placeholder="Pilih / ketik kelas..." required>
                            <datalist id="list_kelas">
                                <?php foreach ($kelasList as $k): ?>
                                    <option value="<?= htmlspecialchars($k) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small d-block">Cabang Ekstrakurikuler (Bisa Pilih Lebih Dari 1)</label>
                        <div class="d-flex flex-wrap gap-2 p-2 bg-light rounded-3 border">
                            <?php foreach ($ekstraList as $e): ?>
                                <div class="form-check me-2">
                                    <input class="form-check-input add-ekstra-chk" type="checkbox" name="nama_ekstra[]" value="<?= htmlspecialchars($e) ?>" id="add_ek_<?= md5($e) ?>">
                                    <label class="form-check-label small fw-semibold" for="add_ek_<?= md5($e) ?>"><?= htmlspecialchars($e) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Centang satu atau beberapa kegiatan ekstra yang diikuti.</small>
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

<!-- Modal Edit Ekstra -->
<div class="modal fade" id="modalEditEkstra" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Data Siswa Ekstra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-ekstra.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_e_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">NISN</label>
                        <input type="text" name="nisn" id="edit_e_nisn" class="form-control" maxlength="10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Siswa</label>
                        <input type="text" name="nama_murid" id="edit_e_nama" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Jenis Kelamin</label>
                            <select name="jenis_kelamin" id="edit_e_jk" class="form-select">
                                <option value="L">Laki-Laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Kelas</label>
                            <input type="text" name="kelas" id="edit_e_kelas" list="list_kelas" class="form-control" placeholder="Pilih / ketik kelas..." required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small d-block">Cabang Ekstrakurikuler (Bisa Pilih Lebih Dari 1)</label>
                        <div class="d-flex flex-wrap gap-2 p-2 bg-light rounded-3 border">
                            <?php foreach ($ekstraList as $e): ?>
                                <div class="form-check me-2">
                                    <input class="form-check-input edit-ekstra-chk" type="checkbox" name="nama_ekstra[]" value="<?= htmlspecialchars($e) ?>" id="edit_ek_<?= md5($e) ?>">
                                    <label class="form-check-label small fw-semibold" for="edit_ek_<?= md5($e) ?>"><?= htmlspecialchars($e) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Centang satu atau beberapa kegiatan ekstra yang diikuti.</small>
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

<!-- Modal Import Ekstra -->
<div class="modal fade" id="modalImportEkstra" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Import Data Ekstra (Excel .xls / CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-ekstra.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Data lama akan dihapus dan digantikan data baru yang diimport.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pilih File Excel (.xlsx / .xls) atau CSV</label>
                        <input type="file" name="file_import" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <small class="text-muted d-block mb-2">Format kolom: No, NISN, Nama Murid, L/P, Kelas, Nama Ekstrakurikuler (bisa dipisah koma jika lebih dari 1)</small>
                    <a href="master-ekstra.php?template=xlsx" class="btn btn-outline-success btn-sm rounded-pill px-3">
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

<form id="formDeleteEkstra" method="POST" action="master-ekstra.php" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_e_id">
</form>

<script>
function autoFillEkstraStudent(selectEl) {
    if (!selectEl.value) return;
    const data = JSON.parse(selectEl.value);
    document.getElementById('add_e_nisn').value = data.nisn || '';
    document.getElementById('add_e_nama').value = data.nama_murid || '';
    document.getElementById('add_e_kelas').value = data.kelas || '';
    document.getElementById('add_e_jk').value = data.jenis_kelamin || 'L';
}

function editEkstra(se) {
    document.getElementById('edit_e_id').value = se.id;
    document.getElementById('edit_e_nisn').value = se.nisn;
    document.getElementById('edit_e_nama').value = se.nama_murid;
    document.getElementById('edit_e_jk').value = se.jenis_kelamin;
    document.getElementById('edit_e_kelas').value = se.kelas;

    // Reset dan centang checkbox yang sesuai
    const currentEkstras = (se.nama_ekstra || '').split(',').map(s => s.trim());
    document.querySelectorAll('.edit-ekstra-chk').forEach(chk => {
        chk.checked = currentEkstras.includes(chk.value);
    });

    new bootstrap.Modal(document.getElementById('modalEditEkstra')).show();
}

function deleteEkstra(id, name) {
    Swal.fire({
        title: 'Hapus Anggota Ekstra?',
        text: 'Apakah Anda yakin ingin menghapus ' + name + ' dari daftar ekstra?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_e_id').value = id;
            document.getElementById('formDeleteEkstra').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
