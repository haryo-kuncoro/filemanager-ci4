<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<?php /** @var array $reqs */ /** @var bool $met */ /** @var array $values */ /** @var array $upload */ ?>
<div class="container py-4" style="max-width: 860px">
    <div class="text-center mb-4">
        <i class="bi bi-hdd-network-fill text-primary display-4"></i>
        <h1 class="h3 mt-2 mb-1">Pemasangan <?= esc(config('Filemanager')->appName) ?></h1>
        <p class="text-body-secondary mb-0">Wizard ini menyiapkan database, peran &amp; izin, dan akun administrator — tanpa perlu SSH atau Composer.</p>
    </div>

    <?= view('layouts/flash') ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-clipboard-check me-1"></i> 1. Persyaratan server</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($reqs as $r): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <?= esc($r['label']) ?>
                        <?php if ($r['info'] !== ''): ?><div class="small text-body-secondary text-break"><?= esc($r['info']) ?></div><?php endif; ?>
                    </div>
                    <?php if ($r['ok']): ?>
                        <span class="badge text-bg-success"><i class="bi bi-check-lg"></i> OK</span>
                    <?php elseif ($r['required']): ?>
                        <span class="badge text-bg-danger"><i class="bi bi-x-lg"></i> Wajib</span>
                    <?php else: ?>
                        <span class="badge text-bg-warning"><i class="bi bi-exclamation-lg"></i> Opsional</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="card-footer small text-body-secondary">
            Batas upload PHP: <code>upload_max_filesize=<?= esc($upload['upload_max_filesize']) ?></code>,
            <code>post_max_size=<?= esc($upload['post_max_size']) ?></code>.
            Berkas diunggah per potongan <strong><?= esc(fm_bytes($upload['chunk'])) ?></strong>, sehingga ukuran berkas
            <em>tidak</em> dibatasi oleh angka tersebut.
        </div>
    </div>

    <?php if (! $met): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-1"></i> Perbaiki persyaratan bertanda <strong>Wajib</strong> (biasanya lewat menu <em>Select PHP Version / PHP Extensions</em> di cPanel), lalu muat ulang halaman ini.</div>
    <?php else: ?>
        <form method="post" action="<?= site_url('install') ?>" autocomplete="off">
            <?= csrf_field() ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold"><i class="bi bi-globe me-1"></i> 2. Alamat situs</div>
                <div class="card-body">
                    <label class="form-label" for="app_url">URL aplikasi</label>
                    <input class="form-control" id="app_url" name="app_url" value="<?= esc($values['app_url']) ?>" required>
                    <div class="form-text">Alamat yang dipakai pengunjung, mis. <code>https://domainanda.com/</code> atau <code>https://domainanda.com/berkas/</code>. Gunakan <code>https://</code> bila SSL aktif — cookie sesi akan ditandai aman.</div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold"><i class="bi bi-database me-1"></i> 3. Database MySQL / MariaDB</div>
                <div class="card-body">
                    <p class="small text-body-secondary">Buat database &amp; pengguna database terlebih dahulu di cPanel (<em>MySQL® Databases</em>) dan beri hak akses penuh.</p>
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label" for="db_host">Host</label><input class="form-control" id="db_host" name="db_host" value="<?= esc($values['db_host']) ?>" required></div>
                        <div class="col-md-4"><label class="form-label" for="db_port">Port</label><input class="form-control" id="db_port" name="db_port" value="<?= esc($values['db_port']) ?>" required></div>
                        <div class="col-md-4"><label class="form-label" for="db_name">Nama database</label><input class="form-control" id="db_name" name="db_name" value="<?= esc($values['db_name']) ?>" required></div>
                        <div class="col-md-4"><label class="form-label" for="db_user">Pengguna</label><input class="form-control" id="db_user" name="db_user" value="<?= esc($values['db_user']) ?>" required autocomplete="off"></div>
                        <div class="col-md-4"><label class="form-label" for="db_pass">Kata sandi</label><input type="password" class="form-control" id="db_pass" name="db_pass" autocomplete="new-password"></div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold"><i class="bi bi-person-gear me-1"></i> 4. Akun administrator</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="admin_name">Nama lengkap</label><input class="form-control" id="admin_name" name="admin_name" value="<?= esc($values['admin_name']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="admin_username">Username</label><input class="form-control" id="admin_username" name="admin_username" value="<?= esc($values['admin_username']) ?>" required pattern="[A-Za-z0-9_\-]{3,50}"></div>
                        <div class="col-12"><label class="form-label" for="admin_email">Email (opsional)</label><input type="email" class="form-control" id="admin_email" name="admin_email" value="<?= esc($values['admin_email']) ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="admin_password">Kata sandi</label><input type="password" class="form-control" id="admin_password" name="admin_password" minlength="<?= config('Filemanager')->minPasswordLength ?>" required autocomplete="new-password"></div>
                        <div class="col-md-6"><label class="form-label" for="admin_confirm">Ulangi kata sandi</label><input type="password" class="form-control" id="admin_confirm" name="admin_confirm" required autocomplete="new-password"></div>
                    </div>
                </div>
            </div>

            <div class="d-grid"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-rocket-takeoff me-1"></i> Pasang sekarang</button></div>
        </form>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
