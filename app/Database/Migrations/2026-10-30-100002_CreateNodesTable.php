<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pohon virtual file & folder. Isi berkas disimpan di WRITEPATH dengan nama acak
 * (`storage_name`), sedangkan nama asli, hierarki, dan pemilik disimpan di tabel ini.
 */
class CreateNodesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'parent_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'type'          => ['type' => 'VARCHAR', 'constraint' => 10], // file | folder
            'name'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'storage_name'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'mime'          => ['type' => 'VARCHAR', 'constraint' => 127, 'null' => true],
            'ext'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'size'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'owner_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'trash_root_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['parent_id', 'deleted_at']);
        $this->forge->addKey('owner_id');
        $this->forge->addKey('trash_root_id');
        $this->forge->addKey('storage_name');
        $this->forge->createTable('nodes', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('nodes', true);
    }
}
