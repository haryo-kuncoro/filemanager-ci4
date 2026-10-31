<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$labels = [
    'login' => 'Login', 'login_failed' => 'Login gagal', 'logout' => 'Logout', 'upload' => 'Unggah', 'mkdir' => 'Folder baru',
    'rename' => 'Ganti nama', 'move' => 'Pindah', 'copy' => 'Salin', 'delete' => 'Hapus', 'restore' => 'Pulihkan', 'purge' => 'Hapus permanen',
    'download' => 'Unduh', 'download_zip' => 'Unduh ZIP', 'share_create' => 'Buat tautan', 'share_update' => 'Ubah tautan',
    'share_delete' => 'Cabut tautan', 'share_view' => 'Tautan dilihat', 'share_download' => 'Tautan diunduh', 'share_disable' => 'Nonaktifkan tautan',
    'share_enable' => 'Aktifkan tautan', 'user_create' => 'Pengguna baru', 'user_update' => 'Ubah pengguna', 'user_delete' => 'Hapus pengguna',
    'role_create' => 'Peran baru', 'role_update' => 'Ubah peran', 'role_delete' => 'Hapus peran', 'password_change' => 'Ganti sandi',
];
$badge = static fn (string $a): string => match (true) {
    in_array($a, ['delete', 'purge', 'login_failed', 'user_delete', 'role_delete', 'share_delete'], true) => 'danger',
    in_array($a, ['upload', 'mkdir', 'restore', 'user_create', 'role_create', 'share_create'], true)       => 'success',
    str_starts_with($a, 'share')                                                                           => 'info',
    default                                                                                                => 'secondary',
};
?>
<h1 class="h4 mb-3"><i class="bi bi-journal-text me-2"></i>Log Aktivitas</h1>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-3">
        <select class="form-select form-select-sm" name="action" onchange="this.form.submit()">
            <option value="">Semua aksi</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= esc($a, 'attr') ?>" <?= $a === $action ? 'selected' : '' ?>><?= esc($labels[$a] ?? $a) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <div class="input-group input-group-sm">
            <input class="form-control" name="q" value="<?= esc($q) ?>" placeholder="Cari pengguna / nama item / IP">
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Item</th><th>Detail</th><th>IP</th></tr></thead>
            <tbody>
            <?php if ($logs === []): ?><tr><td colspan="6" class="text-center text-body-secondary py-4">Tidak ada data.</td></tr><?php endif; ?>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="small text-nowrap"><?= esc(fm_date($l['created_at'], 'd M Y H:i:s')) ?></td>
                    <td><?= $l['username'] !== null ? esc($l['username']) : '<span class="text-body-secondary">publik</span>' ?></td>
                    <td><span class="badge text-bg-<?= $badge($l['action']) ?>"><?= esc($labels[$l['action']] ?? $l['action']) ?></span></td>
                    <td class="small"><?= esc($l['target'] ?? '') ?></td>
                    <td class="small text-body-secondary"><code><?= esc($l['details'] ?? '') ?></code></td>
                    <td class="small"><?= esc($l['ip'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links('default', 'bootstrap_pager') ?></div>
<?= $this->endSection() ?>
