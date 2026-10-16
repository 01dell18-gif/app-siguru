<?php
// config/helper.php - Fungsi-fungsi utilitas pembantu aplikasi SiGuru
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

function getSettings() {
    $pdo = getDBConnection();
    try {
        // Auto-check & migration jika kolom list_tahun_ajaran belum ada
        static $checkedMigration = false;
        if (!$checkedMigration) {
            $checkedMigration = true;
            if (DB_DRIVER === 'sqlite') {
                $cols = $pdo->query("PRAGMA table_info(pengaturan)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('list_tahun_ajaran', $cols)) {
                    $pdo->exec("ALTER TABLE pengaturan ADD COLUMN list_tahun_ajaran TEXT DEFAULT '[\"2025/2026\", \"2026/2027\", \"2027/2028\"]'");
                }
            } else {
                $cols = $pdo->query("SHOW COLUMNS FROM pengaturan LIKE 'list_tahun_ajaran'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE pengaturan ADD COLUMN list_tahun_ajaran TEXT DEFAULT '[\"2025/2026\", \"2026/2027\", \"2027/2028\"]'");
                }
            }
        }

        $stmt = $pdo->query("SELECT * FROM pengaturan LIMIT 1");
        $res = $stmt->fetch();
        if ($res) {
            if (empty($res['kop_surat'])) {
                $res['kop_surat'] = 'assets/images/kop_surat.png';
            }
            if (empty($res['kop_size'])) {
                $res['kop_size'] = '90';
            }
            if (empty($res['kabupaten'])) {
                $res['kabupaten'] = 'Klungkung';
            }
            if (!isset($res['ttd_kepsek'])) {
                $res['ttd_kepsek'] = 'https://drive.google.com/thumbnail?id=1GUfUommgYjV-cnhU0tTB8kRJy8iXagkI';
            }
            if (empty($res['list_tahun_ajaran'])) {
                $res['list_tahun_ajaran'] = json_encode(['2025/2026', '2026/2027', '2027/2028']);
            }
            return $res;
        }
    } catch (Exception $e) {}

    // Fallback default pengaturan jika belum ada
    return [
        'id' => 1,
        'logo' => 'assets/images/logo.png',
        'nama_sekolah' => 'SMK FYJ Channel Ferryxz',
        'tahun_ajaran' => '2026/2027',
        'list_tahun_ajaran' => json_encode(['2025/2026', '2026/2027', '2027/2028']),
        'semester' => 'Ganjil',
        'kelas' => 'XI DKV 1, XI DKV 2, XII DKV, X DKV',
        'mata_pelajaran' => '["Perangkat Lunak Desain", "Proses Produksi Desain", "Desain Breif", "Karya Desain", "Kreativitas, Inovasi, dan Kewirausahaan", "Seni Budaya", "Seni Rupa"]',
        'folder_jurnal' => 'https://drive.google.com/drive/folders/1g1iaALpfRo8oovkJNoPxy_MIDLv5axEB?usp=drive_link',
        'folder_foto' => 'https://drive.google.com/drive/folders/1PmA4EHMEVfgbup9ZJuBrIlFzg37gsQO3?usp=sharing',
        'folder_guru_wali' => 'https://drive.google.com/drive/folders/12RUHlItCa8smp58-aixHOTWHld21md9_?usp=drive_link',
        'folder_wali' => 'https://drive.google.com/drive/folders/1uNSsLv2rUUC1c_ykBhnkzQ7y43DRxaDj?usp=drive_link',
        'folder_ekstra' => 'https://drive.google.com/drive/folders/1Jf8BcS1jHO1wUYCzk6dQi9_sy7BRyroM?usp=drive_link',
        'link_db_absen' => '',
        'link_db_jurnal' => '',
        'link_db_guru_wali' => '',
        'link_db_wali' => '',
        'link_db_ekstra' => '',
        'list_ekstrakurikuler' => 'Pramuka, Paskibra, Futsal, Basket, Rohis, PMR, Desain Grafis, Robotik',
        'kepala_sekolah' => 'Drs. H. Ahmad Fauzi, M.Pd.',
        'nip_kepala_sekolah' => '19750815 199903 1 002',
        'kop_surat' => 'assets/images/kop_surat.png',
        'kop_size' => '90',
        'kabupaten' => 'Klungkung',
        'ttd_kepsek' => 'https://drive.google.com/thumbnail?id=1GUfUommgYjV-cnhU0tTB8kRJy8iXagkI'
    ];
}

// Inisialisasi variabel global $settings agar selalu tersedia di setiap file PHP
$settings = getSettings();

function getDaftarTahunAjaran() {
    global $settings;
    if (empty($settings)) {
        $settings = getSettings();
    }
    $list = [];
    if (!empty($settings['list_tahun_ajaran'])) {
        $list = getListOptions($settings['list_tahun_ajaran']);
    }
    // Tambahkan tahun ajaran aktif jika belum ada di list
    if (!empty($settings['tahun_ajaran']) && !in_array($settings['tahun_ajaran'], $list)) {
        $list[] = $settings['tahun_ajaran'];
    }
    if (empty($list)) {
        $list = ['2025/2026', '2026/2027', '2027/2028'];
    }
    
    // Urutkan tahun ajaran secara kronologis
    sort($list);
    return array_values(array_filter(array_unique($list)));
}

function getDaftarKelas() {
    global $settings;
    if (empty($settings)) {
        $settings = getSettings();
    }
    $list = getListOptions($settings['kelas'] ?? '');
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT DISTINCT kelas FROM data_murid WHERE kelas IS NOT NULL AND kelas != ''");
        while ($c = trim((string)$stmt->fetchColumn())) {
            $cLower = strtolower($c);
            if ($c !== '' && $c !== '[]' && !in_array($cLower, ['«', '»', '&lt;', '&gt;', '&lt', '&gt', '<', '>']) && stripos($c, 'c_rgszSh') === false && !in_array($c, $list)) {
                $list[] = $c;
            }
        }
    } catch (Exception $e) {}
    return array_values(array_filter($list));
}

function getDaftarEkstra() {
    global $settings;
    if (empty($settings)) {
        $settings = getSettings();
    }
    $list = getListOptions($settings['list_ekstrakurikuler'] ?? '');
    if (!empty($list)) {
        return array_values(array_filter($list));
    }
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT DISTINCT nama_ekstra FROM data_murid_ekstra WHERE nama_ekstra IS NOT NULL AND nama_ekstra != ''");
        while ($val = $stmt->fetchColumn()) {
            $parts = explode(',', $val);
            foreach ($parts as $p) {
                $p = trim($p);
                $pLower = strtolower($p);
                if ($p !== '' && $p !== '-' && $p !== '[]' && !in_array($pLower, ['«', '»', '&lt;', '&gt;', '&lt', '&gt', '<', '>']) && stripos($p, 'c_rgszSh') === false && !in_array($p, $list)) {
                    $list[] = $p;
                }
            }
        }
        $stmt2 = $pdo->query("SELECT DISTINCT pembina_ekstra FROM data_guru WHERE pembina_ekstra IS NOT NULL AND pembina_ekstra != '' AND pembina_ekstra != '-'");
        while ($val = trim((string)$stmt2->fetchColumn())) {
            $parts = explode(',', $val);
            foreach ($parts as $p) {
                $p = trim($p);
                $pLower = strtolower($p);
                if ($p !== '' && $p !== '-' && $p !== '[]' && !in_array($pLower, ['«', '»', '&lt;', '&gt;', '&lt', '&gt', '<', '>']) && stripos($p, 'c_rgszSh') === false && !in_array($p, $list)) {
                    $list[] = $p;
                }
            }
        }
    } catch (Exception $e) {}
    return array_values(array_filter($list));
}

/**
 * Mendapatkan mapping Cabang Ekstrakurikuler => Daftar Guru Pembina dari tabel data_guru
 * @return array Asosiatif [nama_ekstra => [guru1, guru2, ...]]
 */
function getEkstraPembinaMap() {
    $pdo = getDBConnection();
    $map = [];
    try {
        $stmt = $pdo->query("SELECT nama_guru, pembina_ekstra FROM data_guru WHERE pembina_ekstra IS NOT NULL AND pembina_ekstra != '' AND pembina_ekstra != '-'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $gNama = trim($row['nama_guru']);
            $branches = array_map('trim', explode(',', $row['pembina_ekstra']));
            foreach ($branches as $br) {
                if ($br === '' || $br === '-') continue;
                if (!isset($map[$br])) {
                    $map[$br] = [];
                }
                if (!in_array($gNama, $map[$br])) {
                    $map[$br][] = $gNama;
                }
            }
        }
    } catch (Exception $e) {}
    return $map;
}

/**
 * Mem-parsing string guru_pembina untuk masing-masing cabang ekstrakurikuler siswa.
 * Format tersimpan bisa berupa:
 * 1. String nama guru tunggal (misal: "FYJ Channel")
 * 2. Format terstruktur per cabang (misal: "Drumband: FYJ Channel; Futsal: Susanto")
 * 3. JSON format {"Drumband":"FYJ Channel"}
 * Jika belum ada atau kosong, otomatis fallback ke pemetaan guru pembina di master data_guru ($ekstraPembinaMap).
 *
 * @param string $pembinaString
 * @param array $ekstraNames
 * @param array $ekstraPembinaMap
 * @return array Asosiatif [nama_ekstra => nama_pembina]
 */
function parseMuridEkstraPembinaMap($pembinaString, $ekstraNames = [], $ekstraPembinaMap = []) {
    $res = [];
    $pembinaString = trim((string)$pembinaString);

    if (empty($ekstraPembinaMap)) {
        $ekstraPembinaMap = getEkstraPembinaMap();
    }

    // 1. JSON parse jika format JSON
    if (!empty($pembinaString) && ($pembinaString[0] === '{' || $pembinaString[0] === '[')) {
        $json = json_decode($pembinaString, true);
        if (is_array($json)) {
            $res = $json;
        }
    }

    // 2. Format per cabang: "Ekstra: Pembina; Ekstra2: Pembina2"
    if (empty($res) && strpos($pembinaString, ':') !== false) {
        $parts = explode(';', $pembinaString);
        foreach ($parts as $part) {
            $sub = explode(':', trim($part), 2);
            if (count($sub) === 2) {
                $ek = trim($sub[0]);
                $pem = trim($sub[1]);
                if ($ek !== '') {
                    $res[$ek] = $pem;
                }
            }
        }
    }

    // 3. String biasa tanpa separator ':' jika hanya ada 1 cabang ekstra
    if (empty($res) && !empty($pembinaString) && count($ekstraNames) === 1) {
        $res[$ekstraNames[0]] = $pembinaString;
    }

    // 4. Fallback ke master data_guru jika belum ditentukan atau belum tersimpan
    foreach ($ekstraNames as $ek) {
        if (!isset($res[$ek]) || $res[$ek] === '' || $res[$ek] === 'Belum Ditentukan') {
            $avail = $ekstraPembinaMap[$ek] ?? [];
            if (!empty($avail)) {
                $res[$ek] = implode(', ', $avail);
            } else {
                $res[$ek] = 'Belum Ditentukan';
            }
        }
    }

    return $res;
}

/**
 * Menyusun string guru_pembina untuk disimpan ke database
 * Jika hanya ada 1 cabang: return nama pembina
 * Jika > 1 cabang: return "Cabang1: Pembina1; Cabang2: Pembina2"
 */
function serializeMuridEkstraPembinaMap($pembinaMap) {
    if (empty($pembinaMap) || !is_array($pembinaMap)) {
        return '';
    }
    if (count($pembinaMap) === 1) {
        return reset($pembinaMap);
    }
    $parts = [];
    foreach ($pembinaMap as $ek => $pem) {
        $parts[] = "$ek: $pem";
    }
    return implode('; ', $parts);
}

function getDaftarMapel() {
    global $settings;
    if (empty($settings)) {
        $settings = getSettings();
    }
    $list = getListOptions($settings['mata_pelajaran'] ?? '');
    try {
        $pdo = getDBConnection();
        $stmtA = $pdo->query("SELECT DISTINCT mata_pelajaran FROM hasil_absen WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != ''");
        while ($val = $stmtA->fetchColumn()) {
            $val = trim($val);
            if ($val !== '' && !in_array($val, $list)) {
                $list[] = $val;
            }
        }
        $stmtJ = $pdo->query("SELECT DISTINCT mata_pelajaran FROM hasil_jurnal WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != ''");
        while ($val = $stmtJ->fetchColumn()) {
            $val = trim($val);
            if ($val !== '' && !in_array($val, $list)) {
                $list[] = $val;
            }
        }
    } catch (Exception $e) {}

    // Jika varian berkoma resmi ada, hilangkan duplikat varian tanpa koma
    if (in_array('Kreativitas, Inovasi, dan Kewirausahaan', $list)) {
        $list = array_values(array_filter($list, function($item) {
            return $item !== 'Kreativitas Inovasi dan Kewirausahaan';
        }));
    }

    return array_values(array_filter($list));
}

/**
 * Menyimpan pesan flash notification ke session
 * @param string $type Jenis notifikasi (success, danger, warning, info)
 * @param string $message Teks pesan notifikasi
 * @return void
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Mengambil data notifikasi flash dari session dan menghapusnya
 * @return array|null Array data notifikasi atau null jika tidak ada
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Memformat string tanggal (Y-m-d) ke format bahasa Indonesia
 * @param string|int|null $date Nilai tanggal atau timestamp
 * @return string Tanggal terformat (contoh: 17 Agustus 2026) atau '-' jika kosong
 */
function formatDateIndo(string|int|null $date): string {
    if (!$date) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $time = strtotime((string)$date);
    if (!$time) return (string)$date;
    $tgl = date('d', $time);
    $bln = $bulan[(int)date('m', $time)];
    $thn = date('Y', $time);
    return "$tgl $bln $thn";
}

/**
 * Menangani upload berkas (single maupun multiple) ke direktori uploads/
 * @param string $fileInputName Nama atribut name dari elemen input file
 * @param string $subfolder Sub-direktori penyimpanan di dalam uploads/
 * @return string|array|null Path relatif berkas tersimpan atau null jika tidak ada berkas
 */
function handleFileUpload(string $fileInputName, string $subfolder = 'documents'): string|array|null {
    if (!isset($_FILES[$fileInputName])) {
        return null;
    }

    $fileData = $_FILES[$fileInputName];
    $uploadDir = __DIR__ . '/../uploads/' . $subfolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // 1. Jika input file berbentuk array/multiple (name="file_bukti[]")
    if (is_array($fileData['name'])) {
        $uploadedPaths = [];
        $count = count($fileData['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($fileData['error'][$i] === UPLOAD_ERR_OK && !empty($fileData['name'][$i])) {
                $origName = basename($fileData['name'][$i]);
                $cleanName = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
                $targetPath = $uploadDir . $cleanName;
                if (move_uploaded_file($fileData['tmp_name'][$i], $targetPath)) {
                    $uploadedPaths[] = 'uploads/' . $subfolder . '/' . $cleanName;
                }
            }
        }
        return !empty($uploadedPaths) ? $uploadedPaths : null;
    }

    // 2. Jika input file berbentuk single (name="foto_file")
    if ($fileData['error'] === UPLOAD_ERR_OK && !empty($fileData['name'])) {
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileData['name']));
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($fileData['tmp_name'], $targetPath)) {
            return 'uploads/' . $subfolder . '/' . $fileName;
        }
    }

    return null;
}

/**
 * Memecah string tautan/file bukti (bisa berupa 1 link, JSON array, atau daftar dipisah koma)
 * menjadi array URL/path aktif
 * @param string|array|null $bukti String tautan, array URL, atau format JSON
 * @return array Array link berkas bukti yang valid
 */
function parseBuktiLinks(string|array|null $bukti): array {
    if (empty($bukti)) return [];
    if (is_array($bukti)) {
        return array_values(array_filter(array_map('trim', $bukti)));
    }
    $bukti = trim((string)$bukti);
    if ($bukti === '') return [];

    // Jika format JSON array
    if ((strpos($bukti, '[') === 0 && substr($bukti, -1) === ']') || (strpos($bukti, '{') === 0 && substr($bukti, -1) === '}')) {
        $decoded = json_decode($bukti, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), function($x) { return $x !== ''; }));
        }
    }

    // Jika dipisah baris baru
    if (strpos($bukti, "\n") !== false) {
        $parts = explode("\n", $bukti);
    } else {
        // Jika dipisah koma
        $parts = explode(',', $bukti);
    }

    $links = [];
    foreach ($parts as $p) {
        $clean = trim($p);
        if ($clean !== '' && $clean !== '-') {
            $links[] = $clean;
        }
    }
    return array_values(array_unique($links));
}

/**
 * Menghapus berkas fisik lokal lama jika ada saat data bukti atau foto diperbarui / digantikan / dihapus
 * @param string|array|null $oldBukti Tautan atau path berkas lama
 * @return void
 */
function deleteOldUploadedFiles(string|array|null $oldBukti): void {
    if (empty($oldBukti)) return;

    $links = [];
    if (is_array($oldBukti)) {
        $links = $oldBukti;
    } else {
        $raw = trim((string)$oldBukti);
        if ($raw === '' || $raw === '-') return;
        if ((strpos($raw, '[') === 0 && substr($raw, -1) === ']') || (strpos($raw, '{') === 0 && substr($raw, -1) === '}')) {
            $dec = json_decode($raw, true);
            if (is_array($dec)) $links = $dec;
        } elseif (strpos($raw, "\n") !== false) {
            $links = explode("\n", $raw);
        } else {
            $links = explode(',', $raw);
        }
    }

    $htdocsDir = dirname(dirname(__DIR__));

    foreach ($links as $link) {
        $clean = trim((string)$link);
        if ($clean === '' || $clean === '-') continue;
        $clean = urldecode($clean);
        $clean = str_replace('\\', '/', $clean);

        // Ambil path relatif mulai dari uploads/ jika disertakan domain atau path relatif lain
        $pos = stripos($clean, 'uploads/');
        if ($pos !== false) {
            $rel = substr($clean, $pos);
            if (($q = strpos($rel, '?')) !== false) $rel = substr($rel, 0, $q);
            if (($h = strpos($rel, '#')) !== false) $rel = substr($rel, 0, $h);
        } else {
            $rel = ltrim($clean, './ ');
        }

        // Pastikan hanya berkas dalam direktori uploads/ dan mencegah path traversal
        if (strpos($rel, 'uploads/') === 0 && strpos($rel, '..') === false && basename($rel) !== '.gitkeep') {
            $candidates = [
                __DIR__ . '/../' . $rel,
                dirname(__DIR__) . '/' . $rel,
                $htdocsDir . '/Siguru-SQL/' . $rel,
                $htdocsDir . '/App Siguru SQL XAMPP/' . $rel,
            ];

            foreach ($candidates as $cand) {
                $candNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cand);
                if (is_file($candNorm)) {
                    @chmod($candNorm, 0777);
                    @unlink($candNorm);
                    @clearstatcache(true, $candNorm);
                }
            }
        }
    }
}

/**
 * Membersihkan seluruh file fisik yang pernah diunggah (foto profil, berkas dokumen, bukti laporan, dll)
 * di dalam direktori uploads/ tanpa menghapus folder struktural dan berkas proteksi (.gitkeep)
 * @return int Jumlah berkas yang berhasil dihapus
 */
function clearAllUploadedFiles(): int {
    $htdocsDir = dirname(dirname(__DIR__));
    $uploadRoots = array_unique([
        dirname(__DIR__) . '/uploads',
        __DIR__ . '/../uploads',
        $htdocsDir . '/Siguru-SQL/uploads',
        $htdocsDir . '/App Siguru SQL XAMPP/uploads',
    ]);

    $deletedCount = 0;
    foreach ($uploadRoots as $root) {
        if (!is_dir($root)) continue;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $filename = $item->getFilename();
                    // Lindungi berkas sistem seperti .gitkeep atau .gitignore
                    if ($filename === '.gitkeep' || $filename === '.gitignore') {
                        continue;
                    }
                    $filePath = $item->getPathname();
                    @chmod($filePath, 0777);
                    if (@unlink($filePath)) {
                        $deletedCount++;
                    }
                    @clearstatcache(true, $filePath);
                }
            }
        } catch (Exception $e) {}
    }

    return $deletedCount;
}

/**
 * Merender daftar tombol/link bukti aktif untuk tabel laporan web
 * @param string|array|null $bukti String tautan bukti atau JSON/array
 * @param string $titlePrefix Awalan judul pada tooltip tombol
 * @return string Markup HTML tombol bukti
 */
function renderBuktiButtons(string|array|null $bukti, string $titlePrefix = 'Buka Berkas Bukti'): string {
    $links = parseBuktiLinks($bukti);
    if (empty($links)) {
        return '<span class="text-muted small">-</span>';
    }

    $html = '<div class="d-flex flex-wrap gap-1 align-items-center">';
    $total = count($links);
    foreach ($links as $idx => $link) {
        $no = $idx + 1;
        $label = $total > 1 ? 'Bukti ' . $no : 'Bukti';
        $isImg = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $link);
        $isPdf = preg_match('/\.pdf$/i', $link);
        $isDrive = (strpos($link, 'drive.google.com') !== false);
        
        $icon = 'bi-link-45deg';
        if ($isImg) $icon = 'bi-image';
        elseif ($isPdf) $icon = 'bi-file-earmark-pdf';
        elseif ($isDrive) $icon = 'bi-google text-danger';

        $safeUrl = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars(($total > 1 ? "$titlePrefix ($no)" : $titlePrefix) . ": $link", ENT_QUOTES, 'UTF-8');

        $html .= '<a href="' . $safeUrl . '" target="_blank" class="btn btn-outline-info btn-bukti btn-sm rounded-pill" title="' . $safeTitle . '">';
        $html .= '<i class="bi ' . $icon . ' me-1"></i>' . $safeLabel;
        $html .= '</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Merender daftar link bukti aktif untuk halaman pratinjau cetak PDF
 * @param string|array|null $bukti String tautan bukti atau JSON/array
 * @return string Markup HTML link bukti cetak
 */
function renderBuktiPrintLinks(string|array|null $bukti): string {
    $links = parseBuktiLinks($bukti);
    if (empty($links)) {
        return '<span class="text-muted small">-</span>';
    }

    $html = '<div style="display: flex; flex-direction: column; gap: 3px; align-items: center;">';
    $total = count($links);
    foreach ($links as $idx => $link) {
        $no = $idx + 1;
        $label = $total > 1 ? 'Bukti ' . $no : 'Buka Bukti';
        $safeUrl = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        $html .= '<a href="' . $safeUrl . '" target="_blank" class="text-primary text-decoration-underline" style="font-size: 8.5pt; display: inline-block; white-space: nowrap;" title="' . $safeTitle . '">';
        $html .= '<i class="bi bi-link-45deg me-1"></i>' . $safeLabel;
        $html .= '</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Mem-parse daftar pilihan yang dipisah koma atau dalam format JSON menjadi array string bersih
 * @param string|array|null $stringOrJson Nilai string atau format JSON array
 * @return array Array daftar opsi
 */
function getListOptions(string|array|null $stringOrJson): array {
    if (empty($stringOrJson)) return [];

    if (is_array($stringOrJson)) {
        return array_values(array_filter(array_map('trim', $stringOrJson), function($x) {
            return $x !== '' && $x !== '[]' && !in_array($x, ['«', '»', '&lt;', '&gt;', '<', '>']) && stripos($x, 'c_rgszSh') === false;
        }));
    }

    $str = trim((string)$stringOrJson);
    if ($str === '' || $str === '[]' || $str === '{}') return [];

    // 1. Jika tersimpan dalam format JSON
    if ((strpos($str, '[') === 0 && substr($str, -1) === ']') || (strpos($str, '{') === 0 && substr($str, -1) === '}')) {
        $decoded = json_decode($str, true);
        if (is_array($decoded)) {
            $cleaned = array_values(array_filter(array_map('trim', $decoded), function($x) {
                return $x !== '' && $x !== '[]' && !in_array($x, ['«', '»', '&lt;', '&gt;', '<', '>']) && stripos($x, 'c_rgszSh') === false;
            }));
            return $cleaned;
        }
    }

    // 2. Lindungi frasa khusus mapel resmi yang mengandung koma agar tidak terpotong oleh pemecahan koma
    $placeholder = '___KREAT_INOV_KEWIR___';
    $strProtected = str_ireplace('Kreativitas, Inovasi, dan Kewirausahaan', $placeholder, $str);

    $parts = explode(',', $strProtected);
    $result = [];
    foreach ($parts as $p) {
        $item = trim(str_replace($placeholder, 'Kreativitas, Inovasi, dan Kewirausahaan', $p));
        if ($item !== '' && $item !== '[]' && !in_array($item, ['«', '»', '&lt;', '&gt;', '<', '>']) && stripos($item, 'c_rgszSh') === false) {
            $result[] = $item;
        }
    }

    // 3. Rekonstruksi jika sudah terlanjur terpecah di database menjadi ['Kreativitas', 'Inovasi', 'dan Kewirausahaan']
    $reconstructed = [];
    $count = count($result);
    for ($i = 0; $i < $count; $i++) {
        if (
            isset($result[$i]) && strcasecmp($result[$i], 'Kreativitas') === 0 &&
            isset($result[$i+1]) && strcasecmp($result[$i+1], 'Inovasi') === 0 &&
            isset($result[$i+2]) && (strcasecmp($result[$i+2], 'dan Kewirausahaan') === 0 || strcasecmp($result[$i+2], 'Kewirausahaan') === 0)
        ) {
            $reconstructed[] = 'Kreativitas, Inovasi, dan Kewirausahaan';
            $i += 2;
        } else {
            if (strcasecmp($result[$i], 'Kreativitas Inovasi dan Kewirausahaan') === 0) {
                $reconstructed[] = 'Kreativitas, Inovasi, dan Kewirausahaan';
            } else {
                $reconstructed[] = $result[$i];
            }
        }
    }

    return array_values(array_unique(array_filter($reconstructed)));
}

/**
 * Format URL foto profil atau file (mendukung Google Drive thumbnail, upload lokal, dan default)
 * @param string|null $url URL foto atau path lokal
 * @return string URL yang siap ditampilkan
 */
function formatPhotoUrl(?string $url): string {
    if (empty($url)) {
        return 'assets/images/logo.png';
    }
    // Jika path lokal
    if (strpos($url, 'uploads/') === 0 || file_exists(__DIR__ . '/../' . $url)) {
        return $url;
    }
    // Jika tautan Google Drive /file/d/ID/view...
    if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w500';
    }
    // Jika tautan Google Drive uc?id=ID atau thumbnail?id=ID
    if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w500';
    }
    return $url;
}

/**
 * Format NISN siswa agar menjadi 10 digit dengan leading zero jika perlu
 * @param string|int|null $nisn Nomor NISN siswa
 * @return string NISN 10 digit terformat
 */
function formatNISN(string|int|null $nisn): string {
    if (empty($nisn)) return '';
    $clean = preg_replace('/\D/', '', (string)$nisn);
    if (empty($clean)) return trim((string)$nisn);
    return str_pad($clean, 10, '0', STR_PAD_LEFT);
}

/**
 * Memformat nomor telepon/HP menjadi tautan WhatsApp resmi https://wa.me/62...
 * @param string|int|null $phone Nomor telepon input
 * @return string|null Tautan WhatsApp resmi atau null jika nomor tidak valid
 */
function formatWhatsAppUrl(string|int|null $phone): ?string {
    if (empty($phone)) return null;
    $trimmed = trim((string)$phone);
    if ($trimmed === '' || $trimmed === '-' || $trimmed === '0') return null;

    // Bersihkan karakter non-digit
    $digits = preg_replace('/[^0-9]/', '', $trimmed);
    if (empty($digits)) return null;

    // Jika sudah diawali 62
    if (substr($digits, 0, 2) === '62') {
        $formatted = $digits;
    } else {
        // Hapus leading zeros (misal 0812... atau 0022...)
        $unzeroed = ltrim($digits, '0');
        if (empty($unzeroed)) return null;
        $formatted = '62' . $unzeroed;
    }

    // Validasi panjang wajar (minimal 5 digit nomor internasional)
    if (strlen($formatted) < 5) return null;

    return 'https://wa.me/' . $formatted;
}

/**
 * Mesin mandiri (Pure PHP) untuk membaca dan menulis berkas Excel .xlsx (OpenXML)
 * Bekerja 100% tanpa library pihak ketiga dan tanpa wajib dependensi ZipArchive.
 */
class SimpleXlsx {
    public static function createZip(array $files): string {
        $data = '';
        $dir = '';
        $offset = 0;
        
        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', $name);
            $uncLen = strlen($content);
            $crc = crc32($content);
            
            if (function_exists('gzdeflate') && $uncLen > 32) {
                $comp = gzdeflate($content);
                $compLen = strlen($comp);
                $meth = 8;
            } else {
                $comp = $content;
                $compLen = $uncLen;
                $meth = 0;
            }
            
            $lh = pack('VvvvVVVVvv',
                0x04034b50, 20, 0, $meth, 0,
                $crc, $compLen, $uncLen,
                strlen($name), 0
            ) . $name;
            
            $data .= $lh . $comp;
            
            $cd = pack('VvvvvVVVVvvvvvVV',
                0x02014b50, 20, 20, 0, $meth, 0,
                $crc, $compLen, $uncLen,
                strlen($name), 0, 0, 0, 0, 0,
                $offset
            ) . $name;
            
            $dir .= $cd;
            $offset = strlen($data);
        }
        
        $eocd = pack('VvvvvVVv',
            0x06054b50, 0, 0, count($files), count($files),
            strlen($dir), $offset, 0
        );
        
        return $data . $dir . $eocd;
    }

    public static function readZip(string $filePath): array {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if (@$zip->open($filePath) === true) {
                $files = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        $files[$name] = $content;
                    }
                }
                $zip->close();
                if (!empty($files)) return $files;
            }
        }
        
        $raw = @file_get_contents($filePath);
        if (!$raw || strlen($raw) < 22) return [];
        
        $eocdPos = strrpos($raw, "\x50\x4b\x05\x06");
        if ($eocdPos === false) return [];
        
        $eocd = unpack('vdisk/vcdDisk/vcount/vtotal/Vsize/Voffset', substr($raw, $eocdPos + 4, 16));
        $cdOffset = $eocd['offset'] ?? 0;
        $totalFiles = $eocd['total'] ?? 0;
        
        $files = [];
        $p = $cdOffset;
        for ($i = 0; $i < $totalFiles; $i++) {
            if (substr($raw, $p, 4) !== "\x50\x4b\x01\x02") break;
            $h = unpack('vver/vverNeed/vflag/vmeth/vtime/vdate/Vcrc/VcompLen/VuncLen/vnameLen/vextraLen/vcommLen/vdisk/vintAttr/VextAttr/Voffset', substr($raw, $p + 4, 42));
            $name = substr($raw, $p + 46, $h['nameLen']);
            $p += 46 + $h['nameLen'] + $h['extraLen'] + $h['commLen'];
            
            $lhPos = $h['offset'];
            if (substr($raw, $lhPos, 4) !== "\x50\x4b\x03\x04") continue;
            $lh = unpack('vver/vflag/vmeth/vtime/vdate/Vcrc/VcompLen/VuncLen/vnameLen/vextraLen', substr($raw, $lhPos + 4, 26));
            $dataPos = $lhPos + 30 + $lh['nameLen'] + $lh['extraLen'];
            $compData = substr($raw, $dataPos, $h['compLen']);
            
            if ($h['meth'] === 0) {
                $files[$name] = $compData;
            } elseif ($h['meth'] === 8 && function_exists('gzinflate')) {
                $uncomp = @gzinflate($compData);
                if ($uncomp !== false) {
                    $files[$name] = $uncomp;
                }
            }
        }
        return $files;
    }

    public static function generateXlsx(array $headers, array $rows): string {
        $sheetData = '';
        $rIdx = 1;
        
        if (!empty($headers)) {
            $sheetData .= '<row r="' . $rIdx . '">';
            $cCol = 0;
            foreach ($headers as $h) {
                $colLetter = self::colToLetter($cCol++);
                $sheetData .= '<c r="' . $colLetter . $rIdx . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$h, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</t></is></c>';
            }
            $sheetData .= '</row>';
            $rIdx++;
        }
        
        foreach ($rows as $row) {
            $sheetData .= '<row r="' . $rIdx . '">';
            $cCol = 0;
            foreach ($row as $cell) {
                $colLetter = self::colToLetter($cCol++);
                $valStr = (string)$cell;
                $sheetData .= '<c r="' . $colLetter . $rIdx . '" t="inlineStr"><is><t>' . htmlspecialchars($valStr, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</t></is></c>';
            }
            $sheetData .= '</row>';
            $rIdx++;
        }
        
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
                '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
                '<Default Extension="xml" ContentType="application/xml"/>' .
                '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
                '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
                '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
                '</Relationships>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
                '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
                '<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets>' .
                '</workbook>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
                '<sheetData>' . $sheetData . '</sheetData>' .
                '</worksheet>'
        ];
        
        return self::createZip($files);
    }

    public static function parseXlsx(string $filePath): array {
        $zipFiles = self::readZip($filePath);
        if (empty($zipFiles)) return [];
        
        $sharedStrings = [];
        if (isset($zipFiles['xl/sharedStrings.xml'])) {
            $xml = @simplexml_load_string($zipFiles['xl/sharedStrings.xml']);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $tStr = '';
                        foreach ($si->r as $r) {
                            $tStr .= (string)$r->t;
                        }
                        $sharedStrings[] = $tStr;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }
        
        $sheetXmlContent = null;
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet.xml'] as $k) {
            if (isset($zipFiles[$k])) {
                $sheetXmlContent = $zipFiles[$k];
                break;
            }
        }
        if (!$sheetXmlContent) {
            foreach ($zipFiles as $k => $c) {
                if (strpos($k, 'xl/worksheets/') === 0) {
                    $sheetXmlContent = $c;
                    break;
                }
            }
        }
        if (!$sheetXmlContent) return [];
        
        $sheetXml = @simplexml_load_string($sheetXmlContent);
        if (!$sheetXml || !isset($sheetXml->sheetData)) return [];
        
        $rows = [];
        foreach ($sheetXml->sheetData->row as $r) {
            $colMap = [];
            $maxColIdx = -1;
            
            foreach ($r->c as $c) {
                $ref = (string)$c['r'];
                preg_match('/^([A-Z]+)([0-9]+)$/', $ref, $m);
                $colIdx = !empty($m[1]) ? self::letterToCol($m[1]) : count($colMap);
                
                $t = (string)$c['t'];
                $val = '';
                if ($t === 's') {
                    $idx = (int)$c->v;
                    $val = $sharedStrings[$idx] ?? '';
                } elseif ($t === 'inlineStr') {
                    $val = (string)($c->is->t ?? '');
                } else {
                    $val = (string)($c->v ?? '');
                }
                
                $colMap[$colIdx] = trim($val);
                if ($colIdx > $maxColIdx) $maxColIdx = $colIdx;
            }
            
            if ($maxColIdx >= 0) {
                $rowArr = [];
                for ($ci = 0; $ci <= $maxColIdx; $ci++) {
                    $rowArr[$ci] = $colMap[$ci] ?? '';
                }
                $nonEmpty = array_filter($rowArr, function($v) { return $v !== ''; });
                if (!empty($nonEmpty)) {
                    $rows[] = $rowArr;
                }
            }
        }
        
        return $rows;
    }

    private static function colToLetter(int $col): string {
        $letter = '';
        while ($col >= 0) {
            $letter = chr($col % 26 + 65) . $letter;
            $col = intdiv($col, 26) - 1;
        }
        return $letter;
    }

    private static function letterToCol(string $letter): int {
        $letter = strtoupper($letter);
        $col = 0;
        for ($i = 0; $i < strlen($letter); $i++) {
            $col = $col * 26 + (ord($letter[$i]) - 64);
        }
        return $col - 1;
    }
}

/**
 * Mengekspor data tabel ke format berkas Excel (.xlsx resmi standar OpenXML)
 * Langsung dibuka mulus di Microsoft Excel tanpa popup peringatan format!
 * @param string $filename Nama berkas hasil download
 * @param array $headers Header kolom tabel
 * @param array $rows Kumpulan baris data
 * @return void
 */
function exportToExcelXLS(string $filename, array $headers, array $rows): void {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Ganti ekstensi menjadi .xlsx resmi
    $cleanName = preg_replace('/\.(csv|xlsx|xls)$/i', '', $filename) . '.xlsx';
    $xlsxBinary = SimpleXlsx::generateXlsx($headers, $rows);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $cleanName . '"');
    header('Content-Length: ' . strlen($xlsxBinary));
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: public');
    
    echo $xlsxBinary;
    exit;
}

/**
 * Alias untuk kompatibilitas penuh dengan pemanggil ekspor XLSX
 */
function exportToExcelXLSX(string $filename, array $headers, array $rows): void {
    exportToExcelXLS($filename, $headers, $rows);
}

/**
 * Ekspor ke format CSV dengan UTF-8 BOM
 */
function exportToExcelCSV(string $filename, array $headers, array $rows): void {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $cleanName = preg_replace('/\.(csv|xlsx|xls)$/i', '', $filename) . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $cleanName . '"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: public');
    
    // UTF-8 BOM
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if (!empty($headers)) {
        fputcsv($out, $headers);
    }
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

/**
 * Membantu membangun blok Kop Surat & Metadata untuk ekspor Excel Laporan Hasil
 * Menghasilkan baris-baris berformat kop resmi dinas / sekolah seperti pada cetak PDF
 */
function buildExcelReportHeader(string $title, string $subTitle = '', ?array $settings = null): array {
    if ($settings === null) {
        $settings = getSettings();
    }
    $school = !empty($settings['nama_sekolah']) ? strtoupper($settings['nama_sekolah']) : 'SISTEM INFORMASI MANAJEMEN GURU (SIGURU)';
    $kab = !empty($settings['kabupaten']) ? 'Kabupaten / Kota: ' . $settings['kabupaten'] : '';
    
    $headerRows = [];
    $headerRows[] = [$school];
    if (!empty($kab)) {
        $headerRows[] = [$kab];
    }
    $headerRows[] = [strtoupper($title)];
    if (!empty($subTitle)) {
        $headerRows[] = [$subTitle];
    }
    $headerRows[] = ['Tanggal Cetak: ' . formatDateIndo(date('Y-m-d'))];
    $headerRows[] = []; // Baris pemisah kosong
    return $headerRows;
}

/**
 * Membantu membangun blok Tanda Tangan Pengesahan (Signature Block) untuk ekspor Excel Laporan Hasil
 * Seperti yang tercantum pada cetak PDF
 */
function buildExcelReportSignatures(int $colSpan, string $guruNama, string $guruNip, string $roleTitle = 'Guru / Guru Mapel', bool $includeKepsek = true, ?array $settings = null): array {
    if ($settings === null) {
        $settings = getSettings();
    }
    $kab = !empty($settings['kabupaten']) ? $settings['kabupaten'] . ', ' : '';
    $kepsekNama = !empty($settings['kepala_sekolah']) ? $settings['kepala_sekolah'] : 'Kepala Sekolah';
    $kepsekNip = !empty($settings['nip_kepala_sekolah']) ? $settings['nip_kepala_sekolah'] : '-';
    
    $rightCol = max(4, $colSpan - 2);
    
    $makeRow = function($leftVal, $rightVal) use ($rightCol) {
        $row = array_fill(0, $rightCol + 1, '');
        $row[0] = $leftVal;
        $row[$rightCol] = $rightVal;
        return $row;
    };
    
    $rows = [];
    $rows[] = []; // Baris pemisah
    $rows[] = []; // Baris pemisah
    
    if ($includeKepsek) {
        $rows[] = $makeRow('Mengetahui,', $kab . formatDateIndo(date('Y-m-d')));
        $rows[] = $makeRow('Kepala Sekolah', $roleTitle . ',');
        $rows[] = []; // Spasi tanda tangan
        $rows[] = [];
        $rows[] = [];
        $rows[] = $makeRow($kepsekNama, $guruNama);
        $rows[] = $makeRow('NIP. ' . $kepsekNip, 'NIP. ' . $guruNip);
    } else {
        // Khusus tanpa TTD Kepsek (seperti Presensi Siswa di print-laporan.php)
        $rows[] = $makeRow('', $kab . formatDateIndo(date('Y-m-d')));
        $rows[] = $makeRow('', $roleTitle . ',');
        $rows[] = []; // Spasi tanda tangan
        $rows[] = [];
        $rows[] = [];
        $rows[] = $makeRow('', $guruNama);
        $rows[] = $makeRow('', 'NIP. ' . $guruNip);
    }
    
    return $rows;
}

/**
 * Parser universal multi-format:
 * Mendukung .xlsx (OpenXML), .xls (HTML / MHTML / XML Spreadsheet), dan .csv / .tsv
 * Otomatis membersihkan tag script, navigasi tabstrip Excel ("c_rgszSh", "«", "»"), dan artefak rusak.
 * @param string $filePath Path berkas yang akan diuraikan
 * @return array Array baris dan kolom hasil impor
 */
function parseImportRows(string $filePath): array {
    if (!file_exists($filePath)) return [];
    
    $headerBytes = @file_get_contents($filePath, false, null, 0, 4);
    
    // 1. Cek berkas asli format .xlsx (ZIP PK header)
    if ($headerBytes === "\x50\x4b\x03\x04" || $headerBytes === "\x50\x4b\x05\x06") {
        $parsed = SimpleXlsx::parseXlsx($filePath);
        if (!empty($parsed)) {
            return $parsed;
        }
    }
    
    $content = file_get_contents($filePath);
    if ($content === false || strlen(trim($content)) === 0) return [];
    
    // 2. Cek apakah berkas berupa XML Spreadsheet 2003 (<Workbook ...><Worksheet><Table>)
    if (stripos($content, '<Workbook') !== false && stripos($content, '<Table') !== false) {
        $cleanXml = preg_replace('/xmlns="[^"]+"/', '', $content);
        $sxml = @simplexml_load_string($cleanXml);
        if ($sxml) {
            $rows = [];
            foreach ($sxml->xpath('//Row') as $r) {
                $row = [];
                foreach ($r->Cell as $c) {
                    $row[] = trim((string)($c->Data ?? $c->children()->Data ?? ''));
                }
                $nonEmpty = array_filter($row, function($v) { return $v !== ''; });
                if (!empty($nonEmpty)) {
                    $rows[] = $row;
                }
            }
            if (!empty($rows)) return $rows;
        }
    }
    
    // 3. Cek apakah berkas berupa HTML Table (ekspor lama .xls atau file Web Page yang disimpan Excel)
    if (stripos($content, '<table') !== false || stripos($content, '<tr') !== false) {
        // Hapus seluruh tag script, style, dan noscript beserta isinya agar kode tabstrip Excel (c_rgszSh) musnah
        $cleanHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $cleanHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $cleanHtml);
        $cleanHtml = preg_replace('/<noscript\b[^>]*>(.*?)<\/noscript>/is', '', $cleanHtml);
        
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $cleanHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $tables = $dom->getElementsByTagName('table');
        $bestRows = [];
        
        foreach ($tables as $tbl) {
            $id = strtolower($tbl->getAttribute('id'));
            $cls = strtolower($tbl->getAttribute('class'));
            // Lewati tabel tabstrip / sheet navigation bawaan Excel
            if (strpos($id, 'tab') !== false || strpos($cls, 'tab') !== false) {
                continue;
            }
            
            $tblRows = [];
            $trList = $tbl->getElementsByTagName('tr');
            foreach ($trList as $tr) {
                $cells = [];
                foreach ($tr->childNodes as $node) {
                    if (in_array(strtolower($node->nodeName), ['td', 'th'])) {
                        $txt = trim(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        // Bersihkan spasi non-breaking
                        $txt = trim(str_replace("\xC2\xA0", ' ', $txt));
                        $cells[] = $txt;
                    }
                }
                if (!empty($cells)) {
                    // Abaikan baris jika hanya berisi tanda navigasi atau script c_rgszSh
                    $realData = array_filter($cells, function($c) {
                        return !in_array($c, ['«', '»', '<', '>', '&lt;', '&gt;', '', '+', '"+"']) && stripos($c, 'c_rgszSh') === false;
                    });
                    if (!empty($realData)) {
                        $tblRows[] = $cells;
                    }
                }
            }
            
            if (count($tblRows) > count($bestRows)) {
                $bestRows = $tblRows;
            }
        }
        
        if (!empty($bestRows)) {
            return $bestRows;
        }
    }
    
    // 4. Jika file berupa CSV / TSV
    $handle = fopen($filePath, 'r');
    if ($handle === false) return [];
    
    // Lewati UTF-8 BOM jika ada
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }
    
    $firstLine = fgets($handle);
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") {
        fseek($handle, 3);
    }
    
    $delimiter = ',';
    if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
        $delimiter = ';';
    } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
        $delimiter = "\t";
    }
    
    $rows = [];
    while (($data = fgetcsv($handle, 2000, $delimiter)) !== false) {
        $cleanData = array_map(function($v) {
            $txt = trim((string)$v);
            return trim(str_replace("\xC2\xA0", ' ', $txt));
        }, $data);
        $nonEmpty = array_filter($cleanData, function($v) { return $v !== ''; });
        if (!empty($nonEmpty)) {
            $rows[] = $cleanData;
        }
    }
    fclose($handle);
    return $rows;
}
