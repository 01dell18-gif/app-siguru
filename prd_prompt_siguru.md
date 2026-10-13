# PRD & PROMPT REKAYASA: SIGURU — SISTEM INFORMASI GURU

**Aplikasi Administrasi Pendidik & Tenaga Kependidikan Terpadu (PHP Native & SQL Database)**  
*Dokumen Spesifikasi Kebutuhan Produk (PRD), Rekayasa Prompt Sistem, Arsitektur Database, dan Alur Kerja Sistem.*

---

## 📌 BAGIAN 1: INFORMASI SISTEM & IDENTITAS PROYEK

- **Nama Aplikasi**: SIGURU — Sistem Informasi Guru
- **Versi Produk**: 2.5 (Professional Enterprise Edition)
- **Teknologi Utama**:
  - **Bahasa Pemrograman**: PHP 8.x Native (Mendukung PHP 8.0, 8.1, 8.2+)
  - **Database Engine**: SQL Relasional Dual-Mode:
    - **Mode Default**: SQLite 3 (`database/siguru.db`) — Siap pakai langsung tanpa setup server database terpisah.
    - **Mode Enterprise**: MySQL / MariaDB — Terkoneksi via PDO pada `config/database.php`.
  - **User Interface (UI)**: Bootstrap 5.3.2, Bootstrap Icons 1.11.3, FontAwesome 6.5.1
  - **Komponen Interaktif**: Chart.js (Grafik Dashboard), SweetAlert2 v11 (Dialog Konfirmasi & Notifikasi Toast), Custom Chip/Tag Input.
  - **Penyimpanan Berkas**: Direktori lokal (`uploads/`) & Tautan Publik Google Drive / Google Sheets.
- **Pengembang & Hak Cipta**: **FYJ Channel** (Ferryxz Yamato)
- **Kanal YouTube Resmi**: [https://www.youtube.com/@fyjchannel](https://www.youtube.com/@fyjchannel)

---

## 🤖 BAGIAN 2: MASTER PROMPT REKAYASA AI (SYSTEM PROMPT REPLIKASI)

Jika Anda ingin meregenerasi atau mereplikasi sistem ini secara utuh menggunakan AI Assistant, gunakan prompt standar berikut:

```markdown
Anda adalah seorang Software Architect & Senior Web Developer spesialis PHP Native, SQL Database, dan Modern UI/UX.
Buatlah sebuah aplikasi web komprehensif bernama "SIGURU — Sistem Informasi Guru" untuk sekolah formal dengan ketentuan:

1. ARSITEKTUR & STACK:
   - Gunakan PHP 8.x murni (tanpa framework berat) dengan struktur kode modular, bersih, dan aman (PDO Prepared Statements, Session Protection, CSRF Guard, XSS Sanitization).
   - Database default menggunakan SQLite (`database/siguru.db`) yang langsung berfungsi tanpa konfigurasi tambahan di XAMPP / Windows / Linux, dengan opsi beralih ke MySQL (`config/database.php`).
   - Tampilan menggunakan Bootstrap 5.3, Bootstrap Icons, FontAwesome, SweetAlert2, dan Chart.js.

2. AKSES PENGGUNA (ROLE-BASED ACCESS CONTROL - RBAC):
   - Super Admin: Akses mutlak ke semua modul, Pengaturan Sistem, Manajemen User, Master Data, Form Inputan, dan Laporan.
   - Guru: Form Presensi, Jurnal Mengajar KBM, Laporan Kehadiran, Laporan Jurnal.
   - Wali Kelas: Form Kegiatan Wali Kelas, Laporan Kegiatan Wali, Presensi Siswa.
   - Guru Wali: Form Bimbingan Konseling Siswa Asuh, Laporan Bimbingan Guru Wali.
   - Pembina Ekstra: Form Kegiatan Ekstrakurikuler, Laporan Ekstrakurikuler.
   - Pelihat (Viewer / Pengawas): Akses Hanya-Baca (Read-Only) khusus untuk melihat Rekapitulasi & Laporan.
   - Dukungan Multi-Role: Pengguna dapat memiliki kombinasi peran sekaligus (misal: "Guru, Wali Kelas, Pembina Ekstra").
   - Proteksi Aksi Laporan: Tombol Edit dan Hapus pada laporan HANYA dapat dilakukan oleh guru pemilik data atau Super Admin. Pengguna lain berstatus "Terkunci (Read-Only)".

3. MODUL UTAMA:
   - Frontend Publik (index.php): Landing page modern, statistik counter, informasi akademik aktif, kartu modul, dan badge resmi subscribe YouTube FYJ Channel.
   - Halaman Login (login.php): Desain split-screen showcase interaktif, kontras tinggi, navigasi modul, dan badge kreator FYJ Channel.
   - Header Auto-Hiding Navbar (views/header.php): Navbar fluid modern yang mengecil/sembunyi saat scroll, widget jam & tanggal real-time sesuai perangkat pengguna (klien), aksi icon-only compact (Refresh, Hapus Cache, dan Avatar Profil Dropdown) agar hemat ruang dan tidak memenuhi layar.
   - Dashboard Admin (dashboard.php): Kartu metrik counter real-time, grafik kehadiran Chart.js, log aktivitas terbaru.
   - Master Data (Data Guru, Data Siswa, Murid Guru Wali, Murid Ekstra): CRUD lengkap, Quick-edit status siswa, Lightbox foto murid, Export .xls, Import Merge/Replace.
   - Formulir Pencatatan (Presensi Grid dengan Segmented H/I/S/A/D, Jurnal Mengajar dengan Auto Pertemuan Ke, Bimbingan Konseling, Catatan Wali Kelas, Latihan Ekstra).
   - Rekapitulasi & Laporan: Filter multi-kriteria dengan dropdown pilihan tahun ajaran dinamis, Pagination dinamis (10-500), Ekspor Excel, tombol link bukti rapi responsif, dan Cetak PDF resmi tab baru dengan Kop Surat resmi dan TTD Digital Kepala Sekolah.
   - Pengaturan Sistem (pengaturan.php): Identitas sekolah, dropdown tahun ajaran aktif, menu Tag Manager dinamis Tahun Ajaran (tambah data & hapus dengan tombol "x"), Tag Manager Rombel/Mapel/Ekstra, layout logo sekolah rapi tanpa terpotong, serta "Tombol Sakti: Hapus Data" (pembersihan database dengan konfirmasi Ya/Tidak dan proteksi akun Super Admin, Pelihat, Logo, Sekolah, Semester, TTD Kepsek).
   - Manajemen User (user-management.php): Pembuatan akun pengguna dengan tarik data otomatis dari master guru, multi-role assigning, dan proteksi akun induk SAdmin.
```

---

## 📋 BAGIAN 3: PRODUCT REQUIREMENT DOCUMENT (PRD) LENGKAP

### 3.1. Visi & Tujuan Produk

SIGURU dirancang untuk menggantikan pencatatan administrasi sekolah konvensional berbasis kertas maupun Google Sheets yang lambat menjadi aplikasi web berbasis SQL yang cepat, terpusat, aman, dan mudah digunakan oleh seluruh guru, wali kelas, guru wali, pembina ekstra, dan kepala sekolah.

---

### 3.2. Matriks Hak Akses Pengguna (Role-Based Access Control)

| Modul / Halaman | Super Admin | Guru Mapel | Wali Kelas | Guru Wali | Pembina Ekstra | Pelihat (Viewer) |
| --- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Dashboard Utama** | ✅ Penuh | ✅ Personal | ✅ Personal | ✅ Personal | ✅ Personal | ✅ Ringkasan |
| **Data Guru (`master-guru.php`)** | ✅ CRUD | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Data Siswa (`master-murid.php`)** | ✅ CRUD | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Murid Guru Wali (`master-bimbingan.php`)** | ✅ CRUD | ❌ | ❌ | ✅ CRUD Binaan | ❌ | ❌ |
| **Murid Ekstra (`master-ekstra.php`)** | ✅ CRUD | ❌ | ❌ | ❌ | ✅ CRUD Ekstra | ❌ |
| **Form Presensi (`form-absen.php`)** | ✅ Input | ✅ Input | ✅ Input | ❌ | ❌ | ❌ |
| **Form Jurnal KBM (`form-jurnal.php`)** | ✅ Input | ✅ Input | ❌ | ❌ | ❌ | ❌ |
| **Form Bimbingan (`form-guruwali.php`)** | ✅ Input | ❌ | ❌ | ✅ Input | ❌ | ❌ |
| **Form Wali Kelas (`form-wali.php`)** | ✅ Input | ❌ | ✅ Input | ❌ | ❌ | ❌ |
| **Form Ekstrakurikuler (`form-ekstra.php`)** | ✅ Input | ❌ | ❌ | ❌ | ✅ Input | ❌ |
| **Laporan Presensi (`laporan-absen.php`)** | ✅ CRUD Laporan | ✅ Edit Milik Sendiri | ✅ Edit Kelasnya | ❌ | ❌ | 👁️ Hanya Lihat |
| **Laporan Jurnal (`laporan-jurnal.php`)** | ✅ CRUD Laporan | ✅ Edit Milik Sendiri | ❌ | ❌ | ❌ | 👁️ Hanya Lihat |
| **Laporan Bimbingan (`laporan-guruwali.php`)** | ✅ CRUD Laporan | ❌ | ❌ | ✅ Edit Milik Sendiri | ❌ | 👁️ Hanya Lihat |
| **Laporan Wali Kelas (`laporan-wali.php`)** | ✅ CRUD Laporan | ❌ | ✅ Edit Kelasnya | ❌ | ❌ | 👁️ Hanya Lihat |
| **Laporan Ekstra (`laporan-ekstra.php`)** | ✅ CRUD Laporan | ❌ | ❌ | ❌ | ✅ Edit Ekstranya | 👁️ Hanya Lihat |
| **Pengaturan Sistem (`pengaturan.php`)** | ✅ Penuh | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Manajemen User (`user-management.php`)** | ✅ Penuh | ❌ | ❌ | ❌ | ❌ | ❌ |

> **Aturan Keamanan Ownership**: Pada seluruh tabel laporan, jika pengguna bukan Super Admin dan bukan guru yang menginput data tersebut, tombol Edit & Hapus disembunyikan dan digantikan dengan label `🔒 Terkunci`. Request POST update/delete di sisi server diverifikasi dengan fungsi `canManageReport($type, $record)`.

---

### 3.3. Rincian Fitur & Alur Kerja Antar Modul

#### A. Frontend Publik (`index.php`)

- **Hero Banner**: Menyajikan nama sekolah kapital, deskripsi portal, tombol cepat ke dashboard/login, serta badge interaktif **Subscribe YouTube FYJ Channel**.
- **Kartu Informasi Akademik Aktif**: Menampilkan Tahun Ajaran, Semester aktif, Nama Kepala Sekolah, dan Titimangsa Wilayah/Kabupaten secara dinamis dari tabel `pengaturan`.
- **Statistik Counter**: Total Guru Terdaftar, Siswa Aktif, Rombel Kelas, dan Cabang Ekstrakurikuler.
- **Workflow & Modul Showcase**: 6 kartu modul terintegrasi dengan palet warna modern.
- **Footer 4-Kolom**: Identitas sekolah, tautan navigasi, informasi pembuat web, dan hak cipta.

#### B. Halaman Login Showcase (`login.php`)

- **Split-Screen Design**:
  - Kolom Kiri: Showcase keunggulan aplikasi, identitas sekolah, periode akademik, badge resmi kreator FYJ Channel dengan tombol Subscribe, dan 6 modul utama berlatar belakang *navy glassmorphism*.
  - Kolom Kanan: Card login profesional, input username & password dengan ikon responsif, toggle lihat password, dan submit instan.
- **Sistem Autentikasi**: Memeriksa password hash Bcrypt (dengan fallback kompatibilitas plain text lama), menginisialisasi sesi user (`user_id`, `username`, `nama`, `nip`, `hak_akses`), dan menampilkan SweetAlert toast selamat datang.

#### C. Header & Navigasi Admin (`views/header.php` & `views/sidebar.php`)

- **Auto-Hiding Navbar**: Menggunakan event JavaScript `scroll` untuk menyembunyikan navbar saat halaman di-scroll ke bawah dan menampilkannya kembali saat di-scroll ke atas.
- **Tombol Cepat Refresh**: Muat ulang halaman dan data seketika.
- **Tombol Hapus Cache**: Membersihkan cache sessionStorage, localStorage, dan reload fresh dari server.
- **Profil Pengguna**: Menampilkan inisial avatar, nama lengkap, NIP, badge hak akses, dan tautan ke edit profil serta logout.

#### D. Master Data Terpadu

1. **Data Guru (`master-guru.php`)**:
   - Menampung NIP, Nama Guru, status Sebagai Guru Wali (Ya/Tidak), Sebagai Wali Kelas (pilihan rombel), dan Pembina Ekstra.
2. **Data Siswa (`master-murid.php`)**:
   - Menampung NISN, Nama Siswa, Jenis Kelamin, Kelas/Rombel, dan Status Keterlibatan.
   - **Quick-Edit Checkbox Status**: Pilihan checkbox instan (*Murid, Guru Wali, Wali Kelas, Ekstra*) yang otomatis mensinkronkan ketersediaan data siswa pada modul bimbingan dan ekstra.
3. **Data Siswa Bimbingan (`master-bimbingan.php`)**:
   - Terkoneksi dengan siswa berstatus `Guru Wali`.
   - Menampung Nama Guru Wali penanggung jawab, Kontak WA Siswa, Nama & No HP Orang Tua, Catatan Khusus, serta Foto Siswa.
   - **Lightbox Popup Foto**: Klik pada thumbnail foto akan menampilkan modal pratinjau gambar resolusi penuh.
4. **Data Siswa Ekstrakurikuler (`master-ekstra.php`)**:
   - Terkoneksi dengan siswa berstatus `Ekstra`.
   - Menampung cabang ekstrakurikuler yang diikuti oleh tiap siswa.
5. **Ekspor & Impor Excel (.xls / .csv)**:
   - Tersedia di setiap tabel master.
   - Mendukung mode **Merge** (menambahkan tanpa menghapus yang lama) dan **Replace** (menggantikan seluruh data lama).

#### E. Modul Formulir Pencatatan (Input Transaksi)

> **Aturan Wajib Bidang Nama Guru pada Seluruh Formulir Inputan**:
> Pada semua 5 formulir inputan (`form-absen.php`, `form-jurnal.php`, `form-guruwali.php`, `form-wali.php`, `form-ekstra.php`):
>
> 1. **Selain Super Admin (Guru, Wali Kelas, Guru Wali, Pembina Ekstra, Pelihat)**: Bidang nama guru langsung disertakan dan otomatis terisi dengan nama guru sesuai akun login yang aktif (`$user['nama']`), berstatus terkunci (`readonly`, `bg-light`), dan divalidasi ketat di sisi server saat penyimpanan POST sehingga tidak dapat diubah atau dimanipulasi.
> 2. **Khusus Hak Akses "Super Admin"**: Bidang nama guru disajikan dalam bentuk dropdown interaktif (`<select>`) yang memuat seluruh nama guru dari `data_guru`. Super Admin memiliki wewenang untuk memilih nama guru mana saja yang ingin dicatatkan kegiatannya atas nama guru tersebut.
> 3. **Terkoneksi dengan Hasil Laporan**: Setiap transaksi yang disimpan merekam nama guru terpilih ke kolom database terkait (`hasil_absen.nama_guru`, `hasil_jurnal.nama_guru`, `hasil_guru_wali.nama_guru_wali`, `hasil_wali.nama_wali_kelas`, `hasil_ekstra.pembina_ekstra`). Data ini langsung terkoneksi dengan seluruh modul laporan (`laporan-*.php`) dan pratinjau cetak PDF (`print-laporan.php`), sehingga guru yang bersangkutan langsung melihat rekaman miliknya dan Super Admin dapat memfilter laporan per guru.

1. **Presensi Siswa (`form-absen.php`)**:
   - Memuat bidang **Nama Guru** (dropdown bagi Super Admin, terkunci bagi guru).
   - Memilih Tanggal, Mapel, dan Kelas.
   - Sistem secara otomatis memuat seluruh siswa di kelas tersebut ke dalam tabel dinamis.
   - Input status per siswa menggunakan Segmented Radio Button:
     - 🟢 **Hadir (H)** (Default otomatis)
     - 🟡 **Izin (I)**
     - 🔵 **Sakit (S)**
     - 🔴 **Alpha (A)**
     - 🟣 **Dispen (D)**
   - Fitur "Tandai Semua Hadir" untuk efisiensi waktu guru.
2. **Jurnal Mengajar Guru (`form-jurnal.php`)**:
   - Memuat bidang **Nama Guru** (dropdown bagi Super Admin dengan auto-recalculate pertemuan, terkunci bagi guru).
   - Memilih Jam Ke, Pertemuan Ke (otomatis terhitung dari riwayat database per guru/mapel/kelas), Model Pembelajaran, Materi Pokok, Ketuntasan Kurikulum, multi-pilih Siswa Tidak Hadir, serta upload bukti dokumen/foto KBM atau tautan link Google Drive.
3. **Bimbingan Guru Wali (`form-guruwali.php`)**:
   - Memuat bidang **Nama Guru Wali** (dropdown bagi Super Admin, terkunci bagi guru wali).
   - Memilih siswa asuh dari daftar bimbingan (otomatis memfilter siswa binaan guru terpilih dengan fallback ke seluruh binaan), menentukan kategori konseling (Individu/Kelompok), topik bahasan/masalah, rekomendasi tindak lanjut, dan dokumentasi bukti.
4. **Kegiatan Wali Kelas (`form-wali.php`)**:
   - Memuat bidang **Nama Wali Kelas** (dropdown bagi Super Admin dengan auto-detect kelas binaan, terkunci bagi wali kelas).
   - Mencatat agenda kelas binaan (rapat komite, pembagian rapor, kunjungan rumah / home visit, penanganan kedisiplinan kelas) beserta dokumentasi bukti/link Drive.
5. **Kegiatan Ekstrakurikuler (`form-ekstra.php`)**:
   - Memuat bidang **Nama Guru / Pembina Ekstrakurikuler** (dropdown bagi Super Admin, terkunci bagi pembina ekstra).
   - Memilih cabang ekstra, memilih peserta yang hadir dari tabel checkbox, mencatat ringkasan materi/latihan, dan upload dokumentasi.

#### F. Modul Laporan & Cetak Dokumen PDF Resmi

1. **Filter Fleksibel & Hak Akses Nama Guru**:
   - **Filter Nama Guru Terkunci Berbasis RBAC**:
     - Pengguna **Super Admin**: Memiliki akses penuh memilih nama guru manapun dari dropdown pilihan atau memilih "Semua Guru" untuk melihat seluruh rekaman guru.
     - Pengguna **Selain Super Admin** (Guru, Wali Kelas, Guru Wali, Pembina Ekstra, Pelihat): Filter Nama Guru pada seluruh menu laporan (`laporan-jurnal.php`, `laporan-absen.php`, `laporan-guruwali.php`, `laporan-wali.php`, `laporan-ekstra.php`) dan pratinjau cetak (`print-laporan.php`) **langsung otomatis terisi** dengan nama guru sesuai hak akses akun login pengguna dan **berstatus terkunci (`🔒 [Nama Guru]`)**, sehingga tidak dapat memilih atau memanipulasi filter untuk melihat data guru lain.
   - Filter Tahun Ajaran kini menggunakan **Dropdown Pilihan Dinamis (`<select name="ta">`)** di seluruh 5 jenis laporan (`laporan-absen.php`, `laporan-jurnal.php`, `laporan-guruwali.php`, `laporan-wali.php`, `laporan-ekstra.php`), tersinkronisasi dengan master data tahun ajaran.
   - Filter Semester, Kelas/Rombel, Mata Pelajaran, dan Nama Siswa.
2. **Tombol Tautan Bukti Rapi & Responsif**:
   - Tombol `🔗 Bukti` pada seluruh tabel laporan menggunakan class khusus `.btn-bukti` dengan auto-width dinamis (`width: auto !important`), padding proporsional (`padding: 4px 12px`), gap icon 6px, dan border pill melingkar sempurna (`border-radius: 9999px`) tanpa terpotong atau terikat ukuran tombol aksi tabel 28px.
   - Header widget tanggal dan waktu perangkat menggunakan `.header-clock-widget` dengan padding proporsional (`padding: 4px 12px`), min-height 36px, dan layout rapi berjarak nyaman dari tombol aksi.
3. **Pagination Cepat**:
   - Pilihan limit tampilan: 10, 20, 50, 100, 250, 500 data per halaman.
4. **Ekspor File Excel (.xls)**:
   - Mengunduh rekapitulasi data hasil filter dalam format lembar kerja Excel instan.
5. **Cetak & Preview PDF Tab Baru (`print-laporan.php`)**:
   - Dibuka langsung pada tab baru browser.
   - **Kop Surat Resmi Sekolah**: Logo sekolah di sebelah kiri, nama instansi/sekolah huruf kapital tebal di tengah, garis pembatas kop ganda (garis tebal dan tipis), serta judul laporan huruf kapital tebal bersih tanpa garis bawah (no underline).
   - **Pilihan Tata Letak (Layout)**: Dapat disesuaikan orientasi **Portrait** (Tegak) atau **Landscape** (Mendatar).
   - **Tanda Tangan Digital Resmi**: Menampilkan titimangsa kabupaten/tanggal, kolom tanda tangan digital Kepala Sekolah (beserta NIP), dan kolom tanda tangan Guru Pengampu / Wali Kelas / Pembina Ekstra dengan nama tebal tanpa garis bawah (no underline).
   - **Ringkasan Efektivitas Mengajar**: Strip ringkasan target hari efektif, total jurnal, dan persentase efektivitas menggunakan tipografi ukuran normal serasi (9pt) selaras dengan teks tabel dokumen.

#### G. Pengaturan Sistem (`pengaturan.php`)

1. **Identitas Sekolah & Akademik**:
   - Kolom Logo Sekolah didesain proporsional (`col-md-3 col-lg-2`) agar tombol native file chooser peramban tidak terpotong.
   - Ubah Nama Sekolah, Kabupaten Titimangsa, Kepala Sekolah/NIP, dan Semester.
   - **Dropdown Tahun Ajaran Aktif (`<select name="tahun_ajaran">`)**: Memilih tahun ajaran aktif dari daftar master tahun ajaran yang tersedia secara dinamis.
2. **Kop Surat & Tanda Tangan Digital**:
   - Dukungan unggah berkas gambar langsung (`uploads/photos/`) atau tautan sharing publik Google Drive.
   - Pengaturan skala tinggi kop surat PDF (70px, 85px, 105px, 125px).
3. **Tag Manager Pilihan Master Dinamis**:
   - **Daftar Pilihan Tahun Ajaran**: Super Admin dapat menambah tahun ajaran baru (ketik tahun ajaran lalu tekan Enter atau klik Tambah) dan menghapus tahun ajaran lama dengan mengklik tombol **"x"** (`bi-x-circle-fill`). Perubahan otomatis mensinkronkan dropdown tahun ajaran aktif secara real-time.
   - Pengelolaan chips/tags interaktif untuk **Daftar Rombel / Kelas**, **Daftar Mata Pelajaran**, dan **Cabang Ekstrakurikuler**.
   - Input langsung dengan menekan tombol Enter atau klik Tambah.
   - Export & Import master pilihan ke format Excel (.xls) yang mencakup Tahun Ajaran, Kelas, Mapel, dan Ekstrakurikuler.
4. **Unsaved Changes Guard**:
   - Mencegat perpindahan halaman saat terdapat perubahan form yang belum disimpan dengan dialog konfirmasi SweetAlert2: *"Simpan & Lanjutkan"*, *"Buang Perubahan"*, atau *"Tetap di Halaman Ini"*.
5. **Tombol Sakti "Hapus Data" (Pembersihan Database Periode Baru)**:
   - **Tujuan**: Mengosongkan seluruh database transaksi dan master lama saat pergantian tahun ajaran baru atau fresh start instalasi.
   - **Tindakan Pembersihan**:
     - Menghapus seluruh rekaman transaksi: `hasil_absen`, `hasil_jurnal`, `hasil_guru_wali`, `hasil_wali`, `hasil_ekstra`.
     - Menghapus seluruh data master: `data_guru`, `data_murid`, `data_murid_guru_wali`, `data_murid_ekstra`.
     - Menghapus seluruh akun guru/wali/pembina di `data_user`.
     - Mengosongkan daftar pilihan `kelas`, `mata_pelajaran`, `list_ekstrakurikuler` di tabel `pengaturan`.
     - Mereset sequence auto-increment ID kembali ke 1.
   - **Data yang TETAP AMAN (DILINDUNGI & TIDAK DIHAPUS)**:
     - 🛡️ Akun **Super Admin** (misal `SAdmin`, `ferryxz`)
     - 🛡️ Akun **Pelihat (Hanya Laporan)** (`viewer`)
     - 🛡️ **Logo Sekolah**
     - 🛡️ **Nama Sekolah & Wilayah/Kabupaten**
     - 🛡️ **Semester & Tahun Ajaran**
     - 🛡️ **TTD Digital Kepala Sekolah & Data Kepsek**
     - 🛡️ **Kop Surat Resmi Dokumen**
   - **Notifikasi Konfirmasi Ya / Tidak**:
     - Pilihan tombol: *"Tidak, Batalkan"* (menutup modal tanpa perubahan) dan *"Ya, Hapus & Kosongkan Data"*.
     - Checkbox konfirmasi ganda wajib dicentang sebelum tombol aktif.
     - Konfirmasi dialog SweetAlert2 sekunder sebelum eksekusi transaksi database.
   - **Panduan Alur Pengisian Pasca-Pembersihan**:
     - Tampil kartu panduan terstruktur di bagian atas halaman pengaturan:
       - **Langkah 1**: Mengisi & menyimpan Pengaturan Sistem terlebih dahulu (periksa Semester/Tahun Ajaran, isi Rombel Kelas, Mapel, Ekstra, lalu klik Simpan).
       - **Langkah 2**: Mengisi seluruh Data Master secara berurutan (Data Guru, Data Siswa, Siswa Guru Wali, Siswa Ekstra, serta Akun di Manajemen User).

#### H. Manajemen User (`user-management.php`)

- Pengelolaan kredensial login seluruh staf sekolah.
- Form penambahan akun cerdas: memilih Nama Guru dari dropdown master data guru akan otomatis mengisi NIP dan menetapkan hak akses sesuai penugasan guru tersebut.
- Proteksi Khusus Akun Induk (`SAdmin`): Khusus untuk hak akses Super Admin yang usernamenya **"SAdmin"** (default) berstatus **Terkunci** (username dan role Super Admin tidak boleh diganti, serta tidak boleh dihapus). Sedangkan akun dengan hak akses Super Admin lainnya dapat diedit perannya maupun dihapus melalui antarmuka manajemen user.
- **Fitur Intip / Sembunyikan Password (Tombol Mata)**:
  - Kolom Password pada tabel daftar pengguna dilengkapi tombol mata interaktif (`bi-eye`) agar Super Admin dapat melihat seketika password pengguna yang tersimpan jika pengguna lupa kata sandi.
  - **Kerahasiaan Khusus Password Akun Induk `SAdmin`**: Khusus untuk hak akses Super Admin dengan username **"SAdmin"**, **HANYA username "SAdmin" saja** yang dapat melihat/mengintip passwordnya. Super Admin lain (seperti `ferryxz` atau akun admin tambahan) dan rol akses lain **tidak dapat melihat password SAdmin** (tombol mata ditiadakan khusus pada baris SAdmin bagi pengguna selain SAdmin, digantikan ikon proteksi gembok perisai `🔒`, dan password SAdmin tidak dikirim ke sisi klien/DOM).
  - Form Edit Pengguna otomatis memuat password yang tersimpan (dimasker secara default) dan dilengkapi tombol mata untuk menampilkan password asli atau menggantinya. Akun SAdmin hanya dapat dikelola dan diubah oleh user SAdmin sendiri.
  - Form Tambah Pengguna dilengkapi tombol mata untuk memverifikasi kata sandi baru sebelum disimpan.

---

## 🗄️ BAGIAN 4: SKEMA DATABASE SQL LENGKAP (11 TABEL)

Database tersimpan secara default pada file lokal SQLite: `database/siguru.db` (atau tabel MySQL dengan skema identik):

### 1. Tabel `pengaturan` (Konfigurasi Global Sistem)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY | ID konfigurasi (selalu row ID = 1) |
| `logo` | TEXT | Path lokal atau URL gambar logo sekolah |
| `nama_sekolah` | TEXT | Nama lengkap sekolah (Huruf Kapital) |
| `tahun_ajaran` | TEXT | Tahun ajaran aktif (contoh: 2026/2027) |
| `semester` | TEXT | Semester aktif (Ganjil / Genap) |
| `kelas` | TEXT | Daftar rombel kelas format JSON array atau string |
| `mata_pelajaran` | TEXT | Daftar mata pelajaran format JSON array atau string |
| `list_ekstrakurikuler` | TEXT | Daftar cabang ekstra format JSON array atau string |
| `folder_jurnal` | TEXT | URL Google Drive / folder penyimpanan jurnal |
| `folder_foto` | TEXT | URL Google Drive / folder penyimpanan foto |
| `folder_guru_wali` | TEXT | URL Google Drive / folder bimbingan guru wali |
| `folder_wali` | TEXT | URL Google Drive / folder kegiatan wali kelas |
| `folder_ekstra` | TEXT | URL Google Drive / folder kegiatan ekstrakurikuler |
| `kepala_sekolah` | TEXT | Nama lengkap Kepala Sekolah beserta gelar |
| `nip_kepala_sekolah` | TEXT | NIP resmi Kepala Sekolah |
| `kop_surat` | TEXT | Path berkas gambar atau tautan Google Drive kop surat |
| `kop_size` | TEXT | Ketinggian gambar kop surat PDF (70, 85, 105, 125 px) |
| `kabupaten` | TEXT | Titimangsa wilayah penerbitan dokumen laporan |
| `ttd_kepsek` | TEXT | Path berkas atau tautan Google Drive TTD digital Kepsek |

### 2. Tabel `data_user` (Kredensial & Hak Akses Pengguna)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID pengguna |
| `username` | TEXT UNIQUE | Username unik login (misal: SAdmin, guru) |
| `password` | TEXT | Kredensial password pengguna (mendukung verifikasi hash dan string terbaca untuk fitur intip kata sandi oleh Super Admin) |
| `hak_akses` | TEXT | Hak akses peran: Super Admin, Guru, Wali Kelas, dll |
| `nama` | TEXT | Nama lengkap pemilik akun |
| `nip` | TEXT | Nomor Induk Pegawai (NIP) |

### 3. Tabel `data_guru` (Master Data Tenaga Pendidik)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID data guru |
| `nama_guru` | TEXT | Nama lengkap guru beserta gelar |
| `nip` | TEXT | NIP guru |
| `sebagai_guru_wali` | TEXT | Status penugasan Guru Wali (Ya / Tidak) |
| `sebagai_wali_kelas` | TEXT | Rombel kelas binaan jika bertugas wali kelas |
| `pembina_ekstra` | TEXT | Nama cabang ekstra jika bertugas pembina |

### 4. Tabel `data_murid` (Master Induk Siswa)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID data murid |
| `nisn` | TEXT | Nomor Induk Siswa Nasional |
| `nama_murid` | TEXT | Nama lengkap siswa |
| `jenis_kelamin` | TEXT | Laki-laki (L) / Perempuan (P) |
| `kelas` | TEXT | Rombel kelas siswa saat ini |
| `status` | TEXT | Status tag keterlibatan (Murid, Guru Wali, Wali Kelas, Ekstra) |

### 5. Tabel `data_murid_guru_wali` (Siswa Binaan Guru Wali)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID siswa binaan |
| `nisn` | TEXT | NISN siswa binaan |
| `nama_murid` | TEXT | Nama siswa binaan |
| `kelas` | TEXT | Kelas siswa |
| `jenis_kelamin` | TEXT | Jenis kelamin siswa |
| `foto_murid` | TEXT | Path foto atau URL tautan foto siswa |
| `guru_wali` | TEXT | Nama guru wali pendamping |
| `no_wa_murid` | TEXT | Nomor WhatsApp aktif siswa |
| `nama_ortu` | TEXT | Nama orang tua / wali murid |
| `kontak_ortu` | TEXT | Nomor kontak orang tua murid |
| `catatan_khusus` | TEXT | Catatan khusus perkembangan / kepribadian siswa |

### 6. Tabel `data_murid_ekstra` (Siswa Anggota Ekstrakurikuler)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID siswa ekstra |
| `nisn` | TEXT | NISN siswa |
| `nama_murid` | TEXT | Nama siswa |
| `jenis_kelamin` | TEXT | Jenis kelamin siswa |
| `kelas` | TEXT | Kelas siswa |
| `nama_ekstra` | TEXT | Nama cabang ekstrakurikuler yang diikuti |

### 7. Tabel `hasil_absen` (Rekapitulasi Presensi Siswa)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID presensi |
| `tanggal` | DATE | Tanggal pelaksanaan presensi |
| `tahun_ajaran` | TEXT | Periode tahun ajaran |
| `semester` | TEXT | Semester saat presensi |
| `nisn` | TEXT | NISN siswa |
| `nama_murid` | TEXT | Nama siswa |
| `jenis_kelamin` | TEXT | Jenis kelamin |
| `kelas` | TEXT | Kelas siswa |
| `keterangan` | TEXT | Status: Hadir, Izin, Sakit, Alpha, Dispen |
| `mata_pelajaran` | TEXT | Mata pelajaran yang diajarkan |

### 8. Tabel `hasil_jurnal` (Log Pelaksanaan KBM Guru)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID log jurnal |
| `tanggal` | DATE | Tanggal pelaksanaan KBM |
| `tahun_ajaran` | TEXT | Tahun ajaran |
| `semester` | TEXT | Semester |
| `nama_guru` | TEXT | Nama guru pengampu |
| `mata_pelajaran` | TEXT | Mata pelajaran |
| `jam_ke` | TEXT | Jam pelajaran (contoh: 1-4) |
| `kelas` | TEXT | Kelas KBM |
| `pertemuan_ke` | INTEGER | Pertemuan pembelajaran ke-N |
| `model_pembelajaran` | TEXT | Model KBM (PBL, Discovery, Ceramah, dll) |
| `materi_pembelajaran` | TEXT | Rangkuman materi pokok bahasan |
| `murid_tidak_hadir` | TEXT | Daftar siswa yang tidak hadir beserta statusnya |
| `ketuntasan_materi` | TEXT | Status tuntas / belum tuntas kurikulum |
| `link_bukti_jurnal` | TEXT | Path berkas bukti upload atau link Google Drive |

### 9. Tabel `hasil_guru_wali` (Log Sesi Konseling Guru Wali)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID log konseling |
| `tanggal` | DATE | Tanggal pelaksanaan bimbingan |
| `tahun_ajaran` | TEXT | Tahun ajaran |
| `semester` | TEXT | Semester |
| `nama_guru_wali` | TEXT | Nama guru wali pelaksana |
| `nama_murid` | TEXT | Nama siswa (atau kelompok siswa) binaan |
| `kategori` | TEXT | Kategori konseling (Individu / Kelompok) |
| `topik_masalah` | TEXT | Permasalahan atau topik pembinaan |
| `tindak_lanjut` | TEXT | Solusi, kesepakatan, dan langkah tindak lanjut |
| `keterangan` | TEXT | Catatan status penyelesaian |
| `file_bukti_bimbingan` | TEXT | Path berkas bukti upload atau link Google Drive |

### 10. Tabel `hasil_wali` (Log Agenda & Kegiatan Wali Kelas)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID kegiatan wali |
| `tanggal` | DATE | Tanggal pelaksanaan kegiatan |
| `tahun_ajaran` | TEXT | Tahun ajaran |
| `semester` | TEXT | Semester |
| `kelas` | TEXT | Kelas binaan wali |
| `deskripsi_kegiatan` | TEXT | Uraian lengkap agenda kegiatan kelas |
| `link_bukti_wali` | TEXT | Path berkas bukti upload atau link Google Drive |

### 11. Tabel `hasil_ekstra` (Log Presensi & Latihan Ekstrakurikuler)

| Nama Kolom | Tipe Data | Keterangan |
| --- | --- | --- |
| `id` | INTEGER PRIMARY KEY AUTOINCREMENT | ID kegiatan ekstra |
| `tanggal` | DATE | Tanggal pelaksanaan latihan |
| `tahun_ajaran` | TEXT | Tahun ajaran |
| `semester` | TEXT | Semester |
| `nama_murid` | TEXT | Daftar siswa peserta yang hadir |
| `kegiatan_ekstra` | TEXT | Cabang ekstrakurikuler |
| `keterangan` | TEXT | Ringkasan materi latihan / kegiatan |
| `link_bukti_ekstra` | TEXT | Path berkas bukti upload atau link Google Drive |

---

## 🔒 BAGIAN 5: SISTEM KEAMANAN & KEANDALAN DATA

1. **SQL Injection Defense**: Seluruh interaksi database wajib menggunakan PDO Prepared Statements (`$stmt->prepare()` dan `$stmt->execute([...])`). Tidak ada query konkatenasi string langsung.
2. **Cross-Site Scripting (XSS) Defense**: Seluruh output variabel dinamis di-encode menggunakan `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`.
3. **Session Hardening**: Direktori sesi PHP diproteksi pada `sys_get_temp_dir() . '/siguru_sessions'`, dengan pemeriksaan session timeout dan fungsi `session_regenerate_id()`.
4. **Ownership Access Validation**: Modul laporan memverifikasi hak edit/hapus pada backend controller (`config/auth.php`) sehingga upaya manipulasi POST oleh role lain dicegah secara mutlak.
5. **Double Confirmation on Database Reset**: Tombol Sakti Hapus Data dilindungi oleh otentikasi role `isSuperAdmin()`, checkbox konfirmasi persetujuan sadar, dan modal SweetAlert2 konfirmasi ganda sebelum menjalankan transaksi SQL.
6. **File Write Permissions & SQLite Concurrency**: Direktori `database/` dan file `database/siguru.db` serta `uploads/` wajib memiliki izin akses tulis (read-write / 0777 di Linux/Unix) agar operasi `INSERT`, `UPDATE`, dan `DELETE` berhasil tanpa kendala `attempt to write a readonly database`. SQLite memerlukan izin pembuatan berkas journal sementara pada direktori yang sama saat commit transaksi.
7. **Proteksi Hak Akses Super Admin Default (SAdmin)**: Khusus akun Super Admin default dengan username "SAdmin" dilindungi secara permanen di tingkat antarmuka maupun backend controller (username tidak dapat diganti, role Super Admin terkunci dan tidak dapat diubah, serta dicegah dari penghapusan dan export). Akun pengguna lain dengan hak akses Super Admin tetap memiliki fleksibilitas untuk diedit maupun dihapus oleh administrator yang berwenang.
8. **Launcher Portabel & Multi-Device (`run_siguru.bat`)**: Dilengkapi sistem pendeteksi cerdas XAMPP (pada drive C:, D:, E:, F:, dan folder relatif) serta PHP (Laragon, standalone, dan PATH). Jika salah satu terdeteksi, aplikasi web langsung diaktifkan secara otomatis pada port bebas (bind 0.0.0.0 untuk akses LAN/Wi-Fi). Jika belum terpasang, sistem menyajikan dialog interaktif yang membimbing pengguna dan dapat langsung membuka link unduh resmi XAMPP di browser.
9. **Pilihan Eksklusif Jenis Upload Bukti (Upload File vs Link Drive)**: Pada semua modul formulir transaksional yang menyediakan input bukti (`form-jurnal.php`, `form-guruwali.php`, `form-wali.php`, `form-ekstra.php` serta seluruh modal edit laporan), sistem menerapkan pilihan salah satu (eksklusif). Jika memilih opsi Upload File, link folder Drive tidak disertakan sama sekali. Jika memilih opsi Link Drive, hanya tautan Drive yang diisi dan disimpan ke database. Pada seluruh tabel laporan web (`laporan-*.php`) dan halaman pratinjau / cetak PDF (`print-laporan.php`), bukti disajikan secara rapi dengan tombol pill aktif masing-masing (`Bukti 1`, `Bukti 2`, dst.) beserta ikon format berkas yang sesuai.
10. **Modal Edit Laporan dengan Tombol Switch Upload & Pembersihan Berkas Fisik Otomatis**: Pada seluruh modal edit laporan (`laporan-jurnal.php`, `laporan-wali.php`, `laporan-guruwali.php`, `laporan-ekstra.php`), sistem menyediakan 2 opsi upload dengan tombol switch (`Upload File` vs `Link Google Drive`) yang identik dengan skenario form input. Pengguna memilih salah satu metode penyimpanan. Berkas bukti sebelumnya ditampilkan sebagai badge informatif. Saat bukti baru disimpan atau record laporan dihapus, berkas fisik lama yang tersimpan pada `uploads/documents/` secara otomatis dibersihkan (`unlink`) dari server/disk untuk mencegah akumulasi berkas sampah, dengan pengamanan ketat terhadap path traversal (`..`) serta preservasi aman jika pengguna hanya mengedit teks tanpa mengunggah berkas pengganti baru.

---

*Dokumen ini disusun dan disinkronkan secara resmi untuk proyek SIGURU — Sistem Informasi Guru. Setiap perubahan arsitektur atau fitur sistem wajib diperbarui pada dokumen ini.*
