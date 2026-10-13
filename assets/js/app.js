// assets/js/app.js - Logika Interaktif SiGuru

document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-Hiding Navbar on Scroll
    let lastScrollTop = 0;
    const header = document.querySelector('.main-header');
    
    if (header) {
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            if (currentScroll > 70) {
                header.classList.add('scrolled');
                if (currentScroll > lastScrollTop && currentScroll > 120) {
                    // Scroll down -> hide
                    header.classList.add('navbar-hidden');
                } else {
                    // Scroll up -> show
                    header.classList.remove('navbar-hidden');
                }
            } else {
                header.classList.remove('scrolled');
                header.classList.remove('navbar-hidden');
            }
            lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
        }, { passive: true });
    }

    // 2. Tombol Titik 3 untuk Sembunyikan / Tampilkan Sidebar (Fluid Adjust)
    const btnToggle = document.getElementById('btnToggleSidebar');
    const sidebar = document.querySelector('.main-sidebar');
    const closeBtn = document.getElementById('sidebarCloseBtn');

    // Pulihkan status sidebar dari localStorage
    if (window.innerWidth >= 992 && localStorage.getItem('siguru_sidebar_collapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }

    if (btnToggle) {
        btnToggle.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.innerWidth < 992) {
                // Mobile: toggle show class
                if (sidebar) sidebar.classList.toggle('show');
            } else {
                // Desktop: toggle body collapsed class & simpan preferensi
                const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('siguru_sidebar_collapsed', isCollapsed ? 'true' : 'false');
            }
        });
    }

    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', () => {
            sidebar.classList.remove('show');
        });
    }

    // 3. Tombol Refresh Halaman
    const refreshBtn = document.getElementById('btnRefreshApp');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const icon = refreshBtn.querySelector('#iconRefresh') || refreshBtn.querySelector('i');
            if (icon) {
                icon.style.display = 'inline-block';
                icon.style.transition = 'transform 0.6s ease';
                icon.style.transform = 'rotate(360deg)';
            }
            setTimeout(() => {
                window.location.reload();
            }, 250);
        });
    }

    // 4. Tombol Hapus Cache Browser & Memori
    const clearCacheBtn = document.getElementById('btnClearCache');
    if (clearCacheBtn) {
        clearCacheBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const icon = clearCacheBtn.querySelector('i');
            if (icon) {
                icon.style.display = 'inline-block';
                icon.style.transition = 'transform 0.3s ease';
                icon.style.transform = 'scale(1.25)';
            }

            // Bersihkan semua penyimpanan klien (sessionStorage, localStorage, caches API)
            try {
                sessionStorage.clear();
                localStorage.removeItem('siguru_cache');
                localStorage.removeItem('siguru_settings_draft');
                if ('caches' in window) {
                    caches.keys().then((names) => {
                        names.forEach((name) => caches.delete(name));
                    });
                }
            } catch (err) {}

            // Tampilkan konfirmasi Toast SweetAlert2
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Cache Berhasil Dibersihkan!',
                    text: 'Memuat data segar langsung dari database...',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1100,
                    timerProgressBar: true
                });
            }

            // Hard reload dengan parameter pembersih cache
            setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('_nocache', Date.now());
                window.location.href = url.toString();
            }, 750);
        });
    }

    // 5. Tanggal & Waktu Real-Time Sesuai Perangkat Klien (Header)
    function updateHeaderClock() {
        const dateEl = document.getElementById('headerDeviceDate');
        const timeEl = document.getElementById('headerDeviceTime');
        if (!dateEl && !timeEl) return;

        const now = new Date();

        if (dateEl) {
            try {
                const dateStr = now.toLocaleDateString('id-ID', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
                if (dateEl.textContent !== dateStr) {
                    dateEl.textContent = dateStr;
                }
            } catch (e) {
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                dateEl.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
            }
        }

        if (timeEl) {
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            timeEl.textContent = `${hours}:${minutes}:${seconds}`;
        }
    }

    updateHeaderClock();
    setInterval(updateHeaderClock, 1000);
});

// 4. Modal Popup Foto Murid (Lightbox)
function showStudentPhoto(photoUrl, studentName) {
    let modalEl = document.getElementById('photoLightboxModal');
    if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = 'photoLightboxModal';
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h6 class="modal-title fw-bold" id="photoModalTitle">Foto Murid</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center p-4">
                        <img id="photoModalImg" src="" alt="Foto Murid" class="img-fluid rounded-4 shadow-sm" style="max-height: 420px; object-fit: contain;">
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    const img = modalEl.querySelector('#photoModalImg');
    const title = modalEl.querySelector('#photoModalTitle');
    img.src = photoUrl || 'assets/images/sample_student1.png';
    title.textContent = studentName || 'Foto Siswa';

    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();
}

// 5. Quick Edit Status Checkbox Siswa via AJAX
function toggleStudentStatus(checkbox, studentId, statusTag) {
    const isChecked = checkbox.checked;
    checkbox.disabled = true;

    fetch('api/quick_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            student_id: studentId,
            status_tag: statusTag,
            is_active: isChecked
        })
    })
    .then(r => r.json())
    .then(data => {
        checkbox.disabled = false;
        if (!data.success) {
            checkbox.checked = !isChecked;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menyimpan',
                    text: data.message || 'Gagal mengubah status di database.',
                    timer: 2500,
                    showConfirmButton: false
                });
            } else {
                alert(data.message || 'Gagal mengubah status');
            }
        } else {
            // Notifikasi toast sukses tersimpan di database
            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.onmouseenter = Swal.stopTimer;
                        toast.onmouseleave = Swal.resumeTimer;
                    }
                });
                Toast.fire({
                    icon: 'success',
                    title: data.message || `Status ${statusTag} berhasil disimpan ke database!`
                });
            }
        }
    })
    .catch(err => {
        checkbox.disabled = false;
        checkbox.checked = !isChecked;
        console.error(err);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Koneksi Terputus',
                text: 'Gagal menghubungi server untuk menyimpan data.',
                timer: 2500,
                showConfirmButton: false
            });
        }
    });
}

// 6. Tandai Semua Hadir di Form Presensi
function markAllAttendance(status) {
    document.querySelectorAll('.attendance-select').forEach(sel => {
        sel.value = status;
    });
    const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
    radios.forEach(r => {
        r.checked = true;
    });
}

// 7. Toggle Minimalist Switch Upload File vs Link Drive
function toggleProofInput(prefix, type) {
    const fileContainer = document.getElementById(`proof_file_container_${prefix}`);
    const linkContainer = document.getElementById(`proof_link_container_${prefix}`);
    const fileInput = fileContainer ? fileContainer.querySelector('input[type="file"]') : null;
    const linkInput = linkContainer ? linkContainer.querySelector('input[type="url"], input[type="text"]') : null;

    if (fileContainer && linkContainer) {
        if (type === 'file') {
            fileContainer.style.display = 'block';
            linkContainer.style.display = 'none';
            if (fileInput) fileInput.disabled = false;
            if (linkInput) linkInput.disabled = true;
        } else {
            fileContainer.style.display = 'none';
            linkContainer.style.display = 'block';
            if (fileInput) { fileInput.disabled = true; fileInput.value = ''; }
            if (linkInput) linkInput.disabled = false;
        }
    }
}

// 8. Sinkronisasi Data Bukti & Toggle pada Modal Edit Laporan
function setupEditProofModal(prefix, currentBukti) {
    const displayBox = document.getElementById(`current_proof_display_${prefix}`);
    const badgesBox = document.getElementById(`current_proof_badges_${prefix}`);
    const radioFile = document.getElementById(`edit_proof_mode_file_${prefix}`);
    const radioLink = document.getElementById(`edit_proof_mode_link_${prefix}`);
    const linkInput = document.getElementById(`edit_link_drive_${prefix}`);
    const fileContainer = document.getElementById(`proof_file_container_${prefix}`);
    const fileInput = fileContainer ? fileContainer.querySelector('input[type="file"]') : null;

    if (fileInput) fileInput.value = '';

    const cleanProof = (currentBukti || '').trim();
    if (!cleanProof) {
        if (displayBox) displayBox.style.display = 'none';
        if (badgesBox) badgesBox.innerHTML = '';
        if (radioFile) radioFile.checked = true;
        if (linkInput) linkInput.value = '';
        toggleProofInput(prefix, 'file');
        return;
    }

    const parts = cleanProof.split(/[\n,]+/).map(s => s.trim()).filter(Boolean);
    const driveLinks = parts.filter(p => p.startsWith('http://') || p.startsWith('https://') || p.includes('drive.google.com'));
    const localFiles = parts.filter(p => !p.startsWith('http://') && !p.startsWith('https://') && !p.includes('drive.google.com'));

    if (displayBox) displayBox.style.display = 'block';
    if (badgesBox) {
        let badgeHtml = '';
        parts.forEach((p, idx) => {
            const no = parts.length > 1 ? ` ${idx + 1}` : '';
            const isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(p);
            const isPdf = /\.pdf$/i.test(p);
            const isDrive = p.includes('drive.google.com') || p.startsWith('http');
            let icon = 'bi-link-45deg';
            if (isImg) icon = 'bi-image';
            else if (isPdf) icon = 'bi-file-earmark-pdf';
            else if (isDrive) icon = 'bi-google text-danger';

            badgeHtml += `<a href="${p}" target="_blank" class="badge bg-white text-dark border text-decoration-none shadow-xs px-2 py-1" title="${p}">
                <i class="bi ${icon} me-1"></i>Bukti${no}
            </a>`;
        });
        badgesBox.innerHTML = badgeHtml;
    }

    if (localFiles.length > 0) {
        if (radioFile) radioFile.checked = true;
        if (linkInput) linkInput.value = '';
        toggleProofInput(prefix, 'file');
    } else if (driveLinks.length > 0) {
        if (radioLink) radioLink.checked = true;
        if (linkInput) linkInput.value = driveLinks[0] || '';
        toggleProofInput(prefix, 'link');
    } else {
        if (radioFile) radioFile.checked = true;
        if (linkInput) linkInput.value = '';
        toggleProofInput(prefix, 'file');
    }
}

