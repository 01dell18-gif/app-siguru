<?php
// dashboard.php - Dashboard Admin Real-Time SiGuru
$pageTitle = 'Dashboard Utama';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';

$pdo = getDBConnection();
$user = currentUser();

// 1. Query Ringkasan Data Real-time
$countGuru = $pdo->query("SELECT COUNT(*) FROM data_guru")->fetchColumn();
$countMurid = $pdo->query("SELECT COUNT(*) FROM data_murid")->fetchColumn();
$countAbsen = $pdo->query("SELECT COUNT(*) FROM hasil_absen")->fetchColumn();
$countJurnal = $pdo->query("SELECT COUNT(*) FROM hasil_jurnal")->fetchColumn();
$countGuruWali = $pdo->query("SELECT COUNT(*) FROM hasil_guru_wali")->fetchColumn();
$countWali = $pdo->query("SELECT COUNT(*) FROM hasil_wali")->fetchColumn();
$countEkstra = $pdo->query("SELECT COUNT(*) FROM hasil_ekstra")->fetchColumn();

// 2. Statistik Presensi
$statHadir = $pdo->query("SELECT COUNT(*) FROM hasil_absen WHERE keterangan = 'H'")->fetchColumn();
$statIzin  = $pdo->query("SELECT COUNT(*) FROM hasil_absen WHERE keterangan = 'I'")->fetchColumn();
$statSakit = $pdo->query("SELECT COUNT(*) FROM hasil_absen WHERE keterangan = 'S'")->fetchColumn();
$statAlpha = $pdo->query("SELECT COUNT(*) FROM hasil_absen WHERE keterangan = 'A'")->fetchColumn();
$statDispen= $pdo->query("SELECT COUNT(*) FROM hasil_absen WHERE keterangan = 'D'")->fetchColumn();

// 3. Aktivitas Terbaru
$recentJurnal = $pdo->query("SELECT * FROM hasil_jurnal ORDER BY id DESC LIMIT 5")->fetchAll();
$recentBimbingan = $pdo->query("SELECT * FROM hasil_guru_wali ORDER BY id DESC LIMIT 5")->fetchAll();
$recentWali = $pdo->query("SELECT * FROM hasil_wali ORDER BY id DESC LIMIT 5")->fetchAll();
$recentEkstra = $pdo->query("SELECT * FROM hasil_ekstra ORDER BY id DESC LIMIT 5")->fetchAll();
?>

<main class="content-wrapper">
    <!-- Welcome Greeting -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                Selamat Datang, <?= htmlspecialchars($user['nama']) ?>! 👋
            </h3>
            <p class="text-muted mb-0 small">
                Masuk sebagai: <span class="badge bg-primary-subtle text-primary fw-bold px-2 py-1"><?= htmlspecialchars($user['hak_akses']) ?></span>
                &bull; Tahun Ajaran: <strong><?= htmlspecialchars($settings['tahun_ajaran']) ?></strong> (<?= htmlspecialchars($settings['semester']) ?>)
            </p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <?php if (hasAccess('form_absen')): ?>
                <a href="form-absen.php" class="btn btn-primary rounded-pill px-3 btn-sm shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Input Presensi
                </a>
            <?php endif; ?>
            <?php if (hasAccess('form_jurnal')): ?>
                <a href="form-jurnal.php" class="btn btn-outline-primary rounded-pill px-3 btn-sm">
                    <i class="bi bi-journal-plus me-1"></i> Input Jurnal
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ringkasan Statistik Kartu (Grid) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold d-block mb-1">Data Guru</span>
                        <h3 class="fw-bold mb-1 text-dark"><?= $countGuru ?></h3>
                        <small class="text-primary fw-medium"><i class="bi bi-person-badge me-1"></i>Tenaga Pendidik</small>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold d-block mb-1">Data Siswa</span>
                        <h3 class="fw-bold mb-1 text-dark"><?= $countMurid ?></h3>
                        <small class="text-info fw-medium"><i class="bi bi-mortarboard me-1"></i>Siswa Terdaftar</small>
                    </div>
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="bi bi-person-lines-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold d-block mb-1">Jurnal Mengajar</span>
                        <h3 class="fw-bold mb-1 text-dark"><?= $countJurnal ?></h3>
                        <small class="text-warning fw-medium"><i class="bi bi-journal-text me-1"></i>Total Entri KBM</small>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="bi bi-journal-bookmark-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold d-block mb-1">Presensi Harian</span>
                        <h3 class="fw-bold mb-1 text-dark"><?= $countAbsen ?></h3>
                        <small class="text-success fw-medium"><i class="bi bi-check-all me-1"></i>Rekor Kehadiran</small>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafik Kehadiran & Rekap Indikator -->
    <div class="row g-4 mb-4">
        <!-- Grafik Bar/Doughnut Kehadiran -->
        <div class="col-lg-8">
            <div class="card card-table p-4 h-100">
                <div class="card-header-modern mb-3 pb-2">
                    <div class="card-title-modern">
                        <div class="icon-badge icon-badge-primary">
                            <i class="bi bi-bar-chart-line"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">Statistik Kehadiran Siswa</div>
                            <div class="text-muted fw-normal" style="font-size: 0.78rem;">Grafik proporsi presensi seluruh kelas</div>
                        </div>
                    </div>
                    <span class="badge bg-light text-secondary border">Real-Time Data</span>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="chartKehadiran"></canvas>
                </div>
            </div>
        </div>

        <!-- Rincian Status Kehadiran -->
        <div class="col-lg-4">
            <div class="card card-table p-4 h-100">
                <div class="card-header-modern mb-3 pb-2">
                    <div class="card-title-modern">
                        <div class="icon-badge icon-badge-success">
                            <i class="bi bi-pie-chart"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">Rincian Presensi</div>
                            <div class="text-muted fw-normal" style="font-size: 0.78rem;">Akumulasi status kehadiran</div>
                        </div>
                    </div>
                </div>
                <div class="list-group list-group-flush gap-2">
                    <div class="list-group-item d-flex justify-content-between align-items-center rounded-3 border-0 bg-light px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success rounded-circle p-1" style="width: 8px; height: 8px;"></span>
                            <span class="fw-semibold text-dark">Hadir (H)</span>
                        </div>
                        <span class="badge badge-hadir rounded-pill px-3"><?= $statHadir ?></span>
                    </div>

                    <div class="list-group-item d-flex justify-content-between align-items-center rounded-3 border-0 bg-light px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning rounded-circle p-1" style="width: 8px; height: 8px;"></span>
                            <span class="fw-semibold text-dark">Izin (I)</span>
                        </div>
                        <span class="badge badge-izin rounded-pill px-3"><?= $statIzin ?></span>
                    </div>

                    <div class="list-group-item d-flex justify-content-between align-items-center rounded-3 border-0 bg-light px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary rounded-circle p-1" style="width: 8px; height: 8px;"></span>
                            <span class="fw-semibold text-dark">Sakit (S)</span>
                        </div>
                        <span class="badge badge-sakit rounded-pill px-3"><?= $statSakit ?></span>
                    </div>

                    <div class="list-group-item d-flex justify-content-between align-items-center rounded-3 border-0 bg-light px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger rounded-circle p-1" style="width: 8px; height: 8px;"></span>
                            <span class="fw-semibold text-dark">Alpha (A)</span>
                        </div>
                        <span class="badge badge-alpha rounded-pill px-3"><?= $statAlpha ?></span>
                    </div>

                    <div class="list-group-item d-flex justify-content-between align-items-center rounded-3 border-0 bg-light px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-circle p-1" style="width: 8px; height: 8px; background: #8b5cf6;"></span>
                            <span class="fw-semibold text-dark">Dispen (D)</span>
                        </div>
                        <span class="badge badge-dispen rounded-pill px-3"><?= $statDispen ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feed Aktivitas Real-Time Terkini -->
    <div class="row g-4">
        <!-- Feed Jurnal Guru Terbaru -->
        <div class="col-lg-6">
            <div class="card card-table p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-journal-bookmark me-2 text-warning"></i>Jurnal Mengajar Terbaru</h5>
                    <a href="laporan-jurnal.php" class="text-primary small text-decoration-none fw-semibold">Lihat Semua &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Guru / Mapel</th>
                                <th>Kelas</th>
                                <th>Pertemuan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentJurnal)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada jurnal mengajar.</td></tr>
                            <?php else: foreach ($recentJurnal as $rj): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($rj['nama_guru']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($rj['mata_pelajaran']) ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($rj['kelas']) ?></span></td>
                                    <td>Ke-<?= htmlspecialchars($rj['pertemuan_ke']) ?></td>
                                    <td>
                                        <span class="badge <?= $rj['ketuntasan_materi'] === 'tuntas' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
                                            <?= htmlspecialchars(ucfirst($rj['ketuntasan_materi'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Feed Bimbingan Guru Wali Terbaru -->
        <div class="col-lg-6">
            <div class="card card-table p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-chat-heart me-2 text-danger"></i>Bimbingan Guru Wali Terbaru</h5>
                    <a href="laporan-guruwali.php" class="text-primary small text-decoration-none fw-semibold">Lihat Semua &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Guru Wali</th>
                                <th>Siswa Binaan</th>
                                <th>Kategori</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentBimbingan)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada catatan bimbingan.</td></tr>
                            <?php else: foreach ($recentBimbingan as $rb): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($rb['nama_guru_wali']) ?></td>
                                    <td><?= htmlspecialchars($rb['nama_murid']) ?></td>
                                    <td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($rb['kategori']) ?></span></td>
                                    <td><?= formatDateIndo($rb['tanggal']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Inisialisasi Chart.js Kehadiran
    const ctx = document.getElementById('chartKehadiran').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Hadir (H)', 'Izin (I)', 'Sakit (S)', 'Alpha (A)', 'Dispen (D)'],
            datasets: [{
                label: 'Jumlah Presensi',
                data: [<?= $statHadir ?>, <?= $statIzin ?>, <?= $statSakit ?>, <?= $statAlpha ?>, <?= $statDispen ?>],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)'
                ],
                borderColor: [
                    '#10b981',
                    '#f59e0b',
                    '#3b82f6',
                    '#ef4444',
                    '#8b5cf6'
                ],
                borderWidth: 1.5,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
