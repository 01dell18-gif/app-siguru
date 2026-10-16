<?php
// views/header.php - Header & Auto-Hiding Navbar SiGuru
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

requireAuth();
$user = currentUser();
$settings = getSettings();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - SIGURU</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons & FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Style -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<!-- Auto-Hiding & Fluid Top Navbar Minimalis Modern -->
<header class="main-header d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="d-flex align-items-center gap-2 gap-md-3">
        <!-- Tombol Toggle Sidebar -->
        <button id="btnToggleSidebar" class="btn btn-light btn-sm border shadow-xs d-flex align-items-center justify-content-center text-dark" style="width: 32px; height: 32px; border-radius: 6px;" title="Sembunyikan / Tampilkan Menu">
            <i class="bi bi-layout-sidebar-inset fs-6"></i>
        </button>

        <div class="d-flex align-items-center gap-2">
            <img src="<?= htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png') ?>" alt="Logo" class="rounded" style="height: 28px; width: 28px; object-fit: contain;">
            <div>
                <span class="d-none d-md-block fw-bold text-dark mb-0" style="font-size: 0.88rem; line-height: 1.15; letter-spacing: -0.01em;">
                    <?= htmlspecialchars($settings['nama_sekolah'] ?? 'SMK FYJ Channel Ferryxz') ?>
                </span>
                <span class="text-secondary small" style="line-height: 1.1; font-size: 0.74rem;">
                    SIGURU &bull; <span class="fw-semibold text-dark"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
                </span>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <!-- Widget Tanggal & Waktu Real-Time Sesuai Perangkat (Proporsional & Nyaman Dilihat) -->
        <div class="d-none d-md-flex header-clock-widget" title="Tanggal & Waktu Perangkat Anda">
            <div class="clock-row">
                <i class="bi bi-calendar3"></i>
                <span id="headerDeviceDate" class="clock-date"></span>
            </div>
            <div class="clock-row" style="margin-top: 1px;">
                <i class="bi bi-clock"></i>
                <span id="headerDeviceTime" class="clock-time"></span>
            </div>
        </div>

        <!-- Tombol Refresh Halaman (Ikon Saja) -->
        <button type="button" id="btnRefreshApp" class="btn btn-outline-secondary btn-sm shadow-xs d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 6px; padding: 0;" title="Muat Ulang Halaman (Refresh)">
            <i class="bi bi-arrow-clockwise fs-6" id="iconRefresh"></i>
        </button>

        <!-- Tombol Hapus Cache (Ikon Saja) -->
        <button type="button" id="btnClearCache" class="btn btn-outline-danger btn-sm shadow-xs d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 6px; padding: 0;" title="Hapus Cache Browser & Memori">
            <i class="bi bi-trash3 fs-6" id="iconClearCache"></i>
        </button>

        <!-- User Role Avatar & Dropdown (Ikon Avatar Saja) -->
        <div class="dropdown">
            <button class="btn btn-light btn-sm border px-2 py-1 d-flex align-items-center gap-1.5 dropdown-toggle shadow-xs" style="height: 32px; border-radius: 6px;" data-bs-toggle="dropdown" title="Akun: <?= htmlspecialchars($user['nama']) ?> (<?= htmlspecialchars($user['hak_akses']) ?>)">
                <?php if (!empty($user['foto'])): ?>
                    <img src="<?= htmlspecialchars(formatPhotoUrl($user['foto'])) ?>" alt="Avatar" class="rounded-circle" style="width: 22px; height: 22px; object-fit: cover; border: 1px solid #cbd5e1;">
                <?php else: ?>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 22px; height: 22px; font-size: 0.72rem;">
                        <?= strtoupper(substr($user['nama'] ?: 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border rounded-3 mt-1 py-1" style="font-size: 0.82rem;">
                <li class="px-3 py-2 border-bottom bg-light">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <?php if (!empty($user['foto'])): ?>
                            <img src="<?= htmlspecialchars(formatPhotoUrl($user['foto'])) ?>" alt="Avatar" class="rounded-circle shadow-xs" style="width: 38px; height: 38px; object-fit: cover; border: 1px solid #cbd5e1;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-xs" style="width: 38px; height: 38px; font-size: 1.1rem;">
                                <?= strtoupper(substr($user['nama'] ?: 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="overflow-hidden">
                            <div class="fw-bold text-dark text-truncate" style="max-width: 170px;"><?= htmlspecialchars($user['nama']) ?></div>
                            <div class="small text-muted font-monospace" style="font-size: 0.74rem;">NIP: <?= htmlspecialchars($user['nip'] ?: '-') ?></div>
                        </div>
                    </div>
                    <div class="badge bg-primary-subtle text-primary mt-1"><?= htmlspecialchars($user['hak_akses']) ?></div>
                </li>
                <li>
                    <a class="dropdown-item py-1.5" href="profil.php">
                        <i class="bi bi-person-gear me-2 text-primary"></i>Edit Profil Saya
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-1.5" href="index.php" target="_blank">
                        <i class="bi bi-house-door me-2 text-primary"></i>Halaman Publik (Landing Page)
                    </a>
                </li>
                <?php if (isSuperAdmin()): ?>
                <li><a class="dropdown-item py-1.5" href="pengaturan.php"><i class="bi bi-gear me-2 text-primary"></i>Pengaturan Sistem</a></li>
                <li><a class="dropdown-item py-1.5" href="user-management.php"><i class="bi bi-people me-2 text-primary"></i>Manajemen User</a></li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item py-1.5 text-warning-emphasis" href="#" onclick="document.getElementById('btnClearCache').click(); return false;">
                        <i class="bi bi-arrow-repeat me-2 text-warning"></i>Bersihkan Cache Aplikasi
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item text-danger py-1.5" href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
