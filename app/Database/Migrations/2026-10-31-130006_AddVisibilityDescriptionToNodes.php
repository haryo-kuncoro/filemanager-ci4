<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * visibility : 'shared' (terlihat pengguna lain, bawaan) | 'private' (hanya pemilik).
 * description: keterangan bebas dari pengguna untuk folder/berkas.
 */
class AddVisibilityDescriptionToNodes extends Migration
{
    public function up(): void
    {
        $cols = [
            'visibility'  => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'shared'],
            'description' => ['type' => 'TEXT', 'null' => true],
        ];
        foreach ($cols as $name => $def) { // idempoten: aman bila sebagian kolom sudah ada
            if (! $this->db->fieldExists($name, 'nodes')) {
                $this->forge->addColumn('nodes', [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('nodes', ['visibility', 'description']);
    }
}
