-- ========================================================
-- SIGURU - Sistem Informasi Guru (Database Schema SQLite)
-- Berkas ini dieksekusi otomatis oleh database/init_db.php (SQLite)
-- Untuk instalasi MySQL/MariaDB (phpMyAdmin), gunakan: database/schema_mysql.sql
-- ========================================================

-- 1. Tabel Pengaturan
CREATE TABLE IF NOT EXISTS pengaturan (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    logo TEXT DEFAULT 'assets/images/logo.png',
    nama_sekolah TEXT DEFAULT 'SMK NEGERI 1 CONTOH',
    tahun_ajaran TEXT DEFAULT '2026/2027',
    list_tahun_ajaran TEXT DEFAULT '["2025/2026", "2026/2027", "2027/2028"]',
    semester TEXT DEFAULT 'Ganjil',
    kelas TEXT DEFAULT 'X RPL 1, X RPL 2, XI RPL 1, XI RPL 2, XII RPL 1, XII RPL 2',
    mata_pelajaran TEXT DEFAULT 'Matematika, Bahasa Indonesia, Pemrograman Web, Basis Data, Bahasa Inggris, PJOK, Pendidikan Agama',
    folder_jurnal TEXT,
    folder_foto TEXT,
    folder_guru_wali TEXT,
    folder_wali TEXT,
    folder_ekstra TEXT,
    link_db_absen TEXT,
    link_db_jurnal TEXT,
    link_db_guru_wali TEXT,
    link_db_wali TEXT,
    link_db_ekstra TEXT,
    list_ekstrakurikuler TEXT DEFAULT 'Pramuka, Paskibra, Futsal, Basket, Rohis, PMR, Desain Grafis, Robotik',
    kepala_sekolah TEXT DEFAULT 'Drs. H. Ahmad Fauzi, M.Pd.',
    nip_kepala_sekolah TEXT DEFAULT '19750815 199903 1 002',
    kop_surat TEXT DEFAULT 'assets/images/kop_surat.png',
    kop_size TEXT DEFAULT '90',
    kabupaten TEXT DEFAULT 'Klungkung',
    ttd_kepsek TEXT DEFAULT 'https://drive.google.com/thumbnail?id=1GUfUommgYjV-cnhU0tTB8kRJy8iXagkI'
);

-- 2. Tabel Data User (Autentikasi & RBAC)
CREATE TABLE IF NOT EXISTS data_user (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    hak_akses TEXT NOT NULL,
    nama TEXT NOT NULL,
    nip TEXT,
    foto TEXT
);

-- 3. Tabel Data Guru
CREATE TABLE IF NOT EXISTS data_guru (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama_guru TEXT NOT NULL,
    nip TEXT,
    sebagai_guru_wali TEXT DEFAULT 'Tidak',
    sebagai_wali_kelas TEXT DEFAULT '-',
    pembina_ekstra TEXT DEFAULT '-'
);

-- 4. Tabel Data Murid (Induk Siswa)
CREATE TABLE IF NOT EXISTS data_murid (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nisn TEXT UNIQUE NOT NULL,
    nama_murid TEXT NOT NULL,
    jenis_kelamin TEXT NOT NULL,
    kelas TEXT NOT NULL,
    status TEXT DEFAULT 'Murid'
);

-- 5. Tabel Data Murid Guru Wali (Bimbingan)
CREATE TABLE IF NOT EXISTS data_murid_guru_wali (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nisn TEXT NOT NULL,
    nama_murid TEXT NOT NULL,
    kelas TEXT NOT NULL,
    jenis_kelamin TEXT NOT NULL,
    foto_murid TEXT,
    guru_wali TEXT NOT NULL,
    no_wa_murid TEXT,
    nama_ortu TEXT,
    kontak_ortu TEXT,
    catatan_khusus TEXT
);

-- 6. Tabel Data Murid Ekstrakurikuler
CREATE TABLE IF NOT EXISTS data_murid_ekstra (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nisn TEXT NOT NULL,
    nama_murid TEXT NOT NULL,
    jenis_kelamin TEXT NOT NULL,
    kelas TEXT NOT NULL,
    nama_ekstra TEXT NOT NULL,
    guru_pembina TEXT DEFAULT ''
);

-- 7. Tabel Hasil Absen (Presensi)
CREATE TABLE IF NOT EXISTS hasil_absen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tanggal DATE NOT NULL,
    tahun_ajaran TEXT NOT NULL,
    semester TEXT NOT NULL,
    nisn TEXT NOT NULL,
    nama_murid TEXT NOT NULL,
    jenis_kelamin TEXT NOT NULL,
    kelas TEXT NOT NULL,
    keterangan TEXT NOT NULL,
    mata_pelajaran TEXT NOT NULL,
    nama_guru TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 8. Tabel Hasil Jurnal Mengajar
CREATE TABLE IF NOT EXISTS hasil_jurnal (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tanggal DATE NOT NULL,
    tahun_ajaran TEXT NOT NULL,
    semester TEXT NOT NULL,
    nama_guru TEXT NOT NULL,
    mata_pelajaran TEXT NOT NULL,
    jam_ke TEXT NOT NULL,
    kelas TEXT NOT NULL,
    pertemuan_ke INTEGER NOT NULL,
    model_pembelajaran TEXT,
    materi_pembelajaran TEXT NOT NULL,
    murid_tidak_hadir TEXT,
    ketuntasan_materi TEXT DEFAULT 'tuntas',
    keterangan_tuntas TEXT,
    link_bukti_jurnal TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 9. Tabel Hasil Bimbingan Guru Wali
CREATE TABLE IF NOT EXISTS hasil_guru_wali (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tanggal DATE NOT NULL,
    tahun_ajaran TEXT NOT NULL,
    semester TEXT NOT NULL,
    nama_guru_wali TEXT NOT NULL,
    nama_murid TEXT NOT NULL,
    kategori TEXT DEFAULT 'Individu',
    topik_masalah TEXT NOT NULL,
    tindak_lanjut TEXT NOT NULL,
    keterangan TEXT DEFAULT 'Tidak ada permasalahan yang signifikan',
    file_bukti TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 10. Tabel Hasil Kegiatan Wali Kelas
CREATE TABLE IF NOT EXISTS hasil_wali (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tanggal DATE NOT NULL,
    tahun_ajaran TEXT NOT NULL,
    semester TEXT NOT NULL,
    kelas TEXT NOT NULL,
    nama_wali_kelas TEXT,
    deskripsi_kegiatan TEXT NOT NULL,
    link_bukti_wali TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 11. Tabel Hasil Kegiatan Ekstrakurikuler
CREATE TABLE IF NOT EXISTS hasil_ekstra (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tanggal DATE NOT NULL,
    tahun_ajaran TEXT NOT NULL,
    semester TEXT NOT NULL,
    nama_murid TEXT NOT NULL,
    kegiatan_ekstra TEXT NOT NULL,
    keterangan TEXT,
    link_bukti_ekstra TEXT,
    pembina_ekstra TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ========================================================
-- DATA AWAL / SEED DATA (Pengaturan & Akun Pengguna Default)
-- ========================================================

-- 1. Inisialisasi Pengaturan Default
INSERT OR IGNORE INTO pengaturan (
    id, logo, nama_sekolah, tahun_ajaran, list_tahun_ajaran,
    semester, kelas, mata_pelajaran, list_ekstrakurikuler,
    kepala_sekolah, nip_kepala_sekolah, kop_surat, kop_size,
    kabupaten, ttd_kepsek
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
);

-- 2. Inisialisasi Akun Pengguna Default (Super Admin, Pelihat, Guru, Wali Kelas, Guru Wali)
INSERT OR IGNORE INTO data_user (id, username, password, hak_akses, nama, nip, foto) VALUES
(1, 'SAdmin', 'Pengelola@Admin@Super', 'Super Admin', 'Admin Pengelola', '-', ''),
(2, 'admin', 'admin123', 'Super Admin', 'Administrator Sekolah', '198001012005011001', ''),
(3, 'ferryxz', 'gurudkv', 'Super Admin', 'Ferryxz Yamato', '1111111111111111111', ''),
(4, 'viewer', 'view123', 'Pelihat', 'Pengawas Sekolah', '197509121999031002', ''),
(5, 'pelihat', 'pelihat123', 'Pelihat', 'Pelihat Laporan Sekolah', '197509121999031003', ''),
(6, 'guru', 'guru123', 'Guru,Wali Kelas,Pembina Ekstra,Guru Wali', 'Rahmat Hidayat, S.Kom.', '198502152010011002', ''),
(7, 'wali', 'wali123', 'Wali Kelas', 'Budi Santoso, S.Pd.', '198807202014022001', ''),
(8, 'guruwali', 'gw123', 'Guru Wali', 'Siti Aminah, M.Pd.', '199003112019031005', '');

-- 3. Inisialisasi Contoh Data Guru Pengajar
INSERT OR IGNORE INTO data_guru (id, nama_guru, nip, sebagai_guru_wali, sebagai_wali_kelas, pembina_ekstra) VALUES
(1, 'Drs. Budi Santoso, M.Pd.', '198502152010011002', 'Ya', 'X TKJ 1', 'Catur'),
(2, 'Siti Nurhaliza, S.Pd.', '198807202014022001', 'Ya', 'X TKJ 2', 'Pramuka, PMR'),
(3, 'Rahmat Hidayat, S.Kom.', '199003112019031005', 'Ya', 'XI RPL 1', 'Futsal');

-- 4. Inisialisasi Contoh Data Murid Induk
INSERT OR IGNORE INTO data_murid (id, nisn, nama_murid, jenis_kelamin, kelas, status) VALUES
(1, '0081234567', 'Aditya Pratama Putra', 'L', 'X TKJ 1', 'Murid, Ekstra'),
(2, '0087654321', 'Annisa Nurul Hidayah', 'P', 'X TKJ 1', 'Murid, Guru Wali, Ekstra'),
(3, '0091122334', 'Bagas Dimas Saputra', 'L', 'XI RPL 1', 'Murid, Ekstra'),
(4, '0095566778', 'Cantika Putri Lestari', 'P', 'X TKJ 2', 'Murid, Guru Wali'),
(5, '0079988776', 'Dimas Arya Nugraha', 'L', 'XII TKJ', 'Murid');

-- 5. Inisialisasi Contoh Siswa Bimbingan Guru Wali
INSERT OR IGNORE INTO data_murid_guru_wali (id, nisn, nama_murid, kelas, jenis_kelamin, foto_murid, guru_wali, no_wa_murid, nama_ortu, kontak_ortu, catatan_khusus) VALUES
(1, '0087654321', 'Annisa Nurul Hidayah', 'X TKJ 1', 'P', '', 'Drs. Budi Santoso, M.Pd.', '085712345678', 'Nurul Aini', '085798765432', 'Aktif berprestasi dalam kegiatan OSIS dan minat sains');

-- 6. Inisialisasi Contoh Siswa Ekstrakurikuler
INSERT OR IGNORE INTO data_murid_ekstra (id, nisn, nama_murid, jenis_kelamin, kelas, nama_ekstra) VALUES
(1, '0081234567', 'Aditya Pratama Putra', 'L', 'X TKJ 1', 'Catur, Futsal'),
(2, '0087654321', 'Annisa Nurul Hidayah', 'P', 'X TKJ 1', 'Futsal'),
(3, '0091122334', 'Bagas Dimas Saputra', 'L', 'XI RPL 1', 'Catur');
