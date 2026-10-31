<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-11 col-sm-8 col-md-6 col-lg-4">
            <div class="text-center mb-3">
                <i class="bi bi-shield-lock-fill display-4 text-warning"></i>
                <h1 class="h5 mt-2">Tautan dilindungi kata sandi</h1>
                <p class="text-body-secondary small mb-0"><i class="bi bi-<?= 'file-earmark' ?>"></i> <?= esc($name) ?></p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <?= view('layouts/flash') ?>
                    <form method="post" action="<?= site_url('s/' . $token . '/unlock') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="password">Kata sandi</label>
                            <input type="password" class="form-control" id="password" name="password" required autofocus autocomplete="off">
                        </div>
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-unlock me-1"></i>Buka</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
