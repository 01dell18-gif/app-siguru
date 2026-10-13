<?php
// form-wali.php - Formulir Kegiatan Wali Kelas
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
$user = currentUser();
$settings = getSettings();

if (!hasAccess('form_wali')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke formulir Wali Kelas.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$kelasList = getDaftarKelas();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);

// Default nilai form & hak akses guru
$defaultGuru = isSuperAdmin() ? ($guruList[0] ?? $user['nama']) : $user['nama'];
$selectedGuru = isSuperAdmin() ? ($_GET['guru'] ?? $defaultGuru) : $user['nama'];

// Cek apakah user atau guru terpilih adalah wali kelas tertentu
$myKelas = $kelasList[0] ?? '';
$targetGuruForKelas = isSuperAdmin() ? $selectedGuru : $user['nama'];
$stmtWk = $pdo->prepare("SELECT sebagai_wali_kelas FROM data_guru WHERE nama_guru = ? LIMIT 1");
$stmtWk->execute([$targetGuruForKelas]);
$wkRes = $stmtWk->fetchColumn();
if ($wkRes && $wkRes !== '-') {
    $myKelas = $wkRes;
}

$tanggalHariIni = date('Y-m-d');
$taList = getDaftarTahunAjaran();
$tahunAjaran = $_POST['tahun_ajaran'] ?? ($_GET['ta'] ?? ($_GET['tahun_ajaran'] ?? ($settings['tahun_ajaran'] ?? '2026/2027')));
$semester = $_POST['semester'] ?? ($_GET['semester'] ?? ($settings['semester'] ?? 'Ganjil'));

// Simpan Formulir Kegiatan Wali
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postTanggal = $_POST['tanggal'] ?? $tanggalHariIni;
    $postTA = $_POST['tahun_ajaran'] ?? $tahunAjaran;
    $postSemester = $_POST['semester'] ?? $semester;
    $postKelas = $_POST['kelas'] ?? $myKelas;
    $postWali = isSuperAdmin() ? trim($_POST['nama_wali_kelas'] ?? $selectedGuru) : $user['nama'];
    $deskripsi = trim($_POST['deskripsi_kegiatan'] ?? '');
    
    // Upload Bukti (Hanya memilih salah satu jenis upload: Upload File ATAU Link Drive)
    $proofMode = $_POST['proof_mode_wali'] ?? ($_POST['proof_mode'] ?? 'file');
    $linkBukti = '';
    if ($proofMode === 'link') {
        $linkBukti = trim($_POST['link_bukti_drive'] ?? '');
    } else {
        $uploadedFiles = handleFileUpload('file_bukti', 'documents');
        if (!empty($uploadedFiles)) {
            $linkBukti = is_array($uploadedFiles) ? implode(', ', $uploadedFiles) : $uploadedFiles;
        }
    }

    if (!empty($deskripsi)) {
        $stmt = $pdo->prepare("INSERT INTO hasil_wali (tanggal, tahun_ajaran, semester, kelas, nama_wali_kelas, deskripsi_kegiatan, link_bukti_wali) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$postTanggal, $postTA, $postSemester, $postKelas, $postWali, $deskripsi, $linkBukti]);
        
        setFlash('success', 'Kegiatan Wali Kelas ' . htmlspecialchars($postKelas) . ' (' . htmlspecialchars($postWali) . ') berhasil dicatat!');
        header("Location: laporan-wali.php");
        exit;
    } else {
        setFlash('danger', 'Deskripsi kegiatan tidak boleh kosong.');
    }
}

$pageTitle = 'Formulir Kegiatan Wali Kelas';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Formulir Kegiatan Wali Kelas</h4>
            <p class="text-muted small mb-0">Catat agenda khusus kelas binaan (rapat kelas, paguyuban ortu, pembersihan, pembagian rapor, dll).</p>
        </div>
        <a href="laporan-wali.php" class="btn btn-outline-primary rounded-pill btn-sm">
            <i class="bi bi-clipboard-data me-1"></i> Lihat Rekap Kegiatan
        </a>
    </div>

    <div class="card card-table p-4">
        <form method="POST" action="form-wali.php" enctype="multipart/form-data">
            <div class="row g-3">
                <!-- 1. Nama Wali Kelas -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Nama Wali Kelas</label>
                    <?php if (isSuperAdmin()): ?>
                        <select name="nama_wali_kelas" class="form-select" onchange="window.location.href='form-wali.php?guru=' + encodeURIComponent(this.value)">
                            <?php foreach ($guruList as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>" <?= ($selectedGuru === $g) ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" name="nama_wali_kelas" class="form-control bg-light fw-semibold text-dark" value="<?= htmlspecialchars($user['nama']) ?>" readonly title="Terkunci sesuai akun login Anda">
                    <?php endif; ?>
                </div>

                <!-- 2. Tanggal Kegiatan -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Tanggal Kegiatan</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggalHariIni) ?>" required>
                </div>

                <!-- 3. Kelas Binaan -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Kelas Binaan</label>
                    <?php if (isSuperAdmin()): ?>
                        <select name="kelas" class="form-select">
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= ($myKelas === $k) ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" name="kelas" class="form-control bg-light fw-bold text-primary" value="<?= htmlspecialchars($myKelas) ?>" readonly>
                    <?php endif; ?>
                </div>

                <!-- 4. Tahun Ajaran -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Tahun Ajaran</label>
                    <select name="tahun_ajaran" class="form-select">
                        <?php foreach ($taList as $taItem): ?>
                            <option value="<?= htmlspecialchars($taItem) ?>" <?= $tahunAjaran === $taItem ? 'selected' : '' ?>><?= htmlspecialchars($taItem) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 5. Semester -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                    </select>
                </div>

                <!-- 4. Deskripsi Kegiatan -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Deskripsi & Rincian Kegiatan</label>
                    <textarea name="deskripsi_kegiatan" class="form-control" rows="4" placeholder="Jelaskan nama agenda, tujuan, pihak yang terlibat, dan hasil kesepakatan kegiatan wali kelas..." required></textarea>
                </div>

                <!-- 5. Upload Bukti (Pilih Salah Satu via Minimalist Switch) -->
                <div class="col-12">
                    <div class="card p-3 border rounded-3 bg-light-subtle">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary mb-0">
                                <i class="bi bi-paperclip me-1"></i> Bukti Dokumentasi Kegiatan Wali Kelas
                            </label>
                            <!-- Minimalist Switch / Segmented Control -->
                            <div class="btn-group btn-group-sm p-1 bg-white border rounded-pill shadow-xs" role="group">
                                <input type="radio" class="btn-check" name="proof_mode_wali" id="proof_mode_file_wali" value="file" checked onchange="toggleProofInput('wali', 'file')">
                                <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="proof_mode_file_wali">
                                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                </label>

                                <input type="radio" class="btn-check" name="proof_mode_wali" id="proof_mode_link_wali" value="link" onchange="toggleProofInput('wali', 'link')">
                                <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="proof_mode_link_wali">
                                    <i class="bi bi-link-45deg me-1"></i> Link Drive
                                </label>
                            </div>
                        </div>

                        <div id="proof_file_container_wali">
                            <input type="file" name="file_bukti[]" multiple class="form-control bg-white" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih satu atau beberapa file foto / dokumen notula kegiatan (Maks 10MB per file, dapat memilih lebih dari 1 file sekaligus).</div>
                        </div>

                        <div id="proof_link_container_wali" style="display: none;">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                <input type="url" name="link_bukti_drive" class="form-control bg-white" placeholder="https://drive.google.com/..." value="" disabled>
                            </div>
                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Salin tautan folder atau file Google Drive bukti kegiatan.</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 pt-3 border-top text-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                        <i class="bi bi-save2-fill me-1"></i> Simpan Catatan Kegiatan
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/views/footer.php'; ?>
