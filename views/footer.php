<?php
// views/footer.php - Footer dan Penutup HTML SiGuru
?>
    <!-- Lightbox Modal untuk Foto Murid -->
    <div class="modal fade" id="photoLightboxModal" tabindex="-1">
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
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- App Logic JS -->
    <script src="assets/js/app.js"></script>

    <?php if (isset($flash) && $flash): ?>
    <script>
        Swal.fire({
            icon: '<?= $flash['type'] === 'danger' ? 'error' : ($flash['type'] === 'success' ? 'success' : 'info') ?>',
            title: '<?= addslashes($flash['message']) ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });
    </script>
    <?php endif; ?>
</body>
</html>
