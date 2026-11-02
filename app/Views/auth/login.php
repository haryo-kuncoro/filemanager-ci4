<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<?php
$cfg  = config('Filemanager');
$feat = [
    ['bi-shield-lock-fill', 'Tersimpan aman', 'Semua berkas disimpan di luar folder web dengan nama acak, tidak pernah bisa diakses langsung lewat URL.'],
    ['bi-people-fill', 'Akses sesuai peran', 'Hak akses diatur per peran: siapa boleh melihat, mengunggah, mengubah, menghapus, dan membagikan.'],
    ['bi-link-45deg', 'Bagikan lewat tautan', 'Kirim berkas atau folder ke siapa saja lewat tautan, lengkap dengan kata sandi dan masa berlaku.'],
    ['bi-eye-slash-fill', 'Pribadi atau bersama', 'Tandai folder dan berkas sebagai pribadi, atau kunci agar tidak berubah tanpa sengaja.'],
    ['bi-cloud-arrow-up-fill', 'Unggah berkas besar', 'Unggahan dipecah otomatis, nyaman untuk berkas besar dan koneksi yang kurang stabil.'],
    ['bi-arrow-counterclockwise', 'Sampah & pemulihan', 'Berkas yang terhapus bisa dikembalikan, dan seluruh data dapat dicadangkan oleh administrator.'],
];
?>
<div class="lp">
    <div class="container lp-inner">
        <div class="row align-items-center g-5 py-4">

            <!-- Kolom penjelasan (landing) -->
            <div class="col-lg-7 order-2 order-lg-1">
                <div class="lp-brand d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-hdd-network-fill"></i>
                    <span><?= esc($cfg->appName) ?></span>
                </div>
                <h1 class="lp-title">Semua berkas Anda, <span class="lp-accent">rapi dan aman</span> di satu tempat.</h1>
                <p class="lp-lead">
                    Kelola folder dan berkas lewat browser seperti di komputer sendiri, bagikan dengan tautan,
                    dan atur siapa yang boleh mengakses apa.
                </p>

                <div class="row g-3 mt-2">
                    <?php foreach ($feat as [$icon, $head, $text]) : ?>
                        <div class="col-sm-6">
                            <div class="lp-feat d-flex gap-3">
                                <span class="lp-ico"><i class="bi <?= esc($icon, 'attr') ?>"></i></span>
                                <div>
                                    <div class="fw-semibold"><?= esc($head) ?></div>
                                    <div class="small text-body-secondary"><?= esc($text) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="small text-body-secondary mt-4 mb-0 d-none d-lg-block">
                    <i class="bi bi-info-circle me-1"></i>
                    Punya tautan berbagi? Buka langsung tautannya, tidak perlu masuk.
                </p>
            </div>

            <!-- Kolom masuk -->
            <div class="col-lg-5 order-1 order-lg-2">
                <?php $logo = \App\Models\SettingModel::logoUrl(); ?>
                <?php if ($logo): ?>
                    <div class="text-center mb-3"><img src="<?= esc($logo, 'attr') ?>" alt="<?= esc($cfg->appName, 'attr') ?>" class="lp-logo"></div>
                <?php endif; ?>
                <div class="card shadow lp-card">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h4 mb-1">Masuk</h2>
                        <p class="text-body-secondary small mb-4">Gunakan akun yang diberikan administrator.</p>
                        <?= view('layouts/flash') ?>
                        <form action="<?= site_url('login') ?>" method="post" autocomplete="on">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label" for="username">Username</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= esc(old('username')) ?>" required autofocus autocomplete="username">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="password">Kata sandi</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                                </div>
                            </div>
                            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</button>
                        </form>
                        <hr class="my-4">
                        <p class="small text-body-secondary mb-0 text-center">
                            Lupa kata sandi? Hubungi administrator Anda.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <footer class="lp-foot text-center small text-body-secondary py-3">
        &copy; <?= date('Y') ?> <?= esc($cfg->appName) ?>
    </footer>
</div>
<?= $this->endSection() ?>
