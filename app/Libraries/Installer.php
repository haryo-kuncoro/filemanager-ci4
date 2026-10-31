<?php

namespace App\Libraries;

use App\Database\Seeds\InitialSeeder;
use App\Models\RoleModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Pemasangan lewat web untuk shared hosting tanpa SSH / Composer / cron:
 * cek persyaratan, uji koneksi MySQL, jalankan migrasi, isi peran & izin, buat admin,
 * lalu tulis .env dan berkas kunci instalasi.
 */
class Installer
{
    public const LOCK = 'installed.lock';

    public static function lockPath(): string
    {
        return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . self::LOCK;
    }

    /**
     * Sudah terpasang? Berkas kunci adalah sumber kebenaran; bila belum ada tetapi database
     * sudah berisi pengguna (mis. dipasang via `php spark migrate`), kunci dibuat otomatis.
     */
    public static function isInstalled(): bool
    {
        if (is_file(self::lockPath())) {
            return true;
        }

        try {
            $db = Database::connect();
            if ($db->tableExists('users') && $db->table('users')->countAllResults() > 0) {
                self::writeLock();

                return true;
            }
        } catch (\Throwable) {
            // database belum dikonfigurasi
        }

        return false;
    }

    public static function writeLock(): void
    {
        @file_put_contents(self::lockPath(), 'installed ' . date('c') . "\n");
    }

    // ------------------------------------------------------------------
    // Persyaratan
    // ------------------------------------------------------------------

    /**
     * @return list<array{label:string, ok:bool, required:bool, info:string}>
     */
    public function requirements(): array
    {
        $rows = [];
        $add  = static function (string $label, bool $ok, bool $required, string $info = '') use (&$rows): void {
            $rows[] = ['label' => $label, 'ok' => $ok, 'required' => $required, 'info' => $info];
        };

        $add('PHP 8.2 atau lebih baru', version_compare(PHP_VERSION, '8.2.0', '>='), true, 'Terdeteksi ' . PHP_VERSION);
        foreach (['intl' => true, 'mbstring' => true, 'json' => true, 'mysqli' => true, 'fileinfo' => true, 'zip' => false, 'gd' => false, 'exif' => false] as $ext => $required) {
            $why = match ($ext) {
                'zip'  => 'untuk unduh folder/banyak berkas sebagai ZIP',
                'gd'   => 'untuk thumbnail gambar',
                'exif' => 'untuk memutar thumbnail foto sesuai orientasi',
                default => '',
            };
            $add('Ekstensi ' . $ext, extension_loaded($ext), $required, $required ? '' : ($why . ' (opsional)'));
        }

        $writable = rtrim(WRITEPATH, '/\\');
        $add('Folder writable/ dapat ditulis', is_dir($writable) && is_writable($writable), true, $writable);
        $storage = $writable . DIRECTORY_SEPARATOR . 'filemanager';
        $add('Folder penyimpanan berkas dapat dibuat', (is_dir($storage) && is_writable($storage)) || (! is_dir($storage) && is_writable($writable)), true, $storage);
        $add('Berkas .env dapat ditulis', $this->envWritable(), false, 'Jika tidak, isi .env akan ditampilkan untuk Anda salin manual.');

        return $rows;
    }

    public function requirementsMet(): bool
    {
        foreach ($this->requirements() as $r) {
            if ($r['required'] && ! $r['ok']) {
                return false;
            }
        }

        return true;
    }

    public function envPath(): string
    {
        return rtrim(ROOTPATH, '/\\') . DIRECTORY_SEPARATOR . '.env';
    }

    private function envWritable(): bool
    {
        $p = $this->envPath();

        return is_file($p) ? is_writable($p) : is_writable(dirname($p));
    }

    /** Info batas upload PHP + ukuran potongan efektif. */
    public function uploadInfo(): array
    {
        $storage = new Storage(config('Filemanager'));

        return [
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size'       => (string) ini_get('post_max_size'),
            'max_execution_time'  => (string) ini_get('max_execution_time'),
            'chunk'               => $storage->effectiveChunkSize(),
        ];
    }

    // ------------------------------------------------------------------
    // Pemasangan
    // ------------------------------------------------------------------

    /**
     * @param array{app_url:string, https:bool, db_host:string, db_port:int, db_name:string, db_user:string, db_pass:string,
     *              admin_name:string, admin_username:string, admin_email:string, admin_password:string} $d
     *
     * @return array{ok:bool, message:string, env_written:bool, env:string}
     */
    public function install(array $d): array
    {
        // 1) Koneksi database (override konfigurasi default di memori untuk request ini).
        $dbCfg          = config('Database');
        $dbCfg->default = [
            'DSN' => '', 'hostname' => $d['db_host'], 'username' => $d['db_user'], 'password' => $d['db_pass'],
            'database' => $d['db_name'], 'DBDriver' => 'MySQLi', 'DBPrefix' => '', 'pConnect' => false,
            'DBDebug' => true, 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_general_ci', 'swapPre' => '',
            'encrypt' => false, 'compress' => false, 'strictOn' => false, 'failover' => [],
            'port' => (int) $d['db_port'], 'numberNative' => false, 'foreignKeys' => true, 'busyTimeout' => 1000,
            'dateFormat' => ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'],
        ];

        try {
            /** @var BaseConnection $db */
            $db = Database::connect('default', false);
            $db->initialize();
            $db->query('SELECT 1');
        } catch (\Throwable $e) {
            return $this->fail('Tidak dapat terhubung ke database: ' . $e->getMessage());
        }

        // 2) Jangan menimpa instalasi yang sudah berisi pengguna.
        try {
            if ($db->tableExists('users') && $db->table('users')->countAllResults() > 0) {
                return $this->fail('Database ini sudah berisi pengguna. Gunakan database kosong, atau hapus tabel lama terlebih dahulu.');
            }
        } catch (\Throwable) {
            // tabel belum ada: normal
        }

        // 3) Migrasi.
        try {
            $runner = new \CodeIgniter\Database\MigrationRunner(config('Migrations'), $db);
            $runner->setNamespace('App');
            $runner->latest();
        } catch (\Throwable $e) {
            return $this->fail('Migrasi database gagal: ' . $e->getMessage());
        }

        // 4) Peran, izin, dan akun admin.
        try {
            $seeder              = new InitialSeeder($dbCfg, $db);
            $seeder->createAdmin = false;
            $seeder->run();

            $adminRole = (new RoleModel($db))->where('name', 'administrator')->first();
            if ($adminRole === null) {
                return $this->fail('Peran administrator tidak ditemukan setelah seeding.');
            }

            (new UserModel($db))->insert([
                'name'                 => $d['admin_name'],
                'username'             => strtolower($d['admin_username']),
                'email'                => $d['admin_email'] !== '' ? $d['admin_email'] : null,
                'password_hash'        => password_hash($d['admin_password'], PASSWORD_DEFAULT),
                'role_id'              => (int) $adminRole['id'],
                'is_active'            => 1,
                'must_change_password' => 0,
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Gagal mengisi data awal: ' . $e->getMessage());
        }

        // 5) Penyimpanan berkas + .env + kunci.
        (new Storage(config('Filemanager')))->ensureDirs();

        $env     = $this->buildEnv($d);
        $written = false;
        if ($this->envWritable()) {
            $written = @file_put_contents($this->envPath(), $env) !== false;
        }
        self::writeLock();

        return ['ok' => true, 'message' => 'Instalasi selesai.', 'env_written' => $written, 'env' => $env];
    }

    /** Isi .env produksi. Nilai dikutip agar aman untuk karakter khusus. */
    public function buildEnv(array $d): string
    {
        $q = static fn (string $v): string => "'" . str_replace(["\\", "'", "\r", "\n"], ["\\\\", "\\'", '', ''], $v) . "'";

        $url = rtrim($d['app_url'], '/') . '/';
        $out = [
            '#--------------------------------------------------------------------',
            '# Dibuat oleh installer ' . date('Y-m-d H:i'),
            '#--------------------------------------------------------------------',
            'CI_ENVIRONMENT = production',
            '',
            'app.baseURL = ' . $q($url),
            'app.forceGlobalSecureRequests = ' . ($d['https'] ? 'true' : 'false'),
            '',
            'database.default.hostname = ' . $q($d['db_host']),
            'database.default.database = ' . $q($d['db_name']),
            'database.default.username = ' . $q($d['db_user']),
            'database.default.password = ' . $q($d['db_pass']),
            'database.default.DBDriver = MySQLi',
            'database.default.DBPrefix =',
            'database.default.port = ' . (int) $d['db_port'],
            'database.default.charset = utf8mb4',
            'database.default.DBCollat = utf8mb4_general_ci',
            '',
        ];
        if ($d['https']) {
            $out[] = 'cookie.secure = true';
            $out[] = '';
        }

        return implode("\n", $out) . "\n";
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'env_written' => false, 'env' => ''];
    }
}
