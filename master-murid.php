<?php
// master-murid.php - Master Data Murid / Siswa dengan Quick Status Checkbox
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

if (!hasAccess('master_murid')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke menu Data Siswa.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$kelasList = getDaftarKelas();

// 1. Export Excel / Template / CSV (Dijalankan SEBELUM output header HTML dikirim)
$isTemplate = isset($_GET['template']) || (isset($_GET['export']) && strtolower($_GET['export']) === 'template');
if ($isTemplate || (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv']))) {
    $rows = $pdo->query("SELECT * FROM data_murid ORDER BY kelas ASC, nama_murid ASC")->fetchAll();
    $data = [];
    $no = 1;

    // Jika unduh template, atau data di tabel masih kosong, sertakan data contoh (dummy) sebagai pedoman pengisian
    if ($isTemplate || empty($rows)) {
        $data = [
            [1, '0081234567', 'Aditya Pratama Putra', 'L', 'X TKJ 1', 'Bimbingan, Ekstra'],
            [2, '0087654321', 'Annisa Nurul Hidayah', 'P', 'X TKJ 1', 'Bimbingan'],
            [3, '0091122334', 'Bagas Dimas Saputra', 'L', 'XI RPL 2', 'Ekstra'],
            [4, '0095566778', 'Cantika Putri Lestari', 'P', 'XI RPL 2', '-'],
            [5, '0079988776', 'Dimas Arya Nugraha', 'L', 'XII MM 1', 'Bimbingan, Ekstra']
        ];
        $filenamePrefix = 'Template_Import_Data_Murid_';
    } else {
        foreach ($rows as $r) {
            $data[] = [
                $no++,
                formatNISN($r['nisn']),
                $r['nama_murid'],
                $r['jenis_kelamin'],
                $r['kelas'],
                $r['status']
            ];
        }
        $filenamePrefix = 'Data_Murid_';
    }

    $format = strtolower($_GET['export'] ?? ($_GET['template'] ?? 'xlsx'));
    if ($format === 'csv') {
        exportToExcelCSV($filenamePrefix . date('Ymd_His') . '.csv', ['No', 'NISN', 'Nama Murid', 'Jenis Kelamin', 'Kelas', 'Status Terhubung'], $data);
    } else {
        exportToExcelXLS($filenamePrefix . date('Ymd_His') . '.xlsx', ['No', 'NISN', 'Nama Murid', 'Jenis Kelamin', 'Kelas', 'Status Terhubung'], $data);
    }
}

// 2. Aksi CRUD POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isSuperAdmin()) {
        setFlash('danger', 'Hanya Super Admin yang berhak memodifikasi data siswa.');
        header("Location: master-murid.php");
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nisn = formatNISN($_POST['nisn'] ?? '');
        $nama = trim($_POST['nama_murid'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? 'L';
        $kelas = $_POST['kelas'] ?? '';
        $statuses = isset($_POST['status']) && is_array($_POST['status']) ? implode(', ', $_POST['status']) : 'Murid';

        if (!empty($nisn) && !empty($nama)) {
            $stmt = $pdo->prepare("INSERT INTO data_murid (nisn, nama_murid, jenis_kelamin, kelas, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nisn, $nama, $jk, $kelas, $statuses]);

            // Sinkronisasi otomatis ke data_murid_guru_wali jika ada status 'Guru Wali'
            if (strpos($statuses, 'Guru Wali') !== false) {
                $insGW = $pdo->prepare("INSERT INTO data_murid_guru_wali (nisn, nama_murid, kelas, jenis_kelamin, guru_wali, foto_murid, no_wa_murid, nama_ortu, kontak_ortu, catatan_khusus) VALUES (?, ?, ?, ?, ?, '', '', '', '', '')");
                $insGW->execute([$nisn, $nama, $kelas, $jk, 'Belum Ditentukan']);
            }

            // Sinkronisasi otomatis ke data_murid_ekstra jika ada status 'Ekstra'
            if (strpos($statuses, 'Ekstra') !== false) {
                $insEk = $pdo->prepare("INSERT INTO data_murid_ekstra (nisn, nama_murid, jenis_kelamin, kelas, nama_ekstra) VALUES (?, ?, ?, ?, ?)");
                $insEk->execute([$nisn, $nama, $jk, $kelas, 'Pramuka']);
            }

            setFlash('success', 'Data siswa berhasil ditambahkan.');
        }
        header("Location: master-murid.php");
        exit;
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $nisn = formatNISN($_POST['nisn'] ?? '');
        $nama = trim($_POST['nama_murid'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? 'L';
        $kelas = $_POST['kelas'] ?? '';
        $statuses = isset($_POST['status']) && is_array($_POST['status']) ? implode(', ', $_POST['status']) : 'Murid';

        $stmt = $pdo->prepare("UPDATE data_murid SET nisn = ?, nama_murid = ?, jenis_kelamin = ?, kelas = ?, status = ? WHERE id = ?");
        $stmt->execute([$nisn, $nama, $jk, $kelas, $statuses, $id]);
        setFlash('success', 'Data siswa berhasil diperbarui.');
        header("Location: master-murid.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM data_murid WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Data siswa berhasil dihapus.');
        header("Location: master-murid.php");
        exit;
    }

    // Import Excel (.xlsx / .xls) / CSV
    if ($action === 'import') {
        if (isset($_FILES['file_import']) && $_FILES['file_import']['error'] === UPLOAD_ERR_OK) {
            $rows = parseImportRows($_FILES['file_import']['tmp_name']);
            if (!empty($rows)) {
                $pdo->exec("DELETE FROM data_murid");
                $stmt = $pdo->prepare("INSERT INTO data_murid (nisn, nama_murid, jenis_kelamin, kelas, status) VALUES (?, ?, ?, ?, ?)");
                
                $firstRow = $rows[0];
                $colNisn = 1;
                $colNama = 2;
                $colJk   = 3;
                $colKls  = 4;
                $colSt   = 5;

                $hasHeader = false;
                foreach ($firstRow as $idx => $cell) {
                    $cLower = strtolower(trim((string)$cell));
                    if (strpos($cLower, 'nisn') !== false) { $colNisn = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'nama') !== false) { $colNama = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kelamin') !== false || $cLower === 'jk' || $cLower === 'l/p') { $colJk = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'kelas') !== false || strpos($cLower, 'rombel') !== false) { $colKls = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'status') !== false) { $colSt = $idx; $hasHeader = true; }
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
                        
                        // Abaikan baris rusak atau artefak script
                        if (empty($nama) || in_array($nama, ['«', '»', '<', '>', '&lt;', '&gt;', '+', '"+"']) || stripos($nama, 'c_rgszSh') !== false) {
                            continue;
                        }

                        $jk = strtoupper(trim($data[$colJk] ?? 'L'));
                        $jk = (strpos($jk, 'P') === 0 || strpos($jk, 'PEREMPUAN') === 0) ? 'P' : 'L';

                        $kls = trim($data[$colKls] ?? '');
                        if (in_array(strtolower($kls), ['«', '»', '<', '>', '&lt;', '&gt;'])) $kls = '';

                        $st = trim($data[$colSt] ?? 'Murid');
                        if (empty($st)) $st = 'Murid';

                        $stmt->execute([$nisn, $nama, $jk, $kls, $st]);
                        $inserted++;
                    }
                }
                setFlash('success', 'Import berhasil: ' . $inserted . ' data siswa disimpan ke database.');
            } else {
                setFlash('danger', 'Format berkas tidak valid atau data kosong.');
            }
        }
        header("Location: master-murid.php");
        exit;
    }
}

$pageTitle = 'Master Data Siswa';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

$user = currentUser();
$isSuper = isSuperAdmin();

// Ambil daftar guru dari data_guru
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);

// Petakan kelas yang diampu oleh tiap guru untuk dropdown dinamis
$guruClassMap = [];
foreach ($guruList as $gName) {
    $guruClassMap[$gName] = getKelasByGuru($gName);
}

// 3. Filter Guru & Kelas Sesuai Hak Akses
$search = trim($_GET['q'] ?? '');
$rawLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($rawLimit, [10, 20, 50, 100, 250, 500]) ? $rawLimit : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

if (!$isSuper) {
    // Pengajar (Guru / Wali Kelas): Nama guru terkunci sesuai akun login
    $filterGuru = $user['nama'] ?? '';
    $availableKelas = getKelasByGuru($filterGuru);

    if (empty($availableKelas)) {
        // Jika tidak mengampu kelas, filternya dikosongkan dan tidak dapat memilih kelas
        $filterKelas = '__NONE__';
    } else {
        // Otomatis terisi kelas sesuai gurunya
        $reqKelas = trim($_GET['kelas'] ?? '');
        if (!empty($reqKelas) && in_array($reqKelas, $availableKelas)) {
            $filterKelas = $reqKelas;
        } else {
            $filterKelas = $availableKelas[0]; // Terisi otomatis kelas pertama guru tersebut
        }
    }
} else {
    // Super Admin dapat memilih nama guru atau semua guru
    $filterGuru = trim($_GET['guru'] ?? '');
    if (!empty($filterGuru)) {
        $availableKelas = $guruClassMap[$filterGuru] ?? getKelasByGuru($filterGuru);
        $reqKelas = trim($_GET['kelas'] ?? '');
        if (!empty($reqKelas) && in_array($reqKelas, $availableKelas)) {
            $filterKelas = $reqKelas;
        } elseif (!empty($availableKelas)) {
            $filterKelas = $reqKelas ?: $availableKelas[0]; // Terisi otomatis kelas guru tersebut
        } else {
            $filterKelas = '';
        }
    } else {
        $availableKelas = getDaftarKelas();
        $filterKelas = trim($_GET['kelas'] ?? '');
    }
}

$whereClauses = [];
$params = [];

if ($filterKelas === '__NONE__') {
    $whereClauses[] = "1 = 0"; // Tidak menampilkan siswa jika guru tidak mengampu kelas
} elseif (!empty($filterKelas)) {
    $whereClauses[] = "m.kelas = ?";
    $params[] = $filterKelas;
} elseif (!empty($filterGuru) && !empty($availableKelas)) {
    $inQ = implode(',', array_fill(0, count($availableKelas), '?'));
    $whereClauses[] = "m.kelas IN ($inQ)";
    foreach ($availableKelas as $ck) {
        $params[] = $ck;
    }
}

if (!empty($search)) {
    $whereClauses[] = "(m.nama_murid LIKE ? OR m.nisn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sqlWhere = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM data_murid m $sqlWhere");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT m.*, gw.foto_murid FROM data_murid m LEFT JOIN data_murid_guru_wali gw ON (m.nisn = gw.nisn OR m.nama_murid = gw.nama_murid) $sqlWhere ORDER BY m.kelas ASC, m.nama_murid ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$muridList = $stmt->fetchAll();
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Master Data Siswa</h4>
            <p class="text-muted small mb-0">Daftar induk siswa dengan fitur quick-edit checkbox status terhubung.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <?php if ($isSuper): ?>
            <button class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddMurid">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Siswa
            </button>
            <?php endif; ?>
            <a href="master-murid.php?<?= http_build_query(array_merge($_GET, ['export' => 'xlsx'])) ?>" class="btn btn-outline-success rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
            </a>
            <a href="master-murid.php?template=xlsx" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" title="Unduh template Excel dengan pedoman data contoh (dummy)">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template (.xlsx)
            </a>
            <?php if ($isSuper): ?>
            <button class="btn btn-outline-primary rounded-pill px-3 btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportMurid">
                <i class="bi bi-upload me-1"></i> Import Excel
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card card-table p-3 mb-3">
        <form method="GET" id="formFilterMurid" class="row g-2 align-items-center">
            <div class="col-md-3 col-12">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari NISN atau nama siswa..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>

            <!-- Filter Guru -->
            <div class="col-md-3 col-sm-6 col-12">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-secondary"><i class="bi bi-person-badge"></i></span>
                    <?php if ($isSuper): ?>
                        <select name="guru" id="selectFilterGuru" class="form-select form-select-sm" onchange="handleGuruFilterChange(this.value)">
                            <option value="">Semua Guru</option>
                            <?php foreach ($guruList as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>" <?= $filterGuru === $g ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" class="form-control bg-light fw-semibold text-dark" value="🔒 <?= htmlspecialchars($filterGuru) ?>" readonly title="Terkunci sesuai akun pengajar aktif">
                        <input type="hidden" name="guru" value="<?= htmlspecialchars($filterGuru) ?>">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Kelas (Otomatis Terisi Sesuai Guru) -->
            <div class="col-md-3 col-sm-6 col-12">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-secondary"><i class="bi bi-mortarboard"></i></span>
                    <?php if (!$isSuper && empty($availableKelas)): ?>
                        <select name="kelas" class="form-select form-select-sm bg-light text-muted" disabled title="Pengajar tidak mengampu kelas aktif">
                            <option value="">(Tidak Mengampu Kelas)</option>
                        </select>
                    <?php else: ?>
                        <select name="kelas" id="selectFilterKelas" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php if ($isSuper && empty($filterGuru)): ?>
                                <option value="">Semua Kelas</option>
                            <?php endif; ?>
                            <?php foreach ($availableKelas as $k): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $filterKelas === $k ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-1 col-6">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 50, 100, 250, 500] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?>/hal</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Filter</button>
                <?php if (!empty($search) || ($isSuper && !empty($filterGuru)) || (!empty($filterKelas) && $filterKelas !== '__NONE__')): ?>
                    <a href="master-murid.php" class="btn btn-outline-secondary btn-sm rounded-pill">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabel Data Murid -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>NISN</th>
                        <th>Nama Murid</th>
                        <th>L/P</th>
                        <th>Kelas</th>
                        <th style="min-width: 320px;">
                            Status Terhubung <small class="text-muted fw-normal">(Edit Cepat)</small>
                        </th>
                        <th class="text-center" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($filterKelas === '__NONE__'): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-info-circle me-1 text-primary"></i> Anda tidak tercatat mengampu kelas aktif saat ini. Filter kelas dikosongkan.</td></tr>
                    <?php elseif (empty($muridList)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Data siswa tidak ditemukan.</td></tr>
                    <?php else: 
                        $no = $offset + 1;
                        foreach ($muridList as $m): 
                            $statusArr = array_map('trim', explode(',', $m['status'] ?? ''));
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($m['nisn']) ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($m['foto_murid'])): ?>
                                        <img src="<?= htmlspecialchars(formatPhotoUrl($m['foto_murid'])) ?>" 
                                             alt="Foto <?= htmlspecialchars($m['nama_murid']) ?>" 
                                             class="rounded-circle border shadow-xs" 
                                             style="width: 32px; height: 32px; object-fit: cover; cursor: pointer;"
                                             title="Klik untuk perbesar"
                                             onclick="showStudentPhoto('<?= htmlspecialchars(formatPhotoUrl($m['foto_murid'])) ?>', '<?= htmlspecialchars(addslashes($m['nama_murid'])) ?>')">
                                    <?php else: ?>
                                        <span class="avatar-initials bg-secondary-subtle text-secondary rounded-circle d-inline-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                            <?= strtoupper(substr($m['nama_murid'], 0, 1)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($m['nama_murid']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $m['jenis_kelamin'] === 'L' ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' ?>">
                                    <?= $m['jenis_kelamin'] ?>
                                </span>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary fw-semibold"><?= htmlspecialchars($m['kelas']) ?></span></td>
                            <td>
                                <!-- Quick-Edit Checkboxes Sesuai PRD -->
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="form-check form-check-inline m-0">
                                        <input class="form-check-input" type="checkbox" id="st_m_<?= $m['id'] ?>"
                                            <?= in_array('Murid', $statusArr) ? 'checked' : '' ?>
                                            <?= $isSuper ? 'onchange="toggleStudentStatus(this, ' . $m['id'] . ', \'Murid\')"' : 'disabled' ?>>
                                        <label class="form-check-label small" for="st_m_<?= $m['id'] ?>">Murid</label>
                                    </div>
                                    <div class="form-check form-check-inline m-0">
                                        <input class="form-check-input" type="checkbox" id="st_gw_<?= $m['id'] ?>"
                                            <?= in_array('Guru Wali', $statusArr) ? 'checked' : '' ?>
                                            <?= $isSuper ? 'onchange="toggleStudentStatus(this, ' . $m['id'] . ', \'Guru Wali\')"' : 'disabled' ?>>
                                        <label class="form-check-label small text-info fw-semibold" for="st_gw_<?= $m['id'] ?>">Guru Wali</label>
                                    </div>
                                    <div class="form-check form-check-inline m-0">
                                        <input class="form-check-input" type="checkbox" id="st_wk_<?= $m['id'] ?>"
                                            <?= in_array('Wali Kelas', $statusArr) ? 'checked' : '' ?>
                                            <?= $isSuper ? 'onchange="toggleStudentStatus(this, ' . $m['id'] . ', \'Wali Kelas\')"' : 'disabled' ?>>
                                        <label class="form-check-label small text-primary fw-semibold" for="st_wk_<?= $m['id'] ?>">Wali Kelas</label>
                                    </div>
                                    <div class="form-check form-check-inline m-0">
                                        <input class="form-check-input" type="checkbox" id="st_ek_<?= $m['id'] ?>"
                                            <?= in_array('Ekstra', $statusArr) ? 'checked' : '' ?>
                                            <?= $isSuper ? 'onchange="toggleStudentStatus(this, ' . $m['id'] . ', \'Ekstra\')"' : 'disabled' ?>>
                                        <label class="form-check-label small text-success fw-semibold" for="st_ek_<?= $m['id'] ?>">Ekstra</label>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center nowrap">
                                <?php if ($isSuper): ?>
                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" 
                                        onclick='editMurid(<?= json_encode($m) ?>)' title="Edit Siswa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 ms-1" 
                                        onclick="deleteMurid(<?= $m['id'] ?>, '<?= addslashes($m['nama_murid']) ?>')" title="Hapus Siswa">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border py-1 px-2" title="Menu aksi terkunci untuk hak akses Anda">
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
                    <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&kelas=<?= urlencode($filterKelas) ?>&q=<?= urlencode($search) ?>">Sebelumnya</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&kelas=<?= urlencode($filterKelas) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&kelas=<?= urlencode($filterKelas) ?>&q=<?= urlencode($search) ?>">Selanjutnya</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Tambah Murid -->
<div class="modal fade" id="modalAddMurid" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Data Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-murid.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">NISN</label>
                            <input type="text" name="nisn" class="form-control" required placeholder="0071234567">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Nama Lengkap Siswa</label>
                            <input type="text" name="nama_murid" class="form-control" required placeholder="Nama Siswa">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-select">
                                <option value="L">Laki-Laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12 position-relative" id="comboboxMuridKelasAdd">
                            <label class="form-label fw-semibold small d-flex justify-content-between align-items-center mb-1">
                                <span>Kelas</span>
                                <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.72rem;"><i class="bi bi-keyboard me-1"></i>Bisa Diketik</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" 
                                       name="kelas" 
                                       id="add_m_kelas" 
                                       class="form-control" 
                                       placeholder="Pilih / ketik kelas..." 
                                       autocomplete="off" 
                                       list="list_kelas_murid" 
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="btnToggleMuridKelasAdd" title="Tampilkan semua kelas">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <button class="btn btn-outline-secondary d-none" type="button" id="btnClearMuridKelasAdd" title="Hapus pilihan">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <div class="dropdown-menu shadow w-100 p-1" id="dropdownMuridKelasAdd" style="max-height: 250px; overflow-y: auto; display: none; position: absolute; z-index: 1055;"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small d-block">Status Keterlibatan</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="Murid" id="add_st_murid" checked>
                                <label class="form-check-label small" for="add_st_murid">Murid</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="Guru Wali" id="add_st_gw">
                                <label class="form-check-label small" for="add_st_gw">Guru Wali</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="Wali Kelas" id="add_st_wk">
                                <label class="form-check-label small" for="add_st_wk">Wali Kelas</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="Ekstra" id="add_st_ek">
                                <label class="form-check-label small" for="add_st_ek">Ekstra</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Murid -->
<div class="modal fade" id="modalEditMurid" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Data Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-murid.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_m_id">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">NISN</label>
                            <input type="text" name="nisn" id="edit_m_nisn" class="form-control" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Nama Lengkap Siswa</label>
                            <input type="text" name="nama_murid" id="edit_m_nama" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold small">Jenis Kelamin</label>
                            <select name="jenis_kelamin" id="edit_m_jk" class="form-select">
                                <option value="L">Laki-Laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12 position-relative" id="comboboxMuridKelasEdit">
                            <label class="form-label fw-semibold small d-flex justify-content-between align-items-center mb-1">
                                <span>Kelas</span>
                                <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.72rem;"><i class="bi bi-keyboard me-1"></i>Bisa Diketik</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" 
                                       name="kelas" 
                                       id="edit_m_kelas" 
                                       class="form-control" 
                                       placeholder="Pilih / ketik kelas..." 
                                       autocomplete="off" 
                                       list="list_kelas_murid" 
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="btnToggleMuridKelasEdit" title="Tampilkan semua kelas">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <button class="btn btn-outline-secondary d-none" type="button" id="btnClearMuridKelasEdit" title="Hapus pilihan">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <div class="dropdown-menu shadow w-100 p-1" id="dropdownMuridKelasEdit" style="max-height: 250px; overflow-y: auto; display: none; position: absolute; z-index: 1055;"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small d-block">Status Keterlibatan</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="Murid" id="edit_st_murid">
                                <label class="form-check-label small" for="edit_st_murid">Murid</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="Guru Wali" id="edit_st_gw">
                                <label class="form-check-label small" for="edit_st_gw">Guru Wali</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="Wali Kelas" id="edit_st_wk">
                                <label class="form-check-label small" for="edit_st_wk">Wali Kelas</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="Ekstra" id="edit_st_ek">
                                <label class="form-check-label small" for="edit_st_ek">Ekstra</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Perbarui Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Murid -->
<div class="modal fade" id="modalImportMurid" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Import Data Siswa (Excel .xls / CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="master-murid.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Perhatian: Sesuai ketentuan, data lama akan dihapus dan digantikan dengan data baru yang diimport.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pilih File Excel (.xlsx / .xls) atau CSV</label>
                        <input type="file" name="file_import" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <small class="text-muted d-block mb-2">Format kolom: No, NISN, Nama Murid, Jenis Kelamin (L/P), Kelas, Status</small>
                    <a href="master-murid.php?template=xlsx" class="btn btn-outline-success btn-sm rounded-pill px-3">
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

<form id="formDeleteMurid" method="POST" action="master-murid.php" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_m_id">
</form>

<datalist id="list_kelas_murid">
    <?php foreach ($kelasList as $k): ?>
        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
    <?php endforeach; ?>
</datalist>

<script>
const guruClassMap = <?= json_encode($guruClassMap) ?>;
const allSchoolClasses = <?= json_encode(getDaftarKelas()) ?>;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

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
            input.focus();
            closeList();
            if (typeof config.onSelect === 'function') config.onSelect('');
        });
    }

    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
            closeList();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initSearchableKelasPicker({
        containerId: 'comboboxMuridKelasAdd',
        inputId: 'add_m_kelas',
        toggleBtnId: 'btnToggleMuridKelasAdd',
        clearBtnId: 'btnClearMuridKelasAdd',
        dropdownId: 'dropdownMuridKelasAdd',
        items: allSchoolClasses,
        allowNone: false
    });
    initSearchableKelasPicker({
        containerId: 'comboboxMuridKelasEdit',
        inputId: 'edit_m_kelas',
        toggleBtnId: 'btnToggleMuridKelasEdit',
        clearBtnId: 'btnClearMuridKelasEdit',
        dropdownId: 'dropdownMuridKelasEdit',
        items: allSchoolClasses,
        allowNone: false
    });
});

function handleGuruFilterChange(selectedGuru) {
    const kelasSelect = document.getElementById('selectFilterKelas');
    if (!kelasSelect) return;

    kelasSelect.innerHTML = '';

    if (!selectedGuru) {
        const optAll = document.createElement('option');
        optAll.value = '';
        optAll.textContent = 'Semua Kelas';
        kelasSelect.appendChild(optAll);

        allSchoolClasses.forEach(function(k) {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = k;
            kelasSelect.appendChild(opt);
        });
    } else {
        const classes = guruClassMap[selectedGuru] || [];
        if (classes.length === 0) {
            const optNone = document.createElement('option');
            optNone.value = '';
            optNone.textContent = '(Tidak Mengampu Kelas)';
            kelasSelect.appendChild(optNone);
        } else {
            classes.forEach(function(k, idx) {
                const opt = document.createElement('option');
                opt.value = k;
                opt.textContent = k;
                if (idx === 0) opt.selected = true;
                kelasSelect.appendChild(opt);
            });
        }
    }

    document.getElementById('formFilterMurid').submit();
}

function editMurid(m) {
    document.getElementById('edit_m_id').value = m.id;
    document.getElementById('edit_m_nisn').value = m.nisn;
    document.getElementById('edit_m_nama').value = m.nama_murid;
    document.getElementById('edit_m_jk').value = m.jenis_kelamin;
    document.getElementById('edit_m_kelas').value = m.kelas;
    const clearBtnEdit = document.getElementById('btnClearMuridKelasEdit');
    if (clearBtnEdit) {
        if (m.kelas) clearBtnEdit.classList.remove('d-none');
        else clearBtnEdit.classList.add('d-none');
    }

    const statuses = (m.status || '').split(',').map(s => s.trim());
    document.getElementById('edit_st_murid').checked = statuses.includes('Murid');
    document.getElementById('edit_st_gw').checked = statuses.includes('Guru Wali');
    document.getElementById('edit_st_wk').checked = statuses.includes('Wali Kelas');
    document.getElementById('edit_st_ek').checked = statuses.includes('Ekstra');

    new bootstrap.Modal(document.getElementById('modalEditMurid')).show();
}

function deleteMurid(id, name) {
    Swal.fire({
        title: 'Hapus Siswa?',
        text: 'Apakah Anda yakin ingin menghapus data ' + name + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_m_id').value = id;
            document.getElementById('formDeleteMurid').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
