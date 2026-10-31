<?php

namespace App\Libraries;

use App\Models\ActivityLogModel;
use App\Models\NodeModel;
use App\Models\ShareModel;
use Config\Filemanager;
use ZipArchive;

/**
 * Logika bisnis file manager. Pemeriksaan izin tingkat-rute dilakukan oleh filter `perm`;
 * di sini dilakukan pemeriksaan tingkat-item (kepemilikan untuk peran "own_only"),
 * validasi nama, konflik, dan siklus folder.
 */
class FileService
{
    private NodeModel $nodes;

    public function __construct(
        private Storage $storage,
        private Auth $auth,
        private Filemanager $cfg
    ) {
        $this->nodes = new NodeModel();
    }

    public function nodes(): NodeModel
    {
        return $this->nodes;
    }

    // ------------------------------------------------------------------
    // Serialisasi untuk JSON
    // ------------------------------------------------------------------

    public function serialize(array $n): array
    {
        $iso = static fn (?string $d): ?string => $d ? date('c', strtotime($d)) : null;

        return [
            'id'         => (int) $n['id'],
            'parent_id'  => $n['parent_id'] !== null ? (int) $n['parent_id'] : null,
            'type'       => $n['type'],
            'name'       => $n['name'],
            'ext'        => $n['ext'] ?? '',
            'mime'       => $n['mime'] ?? '',
            'size'       => (int) $n['size'],
            'owner_id'   => $n['owner_id'] !== null ? (int) $n['owner_id'] : null,
            'owner_name' => $n['owner_name'] ?? null,
            'created_at' => $iso($n['created_at'] ?? null),
            'updated_at' => $iso($n['updated_at'] ?? null),
            'shared'     => (int) ($n['share_count'] ?? 0) > 0,
            'mine'       => $this->auth->check() && (int) ($n['owner_id'] ?? 0) === $this->auth->id(),
            'editable'   => ! $this->auth->isOwnOnly() || (int) ($n['owner_id'] ?? 0) === $this->auth->id(),
            'visibility' => ($n['visibility'] ?? 'shared') === 'private' ? 'private' : 'shared',
            'private'    => ($n['visibility'] ?? 'shared') === 'private',
            'description' => $n['description'] ?? null,
            'locked'     => ! empty($n['locked_at']),
            'locked_at'  => $iso($n['locked_at'] ?? null),
            'kind'       => $this->storage->previewKind($n),
            'thumb'      => $n['type'] === 'file' && str_starts_with((string) ($n['mime'] ?? ''), 'image/') && $n['mime'] !== 'image/svg+xml',
        ];
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /** Ambil node aktif atau lempar 404. */
    public function need(int $id): array
    {
        $n = $this->nodes->findActive($id);
        if ($n === null || ! $this->nodes->isVisible($n, $this->auth->id())) {
            throw new FileException('Item tidak ditemukan.', 404); // item pribadi orang lain = tidak ada
        }

        return $n;
    }

    /** Folder tujuan: null = root. Harus berupa folder aktif. */
    public function needFolder(?int $id): ?array
    {
        if ($id === null || $id === 0) {
            return null;
        }
        $n = $this->need($id);
        if ($n['type'] !== 'folder') {
            throw new FileException('Tujuan bukan folder.', 422);
        }

        return $n;
    }

    private function parentOf(?array $folder): ?int
    {
        return $folder ? (int) $folder['id'] : null;
    }

    private function assertCanModify(array $node, string $perm): void
    {
        if (! $this->auth->canModifyNode($node, $perm)) {
            throw new FileException('Anda tidak berhak melakukan aksi ini pada "' . $node['name'] . '".', 403);
        }
    }

    /** Item (atau folder induknya) terkunci -> tidak boleh diubah/dipindah/dihapus. */
    private function assertUnlocked(array $node, bool $deep = false): void
    {
        $lock = $this->nodes->lockedAncestor((int) $node['id']);
        if ($lock !== null) {
            $what = (int) $lock['id'] === (int) $node['id'] ? '"' . $node['name'] . '" terkunci' : '"' . $node['name'] . '" berada di folder terkunci "' . $lock['name'] . '"';
            throw new FileException($what . '. Buka kunci terlebih dahulu.', 423);
        }
        if ($deep && $node['type'] === 'folder' && $this->nodes->hasLockedDescendant((int) $node['id'])) {
            throw new FileException('Folder "' . $node['name'] . '" memuat item terkunci. Buka kunci terlebih dahulu.', 423);
        }
    }

    /** Folder tujuan terkunci (atau di dalam folder terkunci) -> tidak boleh menambah isi. */
    private function assertTargetOpen(?array $folder): void
    {
        if ($folder === null) {
            return;
        }
        $lock = $this->nodes->lockedAncestor((int) $folder['id']);
        if ($lock !== null) {
            throw new FileException('Folder "' . $lock['name'] . '" terkunci, isinya tidak dapat ditambah. Buka kunci terlebih dahulu.', 423);
        }
    }

    /**
     * Kunci / buka kunci. Hanya PEMILIK item yang boleh (administrator pun tidak),
     * kecuali item yatim tanpa pemilik. @return int jumlah item yang berubah
     */
    public function setLock(array $ids, bool $lock): int
    {
        $ids   = $this->normalizeIds($ids);
        $uid   = (int) $this->auth->id();
        $nodes = [];
        foreach ($ids as $id) {
            $node = $this->need($id);
            if ((int) ($node['owner_id'] ?? 0) !== $uid) {
                throw new FileException('Hanya pemilik yang dapat ' . ($lock ? 'mengunci' : 'membuka kunci') . ' "' . $node['name'] . '".', 403);
            }
            $nodes[] = $node;
        }
        $now = $this->now();
        $n   = 0;
        $this->nodes->db->transStart();
        foreach ($nodes as $node) {
            if ($lock === ! empty($node['locked_at'])) {
                continue;
            }
            $this->nodes->update((int) $node['id'], [
                'locked_by' => $lock ? $uid : null, 'locked_at' => $lock ? $now : null,
            ]);
            ActivityLogModel::record($lock ? 'lock' : 'unlock', $this->auth->user(), (int) $node['id'], $node['name']);
            $n++;
        }
        $this->nodes->db->transComplete();

        return $n;
    }

    /** Saring daftar baris menjadi yang terlihat oleh pengguna saat ini. $deep=true untuk hasil lintas folder. */
    public function visibleRows(array $rows, bool $deep): array
    {
        $me = $this->auth->id();
        if ($deep) {
            return $this->nodes->filterVisible($rows, $me);
        }

        return array_values(array_filter($rows, static fn ($r) => NodeModel::selfVisible($r, $me)));
    }

    /**
     * Pindah ke folder yang berada di dalam folder pribadi: semua yang dipindah harus milik sendiri,
     * agar pemilik aslinya tidak kehilangan akses karena item "menghilang" ke folder pribadi orang lain.
     */
    private function assertPrivacyCompatible(array $node, ?array $target): void
    {
        if ($target === null) {
            return;
        }
        $me   = (int) $this->auth->id();
        $cur  = (int) $target['id'];
        $priv = false;
        $g    = 0;
        while ($cur !== 0 && $g++ < 200) {
            $r = $this->nodes->select('id, parent_id, visibility')->find($cur);
            if ($r === null) {
                break;
            }
            if (($r['visibility'] ?? 'shared') === 'private') {
                $priv = true;
                break;
            }
            $cur = (int) ($r['parent_id'] ?? 0);
        }
        if (! $priv) {
            return;
        }
        $foreign = (int) $node['owner_id'] !== $me
            || ($node['type'] === 'folder' && $this->nodes->hasForeignDescendant((int) $node['id'], $me));
        if ($foreign) {
            throw new FileException('"' . $node['name'] . '" milik pengguna lain dan tidak dapat dimasukkan ke folder pribadi.', 403);
        }
    }

    /**
     * Ubah visibilitas ('private'|'shared'). Hanya PEMILIK. Folder yang berisi item milik pengguna lain
     * tidak dapat dijadikan pribadi (agar item mereka tidak tiba-tiba hilang).
     *
     * @return int jumlah item yang berubah
     */
    public function setVisibility(array $ids, string $visibility): int
    {
        if (! in_array($visibility, ['private', 'shared'], true)) {
            throw new FileException('Nilai visibilitas tidak valid.', 422);
        }
        $ids   = $this->normalizeIds($ids);
        $uid   = (int) $this->auth->id();
        $nodes = [];
        foreach ($ids as $id) {
            $node = $this->need($id);
            if ((int) ($node['owner_id'] ?? 0) !== $uid) {
                throw new FileException('Hanya pemilik yang dapat mengubah visibilitas "' . $node['name'] . '".', 403);
            }
            if ($visibility === 'private' && $node['type'] === 'folder' && $this->nodes->hasForeignDescendant((int) $node['id'], $uid)) {
                throw new FileException('Folder "' . $node['name'] . '" berisi item milik pengguna lain, sehingga tidak dapat dijadikan pribadi.', 409);
            }
            $nodes[] = $node;
        }
        $now = $this->now();
        $n   = 0;
        $this->nodes->db->transStart();
        foreach ($nodes as $node) {
            if (($node['visibility'] ?? 'shared') === $visibility) {
                continue;
            }
            $this->nodes->update((int) $node['id'], ['visibility' => $visibility, 'updated_at' => $now]);
            ActivityLogModel::record($visibility === 'private' ? 'make_private' : 'make_shared', $this->auth->user(), (int) $node['id'], $node['name']);
            $n++;
        }
        $this->nodes->db->transComplete();

        return $n;
    }

    /** Deskripsi folder/berkas (maks. 500 karakter). Hak seperti ganti nama; item terkunci harus dibuka dulu. */
    public function setDescription(int $id, string $text): array
    {
        $node = $this->need($id);
        $this->assertCanModify($node, 'files.rename');
        $this->assertUnlocked($node);

        $text = trim(str_replace("\r\n", "\n", $text));
        if (mb_strlen($text) > 500) {
            throw new FileException('Deskripsi maksimal 500 karakter.', 422);
        }
        $this->nodes->update($id, ['description' => $text === '' ? null : $text, 'updated_at' => $this->now()]);
        ActivityLogModel::record('describe', $this->auth->user(), $id, $node['name']);

        return $this->serialize($this->nodes->find($id));
    }

    /** @param array<int|string> $ids */
    private function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $i = (int) $id;
            if ($i > 0) {
                $out[$i] = $i;
            }
        }
        if ($out === []) {
            throw new FileException('Tidak ada item yang dipilih.', 422);
        }

        return array_values($out);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    // ------------------------------------------------------------------
    // Folder
    // ------------------------------------------------------------------

    /**
     * Buat folder. Dengan $ensure=true, folder yang sudah ada dikembalikan apa adanya
     * (dipakai saat mengunggah folder beserta isinya).
     */
    public function createFolder(?int $parentId, string $name, bool $ensure = false): array
    {
        $parent = $this->needFolder($parentId);
        $name   = $this->storage->sanitizeName($name, true);
        if (! ($ensure && $this->nodes->nameExists($this->parentOf($parent), $name))) {
            $this->assertTargetOpen($parent);
        }
        $pid    = $this->parentOf($parent);

        if ($this->nodes->nameExists($pid, $name)) {
            if ($ensure) {
                $existing = $this->nodes->where('parent_id', $pid)->where('deleted_at', null)->where('LOWER(name)', mb_strtolower($name))->first();
                if ($existing !== null && $existing['type'] === 'folder') {
                    return $this->serialize($existing);
                }
            }
            throw new FileException('Sudah ada item bernama "' . $name . '" di folder ini.', 409);
        }

        $now = $this->now();
        $id  = $this->nodes->insert([
            'parent_id' => $pid, 'type' => 'folder', 'name' => $name, 'size' => 0,
            'owner_id'  => $this->auth->id(), 'created_at' => $now, 'updated_at' => $now,
        ], true);

        ActivityLogModel::record('mkdir', $this->auth->user(), (int) $id, $name);

        return $this->serialize($this->nodes->find((int) $id) + ['owner_name' => $this->auth->user()['name'] ?? null]);
    }

    // ------------------------------------------------------------------
    // Rename / pindah / salin
    // ------------------------------------------------------------------

    public function rename(int $id, string $name): array
    {
        $node = $this->need($id);
        $this->assertCanModify($node, 'files.rename');
        $this->assertUnlocked($node);

        $isFolder = $node['type'] === 'folder';
        $name     = $this->storage->sanitizeName($name, $isFolder);

        if ($name === $node['name']) {
            return $this->serialize($node);
        }
        if ($this->nodes->nameExists($node['parent_id'] !== null ? (int) $node['parent_id'] : null, $name, $id)) {
            throw new FileException('Sudah ada item bernama "' . $name . '" di folder ini.', 409);
        }

        $update = ['name' => $name, 'updated_at' => $this->now()];
        if (! $isFolder) {
            $update['ext'] = $this->storage->extensionOf($name);
        }
        $this->nodes->update($id, $update);

        ActivityLogModel::record('rename', $this->auth->user(), $id, $name, ['from' => $node['name']]);

        return $this->serialize($this->nodes->find($id));
    }

    /** @return int jumlah item yang dipindah */
    public function move(array $ids, ?int $targetId): int
    {
        $ids    = $this->normalizeIds($ids);
        $target = $this->needFolder($targetId);
        $this->assertTargetOpen($target);
        $tid    = $this->parentOf($target);
        $plan   = [];

        foreach ($ids as $id) {
            $node = $this->need($id);
            $this->assertCanModify($node, 'files.move');
            $this->assertUnlocked($node);
            $this->assertPrivacyCompatible($node, $target);

            $current = $node['parent_id'] !== null ? (int) $node['parent_id'] : null;
            if ($current === $tid) {
                continue; // sudah di sana
            }
            if ($node['type'] === 'folder' && $tid !== null && $this->nodes->isWithin($tid, $id)) {
                throw new FileException('Folder "' . $node['name'] . '" tidak dapat dipindah ke dalam dirinya sendiri.', 422);
            }
            if ($this->nodes->nameExists($tid, $node['name'])) {
                throw new FileException('Di folder tujuan sudah ada item bernama "' . $node['name'] . '".', 409);
            }
            $plan[] = $node;
        }

        $now = $this->now();
        $this->nodes->db->transStart();
        foreach ($plan as $node) {
            $this->nodes->update((int) $node['id'], ['parent_id' => $tid, 'updated_at' => $now]);
            ActivityLogModel::record('move', $this->auth->user(), (int) $node['id'], $node['name'], ['to' => $tid]);
        }
        $this->nodes->db->transComplete();

        return count($plan);
    }

    /** @return int jumlah item tingkat-atas yang disalin */
    public function copy(array $ids, ?int $targetId): int
    {
        $ids    = $this->normalizeIds($ids);
        $target = $this->needFolder($targetId);
        $this->assertTargetOpen($target);
        $tid    = $this->parentOf($target);
        $count  = 0;

        $created = []; // storage_name baru, untuk rollback fisik bila gagal
        $this->nodes->db->transStart();
        try {
            foreach ($ids as $id) {
                $node = $this->need($id);
                if ($node['type'] === 'folder' && $tid !== null && $this->nodes->isWithin($tid, $id)) {
                    throw new FileException('Folder "' . $node['name'] . '" tidak dapat disalin ke dalam dirinya sendiri.', 422);
                }
                $newId = $this->copyNode($node, $tid, $created);
                ActivityLogModel::record('copy', $this->auth->user(), $newId, $node['name'], ['to' => $tid]);
                $count++;
            }
        } catch (\Throwable $e) {
            $this->nodes->db->transRollback();
            foreach ($created as $sn) {
                $this->storage->delete($sn);
            }
            throw $e;
        }
        $this->nodes->db->transComplete();

        return $count;
    }

    /** Salin rekursif satu node ke folder $parentId. @param list<string> $created */
    private function copyNode(array $node, ?int $parentId, array &$created): int
    {
        $isFolder = $node['type'] === 'folder';
        $name     = $this->nodes->uniqueName($parentId, $node['name'], $isFolder);
        $now      = $this->now();

        $row = [
            'parent_id' => $parentId, 'type' => $node['type'], 'name' => $name,
            'mime' => $node['mime'], 'ext' => $node['ext'], 'size' => $node['size'],
            'owner_id' => $this->auth->id(), 'created_at' => $now, 'updated_at' => $now,
            'visibility' => ($node['visibility'] ?? 'shared') === 'private' ? 'private' : 'shared',
            'description' => $node['description'] ?? null,
        ];
        if (! $isFolder) {
            $row['storage_name'] = $this->storage->duplicate((string) $node['storage_name']);
            $created[]           = $row['storage_name'];
        }

        $newId = (int) $this->nodes->insert($row, true);

        if ($isFolder) {
            foreach ($this->nodes->where('parent_id', (int) $node['id'])->where('deleted_at', null)->findAll() as $child) {
                if (! NodeModel::selfVisible($child, $this->auth->id())) {
                    continue; // item pribadi milik orang lain tidak ikut tersalin
                }
                $this->copyNode($child, $newId, $created);
            }
        }

        return $newId;
    }

    // ------------------------------------------------------------------
    // Sampah
    // ------------------------------------------------------------------

    /** Pindahkan ke sampah (soft delete) beserta seluruh isinya. */
    public function trash(array $ids): int
    {
        $ids   = $this->normalizeIds($ids);
        $nodes = [];
        foreach ($ids as $id) {
            $node = $this->need($id);
            $this->assertCanModify($node, 'files.delete');
            $this->assertUnlocked($node, true);
            if ($node['type'] === 'folder' && $this->nodes->hasHiddenDescendant((int) $node['id'], $this->auth->id())) {
                throw new FileException('Folder "' . $node['name'] . '" memuat item pribadi milik pengguna lain, sehingga tidak dapat dihapus.', 403);
            }
            $nodes[] = $node;
        }

        $now = $this->now();
        $uid = $this->auth->id();
        $this->nodes->db->transStart();
        foreach ($nodes as $node) {
            $all = [(int) $node['id']];
            if ($node['type'] === 'folder') {
                foreach ($this->nodes->descendants((int) $node['id']) as $d) {
                    $all[] = (int) $d['id'];
                }
            }
            foreach (array_chunk($all, 500) as $part) {
                $this->nodes->builder()->whereIn('id', $part)->update([
                    'deleted_at' => $now, 'deleted_by' => $uid, 'trash_root_id' => (int) $node['id'],
                ]);
            }
            ActivityLogModel::record('delete', $this->auth->user(), (int) $node['id'], $node['name']);
        }
        $this->nodes->db->transComplete();

        return count($nodes);
    }

    /** Item sampah tingkat-atas yang boleh dikelola pengguna saat ini. */
    public function trashList(): array
    {
        $rows = $this->nodes->filterVisible($this->nodes->trashRoots(), $this->auth->id());
        if ($this->auth->isOwnOnly()) {
            $me   = $this->auth->id();
            $rows = array_values(array_filter($rows, static fn ($r) => (int) $r['owner_id'] === $me || (int) $r['deleted_by'] === $me));
        }

        return $rows;
    }

    private function needTrashRoot(int $id): array
    {
        $row = $this->nodes->where('id', $id)->where('trash_root_id', $id)->first();
        if ($row === null || $row['deleted_at'] === null || ! $this->nodes->isVisible($row, $this->auth->id())) {
            throw new FileException('Item sampah tidak ditemukan.', 404);
        }
        if ($this->auth->isOwnOnly()) {
            $me = $this->auth->id();
            if ((int) $row['owner_id'] !== $me && (int) $row['deleted_by'] !== $me) {
                throw new FileException('Anda tidak berhak mengelola item ini.', 403);
            }
        }

        return $row;
    }

    public function restore(array $ids): int
    {
        $ids   = $this->normalizeIds($ids);
        $roots = array_map(fn ($id) => $this->needTrashRoot($id), $ids);

        $this->nodes->db->transStart();
        foreach ($roots as $root) {
            $parent = $root['parent_id'] !== null ? (int) $root['parent_id'] : null;

            // Jika folder asal sudah tidak ada / masih di sampah, pulihkan ke root.
            if ($parent !== null && $this->nodes->findActive($parent) === null) {
                $parent = null;
            }
            $name = $this->nodes->uniqueName($parent, $root['name'], $root['type'] === 'folder');

            $this->nodes->update((int) $root['id'], ['parent_id' => $parent, 'name' => $name]);
            $this->nodes->builder()->where('trash_root_id', (int) $root['id'])->update([
                'deleted_at' => null, 'deleted_by' => null, 'trash_root_id' => null,
            ]);
            ActivityLogModel::record('restore', $this->auth->user(), (int) $root['id'], $name);
        }
        $this->nodes->db->transComplete();

        return count($roots);
    }

    public function purge(array $ids): int
    {
        $ids   = $this->normalizeIds($ids);
        $roots = array_map(fn ($id) => $this->needTrashRoot($id), $ids);
        $n     = 0;
        foreach ($roots as $root) {
            $this->purgeRoot($root, true);
            $n++;
        }

        return $n;
    }

    /** Kosongkan sampah (yang boleh dikelola pengguna saat ini). */
    public function emptyTrash(): int
    {
        $n = 0;
        foreach ($this->trashList() as $root) {
            $this->purgeRoot($root, true);
            $n++;
        }

        return $n;
    }

    /** Hapus permanen satu grup sampah: berkas fisik, share, dan baris database. */
    public function purgeRoot(array $root, bool $log = false): void
    {
        $rows = $this->nodes->builder()->select('id, name, type, storage_name')
            ->where('trash_root_id', (int) $root['id'])->get()->getResultArray();

        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);

        $this->nodes->db->transStart();
        foreach (array_chunk($ids, 500) as $part) {
            (new ShareModel())->whereIn('node_id', $part)->delete();
            $this->nodes->builder()->whereIn('id', $part)->delete();
        }
        $this->nodes->db->transComplete();

        // Hapus fisik setelah database berhasil.
        foreach ($rows as $r) {
            if ($r['type'] === 'file') {
                $this->storage->delete($r['storage_name']);
            }
        }

        if ($log) {
            ActivityLogModel::record('purge', $this->auth->user(), (int) $root['id'], $root['name']);
        }
    }

    /** Hapus permanen sampah yang lebih tua dari N hari (untuk CLI). */
    public function purgeOlderThan(int $days): int
    {
        $cut = date('Y-m-d H:i:s', time() - $days * 86400);
        $n   = 0;
        foreach ($this->nodes->trashRoots() as $root) {
            if ($root['deleted_at'] < $cut) {
                $this->purgeRoot($root);
                $n++;
            }
        }

        return $n;
    }

    // ------------------------------------------------------------------
    // Upload per-potongan (chunk)
    // ------------------------------------------------------------------

    /**
     * Terima satu potongan. Potongan harus berurutan (offset == ukuran yang sudah diterima).
     * Mengembalikan ['done'=>false,'received'=>int] atau ['done'=>true,'item'=>array].
     *
     * @param array{upload_id:string,name:string,size:int,offset:int,parent_id:?int} $meta
     */
    public function acceptChunk(array $meta, string $chunkPath, int $chunkSize): array
    {
        $uid = (int) $this->auth->id();
        $up  = (string) $meta['upload_id'];
        if (! preg_match('/^[a-f0-9]{16,64}$/', $up)) {
            throw new FileException('ID upload tidak valid.', 422);
        }

        $parent = $this->needFolder($meta['parent_id']);
        $this->assertTargetOpen($parent);
        $name   = $this->storage->sanitizeUploadName((string) $meta['name']);
        $total  = (int) $meta['size'];
        $offset = (int) $meta['offset'];

        if ($total < 0 || $total > $this->cfg->maxFileSize) {
            throw new FileException('Ukuran berkas melebihi batas (' . self::formatBytes($this->cfg->maxFileSize) . ').', 413);
        }
        if ($chunkSize > $this->cfg->chunkSize * 4) {
            throw new FileException('Potongan terlalu besar.', 413);
        }

        $dir = $this->storage->tmpDir() . DIRECTORY_SEPARATOR . 'up_' . $uid . '_' . $up;
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new FileException('Gagal membuat direktori sementara.', 500);
        }
        $part = $dir . DIRECTORY_SEPARATOR . 'data';

        clearstatcache(true, $part);
        $have = is_file($part) ? (int) filesize($part) : 0;
        if ($offset !== $have) {
            // Beri tahu klien posisi yang benar agar bisa melanjutkan.
            return ['done' => false, 'received' => $have, 'resync' => true];
        }
        if ($have + $chunkSize > $total) {
            @unlink($part);
            throw new FileException('Data yang diterima melebihi ukuran yang dinyatakan.', 422);
        }

        // Tambahkan potongan ke berkas sementara.
        $in  = fopen($chunkPath, 'rb');
        $out = fopen($part, 'ab');
        if ($in === false || $out === false) {
            throw new FileException('Gagal menulis berkas sementara.', 500);
        }
        flock($out, LOCK_EX);
        stream_copy_to_stream($in, $out);
        fflush($out);
        flock($out, LOCK_UN);
        fclose($in);
        fclose($out);

        clearstatcache(true, $part);
        $received = (int) filesize($part);
        if ($received < $total) {
            return ['done' => false, 'received' => $received];
        }

        // ---- selesai: finalisasi ----
        $mime        = $this->storage->detectMime($part, $name);
        $storageName = $this->storage->newStorageName();

        if (! $this->storage->adopt($part, $storageName)) {
            throw new FileException('Gagal menyimpan berkas ke penyimpanan.', 500);
        }
        @rmdir($dir);

        $pid      = $this->parentOf($parent);
        $finalName = $this->nodes->uniqueName($pid, $name);
        $now      = $this->now();

        $id = (int) $this->nodes->insert([
            'parent_id' => $pid, 'type' => 'file', 'name' => $finalName,
            'storage_name' => $storageName, 'mime' => $mime, 'ext' => $this->storage->extensionOf($finalName),
            'size' => $received, 'owner_id' => $uid, 'created_at' => $now, 'updated_at' => $now,
        ], true);

        ActivityLogModel::record('upload', $this->auth->user(), $id, $finalName, ['size' => $received]);

        return [
            'done' => true,
            'item' => $this->serialize($this->nodes->find($id) + ['owner_name' => $this->auth->user()['name'] ?? null]),
        ];
    }

    /** Batalkan upload yang sedang berjalan. */
    public function abortUpload(string $uploadId): void
    {
        if (! preg_match('/^[a-f0-9]{16,64}$/', $uploadId)) {
            return;
        }
        $dir = $this->storage->tmpDir() . DIRECTORY_SEPARATOR . 'up_' . (int) $this->auth->id() . '_' . $uploadId;
        if (is_dir($dir)) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    // ------------------------------------------------------------------
    // ZIP
    // ------------------------------------------------------------------

    /**
     * Bangun ZIP dari sejumlah node (file/folder). Mengembalikan path berkas ZIP sementara.
     * Pemanggil bertanggung jawab menghapusnya (StreamResponse delete_after).
     *
     * @param list<array> $nodes baris node aktif (sudah diperiksa izin aksesnya)
     */
    public function buildZip(array $nodes, bool $public = false): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new FileException('Ekstensi PHP zip tidak tersedia di server.', 500);
        }

        // Hitung dulu total ukuran untuk perlindungan disk.
        $plan  = [];
        $total = 0;
        $used  = [];
        foreach ($nodes as $node) {
            $this->collectZipEntries($node, '', $used, $plan, $total, $public);
        }
        if ($total > $this->cfg->maxZipBytes) {
            throw new FileException('Total ukuran terlalu besar untuk diunduh sebagai ZIP (maksimal ' . self::formatBytes($this->cfg->maxZipBytes) . ').', 413);
        }
        if (count($plan) > 50000) {
            throw new FileException('Terlalu banyak item untuk satu ZIP.', 413);
        }

        $tmp = $this->storage->tmpDir() . DIRECTORY_SEPARATOR . 'zip_' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new FileException('Gagal membuat berkas ZIP.', 500);
        }

        foreach ($plan as [$kind, $zipPath, $realPath]) {
            if ($kind === 'dir') {
                $zip->addEmptyDir($zipPath);
            } elseif (is_file($realPath)) {
                $zip->addFile($realPath, $zipPath);
            }
        }
        if ($plan === []) {
            $zip->addEmptyDir('kosong');
        }
        if (! $zip->close()) {
            @unlink($tmp);
            throw new FileException('Gagal menyelesaikan berkas ZIP.', 500);
        }

        return $tmp;
    }

    /**
     * @param array<string,bool> $used
     * @param list<array{0:string,1:string,2:string}> $plan
     */
    private function collectZipEntries(array $node, string $prefix, array &$used, array &$plan, int &$total, bool $public = false): void
    {
        $name = $this->uniqueZipName($prefix, $node['name'], $used);

        if ($node['type'] === 'folder') {
            $plan[] = ['dir', $prefix . $name . '/', ''];
            $inner  = [];
            foreach ($this->nodes->where('parent_id', (int) $node['id'])->where('deleted_at', null)->orderBy('name')->findAll() as $child) {
                // Tautan publik: item pribadi (siapa pun pemiliknya) tidak ikut. Pengguna login: item pribadi orang lain tidak ikut.
                $ok = $public ? ($child['visibility'] ?? 'shared') !== 'private' : NodeModel::selfVisible($child, $this->auth->id());
                if ($ok) {
                    $this->collectZipEntries($child, $prefix . $name . '/', $inner, $plan, $total, $public);
                }
            }

            return;
        }

        if (Storage::validStorageName($node['storage_name'])) {
            $plan[] = ['file', $prefix . $name, $this->storage->pathFor($node['storage_name'])];
            $total += (int) $node['size'];
        }
    }

    /** @param array<string,bool> $used */
    private function uniqueZipName(string $prefix, string $name, array &$used): string
    {
        $key = mb_strtolower($name);
        if (! isset($used[$key])) {
            $used[$key] = true;

            return $name;
        }
        $dot  = strrpos($name, '.');
        $base = $dot ? substr($name, 0, $dot) : $name;
        $ext  = $dot ? substr($name, $dot) : '';
        for ($i = 1; $i < 10000; $i++) {
            $cand = $base . ' (' . $i . ')' . $ext;
            if (! isset($used[mb_strtolower($cand)])) {
                $used[mb_strtolower($cand)] = true;

                return $cand;
            }
        }

        return $base . '-' . bin2hex(random_bytes(3)) . $ext;
    }

    // ------------------------------------------------------------------
    // Pembersihan otomatis (pengganti cron di shared hosting)
    // ------------------------------------------------------------------

    /**
     * Dijalankan oleh request biasa paling sering sekali per 24 jam:
     * hapus permanen sampah yang melewati masa simpan + buang potongan upload/ZIP yang basi.
     */
    public function housekeeping(): void
    {
        $flag = $this->storage->base() . DIRECTORY_SEPARATOR . '.housekeeping';
        if (is_file($flag) && (time() - (int) @filemtime($flag)) < 86400) {
            return;
        }
        @touch($flag); // tandai dulu agar request paralel tidak ikut menjalankan

        try {
            $this->purgeOlderThan($this->cfg->trashRetentionDays);
            $this->storage->cleanTmp(86400);
        } catch (\Throwable $e) {
            log_message('error', 'Housekeeping gagal: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Respons berkas (dipakai Transfer & Share)
    // ------------------------------------------------------------------

    /** Stream isi berkas sebagai unduhan atau tampilan inline (hanya untuk MIME yang aman). */
    public function fileResponse(array $node, bool $inline, $request = null): StreamResponse
    {
        if ($node['type'] !== 'file' || ! Storage::validStorageName($node['storage_name'])) {
            throw new FileException('Berkas tidak ditemukan.', 404);
        }
        $path = $this->storage->pathFor($node['storage_name']);
        if (! is_file($path)) {
            throw new FileException('Berkas fisik tidak ditemukan di penyimpanan.', 404);
        }

        $mime = (string) ($node['mime'] ?: 'application/octet-stream');
        $opts = [];
        if ($inline && $this->storage->isInlineSafe($node)) {
            $opts = ['inline' => true, 'csp' => $this->storage->inlineCsp()];
        } else {
            // Unduhan: jangan beri MIME yang bisa dirender (html/svg/xml) bila terbuka tanpa disposition.
            if (preg_match('#^(text/html|application/xhtml|image/svg|text/xml|application/xml)#i', $mime)) {
                $mime = 'application/octet-stream';
            }
            $opts = ['inline' => false];
        }

        return StreamResponse::make($path, $node['name'], $mime, $request, $opts);
    }

    public function thumbResponse(array $node, $request = null): ?StreamResponse
    {
        $thumb = $this->storage->thumbnail($node);
        if ($thumb === null) {
            return null;
        }

        return StreamResponse::make($thumb, 'thumb.jpg', 'image/jpeg', $request, ['inline' => true, 'cache' => 86400]);
    }

    // ------------------------------------------------------------------
    // Util
    // ------------------------------------------------------------------

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $i     = (int) floor(log($bytes, 1024));
        $i     = min($i, 4);

        return round($bytes / (1024 ** $i), $i >= 3 ? 2 : 1) . ' ' . $units[$i - 1];
    }
}
