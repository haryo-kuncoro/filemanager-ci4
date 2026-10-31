<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Kunci folder/berkas: hanya pemilik yang dapat mengunci / membuka kunci. */
class AddLockToNodes extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('nodes', [
            'locked_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'locked_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('nodes', ['locked_by', 'locked_at']);
    }
}
