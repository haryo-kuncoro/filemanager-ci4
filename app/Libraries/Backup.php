<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Backup & pemulihan seluruh data (database + berkas fisik) dalam satu ZIP.
 *
 *   manifest.json            metadata (format, versi skema, jumlah baris, dst.)
 *   database/<tabel>.jsonl   satu baris JSON per baris tabel
 *   files/ab/cd/<32 hex>     isi berkas persis seperti di WRITEPATH
 *
 * Murni PHP (ext-zip); tidak memakai mysqldump/exec sehingga aman di shared hosting.
 * Hasil backup memuat hash kata sandi & seluruh data: hanya administrator yang boleh mengakses.
 */
class Backup
{
    public const FORMAT = 1;

    /** Urutan dependensi (induk dulu). */
    public const TABLES = ['roles', 'permissions', 'role_permissions', 'users', 'nodes', 'shares', 'activity_logs', 'settings'];

    private BaseConnection $db;

    public function __construct(private Storage $storage)
    {
        $this->db = Database::connect();
    }

    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    public function dir(): string
    {
        $d = $this->storage->base() . DIRECTORY_SEPARATOR . 'backups';
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,98}\.zip$/', $name) && ! str_contains($name, '..');
    }

    public function path(string $name): string
    {
        if (! self::validName($name)) {
            throw new FileException('Nama backup tidak valid.', 422);
        }
        $p = $this->dir() . DIRECTORY_SEPARATOR . $name;
        if (! is_file($p)) {
            throw new FileException('Backup tidak ditemukan.', 404);
        }

        return $p;
    }

    // ------------------------------------------------------------------
    // Daftar
    // ------------------------------------------------------------------

    /** @return list<array{name:string,size:int,mtime:int,manifest:?array}> terbaru dulu */
    public function all(): array
    {
        $out = [];
        foreach (glob($this->dir() . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $f) {
            $name = basename($f);
            if (! self::validName($name)) {
                continue;
            }
            $out[] = ['name' => $name, 'size' => (int) filesize($f), 'mtime' => (int) filemtime($f), 'manifest' => $this->readManifest($f)];
        }
        usort($out, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $out;
    }

    public function readManifest(string $zipPath): ?array
    {
        if (! self::available()) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        $raw = $zip->getFromName('manifest.json');
        $zip->close();
        $m = $raw !== false ? json_decode($raw, true) : null;

        return is_array($m) && ($m['format'] ?? null) === self::FORMAT && ($m['app'] ?? '') === 'filemanager-ci4' ? $m : null;
    }

    // ------------------------------------------------------------------
    // Buat backup
    // ------------------------------------------------------------------

    /** @return array{name:string,size:int,manifest:array} */
    public function create(?array $actor = null, string $prefix = 'backup'): array
    {
        if (! self::available()) {
            throw new FileException('Ekstensi PHP zip tidak tersedia di server ini, sehingga backup tidak dapat dibuat.', 500);
        }
        @set_time_limit(0);
        @ignore_user_abort(true);

        $name = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.zip';
        $final = $this->dir() . DIRECTORY_SEPARATOR . $name;
        $part  = $final . '.part';
        $tmp   = [];

        $zip = new \ZipArchive();
        if ($zip->open($part, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new FileException('Gagal membuat berkas backup.', 500);
        }

        try {
            // ---- database ----
            $counts = [];
            foreach (self::TABLES as $table) {
                if (! $this->db->tableExists($table)) {
                    continue;
                }
                $file = $this->storage->tmpDir() . DIRECTORY_SEPARATOR . 'bk_' . bin2hex(random_bytes(6)) . '.jsonl';
                $tmp[] = $file;
                $counts[$table] = $this->dumpTable($table, $file);
                $zip->addFile($file, 'database/' . $table . '.jsonl');
            }

            // ---- berkas fisik ----
            $files = 0;
            $bytes = 0;
            $missing = 0;
            $seen = [];
            $q = $this->db->table('nodes')->select('storage_name')->where('storage_name IS NOT NULL', null, false)->get();
            foreach ($q->getResultArray() as $r) {
                $sn = (string) $r['storage_name'];
                if (isset($seen[$sn]) || ! Storage::validStorageName($sn)) {
                    continue;
                }
                $seen[$sn] = true;
                $path = $this->storage->pathFor($sn);
                if (! is_file($path)) {
                    $missing++;
                    continue;
                }
                $entry = 'files/' . substr($sn, 0, 2) . '/' . substr($sn, 2, 2) . '/' . $sn;
                $zip->addFile($path, $entry);
                $zip->setCompressionName($entry, \ZipArchive::CM_STORE); // cepat; kebanyakan berkas sudah terkompresi
                $files++;
                $bytes += (int) filesize($path);
            }

            $manifest = [
                'format'     => self::FORMAT,
                'app'        => 'filemanager-ci4',
                'schema'     => Installer::latestMigration(),
                'created_at' => date('c'),
                'created_by' => $actor['username'] ?? null,
                'tables'     => $counts,
                'files'      => $files,
                'files_bytes' => $bytes,
                'files_missing' => $missing,
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            if (! $zip->close()) {
                throw new FileException('Gagal menyelesaikan berkas backup (ruang disk penuh?).', 500);
            }
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($part);
            foreach ($tmp as $t) {
                @unlink($t);
            }
            throw $e;
        }

        foreach ($tmp as $t) {
            @unlink($t);
        }
        if (! @rename($part, $final)) {
            @unlink($part);
            throw new FileException('Gagal menyimpan berkas backup.', 500);
        }

        return ['name' => $name, 'size' => (int) filesize($final), 'manifest' => $manifest];
    }

    private function dumpTable(string $table, string $file): int
    {
        $fh = fopen($file, 'wb');
        if ($fh === false) {
            throw new FileException('Gagal menulis berkas sementara backup.', 500);
        }
        $fields = $this->db->getFieldNames($table);
        $order  = $fields[0] ?? null;
        $offset = 0;
        $n      = 0;
        do {
            $b = $this->db->table($table);
            if ($order !== null) {
                $b->orderBy($order, 'ASC');
                if (in_array('permission_id', $fields, true)) {
                    $b->orderBy('permission_id', 'ASC');
                }
            }
            $rows = $b->limit(1000, $offset)->get()->getResultArray();
            foreach ($rows as $row) {
                fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
                $n++;
            }
            $offset += 1000;
        } while (count($rows) === 1000);
        fclose($fh);

        return $n;
    }

    // ------------------------------------------------------------------
    // Pulihkan
    // ------------------------------------------------------------------

    /**
     * Pulihkan dari backup: SELURUH data saat ini diganti. Sebelumnya dibuat backup pengaman otomatis.
     *
     * @return array{rows:int,files:int,safety:?string}
     */
    public function restore(string $name, ?array $actor = null, bool $safety = true): array
    {
        if (! self::available()) {
            throw new FileException('Ekstensi PHP zip tidak tersedia di server ini.', 500);
        }
        @set_time_limit(0);
        @ignore_user_abort(true);

        $path = $this->path($name);
        $m    = $this->readManifest($path);
        if ($m === null) {
            throw new FileException('Berkas bukan backup File Manager yang valid atau formatnya tidak didukung.', 422);
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new FileException('Gagal membuka berkas backup.', 422);
        }

        try {
            // Pastikan semua tabel wajib ada di dalam arsip sebelum menyentuh apa pun.
            foreach (['roles', 'permissions', 'role_permissions', 'users', 'nodes'] as $t) {
                if ($zip->locateName('database/' . $t . '.jsonl') === false) {
                    throw new FileException('Backup tidak lengkap: tabel "' . $t . '" tidak ada.', 422);
                }
            }

            $safetyName = null;
            if ($safety) {
                $safetyName = $this->create($actor, 'backup-sebelum-pulih')['name'];
            }

            // 1) Berkas fisik (aditif; nama acak sehingga tidak bentrok dengan data lain).
            $files = $this->extractFiles($zip);

            // 2) Database (satu transaksi; bila gagal data lama utuh).
            $rows = $this->restoreDatabase($zip);
        } finally {
            $zip->close();
        }

        // 3) Rapikan: hapus berkas fisik yatim & cache thumbnail.
        $this->sweepOrphans();

        return ['rows' => $rows, 'files' => $files, 'safety' => $safetyName ?? null];
    }

    private function extractFiles(\ZipArchive $zip): int
    {
        $n = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (! preg_match('#^files/([0-9a-f]{2})/([0-9a-f]{2})/([0-9a-f]{32})$#', $entry, $m)
                || $m[1] !== substr($m[3], 0, 2) || $m[2] !== substr($m[3], 2, 2)) {
                continue; // hanya pola ketat; tidak ada path dari arsip yang dipakai langsung
            }
            $dest = $this->storage->pathFor($m[3]);
            $stat = $zip->statIndex($i);
            if (is_file($dest) && (int) filesize($dest) === (int) ($stat['size'] ?? -1)) {
                $n++;
                continue;
            }
            $in = $zip->getStream($entry);
            if ($in === false) {
                throw new FileException('Gagal membaca "' . $entry . '" dari backup.', 500);
            }
            $tmp = $this->storage->tmpDir() . DIRECTORY_SEPARATOR . 'rs_' . bin2hex(random_bytes(6));
            $out = fopen($tmp, 'wb');
            if ($out === false) {
                fclose($in);
                throw new FileException('Gagal menulis berkas sementara (ruang disk penuh?).', 500);
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            if (! $this->storage->adopt($tmp, $m[3])) {
                @unlink($tmp);
                throw new FileException('Gagal menyimpan berkas hasil pemulihan.', 500);
            }
            $n++;
        }

        return $n;
    }

    private function restoreDatabase(\ZipArchive $zip): int
    {
        $db     = $this->db;
        $driver = strtolower((string) $db->DBDriver);
        $total  = 0;

        if ($driver === 'sqlite3') {
            $db->query('PRAGMA foreign_keys = OFF');
        } else {
            $db->query('SET FOREIGN_KEY_CHECKS = 0');
        }

        try {
            $db->transException(true)->transStart();
            foreach (array_reverse(self::TABLES) as $t) {
                // Backup lama (sebelum ada tabel settings) tidak boleh menghapus pengaturan yang ada.
                if ($t === 'settings' && $zip->locateName('database/settings.jsonl') === false) {
                    continue;
                }
                if ($db->tableExists($t)) {
                    $db->table($t)->emptyTable(); // DELETE FROM (aman dalam transaksi)
                }
            }

            foreach (self::TABLES as $t) {
                $idx = $zip->locateName('database/' . $t . '.jsonl');
                if ($idx === false || ! $db->tableExists($t)) {
                    continue;
                }
                $cols = array_flip($db->getFieldNames($t));
                $in   = $zip->getStream('database/' . $t . '.jsonl');
                if ($in === false) {
                    throw new FileException('Gagal membaca tabel "' . $t . '" dari backup.', 500);
                }
                $batch = [];
                while (($line = fgets($in)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $row = json_decode($line, true);
                    if (! is_array($row)) {
                        fclose($in);
                        throw new FileException('Baris data rusak pada tabel "' . $t . '".', 422);
                    }
                    $row = array_intersect_key($row, $cols);
                    if ($row === []) {
                        continue;
                    }
                    $batch[] = $row;
                    $total++;
                    if (count($batch) >= 200) {
                        $this->insertRows($t, $batch);
                        $batch = [];
                    }
                }
                fclose($in);
                if ($batch !== []) {
                    $this->insertRows($t, $batch);
                }
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e instanceof FileException ? $e : new FileException('Pemulihan database gagal: ' . $e->getMessage(), 500);
        } finally {
            $db->transException(false);
            if ($driver === 'sqlite3') {
                $db->query('PRAGMA foreign_keys = ON');
            } else {
                $db->query('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        return $total;
    }

    /** Baris dalam satu batch bisa berbeda kolomnya bila backup lama; sertakan semua kolom yang ada. */
    private function insertRows(string $table, array $rows): void
    {
        $keys = [];
        foreach ($rows as $r) {
            foreach ($r as $k => $_) {
                $keys[$k] = true;
            }
        }
        $keys = array_keys($keys);
        $norm = [];
        foreach ($rows as $r) {
            $x = [];
            foreach ($keys as $k) {
                $x[$k] = $r[$k] ?? null;
            }
            $norm[] = $x;
        }
        $this->db->table($table)->insertBatch($norm);
    }

    /** Hapus berkas fisik yang tidak dirujuk node mana pun + seluruh cache thumbnail. */
    public function sweepOrphans(): int
    {
        $known = [];
        foreach ($this->db->table('nodes')->select('storage_name')->where('storage_name IS NOT NULL', null, false)->get()->getResultArray() as $r) {
            $known[(string) $r['storage_name']] = true;
        }
        $removed = 0;
        $root = $this->storage->filesDir();
        if (is_dir($root)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                /** @var \SplFileInfo $f */
                if ($f->isFile() && Storage::validStorageName($f->getFilename()) && ! isset($known[$f->getFilename()])) {
                    if (@unlink($f->getPathname())) {
                        $removed++;
                    }
                }
            }
        }
        foreach (glob($this->storage->thumbDir() . DIRECTORY_SEPARATOR . '*') ?: [] as $t) {
            if (is_file($t)) {
                @unlink($t);
            }
        }

        return $removed;
    }

    // ------------------------------------------------------------------
    // Hapus & unggah
    // ------------------------------------------------------------------

    public function delete(string $name): void
    {
        @unlink($this->path($name));
    }

    /**
     * Terima potongan unggahan berkas backup. Selesai -> divalidasi lalu disimpan.
     *
     * @return array{done:bool,received:int,resync?:bool,name?:string}
     */
    public function acceptChunk(int $uid, string $uploadId, int $offset, int $total, string $chunkPath, int $chunkSize, int $maxBytes): array
    {
        if (! preg_match('/^[a-f0-9]{16,64}$/', $uploadId)) {
            throw new FileException('ID upload tidak valid.', 422);
        }
        if ($total <= 0 || $total > $maxBytes) {
            throw new FileException('Ukuran backup tidak valid atau melebihi batas.', 413);
        }
        $part = $this->storage->tmpDir() . DIRECTORY_SEPARATOR . 'bk_up_' . $uid . '_' . $uploadId;
        clearstatcache(true, $part);
        $have = is_file($part) ? (int) filesize($part) : 0;
        if ($offset !== $have) {
            return ['done' => false, 'received' => $have, 'resync' => true];
        }
        if ($have + $chunkSize > $total) {
            @unlink($part);
            throw new FileException('Data melebihi ukuran yang dinyatakan.', 422);
        }
        $in  = fopen($chunkPath, 'rb');
        $out = fopen($part, 'ab');
        if ($in === false || $out === false) {
            throw new FileException('Gagal menulis berkas sementara.', 500);
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        clearstatcache(true, $part);
        $got = (int) filesize($part);
        if ($got < $total) {
            return ['done' => false, 'received' => $got];
        }

        if ($this->readManifest($part) === null) {
            @unlink($part);
            throw new FileException('Berkas yang diunggah bukan backup File Manager yang valid.', 422);
        }
        $name = 'backup-unggahan-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.zip';
        if (! @rename($part, $this->dir() . DIRECTORY_SEPARATOR . $name)) {
            if (! @copy($part, $this->dir() . DIRECTORY_SEPARATOR . $name)) {
                throw new FileException('Gagal menyimpan backup.', 500);
            }
            @unlink($part);
        }

        return ['done' => true, 'received' => $got, 'name' => $name];
    }
}
