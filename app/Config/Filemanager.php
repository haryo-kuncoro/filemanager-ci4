<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi aplikasi File Manager.
 *
 * Seluruh berkas pengguna disimpan di dalam WRITEPATH (di luar folder public)
 * sehingga tidak pernah dapat diakses langsung lewat URL. Semua akses
 * (unduh, pratinjau, thumbnail, tautan publik) harus melalui controller.
 */
class Filemanager extends BaseConfig
{
    /** Nama aplikasi yang tampil di UI. */
    public string $appName = 'File Manager';

    /** Direktori induk penyimpanan (diisi di constructor: WRITEPATH/filemanager). */
    public string $storagePath = '';

    /** Ukuran maksimum satu berkas (byte). Default 1 GB. */
    public int $maxFileSize = 1073741824;

    /** Ukuran potongan (chunk) upload dari browser (byte). Default 2 MB. */
    public int $chunkSize = 2097152;

    /**
     * Ekstensi yang ditolak saat upload / rename.
     * (Berkas tidak pernah dieksekusi karena berada di WRITEPATH dengan nama acak,
     * tetapi daftar ini mencegah pengguna menyimpan berkas berbahaya untuk dibagikan.)
     *
     * @var list<string>
     */
    public array $blockedExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
        'cgi', 'pl', 'asp', 'aspx', 'jsp', 'exe', 'dll', 'bat', 'cmd', 'com', 'scr',
        'msi', 'vbs', 'sh', 'jar', 'htaccess', 'htpasswd',
    ];

    /**
     * MIME yang boleh ditampilkan inline (pratinjau). Selain ini selalu diunduh.
     *
     * @var list<string>
     */
    public array $inlineMimes = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml',
        'application/pdf',
        'video/mp4', 'video/webm', 'video/ogg',
        'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/aac',
    ];

    /**
     * Ekstensi yang dapat dipratinjau sebagai teks (dibaca lewat JSON, tidak pernah di-render sebagai HTML).
     *
     * @var list<string>
     */
    public array $textExtensions = [
        'txt', 'md', 'markdown', 'json', 'xml', 'csv', 'tsv', 'log', 'ini', 'conf', 'env',
        'yaml', 'yml', 'css', 'js', 'ts', 'sql', 'html', 'htm', 'py', 'java', 'c', 'cpp', 'h',
        'go', 'rs', 'rb', 'sh', 'bat', 'vue', 'jsx', 'tsx', 'scss', 'less',
    ];

    /** Batas byte untuk pratinjau teks. */
    public int $textPreviewMaxBytes = 524288;

    /** Ukuran sisi thumbnail (px). */
    public int $thumbSize = 192;

    /** Batas ukuran sumber gambar yang dibuatkan thumbnail (byte). */
    public int $thumbMaxSourceBytes = 25165824;

    /** Batas percobaan login gagal & lama penguncian (detik). */
    public int $loginMaxAttempts = 5;
    public int $loginLockSeconds = 300;

    /** Batas percobaan kata sandi tautan publik & lama penguncian (detik). */
    public int $shareMaxAttempts = 8;
    public int $shareLockSeconds = 300;

    /** Isi sampah dihapus permanen otomatis oleh `php spark fm:cleanup` setelah N hari. */
    public int $trashRetentionDays = 30;

    /** Panjang minimum kata sandi pengguna. */
    public int $minPasswordLength = 10;

    /** Batas total ukuran satu unduhan ZIP (byte). Default 2 GB. */
    public int $maxZipBytes = 2147483648;

    public function __construct()
    {
        parent::__construct();

        if ($this->storagePath === '') {
            $this->storagePath = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'filemanager';
        }
    }

    /** Ukuran maksimum berkas backup yang boleh diunggah untuk dipulihkan (byte). */
    public int $maxBackupUpload = 8 * 1024 * 1024 * 1024;
}
