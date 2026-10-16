<?php
// login.php - Halaman Login SiGuru (Professional Split-Screen Layout)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';
require_once __DIR__ . '/config/auth.php';

// Jika sudah login, langsung ke dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$settings = getSettings();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Silakan isi username dan kata sandi Anda.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM data_user WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                // Cek password hash atau plain text fallback
                $isPasswordValid = password_verify($password, $user['password']) || ($password === $user['password']);
                if ($isPasswordValid) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama'] = $user['nama'];
                    $_SESSION['nip'] = $user['nip'];
                    $_SESSION['hak_akses'] = $user['hak_akses'];
                    $_SESSION['foto'] = $user['foto'] ?? '';

                    setFlash('success', 'Selamat datang, ' . $user['nama'] . '!');
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = 'Kata sandi (password) yang Anda masukkan salah.';
                }
            } else {
                $error = 'Username tidak terdaftar dalam sistem.';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kendala sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke Portal — SIGURU | <?= htmlspecialchars($settings['nama_sekolah'] ?? 'SMK FYJ') ?></title>
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
            --primary-dark: #1d4ed8;
            --primary-light: #eff6ff;
            --primary-gradient: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --navy-deep: #0b1120;
            --navy-surface: #0f172a;
            --navy-card: rgba(30, 41, 59, 0.75);
            --navy-border: rgba(255, 255, 255, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--navy-deep);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            color: #0f172a;
            overflow-x: hidden;
        }

        .auth-container {
            min-height: 100vh;
            display: flex;
        }

        /* -----------------------------------------------------------
           KOLOM KIRI: SHOWCASE INFORMASI & PENJELASAN MODUL
           ----------------------------------------------------------- */
        .showcase-pane {
            background-color: var(--navy-deep);
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(37, 99, 235, 0.28) 0%, transparent 45%),
                radial-gradient(circle at 90% 85%, rgba(124, 58, 237, 0.22) 0%, transparent 45%),
                radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 24px 24px;
            color: #ffffff;
            padding: 3rem 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid var(--navy-border);
            position: relative;
        }

        .showcase-brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .school-brand-box {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .school-logo-img {
            width: 54px;
            height: 54px;
            object-fit: contain;
            border-radius: 12px;
            background: #ffffff;
            padding: 4px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.4);
        }

        .brand-portal-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(37, 99, 235, 0.25);
            border: 1px solid rgba(96, 165, 250, 0.4);
            color: #bfdbfe;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .pulse-live-dot {
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

        .academic-badge {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 0.4rem 0.95rem;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .showcase-headline {
            font-size: 2.15rem;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.03em;
            color: #ffffff;
            margin-bottom: 0.75rem;
        }

        .showcase-headline .gradient-accent {
            background: linear-gradient(135deg, #93c5fd 0%, #c084fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Teks Subtitle dengan Kontras Sangat Jelas */
        .showcase-subtext {
            color: #e2e8f0;
            font-size: 0.95rem;
            line-height: 1.65;
            margin-bottom: 1.35rem;
            max-width: 600px;
        }

        /* -----------------------------------------------------------
           INFORMASI PEMBUAT WEB & YOUTUBE BANNER (DARK THEME)
           ----------------------------------------------------------- */
        .creator-showcase-box {
            display: flex;
            align-items: center;
        }

        .creator-showcase-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.85rem;
            background: rgba(30, 41, 59, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 0.42rem 0.65rem 0.42rem 0.55rem;
            border-radius: 9999px;
            text-decoration: none;
            box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: 100%;
        }

        .creator-showcase-pill:hover {
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(255, 0, 0, 0.6);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px -4px rgba(255, 0, 0, 0.3);
        }

        .creator-showcase-yt-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px -2px rgba(255, 0, 0, 0.5);
            flex-shrink: 0;
        }

        .creator-showcase-info {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
            text-align: left;
        }

        .creator-showcase-label {
            font-size: 0.68rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .creator-showcase-name {
            font-size: 0.86rem;
            color: #ffffff;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .creator-showcase-sub-btn {
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
            box-shadow: 0 2px 8px rgba(255, 0, 0, 0.4);
        }

        .creator-showcase-pill:hover .creator-showcase-sub-btn {
            background: #cc0000;
            box-shadow: 0 4px 12px rgba(204, 0, 0, 0.55);
        }

        /* Grid Modul Ringkas & High-Contrast */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.9rem;
            margin-bottom: 2rem;
        }

        .module-item-card {
            background: var(--navy-card);
            border: 1px solid var(--navy-border);
            border-radius: 12px;
            padding: 0.95rem 1.05rem;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            transition: all 0.25s ease;
            backdrop-filter: blur(8px);
        }

        .module-item-card:hover {
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(147, 197, 253, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -5px rgba(0, 0, 0, 0.5);
        }

        .module-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .pill-absen   { background: rgba(16, 185, 129, 0.22); color: #34d399; }
        .pill-jurnal  { background: rgba(59, 130, 246, 0.22); color: #60a5fa; }
        .pill-konsel  { background: rgba(244, 63, 94, 0.22); color: #fb7185; }
        .pill-wali    { background: rgba(245, 158, 11, 0.22); color: #fbbf24; }
        .pill-ekstra  { background: rgba(168, 85, 247, 0.22); color: #c084fc; }
        .pill-laporan { background: rgba(6, 182, 212, 0.22); color: #22d3ee; }

        .module-item-title {
            font-weight: 700;
            font-size: 0.88rem;
            color: #ffffff;
            margin-bottom: 0.2rem;
        }

        .module-item-desc {
            font-size: 0.76rem;
            color: #cbd5e1;
            margin-bottom: 0;
            line-height: 1.45;
        }

        /* -----------------------------------------------------------
           KOLOM KANAN: FORMULIR OTENTIKASI PROFESIONAL
           ----------------------------------------------------------- */
        .auth-pane {
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 20px 20px;
            padding: 3rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card-pro {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 20px 45px -12px rgba(15, 23, 42, 0.1), 0 0 2px rgba(15, 23, 42, 0.05);
            padding: 2.75rem 2.25rem;
            position: relative;
        }

        .auth-header-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.12) 0%, rgba(99, 102, 241, 0.18) 100%);
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 1.25rem;
        }

        .auth-card-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
        }

        .auth-card-subtitle {
            color: #475569;
            font-size: 0.88rem;
            line-height: 1.55;
            margin-bottom: 1.75rem;
        }

        /* Input Group Custom (Anti-Overlap & Jelas) */
        .form-label-custom {
            font-weight: 700;
            font-size: 0.82rem;
            color: #1e293b;
            margin-bottom: 0.45rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .input-group-pro {
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
            transition: all 0.2s ease;
            display: flex;
            align-items: stretch;
        }

        .input-group-pro:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }

        .input-group-pro .input-icon-box {
            background: #f1f5f9;
            border-right: 1px solid #cbd5e1;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 1rem;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .input-group-pro .form-control {
            border: none !important;
            box-shadow: none !important;
            padding: 0.75rem 1rem;
            font-size: 0.92rem;
            font-weight: 600;
            color: #0f172a;
            background: transparent;
            height: 46px;
        }

        .input-group-pro .form-control::placeholder {
            color: #64748b;
            font-weight: 400;
        }

        .btn-toggle-pwd {
            background: transparent;
            border: none;
            color: #475569;
            padding: 0 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            transition: color 0.2s;
        }

        .btn-toggle-pwd:hover {
            color: #0f172a;
        }

        .btn-submit-pro {
            height: 48px;
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            transition: all 0.25s ease;
            box-shadow: 0 6px 18px -4px rgba(37, 99, 235, 0.45);
            margin-top: 1.5rem;
        }

        .btn-submit-pro:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px -4px rgba(37, 99, 235, 0.6);
            background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
            color: #ffffff;
        }

        .btn-submit-pro:active {
            transform: translateY(0);
        }

        .caps-alert {
            display: none;
            font-size: 0.76rem;
            color: #92400e;
            background: #fef3c7;
            border: 1px solid #fde68a;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            margin-top: 0.45rem;
            font-weight: 600;
        }

        /* Shake animation on error */
        .shake-effect {
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        /* Responsiveness */
        @media (max-width: 991.98px) {
            .auth-container {
                flex-direction: column;
            }
            .showcase-pane {
                padding: 2.5rem 1.75rem;
                order: 2;
            }
            .auth-pane {
                padding: 2.5rem 1.5rem;
                order: 1;
            }
            .module-grid {
                grid-template-columns: 1fr;
            }
            .showcase-headline {
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="row g-0 w-100 min-vh-100">

            <!-- ============================================================
                 KOLOM KIRI: SHOWCASE INFORMASI & PENJELASAN MODUL
                 ============================================================ -->
            <div class="col-lg-7 col-xl-7 showcase-pane">
                
                <div>
                    <!-- Header Branding Sekolah -->
                    <div class="showcase-brand-header">
                        <div class="school-brand-box">
                            <img src="<?= htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png') ?>" alt="Logo Sekolah" class="school-logo-img">
                            <div>
                                <span class="brand-portal-badge">
                                    <span class="pulse-live-dot"></span>
                                    Portal Resmi Pendidik
                                </span>
                                <div class="fw-bold text-white text-uppercase" style="font-size: 0.98rem; letter-spacing: 0.5px;">
                                    <?= htmlspecialchars($settings['nama_sekolah'] ?? 'SMK FYJ Channel Ferryxz') ?>
                                </div>
                            </div>
                        </div>

                        <!-- Badge Status Periode -->
                        <div class="academic-badge">
                            <i class="bi bi-calendar2-week text-info"></i>
                            <span>Tahun Ajaran <?= htmlspecialchars($settings['tahun_ajaran'] ?? '2026/2027') ?></span>
                            <span class="text-white-50">•</span>
                            <span class="text-white fw-bold">Semester <?= htmlspecialchars($settings['semester'] ?? 'Ganjil') ?></span>
                        </div>
                    </div>

                    <!-- Judul Utama & Subteks -->
                    <h1 class="showcase-headline">
                        Sistem Informasi Guru <br>
                        <span class="gradient-accent">Terpadu & Profesional</span>
                    </h1>
                    <p class="showcase-subtext">
                        Aplikasi administrasi pendidik digital untuk pencatatan presensi siswa, agenda jurnal mengajar, konseling siswa asuh, pemantauan wali kelas, dan pembinaan ekstrakurikuler secara terpusat.
                    </p>

                    <!-- Informasi Pembuat Web & Channel YouTube FYJ Channel -->
                    <div class="creator-showcase-box mb-4">
                        <a href="https://www.youtube.com/@fyjchannel" target="_blank" rel="noopener noreferrer" class="creator-showcase-pill" title="Kunjungi & Subscribe Channel YouTube Pembuat Web (@fyjchannel)">
                            <div class="creator-showcase-yt-icon">
                                <i class="bi bi-youtube"></i>
                            </div>
                            <div class="creator-showcase-info">
                                <span class="creator-showcase-label">Pembuat / Pengembang Web:</span>
                                <span class="creator-showcase-name">FYJ Channel</span>
                            </div>
                            <div class="creator-showcase-sub-btn">
                                <i class="bi bi-bell-fill"></i>
                                <span>Subscribe</span>
                                <i class="bi bi-box-arrow-up-right small ms-1"></i>
                            </div>
                        </a>
                    </div>

                    <!-- Penjelasan Menu / Fitur yang Tersedia -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-white fw-bold text-uppercase" style="font-size: 0.76rem; letter-spacing: 1px;">
                            <i class="bi bi-grid-fill text-primary me-1"></i> Modul & Layanan Utama
                        </span>
                        <span class="badge bg-primary text-white rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                            6 Modul Terintegrasi
                        </span>
                    </div>

                    <!-- Kartu Modul Kompak & High-Contrast -->
                    <div class="module-grid">
                        <!-- 1. Presensi -->
                        <div class="module-item-card">
                            <div class="module-icon-pill pill-absen">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <div>
                                <div class="module-item-title">Presensi / Absensi Siswa</div>
                                <p class="module-item-desc">Catat kehadiran harian kelas per jam pelajaran secara instan (Hadir, Izin, Sakit, Alpha, Dispen).</p>
                            </div>
                        </div>

                        <!-- 2. Jurnal Mengajar -->
                        <div class="module-item-card">
                            <div class="module-icon-pill pill-jurnal">
                                <i class="bi bi-journal-bookmark-fill"></i>
                            </div>
                            <div>
                                <div class="module-item-title">Jurnal Mengajar Guru</div>
                                <p class="module-item-desc">Dokumentasi materi KBM, ketuntasan kurikulum, absensi per pertemuan, & bukti foto kegiatan.</p>
                            </div>
                        </div>

                        <!-- 3. Guru Wali & Bimbingan -->
                        <div class="module-item-card">
                            <div class="module-icon-pill pill-konsel">
                                <i class="bi bi-chat-heart-fill"></i>
                            </div>
                            <div>
                                <div class="module-item-title">Bimbingan Guru Wali</div>
                                <p class="module-item-desc">Pencatatan sesi konseling personal, tindak lanjut penanganan kendala, dan pembinaan siswa asuh.</p>
                            </div>
                        </div>

                        <!-- 4. Wali Kelas -->
                        <div class="module-item-card">
                            <div class="module-icon-pill pill-wali">
                                <i class="bi bi-calendar2-check-fill"></i>
                            </div>
                            <div>
                                <div class="module-item-title">Kegiatan Wali Kelas</div>
                                <p class="module-item-desc">Rekap agenda pembinaan kelas binaan, koordinasi orang tua murid, dan laporan berkala wali.</p>
                            </div>
                        </div>

                        <!-- 5. Ekstrakurikuler -->
                        <div class="module-item-card">
                            <div class="module-icon-pill pill-ekstra">
                                <i class="bi bi-trophy-fill"></i>
                            </div>
                            <div>
                                <div class="module-item-title">Ekstrakurikuler</div>
                                <p class="module-item-desc">Manajemen anggota ekskul, pencatatan presensi latihan, pengembangan bakat, & dokumentasi.</p>
                            </div>
                        </div>

                        <!-- 6. Laporan Resmi -->
                        <div class="module-item-card">
                            <div class="module-icon-pill pill-laporan">
                                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                            </div>
                            <div>
                                <div class="module-item-title">Master Data & Laporan</div>
                                <p class="module-item-desc">Sinkronisasi data master guru/siswa, ekspor-impor Excel, dan cetak berkas resmi ber-Kop Surat.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Showcase Sisi Kiri -->
                <div class="pt-3 border-top d-flex flex-wrap align-items-center justify-content-between text-white-50 small" style="border-color: var(--navy-border) !important; font-size: 0.8rem;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-patch-check-fill text-primary"></i>
                        <span class="text-light">Portal Resmi SIGURU</span>
                    </div>
                    <div>
                        <span class="text-white-50">&copy; <?= date('Y') ?> <?= htmlspecialchars($settings['nama_sekolah'] ?? 'SMK FYJ') ?></span>
                    </div>
                </div>

            </div>

            <!-- ============================================================
                 KOLOM KANAN: FORM LOGIN OTENTIKASI PROFESIONAL
                 ============================================================ -->
            <div class="col-lg-5 col-xl-5 auth-pane">
                <div class="auth-card-pro">

                    <!-- Mobile Header Only -->
                    <div class="d-lg-none text-center mb-4 pb-3 border-bottom">
                        <img src="<?= htmlspecialchars($settings['logo'] ?? 'assets/images/logo.png') ?>" alt="Logo" class="mb-2" style="width: 48px; height: 48px; object-fit: contain;">
                        <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($settings['nama_sekolah'] ?? 'SMK FYJ') ?></h6>
                        <small class="text-primary fw-bold">SIGURU — Sistem Informasi Guru</small>
                    </div>

                    <!-- Navigasi Kembali ke Beranda Publik -->
                    <div class="mb-3">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs d-inline-flex align-items-center gap-1.5 text-secondary">
                            <i class="bi bi-arrow-left"></i>
                            <span>Kembali ke Beranda</span>
                        </a>
                    </div>

                    <!-- Header Card -->
                    <div class="auth-header-icon">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h2 class="auth-card-title">Masuk ke Portal</h2>
                    <p class="auth-card-subtitle">
                        Masukkan username dan kata sandi akun Anda untuk mengakses sistem administrasi sekolah.
                    </p>

                    <!-- Alert Error Message -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-start gap-2 py-2 px-3 rounded-3 mb-4 shake-effect" role="alert" style="font-size: 0.88rem; border-left: 4px solid #ef4444;">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger flex-shrink-0 mt-0.5"></i>
                            <div>
                                <strong class="d-block text-danger">Gagal Masuk:</strong>
                                <span class="text-dark"><?= htmlspecialchars($error) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Form Login -->
                    <form method="POST" action="login.php" id="loginForm" novalidate>
                        
                        <!-- Input Username -->
                        <div class="mb-3">
                            <label for="username" class="form-label-custom">
                                <span><i class="bi bi-person-fill text-primary me-1"></i> Username Akun</span>
                                <span class="text-secondary fw-semibold" style="font-size: 0.74rem;">NIP / Akun</span>
                            </label>
                            <div class="input-group-pro">
                                <div class="input-icon-box">
                                    <i class="bi bi-person"></i>
                                </div>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Masukkan username Anda" 
                                       required 
                                       autocomplete="username"
                                       autofocus>
                            </div>
                        </div>

                        <!-- Input Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label-custom">
                                <span><i class="bi bi-lock-fill text-primary me-1"></i> Kata Sandi (Password)</span>
                            </label>
                            <div class="input-group-pro">
                                <div class="input-icon-box">
                                    <i class="bi bi-key"></i>
                                </div>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Masukkan password Anda" 
                                       required 
                                       autocomplete="current-password">
                                <button type="button" 
                                        class="btn-toggle-pwd" 
                                        id="togglePassword" 
                                        tabindex="-1"
                                        title="Tampilkan / Sembunyikan Password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <!-- Peringatan Caps Lock -->
                            <div id="capsAlert" class="caps-alert">
                                <i class="bi bi-exclamation-circle-fill me-1"></i> Perhatian: Tombol Caps Lock sedang aktif!
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" style="cursor: pointer;">
                                <label class="form-check-label text-dark small user-select-none" for="rememberMe" style="cursor: pointer; font-size: 0.82rem; font-weight: 500;">
                                    Ingat akun saya
                                </label>
                            </div>
                            <span class="text-secondary small fw-semibold" style="font-size: 0.78rem;" title="Hubungi admin jika mengalami kendala login">
                                <i class="bi bi-question-circle me-1"></i> Butuh bantuan?
                            </span>
                        </div>

                        <!-- Tombol Submit Login -->
                        <button type="submit" id="btnLogin" class="btn-submit-pro">
                            <i class="bi bi-box-arrow-in-right fs-5"></i>
                            <span id="btnText">Masuk ke Sistem</span>
                            <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>

                    </form>

                    <!-- Kembali ke Beranda Publik -->
                    <div class="text-center mt-4 pt-3 border-top">
                        <a href="index.php" class="text-decoration-none text-dark fw-bold small d-inline-flex align-items-center gap-1 hover-primary-link">
                            <i class="bi bi-arrow-left text-primary"></i>
                            <span>Kembali ke Halaman Beranda Publik</span>
                        </a>
                    </div>

                    <!-- Copyright Footer -->
                    <div class="text-center mt-3">
                        <p class="text-secondary mb-0" style="font-size: 0.74rem;">
                            &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['nama_sekolah'] ?? 'SMK FYJ') ?>. All rights reserved.
                        </p>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Script Interaktif Login -->
    <script>
        // Toggle Show / Hide Password
        const toggleBtn = document.getElementById('togglePassword');
        const pwdInput = document.getElementById('password');
        const capsAlert = document.getElementById('capsAlert');
        const loginForm = document.getElementById('loginForm');
        const btnLogin = document.getElementById('btnLogin');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');

        if (toggleBtn && pwdInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = pwdInput.type === 'password';
                pwdInput.type = isPassword ? 'text' : 'password';
                toggleBtn.innerHTML = isPassword ? '<i class="bi bi-eye-slash text-primary"></i>' : '<i class="bi bi-eye"></i>';
                pwdInput.focus();
            });
        }

        // Deteksi Caps Lock pada field password
        if (pwdInput && capsAlert) {
            const checkCapsLock = (e) => {
                if (e.getModifierState && e.getModifierState('CapsLock')) {
                    capsAlert.style.display = 'block';
                } else {
                    capsAlert.style.display = 'none';
                }
            };
            pwdInput.addEventListener('keyup', checkCapsLock);
            pwdInput.addEventListener('keydown', checkCapsLock);
        }

        // Loading state saat tombol submit ditekan
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                const usernameVal = document.getElementById('username').value.trim();
                const passwordVal = pwdInput.value.trim();

                if (usernameVal && passwordVal) {
                    btnText.textContent = 'Memverifikasi Akun...';
                    btnSpinner.classList.remove('d-none');
                    btnLogin.disabled = true;
                    btnLogin.style.opacity = '0.85';
                    loginForm.submit();
                }
            });
        }
    </script>
</body>
</html>
