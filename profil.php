<?php
// profil.php - Manajemen & Edit Profil Pengguna Sesuai Hak Akses & Upload Foto Profil
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();

$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];

// Pastikan kolom 'foto' ada pada tabel data_user (Auto Migration)
try {
    $pdo->query("SELECT foto FROM data_user LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE data_user ADD COLUMN foto TEXT");
    } catch (Exception $ex) {}
}

// Ambil data user terkini dari database
$stmt = $pdo->prepare("SELECT * FROM data_user WHERE id = ?");
$stmt->execute([$userId]);
$userProfile = $stmt->fetch();

if (!$userProfile) {
    setFlash('danger', 'Akun pengguna tidak ditemukan.');
    header("Location: logout.php");
    exit;
}

/**
 * Fungsi bantu internal untuk validasi dan simpan foto profil
 */
function handleSaveUserProfilePhoto($fileInputName, $currentPhoto, $pdo, $userId) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Tidak ada file yang diunggah
    }

    $file = $_FILES[$fileInputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Terjadi kesalahan saat mengunggah berkas foto (Kode: ' . $file['error'] . ').'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExts)) {
        return ['success' => false, 'message' => 'Format file foto tidak didukung. Harap gunakan format JPG, JPEG, PNG, WEBP, atau GIF.'];
    }

    // Validasi MIME Type menggunakan finfo jika tersedia
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes)) {
            return ['success' => false, 'message' => 'Berkas yang diunggah bukan gambar valid.'];
        }
    }

    // Maksimal 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal ukuran foto adalah 5 MB.'];
    }

    $uploadDir = __DIR__ . '/uploads/photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Hapus foto lama jika tersimpan lokal
        if (!empty($currentPhoto) && strpos($currentPhoto, 'uploads/photos/') === 0 && file_exists(__DIR__ . '/' . $currentPhoto)) {
            @unlink(__DIR__ . '/' . $currentPhoto);
        }

        $relPath = 'uploads/photos/' . $fileName;
        $stmtUpFoto = $pdo->prepare("UPDATE data_user SET foto = ? WHERE id = ?");
        $stmtUpFoto->execute([$relPath, $userId]);

        $_SESSION['foto'] = $relPath;
        return ['success' => true, 'path' => $relPath];
    }

    return ['success' => false, 'message' => 'Gagal menyimpan berkas ke server. Pastikan izin folder uploads/photos aktif.'];
}

// Proses Update Profil, Password, & Foto
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // 1. Upload Foto Profil Cepat (via Modal atau Tombol Kamera)
    if ($action === 'upload_foto') {
        $res = handleSaveUserProfilePhoto('foto_avatar', $userProfile['foto'] ?? '', $pdo, $userId);
        if ($res === null) {
            setFlash('danger', 'Silakan pilih berkas foto terlebih dahulu sebelum menyimpan.');
        } elseif (!$res['success']) {
            setFlash('danger', $res['message']);
        } else {
            setFlash('success', 'Foto profil berhasil diperbarui!');
        }
        header("Location: profil.php");
        exit;
    }

    // 2. Hapus Foto Profil (Kembali ke Inisial Huruf)
    if ($action === 'delete_foto') {
        $currPhoto = $userProfile['foto'] ?? '';
        if (!empty($currPhoto) && strpos($currPhoto, 'uploads/photos/') === 0 && file_exists(__DIR__ . '/' . $currPhoto)) {
            @unlink(__DIR__ . '/' . $currPhoto);
        }
        $stmtDel = $pdo->prepare("UPDATE data_user SET foto = NULL WHERE id = ?");
        $stmtDel->execute([$userId]);
        $_SESSION['foto'] = '';

        setFlash('success', 'Foto profil berhasil dihapus. Sistem akan menggunakan inisial nama Anda.');
        header("Location: profil.php");
        exit;
    }

    // 3. Update Data Profil (Nama, NIP, Username, dan Opsional Foto)
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

        // Simpan update teks ke database
        $oldNama = $userProfile['nama'];
        $stmtUp = $pdo->prepare("UPDATE data_user SET username = ?, nama = ?, nip = ? WHERE id = ?");
        $stmtUp->execute([$username, $nama, $nip, $userId]);

        // Jika ada di tabel data_guru, sinkronkan nama dan NIP agar konsisten di jadwal/laporan
        if (!empty($oldNama)) {
            $stmtGuru = $pdo->prepare("UPDATE data_guru SET nama_guru = ?, nip = ? WHERE nama_guru = ?");
            $stmtGuru->execute([$nama, $nip, $oldNama]);
        }

        // Cek jika pengguna juga memilih foto baru pada form identitas diri
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
            $res = handleSaveUserProfilePhoto('foto_profil', $userProfile['foto'] ?? '', $pdo, $userId);
            if ($res && !$res['success']) {
                setFlash('warning', 'Identitas diri tersimpan, namun foto profil gagal diunggah: ' . $res['message']);
                header("Location: profil.php");
                exit;
            }
        }

        // Perbarui sesi aktif seketika
        $_SESSION['username'] = $username;
        $_SESSION['nama'] = $nama;
        $_SESSION['nip'] = $nip;

        setFlash('success', 'Data profil dan identitas Anda berhasil diperbarui!');
        header("Location: profil.php");
        exit;
    }

    // 4. Ganti Password Akun
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

        // Simpan password baru
        $stmtPass = $pdo->prepare("UPDATE data_user SET password = ? WHERE id = ?");
        $stmtPass->execute([$passwordBaru, $userId]);

        setFlash('success', 'Kata sandi berhasil diperbarui! Silakan gunakan sandi baru untuk login berikutnya.');
        header("Location: profil.php");
        exit;
    }
}

// Ambil kembali data user setelah kemungkinan perubahan foto/profil
$stmt = $pdo->prepare("SELECT * FROM data_user WHERE id = ?");
$stmt->execute([$userId]);
$userProfile = $stmt->fetch();

$pageTitle = 'Edit Profil Pengguna';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

// Parse hak akses untuk ditampilkan dalam bentuk label
$roles = array_map('trim', explode(',', $userProfile['hak_akses']));
$isSuper = in_array('Super Admin', $roles);
$isSAdminUser = ($userProfile['username'] === 'SAdmin');
$hasCustomPhoto = !empty($userProfile['foto']);
?>

<style>
.profile-avatar-container {
    position: relative;
    width: 96px;
    height: 96px;
    margin: 0 auto 1rem auto;
}
.profile-avatar-img {
    width: 96px;
    height: 96px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #ffffff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.profile-avatar-initial {
    width: 96px;
    height: 96px;
    font-size: 2.3rem;
    font-weight: 700;
    border-radius: 50%;
    border: 3px solid #ffffff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}
.profile-avatar-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #2563eb;
    color: #ffffff;
    border: 2px solid #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    cursor: pointer;
    transition: all 0.2s ease;
}
.profile-avatar-badge:hover {
    background: #1d4ed8;
    transform: scale(1.1);
}
.photo-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    background-color: #f8fafc;
    cursor: pointer;
    transition: all 0.2s ease;
}
.photo-dropzone:hover {
    border-color: #2563eb;
    background-color: #eff6ff;
}
</style>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-person-circle me-2 text-primary"></i>Profil Pengguna
            </h4>
            <p class="text-muted small mb-0">Kelola informasi akun, identitas pengajar, foto profil, serta keamanan kata sandi Anda sesuai hak akses aktif.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Kartu Ringkasan Akun, Avatar Foto & Hak Akses -->
        <div class="col-lg-4">
            <div class="card card-table border-0 shadow-sm p-4 text-center mb-4">
                
                <!-- Avatar Foto Profil dengan Tombol Aksi Cepat -->
                <div class="profile-avatar-container">
                    <?php if ($hasCustomPhoto): ?>
                        <img src="<?= htmlspecialchars(formatPhotoUrl($userProfile['foto'])) ?>" alt="Foto Profil" class="profile-avatar-img">
                    <?php else: ?>
                        <div class="profile-avatar-initial bg-primary-subtle text-primary">
                            <?= strtoupper(substr($userProfile['nama'] ?: 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="profile-avatar-badge" data-bs-toggle="modal" data-bs-target="#modalUploadFoto" title="Klik untuk Unggah / Ganti Foto Profil">
                        <i class="bi bi-camera-fill small"></i>
                    </button>
                </div>

                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($userProfile['nama']) ?></h5>
                <p class="text-muted small mb-2 font-monospace">@<?= htmlspecialchars($userProfile['username']) ?></p>
                
                <!-- Badge Hak Akses / Peran -->
                <div class="d-flex flex-wrap justify-content-center gap-1 mb-3">
                    <?php foreach ($roles as $r): ?>
                        <span class="badge <?= $r === 'Super Admin' ? 'bg-danger' : 'bg-primary' ?> px-2 py-1">
                            <?= htmlspecialchars($r) ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <!-- Tombol Manajemen Foto Profil -->
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalUploadFoto">
                        <i class="bi bi-camera"></i>
                        <span><?= $hasCustomPhoto ? 'Ganti Foto' : 'Unggah Foto' ?></span>
                    </button>
                    <?php if ($hasCustomPhoto): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-xs d-inline-flex align-items-center gap-1.5" onclick="confirmHapusFotoProfil()">
                            <i class="bi bi-trash3"></i>
                            <span>Hapus</span>
                        </button>
                    <?php endif; ?>
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
                <form method="POST" action="profil.php" enctype="multipart/form-data">
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

                        <!-- Menu Upload Foto Profil di Form Identitas Diri -->
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary d-flex align-items-center justify-content-between mb-1">
                                <span><i class="bi bi-image me-1.5 text-primary"></i>Foto Profil Pengguna</span>
                                <?php if ($hasCustomPhoto): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.72rem;">
                                        <i class="bi bi-check-circle-fill me-1"></i>Foto Kustom Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.72rem;">Inisial Nama</span>
                                <?php endif; ?>
                            </label>
                            <div class="p-3 bg-light rounded border d-flex flex-wrap align-items-center gap-3">
                                <div class="rounded-circle overflow-hidden shadow-xs border bg-white d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; flex-shrink: 0;">
                                    <?php if ($hasCustomPhoto): ?>
                                        <img id="formFotoPreview" src="<?= htmlspecialchars(formatPhotoUrl($userProfile['foto'])) ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div id="formFotoInitial" class="fw-bold text-primary" style="font-size: 1.3rem;">
                                            <?= strtoupper(substr($userProfile['nama'] ?: 'U', 0, 1)) ?>
                                        </div>
                                        <img id="formFotoPreview" src="" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1" style="min-width: 220px;">
                                    <input type="file" name="foto_profil" id="inputFotoProfilForm" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImageFile(this, 'formFotoPreview', 'formFotoInitial')">
                                    <div class="form-text small text-muted mb-0" style="font-size: 0.74rem;">
                                        Pilih foto (JPG, PNG, WEBP, GIF, maks. 5 MB) untuk memperbarui foto profil Anda.
                                    </div>
                                </div>
                            </div>
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

<!-- ============================================================
     MODAL POPUP: UNGGAH FOTO PROFIL CEPAT & INTERAKTIF
     ============================================================ -->
<div class="modal fade" id="modalUploadFoto" tabindex="-1" aria-labelledby="modalUploadFotoTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalUploadFotoTitle">
                    <i class="bi bi-camera-fill text-primary"></i>
                    <span>Unggah Foto Profil</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            
            <form method="POST" action="profil.php" enctype="multipart/form-data" id="formUploadFotoModal">
                <input type="hidden" name="action" value="upload_foto">
                
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Pilih foto formal atau foto pengajar Anda. Foto ini akan tampil pada avatar akun, navbar atas, serta navigasi sistem.
                    </p>

                    <!-- Preview Foto Modal -->
                    <div class="text-center mb-4">
                        <div class="mx-auto rounded-circle overflow-hidden shadow-sm border bg-light d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; border-width: 3px !important; border-color: #2563eb !important;">
                            <?php if ($hasCustomPhoto): ?>
                                <img id="modalPhotoPreview" src="<?= htmlspecialchars(formatPhotoUrl($userProfile['foto'])) ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                <div id="modalPhotoInitial" class="fw-bold text-primary" style="font-size: 2.8rem; display: none;">
                                    <?= strtoupper(substr($userProfile['nama'] ?: 'U', 0, 1)) ?>
                                </div>
                            <?php else: ?>
                                <div id="modalPhotoInitial" class="fw-bold text-primary" style="font-size: 2.8rem;">
                                    <?= strtoupper(substr($userProfile['nama'] ?: 'U', 0, 1)) ?>
                                </div>
                                <img id="modalPhotoPreview" src="" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            <?php endif; ?>
                        </div>
                        <div id="modalPhotoInfo" class="small text-muted mt-2" style="font-size: 0.78rem;">
                            <?= $hasCustomPhoto ? 'Foto saat ini' : 'Belum ada foto profil kustom' ?>
                        </div>
                    </div>

                    <!-- Area Dropzone / File Picker -->
                    <label class="photo-dropzone w-100 d-block mb-3" for="inputModalAvatar">
                        <i class="bi bi-cloud-arrow-up-fill fs-2 text-primary d-block mb-1"></i>
                        <span class="fw-semibold text-dark d-block">Klik untuk Telusuri / Pilih Berkas Foto</span>
                        <span class="text-muted small" style="font-size: 0.76rem;">Format yang didukung: JPG, JPEG, PNG, WEBP, GIF (Maks. 5 MB)</span>
                        <input type="file" name="foto_avatar" id="inputModalAvatar" class="d-none" accept="image/jpeg,image/png,image/webp,image/gif" required onchange="previewImageFile(this, 'modalPhotoPreview', 'modalPhotoInitial', 'modalPhotoInfo')">
                    </label>

                    <div class="alert alert-info py-2 px-3 rounded-3 small d-flex align-items-start gap-2 mb-0" style="font-size: 0.78rem;">
                        <i class="bi bi-info-circle-fill text-primary flex-shrink-0 mt-0.5"></i>
                        <div>
                            Untuk hasil tampilan terbaik dan proporsional, gunakan foto dengan rasio aspek <strong>1:1 (persegi)</strong> dengan orientasi tegak (potret).
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                    <div>
                        <?php if ($hasCustomPhoto): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="confirmHapusFotoProfil()">
                                <i class="bi bi-trash3 me-1"></i> Hapus Foto
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-semibold shadow-sm">
                            <i class="bi bi-check2 me-1"></i> Simpan Foto
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form Tersembunyi untuk Hapus Foto -->
<form id="formHapusFoto" method="POST" action="profil.php" style="display:none;">
    <input type="hidden" name="action" value="delete_foto">
</form>

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

// Fungsi Live Preview Gambar saat File Dipilih
function previewImageFile(input, imgId, initialId, infoId) {
    if (input.files && input.files[0]) {
        const file = input.files[0];

        // Validasi ukuran sisi client (5 MB)
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'Ukuran Berkas Terlalu Besar',
                text: 'Maksimal ukuran foto adalah 5 MB. Harap pilih foto lain yang lebih kecil.',
                confirmButtonColor: '#2563eb'
            });
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById(imgId);
            const initialDiv = document.getElementById(initialId);
            
            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            }
            if (initialDiv) {
                initialDiv.style.display = 'none';
            }
            if (infoId) {
                const infoElem = document.getElementById(infoId);
                if (infoElem) {
                    infoElem.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB) siap disimpan</span>';
                }
            }
        };
        reader.readAsDataURL(file);
    }
}

// Dialog Konfirmasi Hapus Foto Profil
function confirmHapusFotoProfil() {
    Swal.fire({
        title: 'Hapus Foto Profil?',
        text: 'Foto profil Anda akan dihapus dan digantikan kembali dengan inisial nama.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus Foto',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formHapusFoto').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
