<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-11 col-md-6 col-lg-5 text-center">
            <i class="bi bi-link-45deg display-1 text-body-secondary"></i>
            <h1 class="h4 mt-2">Tautan tidak dapat dibuka</h1>
            <p class="text-body-secondary"><?= esc($message) ?></p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
