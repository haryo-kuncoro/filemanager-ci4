<?php
/** @var string|null $message */
$cfg = config('Filemanager');

// Tujuan tombol utama: berkas bila sudah login, selain itu halaman masuk.
$loggedIn = false;
try {
    $loggedIn = (bool) service('auth')->check();
} catch (\Throwable $e) {
    $loggedIn = false;
}
$home      = $loggedIn ? site_url('files') : site_url('login');
$homeLabel = $loggedIn ? 'Kembali ke Berkas' : 'Ke halaman masuk';
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Halaman tidak ditemukan · <?= esc($cfg->appName) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script>
        try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('fm-theme') || 'light'); } catch (e) {}
    </script>
</head>
<body class="bg-body-tertiary">
<main class="container d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="text-center nf-wrap">
        <div class="nf-code" aria-hidden="true">
            <span>4</span><i class="bi bi-folder-x"></i><span>4</span>
        </div>
        <h1 class="h3 mt-3">Halaman tidak ditemukan</h1>
        <p class="text-body-secondary mb-4">
            Alamat yang Anda tuju tidak ada, sudah dipindahkan, atau tautan berbagi sudah tidak berlaku.
        </p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="<?= esc($home, 'attr') ?>" class="btn btn-primary"><i class="bi bi-house-door me-1"></i> <?= esc($homeLabel) ?></a>
            <button type="button" class="btn btn-outline-secondary" onclick="history.length > 1 ? history.back() : location.assign('<?= esc($home, 'js') ?>')">
                <i class="bi bi-arrow-left me-1"></i> Halaman sebelumnya
            </button>
        </div>
        <?php if (ENVIRONMENT !== 'production' && ! empty($message)) : ?>
            <p class="small text-body-tertiary mt-4 mb-0"><code><?= esc((string) $message) ?></code></p>
        <?php endif; ?>
        <p class="small text-body-tertiary mt-4 mb-0"><?= esc($cfg->appName) ?></p>
    </div>
</main>
</body>
</html>
