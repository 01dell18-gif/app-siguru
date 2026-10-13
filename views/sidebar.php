<?php
// views/sidebar.php - Sidebar Navigasi SiGuru Minimalis & Modern
require_once __DIR__ . '/../config/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$currentUserData = currentUser();

$isMasterActive = in_array($currentPage, ['master-guru.php', 'master-murid.php', 'master-bimbingan.php', 'master-ekstra.php']);
$isFormActive = in_array($currentPage, ['form-absen.php', 'form-jurnal.php', 'form-guruwali.php', 'form-wali.php', 'form-ekstra.php']);
$isLaporanActive = in_array($currentPage, ['laporan-absen.php', 'laporan-jurnal.php', 'laporan-wali.php', 'laporan-guruwali.php', 'laporan-ekstra.php']);
$isPengaturanActive = in_array($currentPage, ['pengaturan.php', 'user-management.php']);
?>
<aside class="main-sidebar d-flex flex-column" id="mainSidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand d-flex justify-content-between align-items-center">
        <a href="dashboard.php" class="text-white text-decoration-none d-flex align-items-center gap-2">
            <div class="sidebar-brand-icon rounded-2 bg-primary d-flex align-items-center justify-content-center text-white" style="width: 32px; height: 32px; font-size: 1rem;">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div>
                <span class="fw-bold tracking-wide d-block text-white" style="font-size: 0.95rem; line-height: 1.15; letter-spacing: 0.5px;">SIGURU</span>
                <span class="text-white-50" style="font-size: 0.65rem; letter-spacing: 0.06em; text-transform: uppercase;">Admin Portal</span>
            </div>
        </a>
        <button type="button" class="btn btn-sm btn-link text-white-50 d-lg-none p-0" id="sidebarCloseBtn" title="Tutup">
            <i class="bi bi-x-lg fs-5"></i>
        </button>
    </div>

    <!-- Menu List -->
    <div class="sidebar-menu flex-grow-1">
        <!-- 1. Menu Dashboard -->
        <a href="dashboard.php" class="nav-link-custom <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <!-- 2. Grup Master Data -->
        <?php if (hasAccess('master_guru') || hasAccess('master_murid')): ?>
        <div class="menu-category">Data Master</div>
        <div class="accordion sidebar-accordion" id="accordionMaster">
            <div class="accordion-item bg-transparent border-0">
                <button class="accordion-button <?= $isMasterActive ? '' : 'collapsed' ?> <?= $isMasterActive ? 'active-parent' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMaster">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-database-fill text-info"></i>
                        <span>Master Data</span>
                    </span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div id="collapseMaster" class="accordion-collapse collapse <?= $isMasterActive ? 'show' : '' ?>" data-bs-parent="#accordionMaster">
                    <div class="accordion-body">
                        <a href="master-guru.php" class="nav-link-custom <?= $currentPage === 'master-guru.php' ? 'active' : '' ?>">
                            <i class="bi bi-person-badge"></i> <span>Data Guru</span>
                        </a>
                        <a href="master-murid.php" class="nav-link-custom <?= $currentPage === 'master-murid.php' ? 'active' : '' ?>">
                            <i class="bi bi-people"></i> <span>Data Siswa</span>
                        </a>
                        <a href="master-bimbingan.php" class="nav-link-custom <?= $currentPage === 'master-bimbingan.php' ? 'active' : '' ?>">
                            <i class="bi bi-person-heart"></i> <span>Data Siswa Bimbingan</span>
                        </a>
                        <a href="master-ekstra.php" class="nav-link-custom <?= $currentPage === 'master-ekstra.php' ? 'active' : '' ?>">
                            <i class="bi bi-trophy"></i> <span>Siswa Ekstrakurikuler</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 3. Grup Form / Inputan -->
        <?php if (hasAccess('form_absen') || hasAccess('form_jurnal') || hasAccess('form_guruwali') || hasAccess('form_wali') || hasAccess('form_ekstra')): ?>
        <div class="menu-category">Pencatatan & Form</div>
        <div class="accordion sidebar-accordion" id="accordionForm">
            <div class="accordion-item bg-transparent border-0">
                <button class="accordion-button <?= $isFormActive ? '' : 'collapsed' ?> <?= $isFormActive ? 'active-parent' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseForm">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square text-warning"></i>
                        <span>Form Inputan</span>
                    </span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div id="collapseForm" class="accordion-collapse collapse <?= $isFormActive ? 'show' : '' ?>" data-bs-parent="#accordionForm">
                    <div class="accordion-body">
                        <?php if (hasAccess('form_absen')): ?>
                        <a href="form-absen.php" class="nav-link-custom <?= $currentPage === 'form-absen.php' ? 'active' : '' ?>">
                            <i class="bi bi-check2-circle"></i> <span>Presensi / Absensi</span>
                        </a>
                        <?php endif; ?>

                        <?php if (hasAccess('form_jurnal')): ?>
                        <a href="form-jurnal.php" class="nav-link-custom <?= $currentPage === 'form-jurnal.php' ? 'active' : '' ?>">
                            <i class="bi bi-journal-bookmark"></i> <span>Jurnal Mengajar</span>
                        </a>
                        <?php endif; ?>

                        <?php if (hasAccess('form_guruwali')): ?>
                        <a href="form-guruwali.php" class="nav-link-custom <?= $currentPage === 'form-guruwali.php' ? 'active' : '' ?>">
                            <i class="bi bi-chat-heart"></i> <span>Bimbingan Guru Wali</span>
                        </a>
                        <?php endif; ?>

                        <?php if (hasAccess('form_wali')): ?>
                        <a href="form-wali.php" class="nav-link-custom <?= $currentPage === 'form-wali.php' ? 'active' : '' ?>">
                            <i class="bi bi-calendar2-week"></i> <span>Kegiatan Wali Kelas</span>
                        </a>
                        <?php endif; ?>

                        <?php if (hasAccess('form_ekstra')): ?>
                        <a href="form-ekstra.php" class="nav-link-custom <?= $currentPage === 'form-ekstra.php' ? 'active' : '' ?>">
                            <i class="bi bi-activity"></i> <span>Kegiatan Ekstrakurikuler</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4. Grup Laporan -->
        <?php if (hasAccess('laporan')): ?>
        <div class="menu-category">Rekap & Laporan</div>
        <div class="accordion sidebar-accordion" id="accordionLaporan">
            <div class="accordion-item bg-transparent border-0">
                <button class="accordion-button <?= $isLaporanActive ? '' : 'collapsed' ?> <?= $isLaporanActive ? 'active-parent' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLaporan">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-bar-graph text-success"></i>
                        <span>Laporan Hasil</span>
                    </span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div id="collapseLaporan" class="accordion-collapse collapse <?= $isLaporanActive ? 'show' : '' ?>" data-bs-parent="#accordionLaporan">
                    <div class="accordion-body">
                        <a href="laporan-absen.php" class="nav-link-custom <?= $currentPage === 'laporan-absen.php' ? 'active' : '' ?>">
                            <i class="bi bi-file-earmark-check"></i> <span>Laporan Kehadiran</span>
                        </a>
                        <a href="laporan-jurnal.php" class="nav-link-custom <?= $currentPage === 'laporan-jurnal.php' ? 'active' : '' ?>">
                            <i class="bi bi-journal-text"></i> <span>Laporan Jurnal Guru</span>
                        </a>
                        <a href="laporan-wali.php" class="nav-link-custom <?= $currentPage === 'laporan-wali.php' ? 'active' : '' ?>">
                            <i class="bi bi-clipboard-data"></i> <span>Laporan Wali Kelas</span>
                        </a>
                        <a href="laporan-guruwali.php" class="nav-link-custom <?= $currentPage === 'laporan-guruwali.php' ? 'active' : '' ?>">
                            <i class="bi bi-heart-pulse"></i> <span>Laporan Guru Wali</span>
                        </a>
                        <a href="laporan-ekstra.php" class="nav-link-custom <?= $currentPage === 'laporan-ekstra.php' ? 'active' : '' ?>">
                            <i class="bi bi-award"></i> <span>Laporan Ekstrakurikuler</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 5. Grup Pengaturan (Super Admin Only) -->
        <?php if (isSuperAdmin()): ?>
        <div class="menu-category">Pengaturan Sistem</div>
        <div class="accordion sidebar-accordion" id="accordionPengaturan">
            <div class="accordion-item bg-transparent border-0">
                <button class="accordion-button <?= $isPengaturanActive ? '' : 'collapsed' ?> <?= $isPengaturanActive ? 'active-parent' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePengaturan">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-sliders2 text-primary"></i>
                        <span>Pengaturan</span>
                    </span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div id="collapsePengaturan" class="accordion-collapse collapse <?= $isPengaturanActive ? 'show' : '' ?>" data-bs-parent="#accordionPengaturan">
                    <div class="accordion-body">
                        <a href="pengaturan.php" class="nav-link-custom <?= $currentPage === 'pengaturan.php' ? 'active' : '' ?>">
                            <i class="bi bi-gear-wide-connected"></i> <span>Pengaturan Sistem</span>
                        </a>
                        <a href="user-management.php" class="nav-link-custom <?= $currentPage === 'user-management.php' ? 'active' : '' ?>">
                            <i class="bi bi-shield-lock"></i> <span>Manajemen User</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Profil Pengguna di Bagian Bawah Sidebar (Modern Minimalist Card) -->
    <div class="sidebar-user-card p-2 mx-2 mb-2 rounded-2">
        <div class="d-flex align-items-center justify-content-between">
            <a href="profil.php" class="d-flex align-items-center gap-2 text-decoration-none overflow-hidden" title="Profil Saya">
                <div class="avatar-sidebar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 30px; height: 30px; font-size: 0.75rem; flex-shrink: 0;">
                    <?= strtoupper(substr($currentUserData['nama'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="overflow-hidden" style="max-width: 125px;">
                    <div class="text-white fw-semibold small text-truncate" style="font-size: 0.8rem; line-height: 1.2;"><?= htmlspecialchars($currentUserData['nama'] ?? 'Pengguna') ?></div>
                    <div class="text-white-50 text-truncate" style="font-size: 0.68rem; line-height: 1.1;"><?= htmlspecialchars($currentUserData['hak_akses'] ?? 'Guru') ?></div>
                </div>
            </a>
            <div class="d-flex align-items-center">
                <a href="profil.php" class="btn btn-sm btn-link text-white-50 p-1" title="Profil">
                    <i class="bi bi-gear" style="font-size: 0.85rem;"></i>
                </a>
                <a href="logout.php" class="btn btn-sm btn-link text-danger p-1" title="Logout">
                    <i class="bi bi-box-arrow-right" style="font-size: 0.85rem;"></i>
                </a>
            </div>
        </div>
    </div>
</aside>

