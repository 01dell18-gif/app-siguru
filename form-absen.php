<?php
// form-absen.php - Formulir Presensi / Absensi Siswa
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
$user = currentUser();
$settings = getSettings();

if (!hasAccess('form_absen')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke formulir Presensi.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$kelasList = getDaftarKelas();
$mapelList = getDaftarMapel();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);

// Default nilai form & hak akses guru
$defaultGuru = isSuperAdmin() ? ($guruList[0] ?? $user['nama']) : $user['nama'];
$selectedGuru = isSuperAdmin() ? ($_GET['guru'] ?? $defaultGuru) : $user['nama'];
$selectedKelas = $_GET['kelas'] ?? ($kelasList[0] ?? '');
$selectedMapel = $_GET['mapel'] ?? ($mapelList[0] ?? '');
$selectedTanggal = $_GET['tanggal'] ?? date('Y-m-d');
$taList = getDaftarTahunAjaran();
$tahunAjaran = $_GET['ta'] ?? ($_GET['tahun_ajaran'] ?? ($settings['tahun_ajaran'] ?? '2026/2027'));
$semester = $_GET['semester'] ?? ($settings['semester'] ?? 'Ganjil');

// Ambil daftar siswa sesuai kelas yang dipilih
$students = [];
if (!empty($selectedKelas)) {
    $stmt = $pdo->prepare("SELECT * FROM data_murid WHERE kelas = ? ORDER BY nama_murid ASC");
    $stmt->execute([$selectedKelas]);
    $students = $stmt->fetchAll();
}

// Cek apakah sudah pernah diabsen pada tanggal, kelas, mapel, tahun ajaran, dan semester ini
$existingAbsen = [];
if (!empty($selectedKelas) && !empty($selectedMapel) && !empty($selectedTanggal)) {
    $chkStmt = $pdo->prepare("SELECT nisn, keterangan, nama_guru FROM hasil_absen WHERE tanggal = ? AND kelas = ? AND mata_pelajaran = ? AND tahun_ajaran = ? AND semester = ?");
    $chkStmt->execute([$selectedTanggal, $selectedKelas, $selectedMapel, $tahunAjaran, $semester]);
    $existingRows = $chkStmt->fetchAll();
    foreach ($existingRows as $er) {
        $existingAbsen[$er['nisn']] = $er['keterangan'];
    }
}

// Proses Simpan Presensi Sekaligus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postTanggal = $_POST['tanggal'] ?? $selectedTanggal;
    $postTA = $_POST['tahun_ajaran'] ?? $tahunAjaran;
    $postSemester = $_POST['semester'] ?? $semester;
    $postMapel = $_POST['mata_pelajaran'] ?? $selectedMapel;
    $postKelas = $_POST['kelas'] ?? $selectedKelas;
    $absenData = $_POST['absen'] ?? []; // array [nisn => 'H'/'I'/'S'/'A'/'D']
    $guruName = isSuperAdmin() ? trim($_POST['nama_guru'] ?? $selectedGuru) : $user['nama'];

    if (!empty($absenData) && !empty($postKelas)) {
        // Hapus presensi lama jika pada tanggal, mapel, kelas, tahun ajaran, dan semester yang sama (replace/update)
        $del = $pdo->prepare("DELETE FROM hasil_absen WHERE tanggal = ? AND kelas = ? AND mata_pelajaran = ? AND tahun_ajaran = ? AND semester = ?");
        $del->execute([$postTanggal, $postKelas, $postMapel, $postTA, $postSemester]);

        // Simpan setiap siswa
        $ins = $pdo->prepare("INSERT INTO hasil_absen (tanggal, tahun_ajaran, semester, nisn, nama_murid, jenis_kelamin, kelas, keterangan, mata_pelajaran, nama_guru) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $mMap = [];
        $allStudents = $pdo->prepare("SELECT * FROM data_murid WHERE kelas = ?");
        $allStudents->execute([$postKelas]);
        foreach ($allStudents->fetchAll() as $s) {
            $mMap[$s['nisn']] = $s;
        }

        foreach ($absenData as $nisn => $ket) {
            $cleanNisn = str_pad(trim($nisn), 10, '0', STR_PAD_LEFT);
            $namaM = $mMap[$cleanNisn]['nama_murid'] ?? ($mMap[$nisn]['nama_murid'] ?? '-');
            $jkM = $mMap[$cleanNisn]['jenis_kelamin'] ?? ($mMap[$nisn]['jenis_kelamin'] ?? 'L');
            $ins->execute([$postTanggal, $postTA, $postSemester, $cleanNisn, $namaM, $jkM, $postKelas, $ket, $postMapel, $guruName]);
        }

        setFlash('success', 'Presensi kelas ' . htmlspecialchars($postKelas) . ' untuk mapel ' . htmlspecialchars($postMapel) . ' (' . htmlspecialchars($guruName) . ') berhasil disimpan!');
        $redir = "form-absen.php?kelas=" . urlencode($postKelas) . "&mapel=" . urlencode($postMapel) . "&tanggal=" . urlencode($postTanggal) . "&ta=" . urlencode($postTA) . "&semester=" . urlencode($postSemester);
        if (isSuperAdmin()) {
            $redir .= "&guru=" . urlencode($guruName);
        }
        header("Location: " . $redir);
        exit;
    }
}

$pageTitle = 'Formulir Presensi Siswa';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Presensi / Absensi Siswa</h4>
            <p class="text-muted small mb-0">Catat kehadiran peserta didik per mata pelajaran & kelas.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="laporan-absen.php" class="btn btn-outline-primary rounded-pill btn-sm">
                <i class="bi bi-file-earmark-check me-1"></i> Lihat Rekap Presensi
            </a>
        </div>
    </div>

    <!-- Filter Header Presensi Sesuai PRD -->
    <div class="card card-table p-4 mb-4">
        <form method="GET" action="form-absen.php" class="row g-3 align-items-end">
            <div class="col-md-2 col-sm-6">
                <label class="form-label fw-semibold small text-secondary">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($selectedTanggal) ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label fw-semibold small text-secondary">Nama Guru</label>
                <?php if (isSuperAdmin()): ?>
                    <select name="guru" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>" <?= ($selectedGuru === $g) ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" class="form-control bg-light fw-semibold text-dark" value="<?= htmlspecialchars($user['nama']) ?>" readonly title="Terkunci sesuai akun login Anda">
                <?php endif; ?>
            </div>
            <div class="col-md-2 col-sm-4">
                <label class="form-label fw-semibold small text-secondary">Tahun Ajaran</label>
                <select name="ta" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($taList as $taItem): ?>
                        <option value="<?= htmlspecialchars($taItem) ?>" <?= $tahunAjaran === $taItem ? 'selected' : '' ?>><?= htmlspecialchars($taItem) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 col-sm-4">
                <label class="form-label fw-semibold small text-secondary">Semester</label>
                <select name="semester" class="form-select" onchange="this.form.submit()">
                    <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                    <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-4">
                <label class="form-label fw-semibold small text-secondary">Mata Pelajaran</label>
                <select name="mapel" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($mapelList as $mp): ?>
                        <option value="<?= htmlspecialchars($mp) ?>" <?= $selectedMapel === $mp ? 'selected' : '' ?>><?= htmlspecialchars($mp) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label fw-semibold small text-secondary">Kelas</label>
                <select name="kelas" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($kelasList as $kls): ?>
                        <option value="<?= htmlspecialchars($kls) ?>" <?= $selectedKelas === $kls ? 'selected' : '' ?>><?= htmlspecialchars($kls) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Tabel Input Kehadiran Siswa (Grid Dynamic Input) -->
    <form method="POST" action="form-absen.php">
        <input type="hidden" name="tanggal" value="<?= htmlspecialchars($selectedTanggal) ?>">
        <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($tahunAjaran) ?>">
        <input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>">
        <input type="hidden" name="mata_pelajaran" value="<?= htmlspecialchars($selectedMapel) ?>">
        <input type="hidden" name="kelas" value="<?= htmlspecialchars($selectedKelas) ?>">
        <input type="hidden" name="nama_guru" value="<?= htmlspecialchars($selectedGuru) ?>">

        <div class="card card-table overflow-hidden">
            <div class="d-flex flex-wrap justify-content-between align-items-center p-3 border-bottom bg-light">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold text-dark">Daftar Kehadiran:</span>
                    <span class="badge bg-primary px-3 py-1"><?= htmlspecialchars($selectedKelas) ?></span>
                    <span class="badge bg-secondary px-3 py-1"><?= htmlspecialchars($selectedMapel) ?></span>
                    <span class="badge bg-light text-dark border px-3 py-1"><i class="bi bi-person-badge text-primary me-1"></i><?= htmlspecialchars($selectedGuru) ?></span>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                    <button type="button" class="btn btn-outline-success btn-sm rounded-pill fw-semibold" onclick="markAllAttendance('H')">
                        <i class="bi bi-check-all me-1"></i> Tandai Semua Hadir
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-save2-fill me-1"></i> Simpan Presensi
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th style="width: 140px;">NISN</th>
                            <th>Nama Murid</th>
                            <th style="width: 80px;">L/P</th>
                            <th style="min-width: 320px;">Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada siswa terdaftar pada kelas <?= htmlspecialchars($selectedKelas) ?>.</td></tr>
                        <?php else: 
                            $no = 1;
                            foreach ($students as $st): 
                                $currentKet = $existingAbsen[$st['nisn']] ?? 'H'; // Default Hadir sesuai PRD
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($st['nisn']) ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($st['nama_murid']) ?></td>
                                <td>
                                    <span class="badge <?= $st['jenis_kelamin'] === 'L' ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' ?>">
                                        <?= $st['jenis_kelamin'] ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Minimalist Attendance Dropdown -->
                                    <select name="absen[<?= $st['nisn'] ?>]" class="form-select form-select-sm attendance-select shadow-sm" style="max-width: 175px; font-weight: 600; border-radius: 8px;">
                                        <option value="H" class="text-success fw-bold" <?= $currentKet === 'H' ? 'selected' : '' ?>>🟢 Hadir (H)</option>
                                        <option value="I" class="text-warning fw-bold" <?= $currentKet === 'I' ? 'selected' : '' ?>>🟡 Izin (I)</option>
                                        <option value="S" class="text-primary fw-bold" <?= $currentKet === 'S' ? 'selected' : '' ?>>🔵 Sakit (S)</option>
                                        <option value="A" class="text-danger fw-bold" <?= $currentKet === 'A' ? 'selected' : '' ?>>🔴 Alpha (A)</option>
                                        <option value="D" style="color: #8b5cf6;" class="fw-bold" <?= $currentKet === 'D' ? 'selected' : '' ?>>🟣 Dispen (D)</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($students)): ?>
            <div class="p-3 bg-light border-top text-end">
                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                    <i class="bi bi-save2-fill me-1"></i> Simpan Presensi Kelas <?= htmlspecialchars($selectedKelas) ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/views/footer.php'; ?>
