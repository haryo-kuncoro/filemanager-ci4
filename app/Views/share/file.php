<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<?php
/** @var array $node */
$base    = site_url('s/' . $token . '/file/' . $node['id']);
$preview = $base . '/preview';
?>
<div class="container py-4" style="max-width: 920px">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <i class="bi <?= fm_icon('file', (string) $node['ext'], (string) $node['mime']) ?> display-5 text-primary"></i>
                <div class="flex-grow-1 text-break">
                    <h1 class="h5 mb-1"><?= esc($node['name']) ?></h1>
                    <div class="small text-body-secondary"><?= fm_bytes($node['size']) ?> · <?= esc(strtoupper((string) $node['ext']) ?: 'FILE') ?> · <?= esc(fm_date($node['updated_at'])) ?></div>
                </div>
                <?php if ($allowDl): ?>
                    <a class="btn btn-primary" href="<?= $base ?>/download"><i class="bi bi-download me-1"></i>Unduh</a>
                <?php endif; ?>
            </div>

            <?php if (! $allowDl): ?>
                <div class="alert alert-info py-2 small"><i class="bi bi-eye me-1"></i> Pemilik tautan hanya mengizinkan melihat berkas ini.</div>
            <?php endif; ?>

            <?php if ($kind === 'image'): ?>
                <div class="text-center bg-body-secondary rounded p-2"><img src="<?= $preview ?>" alt="<?= esc($node['name'], 'attr') ?>" class="img-fluid" style="max-height:70vh"></div>
            <?php elseif ($kind === 'pdf'): ?>
                <iframe src="<?= $preview ?>" class="w-100 border rounded" style="height:75vh" title="Pratinjau PDF"></iframe>
            <?php elseif ($kind === 'video'): ?>
                <video src="<?= $preview ?>" class="w-100 rounded" style="max-height:70vh" controls preload="metadata"></video>
            <?php elseif ($kind === 'audio'): ?>
                <audio src="<?= $preview ?>" class="w-100" controls preload="metadata"></audio>
            <?php elseif ($kind === 'text'): ?>
                <pre id="textPreview" class="border rounded p-3 bg-body-tertiary small" style="max-height:65vh; overflow:auto; white-space:pre-wrap">Memuat…</pre>
            <?php else: ?>
                <div class="text-center text-body-secondary py-4"><i class="bi bi-file-earmark display-3"></i><p class="mt-2 mb-0">Pratinjau tidak tersedia untuk jenis berkas ini.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <p class="text-center small text-body-secondary mt-3">Dibagikan melalui <?= esc(config('Filemanager')->appName) ?></p>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($kind === 'text'): ?>
<script>
    // Teks dimuat sebagai teks biasa (textContent) — tidak pernah dirender sebagai HTML.
    fetch(<?= json_encode($base . '/text') ?>, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (d) { document.getElementById('textPreview').textContent = d.content + (d.truncated ? '\n\n… (dipotong, unduh untuk melihat selengkapnya)' : ''); })
        .catch(function () { document.getElementById('textPreview').textContent = 'Pratinjau tidak tersedia.'; });
</script>
<?php endif; ?>
<?= $this->endSection() ?>
