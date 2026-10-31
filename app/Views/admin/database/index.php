<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3"><i class="bi bi-database-gear me-2"></i>Pembaruan Database</h1>

<div class="alert alert-info small">
    Setelah Anda mengunggah versi aplikasi yang memiliki berkas migrasi baru (<code>app/Database/Migrations</code>), tombol di bawah menjalankan
    <strong>hanya migrasi yang belum pernah dijalankan</strong>. Data yang sudah ada <strong>tidak diganti atau dihapus</strong>.
    Tanpa SSH, tanpa menimpa database.
</div>

<?php if ($error): ?><div class="alert alert-danger">Tidak dapat membaca status: <?= esc($error) ?></div><?php endif; ?>
<?php if ($lastError): ?>
    <div class="alert alert-warning">
        <strong>Percobaan otomatis terakhir gagal:</strong>
        <pre class="mb-0 small" style="white-space:pre-wrap"><?= esc($lastError) ?></pre>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <?php if ($pending === []): ?>
            <p class="mb-0 text-success"><i class="bi bi-check-circle me-1"></i>Database sudah yang terbaru. Tidak ada migrasi tertunda.</p>
        <?php else: ?>
            <h2 class="h6"><i class="bi bi-hourglass-split me-1"></i><?= count($pending) ?> migrasi menunggu:</h2>
            <ul class="small"><?php foreach ($pending as $p): ?><li><code><?= esc($p) ?></code></li><?php endforeach; ?></ul>
            <form method="post" action="<?= site_url('admin/database/migrate') ?>" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Menjalankan…'">
                <?= csrf_field() ?>
                <?php if ($zipOk): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="backup_first" value="1" id="bf" checked>
                        <label class="form-check-label" for="bf">Buat backup pengaman dulu (disarankan; bisa lama bila data besar)</label>
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Jalankan migrasi terbaru</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header small fw-semibold">Riwayat migrasi yang sudah dijalankan</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Versi</th><th>Waktu</th></tr></thead>
            <tbody>
            <?php foreach ($applied as $a): ?><tr><td><code><?= esc($a['version']) ?></code></td><td><?= esc($a['time']) ?></td></tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
