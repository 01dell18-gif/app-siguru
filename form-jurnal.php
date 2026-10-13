<?php
// form-jurnal.php - Formulir Jurnal Mengajar Guru
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
$user = currentUser();
$settings = getSettings();

if (!hasAccess('form_jurnal')) {
    setFlash('danger', 'Anda tidak memiliki hak akses ke modul Jurnal Mengajar.');
    header("Location: dashboard.php");
    exit;
}

$pdo = getDBConnection();
$kelasList = getDaftarKelas();
$mapelList = getDaftarMapel();
$guruList = $pdo->query("SELECT nama_guru FROM data_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_COLUMN);

// Default nilai form
$defaultGuru = isSuperAdmin() ? ($guruList[0] ?? $user['nama']) : $user['nama'];
$selectedKelas = $_GET['kelas'] ?? ($kelasList[0] ?? '');
$selectedMapel = $_GET['mapel'] ?? ($mapelList[0] ?? '');
$selectedGuru = isSuperAdmin() ? ($_GET['guru'] ?? $defaultGuru) : $user['nama'];
$tanggalHariIni = $_GET['tanggal'] ?? date('Y-m-d');
$taList = getDaftarTahunAjaran();
$tahunAjaran = $_GET['ta'] ?? ($_GET['tahun_ajaran'] ?? ($settings['tahun_ajaran'] ?? '2026/2027'));
$semester = $_GET['semester'] ?? ($settings['semester'] ?? 'Ganjil');

// Hitung Pertemuan Ke secara otomatis sesuai PRD
$pertemuanKe = 1;
if (!empty($selectedGuru) && !empty($selectedMapel) && !empty($selectedKelas)) {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM hasil_jurnal WHERE nama_guru = ? AND mata_pelajaran = ? AND kelas = ? AND tahun_ajaran = ? AND semester = ?");
    $stmtCount->execute([$selectedGuru, $selectedMapel, $selectedKelas, $tahunAjaran, $semester]);
    $pertemuanKe = (int)$stmtCount->fetchColumn() + 1;
}

// Ambil daftar presensi untuk konteks KBM ini: Tanggal, Nama Guru, Tahun Ajaran, Semester, Mapel, Kelas
$hasAbsenRecorded = false;
$absenMap = [];
$studentList = [];
$absentCount = 0;

if (!empty($selectedKelas) && !empty($selectedMapel) && !empty($tanggalHariIni)) {
    // 1. Cek apakah di tabel hasil_absen sudah ada data presensi yang sesuai
    $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM hasil_absen WHERE tanggal = ? AND kelas = ? AND mata_pelajaran = ? AND tahun_ajaran = ? AND semester = ?");
    $stmtChk->execute([$tanggalHariIni, $selectedKelas, $selectedMapel, $tahunAjaran, $semester]);
    $totalAbsenRow = (int)$stmtChk->fetchColumn();

    if ($totalAbsenRow > 0) {
        $hasAbsenRecorded = true;
        // Ambil data detail presensi
        $stmtAbsen = $pdo->prepare("SELECT nisn, nama_murid, keterangan, nama_guru FROM hasil_absen WHERE tanggal = ? AND kelas = ? AND mata_pelajaran = ? AND tahun_ajaran = ? AND semester = ?");
        $stmtAbsen->execute([$tanggalHariIni, $selectedKelas, $selectedMapel, $tahunAjaran, $semester]);
        while ($ab = $stmtAbsen->fetch()) {
            $cleanN = formatNISN($ab['nisn']);
            $absenMap[$cleanN] = $ab['keterangan'];
            $absenMap[trim($ab['nisn'])] = $ab['keterangan'];
        }
    }

    // Ambil daftar seluruh murid di kelas ini
    $stmtMurid = $pdo->prepare("SELECT * FROM data_murid WHERE kelas = ? ORDER BY nama_murid ASC");
    $stmtMurid->execute([$selectedKelas]);
    $allMurid = $stmtMurid->fetchAll();

    foreach ($allMurid as $m) {
        $cleanN = formatNISN($m['nisn']);
        $ket = $hasAbsenRecorded ? ($absenMap[$cleanN] ?? ($absenMap[trim($m['nisn'])] ?? 'H')) : '';
        $m['keterangan'] = $ket;
        if (!empty($ket) && $ket !== 'H') {
            $absentCount++;
        }
        $studentList[] = $m;
    }
}

// Simpan Formulir Jurnal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postTanggal = $_POST['tanggal'] ?? $tanggalHariIni;
    $postTA = $_POST['tahun_ajaran'] ?? $tahunAjaran;
    $postSemester = $_POST['semester'] ?? $semester;
    $postGuru = isSuperAdmin() ? trim($_POST['nama_guru'] ?? $selectedGuru) : $user['nama'];
    $postMapel = $_POST['mata_pelajaran'] ?? $selectedMapel;
    $postKelas = $_POST['kelas'] ?? $selectedKelas;
    $postJam = trim($_POST['jam_ke'] ?? 'Jam Ke 1-4');
    $postPertemuan = (int)($_POST['pertemuan_ke'] ?? $pertemuanKe);
    $postModel = trim($_POST['model_pembelajaran'] ?? '');
    $postMateri = trim($_POST['materi_pembelajaran'] ?? '');
    $postTuntas = $_POST['ketuntasan_materi'] ?? 'tuntas';
    $postKetTuntas = trim($_POST['keterangan_tuntas'] ?? '');
    
    // Murid Tidak Hadir (array multiselect dengan status)
    $postAbsent = '-';
    if (!empty($_POST['murid_tidak_hadir']) && is_array($_POST['murid_tidak_hadir'])) {
        $absentClean = array_filter(array_map('trim', $_POST['murid_tidak_hadir']));
        $postAbsent = !empty($absentClean) ? implode(', ', $absentClean) : '-';
    }

    // Upload Bukti (Hanya memilih salah satu jenis upload: Upload File ATAU Link Drive)
    $proofMode = $_POST['proof_mode_jurnal'] ?? ($_POST['proof_mode'] ?? 'file');
    $linkBukti = '';
    if ($proofMode === 'link') {
        $linkBukti = trim($_POST['link_bukti_drive'] ?? '');
    } else {
        $uploadedFiles = handleFileUpload('file_bukti', 'documents');
        if (!empty($uploadedFiles)) {
            $linkBukti = is_array($uploadedFiles) ? implode(', ', $uploadedFiles) : $uploadedFiles;
        }
    }

    if (!empty($postMateri)) {
        $stmtIns = $pdo->prepare("INSERT INTO hasil_jurnal (
            tanggal, tahun_ajaran, semester, nama_guru, mata_pelajaran, jam_ke, kelas, pertemuan_ke,
            model_pembelajaran, materi_pembelajaran, murid_tidak_hadir, ketuntasan_materi, keterangan_tuntas, link_bukti_jurnal
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmtIns->execute([
            $postTanggal, $postTA, $postSemester, $postGuru, $postMapel, $postJam, $postKelas, $postPertemuan,
            $postModel, $postMateri, $postAbsent, $postTuntas, $postKetTuntas, $linkBukti
        ]);

        setFlash('success', 'Jurnal Mengajar Pertemuan Ke-' . $postPertemuan . ' berhasil disimpan!');
        header("Location: laporan-jurnal.php");
        exit;
    } else {
        setFlash('danger', 'Materi Pembelajaran wajib diisi!');
    }
}

$pageTitle = 'Formulir Jurnal Mengajar';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/sidebar.php';
?>

<main class="content-wrapper">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Formulir Jurnal Mengajar</h4>
            <p class="text-muted small mb-0">Catat agenda mengajar harian, progres materi, dan dokumentasi KBM.</p>
        </div>
        <a href="laporan-jurnal.php" class="btn btn-outline-primary rounded-pill btn-sm">
            <i class="bi bi-journal-text me-1"></i> Lihat Rekap Jurnal
        </a>
    </div>

    <div class="card card-table p-4">
        <form method="POST" action="form-jurnal.php" enctype="multipart/form-data">
            <div class="row g-3">
                <!-- 1. Nama Guru -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Nama Guru</label>
                    <?php if (isSuperAdmin()): ?>
                        <select name="nama_guru" id="journalGuru" class="form-select" onchange="reloadJournalContext()">
                            <?php foreach ($guruList as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>" <?= $selectedGuru === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" name="nama_guru" id="journalGuru" class="form-control bg-light fw-semibold text-dark" value="<?= htmlspecialchars($user['nama']) ?>" readonly title="Terkunci sesuai akun login Anda">
                    <?php endif; ?>
                </div>

                <!-- 2. Tanggal -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Tanggal KBM</label>
                    <input type="date" name="tanggal" id="journalTanggal" class="form-control" value="<?= htmlspecialchars($tanggalHariIni) ?>" onchange="reloadJournalContext()" required>
                </div>

                <!-- 3. Tahun Ajaran & Semester -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-secondary">Tahun Ajaran</label>
                    <select name="tahun_ajaran" id="journalTA" class="form-select" onchange="reloadJournalContext()">
                        <?php foreach ($taList as $taItem): ?>
                            <option value="<?= htmlspecialchars($taItem) ?>" <?= $tahunAjaran === $taItem ? 'selected' : '' ?>><?= htmlspecialchars($taItem) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-secondary">Semester</label>
                    <select name="semester" id="journalSemester" class="form-select" onchange="reloadJournalContext()">
                        <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                    </select>
                </div>

                <!-- 4. Mata Pelajaran & Kelas -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-secondary">Mata Pelajaran</label>
                    <select name="mata_pelajaran" id="journalMapel" class="form-select" onchange="reloadJournalContext()">
                        <?php foreach ($mapelList as $mp): ?>
                            <option value="<?= htmlspecialchars($mp) ?>" <?= $selectedMapel === $mp ? 'selected' : '' ?>><?= htmlspecialchars($mp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-secondary">Kelas</label>
                    <select name="kelas" id="journalKelas" class="form-select" onchange="reloadJournalContext()">
                        <?php foreach ($kelasList as $kls): ?>
                            <option value="<?= htmlspecialchars($kls) ?>" <?= $selectedKelas === $kls ? 'selected' : '' ?>><?= htmlspecialchars($kls) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 5. Jam Ke & Pertemuan Ke (Auto-Fill) -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">Jam Ke</label>
                    <input type="text" name="jam_ke" class="form-control" placeholder="Contoh: Jam Ke 1-4" value="Jam Ke 1-4" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small text-secondary">
                        Pertemuan Ke <span class="badge bg-success-subtle text-success ms-1">Auto-Fill Terhitung</span>
                    </label>
                    <input type="number" name="pertemuan_ke" id="journalPertemuanKe" class="form-control fw-bold" value="<?= $pertemuanKe ?>" min="1" required>
                </div>

                <!-- 6. Model Pembelajaran -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Model Pembelajaran</label>
                    <input type="text" name="model_pembelajaran" class="form-control" placeholder="Contoh: Project-Based Learning (PjBL) / Discovery Learning / Problem-Based Learning">
                </div>

                <!-- 7. Materi Pembelajaran -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Materi Pembelajaran (Rangkuman KBM)</label>
                    <textarea name="materi_pembelajaran" class="form-control" rows="3" placeholder="Tuliskan ringkasan materi, kompetensi dasar, serta kegiatan pembelajaran hari ini..." required></textarea>
                </div>

                <!-- 8. Ketuntasan Materi & Keterangan -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Ketuntasan Materi</label>
                    <select name="ketuntasan_materi" class="form-select">
                        <option value="tuntas">Tuntas</option>
                        <option value="berlanjut">Berlanjut</option>
                        <option value="-">-</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold small text-secondary">Keterangan Ketuntasan</label>
                    <input type="text" name="keterangan_tuntas" class="form-control" placeholder="Catatan capaian siswa / tindak lanjut materi selanjutnya">
                </div>

                <!-- 9. Murid Tidak Hadir (Otomatis Terisi & Menyesuaikan Tanggal, Guru, TA, Semester, Mapel, Kelas) -->
                <div class="col-12">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                        <label class="form-label fw-semibold small text-secondary mb-0">
                            <i class="bi bi-people-fill me-1 text-primary"></i> Murid Tidak Hadir (Kelas <?= htmlspecialchars($selectedKelas) ?>)
                        </label>
                        <?php if ($hasAbsenRecorded): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle small">
                                <i class="bi bi-check-circle-fill me-1"></i> Tersinkron Presensi Tanggal <?= htmlspecialchars($tanggalHariIni) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle small">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Presensi Belum Dilakukan
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$hasAbsenRecorded): ?>
                        <!-- Informasi Jika Belum Melakukan Presensi Sesuai Permintaan User -->
                        <div class="alert alert-warning border border-warning-subtle rounded-4 p-3 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-3 shadow-xs">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning bg-opacity-25 d-flex align-items-center justify-content-center p-2 text-warning fs-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Presensi Belum Dilakukan!</h6>
                                    <p class="text-muted small mb-0">
                                        Data kehadiran untuk <strong>Kelas <?= htmlspecialchars($selectedKelas) ?></strong> pada mata pelajaran <strong><?= htmlspecialchars($selectedMapel) ?></strong> tanggal <strong><?= formatDateIndo($tanggalHariIni) ?></strong> belum tercatat. Silakan lakukan presensi terlebih dahulu agar data siswa yang tidak hadir otomatis tersinkronisasi ke jurnal mengajar.
                                    </p>
                                </div>
                            </div>
                            <div>
                                <a href="form-absen.php?kelas=<?= urlencode($selectedKelas) ?>&mapel=<?= urlencode($selectedMapel) ?>&tanggal=<?= urlencode($tanggalHariIni) ?>" class="btn btn-warning rounded-pill px-4 btn-sm fw-bold shadow-sm text-nowrap">
                                    <i class="bi bi-card-checklist me-1"></i> Isi Presensi Sekarang
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Jika Sudah Melakukan Presensi: Tampilkan Data -->
                        <div class="card p-3 bg-light-subtle border rounded-3 shadow-xs">
                            <?php if ($absentCount === 0): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 bg-success-subtle border border-success-subtle rounded-3 text-success mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-check2-circle fs-5"></i>
                                        <span class="small fw-semibold">Seluruh siswa hadir lengkap (Nihil siswa tidak hadir).</span>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm text-success p-0 text-decoration-none fw-semibold" data-bs-toggle="collapse" data-bs-target="#collapseAllMurid">
                                        Pilih Manual <i class="bi bi-chevron-down ms-1"></i>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <div class="<?= ($absentCount === 0) ? 'collapse' : '' ?>" id="collapseAllMurid">
                                <div class="row g-2" style="max-height: 260px; overflow-y: auto;">
                                    <?php 
                                    $statusNames = [
                                        'I' => 'Izin',
                                        'S' => 'Sakit',
                                        'A' => 'Alpha',
                                        'D' => 'Dispen'
                                    ];
                                    foreach ($studentList as $sl): 
                                        $ket = trim($sl['keterangan'] ?? '');
                                        $isAbsent = (!empty($ket) && $ket !== 'H');
                                        $statusText = $statusNames[$ket] ?? ($isAbsent ? $ket : 'Alpha');
                                        $initVal = htmlspecialchars($sl['nama_murid']) . ' (' . $statusText . ')';
                                    ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="p-2 border rounded-3 bg-white d-flex align-items-center justify-content-between gap-2 shadow-xs <?= $isAbsent ? 'border-danger-subtle bg-danger-subtle' : '' ?>">
                                                <div class="form-check mb-0 text-truncate">
                                                    <input class="form-check-input mth-checkbox" type="checkbox" name="murid_tidak_hadir[]" 
                                                           value="<?= $isAbsent ? $initVal : htmlspecialchars($sl['nama_murid']) ?>" 
                                                           id="mth_<?= $sl['nisn'] ?>" 
                                                           data-name="<?= htmlspecialchars($sl['nama_murid']) ?>"
                                                           data-nisn="<?= $sl['nisn'] ?>"
                                                           <?= $isAbsent ? 'checked' : '' ?>
                                                           onchange="updateAbsentVal(this)">
                                                    <label class="form-check-label small fw-semibold text-dark" for="mth_<?= $sl['nisn'] ?>" title="<?= htmlspecialchars($sl['nama_murid']) ?>">
                                                        <?= htmlspecialchars($sl['nama_murid']) ?>
                                                    </label>
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <select class="form-select form-select-sm py-0 px-1 mth-select" id="mth_sel_<?= $sl['nisn'] ?>" 
                                                            style="font-size: 0.72rem; width: 78px; border-radius: 6px; <?= $isAbsent ? '' : 'opacity: 0.55;' ?>"
                                                            onchange="updateAbsentSel(this, '<?= $sl['nisn'] ?>')">
                                                        <option value="Sakit" <?= ($ket === 'S') ? 'selected' : '' ?>>Sakit</option>
                                                        <option value="Izin" <?= ($ket === 'I') ? 'selected' : '' ?>>Izin</option>
                                                        <option value="Alpha" <?= ($ket === 'A' || empty($ket) || $ket === 'H') ? 'selected' : '' ?>>Alpha</option>
                                                        <option value="Dispen" <?= ($ket === 'D') ? 'selected' : '' ?>>Dispen</option>
                                                    </select>
                                                    <?php if ($isAbsent): ?>
                                                        <span class="badge bg-danger text-white border border-danger" style="font-size: 0.65rem;" title="Otomatis dari Presensi">Absen</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 10. Upload Bukti Jurnal (Pilih Salah Satu via Minimalist Switch) -->
                <div class="col-12">
                    <div class="card p-3 border rounded-3 bg-light-subtle">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary mb-0">
                                <i class="bi bi-paperclip me-1"></i> Bukti Dokumentasi Jurnal Mengajar
                            </label>
                            <!-- Minimalist Switch / Segmented Control -->
                            <div class="btn-group btn-group-sm p-1 bg-white border rounded-pill shadow-xs" role="group">
                                <input type="radio" class="btn-check" name="proof_mode_jurnal" id="proof_mode_file_jurnal" value="file" checked onchange="toggleProofInput('jurnal', 'file')">
                                <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="proof_mode_file_jurnal">
                                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload File
                                </label>

                                <input type="radio" class="btn-check" name="proof_mode_jurnal" id="proof_mode_link_jurnal" value="link" onchange="toggleProofInput('jurnal', 'link')">
                                <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 py-1 fw-semibold" for="proof_mode_link_jurnal">
                                    <i class="bi bi-link-45deg me-1"></i> Link Drive
                                </label>
                            </div>
                        </div>

                        <div id="proof_file_container_jurnal">
                            <input type="file" name="file_bukti[]" multiple class="form-control bg-white" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih satu atau beberapa file foto / dokumen pendukung KBM (Maks 10MB per file, dapat memilih lebih dari 1 file sekaligus).</div>
                        </div>

                        <div id="proof_link_container_jurnal" style="display: none;">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-google text-danger"></i></span>
                                <input type="url" name="link_bukti_drive" class="form-control bg-white" placeholder="https://drive.google.com/..." value="" disabled>
                            </div>
                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Salin tautan folder atau file Google Drive bukti kegiatan mengajar.</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 pt-3 border-top text-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                        <i class="bi bi-save2-fill me-1"></i> Simpan Jurnal Mengajar
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
function reloadJournalContext() {
    const kls = document.getElementById('journalKelas')?.value || '';
    const mapel = document.getElementById('journalMapel')?.value || '';
    const tgl = document.getElementById('journalTanggal')?.value || '';
    const guru = document.getElementById('journalGuru')?.value || '';
    const ta = document.getElementById('journalTA')?.value || '';
    const sem = document.getElementById('journalSemester')?.value || '';
    
    window.location.href = `form-jurnal.php?kelas=${encodeURIComponent(kls)}&mapel=${encodeURIComponent(mapel)}&tanggal=${encodeURIComponent(tgl)}&guru=${encodeURIComponent(guru)}&ta=${encodeURIComponent(ta)}&semester=${encodeURIComponent(sem)}`;
}

function updateAbsentVal(chk) {
    const nisn = chk.dataset.nisn;
    const name = chk.dataset.name;
    const sel = document.getElementById('mth_sel_' + nisn);
    if (chk.checked) {
        if (sel) sel.style.opacity = '1';
        chk.value = `${name} (${sel ? sel.value : 'Alpha'})`;
    } else {
        if (sel) sel.style.opacity = '0.55';
        chk.value = name;
    }
}

function updateAbsentSel(sel, nisn) {
    const chk = document.getElementById('mth_' + nisn);
    const name = chk.dataset.name;
    chk.value = `${name} (${sel.value})`;
    if (!chk.checked) {
        chk.checked = true;
        sel.style.opacity = '1';
    }
}

function refreshMeetingNo() {
    const guru = document.getElementById('journalGuru')?.value || '';
    const mapel = document.getElementById('journalMapel')?.value || '';
    const kelas = document.getElementById('journalKelas')?.value || '';
    const ta = document.getElementById('journalTA')?.value || '';
    const semester = document.getElementById('journalSemester')?.value || '';

    fetch(`api/get_meeting_no.php?guru=${encodeURIComponent(guru)}&mapel=${encodeURIComponent(mapel)}&kelas=${encodeURIComponent(kelas)}&ta=${encodeURIComponent(ta)}&semester=${encodeURIComponent(semester)}`)
        .then(r => r.json())
        .then(res => {
            if (res && res.meeting_no) {
                document.getElementById('journalPertemuanKe').value = res.meeting_no;
            }
        })
        .catch(e => console.error(e));
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
