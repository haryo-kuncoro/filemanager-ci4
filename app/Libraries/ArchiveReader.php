<?php

namespace App\Libraries;

use App\Models\ActivityLogModel;
use App\Models\NodeModel;

/**
 * Membaca isi backup (ZIP) TANPA memulihkannya, dan mengekstrak item terpilih ke folder khusus
 * di pohon berkas yang berjalan — data yang sedang dipakai tidak disentuh sama sekali.
 *
 * Seluruh item di arsip terbaca (pemilik mana pun, pribadi, terkunci) karena hanya administrator
 * yang boleh memakai kelas ini (dijaga di rute). Nama entri ZIP TIDAK pernah diambil dari isi arsip:
 * path dihitung dari storage_name yang divalidasi (32 hex).
 */
class ArchiveReader
{
    public const CONTAINER_NAME = 'Pemulihan Arsip';

    private string $path;
    private ?\ZipArchive $zip = null;
    private ?array $manifest  = null;
    /** @var array<int,array> */
    private array $nodes = [];
    /** @var array<int,list<int>> parent (0 = akar) => anak */
    private array $children = [];
    /** @var array<int,string> */
    private array $users  = [];
    private bool $indexed = false;
    /** @var array<string,array<int,int>> */
    private array $sizeCache = [];

    public function __construct(private Storage $storage, private Backup $backup, private string $name)
    {
        if (! Backup::available()) {
            throw new FileException('Ekstensi PHP zip tidak tersedia di server ini.', 500);
        }
        $this->path = $backup->path($name); // memvalidasi nama + keberadaan
    }

    public function name(): string
    {
        return $this->name;
    }

    private function zip(): \ZipArchive
    {
        if ($this->zip === null) {
            $z = new \ZipArchive();
            if ($z->open($this->path, \ZipArchive::RDONLY) !== true) {
                throw new FileException('Backup tidak dapat dibuka (berkas rusak?).', 422);
            }
            $this->zip = $z;
        }

        return $this->zip;
    }

    public function manifest(): array
    {
        if ($this->manifest === null) {
            $raw = $this->zip()->getFromName('manifest.json');
            $m   = $raw !== false ? json_decode($raw, true) : null;
            if (! is_array($m) || ($m['format'] ?? null) !== Backup::FORMAT || ($m['app'] ?? '') !== 'filemanager-ci4') {
                throw new FileException('Berkas ini bukan backup File Manager yang valid.', 422);
            }
            $this->manifest = $m;
        }

        return $this->manifest;
    }

    // ------------------------------------------------------------------
    // Indeks pohon (dibaca streaming dari database/nodes.jsonl)
    // ------------------------------------------------------------------

    /** @return \Generator<array> */
    private function rows(string $table): \Generator
    {
        $fh = $this->zip()->getStream('database/' . $table . '.jsonl');
        if ($fh === false) {
            return;
        }
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $r = json_decode($line, true);
            if (is_array($r)) {
                yield $r;
            }
        }
        fclose($fh);
    }

    private function index(): void
    {
        if ($this->indexed) {
            return;
        }
        $this->manifest();
        foreach ($this->rows('users') as $u) {
            $this->users[(int) ($u['id'] ?? 0)] = (string) (($u['name'] ?? '') !== '' ? $u['name'] : ($u['username'] ?? ''));
        }
        foreach ($this->rows('nodes') as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $pid = isset($r['parent_id']) && $r['parent_id'] !== null ? (int) $r['parent_id'] : null;
            $this->nodes[$id] = [
                'id'      => $id,
                'pid'     => $pid,
                'type'    => ($r['type'] ?? '') === 'folder' ? 'folder' : 'file',
                'name'    => (string) ($r['name'] ?? ''),
                'size'    => (int) ($r['size'] ?? 0),
                'owner'   => isset($r['owner_id']) && $r['owner_id'] !== null ? (int) $r['owner_id'] : null,
                'private' => ($r['visibility'] ?? 'shared') === 'private',
                'locked'  => ! empty($r['locked_at']),
                'deleted' => ! empty($r['deleted_at']),
                'created' => $r['created_at'] ?? null,
                'updated' => $r['updated_at'] ?? null,
                'sn'      => isset($r['storage_name']) ? (string) $r['storage_name'] : null,
                'mime'    => (string) ($r['mime'] ?? ''),
                'desc'    => isset($r['description']) && $r['description'] !== '' ? (string) $r['description'] : null,
            ];
            $this->children[$pid ?? 0][] = $id;
        }
        $this->indexed = true;
    }

    private function node(int $id): array
    {
        $this->index();
        if (! isset($this->nodes[$id])) {
            throw new FileException('Item tidak ditemukan di arsip.', 404);
        }

        return $this->nodes[$id];
    }

    private function entryFor(?string $sn): ?string
    {
        if (! Storage::validStorageName($sn)) {
            return null;
        }

        return 'files/' . substr($sn, 0, 2) . '/' . substr($sn, 2, 2) . '/' . $sn;
    }

    private function fileAvailable(array $n): bool
    {
        $e = $this->entryFor($n['sn']);

        return $e !== null && $this->zip()->locateName($e) !== false;
    }

    /** Total ukuran rekursif per folder (berkas di sampah hanya dihitung bila $withTrash). */
    private function folderSizes(bool $withTrash): array
    {
        $key = $withTrash ? 't' : 'n';
        if (isset($this->sizeCache[$key])) {
            return $this->sizeCache[$key];
        }
        $this->index();
        $tot = [];
        foreach ($this->nodes as $n) {
            if ($n['type'] !== 'file' || ($n['deleted'] && ! $withTrash)) {
                continue;
            }
            $cur   = $n['pid'];
            $guard = 0;
            while ($cur !== null && isset($this->nodes[$cur]) && $guard++ < 200) {
                $tot[$cur] = ($tot[$cur] ?? 0) + $n['size'];
                $cur       = $this->nodes[$cur]['pid'];
            }
        }

        return $this->sizeCache[$key] = $tot;
    }

    private function pathOf(int $id): string
    {
        $parts = [];
        $cur   = $id;
        $guard = 0;
        while ($cur !== null && isset($this->nodes[$cur]) && $guard++ < 200) {
            array_unshift($parts, $this->nodes[$cur]['name']);
            $cur = $this->nodes[$cur]['pid'];
        }

        return '/' . implode('/', $parts);
    }

    // ------------------------------------------------------------------
    // Telusur
    // ------------------------------------------------------------------

    public function summary(): array
    {
        $this->index();
        $m = $this->manifest();

        return [
            'name'       => $this->name,
            'created_at' => $m['created_at'] ?? null,
            'created_by' => $m['created_by'] ?? null,
            'files'      => (int) ($m['files'] ?? 0),
            'bytes'      => (int) ($m['files_bytes'] ?? 0),
            'nodes'      => count($this->nodes),
            'missing'    => (int) ($m['files_missing'] ?? 0),
        ];
    }

    /** @return array{breadcrumb:list<array>,items:list<array>,search:?string} */
    public function list(?int $parent, string $q, bool $withTrash): array
    {
        $this->index();
        $crumbs = [['id' => null, 'name' => 'Akar arsip']];
        if ($parent !== null) {
            $chain = [];
            $cur   = $parent;
            $guard = 0;
            while ($cur !== null && $guard++ < 200) {
                $n = $this->node($cur);
                if ($n['type'] !== 'folder') {
                    throw new FileException('Bukan folder.', 422);
                }
                array_unshift($chain, ['id' => $n['id'], 'name' => $n['name']]);
                $cur = $n['pid'];
            }
            $crumbs = array_merge($crumbs, $chain);
        }

        $ids = [];
        $q   = trim($q);
        if ($q !== '') {
            foreach ($this->nodes as $n) {
                if ($n['deleted'] && ! $withTrash) {
                    continue;
                }
                if (mb_stripos($n['name'], $q) !== false || ($n['desc'] !== null && mb_stripos($n['desc'], $q) !== false)) {
                    $ids[] = $n['id'];
                    if (count($ids) >= 300) {
                        break;
                    }
                }
            }
        } else {
            foreach ($this->children[$parent ?? 0] ?? [] as $id) {
                if ($this->nodes[$id]['deleted'] && ! $withTrash) {
                    continue;
                }
                $ids[] = $id;
            }
        }

        $sizes = $this->folderSizes($withTrash);
        $items = [];
        foreach ($ids as $id) {
            $n   = $this->nodes[$id];
            $row = [
                'id'          => $n['id'],
                'type'        => $n['type'],
                'name'        => $n['name'],
                'size'        => $n['type'] === 'folder' ? ($sizes[$id] ?? 0) : $n['size'],
                'owner_name'  => $n['owner'] !== null ? ($this->users[$n['owner']] ?? ('#' . $n['owner'])) : null,
                'private'     => $n['private'],
                'locked'      => $n['locked'],
                'deleted'     => $n['deleted'],
                'updated_at'  => $n['updated'] ? date('c', strtotime((string) $n['updated'])) : null,
                'description' => $n['desc'],
            ];
            if ($n['type'] === 'file') {
                $row['available'] = $this->fileAvailable($n);
                $row['inline']    = $this->storage->isInlineSafe(['mime' => $n['mime']]);
            }
            if ($q !== '') {
                $row['path'] = $n['pid'] === null ? '/' : $this->pathOf($n['pid']);
            }
            $items[] = $row;
        }
        usort($items, static function ($a, $b) {
            return ($a['type'] === 'folder' ? 0 : 1) <=> ($b['type'] === 'folder' ? 0 : 1)
                ?: strnatcasecmp($a['name'], $b['name']);
        });

        return ['breadcrumb' => $crumbs, 'items' => $items, 'search' => $q !== '' ? $q : null];
    }

    /** Unduh / lihat satu berkas dari arsip (tanpa memulihkan). */
    public function fileResponse(int $id, bool $inline): ZipEntryResponse
    {
        $n = $this->node($id);
        if ($n['type'] !== 'file') {
            throw new FileException('Hanya berkas yang dapat diunduh dari arsip.', 422);
        }
        $entry = $this->entryFor($n['sn']);
        if ($entry === null) {
            throw new FileException('Referensi berkas di arsip tidak valid.', 422);
        }
        $mime = preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#i', $n['mime']) ? $n['mime'] : 'application/octet-stream';
        $opts = ['inline' => false];
        if ($inline && $this->storage->isInlineSafe(['mime' => $mime])) {
            $opts = ['inline' => true, 'csp' => $this->storage->inlineCsp()];
        } elseif (preg_match('#^(text/html|application/xhtml|image/svg|text/xml|application/xml)#i', $mime)) {
            $mime = 'application/octet-stream';
        }

        return ZipEntryResponse::make($this->path, $entry, $n['name'] !== '' ? $n['name'] : 'berkas', $mime, $opts);
    }

    // ------------------------------------------------------------------
    // Ekstrak ke folder khusus
    // ------------------------------------------------------------------

    /**
     * Rencana ekstraksi (tanpa efek samping): antrean item berurutan (induk sebelum anak) + total.
     *
     * @param  int[] $ids
     * @return array{queue:list<array{0:int,1:?int}>,files:int,folders:int,bytes:int,missing:int}
     */
    public function plan(array $ids, bool $withTrash): array
    {
        $this->index();
        $sel = [];
        foreach ($ids as $i) {
            $i = (int) $i;
            if ($i > 0 && isset($this->nodes[$i]) && ($withTrash || ! $this->nodes[$i]['deleted'])) {
                $sel[$i] = true;
            }
        }
        if ($sel === []) {
            throw new FileException('Pilih minimal satu item dari arsip.', 422);
        }

        $queue = [];
        $files = $folders = $bytes = $missing = 0;
        foreach (array_keys($sel) as $rootId) {
            // lewati bila leluhurnya ikut dipilih (akan ikut terekstrak bersama leluhur)
            $cur   = $this->nodes[$rootId]['pid'];
            $skip  = false;
            $guard = 0;
            while ($cur !== null && isset($this->nodes[$cur]) && $guard++ < 200) {
                if (isset($sel[$cur])) {
                    $skip = true;
                    break;
                }
                $cur = $this->nodes[$cur]['pid'];
            }
            if ($skip) {
                continue;
            }
            $stack = [[$rootId, null]];
            $seen  = 0;
            while ($stack !== [] && $seen++ < 2000000) {
                [$id, $parentAid] = array_pop($stack);
                $n = $this->nodes[$id];
                if ($n['deleted'] && ! $withTrash) {
                    continue;
                }
                $queue[] = [$id, $parentAid];
                if ($n['type'] === 'folder') {
                    $folders++;
                    foreach (array_reverse($this->children[$id] ?? []) as $c) {
                        $stack[] = [$c, $id];
                    }
                } else {
                    if ($this->fileAvailable($n)) {
                        $files++;
                        $bytes += $n['size'];
                    } else {
                        $missing++;
                    }
                }
            }
        }

        return ['queue' => $queue, 'files' => $files, 'folders' => $folders, 'bytes' => $bytes, 'missing' => $missing];
    }

    private function freeBytes(): ?int
    {
        $f = @disk_free_space($this->storage->filesDir());

        return $f === false ? null : (int) $f;
    }

    /** Pratinjau ekstraksi untuk dialog konfirmasi. */
    public function planSummary(array $ids, bool $withTrash): array
    {
        $p    = $this->plan($ids, $withTrash);
        $free = $this->freeBytes();

        return [
            'files'   => $p['files'], 'folders' => $p['folders'], 'bytes' => $p['bytes'], 'missing' => $p['missing'],
            'free'    => $free, 'enough' => $free === null || $free >= $p['bytes'] + 50 * 1048576,
            'target'  => self::CONTAINER_NAME . ' / ' . $this->subfolderName(),
        ];
    }

    private function subfolderName(): string
    {
        $m    = $this->manifest();
        $ts   = ! empty($m['created_at']) ? strtotime((string) $m['created_at']) : (int) @filemtime($this->path);
        $base = preg_replace('/\.zip$/i', '', $this->name);

        return 'Arsip ' . date('Y-m-d H.i', $ts ?: time()) . ' — ' . $base;
    }

    /** Mulai ekstraksi: buat folder tujuan (pribadi) + berkas job. */
    public function start(array $ids, bool $withTrash, array $actor): array
    {
        $uid = (int) ($actor['id'] ?? 0);
        $p   = $this->plan($ids, $withTrash);
        $free = $this->freeBytes();
        if ($free !== null && $free < $p['bytes'] + 50 * 1048576) {
            throw new FileException('Ruang disk tidak cukup: dibutuhkan ' . FileService::formatBytes($p['bytes']) . ', tersedia ' . FileService::formatBytes($free) . '.', 507);
        }

        $nodes = new NodeModel();
        $now   = date('Y-m-d H:i:s');

        // Folder induk "Pemulihan Arsip": pakai ulang bila milik administrator ini, selain itu buat baru.
        $root = $nodes->where('parent_id', null)->where('deleted_at', null)->where('LOWER(name)', mb_strtolower(self::CONTAINER_NAME))->first();
        if ($root !== null && $root['type'] === 'folder' && (int) $root['owner_id'] === $uid) {
            $rootId = (int) $root['id'];
        } else {
            $rootId = (int) $nodes->insert([
                'parent_id' => null, 'type' => 'folder', 'name' => $nodes->uniqueName(null, self::CONTAINER_NAME, true), 'size' => 0,
                'owner_id' => $uid, 'visibility' => 'private',
                'description' => 'Hasil ekstrak dari arsip backup. Folder pribadi: hanya terlihat oleh Anda.',
                'created_at' => $now, 'updated_at' => $now,
            ], true);
        }
        $subName = $this->storage->sanitizeName($this->subfolderName(), true);
        $targetId = (int) $nodes->insert([
            'parent_id' => $rootId, 'type' => 'folder', 'name' => $nodes->uniqueName($rootId, $subName, true), 'size' => 0,
            'owner_id' => $uid, 'visibility' => 'private',
            'description' => 'Diekstrak dari ' . $this->name . ' pada ' . $now . '.',
            'created_at' => $now, 'updated_at' => $now,
        ], true);

        $jobId = bin2hex(random_bytes(16));
        $state = [
            'uid' => $uid, 'backup' => $this->name, 'target' => $targetId, 'trash' => $withTrash,
            'queue' => $p['queue'], 'pos' => 0, 'map' => new \stdClass(),
            'total_files' => $p['files'], 'total_folders' => $p['folders'], 'total_bytes' => $p['bytes'],
            'files_done' => 0, 'folders_done' => 0, 'bytes_done' => 0, 'skipped' => [], 'started' => time(),
        ];
        if (file_put_contents(self::jobPath($this->storage, $jobId), json_encode($state, JSON_UNESCAPED_UNICODE)) === false) {
            throw new FileException('Gagal membuat berkas kerja ekstraksi.', 500);
        }

        return ['job' => $jobId, 'target_id' => $targetId, 'total_files' => $p['files'], 'total_folders' => $p['folders'], 'total_bytes' => $p['bytes'], 'missing' => $p['missing']];
    }

    public static function jobPath(Storage $storage, string $jobId): string
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            throw new FileException('ID proses tidak valid.', 422);
        }

        return $storage->tmpDir() . DIRECTORY_SEPARATOR . 'extract-' . $jobId . '.json';
    }

    /**
     * Jalankan satu batch (dibatasi waktu agar aman di shared hosting). Dipanggil berulang oleh browser.
     *
     * @return array<string,mixed>
     */
    public function step(string $jobId, array $actor): array
    {
        $path = self::jobPath($this->storage, $jobId);
        $fh   = is_file($path) ? @fopen($path, 'c+') : false; // 'c+' akan MEMBUAT berkas bila belum ada, jadi cek dulu
        if ($fh === false) {
            throw new FileException('Proses ekstraksi tidak ditemukan (sudah selesai atau dibatalkan).', 404);
        }
        if (! flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            throw new FileException('Batch sebelumnya masih berjalan. Tunggu sebentar.', 409);
        }

        try {
            $raw = stream_get_contents($fh);
            $s   = json_decode((string) $raw, true);
            if (! is_array($s) || (int) ($s['uid'] ?? 0) !== (int) ($actor['id'] ?? -1) || ($s['backup'] ?? '') !== $this->name) {
                throw new FileException('Proses ekstraksi tidak ditemukan.', 404);
            }

            $this->index(); // extractOne() membaca dari indeks arsip
            @set_time_limit(60);
            $maxExec = (int) ini_get('max_execution_time');
            $budget  = $maxExec > 0 ? max(2.0, min(8.0, $maxExec * 0.4)) : 8.0;
            $t0      = microtime(true);
            $nodes   = new NodeModel();
            $total   = count($s['queue']);
            $map     = (array) $s['map'];
            $err     = null;

            try {
                while ($s['pos'] < $total && (microtime(true) - $t0) < $budget) {
                    [$aid, $parentAid] = $s['queue'][$s['pos']];
                    $this->extractOne($nodes, $s, $map, (int) $aid, $parentAid !== null ? (int) $parentAid : null, (int) $actor['id']);
                    $s['pos']++;
                }
            } catch (\Throwable $e) {
                $err = $e instanceof FileException ? $e->getMessage() : 'Galat saat mengekstrak: ' . $e->getMessage();
                log_message('error', 'Ekstrak arsip: ' . $e->getMessage());
            }
            $s['map'] = $map === [] ? new \stdClass() : $map;

            $finished = $err === null && $s['pos'] >= $total;
            if ($finished) {
                ActivityLogModel::record('archive_extract', $actor, (int) $s['target'], $this->name, [
                    'files' => $s['files_done'], 'folders' => $s['folders_done'], 'bytes' => $s['bytes_done'], 'skipped' => count($s['skipped']),
                ]);
            }

            $out = [
                'finished' => $finished, 'error' => $err,
                'files_done' => $s['files_done'], 'folders_done' => $s['folders_done'], 'bytes_done' => $s['bytes_done'],
                'total_files' => $s['total_files'], 'total_folders' => $s['total_folders'], 'total_bytes' => $s['total_bytes'],
                'skipped' => array_slice($s['skipped'], 0, 50), 'skipped_count' => count($s['skipped']),
                'target_id' => (int) $s['target'],
            ];

            if ($finished) {
                flock($fh, LOCK_UN);
                fclose($fh);
                @unlink($path);

                return $out;
            }
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($s, JSON_UNESCAPED_UNICODE));
            fflush($fh);

            return $out;
        } finally {
            if (is_resource($fh)) {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }
    }

    public function cancel(string $jobId, array $actor): void
    {
        $path = self::jobPath($this->storage, $jobId);
        $s    = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
        if (is_array($s) && (int) ($s['uid'] ?? 0) === (int) ($actor['id'] ?? -1)) {
            @unlink($path);
        }
    }

    private function extractOne(NodeModel $nodes, array &$s, array &$map, int $aid, ?int $parentAid, int $uid): void
    {
        $n = $this->nodes[$aid] ?? null;
        if ($n === null) {
            $s['skipped'][] = '#' . $aid . ' (tidak ada di indeks arsip)';

            return;
        }
        $parentNew = $parentAid === null ? (int) $s['target'] : ($map[$parentAid] ?? null);
        if ($parentNew === null) {
            $s['skipped'][] = $n['name'] . ' (induk tidak terekstrak)';

            return;
        }
        $isFolder = $n['type'] === 'folder';
        try {
            $name = $this->storage->sanitizeName($n['name'], $isFolder);
        } catch (FileException $e) {
            $s['skipped'][] = $n['name'] . ' (' . $e->getMessage() . ')';
            if ($isFolder) {
                // anak-anaknya tidak punya induk -> akan ikut dilewati
            }

            return;
        }
        $name = $nodes->uniqueName($parentNew, $name, $isFolder);
        $now  = date('Y-m-d H:i:s');
        $created = $n['created'] ?: $now;
        $updated = $n['updated'] ?: $now;
        $desc = $n['desc'] !== null ? mb_substr($n['desc'], 0, 500) : null;
        $vis  = $n['private'] ? 'private' : 'shared';

        if ($isFolder) {
            $map[$aid] = (int) $nodes->insert([
                'parent_id' => $parentNew, 'type' => 'folder', 'name' => $name, 'size' => 0, 'owner_id' => $uid,
                'visibility' => $vis, 'description' => $desc, 'created_at' => $created, 'updated_at' => $updated,
            ], true);
            $s['folders_done']++;

            return;
        }

        $entry = $this->entryFor($n['sn']);
        if ($entry === null || ($st = $this->zip()->statName($entry)) === false) {
            $s['skipped'][] = $n['name'] . ' (isi berkas tidak ada di backup)';

            return;
        }
        $in = $this->zip()->getStream($entry);
        if ($in === false) {
            $s['skipped'][] = $n['name'] . ' (gagal dibaca dari backup)';

            return;
        }
        $storageName = $this->storage->newStorageName();
        $dest        = $this->storage->pathFor($storageName);
        if (! is_dir(dirname($dest)) && ! @mkdir(dirname($dest), 0775, true) && ! is_dir(dirname($dest))) {
            fclose($in);
            throw new FileException('Gagal membuat folder penyimpanan.', 500);
        }
        $out = @fopen($dest, 'wb');
        if ($out === false) {
            fclose($in);
            throw new FileException('Gagal menulis berkas ke penyimpanan.', 500);
        }
        $copied = stream_copy_to_stream($in, $out);
        fclose($in);
        $okClose = fclose($out);
        if ($copied === false || ! $okClose || $copied !== (int) $st['size']) {
            @unlink($dest);
            throw new FileException('Gagal menyalin "' . $n['name'] . '" (ruang disk penuh?). Proses dihentikan; yang sudah terekstrak tetap ada.', 507);
        }

        $mime = preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#i', $n['mime']) ? $n['mime'] : 'application/octet-stream';
        $nodes->insert([
            'parent_id' => $parentNew, 'type' => 'file', 'name' => $name, 'storage_name' => $storageName, 'mime' => $mime,
            'ext' => $this->storage->extensionOf($name), 'size' => $copied, 'owner_id' => $uid, 'visibility' => $vis,
            'description' => $desc, 'created_at' => $created, 'updated_at' => $updated,
        ]);
        $s['files_done']++;
        $s['bytes_done'] += $copied;
    }

    public function __destruct()
    {
        if ($this->zip !== null) {
            @$this->zip->close();
        }
    }
}
