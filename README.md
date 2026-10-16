# SIGURU — Sistem Informasi Guru (Web & SQL Database)

[![PHP Version](https://img.shields.io/badge/PHP-8.0%20%7C%208.1%20%7C%208.2-blue?logo=php)](https://php.net/)
[![Database](https://img.shields.io/badge/Database-SQLite%203%20%7C%20MySQL-success?logo=sqlite)](https://sqlite.org/)
[![UI Framework](https://img.shields.io/badge/UI-Bootstrap%205.3.2-purple?logo=bootstrap)](https://getbootstrap.com/)
[![YouTube Creator](https://img.shields.io/badge/YouTube-FYJ%20Channel-red?logo=youtube)](https://www.youtube.com/@fyjchannel)

Aplikasi web administrasi digital modern dan terintegrasi untuk pendidik dan tenaga kependidikan sekolah. Dibangun dengan arsitektur **PHP 8.x Native**, sistem database fleksibel **SQLite (Bawaan Tanpa Setup)** dan **MySQL**, antarmuka modern **Bootstrap 5.3**, serta sistem hak akses ketat **Role-Based Access Control (RBAC)**.

Dikembangkan oleh: **FYJ Channel** ([https://www.youtube.com/@fyjchannel](https://www.youtube.com/@fyjchannel))

---

## 🌟 Keunggulan Arsitektur: "Langsung Nyala Tanpa Rumit"

Berbeda dengan aplikasi PHP konvensional yang mewajibkan pembuatan database manual di phpMyAdmin, konfigurasi user/password SQL, dan migrasi rumit, **SIGURU secara default menggunakan SQLite 3 (`database/siguru.db`)**.

- ✅ **Tidak perlu setup database server terpisah.**
- ✅ **Tidak perlu impor SQL manual** saat pertama kali menggunakan di PC baru.
- ✅ Cukup jalankan server web (XAMPP / PHP), aplikasi beserta seluruh data dan akun langsung siap digunakan 100%!
- ✅ Tersedia opsi beralih ke **MySQL / MariaDB** kapan saja di `config/database.php`.

---

## 🚀 Panduan Instalasi di PC Lain (Lengkap & Step-by-Step)

Pilih salah satu metode yang paling nyaman bagi Anda di PC baru:

### METODE 1: Menggunakan XAMPP (Rekomendasi & Paling Populer)

1. **Pasang XAMPP**: Pastikan PC baru sudah terinstal aplikasi [XAMPP for Windows](https://www.apachefriends.org/) (disarankan versi PHP 8.0, 8.1, atau 8.2).
2. **Salin Folder Proyek**:
   - Buka folder direktori XAMPP, biasanya di `C:\xampp\htdocs\`.
   - Copy folder proyek ini ke dalam `C:\xampp\htdocs\`.
   - *Contoh*: Jika nama foldernya `siguru`, maka lokasinya adalah `C:\xampp\htdocs\siguru\`.
3. **Nyalakan Apache di XAMPP**:
   - Buka **XAMPP Control Panel**.
   - Pada baris **Apache**, klik tombol **Start** hingga indikator berwarna hijau.
   - *(Catatan: Modul MySQL tidak wajib dinyalakan jika menggunakan database default SQLite)*.
4. **Buka di Browser**:
   - Akses alamat: `http://localhost/siguru/` atau `http://localhost/siguru/login.php`.
   - Sistem langsung aktif dengan database lokal yang sudah terisi otomatis!

---

### METODE 2: Menggunakan Launcher Cepat `run_siguru.bat` (Sekali Klik & Portabel Antar Perangkat)

Skrip launcher `run_siguru.bat` dirancang khusus agar dapat langsung dijalankan di PC / laptop mana pun:

1. Masuk ke folder proyek.
2. Cukup **klik ganda (double-click)** pada berkas `run_siguru.bat`.
3. **Fitur & Keandalan Launcher Multi-Perangkat**:
   - **Pemeriksaan Cerdas XAMPP & PHP**: Otomatis mendeteksi keberadaan XAMPP di berbagai drive (`C:\xampp`, `D:\xampp`, `E:\xampp`, `F:\xampp`, folder relatif), Laragon, PHP standalone, maupun system `PATH`.
   - **Aktivasi Langsung Otomatis**: Jika salah satu aplikasi (XAMPP atau PHP) terdeteksi, launcher langsung mengaktifkan web server secara instan tanpa perlu pengaturan manual oleh pengguna.
   - **Panduan & Pembuka Link Unduh Otomatis**: Jika di komputer tersebut belum terpasang XAMPP maupun PHP, launcher menampilkan panduan ramah pengguna dan menyediakan opsi tombol interaktif `[Y]` untuk langsung membuka halaman unduh resmi XAMPP di browser peramban.
   - **Folder & Database Auto-Check**: Otomatis memeriksa dan membuat folder database serta subfolder `uploads/` jika belum tersedia.
   - **Auto Port Failover**: Menghindari bentrok port; jika port 8080 sedang digunakan aplikasi lain, skrip otomatis beralih ke port 8081, 8082, dst.
   - **Akses Jaringan Lokal (Multi-Device / Wi-Fi)**: Web server di-bind ke `0.0.0.0`, memungkinkan akses tidak hanya dari PC lokal (`http://localhost:8080`), melainkan juga dari perangkat lain (HP, tablet, laptop guru) pada jaringan Wi-Fi/LAN yang sama melalui IP lokal yang ditampilkan.
   - **Anti Blank Connection**: Jeda otomatis 2 detik memastikan server web PHP telah siap sebelum browser membuka halaman login.

---

### METODE 3: Menggunakan PHP Built-in Server (Tanpa XAMPP)

Jika PC hanya memiliki PHP standalone di terminal/PowerShell:

1. Buka Terminal / Command Prompt / PowerShell di dalam folder proyek ini.
2. Jalankan perintah: `php -S localhost:8080`
3. Buka browser dan akses `http://localhost:8080/login.php`.

---

### METODE 4: Menggunakan Database MySQL / MariaDB (Opsional)

Jika instansi atau sekolah Anda ingin menggunakan server MySQL XAMPP:

1. Buka XAMPP Control Panel, klik **Start** pada modul **Apache** dan **MySQL**.
2. Buka browser ke `http://localhost/phpmyadmin/`.
3. Buat database baru dengan nama: `siguru_db`.
4. Pilih tab **Import**, pilih berkas `database/schema_mysql.sql` dari proyek ini, lalu klik **Import**.
5. Buka berkas `config/database.php` pada editor teks, ubah pengaturan koneksi driver dari `'sqlite'` ke `'mysql'` (`DB_DRIVER = 'mysql'`).
6. Simpan file. SIGURU kini terhubung langsung ke database MySQL XAMPP.

---

## 🛠️ Petunjuk Operasi Database (Tambah, Edit, Update, & Hapus Data)

Agar database dapat berfungsi dengan normal di PC mana pun dan data dapat **ditambah (Create)**, **dilihat (Read)**, **diubah (Update)**, maupun **dihapus (Delete)**, ikuti petunjuk penting berikut:

### 1. Izin Tulis File & Folder Database (Paling Krusial!)

Database default menggunakan berkas SQLite (`database/siguru.db`). Saat PHP mengeksekusi operasi simpan, ubah, atau hapus:

- SQLite membutuhkan **izin tulis (Write Permission)** tidak hanya pada file `siguru.db`, tetapi juga pada folder pembungkusnya `database/` (karena SQLite secara otomatis membuat berkas pengunci sementara seperti `siguru.db-journal` di folder tersebut).
- **Cara memastikan izin tulis di Windows / XAMPP**:
  1. Buka File Explorer, masuk ke folder proyek `siguru`.
  2. Klik kanan pada folder `database` -> pilih **Properties**.
  3. Pada tab **General**, pastikan kotak centang **Read-only (Hanya-baca)** **tidak tercentang** (kosong/uncheck). Klik **Apply** -> pilih *Apply changes to this folder, subfolders and files* -> klik **OK**.
  4. Lakukan hal yang sama untuk folder `uploads` agar fitur upload foto murid, bukti jurnal, kop surat, dan TTD kepsek dapat menulis berkas baru.
- **Cara memastikan izin tulis di Linux / macOS / VPS**: Jalankan `chmod -R 777 database/` dan `chmod -R 777 uploads/`.

### 2. Memastikan Driver PDO SQLite Aktif di PHP

Pada XAMPP standar Windows, ekstensi ini biasanya sudah aktif. Namun jika di PC lain muncul pesan error `could not find driver`:

1. Buka XAMPP Control Panel, klik tombol **Config** di baris **Apache** -> pilih **PHP (php.ini)**.
2. Cari baris `extension=pdo_sqlite` dan `extension=sqlite3`.
3. Pastikan **tidak ada tanda titik koma (`;`)** di awal baris tersebut.
4. Simpan berkas `php.ini`, lalu klik **Stop** dan **Start** ulang Apache.

### 3. Cara Mengoperasikan Database (CRUD)

#### CARA 1: Melalui Antarmuka Web SIGURU (Rekomendasi)

- **Login Sebagai Super Admin** (Gunakan username `SAdmin` atau `ferryxz`) untuk mendapatkan wewenang penuh ke semua tabel dan fitur CRUD:
  - **Tambah Data (Create)**:
    - *Master Data*: Masuk ke menu *Data Guru* atau *Data Siswa* -> klik tombol **Tambah Data** -> lengkapi formulir modal -> klik **Simpan**.
    - *Pencatatan*: Masuk ke formulir *Presensi*, *Jurnal*, *Bimbingan*, *Wali Kelas*, atau *Ekstrakurikuler* -> isi kegiatan -> klik **Simpan**.
  - **Ubah / Edit Data (Update)**:
    - Pada tabel master data atau tabel laporan, klik ikon **Edit (Pensil Kuning / Biru)** pada baris data yang ingin diubah -> sesuaikan informasi -> klik **Perbarui**.
  - **Hapus Data (Delete)**:
    - Klik ikon **Hapus (Tempat Sampah Merah)** pada baris data -> klik konfirmasi **Ya** pada dialog konfirmasi.
  - **Hapus & Bersihkan Seluruh Database (Tahun Ajaran Baru)**:
    - Masuk ke menu **Pengaturan Sistem** -> klik **Tombol Sakti: Hapus Data** -> baca peringatan -> centang kotak persetujuan -> klik **Ya, Hapus & Kosongkan Data**.

#### CARA 2: Mengelola / Mengedit Database Langsung Menggunakan GUI

Jika Anda ingin melihat atau mengedit database secara langsung di luar web:

- **Untuk Database SQLite (`database/siguru.db`)**:
  1. Unduh aplikasi gratis **[DB Browser for SQLite](https://sqlitebrowser.org/)** (tersedia versi Portable tanpa install).
  2. Buka aplikasi, klik **Open Database** -> pilih file `database/siguru.db`.
  3. Buka tab **Browse Data** -> pilih tabel yang ingin diedit (contoh: `data_murid`, `data_user`, `pengaturan`).
  4. Anda dapat:
     - Mengubah isi kolom langsung dengan klik ganda (*double-click*).
     - Menambah baris baru dengan klik **New Record**.
     - Menghapus baris dengan memilih baris lalu klik **Delete Record**.
  5. Setelah selesai mengubah, klik tombol **Write Changes** di toolbar atas untuk menyimpan perubahan secara permanen!
- **Untuk Database MySQL (`siguru_db`)**:
  1. Buka browser: `http://localhost/phpmyadmin/`.
  2. Klik database `siguru_db` di panel sebelah kiri.
  3. Klik tabel yang diinginkan -> gunakan tab **Browse** untuk mengedit/menghapus baris, atau tab **Insert** untuk menambah baris data baru.

---

## 🌐 Cara Menggunakan & Membagikan Project via GitHub

Dengan arsitektur SQLite, Anda dapat menyimpan seluruh web sekaligus databasenya ke GitHub sehingga rekan kerja atau PC lain dapat meng-clone dan langsung menjalankannya tanpa ribet.

### Langkah A: Mengunggah (Push) Proyek ke GitHub

1. Pastikan Git sudah terpasang di PC Anda ([Git for Windows](https://git-scm.com/)).
2. Buka Terminal / PowerShell di folder proyek ini (`C:\xampp\htdocs\siguru`).
3. Inisialisasi repository Git lokal: jalankan `git init`.
4. Tambahkan semua berkas ke staging dan commit: jalankan `git add .` dan `git commit -m "Inisialisasi Project SiGuru"`.
5. Buat repositori baru di akun [GitHub](https://github.com/new) Anda (misal: `siguru-app`), biarkan kosong tanpa README.
6. Hubungkan dan unggah ke GitHub: jalankan `git branch -M main`, `git remote add origin https://github.com/USERNAME_ANDA/siguru-app.git`, dan `git push -u origin main`.

---

### Langkah B: Mengambil (Clone) & Menjalankan di PC Lain dari GitHub

Ketika Anda berpindah ke komputer atau laptop lain:

1. Buka Command Prompt / PowerShell di folder htdocs XAMPP komputer tersebut: `cd C:\xampp\htdocs`.
2. Lakukan clone repositori: `git clone https://github.com/USERNAME_ANDA/siguru-app.git siguru`.
3. Nyalakan **Apache** di XAMPP Control Panel.
4. Buka browser ke alamat `http://localhost/siguru/` atau `http://localhost/siguru/login.php`.
5. **Aplikasi langsung menyala lengkap dengan datanya!** Tidak perlu konfigurasi rumit tambahan apapun.

---

## 🔑 Akun Login Terdaftar (Default Akun Sistem)

| Username | Password | Hak Akses / Peran | Nama Pengguna | NIP |
| --- | --- | --- | --- | --- |
| **`SAdmin`** | `Pengelola@Admin@Super` | Super Admin | Admin Pengelola | - |
| **`fyj`** | `gurudkv` | Super Admin | FYJ Channel | 1111111111111111111 |
| **`guru`** | `guru123` | Guru, Wali Kelas, Pembina Ekstra, Guru Wali | Rahmat Hidayat, S.Kom. | 198502152010011002 |
| **`wali`** | `wali123` | Wali Kelas | Budi Santoso, S.Pd. | 198807202014022001 |
| **`guruwali`** | `gw123` | Pembina Ekstra | Siti Aminah, M.Pd. | 199003112019031005 |
| **`viewer`** | `view123` | Pelihat (Hanya Laporan) | Pengawas Sekolah | 197509121999031002 |

> **Catatan Keamanan Manajemen User**:
>
> 1. **Akun Induk Terkunci**: Khusus akun **Super Admin** default dengan username **`SAdmin`**, sistem mengunci akun ini secara permanen (username dan hak akses tidak dapat diganti, berstatus `🔒 Terkunci`, serta tidak dapat dihapus) guna menjamin ketersediaan akses induk sistem.
> 2. **Kerahasiaan Password `SAdmin`**: HANYA username **`SAdmin`** (saat login sebagai SAdmin) yang dapat melihat password akun SAdmin. Super Admin lain (seperti `ferryxz` atau akun admin baru) dan rol akses lain **tidak dapat melihat password SAdmin** (tombol mata ditiadakan dan password tidak dikirim ke klien).
> 3. **Super Admin Lain**: Akun pengguna dengan hak akses Super Admin lainnya (misalnya `ferryxz` atau admin tambahan) dapat diedit hak aksesnya serta dapat dihapus oleh Super Admin yang berwenang.

---

## ⚡ Fitur Unggulan Sistem

1. **Role-Based Access Control (RBAC) Multilevel**:
   - Membatasi akses menu sesuai tugas pendidik (Guru Mapel, Wali Kelas, Guru Wali, Pembina Ekstra, Pelihat).
   - Pengguna dapat memiliki kombinasi beberapa peran sekaligus.
   - Proteksi Ownership pada Laporan: Aksi edit/hapus pada laporan hanya dapat dilakukan oleh guru pemilik data atau Super Admin. Pengguna lain berstatus `🔒 Terkunci`.
   - Proteksi Khusus Akun Induk SAdmin: Akun Super Admin default `SAdmin` terkunci mutlak dari modifikasi username, role, dan penghapusan, serta passwordnya dirahasiakan mutlak dari admin lain. Sementara Super Admin lainnya dapat dikelola dan dihapus secara fleksibel.

2. **Dynamic Attendance Grid**:
   - Input presensi per kelas dengan segmented radio button (🟢 Hadir, 🟡 Izin, 🔵 Sakit, 🔴 Alpha, 🟣 Dispen) dan tombol instan *"Tandai Semua Hadir"*.

3. **Jurnal Mengajar Otomatis**:
   - Deteksi nomor *Pertemuan Ke* otomatis dari riwayat pembelajaran guru, multi-select siswa yang tidak hadir, dan unggah foto/dokumen KBM.

4. **Cetak Dokumen PDF Resmi (Tab Baru)**:
   - Preview PDF instan di tab baru dengan Kop Surat Resmi (Logo, Nama Sekolah Huruf Kapital, Garis Ganda Pembatas Kop, Judul Laporan bersih tanpa garis bawah / no underline).
   - Pilihan tata letak **Portrait** atau **Landscape**.
   - Tanda tangan digital resmi Kepala Sekolah dan Guru Pengampu / NIP dengan nama tebal tanpa garis bawah (no underline).

5. **Tombol Sakti: "Hapus Data" (Reset Periode Baru)**:
   - Disediakan di menu **Pengaturan Sistem** khusus Super Admin untuk mengosongkan rekaman data transaksi dan master lama saat pergantian tahun ajaran baru.
   - **Data yang TETAP AMAN (TIDAK Dihapus)**:
     - 🛡️ Akun **Super Admin**
     - 🛡️ Akun **Pelihat (Hanya Laporan)**
     - 🛡️ **Logo Sekolah**
     - 🛡️ **Nama Sekolah & Wilayah Titimangsa**
     - 🛡️ **Semester & Tahun Ajaran**
     - 🛡️ **TTD Digital Kepala Sekolah & NIP**
     - 🛡️ **Kop Surat Resmi Dokumen**
   - Disertai modal konfirmasi **Ya / Tidak**, kotak persetujuan sadar, dan panduan instruksi bertahap pasca-pembersihan (Wajib melengkapi Pengaturan Sistem terlebih dahulu, kemudian mengisi Data Master).

6. **Integrasi Kanal YouTube Kreator**:
   - Dilengkapi badge interaktif subscribe resmi ke kanal [FYJ Channel (@fyjchannel)](https://www.youtube.com/@fyjchannel) pada beranda publik dan showcase login.

7. **Widget Jam & Tanggal Real-Time Perangkat**:
   - Header aplikasi dilengkapi penunjuk tanggal dan jam digital real-time interaktif (`.header-clock-widget`) dengan padding proporsional (`padding: 4px 12px`), min-height 36px, dan margin rapi yang otomatis berdetik presisi sesuai perangkat/komputer klien yang digunakan pengguna.

8. **Fitur Intip Password / Tombol Mata (Manajemen User)**:
   - Super Admin dapat melihat kata sandi staf yang tersimpan sewaktu-waktu jika lupa password melalui ikon mata (`bi-eye`) di tabel akun maupun modal Edit Pengguna.

9. **Dropdown Pilihan Tahun Ajaran di Seluruh Filter Laporan**:
   - Di seluruh modul rekapitulasi (`laporan-absen.php`, `laporan-jurnal.php`, `laporan-wali.php`, `laporan-guruwali.php`, `laporan-ekstra.php`), filter Tahun Ajaran kini menggunakan dropdown `<select>` dinamis berbasis master data (bukan lagi input teks manual), memudahkan pemilihan periode akademik.

10. **Tag Manager Tahun Ajaran & Sinkronisasi Pengaturan Sistem**:
    - Di menu **Pengaturan Sistem**, Super Admin dapat mengelola daftar tahun ajaran secara interaktif: menambah tahun ajaran baru via input dan tombol "+ Tambah", serta menghapus tahun ajaran lama dengan mengklik tombol tanda silang **"x"** (`bi-x-circle-fill`).
    - Dropdown Tahun Ajaran Aktif tersinkronisasi otomatis secara real-time dengan daftar tag master tersebut.

11. **Penyempurnaan Tampilan Visual Kotak, Padding & Tombol Bukti**:
    - Tombol tautan dokumen bukti (`🔗 Bukti`) di seluruh tabel laporan ditata rapi menggunakan class khusus `.btn-bukti` dengan auto-width dinamis (`width: auto !important`), padding proporsional (`padding: 4px 12px`), gap icon 6px, dan border pill melingkar sempurna (`border-radius: 9999px`) tanpa terpotong atau terikat ukuran tombol aksi tabel 28px.
    - Kotak widget tanggal dan jam di header diberi padding dan margin proporsional sehingga teks tidak lagi menabrak garis border.
    - Kolom pemilih berkas "Logo Sekolah" di Pengaturan Sistem diperluas proporsional sehingga tombol bawaan browser tidak terpotong.
    - Strip ringkasan "Data Efektivitas Mengajar Guru" pada cetak PDF disesuaikan menggunakan ukuran font normal serasi (9pt) selaras dengan seluruh teks tabel dokumen.

12. **Filter Nama Guru Otomatis & Terkunci (RBAC Laporan)**:
    - Di seluruh modul rekapitulasi laporan (`laporan-jurnal.php`, `laporan-absen.php`, `laporan-guruwali.php`, `laporan-wali.php`, `laporan-ekstra.php`) dan halaman pratinjau cetak PDF (`print-laporan.php`):
      - **Super Admin**: Bebas memilih nama guru manapun dari daftar dropdown atau memilih opsi *"Semua Guru"* untuk melihat seluruh rekap data.
      - **Selain Super Admin** (Guru, Wali Kelas, Guru Wali, Pembina Ekstra, Pelihat): Filter nama guru **langsung otomatis terisi** dengan nama guru sesuai hak akses akun login dan **terkunci permanen (`🔒 [Nama Akun]`)** baik di tampilan filter maupun pada query server-side, sehingga pengguna tidak dapat memilih atau melihat laporan guru lain.

13. **Bidang Nama Guru di Seluruh Form Inputan & Terkoneksi ke Laporan**:
    - Pada semua 5 formulir pencatatan transaksi (`form-absen.php`, `form-jurnal.php`, `form-guruwali.php`, `form-wali.php`, `form-ekstra.php`):
      - **Selain Super Admin** (Guru Mapel, Wali Kelas, Guru Wali, Pembina Ekstra): Bidang nama guru **langsung disertakan dan terkunci otomatis** sesuai nama akun login (`readonly`, `bg-light`), dengan validasi ketat di sisi server (POST) sehingga identitas pencatat tidak dapat dimanipulasi.
      - **Khusus Super Admin**: Bidang nama guru berupa dropdown pilihan interaktif (`<select>`) dari master data guru, memberikan keleluasaan bagi Super Admin untuk memilih dan menginput kegiatan atas nama guru mana pun.
      - **Koneksi Langsung ke Laporan & Cetak PDF**: Data nama guru yang tersimpan langsung terhubung ke database dan tersaring pada modul laporan serta lembar pengesahan tanda tangan PDF masing-masing guru pengampu.

14. **Pilihan Eksklusif Jenis Upload Bukti (Upload File vs Link Drive)**:
    - **Formulir Transaksional**: Pada semua modul input dan edit (`form-jurnal.php`, `form-guruwali.php`, `form-wali.php`, `form-ekstra.php` serta modal edit pada modul laporan), pengguna **hanya memilih salah satu jenis upload bukti**:
      - **Upload File**: Pengguna dapat memilih satu atau beberapa file sekaligus (`multiple`). Link bukti folder Google Drive **tidak disertakan sama sekali** (input link di-disable dan diabaikan di sisi server).
      - **Link Drive**: Hanya tautan Google Drive yang disimpan ke sistem; file upload diabaikan/tidak disertakan.
    - **Tampilan Laporan Web & PDF**: Pada seluruh tabel laporan (`laporan-*.php`) dan cetak PDF (`print-laporan.php`), bukti disajikan rapi sebagai pill tombol aktif masing-masing (`Bukti 1`, `Bukti 2`, dst.) dengan ikon format yang sesuai.

15. **Modal Edit Laporan dengan Tombol Switch Upload & Pembersihan Berkas Fisik Otomatis**:
    - **Dua Opsi Pilihan (Switch Mode)**: Pada seluruh modal edit laporan (`laporan-jurnal.php`, `laporan-wali.php`, `laporan-guruwali.php`, `laporan-ekstra.php`), input bukti dilengkapi tombol switch (`Upload File` vs `Link Google Drive`) yang persis sama dengan alur formulir input.
    - **Pratinjau Bukti Aktif**: Modal edit menampilkan berkas/link yang saat ini terpasang dalam bentuk badge pill interaktif sehingga pengguna dapat memeriksa bukti yang sudah ada sebelumnya.
    - **Penyimpanan Berdasarkan Pilihan**: Pengguna memilih salah satu opsi. Jika memilih unggah berkas baru, berkas tersebut yang disimpan ke database. Jika beralih ke opsi tautan Google Drive, link tersebut yang disimpan.
    - **Pembersihan Berkas Lama Otomatis (Anti Sampah Penyimpanan)**: Saat bukti baru disimpan atau saat baris laporan dihapus, berkas fisik lama yang tersimpan di `uploads/documents/` secara otomatis dihapus (`unlink`) dari server/disk penyimpanan lokal, menjamin efisiensi ruang penyimpanan dan konsistensi data.
    - **Preservasi Aman**: Jika pengguna dalam mode file tidak memilih berkas baru (hanya mengoreksi teks deskripsi atau tanggal), berkas yang sudah ada sebelumnya tetap dipertahankan secara utuh tanpa terhapus.

16. **Proteksi & Kunci Menu Data Siswa (`master-murid.php`)**:
    - Untuk pengguna non-Super Admin (Guru Mapel & Wali Kelas), tombol **Tambah Siswa** dan **Import Excel** disembunyikan.
    - Kolom aksi (Edit & Hapus) terkunci dengan badge `🔒 Terkunci`, dan quick-edit checkbox status dinonaktifkan (`disabled`).
    - Hanya Super Admin yang memiliki wewenang penuh memodifikasi master data siswa.

17. **Kunci Nama Guru pada Siswa Bimbingan (`master-bimbingan.php`)**:
    - Filter nama guru pada data siswa bimbingan otomatis terkunci sesuai akun Guru Wali yang login.
    - Hanya Super Admin yang dapat memilih nama guru wali lain dari dropdown.

18. **Dukungan Input Manual Ketuntasan Materi (`form-jurnal.php` & `laporan-jurnal.php`)**:
    - Bidang **Ketuntasan Materi** mendukung pengetikan manual secara bebas dengan bantuan auto-suggest HTML5 datalist, serta tombol dropdown pilihan cepat (*Tuntas*, *Berlanjut*, *Belum Tuntas*, *Remedial*, *-*).

19. **Otomatisasi Kelas & Pembatasan Filter Kelas Laporan Wali Kelas (`laporan-wali.php`)**:
    - Filter kelas otomatis terisi sesuai kelas binaan wali kelas tersebut.
    - Jika guru tidak mengampu kelas, filter kelas dibiarkan kosong (`(Tidak Mengampu Kelas)`).
    - Hanya Super Admin yang dapat memilih kelas secara bebas dari dropdown.

20. **Restriksi Cabang Ekstrakurikuler Khusus Pembina (`form-ekstra.php` & `laporan-ekstra.php`)**:
    - Bidang dan filter Cabang Ekstrakurikuler secara otomatis disaring hanya pada cabang yang dibina oleh guru bersangkutan.
    - Jika membina 1 cabang: otomatis terisi dan terkunci.
    - Jika membina lebih dari 1 cabang: disajikan dalam bentuk dropdown pilihan cabang binaan guru tersebut.

21. **Pencarian Cepat Siswa Dapat Diketik (Searchable Combobox) pada Master Bimbingan & Master Ekstra**:
    - Input pemilihan siswa dari DataMurid pada modal Tambah Siswa Bimbingan (`master-bimbingan.php`) dan Tambah Anggota Ekstra (`master-ekstra.php`) dapat diketik langsung untuk memfilter nama siswa, NISN, atau rombel kelas secara cepat.
    - Dilengkapi navigasi keyboard (panah atas/bawah, enter, escape), tombol clear instan, opsi manual, dan pengisian otomatis ke semua kolom formulir.

22. **Proteksi & Kunci Menu Data Siswa Ekstrakurikuler (`master-ekstra.php`)**:
    - Untuk pengguna non-Super Admin, tombol **Tambah Anggota Ekstra** dan **Import Excel** disembunyikan.
    - Kolom aksi (Edit & Hapus) terkunci dengan badge `🔒 Terkunci`.
    - Di sisi backend controller, seluruh request POST (`create`, `update`, `delete`, `import`) diverifikasi secara ketat melalui fungsi `isSuperAdmin()`.

23. **Data Guru Pembina Sesuai Cabang Ekstrakurikuler & Dynamic Repeater (`master-ekstra.php`)**:
    - Tabel Data Siswa Ekstrakurikuler menyajikan kolom data **Guru Pembina** sesuai cabang ekstra yang diikuti siswa (diambil dari `data_guru.pembina_ekstra`).
    - Jika siswa memilih lebih dari 1 Cabang Ekstrakurikuler, setiap cabang menampilkan guru pembinanya masing-masing secara terstruktur.
    - Pada modal Tambah dan Edit, pemilihan cabang disajikan dengan komponen dynamic repeater (+ Tambah Cabang) yang menyajikan dropdown cabang ekstra sekaligus menampilkan guru pembinanya secara otomatis dan interaktif.
    - Ekspor data ke Excel & CSV menyertakan kolom data Guru Pembina secara lengkap.

24. **Mode Switch Foto Profil Pengguna & Keselarasan Desain Formulir (`profil.php`)**:
    - Pengelolaan foto profil pengguna mendukung dua metode penyimpanan yang dapat dipilih salah satu (mode switch) antara **Upload Berkas** dan **Tautan URL Foto**, serta opsi **Tetap (Gunakan Saat Ini)**.
    - Desain antarmuka diselaraskan secara konsisten dengan formulir upload lain (`form-jurnal.php`, `form-wali.php`, dll.) menggunakan segmented control pill interaktif (`btn-outline-primary`), pratinjau live avatar instan, dan validasi berkas client-side.
    - Mendukung tautan URL gambar web langsung maupun Google Drive dengan konversi otomatis ke resolusi optimal.

25. **Pengecekan & Pengecualian Nama Siswa yang Sudah Digunakan (`master-bimbingan.php` & `master-ekstra.php`)**:
    - Pada fitur *Pilih Siswa dari DataMurid* di modal **Tambah Data Siswa Bimbingan** dan **Tambah Anggota Ekstra**, sistem melakukan pengecekan otomatis ke database: siswa yang namanya/NISN-nya sudah terdaftar dalam `data_murid_guru_wali` atau `data_murid_ekstra` **tidak ditampilkan lagi** pada daftar pencarian.
    - Dilengkapi pesan informatif jika seluruh siswa DataMurid telah terdaftar, serta validasi backend controller yang menolak penambahan duplikat jika dicoba di-submit ulang.

---

## 📁 Struktur Direktori Proyek

```text
App Admin SQL/
├── assets/                  # Aset CSS kustom, JavaScript aplikasi, dan gambar statis
│   ├── css/style.css        # Desain visual modern & responsif
│   ├── js/app.js            # Interaktivitas client-side, jam real-time, & auto-hide navbar
│   └── images/              # Logo sekolah & kop surat bawaan
├── config/                  # Konfigurasi inti sistem
│   ├── database.php         # Koneksi database PDO (SQLite / MySQL)
│   ├── auth.php             # Utilitas autentikasi, sesi, & RBAC ownership
│   └── helper.php           # Fungsi pembantu, sanitasi, flash message, export Excel
├── database/                # Berkas basis data
│   ├── siguru.db            # Database SQLite aktif (otomatis digunakan)
│   ├── schema.sql           # Skema tabel SQL untuk MySQL / backup
│   └── init_db.php          # Skrip inisialisasi awal tabel dan akun default
├── views/                   # Template layout bersama
│   ├── header.php           # Auto-hiding navbar, jam real-time perangkat, aksi icon-only compact (refresh, cache, user)
│   ├── sidebar.php          # Menu navigasi samping berbasis RBAC
│   └── footer.php           # Lightbox modal, bundle JS, & SweetAlert2 handler
├── uploads/                 # Direktori penyimpanan unggahan berkas lokal
│   ├── photos/              # Foto murid bimbingan, logo, kop, dan TTD
│   └── documents/           # Berkas bukti jurnal, bimbingan, wali, & ekstra
├── index.php                # Landing page publik & showcase akademik
├── login.php                # Halaman autentikasi profesional (split-screen)
├── dashboard.php            # Panel kontrol admin dengan Chart.js
├── master-guru.php          # Manajemen data induk guru
├── master-murid.php         # Manajemen data induk siswa & quick status
├── master-bimbingan.php      # Manajemen data siswa binaan & lightbox foto
├── master-ekstra.php        # Manajemen data peserta ekstrakurikuler
├── form-absen.php           # Formulir input presensi harian siswa
├── form-jurnal.php          # Formulir agenda mengajar guru (KBM)
├── form-guruwali.php        # Formulir bimbingan konseling guru wali
├── form-wali.php            # Formulir agenda kegiatan wali kelas
├── form-ekstra.php          # Formulir kegiatan latihan ekstrakurikuler
├── laporan-absen.php        # Rekapitulasi & filter presensi
├── laporan-jurnal.php       # Rekapitulasi & filter jurnal mengajar
├── laporan-guruwali.php     # Rekapitulasi & filter bimbingan konseling
├── laporan-wali.php         # Rekapitulasi & filter kegiatan wali kelas
├── laporan-ekstra.php       # Rekapitulasi & filter ekstrakurikuler
├── print-laporan.php        # Generator cetak PDF resmi (tab baru)
├── pengaturan.php           # Konfigurasi sekolah & Tombol Sakti Hapus Data
├── user-management.php      # Manajemen akun pengguna & multi-role assigning
├── profil.php               # Halaman edit profil & kata sandi mandiri
├── logout.php               # Penghancur sesi & pengarah ke halaman login
├── prd_prompt_siguru.md     # Spesifikasi PRD lengkap & master AI prompt
├── README.md                # Panduan instalasi, pemakaian, & GitHub ini
├── run_siguru.bat           # Launcher cepat satu klik untuk Windows
└── .gitignore               # Konfigurasi pengabaian berkas sementara Git
```

---

## ⚠️ PENTING: Aturan Pemeliharaan Dokumentasi

Setiap kali terdapat **penambahan fitur baru, perbaikan bug, penyesuaian tata letak, atau perubahan skema database** pada aplikasi SIGURU ini, pengembang atau AI Assistant **WAJIB selalu memperbarui kedua berkas dokumentasi berikut**:

1. **`prd_prompt_siguru.md`** — Memperbarui spesifikasi kebutuhan produk, prompt AI, logika fungsi, dan matriks hak akses.
2. **`README.md`** — Memperbarui panduan instalasi, daftar akun, catatan perubahan, dan instruksi operasional sistem.

---

*Hak Cipta © 2026 **SIGURU — Sistem Informasi Guru** oleh **FYJ Channel**.*  
*Dukung pengembangan aplikasi ini dengan subscribe di: [https://www.youtube.com/@fyjchannel](https://www.youtube.com/@fyjchannel)*
