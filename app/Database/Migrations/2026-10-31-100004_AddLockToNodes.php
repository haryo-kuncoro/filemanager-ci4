<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Kunci folder/berkas: hanya pemilik yang dapat mengunci / membuka kunci. */
class AddLockToNodes extends Migration
{
    public function up(): void
    {
        $cols = [
            'locked_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'locked_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        foreach ($cols as $name => $def) { // idempoten: aman bila sebagian kolom sudah ada
            if (! $this->db->fieldExists($name, 'nodes')) {
                $this->forge->addColumn('nodes', [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('nodes', ['locked_by', 'locked_at']);
    }
}
