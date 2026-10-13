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
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nama' => $_SESSION['nama'],
        'nip' => $_SESSION['nip'] ?? '',
        'hak_akses' => $_SESSION['hak_akses'] ?? 'Pelihat'
    ];
}

function isSuperAdmin() {
    $user = currentUser();
    return $user && ($user['hak_akses'] === 'Super Admin' || $user['hak_akses'] === 'admin');
}

/**
 * Cek apakah role user memiliki akses ke modul tertentu
 * Modul: master_guru, master_murid, form_absen, form_jurnal, form_guruwali, form_wali, form_ekstra, laporan, pengaturan, user_management
 */
function hasAccess($module) {
    if (!isLoggedIn()) return false;
    $user = currentUser();
    $role = $user['hak_akses'];

    // Super Admin memiliki semua hak akses
    if ($role === 'Super Admin' || $role === 'admin') {
        return true;
    }

    // Role Pelihat / Viewer hanya boleh melihat laporan
    if ($role === 'Pelihat') {
        return strpos($module, 'laporan') === 0;
    }

    // Roles dapat berisi multi-role (misal: "Guru, Wali Kelas, Pembina Ekstra")
    $roles = array_map('trim', explode(',', $role));

    switch ($module) {
        case 'master_guru':
        case 'master_murid':
        case 'master_bimbingan':
        case 'master_ekstra':
            return in_array('Super Admin', $roles) || in_array('Admin', $roles);

        case 'form_absen':
            return in_array('Guru', $roles) || in_array('Wali Kelas', $roles) || in_array('Admin', $roles);

        case 'form_jurnal':
            return in_array('Guru', $roles) || in_array('Admin', $roles);

        case 'form_guruwali':
            return in_array('Guru Wali', $roles) || in_array('Guru', $roles) || in_array('Admin', $roles);

        case 'form_wali':
            return in_array('Wali Kelas', $roles) || in_array('Admin', $roles);

        case 'form_ekstra':
            return in_array('Pembina Ekstra', $roles) || in_array('Guru', $roles) || in_array('Admin', $roles);

        case 'laporan_absen':
        case 'laporan_jurnal':
        case 'laporan_wali':
        case 'laporan_guruwali':
        case 'laporan_ekstra':
        case 'laporan':
            // Semua peran yang login diperbolehkan melihat laporan
            return true;

        case 'pengaturan':
        case 'user_management':
            return in_array('Super Admin', $roles);

        default:
            return false;
    }
}

/**
 * Cek apakah user saat ini memiliki izin untuk melakukan aksi (Edit, Update, Hapus) pada rekaman laporan.
 * Ketentuan:
 * - Super Admin memiliki akses penuh ke semua rekaman dan menu aksi.
 * - Guru / Wali / Pembina hanya dapat mengubah/menghapus data miliknya sendiri sesuai hak aksesnya.
 * - Pengguna lain atau peran Pelihat tidak dapat melakukan perubahan.
 */
function canManageReport($type, $record) {
    if (!isLoggedIn()) return false;
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
