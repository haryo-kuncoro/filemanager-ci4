<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSharesAndLogs extends Migration
{
    public function up(): void
    {
        // ---- shares: satu tautan publik per node ----
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'node_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token'          => ['type' => 'VARCHAR', 'constraint' => 64],
            'password_hash'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'expires_at'     => ['type' => 'DATETIME', 'null' => true],
            'allow_download' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'view_count'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'download_count' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->addUniqueKey('node_id');
        $this->forge->createTable('shares', true);

        // ---- activity_logs ----
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'node_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'target'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'details'    => ['type' => 'TEXT', 'null' => true],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('created_at');
        $this->forge->addKey('action');
        $this->forge->addKey('user_id');
        $this->forge->createTable('activity_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('activity_logs', true);
        $this->forge->dropTable('shares', true);
    }
}
