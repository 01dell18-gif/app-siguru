<?php
// config/auth.php - Pengelolaan Autentikasi dan Hak Akses (RBAC)
require_once __DIR__ . '/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'nama' => $_SESSION['nama'] ?? '',
        'nip' => $_SESSION['nip'] ?? '',
        'hak_akses' => $_SESSION['hak_akses'] ?? 'Pelihat',
        'foto' => $_SESSION['foto'] ?? ''
    ];
}

function isSuperAdmin() {
    $user = currentUser();
    if (!$user) return false;
    $role = $user['hak_akses'] ?? '';
    if ($role === 'Super Admin' || $role === 'admin') return true;
    $roles = array_map('trim', explode(',', $role));
    return in_array('Super Admin', $roles) || in_array('admin', $roles);
}

function isPelihat() {
    $user = currentUser();
    if (!$user) return false;
    if (isSuperAdmin()) return false;
    $roles = array_map('trim', explode(',', $user['hak_akses'] ?? ''));
    return in_array('Pelihat', $roles);
}

/**
 * Mendapatkan daftar kelas yang diampu oleh seorang guru
 * Mengambil dari:
 * 1. data_guru.sebagai_wali_kelas
 * 2. hasil_jurnal.kelas
 * 3. hasil_absen.kelas
 */
function getKelasByGuru($namaGuru) {
    if (empty($namaGuru)) return [];
    $pdo = getDBConnection();
    $classes = [];

    // 1. Wali Kelas dari data_guru
    try {
        $stmt = $pdo->prepare("SELECT sebagai_wali_kelas FROM data_guru WHERE nama_guru = ? OR nip = ? LIMIT 1");
        $stmt->execute([$namaGuru, $namaGuru]);
        $wk = $stmt->fetchColumn();
        if (!empty($wk) && $wk !== '-') {
            foreach (explode(',', $wk) as $k) {
                $k = trim($k);
                if (!empty($k) && $k !== '-' && !in_array($k, $classes)) {
                    $classes[] = $k;
                }
            }
        }
    } catch (Exception $e) {}

    // 2. Dari riwayat jurnal mengajar
    try {
        $stmtJ = $pdo->prepare("SELECT DISTINCT kelas FROM hasil_jurnal WHERE nama_guru = ?");
        $stmtJ->execute([$namaGuru]);
        foreach ($stmtJ->fetchAll(PDO::FETCH_COLUMN) as $k) {
            $k = trim($k);
            if (!empty($k) && !in_array($k, $classes)) {
                $classes[] = $k;
            }
        }
    } catch (Exception $e) {}

    // 3. Dari riwayat presensi / absen
    try {
        $stmtA = $pdo->prepare("SELECT DISTINCT kelas FROM hasil_absen WHERE nama_guru = ?");
        $stmtA->execute([$namaGuru]);
        foreach ($stmtA->fetchAll(PDO::FETCH_COLUMN) as $k) {
            $k = trim($k);
            if (!empty($k) && !in_array($k, $classes)) {
                $classes[] = $k;
            }
        }
    } catch (Exception $e) {}

    return array_values($classes);
}

/**
 * Cek apakah role user memiliki akses ke modul tertentu
 * RBAC SiGuru:
 * - Master Data:
 *   - master_guru: Super Admin
 *   - master_murid: Guru, Wali Kelas, Super Admin
 *   - master_bimbingan: Guru Wali, Super Admin
 *   - master_ekstra: Pembina Ekstra, Super Admin
 * - Form Inputan:
 *   - form_absen, form_jurnal: Guru, Super Admin
 *   - form_wali: Wali Kelas, Super Admin
 *   - form_guruwali: Guru Wali, Super Admin
 *   - form_ekstra: Pembina Ekstra, Super Admin
 * - Laporan Hasil:
 *   - laporan_absen, laporan_jurnal: Guru, Pelihat, Super Admin
 *   - laporan_wali: Wali Kelas, Pelihat, Super Admin
 *   - laporan_guruwali: Guru Wali, Pelihat, Super Admin
 *   - laporan_ekstra: Pembina Ekstra, Pelihat, Super Admin
 * - Pengaturan:
 *   - pengaturan, user_management: Super Admin
 */
function hasAccess($module) {
    if (!isLoggedIn()) return false;

    // Super Admin memiliki semua hak akses
    if (isSuperAdmin()) {
        return true;
    }

    $user = currentUser();
    $role = $user['hak_akses'] ?? '';
    $roles = array_map('trim', explode(',', $role));

    // Role Pelihat / Viewer HANYA boleh mengakses laporan
    if (in_array('Pelihat', $roles)) {
        return strpos($module, 'laporan') === 0;
    }

    switch ($module) {
        // 1. Grup Master Data
        case 'master_guru':
            return false; // Hanya Super Admin (sudah dicek di atas)

        case 'master_murid':
            return in_array('Guru', $roles) || in_array('Wali Kelas', $roles);

        case 'master_bimbingan':
            return in_array('Guru Wali', $roles);

        case 'master_ekstra':
            return in_array('Pembina Ekstra', $roles);

        // 2. Grup Form Inputan
        case 'form_absen':
        case 'form_jurnal':
            return in_array('Guru', $roles);

        case 'form_wali':
            return in_array('Wali Kelas', $roles);

        case 'form_guruwali':
            return in_array('Guru Wali', $roles);

        case 'form_ekstra':
            return in_array('Pembina Ekstra', $roles);

        // 3. Grup Laporan Hasil
        case 'laporan_absen':
        case 'laporan_jurnal':
            return in_array('Guru', $roles) || in_array('Pelihat', $roles);

        case 'laporan_wali':
            return in_array('Wali Kelas', $roles) || in_array('Pelihat', $roles);

        case 'laporan_guruwali':
            return in_array('Guru Wali', $roles) || in_array('Pelihat', $roles);

        case 'laporan_ekstra':
            return in_array('Pembina Ekstra', $roles) || in_array('Pelihat', $roles);

        case 'laporan':
            return in_array('Guru', $roles) 
                || in_array('Wali Kelas', $roles) 
                || in_array('Guru Wali', $roles) 
                || in_array('Pembina Ekstra', $roles) 
                || in_array('Pelihat', $roles);

        // 4. Grup Pengaturan Sistem
        case 'pengaturan':
        case 'user_management':
            return false; // Hanya Super Admin

        default:
            return false;
    }
}

/**
 * Cek apakah user saat ini memiliki izin untuk melakukan aksi (Edit, Update, Hapus) pada rekaman laporan.
 * Ketentuan:
 * - Super Admin memiliki akses penuh ke semua rekaman dan menu aksi.
 * - Peran Pelihat SELALU TERKUNCI (tidak memiliki izin aksi edit/hapus).
 * - Guru / Wali / Pembina hanya dapat mengubah/menghapus data miliknya sendiri sesuai hak aksesnya.
 */
function canManageReport($type, $record) {
    if (!isLoggedIn()) return false;
    if (isPelihat()) return false; // Pelihat menu aksi terkunci
    if (isSuperAdmin()) return true;

    $user = currentUser();
    $roles = array_map('trim', explode(',', $user['hak_akses'] ?? ''));
    $userNama = trim($user['nama'] ?? '');
    $userUsername = trim($user['username'] ?? '');
    $userNip = trim($user['nip'] ?? '');

    // Fungsi pencocok identitas pendidik
    $isOwner = function($targetName) use ($userNama, $userUsername, $userNip) {
        $target = trim($targetName ?? '');
        if (empty($target)) return false;
        if (!empty($userNama) && strcasecmp($target, $userNama) === 0) return true;
        if (!empty($userUsername) && strcasecmp($target, $userUsername) === 0) return true;
        if (!empty($userNip) && strcasecmp($target, $userNip) === 0) return true;
        return false;
    };

    switch ($type) {
        case 'jurnal':
            if (!in_array('Guru', $roles)) return false;
            return $isOwner($record['nama_guru'] ?? '');

        case 'absen':
            $hasRole = in_array('Guru', $roles) || in_array('Wali Kelas', $roles);
            if (!$hasRole) return false;
            return $isOwner($record['nama_guru'] ?? '');

        case 'wali':
            if (!in_array('Wali Kelas', $roles)) return false;
            if ($isOwner($record['nama_wali_kelas'] ?? '')) return true;
            return checkGuruWaliKelas($userNama, $record['kelas'] ?? '');

        case 'guruwali':
            if (!in_array('Guru Wali', $roles)) return false;
            return $isOwner($record['nama_guru_wali'] ?? '');

        case 'ekstra':
            if (!in_array('Pembina Ekstra', $roles)) return false;
            if ($isOwner($record['pembina_ekstra'] ?? '')) return true;
            return checkGuruPembinaEkstra($userNama, $record['kegiatan_ekstra'] ?? '');

        default:
            return false;
    }
}

function checkGuruWaliKelas($namaGuru, $kelas) {
    if (empty($namaGuru) || empty($kelas)) return false;
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM data_guru WHERE nama_guru = ? AND sebagai_wali_kelas = ?");
        $stmt->execute([$namaGuru, $kelas]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function checkGuruPembinaEkstra($namaGuru, $ekstra) {
    if (empty($namaGuru) || empty($ekstra)) return false;
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM data_guru WHERE nama_guru = ? AND pembina_ekstra LIKE ?");
        $stmt->execute([$namaGuru, "%$ekstra%"]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}
