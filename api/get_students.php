<?php
// api/get_students.php
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/database.php';

$kelas = $_GET['kelas'] ?? '';
if (empty($kelas)) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, nisn, nama_murid, jenis_kelamin, kelas, status FROM data_murid WHERE kelas = ? ORDER BY nama_murid ASC");
    $stmt->execute([$kelas]);
    $students = $stmt->fetchAll();
    echo json_encode($students);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
