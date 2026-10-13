<?php
// api/quick_status.php - Update status terhubung siswa & sinkronisasi otomatis ke database
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['student_id']) || !isset($data['status_tag'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$studentId = (int)$data['student_id'];
$statusTag = trim($data['status_tag']);
$isActive = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

try {
    $pdo = getDBConnection();
    
    // Ambil data murid saat ini
    $stmt = $pdo->prepare("SELECT * FROM data_murid WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
        exit;
    }

    $cleanNisn = formatNISN($student['nisn']);
    $rawNisn = trim($student['nisn'] ?? '');
    $namaMurid = trim($student['nama_murid']);

    $currentStatuses = array_filter(array_map('trim', explode(',', $student['status'] ?? '')));
    
    if ($isActive) {
        if (!in_array($statusTag, $currentStatuses)) {
            $currentStatuses[] = $statusTag;
        }
    } else {
        $currentStatuses = array_values(array_filter($currentStatuses, function($s) use ($statusTag) {
            return $s !== $statusTag;
        }));
    }

    $newStatusString = implode(', ', $currentStatuses);
    $updateStmt = $pdo->prepare("UPDATE data_murid SET status = ? WHERE id = ?");
    $updateStmt->execute([$newStatusString, $studentId]);

    // Sinkronisasi otomatis ke data_murid_guru_wali & data_murid_ekstra sesuai PRD
    if ($statusTag === 'Guru Wali') {
        if ($isActive) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM data_murid_guru_wali WHERE nisn = ? OR nisn = ?");
            $chk->execute([$cleanNisn, $rawNisn]);
            if ($chk->fetchColumn() == 0) {
                $ins = $pdo->prepare("INSERT INTO data_murid_guru_wali (nisn, nama_murid, kelas, jenis_kelamin, guru_wali, foto_murid, no_wa_murid, nama_ortu, kontak_ortu, catatan_khusus) VALUES (?, ?, ?, ?, ?, '', '', '', '', '')");
                $ins->execute([$cleanNisn, $namaMurid, $student['kelas'], $student['jenis_kelamin'], 'Belum Ditentukan']);
            }
        } else {
            $del = $pdo->prepare("DELETE FROM data_murid_guru_wali WHERE nisn = ? OR nisn = ? OR nama_murid = ?");
            $del->execute([$cleanNisn, $rawNisn, $namaMurid]);
        }
    }

    if ($statusTag === 'Ekstra') {
        if ($isActive) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM data_murid_ekstra WHERE nisn = ? OR nisn = ?");
            $chk->execute([$cleanNisn, $rawNisn]);
            if ($chk->fetchColumn() == 0) {
                $ekstraList = getDaftarEkstra();
                $defEkstra = !empty($ekstraList) ? $ekstraList[0] : 'Pramuka';
                $ins = $pdo->prepare("INSERT INTO data_murid_ekstra (nisn, nama_murid, jenis_kelamin, kelas, nama_ekstra) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$cleanNisn, $namaMurid, $student['jenis_kelamin'], $student['kelas'], $defEkstra]);
            }
        } else {
            $del = $pdo->prepare("DELETE FROM data_murid_ekstra WHERE nisn = ? OR nisn = ? OR nama_murid = ?");
            $del->execute([$cleanNisn, $rawNisn, $namaMurid]);
        }
    }

    $actionVerb = $isActive ? 'diaktifkan & tersimpan' : 'dinonaktifkan & dihapus';
    echo json_encode([
        'success' => true,
        'message' => "Status {$statusTag} untuk {$namaMurid} berhasil {$actionVerb} di database.",
        'student_id' => $studentId,
        'student_name' => $namaMurid,
        'status_tag' => $statusTag,
        'is_active' => $isActive,
        'new_status' => $newStatusString
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan basis data: ' . $e->getMessage()]);
}
