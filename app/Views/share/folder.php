<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<?php
/** @var array $node  folder yang dibagikan */
/** @var array $current folder yang sedang dibuka */
$root = site_url('s/' . $token);
?>
<div class="container py-4" style="max-width: 1000px">
    <div class="card shadow-sm">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-folder-fill text-warning fs-5"></i>
            <nav aria-label="breadcrumb" class="flex-grow-1">
                <ol class="breadcrumb mb-0">
                    <?php foreach ($crumbs as $i => $c): $last = $i === count($crumbs) - 1; ?>
                        <?php if ($last): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= esc($c['name']) ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?= $root . ((int) $c['id'] === (int) $node['id'] ? '' : '?f=' . (int) $c['id']) ?>"><?= esc($c['name']) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php if ($allowDl): ?>
                <a class="btn btn-sm btn-primary" href="<?= $root ?>/zip<?= (int) $current['id'] !== (int) $node['id'] ? '?f=' . (int) $current['id'] : '' ?>"><i class="bi bi-file-earmark-zip me-1"></i>Unduh folder (ZIP)</a>
            <?php endif; ?>
        </div>

        <?php if (! $allowDl): ?>
            <div class="alert alert-info rounded-0 border-0 border-bottom py-2 small mb-0"><i class="bi bi-eye me-1"></i> Pemilik tautan hanya mengizinkan melihat isi folder ini.</div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Nama</th><th class="text-end d-none d-sm-table-cell">Ukuran</th><th class="d-none d-md-table-cell">Diubah</th><th></th></tr></thead>
                <tbody>
                <?php if ($children === []): ?>
                    <tr><td colspan="4" class="text-center text-body-secondary py-4">Folder ini kosong.</td></tr>
                <?php endif; ?>
                <?php foreach ($children as $ch): $isDir = $ch['type'] === 'folder'; ?>
                    <tr>
                        <td class="text-break">
                            <?php if ($isDir): ?>
                                <a class="text-decoration-none" href="<?= $root ?>?f=<?= (int) $ch['id'] ?>"><i class="bi bi-folder-fill text-warning me-2"></i><?= esc($ch['name']) ?></a>
                            <?php else: ?>
                                <a class="text-decoration-none" href="<?= $root ?>/file/<?= (int) $ch['id'] ?>/preview" target="_blank" rel="noopener"><i class="bi <?= fm_icon('file', (string) $ch['ext'], (string) $ch['mime']) ?> me-2 text-secondary"></i><?= esc($ch['name']) ?></a>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small d-none d-sm-table-cell"><?= $isDir ? '—' : fm_bytes($ch['size']) ?></td>
                        <td class="small d-none d-md-table-cell"><?= esc(fm_date($ch['updated_at'])) ?></td>
                        <td class="text-end">
                            <?php if (! $isDir && $allowDl): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= $root ?>/file/<?= (int) $ch['id'] ?>/download" title="Unduh"><i class="bi bi-download"></i></a>
                            <?php elseif ($isDir && $allowDl): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= $root ?>/zip?f=<?= (int) $ch['id'] ?>" title="Unduh ZIP"><i class="bi bi-file-earmark-zip"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-center small text-body-secondary mt-3">Dibagikan melalui <?= esc(config('Filemanager')->appName) ?></p>
</div>
<?= $this->endSection() ?>
