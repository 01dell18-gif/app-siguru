<?php
// views/kop_surat.php - Komponen Kop Surat Resmi untuk Preview & Cetak Laporan PDF
require_once __DIR__ . '/../config/helper.php';
$settings = getSettings();
$kopUrl = formatPhotoUrl($settings['kop_surat'] ?? 'assets/images/kop_surat.png');
$rawHeight = (int)($settings['kop_size'] ?? 85);
$kopHeight = ($rawHeight >= 50 && $rawHeight <= 160) ? $rawHeight : 85;
$kopMaxWidth = round($kopHeight * 5.5);
?>
<div class="kop-surat mb-2 text-center">
    <!-- Banner Gambar Kop Surat Resmi Dinamis & Proporsional -->
    <div class="d-flex justify-content-center align-items-center w-100 mb-1">
        <img src="<?= htmlspecialchars($kopUrl) ?>" 
             alt="Kop Surat Resmi" 
             id="imgKopSurat"
             class="img-fluid kop-banner-img" 
             style="max-height: <?= $kopHeight ?>px; max-width: min(100%, <?= $kopMaxWidth ?>px); width: auto; height: auto; object-fit: contain;"
             referrerpolicy="no-referrer">
    </div>

    <!-- Judul & Subjudul Laporan -->
    <div class="text-center mt-1 mb-2">
        <h5 class="fw-bold text-uppercase mb-1" style="letter-spacing: 0.5px; font-family: 'Times New Roman', Times, serif; font-size: 1.15rem; line-height: 1.25;">
            <?= htmlspecialchars($laporanTitle ?? 'LAPORAN REKAPITULASI') ?>
        </h5>
        <?php if (!empty($subTitle)): ?>
        <p class="mb-0 small text-secondary" style="font-family: 'Times New Roman', Times, serif; font-size: 0.95rem; margin-top: 2px;"><?= htmlspecialchars($subTitle) ?></p>
        <?php endif; ?>
    </div>
</div>
