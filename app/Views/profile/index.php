<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <h1 class="h4 mb-3"><i class="bi bi-person-circle me-2"></i>Profil Saya</h1>
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= site_url('profile') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input class="form-control" value="<?= esc($user['username']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peran</label>
                        <input class="form-control" value="<?= esc($user['role']['label']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="name">Nama lengkap</label>
                        <input class="form-control" id="name" name="name" value="<?= esc(old('name', $user['name'])) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= esc(old('email', $user['email'] ?? '')) ?>">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                        <a class="btn btn-outline-secondary" href="<?= site_url('profile/password') ?>"><i class="bi bi-key me-1"></i>Ganti kata sandi</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">Izin peran Anda</div>
            <div class="card-body">
                <?php foreach ($user['permissions'] as $p): ?><span class="badge text-bg-light border me-1 mb-1"><?= esc($p) ?></span><?php endforeach; ?>
                <?php if ((int) $user['role']['own_only'] === 1): ?>
                    <p class="small text-body-secondary mt-2 mb-0"><i class="bi bi-info-circle"></i> Peran ini hanya dapat mengubah, memindah, menghapus, dan membagikan item <strong>milik sendiri</strong>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
