<?php
$cfg = config('Filemanager');
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title><?= esc(($title ?? '') !== '' ? $title . ' · ' : '') . esc($cfg->appName) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <?= view('layouts/pwa_head') ?>
    <script>
        try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('fm-theme') || 'light'); } catch (e) {}
    </script>
</head>
<body class="bg-body-tertiary">
<?= $this->renderSection('content') ?>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<?= view('layouts/pwa_script') ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
