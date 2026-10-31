<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3"><i class="bi bi-database-down me-2"></i>Backup &amp; Pemulihan</h1>

<?php if (! $zipOk): ?>
    <div class="alert alert-danger">Ekstensi PHP <code>zip</code> tidak aktif di server ini, sehingga backup/pemulihan tidak tersedia. Aktifkan lewat panel hosting (Select PHP Version → Extensions).</div>
<?php endif; ?>

<div class="alert alert-info small">
    Backup berisi <strong>seluruh database</strong> (pengguna beserta hash kata sandi, peran, struktur folder, tautan, log) dan <strong>seluruh isi berkas</strong>.
    Simpan di tempat aman dan <strong>unduh salinannya ke komputer Anda</strong> — folder backup berada di server yang sama dengan data.
    Untuk install ulang: pasang aplikasi, login, lalu unggah backup di bawah dan pulihkan.
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-plus-circle me-1"></i>Buat backup baru</h2>
            <p class="small text-body-secondary">Proses dapat memakan waktu bila datanya besar. Jangan tutup halaman sampai selesai.</p>
            <form method="post" action="<?= site_url('admin/backups') ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Membuat backup…'">
                <?= csrf_field() ?>
                <button class="btn btn-primary" <?= $zipOk ? '' : 'disabled' ?>><i class="bi bi-database-down me-1"></i>Buat backup sekarang</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-cloud-arrow-up me-1"></i>Unggah backup dari komputer</h2>
            <p class="small text-body-secondary mb-2">Berkas .zip hasil backup. Diunggah per potongan (<?= esc(fm_bytes($chunkSize)) ?>), jadi aman untuk berkas besar. Atau unggah lewat FTP ke <code>writable/filemanager/backups/</code>.</p>
            <div class="input-group input-group-sm">
                <input type="file" class="form-control" id="bkFile" accept=".zip" <?= $zipOk ? '' : 'disabled' ?>>
                <button class="btn btn-outline-primary" type="button" id="bkUpload" <?= $zipOk ? '' : 'disabled' ?>>Unggah</button>
            </div>
            <div class="progress mt-2" style="height:6px" hidden id="bkProg"><div class="progress-bar" style="width:0%"></div></div>
            <div class="small mt-1" id="bkMsg"></div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Berkas</th><th>Dibuat</th><th>Isi</th><th class="text-end">Ukuran</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php if ($backups === []): ?>
                <tr><td colspan="5" class="text-center text-body-secondary py-4">Belum ada backup.</td></tr>
            <?php endif; ?>
            <?php foreach ($backups as $b): $m = $b['manifest']; ?>
                <tr>
                    <td><i class="bi bi-file-earmark-zip me-1"></i><code><?= esc($b['name']) ?></code>
                        <?php if ($m === null): ?><span class="badge text-bg-danger">tidak valid</span><?php endif; ?></td>
                    <td class="small"><?= esc(date('d M Y H:i', $b['mtime'])) ?><?php if ($m && ! empty($m['created_by'])): ?><div class="text-body-secondary">oleh <?= esc($m['created_by']) ?></div><?php endif; ?></td>
                    <td class="small">
                        <?php if ($m): ?>
                            <?= (int) ($m['tables']['users'] ?? 0) ?> pengguna, <?= (int) ($m['tables']['nodes'] ?? 0) ?> item, <?= (int) $m['files'] ?> berkas (<?= esc(fm_bytes($m['files_bytes'])) ?>)
                            <?php if (! empty($m['files_missing'])): ?><div class="text-warning"><?= (int) $m['files_missing'] ?> berkas fisik hilang saat backup</div><?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-end"><?= esc(fm_bytes($b['size'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/backups/download/' . rawurlencode($b['name'])) ?>" title="Unduh"><i class="bi bi-download"></i></a>
                        <?php if ($m): ?><button class="btn btn-sm btn-outline-warning" type="button" data-restore="<?= esc($b['name'], 'attr') ?>" title="Pulihkan"><i class="bi bi-arrow-counterclockwise"></i> Pulihkan</button><?php endif; ?>
                        <form class="d-inline" method="post" action="<?= site_url('admin/backups/delete') ?>" onsubmit="return confirm('Hapus backup ini?')">
                            <?= csrf_field() ?><input type="hidden" name="name" value="<?= esc($b['name'], 'attr') ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalRestore" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('admin/backups/restore') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="name" id="rsName">
        <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Pulihkan data</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p>Semua data saat ini (<strong>pengguna, peran, berkas, folder, tautan, log</strong>) akan <strong>diganti</strong> dengan isi <code id="rsLabel"></code>. Sebuah backup pengaman dibuat otomatis lebih dulu. Setelah selesai Anda akan keluar dan harus login dengan akun dari backup.</p>
            <div class="mb-2"><label class="form-label">Kata sandi Anda</label><input type="password" class="form-control" name="password" required autocomplete="current-password"></div>
            <div><label class="form-label">Ketik <code>PULIHKAN</code> untuk konfirmasi</label><input type="text" class="form-control" name="confirm" required autocomplete="off"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger">Pulihkan sekarang</button></div>
    </form></div>
</div>

<script id="bk-config" type="application/json"><?= json_encode(['chunk' => $chunkSize, 'max' => $maxUpload, 'csrfName' => $csrfName, 'csrfHeader' => $csrfHeader, 'csrfHash' => csrf_hash(), 'url' => site_url('admin/backups/upload')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/backups.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/backups.js') ?>"></script>
<?= $this->endSection() ?>
