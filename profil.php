<?php
// profil.php - Manajemen & Edit Profil Pengguna Sesuai Hak Akses
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();

$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];

// Ambil data user terkini dari database
$stmt = $pdo->prepare("SELECT * FROM data_user WHERE id = ?");
$stmt->execute([$userId]);
$userProfile = $stmt->fetch();

if (!$userProfile) {
    setFlash('danger', 'Akun pengguna tidak ditemukan.');
    header("Location: logout.php");
    exit;
}

// Proses Update Profil & Password
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // 1. Update Data Profil (Nama, NIP, Username)
    if ($action === 'update_profile') {
        $nama = trim($_POST['nama'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($nama)) {
            setFlash('danger', 'Nama lengkap wajib diisi.');
            header("Location: profil.php");
            exit;
        }

        // Ketentuan: Akun sistem 'SAdmin' tidak dapat diubah username-nya
        if ($userProfile['username'] === 'SAdmin') {
            $username = 'SAdmin';
        } else {
            // Validasi format username
            if (empty($username)) {
                $username = $userProfile['username'];
            }
            if (strtolower($username) === 'sadmin') {
                setFlash('danger', 'Username "SAdmin" dilindungi sistem dan tidak dapat digunakan.');
                header("Location: profil.php");
                exit;
            }

            // Cek apakah username baru sudah dipakai akun lain
            if ($username !== $userProfile['username']) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM data_user WHERE username = ? AND id != ?");
                $chk->execute([$username, $userId]);
                if ($chk->fetchColumn() > 0) {
                    setFlash('danger', 'Username "' . htmlspecialchars($username) . '" sudah digunakan akun lain. Silakan pilih username lain.');
                    header("Location: profil.php");
                    exit;
                }
            }
        }

        // Simpan update ke database
        $oldNama = $userProfile['nama'];
        $stmtUp = $pdo->prepare("UPDATE data_user SET username = ?, nama = ?, nip = ? WHERE id = ?");
        $stmtUp->execute([$username, $nama, $nip, $userId]);

        // Jika ada di tabel data_guru, sinkronkan nama dan NIP agar konsisten di jadwal/laporan
        if (!empty($oldNama)) {
            $stmtGuru = $pdo->prepare("UPDATE data_guru SET nama_guru = ?, nip = ? WHERE nama_guru = ?");
            $stmtGuru->execute([$nama, $nip, $oldNama]);
        }

        // Perbarui sesi aktif seketika
        $_SESSION['username'] = $username;
        $_SESSION['nama'] = $nama;
        $_SESSION['nip'] = $nip;

        setFlash('success', 'Data profil Anda berhasil diperbarui!');
        header("Location: profil.php");
        exit;
    }

    // 2. Ganti Password Akun
    if ($action === 'update_password') {
        $passwordLama = trim($_POST['password_lama'] ?? '');
        $passwordBaru = trim($_POST['password_baru'] ?? '');
        $passwordKonf = trim($_POST['password_konfirmasi'] ?? '');

        if (empty($passwordLama) || empty($passwordBaru) || empty($passwordKonf)) {
            setFlash('danger', 'Seluruh kolom kata sandi wajib diisi untuk mengganti password.');
            header("Location: profil.php");
            exit;
        }

        // Verifikasi password saat ini
        $isOldValid = password_verify($passwordLama, $userProfile['password']) || ($passwordLama === $userProfile['password']);
        if (!$isOldValid) {
            setFlash('danger', 'Password lama yang Anda masukkan tidak sesuai.');
            header("Location: profil.php");
            exit;
        }

        // Verifikasi konfirmasi password baru
        if ($passwordBaru !== $passwordKonf) {
            setFlash('danger', 'Konfirmasi password baru tidak cocok dengan password baru.');
            header("Location: profil.php");
            exit;
        }

        if (strlen($passwordBaru) < 5) {
            setFlash('danger', 'Password baru minimal terdiri dari 5 karakter.');
            header("Location: profil.php");
            exit;
        }

        // Simpan password baru agar dapat dilihat admin via tombol mata jika lupa
        $stmtPass = $pdo->prepare("UPDATE data_user SET password = ? WHERE id = ?");
        $stmtPass->execute([$passwordBaru, $userId]);

        setFlash('success', 'Kata sandi berhasil diperbarui! Silakan gunakan sandi baru untuk login berikutnya.');
        header("Location: profil.php");
        exit;
    }
}

$pageTitle = 'Edit Profil Pengguna';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

// Parse hak akses untuk ditampilkan dalam bentuk label
$roles = array_map('trim', explode(',', $userProfile['hak_akses']));
$isSuper = in_array('Super Admin', $roles);
$isSAdminUser = ($userProfile['username'] === 'SAdmin');
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-person-circle me-2 text-primary"></i>Profil Pengguna
            </h4>
            <p class="text-muted small mb-0">Kelola informasi akun, identitas pengajar, serta keamanan kata sandi Anda sesuai hak akses aktif.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Kartu Ringkasan Akun & Hak Akses -->
        <div class="col-lg-4">
            <div class="card card-table border-0 shadow-sm p-4 text-center mb-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary fw-bold shadow-sm" style="width: 84px; height: 84px; font-size: 2rem; border: 3px solid #fff;">
                    <?= strtoupper(substr($userProfile['nama'] ?: 'U', 0, 1)) ?>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($userProfile['nama']) ?></h5>
                <p class="text-muted small mb-2 font-monospace">@<?= htmlspecialchars($userProfile['username']) ?></p>
                <div class="d-flex flex-wrap justify-content-center gap-1 mb-3">
                    <?php foreach ($roles as $r): ?>
                        <span class="badge <?= $r === 'Super Admin' ? 'bg-danger' : 'bg-primary' ?> px-2 py-1">
                            <?= htmlspecialchars($r) ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <div class="border-top pt-3 text-start small">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-secondary">NIP:</span>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($userProfile['nip'] ?: '-') ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-secondary">Status Akun:</span>
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-secondary">Tipe User:</span>
                        <span class="fw-semibold <?= $isSAdminUser ? 'text-danger' : 'text-primary' ?>">
                            <?= $isSAdminUser ? 'Akun Induk Sistem' : ($isSuper ? 'Administrator' : 'Pengguna Sekolah') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Kartu Hak Akses & Fitur yang Aktif -->
            <div class="card card-table border-0 shadow-sm p-3">
                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-shield-check me-2 text-success"></i>Modul & Hak Akses Anda
                </h6>
                <p class="text-muted small mb-3" style="font-size: 0.8rem;">
                    Berdasarkan hak akses Anda, berikut modul sistem yang dapat Anda gunakan:
                </p>
                <ul class="list-group list-group-flush small" style="font-size: 0.82rem;">
                    <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-2">
                        <span><i class="bi bi-calendar-check text-primary me-2"></i>Presensi / Absensi Siswa</span>
                        <?php if (hasAccess('form_absen')): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle text-muted"></i>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-2">
                        <span><i class="bi bi-journal-bookmark text-primary me-2"></i>Jurnal Mengajar Guru</span>
                        <?php if (hasAccess('form_jurnal')): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle text-muted"></i>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-2">
                        <span><i class="bi bi-person-workspace text-primary me-2"></i>Agenda Wali Kelas</span>
                        <?php if (hasAccess('form_wali')): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle text-muted"></i>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-2">
                        <span><i class="bi bi-chat-heart text-primary me-2"></i>Bimbingan Siswa (Guru Wali)</span>
                        <?php if (hasAccess('form_guruwali')): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle text-muted"></i>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-2">
                        <span><i class="bi bi-trophy text-primary me-2"></i>Kegiatan Ekstrakurikuler</span>
                        <?php if (hasAccess('form_ekstra')): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle text-muted"></i>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-2">
                        <span><i class="bi bi-printer text-primary me-2"></i>Preview / Cetak Laporan PDF</span>
                        <i class="bi bi-check-circle-fill text-success"></i>
                    </li>
                    <?php if ($isSuper): ?>
                    <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-2 bg-light rounded px-2">
                        <span class="fw-semibold text-danger"><i class="bi bi-gear-fill text-danger me-2"></i>Pengaturan & Kelola User</span>
                        <i class="bi bi-check-circle-fill text-danger"></i>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Kolom Kanan: Formulir Edit Profil & Ganti Password -->
        <div class="col-lg-8">
            <!-- 1. Formulir Data Diri & Identitas -->
            <div class="card card-table border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="bi bi-person-lines-fill me-2 text-primary"></i>Perbarui Identitas Diri
                </h5>
                <form method="POST" action="profil.php">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Username Login</label>
                            <?php if ($isSAdminUser): ?>
                                <input type="text" name="username" class="form-control bg-light text-muted fw-bold" value="SAdmin" readonly>
                                <small class="text-danger" style="font-size: 0.75rem;">
                                    <i class="bi bi-lock-fill me-1"></i>Akun Induk Sistem: Username tidak dapat diganti.
                                </small>
                            <?php else: ?>
                                <input type="text" name="username" class="form-control fw-bold" value="<?= htmlspecialchars($userProfile['username']) ?>" required>
                                <small class="text-muted" style="font-size: 0.75rem;">Digunakan untuk masuk ke dalam aplikasi.</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">NIP (Nomor Induk Pegawai)</label>
                            <input type="text" name="nip" class="form-control font-monospace" value="<?= htmlspecialchars($userProfile['nip'] ?? '') ?>" placeholder="198502152010011002">
                            <small class="text-muted" style="font-size: 0.75rem;">Isikan tanda strip (-) jika tidak memiliki NIP.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Nama Lengkap & Gelar</label>
                            <input type="text" name="nama" class="form-control fw-semibold" value="<?= htmlspecialchars($userProfile['nama']) ?>" required>
                            <small class="text-muted" style="font-size: 0.75rem;">Nama ini otomatis tercantum pada tanda tangan laporan dan identitas akun.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary d-block">Hak Akses / Peran Akun</label>
                            <div class="p-2 bg-light rounded border d-flex flex-wrap align-items-center gap-2">
                                <?php foreach ($roles as $r): ?>
                                    <span class="badge bg-primary px-3 py-2 fw-semibold">
                                        <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($r) ?>
                                    </span>
                                <?php endforeach; ?>
                                <span class="text-muted small ms-auto" style="font-size: 0.78rem;">
                                    <i class="bi bi-info-circle me-1"></i>Hak akses dikelola oleh Super Admin di Manajemen Pengguna.
                                </span>
                            </div>
                        </div>

                        <div class="col-12 text-end pt-2">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="bi bi-check-circle-fill me-1"></i> Simpan Perubahan Profil
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 2. Formulir Ganti Kata Sandi -->
            <div class="card card-table border-0 shadow-sm p-4">
                <h5 class="fw-bold text-dark mb-1">
                    <i class="bi bi-key-fill me-2 text-warning"></i>Keamanan & Ganti Kata Sandi
                </h5>
                <p class="text-muted small mb-3">Pastikan kata sandi baru Anda kuat dan tidak mudah ditebak oleh orang lain.</p>

                <form method="POST" action="profil.php">
                    <input type="hidden" name="action" value="update_password">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-secondary">Kata Sandi Saat Ini</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-shield-lock text-secondary"></i></span>
                                <input type="password" name="password_lama" id="pass_lama" class="form-control" placeholder="Masukkan password saat ini" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('pass_lama', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Kata Sandi Baru</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-lock text-primary"></i></span>
                                <input type="password" name="password_baru" id="pass_baru" class="form-control" placeholder="Minimal 5 karakter" required minlength="5">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('pass_baru', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Ulangi Kata Sandi Baru</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-check2-circle text-success"></i></span>
                                <input type="password" name="password_konfirmasi" id="pass_konf" class="form-control" placeholder="Ketik ulang password baru" required minlength="5">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('pass_konf', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-12 text-end pt-2">
                            <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="bi bi-shield-fill-check me-1"></i> Perbarui Kata Sandi
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
