<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<div class="container py-5" style="max-width: 760px">
    <?php if ($state === 'locked'): ?>
        <div class="text-center">
            <i class="bi bi-shield-lock-fill display-3 text-success"></i>
            <h1 class="h4 mt-2">Aplikasi sudah terpasang</h1>
            <p class="text-body-secondary">Halaman instalasi dikunci demi keamanan. Untuk memasang ulang, hapus berkas <code>writable/installed.lock</code> <strong>dan</strong> kosongkan database.</p>
            <a class="btn btn-primary" href="<?= site_url('login') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Ke halaman login</a>
        </div>
    <?php else: ?>
        <div class="text-center mb-4">
            <i class="bi bi-check-circle-fill display-3 text-success"></i>
            <h1 class="h3 mt-2">Instalasi selesai</h1>
        </div>

        <?php if ($env_written): ?>
            <div class="alert alert-success"><i class="bi bi-file-earmark-check me-1"></i> Berkas <code>.env</code> berhasil ditulis.</div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i> Berkas <code>.env</code> tidak dapat ditulis otomatis. Buat berkas bernama <code>.env</code> di folder utama aplikasi dengan isi berikut:
            </div>
            <pre class="border rounded p-3 bg-body-tertiary small user-select-all" style="white-space:pre-wrap"><?= esc($env) ?></pre>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header fw-semibold">Langkah berikutnya</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">Login dengan akun administrator yang baru dibuat.</li>
                <li class="list-group-item">Buat peran &amp; pengguna di menu <em>Administrasi</em>.</li>
                <li class="list-group-item">Halaman <code>/install</code> kini terkunci otomatis. Anda tidak perlu menghapus apa pun.</li>
            </ul>
        </div>
        <div class="text-center"><a class="btn btn-primary btn-lg" href="<?= esc($login_url, 'attr') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk</a></div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
