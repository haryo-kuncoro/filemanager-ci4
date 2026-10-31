<?php
/** @var string $title */
$cfg   = config('Filemanager');
$user  = auth_user();
$full  = $fullHeight ?? false;
$path  = trim(service('request')->getUri()->getPath(), '/');
$active = static fn (string $prefix): string => str_starts_with($path, $prefix) ? 'active' : '';
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="robots" content="noindex,nofollow">
    <title><?= esc(($title ?? '') !== '' ? $title . ' · ' : '') . esc($cfg->appName) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script>
        try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('fm-theme') || 'light'); } catch (e) {}
    </script>
</head>
<body class="<?= $full ? 'fm-fullpage' : '' ?>">

<nav class="navbar navbar-expand-md bg-body-tertiary border-bottom fm-navbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="<?= site_url('files') ?>">
            <i class="bi bi-hdd-network-fill text-primary"></i> <?= esc($cfg->appName) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php if (can('files.view')): ?>
                    <li class="nav-item"><a class="nav-link <?= $active('files') ?>" href="<?= site_url('files') ?>"><i class="bi bi-folder2-open"></i> Berkas</a></li>
                <?php endif; ?>
                <?php if (can('users.manage') || can('roles.manage') || can('shares.manage') || can('logs.view') || can('backup.manage') || is_admin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $active('admin') ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Administrasi</a>
                        <ul class="dropdown-menu">
                            <?php if (can('users.manage')): ?><li><a class="dropdown-item" href="<?= site_url('admin/users') ?>"><i class="bi bi-people me-2"></i>Pengguna</a></li><?php endif; ?>
                            <?php if (can('roles.manage')): ?><li><a class="dropdown-item" href="<?= site_url('admin/roles') ?>"><i class="bi bi-shield-lock me-2"></i>Peran &amp; Izin</a></li><?php endif; ?>
                            <?php if (can('shares.manage')): ?><li><a class="dropdown-item" href="<?= site_url('admin/shares') ?>"><i class="bi bi-link-45deg me-2"></i>Tautan Dibagikan</a></li><?php endif; ?>
                            <?php if (can('logs.view')): ?><li><a class="dropdown-item" href="<?= site_url('admin/logs') ?>"><i class="bi bi-journal-text me-2"></i>Log Aktivitas</a></li><?php endif; ?>
                            <?php if (can('backup.manage') || is_admin()): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                            <?php if (can('backup.manage')): ?><li><a class="dropdown-item" href="<?= site_url('admin/backups') ?>"><i class="bi bi-database-down me-2"></i>Backup &amp; Pemulihan</a></li><?php endif; ?>
                            <?php if (is_admin()): ?><li><a class="dropdown-item" href="<?= site_url('admin/database') ?>"><i class="bi bi-database-gear me-2"></i>Pembaruan Database</a></li><?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="themeToggle" type="button" title="Ganti tema"><i class="bi bi-moon-stars"></i></button>
                <?php if ($user): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= esc($user['name']) ?>
                            <span class="badge text-bg-secondary ms-1"><?= esc($user['role']['label']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= site_url('profile') ?>"><i class="bi bi-person me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="<?= site_url('profile/password') ?>"><i class="bi bi-key me-2"></i>Ganti kata sandi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="<?= site_url('logout') ?>" method="post" class="m-0">
                                    <?= csrf_field() ?>
                                    <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="<?= $full ? 'fm-fullmain' : 'container py-4' ?>">
    <?php if (! $full): ?>
        <?php if (is_admin() && \App\Libraries\Installer::schemaOutdated()): ?>
            <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>Ada pembaruan struktur database yang belum selesai. <a href="<?= site_url('admin/database') ?>">Buka Pembaruan Database</a>.</div>
        <?php endif; ?>
        <?= view('layouts/flash') ?>
    <?php endif; ?>
    <?= $this->renderSection('content') ?>
</main>

<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script>
    document.getElementById('themeToggle')?.addEventListener('click', function () {
        var root = document.documentElement;
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('fm-theme', next); } catch (e) {}
    });
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
