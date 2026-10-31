<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-shield-lock me-2"></i>Peran &amp; Izin</h1>
    <a href="<?= site_url('admin/roles/new') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah peran</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Peran</th><th>Keterangan</th><th class="text-center">Izin</th><th class="text-center">Pengguna</th><th>Cakupan</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr>
                    <td><strong><?= esc($r['label']) ?></strong> <code class="small"><?= esc($r['name']) ?></code>
                        <?php if ($r['is_system']): ?><span class="badge text-bg-light border">bawaan</span><?php endif; ?></td>
                    <td class="small text-body-secondary"><?= esc($r['description'] ?? '') ?></td>
                    <td class="text-center"><?= (int) $r['perm_count'] ?></td>
                    <td class="text-center"><?= (int) $r['user_count'] ?></td>
                    <td><?= $r['own_only'] ? '<span class="badge text-bg-info">Item sendiri</span>' : '<span class="badge text-bg-secondary">Semua item</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/roles/' . $r['id'] . '/edit') ?>"><i class="bi bi-pencil"></i></a>
                        <?php if (! $r['is_system']): ?>
                            <form action="<?= site_url('admin/roles/' . $r['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Hapus peran ini?')">
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
<p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-info-circle"></i> Cakupan <strong>Item sendiri</strong>: pengguna dengan peran ini hanya boleh mengubah nama, memindah, menghapus, dan membagikan berkas/folder yang ia unggah sendiri. Berkas orang lain tetap dapat dilihat, diunduh, dan disalin.</p>
<?= $this->endSection() ?>
