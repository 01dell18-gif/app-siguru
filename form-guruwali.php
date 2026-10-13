<?php
// form-guruwali.php - Formulir Bimbingan Guru Wali
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
$user = currentUser();
$settings = getSettings();

if (!hasAccess('form_guruwali')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke modul Guru Wali.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru WHERE sebagai_guru_wali = 'Ya' OR sebagai_guru_wali = 'ya' ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($guruList)) {
    $guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);
}

$defaultGuru = isSuperAdmin() ? ($guruList[0] ?? $user['nama']) : $user['nama'];
$selectedGuru = isSuperAdmin() ? ($_GET['guru'] ?? $defaultGuru) : $user['nama'];
$tanggalHariIni = date('Y-m-d');
$taList = getDaftarTahunAjaran();
$tahunAjaran = $_POST['tahun_ajaran'] ?? ($_GET['ta'] ?? ($_GET['tahun_ajaran'] ?? ($settings['tahun_ajaran'] ?? '2026/2027')));
$semester = $_POST['semester'] ?? ($_GET['semester'] ?? ($settings['semester'] ?? 'Ganjil'));

// Ambil daftar siswa bimbingan dari DataMuridGuruWali
$stmtBimbingan = $pdo->prepare("SELECT * FROM data_murid_guru_wali WHERE guru_wali = ? ORDER BY kelas ASC, nama_murid ASC");
$stmtBimbingan->execute([$selectedGuru]);
$bimbinganStudents = $stmtBimbingan->fetchAll();
if (empty($bimbinganStudents)) {
    // Fallback jika belum terpetakan khusus ke nama guru ini
    $bimbinganStudents = $pdo->query("SELECT * FROM data_murid_guru_wali ORDER BY kelas ASC, nama_murid ASC")->fetchAll();
}

// Proses Simpan Bimbingan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postTanggal = $_POST['tanggal'] ?? $tanggalHariIni;
    $postTA = $_POST['tahun_ajaran'] ?? $tahunAjaran;
    $postSemester = $_POST['semester'] ?? $semester;
    $postGuru = isSuperAdmin() ? trim($_POST['nama_guru_wali'] ?? $selectedGuru) : $user['nama'];
    $selectedStudents = isset($_POST['nama_murid']) ? (is_array($_POST['nama_murid']) ? implode(', ', $_POST['nama_murid']) : trim($_POST['nama_murid'])) : '';
    $kategori = trim($_POST['kategori'] ?? 'Individu');
    $topik = trim($_POST['topik_masalah'] ?? '');
    $tindakLanjut = trim($_POST['tindak_lanjut'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? 'Tidak ada permasalahan yang signifikan');

    // Upload Bukti (Hanya memilih salah satu jenis upload: Upload File ATAU Link Drive)
    $proofMode = $_POST['proof_mode_gw'] ?? ($_POST['proof_mode'] ?? 'file');
    $fileBukti = '';
    if ($proofMode === 'link') {
        $fileBukti = trim($_POST['link_bukti_drive'] ?? '');
    } else {
        $uploadedFiles = handleFileUpload('file_bukti', 'documents');
        if (!empty($uploadedFiles)) {
            $fileBukti = is_array($uploadedFiles) ? implode(', ', $uploadedFiles) : $uploadedFiles;
        }
    }

    if (!empty($selectedStudents) && !empty($topik)) {
        $stmt = $pdo->prepare("INSERT INTO hasil_guru_wali (
            tanggal, tahun_ajaran, semester, nama_guru_wali, nama_murid, kategori, topik_masalah, tindak_lanjut, keterangan, file_bukti
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $postTanggal, $postTA, $postSemester, $postGuru, $selectedStudents, $kategori, $topik, $tindakLanjut, $keterangan, $fileBukti
        ]);

        setFlash('success', 'Catatan Bimbingan Guru Wali (' . htmlspecialchars($postGuru) . ') berhasil disimpan!');
        header("Location: laporan-guruwali.php");
        exit;
    } else {
        setFlash('danger', 'Pilih minimal satu siswa dan isi topik permasalahan.');
    }
}

$pageTitle = 'Formulir Bimbingan Guru Wali';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Formulir Bimbingan Guru Wali</h4>
            <p class="text-muted small mb-0">Catat sesi konseling, pendampingan, atau bimbingan karakter siswa binaan.</p>
        </div>
        <a href="laporan-guruwali.php" class="btn btn-outline-primary rounded-pill btn-sm">
            <i class="bi bi-heart-pulse me-1"></i> Lihat Rekap Bimbingan
        </a>
    </div>

    <div class="card card-table p-4">
        <form method="POST" action="form-guruwali.php" enctype="multipart/form-data">
            <div class="row g-3">
                <!-- 1. Nama Guru Wali -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Nama Guru Wali</label>
                    <?php if (isSuperAdmin()): ?>
                        <select name="nama_guru_wali" class="form-select" onchange="window.location.href='form-guruwali.php?guru=' + encodeURIComponent(this.value)">
                            <?php foreach ($guruList as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>" <?= $selectedGuru === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" name="nama_guru_wali" class="form-control bg-light fw-semibold text-dark" value="<?= htmlspecialchars($user['nama']) ?>" readonly title="Terkunci sesuai akun login Anda">
                    <?php endif; ?>
                </div>

                <!-- 2. Tanggal Bimbingan -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Tanggal Sesi Bimbingan</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggalHariIni) ?>" required>
                </div>

                <!-- 3. Tahun Ajaran & Semester -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Tahun Ajaran</label>
                    <select name="tahun_ajaran" class="form-select">
                        <?php foreach ($taList as $taItem): ?>
                            <option value="<?= htmlspecialchars($taItem) ?>" <?= $tahunAjaran === $taItem ? 'selected' : '' ?>><?= htmlspecialchars($taItem) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                    </select>
                </div>

                <!-- 4. Nama Murid (Multi-select Checkbox Table Sesuai PRD) -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary d-block">
                        Pilih Siswa Bimbingan (Dari DataMuridGuruWali)
                    </label>
                    <div class="card p-3 bg-light border rounded-3" style="max-height: 200px; overflow-y: auto;">
                        <div class="row g-2">
                            <?php if (empty($bimbinganStudents)): ?>
                                <div class="col-12 text-muted small">Belum ada siswa bimbingan terdaftar. Tambahkan di menu Data Siswa Bimbingan.</div>
                            <?php else: foreach ($bimbinganStudents as $bs): ?>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="nama_murid[]" value="<?= htmlspecialchars($bs['nama_murid']) ?>" id="bs_<?= $bs['id'] ?>">
                                        <label class="form-check-label small" for="bs_<?= $bs['id'] ?>">
                                            <?= htmlspecialchars($bs['nama_murid']) ?> <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($bs['kelas']) ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 5. Kategori -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Kategori Bimbingan</label>
                    <select name="kategori" class="form-select">
                        <option value="Individu">Individu</option>
                        <option value="Kelompok">Kelompok</option>
                        <option value="-">-</option>
                    </select>
                </div>

                <!-- 6. Topik / Masalah yang Dibahas -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Topik atau Masalah yang Dibahas</label>
                    <textarea name="topik_masalah" class="form-control" rows="3" placeholder="Deskripsikan inti topik konsultasi, bimbingan, atau keluhan belajar siswa..." required></textarea>
                </div>

                <!-- 7. Tindak Lanjut -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Tindak Lanjut & Kesepakatan</label>
                    <textarea name="tindak_lanjut" class="form-control" rows="3" placeholder="Langkah konkrit penanganan, komitmen siswa, atau koordinasi lanjutan..."></textarea>
                </div>

                <!-- 8. Keterangan -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Keterangan Tambahan</label>
                    <input type="text" name="keterangan" class="form-control" value="Tidak ada permasalahan yang signifikan">
                </div>

                <!-- 9. Upload Bukti Bimbingan (Pilih Salah Satu via Minimalist Switch) -->
                <div class="col-12">
                    <div class="card p-3 border rounded-3 bg-light-subtle">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary mb-0">
                                <i class="bi bi-paperclip me-1"></i> Bukti Dokumentasi Bimbingan Guru Wali
                            </label>
                            <!-- Minimalist Switch / Segmented Control -->
                            <div class="btn-group btn-group-sm p-1 bg-white border rounded-pill shadow-xs" role="group">
                                <input type="radio" class="btn-check" name="proof_mode_gw" id="proof_mode_file_gw" value="file" checked onchange="toggleProofInput('gw', 'file')">
                                <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="proof_mode_file_gw">
                                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                </label>

                                <input type="radio" class="btn-check" name="proof_mode_gw" id="proof_mode_link_gw" value="link" onchange="toggleProofInput('gw', 'link')">
                                <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="proof_mode_link_gw">
                                    <i class="bi bi-link-45deg me-1"></i> Link Drive
                                </label>
                            </div>
                        </div>

                        <div id="proof_file_container_gw">
                            <input type="file" name="file_bukti[]" multiple class="form-control bg-white" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih satu atau beberapa file foto / dokumen notula sesi bimbingan (Maks 10MB per file, dapat memilih lebih dari 1 file sekaligus).</div>
                        </div>

                        <div id="proof_link_container_gw" style="display: none;">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                <input type="url" name="link_bukti_drive" class="form-control bg-white" placeholder="https://drive.google.com/..." value="" disabled>
                            </div>
                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Salin tautan folder atau file Google Drive bukti bimbingan.</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 pt-3 border-top text-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                        <i class="bi bi-save2-fill me-1"></i> Simpan Sesi Bimbingan
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/views/footer.php'; ?>
