<?php
/** @var string $message */
/** @var int $code */
$cfg = config('Filemanager');
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= (int) $code ?> · <?= esc($cfg->appName) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center">
        <div class="display-1 fw-bold text-body-secondary"><?= (int) $code ?></div>
        <p class="lead"><?= esc($message) ?></p>
        <a href="<?= site_url('/') ?>" class="btn btn-primary"><i class="bi bi-house me-1"></i> Kembali ke beranda</a>
    </div>
</div>
</body>
</html>
