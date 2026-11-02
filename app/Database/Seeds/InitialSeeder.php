<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Mengisi izin, peran bawaan, dan satu akun administrator awal.
 * Aman dijalankan berulang kali (idempoten).
 *
 * Kata sandi admin: dari env ADMIN_PASSWORD bila ada, jika tidak dibuat acak
 * dan ditampilkan SEKALI di konsol. Pengguna wajib menggantinya saat login pertama.
 */
class InitialSeeder extends Seeder
{
    /** false saat dipanggil installer web (admin dibuat dari form). */
    public bool $createAdmin = true;

    /** @var array<string, array{0:string,1:string}> kode => [label, grup] */
    public const PERMISSIONS = [
        'files.view'          => ['Melihat berkas & folder', 'Berkas'],
        'files.download'      => ['Mengunduh berkas / ZIP', 'Berkas'],
        'files.upload'        => ['Mengunggah berkas', 'Berkas'],
        'files.create_folder' => ['Membuat folder', 'Berkas'],
        'files.rename'        => ['Mengubah nama', 'Berkas'],
        'files.move'          => ['Memindahkan', 'Berkas'],
        'files.copy'          => ['Menyalin', 'Berkas'],
        'files.delete'        => ['Menghapus (ke sampah)', 'Berkas'],
        'files.share'         => ['Membagikan lewat tautan publik', 'Berkas'],
        'trash.manage'        => ['Mengelola sampah (pulihkan / hapus permanen)', 'Berkas'],
        'users.manage'        => ['Mengelola pengguna', 'Administrasi'],
        'roles.manage'        => ['Mengelola peran & izin', 'Administrasi'],
        'shares.manage'       => ['Mengelola semua tautan publik', 'Administrasi'],
        'logs.view'           => ['Melihat log aktivitas', 'Administrasi'],
        'backup.manage'       => ['Backup: buat, unduh, unggah, hapus (berisi data & hash sandi SELURUH pengguna)', 'Administrasi'],
        'backup.restore'      => ['Backup: memulihkan data (mengganti SEMUA data; setara akses penuh)', 'Administrasi'],
        'settings.manage'     => ['Pengaturan aplikasi (logo)', 'Administrasi'],
    ];

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $db  = $this->db;

        // ---- izin ----
        foreach (self::PERMISSIONS as $code => [$label, $group]) {
            $exists = $db->table('permissions')->where('code', $code)->countAllResults();
            if ($exists === 0) {
                $db->table('permissions')->insert(['code' => $code, 'label' => $label, 'group_name' => $group]);
            }
        }
        $permIds = array_column($db->table('permissions')->select('id, code')->get()->getResultArray(), 'id', 'code');

        // ---- peran bawaan ----
        $fileAll = ['files.view', 'files.download', 'files.upload', 'files.create_folder', 'files.rename',
            'files.move', 'files.copy', 'files.delete', 'files.share', 'trash.manage'];

        $roles = [
            'administrator' => ['Administrator', 'Akses penuh ke seluruh fitur dan administrasi.', 0, array_keys(self::PERMISSIONS)],
            'manager'       => ['Manager', 'Mengelola seluruh berkas, tautan publik, dan sampah.', 0, array_merge($fileAll, ['shares.manage', 'logs.view'])],
            'editor'        => ['Editor', 'Menambah & mengelola berkas miliknya sendiri; berkas orang lain hanya dibaca.', 1,
                ['files.view', 'files.download', 'files.upload', 'files.create_folder', 'files.rename', 'files.move', 'files.copy', 'files.delete', 'files.share']],
            'viewer'        => ['Viewer', 'Hanya melihat dan mengunduh.', 1, ['files.view', 'files.download']],
        ];

        $roleIds = [];
        foreach ($roles as $name => [$label, $desc, $ownOnly, $perms]) {
            $row = $db->table('roles')->where('name', $name)->get()->getRowArray();
            if ($row === null) {
                $db->table('roles')->insert([
                    'name' => $name, 'label' => $label, 'description' => $desc,
                    'own_only' => $ownOnly, 'is_system' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $roleId = (int) $db->insertID();
                foreach ($perms as $code) {
                    $db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permIds[$code]]);
                }
            } else {
                $roleId = (int) $row['id'];
                // Administrator selalu mendapat semua izin (termasuk izin baru di versi berikutnya).
                if ($name === 'administrator') {
                    foreach ($perms as $code) {
                        $has = $db->table('role_permissions')->where(['role_id' => $roleId, 'permission_id' => $permIds[$code]])->countAllResults();
                        if ($has === 0) {
                            $db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permIds[$code]]);
                        }
                    }
                }
            }
            $roleIds[$name] = $roleId;
        }

        // ---- akun admin awal ----
        if ($this->createAdmin && $db->table('users')->countAllResults() === 0) {
            $password = (string) (env('ADMIN_PASSWORD') ?: bin2hex(random_bytes(6)));
            $generated = ! env('ADMIN_PASSWORD');

            $db->table('users')->insert([
                'name'                 => 'Administrator',
                'username'             => 'admin',
                'email'                => null,
                'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
                'role_id'              => $roleIds['administrator'],
                'is_active'            => 1,
                'must_change_password' => 1,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            \CodeIgniter\CLI\CLI::write('Akun administrator dibuat.', 'green');
            \CodeIgniter\CLI\CLI::write('  Username : admin');
            \CodeIgniter\CLI\CLI::write('  Password : ' . $password . ($generated ? '   (dibuat acak — catat sekarang!)' : ''));
            \CodeIgniter\CLI\CLI::write('Anda akan diminta mengganti kata sandi saat login pertama.', 'yellow');
        }
    }
}
