<?php

namespace App\Database\Migrations;

use App\Models\ReceivablesModel;
use CodeIgniter\Database\Migration;

class NormalizeReceivablesAndWriteoffs extends Migration
{
    public function up(): void
    {
        $this->createReceivablesTable();
        $this->createPaymentsTable();
        $this->createWriteoffsTable();

        $row = $this->db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Operational state row is missing; receivables cannot be migrated.');
        }
        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $receivables = $state['receivables'] ?? null;
        $writeoffs = $state['receivableWriteoffs'] ?? [];
        if (! is_array($receivables) || ! array_is_list($receivables)
            || ! is_array($writeoffs) || ! array_is_list($writeoffs)) {
            throw new \RuntimeException('Receivable data is invalid; the migration cannot continue.');
        }
        (new ReceivablesModel($this->db))->validate($receivables, $writeoffs);

        if (! $this->db->transBegin()) {
            throw new \RuntimeException('A transaction could not be started for receivable migration.');
        }
        try {
            foreach ($receivables as $position => $receivable) {
                $this->db->table('receivables')->insert([
                    'id'          => $receivable['id'],
                    'position'    => $position,
                    'sale_id'     => $receivable['saleId'],
                    'number'      => $receivable['number'],
                    'customer'    => $receivable['customer'],
                    'customer_id' => $receivable['customerId'] ?? null,
                    'total'       => $receivable['total'],
                    'paid'        => $receivable['paid'],
                    'due_date'    => $receivable['dueDate'] ?? null,
                    'sale_date'   => $receivable['date'] ?? null,
                    'created_at'  => $receivable['createdAt'],
                ]);
                foreach ($receivable['payments'] as $paymentPosition => $payment) {
                    $this->db->table('receivable_payments')->insert([
                        'id'             => $payment['id'],
                        'receivable_id'  => $receivable['id'],
                        'position'       => $paymentPosition,
                        'amount'         => $payment['amount'],
                        'method'         => $payment['method'],
                        'user_name'      => $payment['userName'],
                        'payment_date'   => $payment['date'],
                        'created_at'     => $payment['createdAt'],
                    ]);
                }
            }
            foreach ($writeoffs as $position => $writeoff) {
                $this->db->table('receivable_writeoffs')->insert([
                    'id'            => $writeoff['id'],
                    'position'      => $position,
                    'receivable_id' => $writeoff['receivableId'],
                    'sale_id'       => $writeoff['saleId'],
                    'number'        => $writeoff['number'],
                    'customer'      => $writeoff['customer'],
                    'amount'        => $writeoff['amount'],
                    'reason'        => $writeoff['reason'],
                    'writeoff_date' => $writeoff['date'],
                    'created_at'    => $writeoff['createdAt'],
                    'user_name'     => $writeoff['userName'],
                ]);
            }

            unset($state['receivables'], $state['receivableWriteoffs']);
            $this->db->table('application_state')->where('id', 1)->update([
                'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Receivable migration failed; the original state was preserved.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Receivable migration could not be committed.');
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
            throw new \RuntimeException('Operational state row is missing; receivables cannot be restored.');
        }
        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $state['receivables'] = (new ReceivablesModel($this->db))->all();
        $state['receivableWriteoffs'] = (new ReceivablesModel($this->db))->writeoffs();
        $this->db->table('application_state')->where('id', 1)->update([
            'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->forge->dropTable('receivable_writeoffs', true);
        $this->forge->dropTable('receivable_payments', true);
        $this->forge->dropTable('receivables', true);
    }

    private function createReceivablesTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'sale_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'customer' => ['type' => 'VARCHAR', 'constraint' => 120],
            'customer_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'total' => ['type' => 'BIGINT', 'constraint' => 20],
            'paid' => ['type' => 'BIGINT', 'constraint' => 20],
            'due_date' => ['type' => 'DATE', 'null' => true],
            'sale_date' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('sale_id', 'receivables_sale_unique');
        $this->forge->addKey('position', false, false, 'receivables_position_index');
        $this->forge->addKey(['customer_id', 'due_date'], false, false, 'receivables_customer_due_index');
        $this->forge->createTable('receivables', true);
    }

    private function createPaymentsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'receivable_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'amount' => ['type' => 'BIGINT', 'constraint' => 20],
            'method' => ['type' => 'VARCHAR', 'constraint' => 20],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'payment_date' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['receivable_id', 'position'], false, false, 'receivable_payments_position_index');
        $this->forge->createTable('receivable_payments', true);
    }

    private function createWriteoffsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'receivable_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'sale_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'customer' => ['type' => 'VARCHAR', 'constraint' => 120],
            'amount' => ['type' => 'BIGINT', 'constraint' => 20],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 180],
            'writeoff_date' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 80],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['receivable_id', 'writeoff_date'], false, false, 'receivable_writeoffs_receivable_date_index');
        $this->forge->addKey('position', false, false, 'receivable_writeoffs_position_index');
        $this->forge->createTable('receivable_writeoffs', true);
    }
}
