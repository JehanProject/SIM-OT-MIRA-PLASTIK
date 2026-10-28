<?php

namespace App\Database\Migrations;

use App\Models\ReturnsCashModel;
use CodeIgniter\Database\Migration;

class NormalizeReturnsAndCashEntries extends Migration
{
    public function up(): void
    {
        $this->createReturnsTable();
        $this->createReturnItemsTable();
        $this->createCashEntriesTable();

        $row = $this->db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Operational state row is missing; returns and cash entries cannot be migrated.');
        }
        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $returns = $state['returns'] ?? null;
        $cashEntries = $state['cashEntries'] ?? null;
        if (! is_array($returns) || ! array_is_list($returns)
            || ! is_array($cashEntries) || ! array_is_list($cashEntries)) {
            throw new \RuntimeException('Return or cash entry data is invalid; the migration cannot continue.');
        }
        $model = new ReturnsCashModel($this->db);
        $model->validateReturns($returns);
        $model->validateCashEntries($cashEntries);

        if (! $this->db->transBegin()) {
            throw new \RuntimeException('A transaction could not be started for return and cash entry migration.');
        }
        try {
            foreach ($returns as $position => $return) {
                $this->db->table('return_adjustments')->insert($this->returnRow($return, $position));
                foreach ($return['items'] as $itemPosition => $item) {
                    $this->db->table('return_items')->insert($this->returnItemRow($return, $item, $itemPosition));
                }
            }
            foreach ($cashEntries as $position => $entry) {
                $this->db->table('cash_entries')->insert($this->cashEntryRow($entry, $position));
            }

            unset($state['returns'], $state['cashEntries']);
            $this->db->table('application_state')->where('id', 1)->update([
                'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Return and cash entry migration failed; the original state was preserved.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Return and cash entry migration could not be committed.');
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
            throw new \RuntimeException('Operational state row is missing; returns and cash entries cannot be restored.');
        }
        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $model = new ReturnsCashModel($this->db);
        $state['returns'] = $model->returns();
        $state['cashEntries'] = $model->cashEntries();
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('A transaction could not be started to restore returns and cash entries.');
        }
        try {
            $this->db->table('application_state')->where('id', 1)->update([
                'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Return and cash entry rollback failed; normalized data was preserved.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Return and cash entry rollback could not be committed.');
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }

        $this->forge->dropTable('cash_entries', true);
        $this->forge->dropTable('return_items', true);
        $this->forge->dropTable('return_adjustments', true);
    }

    private function createReturnsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'sale_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'status' => ['type' => 'VARCHAR', 'constraint' => 40],
            'amount' => ['type' => 'BIGINT', 'constraint' => 20],
            'cash_refund' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'refund_method' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 180],
            'return_date' => ['type' => 'DATE'],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
            'user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'approved_by' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'approved_at' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'reviewed_by' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'reviewed_at' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['sale_id', 'status'], false, false, 'return_adjustments_sale_status_index');
        $this->forge->addKey('position', false, false, 'return_adjustments_position_index');
        $this->forge->createTable('return_adjustments', true);
    }

    private function createReturnItemsTable(): void
    {
        $this->forge->addField([
            'return_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'price' => ['type' => 'BIGINT', 'constraint' => 20],
            'cost' => ['type' => 'BIGINT', 'constraint' => 20],
            'amount' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'restocked' => ['type' => 'BOOLEAN'],
        ]);
        $this->forge->addPrimaryKey(['return_id', 'position']);
        $this->forge->addKey('product_id', false, false, 'return_items_product_index');
        $this->forge->createTable('return_items', true);
    }

    private function createCashEntriesTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'entry_date' => ['type' => 'DATE'],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
            'direction' => ['type' => 'VARCHAR', 'constraint' => 10],
            'category' => ['type' => 'VARCHAR', 'constraint' => 60],
            'description' => ['type' => 'VARCHAR', 'constraint' => 180],
            'amount' => ['type' => 'BIGINT', 'constraint' => 20],
            'method' => ['type' => 'VARCHAR', 'constraint' => 20],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 80],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['entry_date', 'direction'], false, false, 'cash_entries_date_direction_index');
        $this->forge->addKey('position', false, false, 'cash_entries_position_index');
        $this->forge->createTable('cash_entries', true);
    }

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private function returnRow(array $return, int $position): array
    {
        return [
            'id'            => $return['id'],
            'position'      => $position,
            'sale_id'       => $return['saleId'],
            'number'        => $return['number'],
            'type'          => $return['type'],
            'status'        => $return['status'],
            'amount'        => $return['amount'],
            'cash_refund'   => $return['cashRefund'] ?? null,
            'refund_method' => $return['refundMethod'] ?? null,
            'reason'        => $return['reason'],
            'return_date'   => $return['date'],
            'created_at'    => $return['createdAt'],
            'user_id'       => $return['userId'] ?? null,
            'user_name'     => $return['userName'],
            'approved_by'   => $return['approvedBy'] ?? null,
            'approved_at'   => $return['approvedAt'] ?? null,
            'reviewed_by'   => $return['reviewedBy'] ?? null,
            'reviewed_at'   => $return['reviewedAt'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $return
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function returnItemRow(array $return, array $item, int $position): array
    {
        return [
            'return_id' => $return['id'],
            'position'  => $position,
            'product_id' => $item['productId'],
            'code'      => $item['code'] ?? null,
            'name'      => $item['name'],
            'unit'      => $item['unit'] ?? null,
            'quantity'  => $item['quantity'],
            'price'     => $item['price'],
            'cost'      => $item['cost'],
            'amount'    => $item['amount'] ?? null,
            'restocked' => $item['restocked'] ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function cashEntryRow(array $entry, int $position): array
    {
        return [
            'id'          => $entry['id'],
            'position'    => $position,
            'entry_date'  => $entry['date'],
            'created_at'  => $entry['createdAt'],
            'direction'   => $entry['direction'],
            'category'    => $entry['category'],
            'description' => $entry['description'],
            'amount'      => $entry['amount'],
            'method'      => $entry['method'],
            'user_name'   => $entry['userName'],
        ];
    }
}
