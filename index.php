<?php
// index.php - Frontend Publik Terpisah SiGuru (Professional Landing Page)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';
require_once __DIR__ . '/config/auth.php';

$settings = getSettings();
$pdo = getDBConnection();

// Hitung ringkasan data publik secara dinamis
try {
    $totalGuru = $pdo->query("SELECT COUNT(*) FROM data_guru")->fetchColumn();
    $totalMurid = $pdo->query("SELECT COUNT(*) FROM data_murid")->fetchColumn();
    $totalEkstra = count(getListOptions($settings['list_ekstrakurikuler'] ?? ''));
    $totalJurnal = $pdo->query("SELECT COUNT(*) FROM hasil_jurnal")->fetchColumn();
} catch (Exception $e) {
    $totalGuru = 0;
    $totalMurid = 0;
    $totalEkstra = 0;
    $totalJurnal = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGURU — Sistem Informasi Guru Terpadu | <?= htmlspecialchars($settings['nama_sekolah']) ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --primary-gradient: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --navy-dark: #0b1120;
            --navy-surface: #0f172a;
            --navy-card: #1e293b;
            --text-dark: #0f172a;
            --text-muted: #475569;
            --border-light: #cbd5e1;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            overflow-x: hidden;
            font-size: 0.92rem;
            line-height: 1.6;
        }

        /* -----------------------------------------------------------
           NAVBAR MODERN & GLASSMORPHISM
           ----------------------------------------------------------- */
        .public-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .navbar-brand-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 2px;
        }

        .nav-link-custom {
            font-weight: 600;
            color: #334155 !important;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .nav-link-custom:hover, .nav-link-custom.active {
            color: var(--primary) !important;
            background: rgba(37, 99, 235, 0.08);
        }

        /* -----------------------------------------------------------
           HERO SECTION
           ----------------------------------------------------------- */
        .hero-section {
            background: radial-gradient(120% 120% at 50% 10%, #e0f2fe 0%, #f8fafc 100%);
            padding: 5.5rem 0 4.5rem;
            position: relative;
            overflow: hidden;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #ffffff;
            border: 1px solid #93c5fd;
            color: #1d4ed8;
            padding: 0.4rem 1.1rem;
            border-radius: 9999px;
            font-size: 0.84rem;
            font-weight: 700;
            box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.1);
            margin-bottom: 1.5rem;
        }

        .pulse-dot-green {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-ring 2s infinite cubic-bezier(0.66, 0, 0, 1);
        }

        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .hero-title {
            font-size: 2.85rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.03em;
            color: #0f172a;
            margin-bottom: 1.25rem;
        }

        .hero-title .text-gradient {
            background: linear-gradient(135deg, #1d4ed8 0%, #6d28d9 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.08rem;
            color: #334155;
            line-height: 1.7;
            margin-bottom: 1.5rem;
            max-width: 620px;
            font-weight: 500;
        }

        /* -----------------------------------------------------------
           INFORMASI PEMBUAT WEB & YOUTUBE BANNER
           ----------------------------------------------------------- */
        .hero-creator-box {
            display: flex;
            align-items: center;
        }

        .hero-creator-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.85rem;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            padding: 0.42rem 0.65rem 0.42rem 0.55rem;
            border-radius: 9999px;
            text-decoration: none;
            box-shadow: 0 4px 14px -3px rgba(15, 23, 42, 0.08);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: 100%;
        }

        .hero-creator-pill:hover {
            border-color: #ef4444;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -4px rgba(239, 68, 68, 0.22);
        }

        .creator-yt-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px -2px rgba(255, 0, 0, 0.4);
            flex-shrink: 0;
        }

        .creator-info-text {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
            text-align: left;
        }

        .creator-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 600;
        }

        .creator-name {
            font-size: 0.86rem;
            color: #0f172a;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .creator-sub-btn {
            background: #ff0000;
            color: #ffffff;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(255, 0, 0, 0.35);
        }

        .hero-creator-pill:hover .creator-sub-btn {
            background: #cc0000;
            box-shadow: 0 4px 12px rgba(204, 0, 0, 0.45);
        }

        .hero-academic-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.1), 0 0 1px rgba(15, 23, 42, 0.05);
            padding: 2.25rem 2rem;
            position: relative;
        }

        /* -----------------------------------------------------------
           STATISTIK COUNTER STRIP
           ----------------------------------------------------------- */
        .stats-strip {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 2.25rem 0;
        }

        .stat-item-box {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .stat-icon-wrapper {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .stat-number {
            font-size: 1.85rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            margin-bottom: 0.2rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #475569;
            font-weight: 600;
            margin: 0;
        }

        /* -----------------------------------------------------------
           FEATURE / MODUL CARDS
           ----------------------------------------------------------- */
        .feature-card-pro {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.85rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .feature-card-pro:hover {
            transform: translateY(-5px);
            border-color: #60a5fa;
            box-shadow: 0 16px 32px -8px rgba(37, 99, 235, 0.15);
        }

        .feature-icon-badge {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 1.25rem;
        }

        .badge-absen   { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .badge-jurnal  { background: rgba(37, 99, 235, 0.15); color: #1d4ed8; }
        .badge-konsel  { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }
        .badge-wali    { background: rgba(245, 158, 11, 0.15); color: #b45309; }
        .badge-ekstra  { background: rgba(147, 51, 234, 0.15); color: #6b21a8; }
        .badge-laporan { background: rgba(8, 145, 178, 0.15); color: #0e7490; }

        .feature-card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.6rem;
        }

        .feature-card-desc {
            font-size: 0.88rem;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }

        /* -----------------------------------------------------------
           WORKFLOW SECTION
           ----------------------------------------------------------- */
        .step-number-badge {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            margin-bottom: 1rem;
        }

        /* -----------------------------------------------------------
           CTA BANNER
           ----------------------------------------------------------- */
        .cta-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 50%, #2563eb 100%);
            border-radius: 24px;
            padding: 3.5rem 3rem;
            color: #ffffff;
            box-shadow: 0 25px 50px -12px rgba(37, 99, 235, 0.35);
            position: relative;
            overflow: hidden;
        }

        /* -----------------------------------------------------------
           FOOTER PROFESIONAL DENGAN KONTRAS JELAS
           ----------------------------------------------------------- */
        .footer-pro {
            background-color: var(--navy-dark);
            color: #e2e8f0;
            padding-top: 5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            position: relative;
        }

        .footer-brand-title {
            color: #ffffff;
            font-weight: 800;
            font-size: 1.25rem;
            letter-spacing: -0.01em;
            margin-bottom: 0.2rem;
        }

        .footer-desc-text {
            color: #cbd5e1;
            font-size: 0.88rem;
            line-height: 1.7;
        }

        .footer-col-title {
            color: #ffffff;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 1.35rem;
            position: relative;
            padding-bottom: 0.65rem;
        }

        .footer-col-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 32px;
            height: 2px;
            background: #3b82f6;
            border-radius: 2px;
        }

        .footer-link-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-link-list li {
            margin-bottom: 0.75rem;
        }

        .footer-link-list a {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .footer-link-list a:hover {
            color: #ffffff;
            transform: translateX(4px);
        }

        .footer-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            margin-bottom: 1rem;
            font-size: 0.88rem;
            color: #cbd5e1;
        }

        .footer-contact-icon {
            color: #60a5fa;
            font-size: 1.2rem;
            margin-top: 0.1rem;
            flex-shrink: 0;
        }

        .footer-bottom-bar {
            background-color: #070b14;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1.75rem 0;
            font-size: 0.84rem;
            color: #94a3b8;
        }

        .scroll-top-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .scroll-top-btn:hover {
            background: var(--primary);
            color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- ============================================================
         NAVBAR PUBLIK RESMI
         ============================================================ -->
    <nav class="navbar navbar-expand-lg public-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="<?= htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png') ?>" alt="Logo Sekolah" class="navbar-brand-logo">
                <div>
                    <span class="fw-bold fs-5 text-primary tracking-wide d-block" style="line-height: 1.1;">SIGURU</span>
                    <small class="text-dark fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        <?= htmlspecialchars($settings['nama_sekolah']) ?>
                    </small>
                </div>
            </a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-1">
                    <li class="nav-item"><a class="nav-link nav-link-custom active" href="#beranda">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="#modul">Layanan & Modul</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="#alur">Alur Kerja</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="#keunggulan">Keunggulan</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="#informasi">Informasi Sekolah</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-speedometer2"></i>
                            <span>Buka Dashboard</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>Masuk ke Portal</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- ============================================================
         HERO SECTION
         ============================================================ -->
    <section class="hero-section" id="beranda">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="hero-badge">
                        <span class="pulse-dot-green"></span>
                        Portal Resmi Pendidik & Tenaga Kependidikan
                    </div>
                    <h1 class="hero-title">
                        Sistem Informasi Guru <br>
                        <span class="text-gradient"><?= htmlspecialchars($settings['nama_sekolah']) ?></span>
                    </h1>
                    <p class="hero-subtitle">
                        Platform administrasi digital modern untuk pencatatan presensi siswa, agenda mengajar KBM, bimbingan konseling guru wali, monitoring wali kelas, serta pembinaan ekstrakurikuler yang akurat, real-time, dan terintegrasi.
                    </p>

                    <!-- Informasi Pembuat Web & Channel YouTube FYJ Channel -->
                    <div class="hero-creator-box mb-4">
                        <a href="https://www.youtube.com/@fyjchannel" target="_blank" rel="noopener noreferrer" class="hero-creator-pill" title="Kunjungi & Subscribe Channel YouTube Pembuat Web (@fyjchannel)">
                            <div class="creator-yt-icon">
                                <i class="bi bi-youtube"></i>
                            </div>
                            <div class="creator-info-text">
                                <span class="creator-label">Pembuat / Pengembang Web:</span>
                                <span class="creator-name">FYJ Channel</span>
                            </div>
                            <div class="creator-sub-btn">
                                <i class="bi bi-bell-fill"></i>
                                <span>Subscribe</span>
                                <i class="bi bi-box-arrow-up-right small ms-1"></i>
                            </div>
                        </a>
                    </div>

                    <div class="d-flex flex-wrap gap-3">
                        <?php if (isLoggedIn()): ?>
                            <a href="dashboard.php" class="btn btn-primary btn-lg rounded-pill px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-speedometer2"></i> Menuju Dashboard Admin
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-lg rounded-pill px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-box-arrow-in-right"></i> Masuk ke Panel Pendidik
                            </a>
                        <?php endif; ?>
                        <a href="#modul" class="btn btn-outline-secondary btn-lg rounded-pill px-4 py-2.5 fw-bold text-dark">
                            <i class="bi bi-grid me-1 text-primary"></i> Jelajahi Modul
                        </a>
                    </div>
                </div>

                <!-- Card Informasi Akademik Aktif -->
                <div class="col-lg-5">
                    <div class="hero-academic-card">
                        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
                            <div>
                                <span class="badge bg-primary text-white fw-bold text-uppercase px-2.5 py-1 mb-1" style="font-size: 0.72rem;">
                                    Tahun Ajaran Aktif
                                </span>
                                <h5 class="fw-bold text-dark mb-0">Informasi Akademik</h5>
                            </div>
                            <span class="badge bg-success text-white px-3 py-1.5 rounded-pill fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> Aktif Berjalan
                            </span>
                        </div>

                        <div class="d-flex flex-column gap-3 mb-4">
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="text-secondary fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i>Tahun Ajaran</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($settings['tahun_ajaran']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="text-secondary fw-semibold"><i class="bi bi-layers me-2 text-primary"></i>Semester</span>
                                <span class="fw-bold badge bg-primary px-3 py-1.5"><?= htmlspecialchars($settings['semester']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="text-secondary fw-semibold"><i class="bi bi-person-workspace me-2 text-primary"></i>Kepala Sekolah</span>
                                <span class="fw-bold text-dark text-end"><?= htmlspecialchars($settings['kepala_sekolah'] ?? '-') ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2">
                                <span class="text-secondary fw-semibold"><i class="bi bi-geo-alt me-2 text-primary"></i>Wilayah / Instansi</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($settings['kabupaten'] ?? 'Klungkung') ?></span>
                            </div>
                        </div>

                        <a href="login.php" class="btn btn-outline-primary w-100 rounded-pill py-2.5 fw-bold d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-shield-lock-fill"></i> Akses Panel Guru & Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         STATISTIK REAL-TIME STRIP
         ============================================================ -->
    <div class="stats-strip">
        <div class="container">
            <div class="row g-4 justify-content-between">
                <div class="col-6 col-lg-3">
                    <div class="stat-item-box">
                        <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                        <div>
                            <div class="stat-number"><?= $totalGuru ?></div>
                            <p class="stat-label">Tenaga Pendidik</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-item-box">
                        <div class="stat-icon-wrapper bg-success-subtle text-success">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <div class="stat-number"><?= $totalMurid ?></div>
                            <p class="stat-label">Siswa Terdaftar</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-item-box">
                        <div class="stat-icon-wrapper" style="background-color: rgba(147, 51, 234, 0.15); color: #7e22ce;">
                            <i class="bi bi-trophy-fill"></i>
                        </div>
                        <div>
                            <div class="stat-number"><?= $totalEkstra ?></div>
                            <p class="stat-label">Cabang Ekstrakurikuler</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-item-box">
                        <div class="stat-icon-wrapper bg-info-subtle text-info">
                            <i class="bi bi-journal-check"></i>
                        </div>
                        <div>
                            <div class="stat-number">6 Modul</div>
                            <p class="stat-label">Terintegrasi Sistem</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         LAYANAN & MODUL UTAMA SIGURU
         ============================================================ -->
    <section class="py-5" id="modul">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-primary text-white px-3 py-1.5 rounded-pill fw-bold text-uppercase mb-2">
                    Layanan & Modul Terpadu
                </span>
                <h2 class="fw-bold text-dark display-6">Fitur Lengkap Aplikasi SIGURU</h2>
                <p class="text-secondary max-w-600 mx-auto fw-medium" style="max-width: 600px;">
                    Dirancang untuk mengakomodasi seluruh tugas administratif guru, wali kelas, guru wali, dan pembina secara rapi, cepat, dan terstandar.
                </p>
            </div>

            <div class="row g-4">
                <!-- Modul 1: Presensi -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card-pro">
                        <div>
                            <div class="feature-icon-badge badge-absen">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <h4 class="feature-card-title">Presensi Siswa Dinamis</h4>
                            <p class="feature-card-desc">
                                Pencatatan kehadiran siswa per mata pelajaran & kelas secara interaktif dengan opsi status Hadir, Izin, Sakit, Alpha, dan Dispen secara real-time.
                            </p>
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="badge bg-success text-white">Guru Mapel</span>
                            <span class="small text-secondary fw-bold">Rekap Otomatis</span>
                        </div>
                    </div>
                </div>

                <!-- Modul 2: Jurnal Mengajar -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card-pro">
                        <div>
                            <div class="feature-icon-badge badge-jurnal">
                                <i class="bi bi-journal-bookmark-fill"></i>
                            </div>
                            <h4 class="feature-card-title">Jurnal Mengajar KBM</h4>
                            <p class="feature-card-desc">
                                Merekam agenda mengajar guru, topik materi, capaian pembelajaran, nomor pertemuan bertambah otomatis, absensi kelas, dan upload bukti dokumentasi.
                            </p>
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="badge bg-primary text-white">Guru Mapel</span>
                            <span class="small text-secondary fw-bold">Auto-Pertemuan</span>
                        </div>
                    </div>
                </div>

                <!-- Modul 3: Bimbingan Guru Wali -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card-pro">
                        <div>
                            <div class="feature-icon-badge badge-konsel">
                                <i class="bi bi-chat-heart-fill"></i>
                            </div>
                            <h4 class="feature-card-title">Bimbingan Guru Wali</h4>
                            <p class="feature-card-desc">
                                Pendampingan personal siswa asuh, rekam pemecahan masalah (akademik maupun karakter), pencatatan solusi, dan monitoring berkelanjutan.
                            </p>
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="badge bg-danger text-white">Guru Wali</span>
                            <span class="small text-secondary fw-bold">Konseling Personal</span>
                        </div>
                    </div>
                </div>

                <!-- Modul 4: Kegiatan Wali Kelas -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card-pro">
                        <div>
                            <div class="feature-icon-badge badge-wali">
                                <i class="bi bi-calendar2-check-fill"></i>
                            </div>
                            <h4 class="feature-card-title">Kegiatan Wali Kelas</h4>
                            <p class="feature-card-desc">
                                Pengelolaan agenda pembinaan kelas binaan, koordinasi paguyuban orang tua, rekap permasalahan siswa di kelas, dan pelaporan wali kelas.
                            </p>
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="badge bg-warning text-dark fw-bold">Wali Kelas</span>
                            <span class="small text-secondary fw-bold">Manajemen Kelas</span>
                        </div>
                    </div>
                </div>

                <!-- Modul 5: Ekstrakurikuler -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card-pro">
                        <div>
                            <div class="feature-icon-badge badge-ekstra">
                                <i class="bi bi-trophy-fill"></i>
                            </div>
                            <h4 class="feature-card-title">Manajemen Ekstrakurikuler</h4>
                            <p class="feature-card-desc">
                                Pencatatan kehadiran latihan rutin siswa, pemantauan agenda pembinaan bakat & minat, serta rekapitulasi portofolio prestasi ekskul sekolah.
                            </p>
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="badge text-white" style="background-color: #7e22ce;">Pembina Ekstra</span>
                            <span class="small text-secondary fw-bold">Bakat & Minat</span>
                        </div>
                    </div>
                </div>

                <!-- Modul 6: Laporan & Cetak -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card-pro">
                        <div>
                            <div class="feature-icon-badge badge-laporan">
                                <i class="bi bi-printer-fill"></i>
                            </div>
                            <h4 class="feature-card-title">Laporan Ber-Kop Surat Resmi</h4>
                            <p class="feature-card-desc">
                                Rekapitulasi data lengkap dengan filter periode, ekspor ke format Excel, dan cetak dokumen resmi ber-Kop Surat serta tanda tangan kepala sekolah.
                            </p>
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="badge bg-info text-white">Laporan Cetak</span>
                            <span class="small text-secondary fw-bold">Siap Cetak / Ekspor</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         ALUR KERJA PENGGUNAAN SISTEM (WORKFLOW)
         ============================================================ -->
    <section class="py-5 bg-white border-top border-bottom" id="alur">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-primary text-white px-3 py-1.5 rounded-pill fw-bold text-uppercase mb-2">
                    Alur Kerja Praktis
                </span>
                <h2 class="fw-bold text-dark">4 Langkah Mudah Penggunaan SIGURU</h2>
                <p class="text-secondary fw-medium">Proses yang terstruktur mempermudah aktivitas harian seluruh pendidik.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-3">
                    <div class="p-4 rounded-4 bg-light h-100 border">
                        <div class="step-number-badge">01</div>
                        <h5 class="fw-bold text-dark">Masuk ke Akun</h5>
                        <p class="text-secondary small mb-0 fw-medium">Masuk dengan username dan kata sandi Anda. Sistem langsung membuka menu kerja yang relevan.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-4 rounded-4 bg-light h-100 border">
                        <div class="step-number-badge">02</div>
                        <h5 class="fw-bold text-dark">Input Presensi & KBM</h5>
                        <p class="text-secondary small mb-0 fw-medium">Pilih kelas & mapel, input status kehadiran siswa dengan tombol cepat, dan lengkapi materi pembelajaran harian.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-4 rounded-4 bg-light h-100 border">
                        <div class="step-number-badge">03</div>
                        <h5 class="fw-bold text-dark">Monitoring & Bimbingan</h5>
                        <p class="text-secondary small mb-0 fw-medium">Wali kelas & guru wali memantau perkembangan siswa asuh, mencatat sesi konseling, dan mengawal tindak lanjut.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-4 rounded-4 bg-light h-100 border">
                        <div class="step-number-badge">04</div>
                        <h5 class="fw-bold text-dark">Rekap & Cetak Laporan</h5>
                        <p class="text-secondary small mb-0 fw-medium">Data otomatis terakumulasi dalam tabel laporan. Cetak dokumen resmi ber-Kop Surat atau ekspor ke file Excel.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         KEUNGGULAN SISTEM
         ============================================================ -->
    <section class="py-5" id="keunggulan">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="badge bg-primary text-white px-3 py-1.5 rounded-pill fw-bold text-uppercase mb-2">
                        Mengapa Menggunakan SiGuru?
                    </span>
                    <h2 class="fw-bold text-dark display-6 mb-4">Solusi Cerdas Pengelolaan Administrasi Sekolah</h2>
                    
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-1">Keamanan & Tata Kelola Akun Pendidik</h5>
                            <p class="text-secondary mb-0 small fw-medium">Sistem terdistribusi aman di mana setiap guru, wali kelas, dan pembina mengakses instrumen kerja masing-masing secara teratur.</p>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3 mb-4">
                        <div class="stat-icon-wrapper bg-success-subtle text-success">
                            <i class="bi bi-cloud-arrow-up"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-1">Dukungan Dokumentasi & Foto Digital</h5>
                            <p class="text-secondary mb-0 small fw-medium">Tiap agenda jurnal dan bimbingan mendukung unggah foto atau tautan bukti kegiatan belajar untuk transparansi akreditasi.</p>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-1">Kop Surat Dinas Resmi & TTD Digital</h5>
                            <p class="text-secondary mb-0 small fw-medium">Format cetak laporan presensi dan jurnal langsung terintegrasi dengan Kop Surat resmi sekolah dan spesimen tanda tangan kepala sekolah.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="cta-banner">
                        <span class="badge bg-white text-primary fw-bold px-3 py-1.5 rounded-pill mb-3">Akses Mudah Dari Mana Saja</span>
                        <h3 class="fw-bold mb-3">Tingkatkan Kualitas Pembelajaran dengan Administrasi yang Rapi</h3>
                        <p class="text-white mb-4" style="font-size: 0.95rem; opacity: 0.9;">
                            Seluruh pendidik di <?= htmlspecialchars($settings['nama_sekolah']) ?> dapat mengakses sistem ini untuk mencatat presensi dan jurnal mengajar secara efisien.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="login.php" class="btn btn-light text-primary btn-lg rounded-pill px-4 fw-bold shadow">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                            </a>
                            <a href="#modul" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-semibold">
                                Pelajari Fitur
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         FOOTER LENGKAP & PROFESIONAL DENGAN KONTRAS JELAS
         ============================================================ -->
    <footer class="footer-pro" id="informasi">
        <div class="container pb-5">
            <div class="row g-4 g-lg-5">
                
                <!-- Kolom 1: Profil Sekolah & Sistem -->
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="<?= htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png') ?>" alt="Logo" class="rounded" style="width: 46px; height: 46px; object-fit: contain; background: #ffffff; padding: 3px;">
                        <div>
                            <div class="footer-brand-title">SIGURU</div>
                            <small class="text-light text-uppercase fw-bold" style="font-size: 0.74rem; letter-spacing: 0.5px;">Sistem Informasi Guru</small>
                        </div>
                    </div>
                    <p class="footer-desc-text mb-3">
                        Aplikasi sistem informasi administrasi guru terpadu untuk pencatatan presensi siswa harian, agenda jurnal mengajar, konseling siswa guru wali, agenda kegiatan wali kelas, dan ekstrakurikuler sekolah.
                    </p>
                    <div class="text-white-50 small">
                        <span><?= htmlspecialchars($settings['nama_sekolah']) ?></span>
                    </div>
                </div>

                <!-- Kolom 2: Navigasi Modul Pendidik -->
                <div class="col-lg-2 col-md-6 col-6">
                    <h6 class="footer-col-title">Modul Layanan</h6>
                    <ul class="footer-link-list">
                        <li><a href="login.php"><i class="bi bi-chevron-right small text-primary"></i> Presensi Siswa</a></li>
                        <li><a href="login.php"><i class="bi bi-chevron-right small text-primary"></i> Jurnal Mengajar</a></li>
                        <li><a href="login.php"><i class="bi bi-chevron-right small text-primary"></i> Guru Wali</a></li>
                        <li><a href="login.php"><i class="bi bi-chevron-right small text-primary"></i> Wali Kelas</a></li>
                        <li><a href="login.php"><i class="bi bi-chevron-right small text-primary"></i> Ekstrakurikuler</a></li>
                        <li><a href="login.php"><i class="bi bi-chevron-right small text-primary"></i> Rekap Laporan</a></li>
                    </ul>
                </div>

                <!-- Kolom 3: Tautan Cepat & Navigasi Portal -->
                <div class="col-lg-3 col-md-6 col-6">
                    <h6 class="footer-col-title">Tautan Cepat</h6>
                    <ul class="footer-link-list">
                        <li><a href="#beranda"><i class="bi bi-chevron-right small text-primary"></i> Beranda Publik</a></li>
                        <li><a href="login.php"><i class="bi bi-chevron-right small text-primary"></i> Masuk ke Portal (Login)</a></li>
                        <li><a href="#modul"><i class="bi bi-chevron-right small text-primary"></i> Informasi Layanan</a></li>
                        <li><a href="#alur"><i class="bi bi-chevron-right small text-primary"></i> Alur Kerja Sistem</a></li>
                        <li><a href="#keunggulan"><i class="bi bi-chevron-right small text-primary"></i> Keunggulan Aplikasi</a></li>
                        <li><a href="#informasi"><i class="bi bi-chevron-right small text-primary"></i> Kontak Instansi</a></li>
                    </ul>
                </div>

                <!-- Kolom 4: Informasi Instansi & Kontak -->
                <div class="col-lg-3 col-md-6">
                    <h6 class="footer-col-title">Informasi Sekolah</h6>
                    
                    <div class="footer-contact-item">
                        <i class="bi bi-building footer-contact-icon"></i>
                        <div>
                            <strong class="text-white d-block" style="font-size: 0.95rem;"><?= htmlspecialchars($settings['nama_sekolah']) ?></strong>
                            <span class="text-light">Kabupaten <?= htmlspecialchars($settings['kabupaten'] ?? 'Klungkung') ?></span>
                        </div>
                    </div>

                    <div class="footer-contact-item">
                        <i class="bi bi-person-workspace footer-contact-icon"></i>
                        <div>
                            <span class="text-light d-block" style="font-size: 0.78rem;">Kepala Sekolah:</span>
                            <span class="text-white fw-bold d-block"><?= htmlspecialchars($settings['kepala_sekolah'] ?? '-') ?></span>
                            <span class="text-light small">NIP. <?= htmlspecialchars($settings['nip_kepala_sekolah'] ?? '-') ?></span>
                        </div>
                    </div>

                    <div class="footer-contact-item">
                        <i class="bi bi-clock-history footer-contact-icon"></i>
                        <div>
                            <span class="text-light d-block" style="font-size: 0.78rem;">Layanan Presensi:</span>
                            <span class="text-white fw-medium">Senin — Jumat (07.00 - 16.00 WITA)</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Sub-Footer / Bottom Bar -->
        <div class="footer-bottom-bar">
            <div class="container">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <span class="text-light">&copy; <?= date('Y') ?> <strong class="text-white"><?= htmlspecialchars($settings['nama_sekolah']) ?></strong>. All rights reserved.</span>
                        <span class="mx-2 text-secondary">•</span>
                        <span class="text-light">SIGURU Platform (Sistem Informasi Guru)</span>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <a href="#beranda" class="scroll-top-btn" title="Kembali ke Atas">
                            <i class="bi bi-arrow-up"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
