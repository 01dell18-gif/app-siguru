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

-- ========================================================
-- DATA AWAL / SEED DATA (Pengaturan & Akun Pengguna Default)
-- Supaya saat diimpor ke MySQL langsung dapat login & digunakan
-- ========================================================

-- 1. Inisialisasi Pengaturan Default
INSERT INTO `pengaturan` (
    `id`, `logo`, `nama_sekolah`, `tahun_ajaran`, `list_tahun_ajaran`,
    `semester`, `kelas`, `mata_pelajaran`, `list_ekstrakurikuler`,
    `kepala_sekolah`, `nip_kepala_sekolah`, `kop_surat`, `kop_size`,
    `kabupaten`, `ttd_kepsek`
) VALUES (
    1,
    'assets/images/logo.png',
    'SMK FYJ Channel Ferryxz',
    '2026/2027',
    '["2025/2026", "2026/2027", "2027/2028"]',
    'Ganjil',
    'X TKJ 1, X TKJ 2, XI RPL 1, XI RPL 2, XII TKJ',
    'Matematika, Bahasa Indonesia, Informatika, Bahasa Inggris, Pendidikan Agama, PJOK',
    'Pramuka, Paskibra, Futsal, Basket, Catur, PMR, Rohis',
    'Ferryxz Yamato',
    '1111111111111111111',
    'assets/images/kop_surat.png',
    '90',
    'Klungkung',
    'https://drive.google.com/thumbnail?id=1GUfUommgYjV-cnhU0tTB8kRJy8iXagkI'
) ON DUPLICATE KEY UPDATE `id` = `id`;

-- 2. Inisialisasi Akun Pengguna Default (Super Admin, Pelihat, Guru, Wali Kelas, Guru Wali)
INSERT INTO `data_user` (`id`, `username`, `password`, `hak_akses`, `nama`, `nip`, `foto`) VALUES
(1, 'SAdmin', 'Pengelola@Admin@Super', 'Super Admin', 'Admin Pengelola', '-', ''),
(2, 'admin', 'admin123', 'Super Admin', 'Administrator Sekolah', '198001012005011001', ''),
(3, 'ferryxz', 'gurudkv', 'Super Admin', 'Ferryxz Yamato', '1111111111111111111', ''),
(4, 'viewer', 'view123', 'Pelihat', 'Pengawas Sekolah', '197509121999031002', ''),
(5, 'pelihat', 'pelihat123', 'Pelihat', 'Pelihat Laporan Sekolah', '197509121999031003', ''),
(6, 'guru', 'guru123', 'Guru,Wali Kelas,Pembina Ekstra,Guru Wali', 'Rahmat Hidayat, S.Kom.', '198502152010011002', ''),
(7, 'wali', 'wali123', 'Wali Kelas', 'Budi Santoso, S.Pd.', '198807202014022001', ''),
(8, 'guruwali', 'gw123', 'Guru Wali', 'Siti Aminah, M.Pd.', '199003112019031005', '')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 3. Inisialisasi Contoh Data Guru Pengajar
INSERT INTO `data_guru` (`id`, `nama_guru`, `nip`, `sebagai_guru_wali`, `sebagai_wali_kelas`, `pembina_ekstra`) VALUES
(1, 'Drs. Budi Santoso, M.Pd.', '198502152010011002', 'Ya', 'X TKJ 1', 'Catur'),
(2, 'Siti Nurhaliza, S.Pd.', '198807202014022001', 'Ya', 'X TKJ 2', 'Pramuka, PMR'),
(3, 'Rahmat Hidayat, S.Kom.', '199003112019031005', 'Ya', 'XI RPL 1', 'Futsal')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 4. Inisialisasi Contoh Data Murid Induk
INSERT INTO `data_murid` (`id`, `nisn`, `nama_murid`, `jenis_kelamin`, `kelas`, `status`) VALUES
(1, '0081234567', 'Aditya Pratama Putra', 'L', 'X TKJ 1', 'Murid, Ekstra'),
(2, '0087654321', 'Annisa Nurul Hidayah', 'P', 'X TKJ 1', 'Murid, Guru Wali, Ekstra'),
(3, '0091122334', 'Bagas Dimas Saputra', 'L', 'XI RPL 1', 'Murid, Ekstra'),
(4, '0095566778', 'Cantika Putri Lestari', 'P', 'X TKJ 2', 'Murid, Guru Wali'),
(5, '0079988776', 'Dimas Arya Nugraha', 'L', 'XII TKJ', 'Murid')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 5. Inisialisasi Contoh Siswa Bimbingan Guru Wali
INSERT INTO `data_murid_guru_wali` (`id`, `nisn`, `nama_murid`, `kelas`, `jenis_kelamin`, `foto_murid`, `guru_wali`, `no_wa_murid`, `nama_ortu`, `kontak_ortu`, `catatan_khusus`) VALUES
(1, '0087654321', 'Annisa Nurul Hidayah', 'X TKJ 1', 'P', '', 'Drs. Budi Santoso, M.Pd.', '085712345678', 'Nurul Aini', '085798765432', 'Aktif berprestasi dalam kegiatan OSIS dan minat sains')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 6. Inisialisasi Contoh Siswa Ekstrakurikuler
INSERT INTO `data_murid_ekstra` (`id`, `nisn`, `nama_murid`, `jenis_kelamin`, `kelas`, `nama_ekstra`) VALUES
(1, '0081234567', 'Aditya Pratama Putra', 'L', 'X TKJ 1', 'Catur, Futsal'),
(2, '0087654321', 'Annisa Nurul Hidayah', 'P', 'X TKJ 1', 'Futsal'),
(3, '0091122334', 'Bagas Dimas Saputra', 'L', 'XI RPL 1', 'Catur')
ON DUPLICATE KEY UPDATE `id` = `id`;
