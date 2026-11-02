<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $min = config('Filemanager')->minPasswordLength; ?>
<div class="row justify-content-center">
    <div class="col-lg-5">
        <h1 class="h4 mb-3"><i class="bi bi-key me-2"></i>Ganti Kata Sandi</h1>
        <?php if ($forced): ?>
            <div class="alert alert-warning"><i class="bi bi-shield-exclamation me-1"></i> Demi keamanan, Anda wajib mengganti kata sandi sebelum melanjutkan.</div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= site_url('profile/password') ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Kata sandi saat ini</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">Kata sandi baru</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="<?= $min ?>" required autocomplete="new-password">
                        <div class="form-text">Minimal <?= $min ?> karakter, memuat huruf dan angka/simbol, tidak mengandung username.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Ulangi kata sandi baru</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
