<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-11 col-sm-8 col-md-6 col-lg-4">
            <div class="text-center mb-3">
                <i class="bi bi-life-preserver display-4 text-danger"></i>
                <h1 class="h5 mt-2">Reset kata sandi darurat</h1>
                <p class="small text-body-secondary">Token ada di berkas <code>.env</code> (<code>fm.resetToken</code>).</p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <?= view('layouts/flash') ?>
                    <form method="post" action="<?= site_url('install/reset') ?>" autocomplete="off">
                        <?= csrf_field() ?>
                        <div class="mb-3"><label class="form-label" for="token">Token</label><input type="password" class="form-control" id="token" name="token" required></div>
                        <div class="mb-3"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" value="admin" required></div>
                        <div class="mb-3"><label class="form-label" for="password">Kata sandi baru</label><input type="password" class="form-control" id="password" name="password" minlength="<?= config('Filemanager')->minPasswordLength ?>" required autocomplete="new-password"></div>
                        <button class="btn btn-danger w-100" type="submit">Reset kata sandi</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
