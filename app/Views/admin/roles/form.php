<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$edit   = $role !== null;
$system = $edit && $role['is_system'];
$val    = static fn (string $k, $d = '') => old($k, $edit ? ($role[$k] ?? $d) : $d);
$oldPerms = old('permissions');
$checked  = is_array($oldPerms) ? $oldPerms : $granted;
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h4 mb-3"><i class="bi bi-shield-check me-2"></i><?= esc($title) ?></h1>
        <form method="post" action="<?= $edit ? site_url('admin/roles/' . $role['id']) : site_url('admin/roles') ?>">
            <?= csrf_field() ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="label">Nama peran</label>
                            <input class="form-control" id="label" name="label" value="<?= esc($val('label')) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="name">Kode (huruf kecil, tanpa spasi)</label>
                            <input class="form-control" id="name" name="name" value="<?= esc($val('name')) ?>" required pattern="[A-Za-z0-9_\-]{2,50}" <?= $system ? 'readonly' : '' ?>>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Keterangan</label>
                        <input class="form-control" id="description" name="description" value="<?= esc($val('description')) ?>" maxlength="255">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="own_only" name="own_only" value="1" <?= (int) $val('own_only', 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="own_only">Hanya boleh mengubah / memindah / menghapus / membagikan <strong>item miliknya sendiri</strong></label>
                    </div>
                </div>
            </div>

            <?php foreach ($groups as $group => $perms): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><?= esc($group) ?></strong>
                        <button type="button" class="btn btn-sm btn-link p-0 js-toggle-group">pilih semua / kosongkan</button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($perms as $p): ?>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input perm-check" type="checkbox" name="permissions[]" id="perm_<?= esc($p['code'], 'attr') ?>" value="<?= esc($p['code'], 'attr') ?>" <?= in_array($p['code'], $checked, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="perm_<?= esc($p['code'], 'attr') ?>"><?= esc($p['label']) ?> <code class="small text-body-secondary"><?= esc($p['code']) ?></code></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('admin/roles') ?>">Batal</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.querySelectorAll('.js-toggle-group').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var boxes = btn.closest('.card').querySelectorAll('.perm-check');
            var all = Array.prototype.every.call(boxes, function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !all; });
        });
    });
</script>
<?= $this->endSection() ?>
