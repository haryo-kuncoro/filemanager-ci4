<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3"><i class="bi bi-link-45deg me-2"></i>Tautan Dibagikan</h1>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Item</th><th>Dibuat oleh</th><th>Status</th><th>Kedaluwarsa</th><th class="text-center">Dilihat</th><th class="text-center">Diunduh</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php if ($shares === []): ?>
                <tr><td colspan="7" class="text-center text-body-secondary py-4">Belum ada tautan yang dibagikan.</td></tr>
            <?php endif; ?>
            <?php foreach ($shares as $s):
                $expired = ! empty($s['expires_at']) && strtotime($s['expires_at']) < time();
                $gone    = $s['node_name'] === null || $s['node_deleted_at'] !== null;
                $url     = fm_share_url($s['token']);
                ?>
                <tr>
                    <td>
                        <i class="bi <?= fm_icon((string) $s['node_type']) ?> me-1"></i>
                        <?= $s['node_name'] !== null ? esc($s['node_name']) : '<em>(dihapus)</em>' ?>
                        <?php if ($gone && $s['node_name'] !== null): ?><span class="badge text-bg-secondary">di sampah</span><?php endif; ?>
                        <?php if (! empty($s['password_hash'])): ?><i class="bi bi-lock-fill text-warning ms-1" title="Dilindungi kata sandi"></i><?php endif; ?>
                        <div class="small"><a href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener"><?= esc($url) ?></a></div>
                    </td>
                    <td class="small"><?= esc($s['creator_name'] ?? '-') ?><div class="text-body-secondary"><?= esc(fm_date($s['created_at'])) ?></div></td>
                    <td>
                        <?php if ($expired): ?><span class="badge text-bg-warning">Kedaluwarsa</span>
                        <?php elseif ($s['is_active']): ?><span class="badge text-bg-success">Aktif</span>
                        <?php else: ?><span class="badge text-bg-secondary">Nonaktif</span><?php endif; ?>
                        <?php if (! $s['allow_download']): ?><span class="badge text-bg-info">hanya lihat</span><?php endif; ?>
                    </td>
                    <td class="small"><?= $s['expires_at'] ? esc(fm_date($s['expires_at'])) : '—' ?></td>
                    <td class="text-center"><?= (int) $s['view_count'] ?></td>
                    <td class="text-center"><?= (int) $s['download_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <form action="<?= site_url('admin/shares/' . $s['id'] . '/toggle') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-secondary" type="submit" title="<?= $s['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"><i class="bi bi-<?= $s['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i></button>
                        </form>
                        <form action="<?= site_url('admin/shares/' . $s['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Cabut tautan ini secara permanen?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Cabut"><i class="bi bi-x-circle"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
