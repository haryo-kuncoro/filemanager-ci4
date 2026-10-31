<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-people me-2"></i>Pengguna</h1>
    <a href="<?= site_url('admin/users/new') ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Tambah pengguna</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= esc($u['name']) ?><div class="small text-body-secondary"><?= esc($u['email'] ?? '') ?></div></td>
                    <td><code><?= esc($u['username']) ?></code></td>
                    <td><span class="badge text-bg-secondary"><?= esc($u['role_label']) ?></span></td>
                    <td>
                        <?php if ($u['is_active']): ?><span class="badge text-bg-success">Aktif</span><?php else: ?><span class="badge text-bg-danger">Nonaktif</span><?php endif; ?>
                        <?php if ($u['must_change_password']): ?><span class="badge text-bg-warning" title="Wajib ganti kata sandi">Reset</span><?php endif; ?>
                    </td>
                    <td class="small"><?= $u['last_login_at'] ? esc(fm_date($u['last_login_at'])) . '<div class="text-body-secondary">' . esc($u['last_login_ip']) . '</div>' : '-' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/users/' . $u['id'] . '/edit') ?>"><i class="bi bi-pencil"></i></a>
                        <?php if ((int) $u['id'] !== (int) auth_user()['id']): ?>
                            <form action="<?= site_url('admin/users/' . $u['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Hapus pengguna <?= esc($u['username'], 'js') ?>? Berkas miliknya akan dialihkan ke Anda.')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
