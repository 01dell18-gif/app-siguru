@echo off
setlocal enabledelayedexpansion
title SIGURU - Sistem Informasi Guru (Web Server)
color 0b

echo ========================================================
echo   SIGURU - Sistem Informasi Guru (Admin Web SQL)
echo ========================================================
echo.

:: 1. Pastikan direktori kerja berada di folder tempat script ini berada
cd /d "%~dp0"

:: 2. Deteksi otomatis XAMPP dan PHP pada PC / Perangkat
set "XAMPP_DIR="
set "PHP_CMD="
set "PHP_SOURCE="

:: A. Periksa PHP portabel di dalam folder proyek sendiri jika ada
if exist "%~dp0php\php.exe" (
    set "PHP_CMD=%~dp0php\php.exe"
    set "PHP_SOURCE=Folder Proyek Portabel (%~dp0php)"
)

:: B. Periksa instalasi XAMPP di berbagai drive dan folder relatif
if not defined PHP_CMD (
    for %%D in (
        "%~dp0..\.."
        "%~dp0.."
        "C:\xampp"
        "D:\xampp"
        "E:\xampp"
        "F:\xampp"
        "G:\xampp"
        "C:\xampp8"
        "D:\xampp8"
    ) do (
        if exist "%%~fD\php\php.exe" (
            if not defined XAMPP_DIR set "XAMPP_DIR=%%~fD"
            if not defined PHP_CMD (
                set "PHP_CMD=%%~fD\php\php.exe"
                set "PHP_SOURCE=XAMPP [%%~fD]"
            )
        )
    )
)

:: C. Periksa Laragon jika XAMPP tidak ditemukan
if not defined PHP_CMD (
    for /d %%D in ("C:\laragon\bin\php\php*" "D:\laragon\bin\php\php*" "E:\laragon\bin\php\php*") do (
        if exist "%%~fD\php.exe" (
            if not defined PHP_CMD (
                set "PHP_CMD=%%~fD\php.exe"
                set "PHP_SOURCE=Laragon [%%~fD]"
            )
        )
    )
)

:: D. Periksa instalasi PHP Standalone
if not defined PHP_CMD (
    for %%P in (
        "C:\php\php.exe"
        "D:\php\php.exe"
        "E:\php\php.exe"
        "F:\php\php.exe"
        "C:\Program Files\PHP\php.exe"
        "C:\Program Files (x86)\PHP\php.exe"
    ) do (
        if exist "%%~fP" if not defined PHP_CMD (
            set "PHP_CMD=%%~fP"
            set "PHP_SOURCE=PHP Standalone [%%~fP]"
        )
    )
)

:: E. Periksa PHP dari System PATH
if not defined PHP_CMD (
    where php.exe >nul 2>nul
    if !errorlevel! equ 0 (
        for /f "delims=" %%I in ('where php.exe 2^>nul') do (
            if not defined PHP_CMD (
                set "PHP_CMD=%%I"
                set "PHP_SOURCE=System PATH [%%I]"
            )
        )
    )
)

:: 3. Jika XAMPP maupun PHP TIDAK DITEMUKAN di komputer ini
if not defined PHP_CMD goto :no_php_found
goto :php_is_ready

:no_php_found
color 0c
echo ========================================================
echo   [PERINGATAN] XAMPP ATAU PHP BELUM TERPASANG DI KOMPUTER
echo ========================================================
echo.
echo Aplikasi web SIGURU membutuhkan PHP agar server web
echo dan database dapat berjalan di komputer ini.
echo.
echo PILIHAN APLIKASI:
echo 1. XAMPP - Sangat Direkomendasikan [Paling Praktis ^& Lengkap]
echo    Menyertakan Apache, PHP, dan MySQL siap pakai tanpa setup.
echo    Situs Web: https://www.apachefriends.org/download.html
echo.
echo 2. PHP for Windows Standalone
echo    Situs Web: https://windows.php.net/download/
echo.
echo ========================================================
echo Ingin membuka halaman unduh XAMPP di browser sekarang?
choice /c YT /n /m "Tekan [Y] untuk Buka Link Download XAMPP, atau [T] untuk Keluar: "
if !errorlevel! equ 1 (
    echo.
    echo [INFO] Membuka halaman unduh resmi XAMPP di browser Anda...
    start https://www.apachefriends.org/download.html
    echo.
    echo Silakan pasang XAMPP, lalu jalankan kembali run_siguru.bat ini.
) else (
    echo.
    echo Silakan pasang XAMPP atau PHP terlebih dahulu pada komputer ini.
)
echo.
pause
exit /b 1

:php_is_ready
:: 4. Jika aplikasi ditemukan: Tampilkan Status & Langsung Aktifkan
echo [STATUS SISTEM]
if defined XAMPP_DIR (
    echo   [OK] XAMPP Terdeteksi   : !XAMPP_DIR!
)
echo   [OK] Mesin PHP Aktif    : "!PHP_CMD!"
echo   [OK] Sumber Deteksi     : !PHP_SOURCE!
echo.

:: 5. Buat folder-folder esensial jika belum ada
if not exist "database" mkdir "database"
if not exist "uploads" mkdir "uploads"
if not exist "uploads\photos" mkdir "uploads\photos"
if not exist "uploads\documents" mkdir "uploads\documents"
if not exist "uploads\bukti_jurnal" mkdir "uploads\bukti_jurnal"
if not exist "uploads\bukti_ekstra" mkdir "uploads\bukti_ekstra"
if not exist "uploads\foto_siswa" mkdir "uploads\foto_siswa"
if not exist "uploads\guru_wali" mkdir "uploads\guru_wali"
if not exist "uploads\wali" mkdir "uploads\wali"

:: 6. Inisialisasi database jika file database belum ada
if not exist "database\siguru.db" (
    echo [INFO] Database SQLite belum ada. Membuat database awal...
    "!PHP_CMD!" "database\init_db.php"
) else (
    echo   [OK] Database SQL       : siguru.db [Siap Digunakan]
)

:: 7. Deteksi port bebas dan IP lokal otomatis (via check_env.php)
set PORT=8080
set LOCAL_IP=127.0.0.1
if exist "database\check_env.php" (
    "!PHP_CMD!" "database\check_env.php" > "%TEMP%\siguru_env.tmp"
    if exist "%TEMP%\siguru_env.tmp" (
        set /p ENV_RESULT=<"%TEMP%\siguru_env.tmp"
        del "%TEMP%\siguru_env.tmp" 2>nul
        for /f "tokens=1,2 delims=|" %%a in ("!ENV_RESULT!") do (
            if not "%%a"=="" set PORT=%%a
            if not "%%b"=="" set LOCAL_IP=%%b
        )
    )
)

echo.
echo ========================================================
echo   SERVER BERHASIL DIAKTIFKAN! APLIKASI SIAP DIGUNAKAN
echo ========================================================
echo   [AKSES KOMPUTER INI]  : http://localhost:!PORT!/
echo   [AKSES PERANGKAT LAIN] : http://!LOCAL_IP!:!PORT!/
echo ========================================================
echo   * Perangkat lain (HP/Laptop) wajib berada di Wi-Fi/LAN yang sama.
echo   * Jika perangkat lain tidak bisa mengakses:
echo     - Pastikan jaringan Wi-Fi diatur ke 'Private network'.
echo     - Izinkan PHP pada pop-up Windows Firewall jika muncul.
echo   * Browser akan terbuka otomatis menuju Halaman Utama (SIGURU).
echo   * Tekan [Ctrl + C] di jendela ini untuk mematikan server.
echo ========================================================
echo.

:: 8. Buka browser otomatis dengan jeda 2 detik (mengarah langsung ke Halaman Utama)
start "" cmd /c "ping -n 3 127.0.0.1 >nul & start http://localhost:!PORT!/"

:: 9. Jalankan Web Server PHP (bind ke 0.0.0.0 agar dapat diakses dari PC/HP lain di LAN)
"!PHP_CMD!" -S 0.0.0.0:!PORT!

echo.
echo ========================================================
echo   SERVER WEB TELAH BERHENTI ATAU DITUTUP.
echo ========================================================
pause
