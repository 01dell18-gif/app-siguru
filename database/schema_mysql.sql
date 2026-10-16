-- ========================================================
-- SIGURU - Sistem Informasi Guru (Database Schema MySQL / MariaDB)
-- Kompatibel dengan MySQL 5.7+, MySQL 8.0+, dan MariaDB (XAMPP / phpMyAdmin)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `siguru_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `siguru_db`;

-- 1. Tabel Pengaturan
CREATE TABLE IF NOT EXISTS `pengaturan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `logo` VARCHAR(255) DEFAULT 'assets/images/logo.png',
    `nama_sekolah` VARCHAR(255) DEFAULT 'SMK NEGERI 1 CONTOH',
    `tahun_ajaran` VARCHAR(50) DEFAULT '2026/2027',
    `list_tahun_ajaran` TEXT,
    `semester` VARCHAR(20) DEFAULT 'Ganjil',
    `kelas` TEXT,
    `mata_pelajaran` TEXT,
    `folder_jurnal` TEXT,
    `folder_foto` TEXT,
    `folder_guru_wali` TEXT,
    `folder_wali` TEXT,
    `folder_ekstra` TEXT,
    `link_db_absen` TEXT,
    `link_db_jurnal` TEXT,
    `link_db_guru_wali` TEXT,
    `link_db_wali` TEXT,
    `link_db_ekstra` TEXT,
    `list_ekstrakurikuler` TEXT,
    `kepala_sekolah` VARCHAR(255) DEFAULT 'Drs. H. Ahmad Fauzi, M.Pd.',
    `nip_kepala_sekolah` VARCHAR(50) DEFAULT '19750815 199903 1 002',
    `kop_surat` VARCHAR(255) DEFAULT 'assets/images/kop_surat.png',
    `kop_size` VARCHAR(20) DEFAULT '90',
    `kabupaten` VARCHAR(100) DEFAULT 'Klungkung',
    `ttd_kepsek` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Data User (Autentikasi & RBAC)
CREATE TABLE IF NOT EXISTS `data_user` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `hak_akses` VARCHAR(50) NOT NULL,
    `nama` VARCHAR(255) NOT NULL,
    `nip` VARCHAR(50),
    `foto` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Data Guru
CREATE TABLE IF NOT EXISTS `data_guru` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_guru` VARCHAR(255) NOT NULL,
    `nip` VARCHAR(50),
    `sebagai_guru_wali` VARCHAR(20) DEFAULT 'Tidak',
    `sebagai_wali_kelas` VARCHAR(100) DEFAULT '-',
    `pembina_ekstra` VARCHAR(255) DEFAULT '-'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Data Murid (Induk Siswa)
CREATE TABLE IF NOT EXISTS `data_murid` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nisn` VARCHAR(30) UNIQUE NOT NULL,
    `nama_murid` VARCHAR(255) NOT NULL,
    `jenis_kelamin` VARCHAR(20) NOT NULL,
    `kelas` VARCHAR(100) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'Murid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Data Murid Guru Wali (Bimbingan)
CREATE TABLE IF NOT EXISTS `data_murid_guru_wali` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nisn` VARCHAR(30) NOT NULL,
    `nama_murid` VARCHAR(255) NOT NULL,
    `kelas` VARCHAR(100) NOT NULL,
    `jenis_kelamin` VARCHAR(20) NOT NULL,
    `foto_murid` TEXT,
    `guru_wali` VARCHAR(255) NOT NULL,
    `no_wa_murid` VARCHAR(50),
    `nama_ortu` VARCHAR(255),
    `kontak_ortu` VARCHAR(50),
    `catatan_khusus` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel Data Murid Ekstrakurikuler
CREATE TABLE IF NOT EXISTS `data_murid_ekstra` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nisn` VARCHAR(30) NOT NULL,
    `nama_murid` VARCHAR(255) NOT NULL,
    `jenis_kelamin` VARCHAR(20) NOT NULL,
    `kelas` VARCHAR(100) NOT NULL,
    `nama_ekstra` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabel Hasil Absen (Presensi)
CREATE TABLE IF NOT EXISTS `hasil_absen` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tanggal` DATE NOT NULL,
    `tahun_ajaran` VARCHAR(50) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `nisn` VARCHAR(30) NOT NULL,
    `nama_murid` VARCHAR(255) NOT NULL,
    `jenis_kelamin` VARCHAR(20) NOT NULL,
    `kelas` VARCHAR(100) NOT NULL,
    `keterangan` VARCHAR(50) NOT NULL,
    `mata_pelajaran` VARCHAR(255) NOT NULL,
    `nama_guru` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabel Hasil Jurnal Mengajar
CREATE TABLE IF NOT EXISTS `hasil_jurnal` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tanggal` DATE NOT NULL,
    `tahun_ajaran` VARCHAR(50) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `nama_guru` VARCHAR(255) NOT NULL,
    `mata_pelajaran` VARCHAR(255) NOT NULL,
    `jam_ke` VARCHAR(50) NOT NULL,
    `kelas` VARCHAR(100) NOT NULL,
    `pertemuan_ke` INT NOT NULL,
    `model_pembelajaran` VARCHAR(255),
    `materi_pembelajaran` TEXT NOT NULL,
    `murid_tidak_hadir` TEXT,
    `ketuntasan_materi` VARCHAR(50) DEFAULT 'tuntas',
    `keterangan_tuntas` TEXT,
    `link_bukti_jurnal` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Tabel Hasil Bimbingan Guru Wali
CREATE TABLE IF NOT EXISTS `hasil_guru_wali` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tanggal` DATE NOT NULL,
    `tahun_ajaran` VARCHAR(50) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `nama_guru_wali` VARCHAR(255) NOT NULL,
    `nama_murid` VARCHAR(255) NOT NULL,
    `kategori` VARCHAR(50) DEFAULT 'Individu',
    `topik_masalah` TEXT NOT NULL,
    `tindak_lanjut` TEXT NOT NULL,
    `keterangan` TEXT,
    `file_bukti` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Tabel Hasil Kegiatan Wali Kelas
CREATE TABLE IF NOT EXISTS `hasil_wali` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tanggal` DATE NOT NULL,
    `tahun_ajaran` VARCHAR(50) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `kelas` VARCHAR(100) NOT NULL,
    `nama_wali_kelas` VARCHAR(255),
    `deskripsi_kegiatan` TEXT NOT NULL,
    `link_bukti_wali` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Tabel Hasil Kegiatan Ekstrakurikuler
CREATE TABLE IF NOT EXISTS `hasil_ekstra` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tanggal` DATE NOT NULL,
    `tahun_ajaran` VARCHAR(50) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `nama_murid` VARCHAR(255) NOT NULL,
    `kegiatan_ekstra` VARCHAR(255) NOT NULL,
    `keterangan` TEXT,
    `link_bukti_ekstra` TEXT,
    `pembina_ekstra` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
