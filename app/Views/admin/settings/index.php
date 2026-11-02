<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3"><i class="bi bi-sliders me-2"></i>Pengaturan</h1>
<?= view('layouts/flash') ?>

<?php if (! $ready): ?>
    <div class="alert alert-warning">Struktur database belum diperbarui. Buka <strong>Administrasi → Pembaruan Database</strong> lalu jalankan migrasi.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h2 class="h6"><i class="bi bi-image me-1"></i>Logo aplikasi</h2>
            <p class="small text-body-secondary">Ditampilkan di atas panel login. Jika kosong, tidak ada logo yang tampil.</p>

            <div class="border rounded p-3 mb-3 text-center bg-body-tertiary">
                <?php if ($logoUrl): ?>
                    <img src="<?= esc($logoUrl, 'attr') ?>" alt="Logo saat ini" style="max-height:96px;max-width:100%">
                <?php else: ?>
                    <span class="text-body-secondary small"><i class="bi bi-image me-1"></i>Belum ada logo</span>
                <?php endif; ?>
            </div>

            <form method="post" action="<?= site_url('admin/settings/logo') ?>" enctype="multipart/form-data" class="mb-2">
                <?= csrf_field() ?>
                <label class="form-label" for="logo">Unggah logo baru</label>
                <div class="input-group">
                    <input class="form-control" type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp" required>
                    <button class="btn btn-primary" <?= $ready ? '' : 'disabled' ?>><i class="bi bi-upload me-1"></i>Simpan</button>
                </div>
                <div class="form-text">PNG, JPG, GIF, atau WebP, maksimal <?= (int) $maxKb ?> KB. Disarankan PNG berlatar transparan. SVG tidak didukung demi keamanan. Gambar otomatis diperkecil (maks. 600 px) bila server mendukung GD.</div>
            </form>

            <?php if ($logoUrl): ?>
                <form method="post" action="<?= site_url('admin/settings/logo/delete') ?>" onsubmit="return confirm('Hapus logo?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus logo</button>
                </form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
