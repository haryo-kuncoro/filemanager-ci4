<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $s = $summary; ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/backups') ?>"><i class="bi bi-arrow-left"></i> Backup</a>
    <h1 class="h5 mb-0"><i class="bi bi-folder2-open me-1"></i>Jelajahi arsip</h1>
    <code><?= esc($name) ?></code>
</div>
<p class="small text-body-secondary mb-2">
    Dibuat <?= $s['created_at'] ? esc(date('d M Y H:i', strtotime($s['created_at']))) : '—' ?><?= $s['created_by'] ? ' oleh ' . esc($s['created_by']) : '' ?>
    · <?= (int) $s['nodes'] ?> item, <?= (int) $s['files'] ?> berkas (<?= esc(fm_bytes($s['bytes'])) ?>)
    <?php if ($s['missing']): ?>· <span class="text-warning"><?= (int) $s['missing'] ?> berkas fisik hilang saat backup dibuat</span><?php endif; ?>
</p>
<div class="alert alert-info py-2 small">
    <i class="bi bi-eye me-1"></i><strong>Mode baca.</strong> Isi arsip dibuka langsung dari berkas backup — data yang sedang berjalan <strong>tidak diubah atau ditimpa</strong>.
    Sebagai administrator Anda melihat <strong>seluruh</strong> item arsip, termasuk milik pengguna lain, yang pribadi, dan yang terkunci.
    Pilih item lalu <em>Ekstrak</em> untuk menyalinnya ke folder pribadi <code>Pemulihan Arsip</code> tanpa mengganggu berkas yang ada.
</div>

<div class="card">
    <div class="card-body border-bottom py-2 d-flex flex-wrap gap-2 align-items-center">
        <nav id="arCrumbs" class="small me-auto" aria-label="Lokasi"></nav>
        <div class="form-check form-switch mb-0 small">
            <input class="form-check-input" type="checkbox" id="arTrash"><label class="form-check-label" for="arTrash">Tampilkan yang sudah dihapus</label>
        </div>
        <div class="input-group input-group-sm" style="max-width:16rem">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="arSearch" placeholder="Cari di arsip…" autocomplete="off">
        </div>
        <button class="btn btn-sm btn-primary" id="arExtract" type="button" disabled><i class="bi bi-box-arrow-in-down me-1"></i>Ekstrak <span id="arSelCount"></span></button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="arTable">
            <thead class="table-light"><tr>
                <th style="width:2rem"><input class="form-check-input" type="checkbox" id="arAll" aria-label="Pilih semua"></th>
                <th>Nama</th><th class="d-none d-md-table-cell">Pemilik asli</th><th class="text-end">Ukuran</th><th class="d-none d-lg-table-cell">Diubah</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody id="arBody"><tr><td colspan="6" class="text-center text-body-secondary py-4">Memuat…</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalExtract" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-1"></i>Ekstrak dari arsip</h5><button type="button" class="btn-close" id="exClose" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div id="exPlan" class="small"></div>
            <div id="exProgWrap" hidden>
                <div class="progress mb-2" style="height:.7rem"><div class="progress-bar progress-bar-striped progress-bar-animated" id="exBar" style="width:0%"></div></div>
                <div class="small" id="exStatus"></div>
            </div>
            <div id="exSkipped" class="small text-warning mt-2" hidden></div>
            <div id="exErr" class="alert alert-danger small mt-2 mb-0" hidden></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" id="exCancel">Batal</button>
            <a class="btn btn-success" id="exOpen" href="#" hidden><i class="bi bi-folder2-open me-1"></i>Buka folder hasil</a>
            <button type="button" class="btn btn-primary" id="exGo" hidden>Mulai ekstrak</button>
        </div>
    </div></div>
</div>

<script id="ar-config" type="application/json"><?= json_encode([
    'base' => site_url('admin/archive/' . rawurlencode($name)),
    'files' => site_url('files'),
    'csrfHeader' => $csrfHeader, 'csrfHash' => csrf_hash(),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/archive.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/archive.js') ?>"></script>
<?= $this->endSection() ?>
