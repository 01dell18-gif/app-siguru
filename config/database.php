<?php
// config/database.php - Koneksi Database SQL (Mendukung SQLite dan MySQL)

// Pastikan direktori session valid dan dapat ditulis di semua environment Windows/XAMPP
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'siguru_sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        @session_save_path($sessionPath);
    }
    @session_start();
}

// Aktifkan Output Buffering untuk mencegah error "headers already sent" saat redirect header()
if (!ob_get_level()) {
    ob_start();
}

// Konfigurasi Driver: 'sqlite' (bawaan, langsung jalan tanpa setup MySQL) atau 'mysql'
define('DB_DRIVER', 'sqlite'); 

// Konfigurasi MySQL jika ingin beralih ke MySQL/MariaDB XAMPP:
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'siguru_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// File SQLite Database
define('SQLITE_FILE', __DIR__ . '/../database/siguru.db');

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            $dbDir = dirname(SQLITE_FILE);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            $pdo = new PDO("sqlite:" . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");
            $pdo->exec("PRAGMA busy_timeout = 5000;");
        } else {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        die("Koneksi Database Gagal: " . $e->getMessage());
    }

    return $pdo;
}
