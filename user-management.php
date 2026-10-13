<?php
// user-management.php - Manajemen User & Hak Akses (RBAC) SiGuru
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
$user = currentUser();

if (!isSuperAdmin()) {
    setFlash('danger', 'Hanya Super Admin yang dapat mengakses Manajemen User.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();

// Export Excel / Template / CSV (Dijalankan SEBELUM output header HTML)
// Khusus username default (SAdmin) tidak dapat di-export demi keamanan akun induk sistem
$isTemplate = isset($_GET['template']) || (isset($_GET['export']) && strtolower($_GET['export']) === 'template');
if ($isTemplate || (isset($_GET['export']) && in_array(strtolower($_GET['export']), ['xlsx', 'xls', 'csv']))) {
    $rows = $pdo->query("SELECT * FROM data_user WHERE username != 'SAdmin' ORDER BY id ASC")->fetchAll();
    $data = [];
    $no = 1;

    // Jika unduh template, atau data user masih kosong, sertakan data contoh (dummy) sebagai pedoman pengisian
    if ($isTemplate || empty($rows)) {
        $data = [
            [1, 'budi.santoso', 'guru123', 'Guru', 'Drs. Budi Santoso, M.Pd.', '197503122000031002'],
            [2, 'siti.nurhaliza', 'guru123', 'Guru Wali, Wali Kelas', 'Siti Nurhaliza, S.Pd.', '198506242008012005'],
            [3, 'ahmad.fauzi', 'guru123', 'Guru, Pembina Ekstra', 'Ahmad Fauzi, S.Kom.', '199201152019031004'],
            [4, 'admin.kurikulum', 'admin123', 'Super Admin', 'Staf Administrasi Kurikulum', '-'],
            [5, 'kepala.sekolah', 'kepsek123', 'Pelihat', 'Dra. Hj. Wahyuni, M.Pd.', '197008171995122001']
        ];
        $filenamePrefix = 'Template_Import_Data_User_';
    } else {
        foreach ($rows as $r) {
            $data[] = [$no++, $r['username'], $r['password'], $r['hak_akses'], $r['nama'], $r['nip'] ?: '-'];
        }
        $filenamePrefix = 'Data_User_';
    }

    $format = strtolower($_GET['export'] ?? ($_GET['template'] ?? 'xlsx'));
    if ($format === 'csv') {
        exportToExcelCSV($filenamePrefix . date('Ymd_His') . '.csv', ['No', 'Username', 'Password', 'Hak Akses', 'Nama', 'NIP'], $data);
    } else {
        exportToExcelXLS($filenamePrefix . date('Ymd_His') . '.xlsx', ['No', 'Username', 'Password', 'Hak Akses', 'Nama', 'NIP'], $data);
    }
}

// 1. Aksi CRUD POST (Dijalankan sebelum include views/header.php agar header redirect selalu bersih)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $nip = trim($_POST['nip'] ?? '');

        // Proteksi: cegah pembuatan username SAdmin duplikat
        if (strtolower($username) === 'sadmin') {
            setFlash('danger', 'Username "SAdmin" adalah akun sistem tetap dan tidak dapat dibuat ulang.');
            header("Location: user-management.php");
            exit;
        }
        
        // Multi-role selection (bisa pilih lebih dari 1 role atau Super Admin / Pelihat)
        $roles = isset($_POST['hak_akses']) && is_array($_POST['hak_akses']) 
            ? implode(', ', $_POST['hak_akses']) 
            : trim($_POST['hak_akses_single'] ?? 'Guru');

        if (!empty($username) && !empty($password) && !empty($nama)) {
            // Cek apakah username sudah ada
            $chk = $pdo->prepare("SELECT COUNT(*) FROM data_user WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetchColumn() > 0) {
                setFlash('danger', 'Username sudah digunakan oleh akun lain.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO data_user (username, password, hak_akses, nama, nip) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password, $roles, $nama, $nip]);
                setFlash('success', 'User ' . htmlspecialchars($username) . ' berhasil ditambahkan.');
            }
        }
        header("Location: user-management.php");
        exit;
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        
        // Cek target user yang akan diperbarui
        $targetStmt = $pdo->prepare("SELECT * FROM data_user WHERE id = ?");
        $targetStmt->execute([$id]);
        $targetUser = $targetStmt->fetch();

        if (!$targetUser) {
            setFlash('danger', 'User tidak ditemukan.');
            header("Location: user-management.php");
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        
        $submittedRoles = isset($_POST['hak_akses']) && is_array($_POST['hak_akses']) 
            ? implode(', ', $_POST['hak_akses']) 
            : trim($_POST['hak_akses_single'] ?? 'Guru');

        // Ketentuan Khusus:
        // 1. Khusus hak akses Super Admin yang usernamenya "SAdmin" (default) tidak boleh diganti dan terkunci
        if ($targetUser['username'] === 'SAdmin') {
            // Hanya akun SAdmin sendiri yang berwenang mengubah profil/password SAdmin
            if ($user['username'] !== 'SAdmin') {
                setFlash('danger', 'Akun default "SAdmin" terkunci dan hanya dapat dikelola oleh user SAdmin sendiri.');
                header("Location: user-management.php");
                exit;
            }
            $username = 'SAdmin'; // Kunci tetap SAdmin
            $roles = 'Super Admin'; // Kunci hak akses tetap Super Admin
        } else {
            // Cegah akun lain mengubah username menjadi SAdmin
            if (strtolower($username) === 'sadmin') {
                setFlash('danger', 'Username "SAdmin" dikhususkan untuk akun sistem default dan tidak dapat digunakan.');
                header("Location: user-management.php");
                exit;
            }

            // Cek apakah username baru sudah digunakan oleh akun lain
            $chkDup = $pdo->prepare("SELECT COUNT(*) FROM data_user WHERE username = ? AND id != ?");
            $chkDup->execute([$username, $id]);
            if ($chkDup->fetchColumn() > 0) {
                setFlash('danger', 'Username "' . htmlspecialchars($username) . '" sudah digunakan oleh akun lain.');
                header("Location: user-management.php");
                exit;
            }

            // Hak akses akun lain (termasuk Super Admin selain SAdmin) dapat diubah sesuai form
            $roles = !empty($submittedRoles) ? $submittedRoles : 'Guru';
        }

        if (!empty($password)) {
            // Update dengan password baru
            $stmt = $pdo->prepare("UPDATE data_user SET username = ?, password = ?, hak_akses = ?, nama = ?, nip = ? WHERE id = ?");
            $stmt->execute([$username, $password, $roles, $nama, $nip, $id]);
        } else {
            // Update tanpa mengganti password
            $stmt = $pdo->prepare("UPDATE data_user SET username = ?, hak_akses = ?, nama = ?, nip = ? WHERE id = ?");
            $stmt->execute([$username, $roles, $nama, $nip, $id]);
        }

        // Jika user yang diperbarui adalah akun yang sedang aktif login, perbarui session langsung
        if ($id === $user['id']) {
            $_SESSION['username'] = $username;
            $_SESSION['nama'] = $nama;
            $_SESSION['nip'] = $nip;
            $_SESSION['hak_akses'] = $roles;
        }

        setFlash('success', 'Data user ' . htmlspecialchars($username) . ' berhasil diperbarui.');
        header("Location: user-management.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        $targetStmt = $pdo->prepare("SELECT * FROM data_user WHERE id = ?");
        $targetStmt->execute([$id]);
        $targetUser = $targetStmt->fetch();

        // Khusus hak akses Super Admin dengan username "SAdmin" (default) yang tidak boleh dihapus dan terkunci.
        // Hak akses Super Admin lainnya dapat dihapus.
        if ($targetUser && $targetUser['username'] === 'SAdmin') {
            setFlash('danger', 'Akun default "SAdmin" adalah akun induk sistem, terkunci dan tidak dapat dihapus.');
        } elseif ($id === $user['id']) {
            setFlash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM data_user WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'User ' . htmlspecialchars($targetUser['username'] ?? '') . ' berhasil dihapus.');
        }
        header("Location: user-management.php");
        exit;
    }

    // Import Excel CSV Sesuai PRD:
    // Khusus akun default SAdmin tetap dipertahankan, data akun lain akan diperbarui/digantikan
    if ($action === 'import') {
        if (isset($_FILES['file_import']) && $_FILES['file_import']['error'] === UPLOAD_ERR_OK) {
            $rows = parseImportRows($_FILES['file_import']['tmp_name']);
            if (!empty($rows)) {
                // Hapus user lama KECUALI Super Admin (SAdmin, fyj) dan Pelihat (viewer)
                $pdo->exec("DELETE FROM data_user WHERE username NOT IN ('SAdmin', 'admin', 'viewer', 'fyj') AND hak_akses NOT LIKE '%Super Admin%'");
                $stmt = $pdo->prepare("INSERT INTO data_user (username, password, hak_akses, nama, nip) VALUES (?, ?, ?, ?, ?)");
                
                $firstRow = $rows[0];
                $colUn = 1;
                $colPw = 2;
                $colHa = 3;
                $colNm = 4;
                $colNp = 5;

                $hasHeader = false;
                foreach ($firstRow as $idx => $cell) {
                    $cLower = strtolower(trim((string)$cell));
                    if (strpos($cLower, 'username') !== false || $cLower === 'user') { $colUn = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'password') !== false || strpos($cLower, 'sandi') !== false) { $colPw = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'akses') !== false || strpos($cLower, 'role') !== false) { $colHa = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'nama') !== false) { $colNm = $idx; $hasHeader = true; }
                    elseif (strpos($cLower, 'nip') !== false) { $colNp = $idx; $hasHeader = true; }
                }

                $inserted = 0;
                foreach ($rows as $idx => $data) {
                    if ($idx === 0 && $hasHeader) continue;
                    if (count($data) >= 3) {
                        $un = trim($data[$colUn] ?? '');
                        $pw = trim($data[$colPw] ?? '123456');
                        $ha = trim($data[$colHa] ?? 'Guru');
                        $nm = trim($data[$colNm] ?? '');
                        $np = trim($data[$colNp] ?? '');

                        // Abaikan artefak navigasi / script
                        if (empty($un) || empty($nm) || in_array($un, ['«', '»', '<', '>', '&lt;', '&gt;', '+', '"+"']) || stripos($un, 'c_rgszSh') !== false) {
                            continue;
                        }

                        if (!in_array(strtolower($un), ['sadmin', 'admin', 'viewer', 'fyj'])) {
                            $stmt->execute([$un, $pw, $ha, $nm, $np]);
                            $inserted++;
                        }
                    }
                }
                setFlash('success', 'Import berhasil: ' . $inserted . ' akun pengguna disimpan ke database.');
            } else {
                setFlash('danger', 'Format berkas tidak valid atau data kosong.');
            }
        }
        header("Location: user-management.php");
        exit;
    }
}

$userList = $pdo->query("SELECT * FROM data_user ORDER BY id ASC")->fetchAll();
$guruList = $pdo->query("SELECT id, nama_guru, nip, sebagai_guru_wali, sebagai_wali_kelas FROM data_guru ORDER BY nama_guru ASC")->fetchAll();

$pageTitle = 'Manajemen Pengguna & Role';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Manajemen Pengguna & Role Akses</h4>
            <p class="text-muted small mb-0">Kelola akun pengguna, penetapan peran (RBAC), dan otorisasi menu.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <button class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddUser">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah User Baru
            </button>
            <a href="user-management.php?export=xlsx" class="btn btn-outline-success rounded-pill px-3 btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel (.xlsx)
            </a>
            <a href="user-management.php?template=xlsx" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" title="Unduh template Excel dengan pedoman data contoh (dummy)">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template (.xlsx)
            </a>
            <button class="btn btn-outline-primary rounded-pill px-3 btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportUser">
                <i class="bi bi-upload me-1"></i> Import Excel
            </button>
        </div>
    </div>

    <!-- Tabel Data Pengguna -->
    <div class="card card-table overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Nama Pengguna</th>
                        <th>NIP</th>
                        <th>Hak Akses / Peran</th>
                        <th class="text-center" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($userList as $u): 
                        $isSAdmin = ($u['username'] === 'SAdmin');
                        $isSuper = ($u['hak_akses'] === 'Super Admin' || strpos($u['hak_akses'], 'Super Admin') !== false);

                        // Aturan Password SAdmin:
                        // Hanya username "SAdmin" saja yang dapat melihat password akun SAdmin.
                        // Super Admin lain dan rol akses lain tidak dapat melihat password SAdmin.
                        $canViewThisPassword = true;
                        if ($isSAdmin && $user['username'] !== 'SAdmin') {
                            $canViewThisPassword = false;
                        }

                        // Payload data yang aman untuk modal edit (password SAdmin disembunyikan jika bukan SAdmin)
                        $uSafe = $u;
                        if ($isSAdmin && $user['username'] !== 'SAdmin') {
                            $uSafe['password'] = '';
                        }
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <strong class="font-monospace text-primary"><?= htmlspecialchars($u['username']) ?></strong>
                                <?php if ($isSAdmin): ?>
                                    <span class="badge bg-dark-subtle text-dark border ms-1" title="Username Sistem Terkunci"><i class="bi bi-lock-fill text-warning me-1"></i>Terkunci</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canViewThisPassword): ?>
                                    <div class="d-inline-flex align-items-center gap-1 font-monospace bg-light px-2 py-1 rounded border">
                                        <span id="table_pwd_<?= $u['id'] ?>" class="text-secondary small">••••••••</span>
                                        <button type="button" class="btn btn-sm btn-link p-0 text-secondary border-0 ms-1 text-decoration-none" 
                                            onclick="toggleTablePassword(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['password'])) ?>', this)" 
                                            title="Lihat / Sembunyikan Password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="d-inline-flex align-items-center gap-1 font-monospace bg-light px-2 py-1 rounded border text-muted" title="Password Akun Induk SAdmin Dirahasiakan (Hanya dapat dilihat oleh username SAdmin)">
                                        <span class="text-secondary small">••••••••</span>
                                        <span class="text-secondary ms-1 small" title="Dirahasiakan">
                                            <i class="bi bi-shield-lock-fill text-warning"></i>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($u['nama']) ?></td>
                            <td><?= htmlspecialchars($u['nip'] ?: '-') ?></td>
                            <td>
                                <?php if ($isSuper): ?>
                                    <span class="badge bg-danger px-3 py-1"><i class="bi bi-shield-lock-fill me-1"></i>Super Admin</span>
                                <?php elseif ($u['hak_akses'] === 'Pelihat'): ?>
                                    <span class="badge bg-secondary px-3 py-1">Pelihat (Hanya Laporan)</span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1"><?= htmlspecialchars($u['hak_akses']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isSAdmin && $user['username'] !== 'SAdmin'): ?>
                                <button class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-0 disabled" title="Akun Induk Terkunci (Hanya dapat diedit oleh akun SAdmin)" disabled>
                                    <i class="bi bi-lock-fill"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" 
                                    onclick='editUser(<?= json_encode($uSafe) ?>)' title="<?= $isSAdmin ? 'Edit Profil/Password SAdmin' : 'Edit Akun' ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php endif; ?>

                                <?php if (!$isSAdmin && $u['id'] !== $user['id']): ?>
                                <button class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 ms-1" 
                                    onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes($u['username']) ?>')" title="Hapus Akun">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php elseif ($isSAdmin): ?>
                                <button class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-0 ms-1 disabled" title="Akun Induk Default Terkunci (SAdmin tidak dapat dihapus)" disabled>
                                    <i class="bi bi-lock-fill text-warning"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-0 ms-1 disabled" title="Akun Anda Sendiri (Sedang aktif)" disabled>
                                    <i class="bi bi-lock-fill"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Tambah User -->
<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Akun Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="user-management.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <!-- Ambil Data dari DataGuru Sesuai PRD -->
                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <label class="form-label small fw-semibold text-primary d-block">
                            <i class="bi bi-magic me-1"></i> Ambil Data dari Guru Master (Opsional):
                        </label>
                        <select class="form-select form-select-sm" onchange="autoFillGuruUser(this)">
                            <option value="">-- Pilih Guru untuk Mengisi Nama & NIP Otomatis --</option>
                            <?php foreach ($guruList as $g): ?>
                                <option value="<?= htmlspecialchars(json_encode($g)) ?>">
                                    <?= htmlspecialchars($g['nama_guru']) ?> (NIP: <?= htmlspecialchars($g['nip'] ?: '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nama Lengkap</label>
                            <input type="text" name="nama" id="add_u_nama" class="form-control" required placeholder="Nama Lengkap">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">NIP</label>
                            <input type="text" name="nip" id="add_u_nip" class="form-control" placeholder="19800101 ...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Username</label>
                            <input type="text" name="username" id="add_u_username" class="form-control" required placeholder="username_login">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="add_u_password" class="form-control" required placeholder="Minimal 6 karakter">
                                <button type="button" class="btn btn-outline-secondary btn-toggle-password" onclick="togglePass('add_u_password', this)" title="Lihat / Sembunyikan Password" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Role / Hak Akses (Mendukung multi role sesuai PRD) -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small d-block">Hak Akses / Peran Pengguna</label>
                            <div class="card p-3 bg-light border">
                                <small class="text-muted d-block mb-2">Pilih peran akun (dapat mencentang lebih dari satu peran untuk akses jamak):</small>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Super Admin" id="role_sa">
                                            <label class="form-check-label fw-bold text-danger" for="role_sa">Super Admin (Full Akses)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Guru" id="role_guru" checked>
                                            <label class="form-check-label fw-semibold" for="role_guru">Guru (Presensi & Jurnal)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Wali Kelas" id="role_wk">
                                            <label class="form-check-label fw-semibold" for="role_wk">Wali Kelas</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Guru Wali" id="role_gw">
                                            <label class="form-check-label fw-semibold" for="role_gw">Guru Wali (Bimbingan)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Pembina Ekstra" id="role_ek">
                                            <label class="form-check-label fw-semibold" for="role_ek">Pembina Ekstra</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Pelihat" id="role_vi">
                                            <label class="form-check-label text-muted" for="role_vi">Pelihat (Hanya Laporan)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Buat Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Akun Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="user-management.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_u_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nama Lengkap</label>
                            <input type="text" name="nama" id="edit_u_nama" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">NIP</label>
                            <input type="text" name="nip" id="edit_u_nip" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Username</label>
                            <input type="text" name="username" id="edit_u_username" class="form-control" required>
                            <small id="sadmin_username_locked_msg" class="text-danger fw-semibold d-none mt-1">
                                <i class="bi bi-lock-fill me-1"></i>Username SAdmin adalah akun induk sistem (terkunci) dan tidak dapat diganti.
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Password Pengguna</label>
                            <div class="input-group">
                                <input type="password" name="password" id="edit_u_password" class="form-control" required placeholder="Password Pengguna">
                                <button type="button" class="btn btn-outline-secondary btn-toggle-password" onclick="togglePass('edit_u_password', this)" title="Lihat / Sembunyikan Password" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted" style="font-size: 0.74rem;">Klik ikon mata untuk melihat password tersimpan, atau ketik langsung jika ingin mengganti.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small d-block">Hak Akses / Peran</label>
                            <input type="hidden" name="hak_akses[]" value="Super Admin" id="hidden_sa_role" disabled>
                            <div class="card p-3 bg-light border">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Super Admin" id="e_role_sa">
                                            <label class="form-check-label fw-bold text-danger" for="e_role_sa">Super Admin</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Guru" id="e_role_guru">
                                            <label class="form-check-label fw-semibold" for="e_role_guru">Guru</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Wali Kelas" id="e_role_wk">
                                            <label class="form-check-label fw-semibold" for="e_role_wk">Wali Kelas</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Guru Wali" id="e_role_gw">
                                            <label class="form-check-label fw-semibold" for="e_role_gw">Guru Wali</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Pembina Ekstra" id="e_role_ek">
                                            <label class="form-check-label fw-semibold" for="e_role_ek">Pembina Ekstra</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hak_akses[]" value="Pelihat" id="e_role_vi">
                                            <label class="form-check-label text-muted" for="e_role_vi">Pelihat</label>
                                        </div>
                                    </div>
                                </div>
                                <small id="sa_role_locked_msg" class="text-danger fw-semibold d-none mt-2">
                                    <i class="bi bi-shield-lock-fill me-1"></i>Hak akses Super Admin untuk akun default SAdmin terkunci dan tidak dapat diganti.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Perbarui Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import User -->
<div class="modal fade" id="modalImportUser" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Import Data User (Excel .xls / CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="user-management.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Perhatian: Data akun lama (kecuali akun default SAdmin) akan dihapus dan digantikan data yang diimport.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pilih File Excel (.xlsx / .xls) atau CSV</label>
                        <input type="file" name="file_import" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <small class="text-muted d-block mb-2">Format kolom: No, Username, Password, Hak Akses, Nama, NIP</small>
                    <a href="user-management.php?template=xlsx" class="btn btn-outline-success btn-sm rounded-pill px-3">
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

<form id="formDeleteUser" method="POST" action="user-management.php" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_u_id">
</form>

<script>
function autoFillGuruUser(sel) {
    if (!sel.value) return;
    const g = JSON.parse(sel.value);
    document.getElementById('add_u_nama').value = g.nama_guru || '';
    document.getElementById('add_u_nip').value = g.nip || '';
    
    // Generate username default dari nama
    const cleanName = (g.nama_guru || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    document.getElementById('add_u_username').value = cleanName.substring(0, 10);

    // Auto checklist role yang sesuai
    if (g.sebagai_guru_wali === 'Ya' || g.sebagai_guru_wali === 'ya') {
        document.getElementById('role_gw').checked = true;
    }
    if (g.sebagai_wali_kelas && g.sebagai_wali_kelas !== '-') {
        document.getElementById('role_wk').checked = true;
    }
}

function editUser(u) {
    document.getElementById('edit_u_id').value = u.id;
    document.getElementById('edit_u_nama').value = u.nama;
    document.getElementById('edit_u_nip').value = u.nip || '';

    const unInput = document.getElementById('edit_u_username');
    unInput.value = u.username;

    // Isi password yang tersimpan ke field password edit
    const editPassInput = document.getElementById('edit_u_password');
    if (editPassInput) {
        editPassInput.value = u.password || '';
        editPassInput.type = 'password';
        const editPassIcon = editPassInput.parentElement.querySelector('.btn-toggle-password i');
        if (editPassIcon) {
            editPassIcon.classList.remove('bi-eye-slash');
            editPassIcon.classList.add('bi-eye');
        }
    }

    const isSAdmin = (u.username === 'SAdmin');

    const sadminNotice = document.getElementById('sadmin_username_locked_msg');
    if (isSAdmin) {
        unInput.setAttribute('readonly', 'true');
        unInput.classList.add('bg-light');
        if (sadminNotice) sadminNotice.classList.remove('d-none');
    } else {
        unInput.removeAttribute('readonly');
        unInput.classList.remove('bg-light');
        if (sadminNotice) sadminNotice.classList.add('d-none');
    }

    const roles = (u.hak_akses || '').split(',').map(r => r.trim());
    const saCheckbox = document.getElementById('e_role_sa');
    const saNotice = document.getElementById('sa_role_locked_msg');
    const hiddenSa = document.getElementById('hidden_sa_role');

    document.getElementById('e_role_guru').checked = roles.includes('Guru');
    document.getElementById('e_role_wk').checked = roles.includes('Wali Kelas');
    document.getElementById('e_role_gw').checked = roles.includes('Guru Wali');
    document.getElementById('e_role_ek').checked = roles.includes('Pembina Ekstra');
    document.getElementById('e_role_vi').checked = roles.includes('Pelihat');

    // Khusus untuk hak akses Super Admin yang usernamenya "SAdmin" (default) yang terkunci.
    // Hak akses Super Admin lainnya dapat diganti / diubah secara bebas.
    if (isSAdmin) {
        saCheckbox.checked = true;
        saCheckbox.disabled = true;
        hiddenSa.disabled = false;
        if (saNotice) saNotice.classList.remove('d-none');
    } else {
        saCheckbox.checked = roles.includes('Super Admin');
        saCheckbox.disabled = false;
        hiddenSa.disabled = true;
        if (saNotice) saNotice.classList.add('d-none');
    }

    new bootstrap.Modal(document.getElementById('modalEditUser')).show();
}

function deleteUser(id, username) {
    Swal.fire({
        title: 'Hapus Akun Pengguna?',
        text: 'Apakah Anda yakin ingin menghapus akun ' + username + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_u_id').value = id;
            document.getElementById('formDeleteUser').submit();
        }
    });
}

function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    input.focus();
}

function toggleTablePassword(id, realPass, btn) {
    const span = document.getElementById('table_pwd_' + id);
    const icon = btn.querySelector('i');
    if (!span) return;
    if (span.innerText === '••••••••') {
        span.innerText = realPass;
        span.classList.remove('text-secondary');
        span.classList.add('text-primary', 'fw-bold');
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    } else {
        span.innerText = '••••••••';
        span.classList.remove('text-primary', 'fw-bold');
        span.classList.add('text-secondary');
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
}

// Reset visibility password di modal tambah jika ditutup
const modalAddUserEl = document.getElementById('modalAddUser');
if (modalAddUserEl) {
    modalAddUserEl.addEventListener('hidden.bs.modal', function () {
        const addPassInput = document.getElementById('add_u_password');
        if (addPassInput) {
            addPassInput.type = 'password';
            const addPassIcon = addPassInput.parentElement.querySelector('.btn-toggle-password i');
            if (addPassIcon) {
                addPassIcon.classList.remove('bi-eye-slash');
                addPassIcon.classList.add('bi-eye');
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
