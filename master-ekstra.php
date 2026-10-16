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
try {
    $pdo->exec("ALTER TABLE data_murid_ekstra ADD COLUMN guru_pembina TEXT DEFAULT ''");
} catch (Exception $e) {}
$isSuper = isSuperAdmin();
$ekstraList = getDaftarEkstra();
$kelasList = getDaftarKelas();
$ekstraPembinaMap = getEkstraPembinaMap();

// 1. Export Excel / Template / CSV (Dijalankan SEBELUM output header HTML dikirim)
$isTemplate = isset($_GET['template']) || (isset($_GET['export']) && strtolower($_GET['export']) === 'template');
if ($isTemplate || (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv']))) {
    $rows = $pdo->query("SELECT * FROM data_murid_ekstra ORDER BY nama_ekstra ASC, nama_murid ASC")->fetchAll();
    $data = [];
    $no = 1;

    // Jika unduh template, atau data di tabel masih kosong, sertakan data contoh (dummy) sebagai pedoman pengisian
    if ($isTemplate || empty($rows)) {
        $data = [
            [1, '0081234567', 'Aditya Pratama Putra', 'L', 'X TKJ 1', 'Pramuka, Futsal', 'Pramuka: Belum Ditentukan; Futsal: Susanto'],
            [2, '0087654321', 'Annisa Nurul Hidayah', 'P', 'X TKJ 1', 'PMR', 'Belum Ditentukan'],
            [3, '0091122334', 'Bagas Dimas Saputra', 'L', 'XI RPL 2', 'Basket', 'Belum Ditentukan'],
            [4, '0095566778', 'Cantika Putri Lestari', 'P', 'XI RPL 2', 'Paskibra, Tari', 'Paskibra: Belum Ditentukan; Tari: Belum Ditentukan'],
            [5, '0079988776', 'Dimas Arya Nugraha', 'L', 'XII MM 1', 'Robotik', 'Belum Ditentukan']
        ];
        $filenamePrefix = 'Template_Import_Data_Ekstra_';
    } else {
        foreach ($rows as $r) {
            $items = array_values(array_filter(array_map('trim', explode(',', $r['nama_ekstra'] ?? ''))));
            $assignedMap = parseMuridEkstraPembinaMap($r['guru_pembina'] ?? '', $items, $ekstraPembinaMap);
            $pembinaParts = [];
            foreach ($items as $it) {
                $pembinaStr = $assignedMap[$it] ?? 'Belum Ditentukan';
                if (count($items) > 1) {
                    $pembinaParts[] = "$it: $pembinaStr";
                } else {
                    $pembinaParts[] = $pembinaStr;
                }
            }
            $pembinaText = !empty($pembinaParts) ? implode('; ', $pembinaParts) : '-';

            $data[] = [
                $no++,
                formatNISN($r['nisn']),
                $r['nama_murid'],
                $r['jenis_kelamin'],
                $r['kelas'],
                $r['nama_ekstra'],
                $pembinaText
            ];
        }
        $filenamePrefix = 'Data_Murid_Ekstra_';
    }

    $format = strtolower($_GET['export'] ?? ($_GET['template'] ?? 'xlsx'));
    $exportHeaders = ['No', 'NISN', 'Nama Murid', 'Jenis Kelamin', 'Kelas', 'Nama Ekstrakurikuler', 'Guru Pembina'];
    if ($format === 'csv') {
        exportToExcelCSV($filenamePrefix . date('Ymd_His') . '.csv', $exportHeaders, $data);
    } else {
        exportToExcelXLS($filenamePrefix . date('Ymd_His') . '.xlsx', $exportHeaders, $data);
    }
}

// Ambil daftar murid yang berstatus "Ekstra" dari DataMurid yang BELUM terdaftar di data_murid_ekstra
$eligibleStudents = $pdo->query("SELECT * FROM data_murid 
    WHERE status LIKE '%Ekstra%'
    AND TRIM(nama_murid) NOT IN (SELECT TRIM(nama_murid) FROM data_murid_ekstra WHERE nama_murid IS NOT NULL AND nama_murid != '')
    AND (nisn IS NULL OR nisn = '' OR nisn NOT IN (SELECT nisn FROM data_murid_ekstra WHERE nisn IS NOT NULL AND nisn != ''))
    ORDER BY nama_murid ASC")->fetchAll();

// 2. Aksi CRUD POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isSuper) {
        setFlash('danger', 'Hanya Super Admin yang berhak memodifikasi data siswa ekstrakurikuler.');
        header("Location: master-ekstra.php");
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nisn = formatNISN($_POST['nisn'] ?? '');
        $nama = trim($_POST['nama_murid'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? 'L';
        $kelas = trim($_POST['kelas'] ?? '');
        
        $postEkstra = '';
        $pembinaArr = [];
        if (isset($_POST['nama_ekstra']) && is_array($_POST['nama_ekstra'])) {
            $submittedEkstras = $_POST['nama_ekstra'];
            $submittedPembinas = $_POST['guru_pembina'] ?? [];
            $cleanEkstras = [];
            foreach ($submittedEkstras as $idx => $ek) {
                $ek = trim((string)$ek);
                if ($ek === '' || in_array($ek, $cleanEkstras)) continue;
                $cleanEkstras[] = $ek;
                $pem = isset($submittedPembinas[$idx]) ? trim((string)$submittedPembinas[$idx]) : '';
                if ($pem === '' || $pem === 'Pilih cabang ekstra dulu') {
                    $avail = $ekstraPembinaMap[$ek] ?? [];
                    $pem = !empty($avail) ? implode(', ', $avail) : 'Belum Ditentukan';
                }
                $pembinaArr[$ek] = $pem;
            }
            $postEkstra = implode(', ', $cleanEkstras);
        } else {
            $postEkstra = trim($_POST['nama_ekstra'] ?? '');
            if (!empty($postEkstra)) {
                $pembinaArr = parseMuridEkstraPembinaMap($_POST['guru_pembina'] ?? '', [$postEkstra], $ekstraPembinaMap);
            }
        }
        $postGuruPembina = serializeMuridEkstraPembinaMap($pembinaArr);

        if (!empty($nama) && !empty($postEkstra)) {
            // Validasi duplikasi: Cek apakah nama atau NISN siswa sudah terdaftar dalam data_murid_ekstra
            $chk = $pdo->prepare("SELECT COUNT(*) FROM data_murid_ekstra WHERE TRIM(nama_murid) = ? OR (nisn != '' AND nisn = ?)");
            $chk->execute([$nama, $nisn]);
            if ($chk->fetchColumn() > 0) {
                setFlash('danger', 'Siswa "' . htmlspecialchars($nama) . '" sudah terdaftar dalam data siswa ekstrakurikuler! Jika ingin menambahkan cabang ekstra lain, silakan gunakan fitur Edit pada siswa tersebut.');
                header("Location: master-ekstra.php");
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO data_murid_ekstra (nisn, nama_murid, jenis_kelamin, kelas, nama_ekstra, guru_pembina) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nisn, $nama, $jk, $kelas, $postEkstra, $postGuruPembina]);
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
        $pembinaArr = [];
        if (isset($_POST['nama_ekstra']) && is_array($_POST['nama_ekstra'])) {
            $submittedEkstras = $_POST['nama_ekstra'];
            $submittedPembinas = $_POST['guru_pembina'] ?? [];
            $cleanEkstras = [];
            foreach ($submittedEkstras as $idx => $ek) {
                $ek = trim((string)$ek);
                if ($ek === '' || in_array($ek, $cleanEkstras)) continue;
                $cleanEkstras[] = $ek;
                $pem = isset($submittedPembinas[$idx]) ? trim((string)$submittedPembinas[$idx]) : '';
                if ($pem === '' || $pem === 'Pilih cabang ekstra dulu') {
                    $avail = $ekstraPembinaMap[$ek] ?? [];
                    $pem = !empty($avail) ? implode(', ', $avail) : 'Belum Ditentukan';
                }
                $pembinaArr[$ek] = $pem;
            }
            $postEkstra = implode(', ', $cleanEkstras);
        } else {
            $postEkstra = trim($_POST['nama_ekstra'] ?? '');
            if (!empty($postEkstra)) {
                $pembinaArr = parseMuridEkstraPembinaMap($_POST['guru_pembina'] ?? '', [$postEkstra], $ekstraPembinaMap);
            }
        }
        $postGuruPembina = serializeMuridEkstraPembinaMap($pembinaArr);

        $stmt = $pdo->prepare("UPDATE data_murid_ekstra SET nisn = ?, nama_murid = ?, jenis_kelamin = ?, kelas = ?, nama_ekstra = ?, guru_pembina = ? WHERE id = ?");
        $stmt->execute([$nisn, $nama, $jk, $kelas, $postEkstra, $postGuruPembina, $id]);
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
                $stmt = $pdo->prepare("INSERT INTO data_murid_ekstra (nisn, nama_murid, jenis_kelamin, kelas, nama_ekstra, guru_pembina) VALUES (?, ?, ?, ?, ?, ?)");
                
                $firstRow = $rows[0];
                $colNisn = 1;
                $colNama = 2;
                $colJk   = 3;
                $colKls  = 4;
                $colEks  = 5;
                $colPem  = null;

                $hasHeader = false;
                foreach ($firstRow as $idx => $cell) {
                    $cLower = strtolower(trim((string)$cell));
                    if (strpos($cLower, 'nisn') !== false) { $colNisn = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'nama') !== false) { $colNama = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kelamin') !== false || $cLower === 'jk' || $cLower === 'l/p') { $colJk = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kelas') !== false || strpos($cLower, 'rombel') !== false) { $colKls = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'ekstra') !== false) { $colEks = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'pembina') !== false) { $colPem = $idx; $hasHeader = true; }
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

                        $pem = ($colPem !== null && isset($data[$colPem])) ? trim($data[$colPem]) : '';
                        if (in_array(strtolower($pem), ['«', '»', '<', '>', '&lt;', '&gt;', '-'])) $pem = '';

                        $stmt->execute([$nisn, $nama, $jk, $kls, $eks, $pem]);
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
            <?php if ($isSuper): ?>
            <button class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddEkstra">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Anggota Ekstra
            </button>
            <?php endif; ?>
            <a href="master-ekstra.php?export=xlsx" class="btn btn-outline-success rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
            </a>
            <a href="master-ekstra.php?template=xlsx" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" title="Unduh template Excel dengan pedoman data contoh (dummy)">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template (.xlsx)
            </a>
            <?php if ($isSuper): ?>
            <button class="btn btn-outline-primary rounded-pill px-3 btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportEkstra">
                <i class="bi bi-upload me-1"></i> Import Excel
            </button>
            <?php endif; ?>
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
                        <th>Cabang Ekstrakurikuler</th>
                        <th>Guru Pembina</th>
                        <th class="text-center" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswaEkstraList)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Data anggota ekstrakurikuler belum ada.</td></tr>
                    <?php else: 
                        $no = $offset + 1;
                        foreach ($siswaEkstraList as $se): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($se['nisn']) ?></span></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($se['nama_murid']) ?></td>
                            <td>
                                <span class="badge <?= $se['jenis_kelamin'] === 'P' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>">
                                    <?= htmlspecialchars($se['jenis_kelamin']) ?>
                                </span>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($se['kelas']) ?></span></td>
                            <td>
                                <?php 
                                $items = array_values(array_filter(array_map('trim', explode(',', $se['nama_ekstra'] ?? ''))));
                                foreach ($items as $it):
                                ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1 me-1 mb-1">
                                        <i class="bi bi-award me-1"></i><?= htmlspecialchars($it) ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php 
                                $assignedMap = parseMuridEkstraPembinaMap($se['guru_pembina'] ?? '', $items, $ekstraPembinaMap);
                                if (empty($items)): ?>
                                    <span class="text-muted small fst-italic">-</span>
                                <?php elseif (count($items) === 1): 
                                    $it = $items[0];
                                    $pembinaName = $assignedMap[$it] ?? 'Belum Ditentukan';
                                    $isSet = (!empty($pembinaName) && $pembinaName !== 'Belum Ditentukan');
                                ?>
                                    <div class="<?= $isSet ? 'fw-semibold text-primary' : 'text-muted fst-italic' ?> small">
                                        <i class="bi <?= $isSet ? 'bi-person-badge text-primary' : 'bi-dash-circle text-muted' ?> me-1"></i>
                                        <?= htmlspecialchars($pembinaName) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex flex-column gap-1">
                                        <?php foreach ($items as $it): 
                                            $pembinaName = $assignedMap[$it] ?? 'Belum Ditentukan';
                                            $isSet = (!empty($pembinaName) && $pembinaName !== 'Belum Ditentukan');
                                        ?>
                                            <div class="small d-flex align-items-center">
                                                <span class="badge bg-light text-secondary border me-1 fw-semibold" style="font-size: 0.72rem; min-width: 65px;">
                                                    <?= htmlspecialchars($it) ?>
                                                </span>
                                                <span class="<?= $isSet ? 'fw-semibold text-primary' : 'text-muted fst-italic' ?>" style="font-size: 0.82rem;">
                                                    <i class="bi <?= $isSet ? 'bi-person-badge text-primary' : 'bi-dash-circle text-muted' ?> me-1"></i><?= htmlspecialchars($pembinaName) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isSuper): ?>
                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" 
                                        onclick='editEkstra(<?= json_encode($se) ?>)' title="Edit Anggota Ekstra">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 ms-1" 
                                        onclick="deleteEkstra(<?= $se['id'] ?>, '<?= addslashes($se['nama_murid']) ?>')" title="Hapus Anggota Ekstra">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border py-1 px-2" title="Menu aksi terkunci (Hanya Super Admin)">
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

<?php if ($isSuper): ?>
<!-- Modal Tambah Anggota Ekstra -->
<div class="modal fade" id="modalAddEkstra" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Anggota Ekstra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-ekstra.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <!-- Dropdown Cari Berdasarkan Status Murid dari DataMurid (Dapat Diketik Sesuai Permintaan) -->
                    <div class="mb-3 position-relative" id="comboboxMuridEkstra">
                        <label class="form-label fw-semibold small text-primary d-flex justify-content-between align-items-center mb-1">
                            <span><i class="bi bi-person-lines-fill me-1"></i> Pilih Siswa dari DataMurid (Status: Ekstra)</span>
                            <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.72rem;"><i class="bi bi-keyboard me-1"></i>Bisa Diketik</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   id="searchMuridEkstraInput" 
                                   placeholder="-- Ketik Nama Siswa, NISN, atau Kelas --" 
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btnToggleMuridEkstra" title="Tampilkan semua siswa">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <button class="btn btn-outline-danger d-none" type="button" id="btnClearMuridEkstra" title="Reset pencarian">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div id="dropdownMuridEkstraList" class="dropdown-menu w-100 shadow-lg border mt-1 p-0 overflow-auto" style="max-height: 250px; z-index: 1060; display: none;">
                            <!-- Opsi siswa diisi dinamis oleh JS -->
                        </div>
                        <div class="form-text small text-muted">
                            <i class="bi bi-info-circle me-1"></i> Ketik nama atau NISN untuk mencari cepat. Klik pilihan untuk mengisi otomatis data siswa di bawah.
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">NISN</label>
                            <input type="text" name="nisn" id="add_e_nisn" class="form-control" required placeholder="0071234567">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Nama Siswa</label>
                            <input type="text" name="nama_murid" id="add_e_nama" class="form-control" required placeholder="Nama Siswa">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Jenis Kelamin</label>
                            <select name="jenis_kelamin" id="add_e_jk" class="form-select">
                                <option value="L">Laki-Laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
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
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold small text-primary mb-0">
                                <i class="bi bi-award-fill me-1"></i> Cabang Ekstrakurikuler & Pembina
                            </label>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2.5" onclick="addEkstraRow('add')" style="font-size: 0.78rem;">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Cabang
                            </button>
                        </div>
                        
                        <!-- Dynamic Container for Ekstra Rows -->
                        <div id="containerEkstraRowsAdd" class="d-flex flex-column gap-2 p-2 bg-light rounded-3 border">
                            <!-- Baris cabang ekstra dimasukkan secara dinamis via JavaScript -->
                        </div>
                        <div class="form-text small text-muted mt-1">
                            <i class="bi bi-info-circle me-1"></i> Pilih cabang ekstra, sistem otomatis menampilkan guru pembinanya. Jika ada lebih dari 1 guru pembina, pilih salah satu guru pembinanya. Klik "+ Tambah Cabang" jika siswa mengikuti lebih dari 1 kegiatan.
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

<!-- Modal Edit Ekstra -->
<div class="modal fade" id="modalEditEkstra" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Data Siswa Ekstra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-ekstra.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_e_id">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">NISN</label>
                            <input type="text" name="nisn" id="edit_e_nisn" class="form-control" maxlength="10" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Nama Siswa</label>
                            <input type="text" name="nama_murid" id="edit_e_nama" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Jenis Kelamin</label>
                            <select name="jenis_kelamin" id="edit_e_jk" class="form-select">
                                <option value="L">Laki-Laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Kelas</label>
                            <input type="text" name="kelas" id="edit_e_kelas" list="list_kelas" class="form-control" placeholder="Pilih / ketik kelas..." required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold small text-primary mb-0">
                                <i class="bi bi-award-fill me-1"></i> Cabang Ekstrakurikuler & Pembina
                            </label>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2.5" onclick="addEkstraRow('edit')" style="font-size: 0.78rem;">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Cabang
                            </button>
                        </div>
                        
                        <!-- Dynamic Container for Ekstra Rows -->
                        <div id="containerEkstraRowsEdit" class="d-flex flex-column gap-2 p-2 bg-light rounded-3 border">
                            <!-- Baris cabang ekstra dimasukkan secara dinamis via JavaScript -->
                        </div>
                        <div class="form-text small text-muted mt-1">
                            <i class="bi bi-info-circle me-1"></i> Pilih cabang ekstra, sistem otomatis menampilkan guru pembinanya. Jika ada lebih dari 1 guru pembina, pilih salah satu guru pembinanya. Klik "+ Tambah Cabang" jika siswa mengikuti lebih dari 1 kegiatan.
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
<?php endif; ?>

<datalist id="list_ekstra_options">
    <?php foreach ($ekstraList as $e): ?>
        <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
    <?php endforeach; ?>
</datalist>

<script>
const eligibleStudentsEkstra = <?= json_encode($eligibleStudents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function autoFillEkstraStudent(data) {
    if (!data) return;
    if (data.value !== undefined) {
        if (!data.value) return;
        data = JSON.parse(data.value);
    }
    document.getElementById('add_e_nisn').value = data.nisn || '';
    document.getElementById('add_e_nama').value = data.nama_murid || '';
    document.getElementById('add_e_kelas').value = data.kelas || '';
    document.getElementById('add_e_jk').value = data.jenis_kelamin || 'L';
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
                emptyDiv.innerHTML = '<i class="bi bi-info-circle me-1 text-primary"></i> Semua siswa DataMurid berstatus Ekstra sudah terdaftar sebagai anggota ekstrakurikuler.';
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

const ekstraOptionsData = <?= json_encode(array_map(function($e) use ($ekstraPembinaMap) {
    $pembinas = $ekstraPembinaMap[$e] ?? [];
    $pembinaLbl = !empty($pembinas) ? implode(', ', $pembinas) : 'Belum Ditentukan';
    return [
        'name' => $e, 
        'pembina' => $pembinaLbl,
        'pembinas' => array_values($pembinas)
    ];
}, $ekstraList), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function getPembinaInfo(ekstraName) {
    const item = ekstraOptionsData.find(e => e.name === ekstraName);
    if (!item) return { pembinas: [], pembina: 'Belum Ditentukan' };
    return item;
}

function renderPembinaInputHtml(ekstraName, selectedPembina = '') {
    if (!ekstraName) {
        return `
            <div class="pembina-info-box small bg-light border rounded px-2 py-1 text-truncate d-flex align-items-center" style="min-height: 31px;">
                <i class="bi bi-dash-circle text-muted me-1"></i>
                <span class="pembina-text text-muted fst-italic" style="font-size: 0.78rem;">Pilih cabang ekstra dulu</span>
                <input type="hidden" name="guru_pembina[]" value="">
            </div>
        `;
    }

    const info = getPembinaInfo(ekstraName);
    const pembinas = info.pembinas || [];

    if (pembinas.length === 0) {
        return `
            <div class="pembina-info-box small bg-light border rounded px-2 py-1 text-truncate d-flex align-items-center" style="min-height: 31px;">
                <i class="bi bi-dash-circle text-muted me-1"></i>
                <span class="pembina-text text-muted fst-italic" style="font-size: 0.78rem;">Belum Ditentukan</span>
                <input type="hidden" name="guru_pembina[]" value="Belum Ditentukan">
            </div>
        `;
    } else if (pembinas.length === 1) {
        const pName = pembinas[0];
        return `
            <div class="pembina-info-box small bg-light border rounded px-2 py-1 text-truncate d-flex align-items-center" style="min-height: 31px;">
                <i class="bi bi-person-badge text-primary me-1"></i>
                <span class="pembina-text fw-bold text-primary text-truncate" style="font-size: 0.78rem;">${escapeHtml(pName)}</span>
                <input type="hidden" name="guru_pembina[]" value="${escapeHtml(pName)}">
            </div>
        `;
    } else {
        // Lebih dari 1 Guru Pembina: buat select dropdown agar bisa dipilih salah satu!
        let opts = `<option value="">-- Pilih Guru Pembina --</option>`;
        pembinas.forEach(p => {
            const isSel = (selectedPembina && p.trim() === selectedPembina.trim());
            opts += `<option value="${escapeHtml(p)}" ${isSel ? 'selected' : ''}>${escapeHtml(p)}</option>`;
        });

        return `
            <div class="position-relative">
                <select name="guru_pembina[]" class="form-select form-select-sm select-guru-pembina border-primary" style="min-height: 31px; font-size: 0.78rem;" required>
                    ${opts}
                </select>
            </div>
        `;
    }
}

function createEkstraRowHtml(type, selectedValue = '', selectedPembina = '') {
    const div = document.createElement('div');
    div.className = 'ekstra-row-item card border shadow-xs p-2.5 bg-white rounded-3';
    div.innerHTML = `
        <div class="row g-2 align-items-center">
            <div class="col-md-6 col-12">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small text-secondary fw-semibold mb-0" style="font-size: 0.74rem;">Cabang Ekstrakurikuler</label>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">
                        <i class="bi bi-keyboard me-1"></i>Bisa Diketik
                    </span>
                </div>
                <div class="position-relative combobox-ekstra-container">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted border-end-0 py-1 px-2" style="font-size: 0.78rem;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               name="nama_ekstra[]" 
                               class="form-control form-control-sm input-search-ekstra border-start-0 border-end-0" 
                               style="font-size: 0.8rem; height: 31px;"
                               placeholder="-- Ketik atau Pilih Cabang --" 
                               value="${escapeHtml(selectedValue)}"
                               autocomplete="off" 
                               list="list_ekstra_options"
                               required>
                        <button type="button" 
                                class="btn btn-outline-secondary btn-clear-ekstra border-start-0 border-end-0 ${selectedValue ? '' : 'd-none'} text-muted py-1 px-2" 
                                style="font-size: 0.75rem;"
                                title="Kosongkan pilihan">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-secondary btn-toggle-ekstra py-1 px-2" 
                                style="font-size: 0.75rem;"
                                title="Tampilkan semua cabang">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="dropdown-menu dropdown-ekstra-list shadow-sm w-100 p-0 mt-1" 
                         style="max-height: 200px; overflow-y: auto; display: none; position: absolute; z-index: 1060;">
                    </div>
                </div>
            </div>
            <div class="col-md-5 col-10">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small text-secondary fw-semibold mb-0" style="font-size: 0.74rem;">Guru Pembina</label>
                </div>
                <div class="pembina-input-container">
                    ${renderPembinaInputHtml(selectedValue, selectedPembina)}
                </div>
            </div>
            <div class="col-md-1 col-2 text-end pt-md-3">
                <button type="button" class="btn btn-outline-danger btn-sm rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" onclick="removeEkstraRowItem(this)" title="Hapus Cabang">
                    <i class="bi bi-trash small"></i>
                </button>
            </div>
        </div>
    `;
    return div;
}

function wireUpSearchableEkstraRow(rowEl) {
    const container = rowEl.querySelector('.combobox-ekstra-container');
    const input = rowEl.querySelector('.input-search-ekstra');
    const toggleBtn = rowEl.querySelector('.btn-toggle-ekstra');
    const clearBtn = rowEl.querySelector('.btn-clear-ekstra');
    const dropdown = rowEl.querySelector('.dropdown-ekstra-list');
    const pembinaContainer = rowEl.querySelector('.pembina-input-container');

    if (!container || !input || !dropdown) return;

    function renderDropdown(filterText = '') {
        const q = filterText.trim().toLowerCase();
        const filtered = ekstraOptionsData.filter(item => {
            if (!q) return true;
            return item.name.toLowerCase().includes(q);
        });

        dropdown.innerHTML = '';

        if (filtered.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'p-2 text-center text-muted small';
            emptyDiv.innerHTML = '<i class="bi bi-search me-1"></i> Tidak ada cabang yang cocok';
            dropdown.appendChild(emptyDiv);
        } else {
            filtered.forEach(item => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dropdown-item py-1.5 px-3 border-bottom text-start w-100 d-flex justify-content-between align-items-center';
                
                let pBadge = '';
                if (item.pembinas && item.pembinas.length > 1) {
                    pBadge = `<span class="badge bg-primary-subtle text-primary border" style="font-size: 0.65rem;">${item.pembinas.length} Pembina</span>`;
                } else if (item.pembinas && item.pembinas.length === 1) {
                    pBadge = `<span class="badge bg-light text-secondary border small text-truncate" style="max-width: 130px; font-size: 0.65rem;">${escapeHtml(item.pembinas[0])}</span>`;
                }

                btn.innerHTML = `
                    <span class="fw-semibold text-dark" style="font-size: 0.8rem;">${escapeHtml(item.name)}</span>
                    ${pBadge}
                `;

                btn.addEventListener('click', () => {
                    input.value = item.name;
                    if (clearBtn) clearBtn.classList.remove('d-none');
                    closeList();
                    if (pembinaContainer) {
                        pembinaContainer.innerHTML = renderPembinaInputHtml(item.name, '');
                    }
                });
                dropdown.appendChild(btn);
            });
        }
        dropdown.style.display = 'block';
    }

    function openList() {
        renderDropdown(input.value);
    }

    function closeList() {
        dropdown.style.display = 'none';
    }

    input.addEventListener('focus', openList);
    input.addEventListener('input', function() {
        renderDropdown(this.value);
        if (clearBtn) {
            if (this.value) clearBtn.classList.remove('d-none');
            else clearBtn.classList.add('d-none');
        }

        const exactMatch = ekstraOptionsData.find(e => e.name.toLowerCase() === this.value.trim().toLowerCase());
        if (exactMatch) {
            if (pembinaContainer) {
                pembinaContainer.innerHTML = renderPembinaInputHtml(exactMatch.name, '');
            }
        } else if (!this.value.trim()) {
            if (pembinaContainer) {
                pembinaContainer.innerHTML = renderPembinaInputHtml('', '');
            }
        }
    });

    input.addEventListener('change', function() {
        const exactMatch = ekstraOptionsData.find(e => e.name.toLowerCase() === this.value.trim().toLowerCase());
        if (exactMatch) {
            this.value = exactMatch.name;
            if (pembinaContainer) {
                pembinaContainer.innerHTML = renderPembinaInputHtml(exactMatch.name, '');
            }
        } else {
            if (pembinaContainer) {
                pembinaContainer.innerHTML = renderPembinaInputHtml(this.value.trim(), '');
            }
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
            input.value = '';
            clearBtn.classList.add('d-none');
            if (pembinaContainer) {
                pembinaContainer.innerHTML = renderPembinaInputHtml('', '');
            }
            closeList();
            input.focus();
        });
    }

    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
            closeList();
        }
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeList();
        }
    });
}

function addEkstraRow(type, selectedValue = '', selectedPembina = '') {
    const containerId = type === 'add' ? 'containerEkstraRowsAdd' : 'containerEkstraRowsEdit';
    const container = document.getElementById(containerId);
    if (!container) return;

    const rowEl = createEkstraRowHtml(type, selectedValue, selectedPembina);
    container.appendChild(rowEl);
    wireUpSearchableEkstraRow(rowEl);
}

function removeEkstraRowItem(btn) {
    const row = btn.closest('.ekstra-row-item');
    if (!row) return;
    const container = row.parentElement;
    row.remove();

    // Pastikan minimal ada 1 baris
    if (container && container.children.length === 0) {
        const type = container.id === 'containerEkstraRowsAdd' ? 'add' : 'edit';
        addEkstraRow(type, '', '');
    }
}

function parsePembinaMapJs(pembinaStr, ekstraArr) {
    const map = {};
    if (!pembinaStr) return map;
    pembinaStr = String(pembinaStr).trim();

    if (pembinaStr.startsWith('{')) {
        try {
            return JSON.parse(pembinaStr);
        } catch (e) {}
    }

    if (pembinaStr.includes(':')) {
        const parts = pembinaStr.split(';');
        parts.forEach(part => {
            const sub = part.split(':');
            if (sub.length >= 2) {
                const k = sub[0].trim();
                const v = sub.slice(1).join(':').trim();
                if (k) map[k] = v;
            }
        });
        return map;
    }

    if (ekstraArr && ekstraArr.length === 1) {
        map[ekstraArr[0]] = pembinaStr;
        return map;
    }

    return map;
}

document.addEventListener('DOMContentLoaded', function() {
    initSearchableStudentPicker({
        containerId: 'comboboxMuridEkstra',
        inputId: 'searchMuridEkstraInput',
        toggleBtnId: 'btnToggleMuridEkstra',
        clearBtnId: 'btnClearMuridEkstra',
        dropdownId: 'dropdownMuridEkstraList',
        students: eligibleStudentsEkstra,
        onSelect: autoFillEkstraStudent
    });

    // Inisialisasi minimal 1 baris di modal Tambah
    const containerAdd = document.getElementById('containerEkstraRowsAdd');
    if (containerAdd && containerAdd.children.length === 0) {
        addEkstraRow('add', '', '');
    }
});

function editEkstra(se) {
    document.getElementById('edit_e_id').value = se.id;
    document.getElementById('edit_e_nisn').value = se.nisn;
    document.getElementById('edit_e_nama').value = se.nama_murid;
    document.getElementById('edit_e_jk').value = se.jenis_kelamin;
    document.getElementById('edit_e_kelas').value = se.kelas;

    // Bersihkan dan masukkan baris ekstra yang dipilih beserta pembina terpilih
    const container = document.getElementById('containerEkstraRowsEdit');
    if (container) {
        container.innerHTML = '';
        const currentEkstras = (se.nama_ekstra || '').split(',').map(s => s.trim()).filter(Boolean);
        const pembinaMap = parsePembinaMapJs(se.guru_pembina || '', currentEkstras);

        if (currentEkstras.length === 0) {
            addEkstraRow('edit', '', '');
        } else {
            currentEkstras.forEach(ekstraName => {
                const savedCoach = pembinaMap[ekstraName] || '';
                addEkstraRow('edit', ekstraName, savedCoach);
            });
        }
    }

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
