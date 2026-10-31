<?php

namespace App\Libraries;

use Config\Filemanager;

/**
 * Penyimpanan fisik di WRITEPATH/filemanager.
 *
 *   files/ab/cd/<32 hex>   isi berkas (nama acak, tanpa ekstensi)
 *   tmp/                   potongan upload & ZIP sementara
 *   thumbs/                cache thumbnail gambar
 *
 * Nama asli, hierarki, dan pemilik hanya ada di database, sehingga tidak ada
 * celah path traversal dari nama berkas yang dikirim pengguna.
 */
class Storage
{
    public function __construct(private Filemanager $cfg)
    {
        $this->ensureDirs();
    }

    // ------------------------------------------------------------------
    // Direktori & path
    // ------------------------------------------------------------------

    public function base(): string
    {
        return rtrim($this->cfg->storagePath, '/\\');
    }

    public function filesDir(): string
    {
        return $this->base() . DIRECTORY_SEPARATOR . 'files';
    }

    public function tmpDir(): string
    {
        return $this->base() . DIRECTORY_SEPARATOR . 'tmp';
    }

    public function thumbDir(): string
    {
        return $this->base() . DIRECTORY_SEPARATOR . 'thumbs';
    }

    public function ensureDirs(): void
    {
        foreach ([$this->base(), $this->filesDir(), $this->tmpDir(), $this->thumbDir()] as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        // Lapisan pengaman tambahan bila folder WRITEPATH suatu saat terekspos web server.
        $guard = $this->base() . DIRECTORY_SEPARATOR . '.htaccess';
        if (! is_file($guard)) {
            @file_put_contents($guard, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
        $idx = $this->base() . DIRECTORY_SEPARATOR . 'index.html';
        if (! is_file($idx)) {
            @file_put_contents($idx, '<!doctype html><title>403</title>');
        }
    }

    public function newStorageName(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function validStorageName(?string $name): bool
    {
        return $name !== null && (bool) preg_match('/^[a-f0-9]{32}$/', $name);
    }

    /** Path absolut isi berkas. Melempar bila storage_name tidak valid. */
    public function pathFor(string $storageName): string
    {
        if (! self::validStorageName($storageName)) {
            throw new FileException('Referensi berkas tidak valid.', 500);
        }

        return $this->filesDir() . DIRECTORY_SEPARATOR . substr($storageName, 0, 2)
            . DIRECTORY_SEPARATOR . substr($storageName, 2, 2) . DIRECTORY_SEPARATOR . $storageName;
    }

    public function exists(string $storageName): bool
    {
        return is_file($this->pathFor($storageName));
    }

    /** Pindahkan berkas sementara ke lokasi akhir. */
    public function adopt(string $sourcePath, string $storageName): bool
    {
        $dest = $this->pathFor($storageName);
        $dir  = dirname($dest);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }
        if (@rename($sourcePath, $dest)) {
            return true;
        }
        // rename lintas filesystem bisa gagal -> salin lalu hapus
        if (@copy($sourcePath, $dest)) {
            @unlink($sourcePath);

            return true;
        }

        return false;
    }

    /** Duplikasi isi berkas; mengembalikan storage_name baru. */
    public function duplicate(string $storageName): string
    {
        $src = $this->pathFor($storageName);
        if (! is_file($src)) {
            throw new FileException('Berkas fisik tidak ditemukan di penyimpanan.', 404);
        }
        $new  = $this->newStorageName();
        $dest = $this->pathFor($new);
        if (! is_dir(dirname($dest))) {
            @mkdir(dirname($dest), 0775, true);
        }
        if (! @copy($src, $dest)) {
            throw new FileException('Gagal menyalin berkas.', 500);
        }

        return $new;
    }

    public function delete(?string $storageName): void
    {
        if (! self::validStorageName($storageName)) {
            return;
        }
        @unlink($this->pathFor($storageName));
        @unlink($this->thumbPath($storageName));
    }

    // ------------------------------------------------------------------
    // Nama berkas
    // ------------------------------------------------------------------

    /**
     * Bersihkan & validasi nama file/folder yang diketik pengguna.
     *
     * @throws FileException
     */
    public function sanitizeName(string $name, bool $isFolder = false): string
    {
        $name = str_replace("\0", '', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            throw new FileException('Nama tidak boleh kosong.', 422);
        }
        if (preg_match('#[/\\\\]#', $name)) {
            throw new FileException('Nama tidak boleh mengandung karakter / atau \\.', 422);
        }
        if (mb_strlen($name) > 255) {
            throw new FileException('Nama terlalu panjang (maksimal 255 karakter).', 422);
        }
        if (! $isFolder && $this->isBlocked($name)) {
            throw new FileException('Jenis berkas ini tidak diizinkan (' . $this->extensionOf($name) . ').', 422);
        }

        return $name;
    }

    /** Untuk nama dari browser saat upload: ambil bagian nama saja, lalu sanitasi. */
    public function sanitizeUploadName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);

        return $this->sanitizeName($name);
    }

    public function extensionOf(string $name): string
    {
        return mb_strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    public function isBlocked(string $name): bool
    {
        $lower = mb_strtolower(trim($name));
        $parts = explode('.', ltrim($lower, '.'));
        $final = $this->extensionOf($name);

        // Ekstensi akhir yang dilarang (".htaccess" dianggap berekstensi "htaccess").
        if (in_array($final, $this->cfg->blockedExtensions, true) || in_array(ltrim($lower, '.'), $this->cfg->blockedExtensions, true)) {
            return true;
        }

        // Ekstensi ganda ala "shell.php.jpg" (dieksekusi oleh konfigurasi Apache tertentu).
        $execSegments = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'cgi', 'asp', 'aspx', 'jsp'];
        array_shift($parts); // nama dasar
        array_pop($parts);   // ekstensi akhir (sudah diperiksa)
        foreach ($parts as $segment) {
            if (in_array($segment, $execSegments, true)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Tipe berkas
    // ------------------------------------------------------------------

    public function detectMime(string $path, string $name): string
    {
        $mime = '';
        if (function_exists('finfo_open') && ($f = finfo_open(FILEINFO_MIME_TYPE)) !== false) {
            $mime = (string) finfo_file($f, $path);
            finfo_close($f);
        }

        $ext = $this->extensionOf($name);
        if ($mime === '' || $mime === 'application/octet-stream' || $mime === 'text/plain' || $mime === 'application/x-empty') {
            $byExt = [
                'svg' => 'image/svg+xml', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg',
                'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4',
                'pdf' => 'application/pdf', 'json' => 'application/json', 'csv' => 'text/csv',
                'md' => 'text/markdown', 'txt' => 'text/plain', 'webp' => 'image/webp',
            ];
            if (isset($byExt[$ext])) {
                return $byExt[$ext];
            }
        }
        if ($mime === 'image/svg+xml' && $ext !== 'svg') {
            // jangan percaya SVG yang menyamar
            return 'application/octet-stream';
        }

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    /** image | pdf | video | audio | text | null */
    public function previewKind(array $node): ?string
    {
        if (($node['type'] ?? '') !== 'file') {
            return null;
        }
        $mime = (string) ($node['mime'] ?? '');
        $ext  = (string) ($node['ext'] ?? '');

        if (in_array($mime, $this->cfg->inlineMimes, true)) {
            return match (true) {
                str_starts_with($mime, 'image/') => 'image',
                $mime === 'application/pdf'      => 'pdf',
                str_starts_with($mime, 'video/') => 'video',
                str_starts_with($mime, 'audio/') => 'audio',
                default                          => null,
            };
        }
        if (in_array($ext, $this->cfg->textExtensions, true)) {
            return 'text';
        }

        return null;
    }

    public function isInlineSafe(array $node): bool
    {
        return in_array($node['mime'] ?? '', $this->cfg->inlineMimes, true);
    }

    /** CSP ketat untuk berkas yang disajikan inline (terutama SVG). */
    public function inlineCsp(): string
    {
        return "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox";
    }

    // ------------------------------------------------------------------
    // Batas upload PHP (shared hosting biasanya 2-64 MB)
    // ------------------------------------------------------------------

    /** "64M" / "2G" / "512K" / "1048576" -> byte (0 bila tak terbatas/tak valid). */
    public static function iniBytes(string|false $value): int
    {
        if ($value === false || $value === '' || $value === '-1') {
            return 0;
        }
        $value = trim($value);
        $num   = (float) $value;
        $unit  = strtolower(substr($value, -1));

        return (int) match ($unit) {
            'g'     => $num * 1024 ** 3,
            'm'     => $num * 1024 ** 2,
            'k'     => $num * 1024,
            default => $num,
        };
    }

    /**
     * Ukuran potongan yang aman dikirim browser: tidak melebihi konfigurasi, upload_max_filesize,
     * maupun post_max_size (dikurangi ruang untuk overhead multipart).
     */
    public function effectiveChunkSize(): int
    {
        $limits = [$this->cfg->chunkSize];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $v = self::iniBytes(ini_get($key));
            if ($v > 0) {
                $limits[] = $v;
            }
        }
        $size = (int) floor(min($limits) * 0.85) - 65536;

        return max(65536, min($size, $this->cfg->chunkSize));
    }

    // ------------------------------------------------------------------
    // Thumbnail
    // ------------------------------------------------------------------

    public function thumbPath(string $storageName): string
    {
        return $this->thumbDir() . DIRECTORY_SEPARATOR . $storageName . '.jpg';
    }

    /** Buat (atau ambil dari cache) thumbnail JPEG; null bila tidak bisa. */
    public function thumbnail(array $node): ?string
    {
        if (! extension_loaded('gd') || ! self::validStorageName($node['storage_name'] ?? null)) {
            return null;
        }
        if (! in_array($node['mime'] ?? '', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'], true)) {
            return null;
        }

        $thumb = $this->thumbPath($node['storage_name']);
        if (is_file($thumb)) {
            return $thumb;
        }

        $src = $this->pathFor($node['storage_name']);
        if (! is_file($src) || filesize($src) > $this->cfg->thumbMaxSourceBytes) {
            return null;
        }

        $info = @getimagesize($src);
        if (! $info) {
            return null;
        }
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || ($w * $h * 5) > 256 * 1024 * 1024) { // batas memori kira-kira
            return null;
        }

        $img = match ($node['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/gif'  => @imagecreatefromgif($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            'image/bmp'  => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($src) : false,
            default      => false,
        };
        if (! $img) {
            return null;
        }

        // Koreksi orientasi EXIF untuk JPEG.
        if ($node['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($src);
            $o    = $exif['Orientation'] ?? 1;
            $rot  = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot !== 0 && ($rotated = imagerotate($img, $rot, 0))) {
                $img = $rotated;
                $w   = imagesx($img);
                $h   = imagesy($img);
            }
        }

        $size  = $this->cfg->thumbSize;
        $scale = min($size / $w, $size / $h, 1);
        $tw    = max(1, (int) round($w * $scale));
        $th    = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($tw, $th);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // latar putih untuk PNG transparan
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $tw, $th, $w, $h);

        $ok = imagejpeg($dst, $thumb, 82);
        imagedestroy($img);
        imagedestroy($dst);

        return $ok ? $thumb : null;
    }

    // ------------------------------------------------------------------
    // Pembersihan
    // ------------------------------------------------------------------

    /** Hapus potongan upload / ZIP sementara yang lebih tua dari $maxAgeSeconds. */
    public function cleanTmp(int $maxAgeSeconds = 86400): int
    {
        $removed = 0;
        $now     = time();
        foreach (glob($this->tmpDir() . DIRECTORY_SEPARATOR . '*') ?: [] as $entry) {
            if ($now - (int) @filemtime($entry) < $maxAgeSeconds) {
                continue;
            }
            if (is_dir($entry)) {
                foreach (glob($entry . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($entry);
            } else {
                @unlink($entry);
            }
            $removed++;
        }

        return $removed;
    }
}
