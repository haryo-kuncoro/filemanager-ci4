<?php
/** @var array $client */
$canUpload = can('files.upload');
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<script id="fm-config" type="application/json"><?= json_encode($client, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<div class="fm" id="fm">

    <!-- ===================== Toolbar ===================== -->
    <div class="fm-toolbar" id="fmToolbar" role="toolbar" aria-label="Perintah file manager">
        <button class="btn btn-sm btn-outline-secondary d-md-none" data-act="toggle-tree" title="Folder"><i class="bi bi-layout-sidebar"></i></button>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" data-act="up" title="Naik satu tingkat (Backspace)"><i class="bi bi-arrow-up"></i></button>
            <button class="btn btn-outline-secondary" data-act="refresh" title="Muat ulang"><i class="bi bi-arrow-clockwise"></i></button>
        </div>

        <span data-mode="files" class="d-contents">
            <span class="vr"></span>
            <?php if ($canUpload): ?>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-primary" data-act="upload" title="Unggah berkas"><i class="bi bi-cloud-arrow-up"></i> <span class="d-none d-lg-inline">Unggah</span></button>
                    <?php if (can('files.create_folder')): ?>
                        <button class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="Opsi unggah"></button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item" data-act="upload"><i class="bi bi-file-earmark-arrow-up me-2"></i>Unggah berkas</button></li>
                            <li><button class="dropdown-item" data-act="upload-folder"><i class="bi bi-folder-symlink me-2"></i>Unggah folder</button></li>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (can('files.create_folder')): ?>
                <button class="btn btn-sm btn-outline-secondary" data-act="mkdir" title="Folder baru"><i class="bi bi-folder-plus"></i> <span class="d-none d-xl-inline">Folder baru</span></button>
            <?php endif; ?>
            <span class="vr"></span>
            <?php if (can('files.download')): ?><button class="btn btn-sm btn-outline-secondary" data-act="download" title="Unduh"><i class="bi bi-download"></i></button><?php endif; ?>
            <?php if (can('files.copy')): ?><button class="btn btn-sm btn-outline-secondary" data-act="copy" title="Salin (Ctrl+C)"><i class="bi bi-files"></i></button><?php endif; ?>
            <?php if (can('files.move')): ?><button class="btn btn-sm btn-outline-secondary" data-act="cut" title="Potong (Ctrl+X)"><i class="bi bi-scissors"></i></button><?php endif; ?>
            <?php if (can('files.copy') || can('files.move')): ?><button class="btn btn-sm btn-outline-secondary" data-act="paste" title="Tempel (Ctrl+V)"><i class="bi bi-clipboard-check"></i></button><?php endif; ?>
            <?php if (can('files.rename')): ?><button class="btn btn-sm btn-outline-secondary" data-act="rename" title="Ubah nama (F2)"><i class="bi bi-input-cursor-text"></i></button><?php endif; ?>
            <?php if (can('files.share')): ?><button class="btn btn-sm btn-outline-secondary" data-act="share" title="Bagikan lewat tautan"><i class="bi bi-share"></i></button><?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary" data-act="info" title="Properti"><i class="bi bi-info-circle"></i></button>
            <?php if (can('files.delete')): ?><button class="btn btn-sm btn-outline-danger" data-act="delete" title="Hapus (Delete)"><i class="bi bi-trash"></i></button><?php endif; ?>
        </span>

        <?php if (can('trash.manage')): ?>
            <span data-mode="trash" class="d-contents" hidden>
                <span class="vr"></span>
                <button class="btn btn-sm btn-outline-success" data-act="restore"><i class="bi bi-arrow-counterclockwise"></i> Pulihkan</button>
                <button class="btn btn-sm btn-outline-danger" data-act="purge"><i class="bi bi-x-octagon"></i> Hapus permanen</button>
                <button class="btn btn-sm btn-danger" data-act="empty-trash"><i class="bi bi-trash3"></i> Kosongkan sampah</button>
            </span>
        <?php endif; ?>

        <span class="fm-spacer"></span>

        <div class="btn-group btn-group-sm" role="group" aria-label="Tampilan">
            <button class="btn btn-outline-secondary" data-act="view-list" title="Tampilan daftar"><i class="bi bi-list-ul"></i></button>
            <button class="btn btn-outline-secondary" data-act="view-grid" title="Tampilan ikon"><i class="bi bi-grid-3x3-gap"></i></button>
        </div>
        <div class="input-group input-group-sm fm-search" data-mode="files">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="fmSearch" placeholder="Cari…" aria-label="Cari berkas">
        </div>
    </div>

    <!-- ===================== Badan ===================== -->
    <div class="fm-body">
        <aside class="fm-side" id="fmSide" aria-label="Folder">
            <div class="fm-side-head">Folder</div>
            <ul class="fm-tree" id="fmTree"></ul>
            <?php if (can('trash.manage')): ?>
                <div class="fm-side-head mt-2">Lainnya</div>
                <div class="fm-side-link" id="navTrash" role="button" tabindex="0"><i class="bi bi-trash3"></i> Sampah</div>
            <?php endif; ?>
            <div class="fm-usage" id="fmUsage"></div>
        </aside>

        <section class="fm-main" id="fmMain">
            <nav class="fm-crumbs" id="fmCrumbs" aria-label="Lokasi"></nav>
            <div class="fm-list list" id="fmList" tabindex="0" aria-label="Daftar berkas"></div>
            <div class="fm-drop" id="fmDrop" hidden><i class="bi bi-cloud-arrow-up display-4"></i>Lepaskan untuk mengunggah ke folder ini</div>
        </section>
    </div>

    <!-- ===================== Status ===================== -->
    <div class="fm-status" id="fmStatus">
        <span id="stCount"></span>
        <span id="stSel"></span>
        <span class="grow"></span>
        <span id="stClip"></span>
    </div>
</div>

<!-- Input unggah (tersembunyi) -->
<input type="file" id="inpFiles" multiple hidden>
<input type="file" id="inpDir" webkitdirectory hidden>

<!-- Menu konteks -->
<div class="dropdown-menu fm-ctx shadow" id="fmCtx" role="menu"></div>

<!-- Panel unggahan -->
<div class="fm-upload-panel" id="upPanel" hidden>
    <div class="head">
        <i class="bi bi-cloud-arrow-up"></i><span id="upTitle" class="flex-grow-1">Mengunggah…</span>
        <button class="btn btn-sm btn-link p-0" id="upToggle" title="Ciutkan / buka"><i class="bi bi-chevron-down"></i></button>
        <button class="btn btn-sm btn-link p-0 text-danger" id="upClose" title="Tutup"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="body" id="upList"></div>
</div>

<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="fmToasts" style="z-index:2100"></div>

<!-- ===================== Modal: isian teks ===================== -->
<div class="modal fade" id="modalPrompt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="promptForm" autocomplete="off">
            <div class="modal-header"><h5 class="modal-title" id="promptTitle">Nama</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <label class="form-label" for="promptInput" id="promptLabel">Nama</label>
                <input type="text" class="form-control" id="promptInput" maxlength="255" required>
                <div class="invalid-feedback" id="promptError"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary" id="promptOk">Simpan</button></div>
        </form>
    </div>
</div>

<!-- ===================== Modal: konfirmasi ===================== -->
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="confirmTitle">Konfirmasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body" id="confirmBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-danger" id="confirmOk">Ya</button></div>
        </div>
    </div>
</div>

<!-- ===================== Modal: pratinjau ===================== -->
<div class="modal fade" id="modalPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-truncate" id="pvTitle">Pratinjau</h5>
                <span class="ms-auto d-flex gap-2 align-items-center">
                    <small class="text-body-secondary d-none d-sm-inline" id="pvMeta"></small>
                    <?php if (can('files.download')): ?><a class="btn btn-sm btn-primary" id="pvDownload" href="#"><i class="bi bi-download"></i> Unduh</a><?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </span>
            </div>
            <div class="modal-body p-0 position-relative">
                <button class="btn btn-dark btn-sm rounded-circle fm-preview-nav prev" id="pvPrev" aria-label="Sebelumnya"><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-dark btn-sm rounded-circle fm-preview-nav next" id="pvNext" aria-label="Berikutnya"><i class="bi bi-chevron-right"></i></button>
                <div class="fm-preview-body" id="pvBody"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===================== Modal: bagikan ===================== -->
<div class="modal fade" id="modalShare" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-share me-2"></i>Bagikan lewat tautan</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <p class="mb-3 text-break"><i class="bi bi-file-earmark me-1" id="shIcon"></i><strong id="shName"></strong></p>
                <div id="shEmpty" class="text-center py-3" hidden>
                    <p class="text-body-secondary">Item ini belum memiliki tautan publik. Siapa pun yang memiliki tautan dapat membuka tanpa login.</p>
                    <button class="btn btn-primary" id="shCreate"><i class="bi bi-link-45deg me-1"></i>Buat tautan publik</button>
                </div>
                <form id="shForm" hidden autocomplete="off">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="shUrl" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="shCopy"><i class="bi bi-clipboard"></i> Salin</button>
                    </div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="shActive"><label class="form-check-label" for="shActive">Tautan aktif</label></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="shDownload"><label class="form-check-label" for="shDownload">Izinkan unduh <span class="text-body-secondary small">(nonaktif = hanya lihat)</span></label></div>
                    <div class="mb-3">
                        <label class="form-label" for="shPassword">Kata sandi <span class="text-body-secondary small" id="shPwState"></span></label>
                        <input type="password" class="form-control" id="shPassword" placeholder="Kosongkan = tanpa kata sandi" autocomplete="new-password">
                        <div class="form-check mt-1" id="shClearWrap" hidden><input class="form-check-input" type="checkbox" id="shClearPw"><label class="form-check-label small" for="shClearPw">Hapus kata sandi</label></div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="shExpires">Kedaluwarsa <span class="text-body-secondary small">(opsional)</span></label><input type="datetime-local" class="form-control" id="shExpires"></div>
                    <div class="small text-body-secondary" id="shStats"></div>
                </form>
            </div>
            <div class="modal-footer justify-content-between" id="shFooter" hidden>
                <button type="button" class="btn btn-outline-danger" id="shRemove"><i class="bi bi-x-circle me-1"></i>Cabut tautan</button>
                <button type="button" class="btn btn-primary" id="shSave"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- ===================== Modal: properti ===================== -->
<div class="modal fade" id="modalInfo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Properti</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body"><dl class="row mb-0" id="infoBody"></dl></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/filemanager.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/filemanager.js') ?: 1 ?>"></script>
<?= $this->endSection() ?>
