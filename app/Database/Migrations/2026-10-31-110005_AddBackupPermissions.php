<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Izin backup agar bisa diberikan ke peran selain administrator:
 *   backup.manage  - buat / unduh / unggah / hapus backup
 *   backup.restore - memulihkan (butuh backup.manage juga)
 * Peran administrator otomatis mendapatkannya. Idempoten; pada instalasi baru seeder yang mengisinya.
 */
class AddBackupPermissions extends Migration
{
    private const PERMS = [
        'backup.manage'  => ['Backup: buat, unduh, unggah, hapus (berisi data & hash sandi SELURUH pengguna)', 'Administrasi'],
        'backup.restore' => ['Backup: memulihkan data (mengganti SEMUA data; setara akses penuh)', 'Administrasi'],
    ];

    public function up(): void
    {
        $db = $this->db;
        if (! $db->tableExists('permissions')) {
            return;
        }
        foreach (self::PERMS as $code => [$label, $group]) {
            if ($db->table('permissions')->where('code', $code)->countAllResults() === 0) {
                $db->table('permissions')->insert(['code' => $code, 'label' => $label, 'group_name' => $group]);
            }
        }
        $admin = $db->table('roles')->where('name', 'administrator')->get()->getRowArray();
        if ($admin === null) {
            return; // instalasi baru: seeder akan mengisi
        }
        foreach (array_keys(self::PERMS) as $code) {
            $pid = (int) $db->table('permissions')->where('code', $code)->get()->getRowArray()['id'];
            if ($db->table('role_permissions')->where(['role_id' => (int) $admin['id'], 'permission_id' => $pid])->countAllResults() === 0) {
                $db->table('role_permissions')->insert(['role_id' => (int) $admin['id'], 'permission_id' => $pid]);
            }
        }
    }

    public function down(): void
    {
        $this->db->table('permissions')->whereIn('code', array_keys(self::PERMS))->delete();
    }
}
