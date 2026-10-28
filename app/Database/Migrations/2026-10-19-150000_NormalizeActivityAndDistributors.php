<?php

namespace App\Database\Migrations;

use App\Models\ActivityDistributorsModel;
use CodeIgniter\Database\Migration;

class NormalizeActivityAndDistributors extends Migration
{
    public function up(): void
    {
        $this->createActivityTable();
        $this->createDistributorsTable();

        $row = $this->db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Operational state row is missing; activity and distributors cannot be migrated.');
        }
        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $activity = $state['activity'] ?? null;
        $distributors = $state['distributors'] ?? null;
        if (! is_array($activity) || ! array_is_list($activity)
            || ! is_array($distributors) || ! array_is_list($distributors)) {
            throw new \RuntimeException('Activity or distributor data is invalid; the migration cannot continue.');
        }
        $model = new ActivityDistributorsModel($this->db);
        $model->validateActivity($activity);
        $model->validateDistributors($distributors);

        if (! $this->db->transBegin()) {
            throw new \RuntimeException('A transaction could not be started for activity and distributor migration.');
        }
        try {
            foreach ($activity as $position => $item) {
                $this->db->table('activity_logs')->insert([
                    'id'         => $item['id'],
                    'position'   => $position,
                    'user_id'    => isset($item['userId']) ? (string) $item['userId'] : null,
                    'name'       => $item['name'],
                    'role'       => $item['role'],
                    'message'    => $item['message'],
                    'created_at' => $item['createdAt'],
                ]);
            }
            foreach ($distributors as $position => $distributor) {
                $this->db->table('distributors')->insert([
                    'id'       => $distributor['id'],
                    'position' => $position,
                    'name'     => $distributor['name'],
                    'phone'    => $distributor['phone'] ?? '',
                ]);
            }

            unset($state['activity'], $state['distributors']);
            $this->db->table('application_state')->where('id', 1)->update([
                'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Activity and distributor migration failed; the original state was preserved.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Activity and distributor migration could not be committed.');
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function down(): void
    {
        $row = $this->db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Operational state row is missing; activity and distributors cannot be restored.');
        }
        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $model = new ActivityDistributorsModel($this->db);
        $state['activity'] = $model->activity();
        $state['distributors'] = $model->distributors();
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('A transaction could not be started to restore activity and distributors.');
        }
        try {
            $this->db->table('application_state')->where('id', 1)->update([
                'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Activity and distributor rollback failed; normalized data was preserved.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Activity and distributor rollback could not be committed.');
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }

        $this->forge->dropTable('distributors', true);
        $this->forge->dropTable('activity_logs', true);
    }

    private function createActivityTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'user_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'role' => ['type' => 'VARCHAR', 'constraint' => 32],
            'message' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['created_at', 'user_id'], false, false, 'activity_logs_created_user_index');
        $this->forge->addKey('position', false, false, 'activity_logs_position_index');
        $this->forge->createTable('activity_logs', true);
    }

    private function createDistributorsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 40],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('position', false, false, 'distributors_position_index');
        $this->forge->createTable('distributors', true);
    }
}
