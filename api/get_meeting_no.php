<?php
// api/get_meeting_no.php
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/database.php';

$guru = $_GET['guru'] ?? '';
$mapel = $_GET['mapel'] ?? '';
$kelas = $_GET['kelas'] ?? '';
$ta = $_GET['ta'] ?? '';
$semester = $_GET['semester'] ?? '';

if (empty($guru) || empty($mapel) || empty($kelas)) {
    echo json_encode(['meeting_no' => 1]);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_jurnal WHERE nama_guru = ? AND mata_pelajaran = ? AND kelas = ? AND tahun_ajaran = ? AND semester = ?");
    $stmt->execute([$guru, $mapel, $kelas, $ta, $semester]);
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['meeting_no' => $count + 1]);
} catch (Exception $e) {
    echo json_encode(['meeting_no' => 1]);
}
