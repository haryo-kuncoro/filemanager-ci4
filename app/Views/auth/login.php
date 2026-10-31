<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<?php $cfg = config('Filemanager'); ?>
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-11 col-sm-8 col-md-6 col-lg-4">
            <div class="text-center mb-4">
                <i class="bi bi-hdd-network-fill text-primary display-4"></i>
                <h1 class="h4 mt-2 mb-0"><?= esc($cfg->appName) ?></h1>
                <p class="text-body-secondary small">Masuk untuk mengelola berkas</p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <?= view('layouts/flash') ?>
                    <form action="<?= site_url('login') ?>" method="post" autocomplete="on">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="username">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?= esc(old('username')) ?>" required autofocus autocomplete="username">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Kata sandi</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                            </div>
                        </div>
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
