<?php

namespace App\Database\Migrations;

use App\Models\StockOperationsModel;
use CodeIgniter\Database\Migration;

class NormalizeStockOperations extends Migration
{
    public function up(): void
    {
        $this->createMovementsTable();
        $this->createReceiptsTable();
        $row = $this->db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Operational state row is missing; stock operations cannot be migrated.');
        }
        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $movements = $state['stockMovements'] ?? null;
        $receipts = $state['receipts'] ?? null;
        if (! is_array($movements) || ! array_is_list($movements)
            || ! is_array($receipts) || ! array_is_list($receipts)) {
            throw new \RuntimeException('Stock operation data is invalid; the migration cannot continue.');
        }
        $model = new StockOperationsModel($this->db);
        $model->validateMovements($movements);
        $model->validateReceipts($receipts);

        if (! $this->db->transBegin()) {
            throw new \RuntimeException('A transaction could not be started for stock operation migration.');
        }
        try {
            foreach ($movements as $position => $item) {
                $this->db->table('stock_movements')->insert($this->movementRow($item, $position));
            }
            foreach ($receipts as $position => $item) {
                $this->db->table('stock_receipts')->insert($this->receiptRow($item, $position));
            }
            unset($state['stockMovements'], $state['receipts']);
            $this->db->table('application_state')->where('id', 1)->update([
                'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Stock operation migration failed; the original state was preserved.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Stock operation migration could not be committed.');
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
            throw new \RuntimeException('Operational state row is missing; stock operations cannot be restored.');
        }
        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $model = new StockOperationsModel($this->db);
        $state['stockMovements'] = $model->movements();
        $state['receipts'] = $model->receipts();
        $this->db->table('application_state')->where('id', 1)->update([
            'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->forge->dropTable('stock_receipts', true);
        $this->forge->dropTable('stock_movements', true);
    }

    private function createMovementsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'product_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'product_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'location' => ['type' => 'VARCHAR', 'constraint' => 40],
            'delta' => ['type' => 'BIGINT', 'constraint' => 20],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 30],
            'loss' => ['type' => 'BIGINT', 'constraint' => 20],
            'reason' => ['type' => 'TEXT'],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['product_id', 'created_at'], false, false, 'stock_movements_product_created_index');
        $this->forge->addKey('position', false, false, 'stock_movements_position_index');
        $this->forge->createTable('stock_movements', true);
    }

    private function createReceiptsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'product_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'product_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 30],
            'distributor' => ['type' => 'VARCHAR', 'constraint' => 100],
            'invoice' => ['type' => 'VARCHAR', 'constraint' => 50],
            'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'price' => ['type' => 'BIGINT', 'constraint' => 20],
            'total' => ['type' => 'BIGINT', 'constraint' => 20],
            'receipt_date' => ['type' => 'DATE'],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'method' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('invoice', 'stock_receipts_invoice_unique');
        $this->forge->addKey(['product_id', 'receipt_date'], false, false, 'stock_receipts_product_date_index');
        $this->forge->addKey('position', false, false, 'stock_receipts_position_index');
        $this->forge->createTable('stock_receipts', true);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function movementRow(array $item, int $position): array
    {
        return [
            'id'           => $item['id'],
            'position'     => $position,
            'product_id'   => $item['productId'],
            'product_code' => $item['productCode'],
            'product_name' => $item['productName'],
            'type'         => $item['type'],
            'location'     => $item['location'],
            'delta'        => $item['delta'],
            'unit'         => $item['unit'],
            'loss'         => $item['loss'],
            'reason'       => $item['reason'],
            'user_name'    => $item['userName'],
            'created_at'   => $item['createdAt'],
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function receiptRow(array $item, int $position): array
    {
        return [
            'id'           => $item['id'],
            'position'     => $position,
            'product_id'   => $item['productId'],
            'product_code' => $item['productCode'],
            'product_name' => $item['productName'],
            'unit'         => $item['unit'],
            'distributor'  => $item['distributor'],
            'invoice'      => $item['invoice'],
            'quantity'     => $item['quantity'],
            'price'        => $item['price'],
            'total'        => $item['total'],
            'receipt_date' => $item['date'],
            'created_at'   => $item['createdAt'],
            'user_name'    => $item['userName'],
            'method'       => $item['method'] ?? null,
        ];
    }
}
