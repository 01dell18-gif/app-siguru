<?php
// form-ekstra.php - Formulir Kegiatan Ekstrakurikuler
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
$user = currentUser();
$settings = getSettings();

if (!hasAccess('form_ekstra')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke formulir Ekstrakurikuler.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$ekstraList = getDaftarEkstra();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);

// Default nilai form & hak akses guru
$defaultGuru = isSuperAdmin() ? ($guruList[0] ?? $user['nama']) : $user['nama'];
$selectedGuru = isSuperAdmin() ? ($_GET['guru'] ?? $defaultGuru) : $user['nama'];
$selectedEkstra = $_GET['ekstra'] ?? ($ekstraList[0] ?? 'Pramuka');

$tanggalHariIni = date('Y-m-d');
$taList = getDaftarTahunAjaran();
$tahunAjaran = $_POST['tahun_ajaran'] ?? ($_GET['ta'] ?? ($_GET['tahun_ajaran'] ?? ($settings['tahun_ajaran'] ?? '2026/2027')));
$semester = $_POST['semester'] ?? ($_GET['semester'] ?? ($settings['semester'] ?? 'Ganjil'));

// Ambil daftar siswa anggota ekstra yang dipilih
$siswaPeserta = [];
if (!empty($selectedEkstra)) {
    $stmtPeserta = $pdo->prepare("SELECT * FROM data_murid_ekstra WHERE (nama_ekstra = ? OR nama_ekstra LIKE ? OR nama_ekstra LIKE ? OR nama_ekstra LIKE ?) ORDER BY nama_murid ASC");
    $stmtPeserta->execute([$selectedEkstra, "$selectedEkstra, %", "%, $selectedEkstra, %", "%, $selectedEkstra"]);
    $siswaPeserta = $stmtPeserta->fetchAll();
    
    // Fallback jika belum ada yang terdaftar spesifik pada nama_ekstra ini, ambil semua siswa ekstra
    if (empty($siswaPeserta)) {
        $siswaPeserta = $pdo->query("SELECT * FROM data_murid_ekstra ORDER BY nama_murid ASC")->fetchAll();
    }
}

// Simpan Formulir Kegiatan Ekstra
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postTanggal = $_POST['tanggal'] ?? $tanggalHariIni;
    $postTA = $_POST['tahun_ajaran'] ?? $tahunAjaran;
    $postSemester = $_POST['semester'] ?? $semester;
    $postEkstra = $_POST['nama_ekstra'] ?? $selectedEkstra;
    $postPembina = isSuperAdmin() ? trim($_POST['pembina_ekstra'] ?? $selectedGuru) : $user['nama'];
    $postKegiatan = trim($_POST['kegiatan_ekstra'] ?? '');
    $postKeterangan = trim($_POST['keterangan'] ?? '');
    $postPeserta = isset($_POST['nama_murid']) ? (is_array($_POST['nama_murid']) ? implode(', ', $_POST['nama_murid']) : trim($_POST['nama_murid'])) : '';

    // Upload Bukti (Hanya memilih salah satu jenis upload: Upload File ATAU Link Drive)
    $proofMode = $_POST['proof_mode_ekstra'] ?? ($_POST['proof_mode'] ?? 'file');
    $linkBukti = '';
    if ($proofMode === 'link') {
        $linkBukti = trim($_POST['link_bukti_drive'] ?? '');
    } else {
        $uploadedFiles = handleFileUpload('file_bukti', 'documents');
        if (!empty($uploadedFiles)) {
            $linkBukti = is_array($uploadedFiles) ? implode(', ', $uploadedFiles) : $uploadedFiles;
        }
    }

    if (!empty($postKegiatan) && !empty($postPeserta)) {
        $stmt = $pdo->prepare("INSERT INTO hasil_ekstra (
            tanggal, tahun_ajaran, semester, nama_murid, kegiatan_ekstra, keterangan, link_bukti_ekstra, pembina_ekstra
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $postTanggal, $postTA, $postSemester, $postPeserta, $postKegiatan, $postKeterangan, $linkBukti, $postPembina
        ]);

        setFlash('success', 'Kegiatan Ekstrakurikuler ' . htmlspecialchars($postEkstra) . ' (' . htmlspecialchars($postPembina) . ') berhasil disimpan!');
        header("Location: laporan-ekstra.php");
        exit;
    } else {
        setFlash('danger', 'Harap isi kegiatan ekstra dan pilih peserta yang hadir.');
    }
}

$pageTitle = 'Formulir Kegiatan Ekstrakurikuler';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Formulir Kegiatan Ekstrakurikuler</h4>
            <p class="text-muted small mb-0">Catat agenda latihan mingguan, materi kegiatan, dan presensi anggota ekstra.</p>
        </div>
        <a href="laporan-ekstra.php" class="btn btn-outline-primary rounded-pill btn-sm">
            <i class="bi bi-award me-1"></i> Lihat Rekap Ekstrakurikuler
        </a>
    </div>

    <div class="card card-table p-4">
        <form method="POST" action="form-ekstra.php" enctype="multipart/form-data">
            <div class="row g-3">
                <!-- 1. Nama Guru / Pembina Ekstra -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Nama Guru / Pembina Ekstrakurikuler</label>
                    <?php if (isSuperAdmin()): ?>
                        <select name="pembina_ekstra" class="form-select" onchange="window.location.href='form-ekstra.php?guru=' + encodeURIComponent(this.value) + '&ekstra=<?= urlencode($selectedEkstra) ?>'">
                            <?php foreach ($guruList as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>" <?= ($selectedGuru === $g) ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" name="pembina_ekstra" class="form-control bg-light fw-semibold text-dark" value="<?= htmlspecialchars($user['nama']) ?>" readonly title="Terkunci sesuai akun login Anda">
                    <?php endif; ?>
                </div>

                <!-- 2. Tanggal -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Tanggal Latihan / Kegiatan</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggalHariIni) ?>" required>
                </div>

                <!-- 3. Cabang Ekstra -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Cabang Ekstrakurikuler</label>
                    <select name="nama_ekstra" class="form-select" onchange="window.location.href='form-ekstra.php?ekstra=' + encodeURIComponent(this.value) + '<?= isSuperAdmin() ? "&guru=" . urlencode($selectedGuru) : "" ?>'">
                        <?php foreach ($ekstraList as $e): ?>
                            <option value="<?= htmlspecialchars($e) ?>" <?= $selectedEkstra === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                        <?php endforeach; ?>
                    </select>
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

                <!-- 4. Daftar Siswa Peserta (Multi-select Checkbox Table Sesuai PRD) -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary d-block">
                        Daftar Hadir Anggota Ekstrakurikuler (<?= htmlspecialchars($selectedEkstra) ?>)
                    </label>
                    <div class="card p-3 bg-light border rounded-3" style="max-height: 200px; overflow-y: auto;">
                        <div class="row g-2">
                            <?php if (empty($siswaPeserta)): ?>
                                <div class="col-12 text-muted small">Belum ada siswa terdaftar pada cabang ekstra ini.</div>
                            <?php else: foreach ($siswaPeserta as $sp): ?>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="nama_murid[]" value="<?= htmlspecialchars($sp['nama_murid']) ?>" id="sp_<?= $sp['id'] ?>" checked>
                                        <label class="form-check-label small" for="sp_<?= $sp['id'] ?>">
                                            <?= htmlspecialchars($sp['nama_murid']) ?> <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($sp['kelas']) ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 5. Kegiatan Ekstra -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Kegiatan / Agenda Ekstra</label>
                    <input type="text" name="kegiatan_ekstra" class="form-control" placeholder="Contoh: Latihan Rutin Basket / Pramuka - Penjelajahan / Workshop Robotika" required>
                </div>

                <!-- 6. Keterangan Kegiatan -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Keterangan & Rincian Materi Latihan</label>
                    <textarea name="keterangan" class="form-control" rows="3" placeholder="Menjelaskan ringkasan materi, capaian fisik/keterampilan hari ini..."></textarea>
                </div>

                <!-- 7. Upload Bukti (Pilih Salah Satu via Minimalist Switch) -->
                <div class="col-12">
                    <div class="card p-3 border rounded-3 bg-light-subtle">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary mb-0">
                                <i class="bi bi-paperclip me-1"></i> Bukti Dokumentasi Ekstrakurikuler
                            </label>
                            <!-- Minimalist Switch / Segmented Control -->
                            <div class="btn-group btn-group-sm p-1 bg-white border rounded-pill shadow-xs" role="group">
                                <input type="radio" class="btn-check" name="proof_mode_ekstra" id="proof_mode_file_ekstra" value="file" checked onchange="toggleProofInput('ekstra', 'file')">
                                <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="proof_mode_file_ekstra">
                                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                </label>

                                <input type="radio" class="btn-check" name="proof_mode_ekstra" id="proof_mode_link_ekstra" value="link" onchange="toggleProofInput('ekstra', 'link')">
                                <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="proof_mode_link_ekstra">
                                    <i class="bi bi-link-45deg me-1"></i> Link Drive
                                </label>
                            </div>
                        </div>

                        <div id="proof_file_container_ekstra">
                            <input type="file" name="file_bukti[]" multiple class="form-control bg-white" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih satu atau beberapa file foto atau dokumen dokumentasi kegiatan (Maks 10MB per file, dapat memilih lebih dari 1 file sekaligus).</div>
                        </div>

                        <div id="proof_link_container_ekstra" style="display: none;">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                <input type="url" name="link_bukti_drive" class="form-control bg-white" placeholder="https://drive.google.com/..." value="" disabled>
                            </div>
                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Salin tautan folder atau file Google Drive bukti kegiatan ekstra.</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 pt-3 border-top text-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                        <i class="bi bi-save2-fill me-1"></i> Simpan Catatan Ekstrakurikuler
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/views/footer.php'; ?>
