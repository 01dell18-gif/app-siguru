<?php
// print-laporan.php - Halaman Cetak & Preview PDF di Tab Baru SiGuru
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helper.php';

requireAuth();
$settings = getSettings();
$pdo = getDBConnection();
$currentUser = currentUser();

$type = $_GET['type'] ?? 'absen';
$orientation = $_GET['orientation'] ?? 'landscape'; // portrait atau landscape

// Filter parameter
$ta = $_GET['ta'] ?? $settings['tahun_ajaran'];
$semester = $_GET['semester'] ?? $settings['semester'];
$kelas = $_GET['kelas'] ?? '';
$mapel = $_GET['mapel'] ?? '';
$murid = $_GET['murid'] ?? '';
$ekstra = $_GET['ekstra'] ?? '';

// Filter Nama Guru: Selain Super Admin, langsung terisi nama guru sesuai hak akses akun login
if (!isSuperAdmin()) {
    $guru = $currentUser['nama'] ?? '';
} else {
    $guru = $_GET['guru'] ?? '';
}

$laporanTitle = 'LAPORAN REKAPITULASI';
$subTitle = "Tahun Ajaran: $ta | Semester: $semester";
$dataRows = [];
$sumberDrive = '';
$rekapMurid = [];
$totalAllH = 0;
$totalAllS = 0;
$totalAllI = 0;
$totalAllA = 0;
$totalAllD = 0;
$totalAllTotal = 0;
$targetEfektif = 16;
$totalJurnal = 0;
$persenEfektif = 0;

switch ($type) {
    case 'absen':
        $laporanTitle = 'LAPORAN KEHADIRAN / PRESENSI SISWA';
        $sumberDrive = $settings['folder_foto'] ?? '';
        $sql = "SELECT * FROM hasil_absen WHERE tahun_ajaran = ? AND semester = ?";
        $params = [$ta, $semester];
        if (!empty($kelas)) { $sql .= " AND kelas = ?"; $params[] = $kelas; $subTitle .= " | Kelas: $kelas"; }
        if (!empty($mapel)) { $sql .= " AND mata_pelajaran = ?"; $params[] = $mapel; $subTitle .= " | Mapel: $mapel"; }
        if (!empty($guru))  { $sql .= " AND nama_guru = ?"; $params[] = $guru; $subTitle .= " | Guru: $guru"; }
        if (!empty($murid)) { $sql .= " AND (nama_murid LIKE ? OR nama_murid = ?)"; $params[] = "%$murid%"; $params[] = $murid; $subTitle .= " | Siswa: $murid"; }
        $sql .= " ORDER BY tanggal DESC, nama_murid ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataRows = $stmt->fetchAll();

        // 1. Rekapitulasi Persentase Kehadiran Per Murid
        $rekapMurid = [];
        $totalAllH = 0; $totalAllS = 0; $totalAllI = 0; $totalAllA = 0; $totalAllD = 0; $totalAllTotal = 0;
        foreach ($dataRows as $r) {
            $mKey = trim($r['nama_murid']);
            if (!isset($rekapMurid[$mKey])) {
                $rekapMurid[$mKey] = [
                    'nisn' => $r['nisn'] ?? '-',
                    'nama_murid' => $r['nama_murid'],
                    'jenis_kelamin' => $r['jenis_kelamin'] ?? '-',
                    'kelas' => $r['kelas'] ?? '-',
                    'h' => 0,
                    's' => 0,
                    'i' => 0,
                    'a' => 0,
                    'd' => 0,
                    'total' => 0
                ];
            }
            $ket = strtoupper(trim($r['keterangan'] ?? ''));
            if (in_array($ket, ['H', 'HADIR'])) {
                $rekapMurid[$mKey]['h']++;
                $totalAllH++;
            } elseif (in_array($ket, ['S', 'SAKIT'])) {
                $rekapMurid[$mKey]['s']++;
                $totalAllS++;
            } elseif (in_array($ket, ['I', 'IZIN'])) {
                $rekapMurid[$mKey]['i']++;
                $totalAllI++;
            } elseif (in_array($ket, ['A', 'ALPA', 'ALPHA'])) {
                $rekapMurid[$mKey]['a']++;
                $totalAllA++;
            } elseif (in_array($ket, ['D', 'DISPEN', 'DISPENSASI'])) {
                $rekapMurid[$mKey]['d']++;
                $totalAllD++;
            }
            $rekapMurid[$mKey]['total']++;
            $totalAllTotal++;
        }
        ksort($rekapMurid, SORT_NATURAL | SORT_FLAG_CASE);
        break;

    case 'jurnal':
        $laporanTitle = 'LAPORAN JURNAL MENGAJAR GURU';
        $sumberDrive = $settings['folder_jurnal'] ?? '';
        $targetEfektif = (int)($_GET['target_efektif'] ?? 16);
        if ($targetEfektif < 1) $targetEfektif = 16;
        $sql = "SELECT * FROM hasil_jurnal WHERE tahun_ajaran = ? AND semester = ?";
        $params = [$ta, $semester];
        if (!empty($kelas)) { $sql .= " AND kelas = ?"; $params[] = $kelas; $subTitle .= " | Kelas: $kelas"; }
        if (!empty($mapel)) { $sql .= " AND mata_pelajaran = ?"; $params[] = $mapel; $subTitle .= " | Mapel: $mapel"; }
        if (!empty($guru))  { $sql .= " AND nama_guru = ?"; $params[] = $guru; $subTitle .= " | Guru: $guru"; }
        $sql .= " ORDER BY tanggal DESC, pertemuan_ke ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataRows = $stmt->fetchAll();
        $totalJurnal = count($dataRows);
        $persenEfektif = round(($totalJurnal / $targetEfektif) * 100);
        break;

    case 'wali':
        $laporanTitle = 'LAPORAN KEGIATAN WALI KELAS';
        $sumberDrive = $settings['folder_wali'] ?? '';
        $sql = "SELECT * FROM hasil_wali WHERE tahun_ajaran = ? AND semester = ?";
        $params = [$ta, $semester];
        if (!empty($kelas)) { $sql .= " AND kelas = ?"; $params[] = $kelas; $subTitle .= " | Kelas: $kelas"; }
        if (!empty($guru))  { $sql .= " AND nama_wali_kelas = ?"; $params[] = $guru; $subTitle .= " | Wali: $guru"; }
        $sql .= " ORDER BY tanggal DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataRows = $stmt->fetchAll();
        break;

    case 'guruwali':
        $laporanTitle = 'LAPORAN BIMBINGAN GURU WALI';
        $sumberDrive = $settings['folder_guru_wali'] ?? '';
        $sql = "SELECT * FROM hasil_guru_wali WHERE tahun_ajaran = ? AND semester = ?";
        $params = [$ta, $semester];
        if (!empty($guru)) { $sql .= " AND nama_guru_wali = ?"; $params[] = $guru; $subTitle .= " | Guru Wali: $guru"; }
        $sql .= " ORDER BY tanggal DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataRows = $stmt->fetchAll();
        break;

    case 'ekstra':
        $laporanTitle = 'LAPORAN KEGIATAN EKSTRAKURIKULER';
        $sumberDrive = $settings['folder_ekstra'] ?? '';
        $sql = "SELECT * FROM hasil_ekstra WHERE tahun_ajaran = ? AND semester = ?";
        $params = [$ta, $semester];
        if (!empty($ekstra)) { $sql .= " AND kegiatan_ekstra LIKE ?"; $params[] = "%$ekstra%"; $subTitle .= " | Ekstra: $ekstra"; }
        if (!empty($guru))   { $sql .= " AND pembina_ekstra = ?"; $params[] = $guru; $subTitle .= " | Pembina: $guru"; }
        $sql .= " ORDER BY tanggal DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataRows = $stmt->fetchAll();
        break;
}

// Data penandatangan guru / pembina pada lembar pengesahan
$ttdNama = !empty($guru) ? $guru : $currentUser['nama'];
$ttdNip = $currentUser['nip'] ?: '-';
if (!empty($guru)) {
    $stmtG = $pdo->prepare("SELECT nip FROM data_guru WHERE nama_guru = ? LIMIT 1");
    $stmtG->execute([$guru]);
    $gNip = $stmtG->fetchColumn();
    if (!empty($gNip)) {
        $ttdNip = $gNip;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($laporanTitle) ?> - SIGURU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @page {
            size: <?= $orientation === 'portrait' ? 'A4 portrait' : 'A4 landscape' ?>;
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
        }
        .page-container {
            max-width: <?= $orientation === 'portrait' ? '850px' : '1150px' ?>;
            margin: 15px auto;
            background: #ffffff;
            padding: 22px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .table-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-top: 4px;
            margin-bottom: 12px;
        }
        .table-print th, .table-print td {
            border: 1px solid #1e293b;
            padding: 5px 6px;
            vertical-align: middle;
            line-height: 1.25;
        }
        .table-print th {
            background-color: #f1f5f9 !important;
            font-weight: bold;
            text-align: center;
            font-size: 9pt;
            padding: 5px 6px;
        }
        .table-print .nowrap {
            white-space: nowrap !important;
        }
        .toolbar-preview {
            max-width: <?= $orientation === 'portrait' ? '850px' : '1150px' ?>;
            margin: 15px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media print {
            body {
                background: none !important;
                padding: 0 !important;
            }
            .toolbar-preview, .no-print {
                display: none !important;
            }
            .page-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Toolbar Kontrol Cetak & Layout -->
    <div class="toolbar-preview no-print">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="badge bg-primary px-3 py-2 fs-6">Preview Laporan SiGuru</span>
            <!-- Pilihan Layout Portrait / Landscape Sesuai PRD -->
            <div class="btn-group btn-group-sm">
                <a href="?<?= http_build_query(array_merge($_GET, ['orientation' => 'portrait'])) ?>" class="btn <?= $orientation === 'portrait' ? 'btn-dark' : 'btn-outline-dark' ?>">
                    <i class="bi bi-file-earmark me-1"></i> Portrait
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['orientation' => 'landscape'])) ?>" class="btn <?= $orientation === 'landscape' ? 'btn-dark' : 'btn-outline-dark' ?>">
                    <i class="bi bi-file-earmark-landscape me-1"></i> Landscape
                </a>
            </div>

            <!-- Pilihan Cepat Skala Ukuran Kop Surat -->
            <div class="btn-group btn-group-sm ms-md-2" id="groupKopSize">
                <button type="button" class="btn btn-outline-secondary" onclick="setKopHeight(70)" title="Ukuran Kop 70px (Ekstra Ringkas)">
                    <i class="bi bi-zoom-out me-1"></i>Kop Kecil
                </button>
                <button type="button" class="btn btn-dark active" id="btnKopDefault" onclick="setKopHeight(85)" title="Ukuran Kop 85px (Proporsional / Rekomendasi)">
                    Kop Proporsional
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="setKopHeight(105)" title="Ukuran Kop 105px (Sedang)">
                    Kop Sedang
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="setKopHeight(130)" title="Ukuran Kop 130px (Besar)">
                    <i class="bi bi-zoom-in me-1"></i>Kop Besar
                </button>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                <i class="bi bi-printer-fill me-1"></i> Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary btn-sm px-3">
                <i class="bi bi-x-circle me-1"></i> Tutup
            </button>
        </div>
    </div>

    <!-- Halaman Dokumen Cetak -->
    <div class="page-container">
        <!-- 1. Identitas Kop Surat Resmi Sesuai PRD -->
        <?php include __DIR__ . '/views/kop_surat.php'; ?>

        <!-- 2. Isi Tabel Laporan Sesuai PRD -->
        <?php if ($type === 'absen'): ?>
            <!-- 1. Persentase Kehadiran Per Murid -->
            <div class="fw-bold mb-1 mt-2 text-dark" style="font-size: 10pt; font-family: 'Times New Roman', Times, serif;">
                1. Persentase Kehadiran Per Murid
            </div>
            <table class="table-print mb-4">
                <thead>
                    <tr>
                        <th style="width: 4%;">No</th>
                        <th style="width: 13%;">NISN</th>
                        <th>Nama Murid</th>
                        <th style="width: 5%;">L/P</th>
                        <th style="width: 9%;">Kelas</th>
                        <th style="width: 5.5%;">H</th>
                        <th style="width: 5.5%;">S</th>
                        <th style="width: 5.5%;">I</th>
                        <th style="width: 5.5%;">A</th>
                        <th style="width: 5.5%;">D</th>
                        <th style="width: 7%;">Total</th>
                        <th style="width: 11%;">% Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekapMurid)): ?>
                        <tr><td colspan="12" class="text-center py-2">Tidak ada data rekap kehadiran murid.</td></tr>
                    <?php else: 
                        $noM = 1;
                        foreach ($rekapMurid as $m): 
                            $pct = $m['total'] > 0 ? round(($m['h'] / $m['total']) * 100) : 0;
                    ?>
                        <tr>
                            <td class="text-center"><?= $noM++ ?></td>
                            <td class="text-center nowrap font-monospace"><?= htmlspecialchars(formatNISN($m['nisn'])) ?></td>
                            <td><?= htmlspecialchars($m['nama_murid']) ?></td>
                            <td class="text-center nowrap"><?= htmlspecialchars($m['jenis_kelamin']) ?></td>
                            <td class="text-center nowrap"><?= htmlspecialchars($m['kelas']) ?></td>
                            <td class="text-center"><?= $m['h'] ?></td>
                            <td class="text-center"><?= $m['s'] ?></td>
                            <td class="text-center"><?= $m['i'] ?></td>
                            <td class="text-center"><?= $m['a'] ?></td>
                            <td class="text-center"><?= $m['d'] ?></td>
                            <td class="text-center fw-bold"><?= $m['total'] ?></td>
                            <td class="text-center fw-bold"><?= $pct ?>%</td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rekapMurid)): 
                    $avgPersen = $totalAllTotal > 0 ? round(($totalAllH / $totalAllTotal) * 100) : 0;
                ?>
                <tfoot>
                    <tr style="background-color: #f1f5f9; font-weight: bold;">
                        <td colspan="5" class="text-center">Total Keseluruhan</td>
                        <td class="text-center"><?= $totalAllH ?></td>
                        <td class="text-center"><?= $totalAllS ?></td>
                        <td class="text-center"><?= $totalAllI ?></td>
                        <td class="text-center"><?= $totalAllA ?></td>
                        <td class="text-center"><?= $totalAllD ?></td>
                        <td class="text-center"><?= $totalAllTotal ?></td>
                        <td class="text-center"><?= $avgPersen ?>%</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>

            <!-- 2. Rincian Histori Absensi Harian -->
            <div class="fw-bold mb-1 mt-3 text-dark" style="font-size: 10pt; font-family: 'Times New Roman', Times, serif;">
                2. Rincian Histori Absensi Harian
            </div>
            <table class="table-print">
                <thead>
                    <tr>
                        <th style="width: 4%;">No</th>
                        <th style="width: 13%;">Tanggal</th>
                        <th style="width: 11%;">NISN</th>
                        <th style="width: 18%;">Nama Murid</th>
                        <th style="width: 5%;">L/P</th>
                        <th style="width: 8%;">Kelas</th>
                        <th style="width: 18%;">Mata Pelajaran</th>
                        <th style="width: 6%;">Status</th>
                        <th style="width: 17%;">Guru Pengampu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataRows)): ?>
                        <tr><td colspan="9" class="text-center py-2">Tidak ada data presensi yang sesuai.</td></tr>
                    <?php else: 
                        $no = 1;
                        foreach ($dataRows as $r): 
                    ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="text-center nowrap"><?= formatDateIndo($r['tanggal']) ?></td>
                            <td class="text-center nowrap font-monospace"><?= htmlspecialchars(formatNISN($r['nisn'])) ?></td>
                            <td><?= htmlspecialchars($r['nama_murid']) ?></td>
                            <td class="text-center nowrap"><?= $r['jenis_kelamin'] ?></td>
                            <td class="text-center nowrap"><?= htmlspecialchars($r['kelas']) ?></td>
                            <td><?= htmlspecialchars($r['mata_pelajaran']) ?></td>
                            <td class="text-center fw-bold nowrap"><?= $r['keterangan'] ?></td>
                            <td><?= htmlspecialchars($r['nama_guru']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php if ($type === 'jurnal'): ?>
                <!-- Ringkasan Efektivitas Mengajar Sesuai Permintaan User (Ukuran Teks Normal & Serasi) -->
                <div class="mb-2 rounded" style="background-color: #f8fafc; border: 1px solid #1e293b; padding: 5px 12px; font-family: 'Times New Roman', Times, serif; font-size: 9pt;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="fw-bold text-dark" style="font-size: 9pt;">
                            <i class="bi bi-calendar-check me-1"></i> Data Efektivitas Mengajar Guru
                        </div>
                        <div class="d-flex align-items-center gap-3" style="font-size: 9pt;">
                            <span>Target Hari Efektif: <strong><?= $targetEfektif ?> Hari</strong></span>
                            <span>Total Jurnal Terlaksana: <strong style="color: #1d4ed8;"><?= $totalJurnal ?> Jurnal</strong></span>
                            <span>Persentase Efektivitas: <strong class="text-success"><?= $persenEfektif ?>%</strong></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <table class="table-print">
            <?php if ($type === 'jurnal'): ?>
                <thead>
                    <tr>
                        <th style="width: 3.5%;">No</th>
                        <th style="width: 13%;">Tanggal</th>
                        <th style="width: 18%;">Guru & Mapel</th>
                        <th style="width: 8%;">Kelas</th>
                        <th style="width: 4.5%;">Ke-</th>
                        <th style="width: 25%;">Materi Pembelajaran</th>
                        <th style="width: 13%;">Murid Tdk Hadir</th>
                        <th style="width: 7%;">Status</th>
                        <th style="width: 8%;">Bukti / Berkas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataRows)): ?>
                        <tr><td colspan="9" class="text-center py-2">Tidak ada data jurnal mengajar.</td></tr>
                    <?php else: 
                        $no = 1;
                        foreach ($dataRows as $r): 
                    ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="text-center nowrap"><?= formatDateIndo($r['tanggal']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($r['nama_guru']) ?></strong><br>
                                <small class="text-secondary"><?= htmlspecialchars($r['mata_pelajaran']) ?> (<?= htmlspecialchars($r['jam_ke']) ?>)</small>
                            </td>
                            <td class="text-center nowrap"><?= htmlspecialchars($r['kelas']) ?></td>
                            <td class="text-center nowrap"><?= $r['pertemuan_ke'] ?></td>
                            <td>
                                <?php if (!empty($r['model_pembelajaran'])): ?>
                                    <small class="text-muted d-block">[<?= htmlspecialchars($r['model_pembelajaran']) ?>]</small>
                                <?php endif; ?>
                                <?= nl2br(htmlspecialchars($r['materi_pembelajaran'])) ?>
                            </td>
                            <td><small><?= htmlspecialchars($r['murid_tidak_hadir'] ?: '-') ?></small></td>
                            <td class="text-center text-capitalize nowrap"><?= htmlspecialchars($r['ketuntasan_materi']) ?></td>
                            <td class="text-center nowrap">
                                <?= renderBuktiPrintLinks($r['link_bukti_jurnal']) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($type === 'wali'): ?>
                <thead>
                    <tr>
                        <th style="width: 4%;">No</th>
                        <th style="width: 14%;">Tanggal</th>
                        <th style="width: 9%;">Kelas</th>
                        <th style="width: 18%;">Wali Kelas</th>
                        <th style="width: 45%;">Deskripsi Agenda Kegiatan</th>
                        <th style="width: 10%;">Bukti / Berkas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataRows)): ?>
                        <tr><td colspan="6" class="text-center py-2">Tidak ada data kegiatan wali kelas.</td></tr>
                    <?php else: 
                        $no = 1;
                        foreach ($dataRows as $r): 
                    ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="text-center nowrap"><?= formatDateIndo($r['tanggal']) ?></td>
                            <td class="text-center font-monospace nowrap"><?= htmlspecialchars($r['kelas']) ?></td>
                            <td><?= htmlspecialchars($r['nama_wali_kelas']) ?></td>
                            <td><?= nl2br(htmlspecialchars($r['deskripsi_kegiatan'])) ?></td>
                            <td class="text-center nowrap">
                                <?= renderBuktiPrintLinks($r['link_bukti_wali']) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($type === 'guruwali'): ?>
                <thead>
                    <tr>
                        <th style="width: 3.5%;">No</th>
                        <th style="width: 13.5%;">Tanggal</th>
                        <th style="width: 15%;">Guru Wali</th>
                        <th style="width: 14%;">Siswa Bimbingan</th>
                        <th style="width: 8%;">Kategori</th>
                        <th style="width: 20%;">Topik Masalah & Konseling</th>
                        <th style="width: 17%;">Tindak Lanjut</th>
                        <th style="width: 9%;">Bukti / Berkas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataRows)): ?>
                        <tr><td colspan="8" class="text-center py-2">Tidak ada catatan bimbingan guru wali.</td></tr>
                    <?php else: 
                        $no = 1;
                        foreach ($dataRows as $r): 
                    ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="text-center nowrap"><?= formatDateIndo($r['tanggal']) ?></td>
                            <td><?= htmlspecialchars($r['nama_guru_wali']) ?></td>
                            <td><strong><?= htmlspecialchars($r['nama_murid']) ?></strong></td>
                            <td class="text-center nowrap"><?= htmlspecialchars($r['kategori']) ?></td>
                            <td><?= nl2br(htmlspecialchars($r['topik_masalah'])) ?></td>
                            <td><?= nl2br(htmlspecialchars($r['tindak_lanjut'])) ?></td>
                            <td class="text-center nowrap">
                                <?= renderBuktiPrintLinks($r['file_bukti']) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($type === 'ekstra'): ?>
                <thead>
                    <tr>
                        <th style="width: 4%;">No</th>
                        <th style="width: 13.5%;">Tanggal</th>
                        <th style="width: 13.5%;">Cabang Ekstra</th>
                        <th style="width: 16%;">Pembina</th>
                        <th style="width: 16%;">Peserta Hadir</th>
                        <th style="width: 18%;">Agenda Latihan</th>
                        <th style="width: 10%;">Keterangan</th>
                        <th style="width: 9%;">Bukti / Berkas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataRows)): ?>
                        <tr><td colspan="8" class="text-center py-2">Tidak ada catatan kegiatan ekstra.</td></tr>
                    <?php else: 
                        $no = 1;
                        foreach ($dataRows as $r): 
                    ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="text-center nowrap"><?= formatDateIndo($r['tanggal']) ?></td>
                            <td class="nowrap"><strong><?= htmlspecialchars($r['kegiatan_ekstra']) ?></strong></td>
                            <td><?= htmlspecialchars($r['pembina_ekstra']) ?></td>
                            <td><small><?= htmlspecialchars($r['nama_murid']) ?></small></td>
                            <td><?= nl2br(htmlspecialchars($r['kegiatan_ekstra'])) ?></td>
                            <td><small><?= nl2br(htmlspecialchars($r['keterangan'] ?: '-')) ?></small></td>
                            <td class="text-center nowrap">
                                <?= renderBuktiPrintLinks($r['link_bukti_ekstra']) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            <?php endif; ?>
        </table>
        <?php endif; ?>

        <!-- Tanggal Cetak & TTD Pengesahan Resmi -->
        <div class="row mt-3 pt-1">
            <?php if ($type !== 'absen'): ?>
                <div class="col-6 text-center">
                    Mengetahui,<br>
                    Kepala Sekolah<br>
                    <?php if (!empty($settings['ttd_kepsek'])): ?>
                        <div class="d-flex align-items-center justify-content-center my-1" style="height: 55px;">
                            <img src="<?= htmlspecialchars(formatPhotoUrl($settings['ttd_kepsek'])) ?>" 
                                 alt="TTD Kepala Sekolah" 
                                 style="max-height: 52px; max-width: 130px; object-fit: contain;"
                                 referrerpolicy="no-referrer">
                        </div>
                    <?php else: ?>
                        <div style="height: 50px;"></div>
                    <?php endif; ?>
                    <strong><?= htmlspecialchars($settings['kepala_sekolah'] ?? 'Kepala Sekolah') ?></strong><br>
                    NIP. <?= htmlspecialchars($settings['nip_kepala_sekolah'] ?? '-') ?>
                </div>
                <div class="col-6 text-center">
                    <?= htmlspecialchars(!empty($settings['kabupaten']) ? $settings['kabupaten'] : 'Kabupaten') ?>, <?= formatDateIndo(date('Y-m-d')) ?><br>
                    Guru / Guru Mapel,<br>
                    <?php if (!empty($settings['ttd_kepsek'])): ?>
                        <div style="height: 55px;" class="my-1"></div>
                    <?php else: ?>
                        <div style="height: 50px;"></div>
                    <?php endif; ?>
                    <strong><?= htmlspecialchars($ttdNama) ?></strong><br>
                    NIP. <?= htmlspecialchars($ttdNip) ?>
                </div>
            <?php else: ?>
                <!-- Khusus Laporan Presensi Siswa: TTD Kepsek Dihilangkan Sesuai Permintaan User -->
                <div class="col-6"></div>
                <div class="col-6 text-center">
                    <?= htmlspecialchars(!empty($settings['kabupaten']) ? $settings['kabupaten'] : 'Kabupaten') ?>, <?= formatDateIndo(date('Y-m-d')) ?><br>
                    Guru / Guru Mapel,<br>
                    <div style="height: 50px;"></div>
                    <strong><?= htmlspecialchars($ttdNama) ?></strong><br>
                    NIP. <?= htmlspecialchars($ttdNip) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function setKopHeight(h) {
        const img = document.getElementById('imgKopSurat');
        if (img) {
            img.style.maxHeight = h + 'px';
            img.style.maxWidth = Math.round(h * 5.5) + 'px';
        }
        document.querySelectorAll('#groupKopSize .btn').forEach(b => {
            b.classList.remove('active', 'btn-dark');
            b.classList.add('btn-outline-secondary');
        });
        if (window.event && window.event.currentTarget) {
            window.event.currentTarget.classList.add('active', 'btn-dark');
            window.event.currentTarget.classList.remove('btn-outline-secondary');
        }
    }
    </script>
</body>
</html>
