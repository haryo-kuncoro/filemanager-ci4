<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel pengaturan (key/value) + izin settings.manage.
 * Logo disimpan di sini (base64) agar ikut backup/restore otomatis. Idempoten.
 */
class CreateSettingsTable extends Migration
{
    public function up(): void
    {
        $db = $this->db;

        if (! $db->tableExists('settings')) {
            $this->forge->addField([
                'name'       => ['type' => 'VARCHAR', 'constraint' => 64],
                'value'      => ['type' => 'MEDIUMTEXT', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('name');
            $this->forge->createTable('settings', true);
        }

        if (! $db->tableExists('permissions')) {
            return;
        }
        $code = 'settings.manage';
        if ($db->table('permissions')->where('code', $code)->countAllResults() === 0) {
            $db->table('permissions')->insert(['code' => $code, 'label' => 'Pengaturan aplikasi (logo)', 'group_name' => 'Administrasi']);
        }
        $admin = $db->table('roles')->where('name', 'administrator')->get()->getRowArray();
        if ($admin === null) {
            return;
        }
        $pid = (int) $db->table('permissions')->where('code', $code)->get()->getRowArray()['id'];
        if ($db->table('role_permissions')->where(['role_id' => (int) $admin['id'], 'permission_id' => $pid])->countAllResults() === 0) {
            $db->table('role_permissions')->insert(['role_id' => (int) $admin['id'], 'permission_id' => $pid]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('settings', true);
        $this->db->table('permissions')->where('code', 'settings.manage')->delete();
    }
}
