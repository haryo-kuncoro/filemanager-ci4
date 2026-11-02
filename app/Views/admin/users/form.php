<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$edit = $user !== null;
$min  = config('Filemanager')->minPasswordLength;
$val  = static fn (string $k, $d = '') => old($k, $edit ? ($user[$k] ?? $d) : $d);
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <h1 class="h4 mb-3"><i class="bi bi-person-gear me-2"></i><?= esc($title) ?></h1>
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= $edit ? site_url('admin/users/' . $user['id']) : site_url('admin/users') ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="name">Nama lengkap</label>
                        <input class="form-control" id="name" name="name" value="<?= esc($val('name')) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="username">Username</label>
                            <input class="form-control" id="username" name="username" value="<?= esc($val('username')) ?>" required pattern="[A-Za-z0-9_\-]{3,50}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="email">Email (opsional)</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= esc($val('email')) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role_id">Peran</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= (int) $val('role_id', 0) === (int) $r['id'] ? 'selected' : '' ?>><?= esc($r['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password"><?= $edit ? 'Kata sandi baru (kosongkan bila tidak diganti)' : 'Kata sandi' ?></label>
                        <input type="password" class="form-control" id="password" name="password" minlength="<?= $min ?>" <?= $edit ? '' : 'required' ?> autocomplete="new-password">
                        <div class="form-text">Minimal <?= $min ?> karakter, memuat huruf dan angka/simbol, tidak mengandung username.</div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $val('is_active', 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Akun aktif</label>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="must_change_password" name="must_change_password" value="1" <?= (int) $val('must_change_password', $edit ? 0 : 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="must_change_password">Wajib ganti kata sandi saat login berikutnya</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                        <a class="btn btn-outline-secondary" href="<?= site_url('admin/users') ?>">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
