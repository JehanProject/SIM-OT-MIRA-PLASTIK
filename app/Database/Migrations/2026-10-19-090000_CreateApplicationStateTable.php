<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateApplicationStateTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'SMALLINT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'revision' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'payload' => [
                'type' => 'LONGTEXT',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('application_state', true);

        $this->db->table('application_state')->insert([
            'id'         => 1,
            'revision'   => 0,
            'payload'    => json_encode([
                'activity'      => [],
                'products'      => [],
                'distributors'  => [],
                'customers'     => [],
                'stockMovements' => [],
                'receipts'      => [],
                'sales'         => [],
                'receivables'   => [],
                'receivableWriteoffs' => [],
                'returns'       => [],
                'cashEntries'   => [],
            ], JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('application_state', true);
    }
}
