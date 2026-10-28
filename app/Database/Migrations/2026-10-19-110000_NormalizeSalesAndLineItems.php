<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeSalesAndLineItems extends Migration
{
    public function up(): void
    {
        $this->createSalesTable();
        $this->createSaleItemsTable();
        $this->createSaleItemStockAllocationsTable();

        $row = $this->db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Operational state row is missing; sales cannot be migrated.');
        }

        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $sales = $state['sales'] ?? null;
        if (! is_array($sales) || ! array_is_list($sales)) {
            throw new \RuntimeException('The sales collection is invalid; sales cannot be migrated.');
        }
        (new \App\Models\SalesModel($this->db))->validate($sales);

        if (! $this->db->transBegin()) {
            throw new \RuntimeException('A transaction could not be started for sales migration.');
        }
        try {
            foreach ($sales as $position => $sale) {
                if (! is_array($sale)) {
                    throw new \RuntimeException('A sale record is invalid; sales cannot be migrated.');
                }
                $this->db->table('sales')->insert($this->saleRow($sale, $position));
                $items = $sale['items'] ?? null;
                if (! is_array($items) || ! array_is_list($items)) {
                    throw new \RuntimeException('A sale item list is invalid; sales cannot be migrated.');
                }
                foreach ($items as $itemPosition => $item) {
                    if (! is_array($item)) {
                        throw new \RuntimeException('A sale item is invalid; sales cannot be migrated.');
                    }
                    $itemId = $sale['id'] . ':' . $itemPosition;
                    $this->db->table('sale_items')->insert($this->saleItemRow($sale, $item, $itemPosition, $itemId));
                    $allocations = $item['stockAllocations'] ?? [];
                    if (! is_array($allocations) || ! array_is_list($allocations)) {
                        throw new \RuntimeException('Sale stock allocations are invalid; sales cannot be migrated.');
                    }
                    foreach ($allocations as $allocationPosition => $allocation) {
                        if (! is_array($allocation)) {
                            throw new \RuntimeException('A sale stock allocation is invalid; sales cannot be migrated.');
                        }
                        $this->db->table('sale_item_stock_allocations')->insert([
                            'sale_item_id' => $itemId,
                            'position'     => $allocationPosition,
                            'location'     => $this->requiredString($allocation, 'location', 20),
                            'quantity'     => $this->positiveInteger($allocation, 'quantity'),
                        ]);
                    }
                }
            }

            unset($state['sales']);
            $this->db->table('application_state')->where('id', 1)->update([
                'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Sales migration failed; the original state was preserved.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Sales migration could not be committed.');
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
            throw new \RuntimeException('Operational state row is missing; sales cannot be restored.');
        }

        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $sales = [];
        foreach ($this->db->table('sales')->orderBy('position')->get()->getResultArray() as $sale) {
            $sale['items'] = [];
            foreach ($this->db->table('sale_items')->where('sale_id', $sale['id'])->orderBy('position')->get()->getResultArray() as $item) {
                $allocations = $this->db->table('sale_item_stock_allocations')
                    ->where('sale_item_id', $item['id'])
                    ->orderBy('position')
                    ->get()
                    ->getResultArray();
                $sale['items'][] = [
                    'productId' => $item['product_id'],
                    'code'      => $item['product_code'],
                    'name'      => $item['product_name'],
                    'unit'      => $item['unit'],
                    'quantity'  => (int) $item['quantity'],
                    'price'     => (int) $item['price'],
                    'subtotal'  => (int) $item['subtotal'],
                    'cost'      => (int) $item['cost'],
                    'stockAllocations' => array_map(static fn (array $allocation): array => [
                        'location' => $allocation['location'],
                        'quantity' => (int) $allocation['quantity'],
                    ], $allocations),
                ];
                unset($item);
            }
            $sales[] = [
                'id'          => $sale['id'],
                'number'      => $sale['number'],
                'type'        => $sale['type'],
                'payment'     => $sale['payment'],
                'status'      => $sale['status'],
                'customer'    => $sale['customer'],
                'customerId'  => $sale['customer_id'],
                'dueDate'     => $sale['due_date'],
                'date'        => $sale['sale_date'],
                'total'       => (int) $sale['total'],
                'profit'      => (int) $sale['profit'],
                'paidAmount'  => (int) $sale['paid_amount'],
                'cashReceived'=> $sale['cash_received'] === null ? null : (int) $sale['cash_received'],
                'change'      => (int) $sale['change_amount'],
                'userId'      => $sale['user_id'],
                'userName'    => $sale['user_name'],
                'createdAt'   => $sale['created_at'],
                'items'       => $sale['items'],
            ];
        }
        $state['sales'] = $sales;
        $this->db->table('application_state')->where('id', 1)->update([
            'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->forge->dropTable('sale_item_stock_allocations', true);
        $this->forge->dropTable('sale_items', true);
        $this->forge->dropTable('sales', true);
    }

    private function createSalesTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'payment' => ['type' => 'VARCHAR', 'constraint' => 20],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30],
            'customer' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'customer_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'due_date' => ['type' => 'DATE', 'null' => true],
            'sale_date' => ['type' => 'DATE', 'null' => true],
            'total' => ['type' => 'BIGINT', 'constraint' => 20],
            'profit' => ['type' => 'BIGINT', 'constraint' => 20],
            'paid_amount' => ['type' => 'BIGINT', 'constraint' => 20],
            'cash_received' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'change_amount' => ['type' => 'BIGINT', 'constraint' => 20],
            'user_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('number', 'sales_number_unique');
        $this->forge->addKey('position', false, false, 'sales_position_index');
        $this->forge->addKey(['sale_date', 'created_at'], false, false, 'sales_date_created_index');
        $this->forge->createTable('sales', true);
    }

    private function createSaleItemsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 150],
            'sale_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'product_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'product_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 30],
            'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'price' => ['type' => 'BIGINT', 'constraint' => 20],
            'subtotal' => ['type' => 'BIGINT', 'constraint' => 20],
            'cost' => ['type' => 'BIGINT', 'constraint' => 20],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['sale_id', 'position'], false, false, 'sale_items_sale_position_index');
        $this->forge->addKey('product_id', false, false, 'sale_items_product_index');
        $this->forge->createTable('sale_items', true);
    }

    private function createSaleItemStockAllocationsTable(): void
    {
        $this->forge->addField([
            'sale_item_id' => ['type' => 'VARCHAR', 'constraint' => 150],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'location' => ['type' => 'VARCHAR', 'constraint' => 20],
            'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['sale_item_id', 'position']);
        $this->forge->createTable('sale_item_stock_allocations', true);
    }

    /**
     * @param array<string, mixed> $sale
     * @return array<string, mixed>
     */
    private function saleRow(array $sale, int $position): array
    {
        return [
            'id'            => $this->requiredString($sale, 'id', 100),
            'position'      => $position,
            'number'         => $this->requiredString($sale, 'number', 50),
            'type'           => $this->requiredString($sale, 'type', 20),
            'payment'        => $this->requiredString($sale, 'payment', 20),
            'status'         => $this->requiredString($sale, 'status', 30),
            'customer'       => $this->optionalString($sale, 'customer', 120),
            'customer_id'    => $this->optionalString($sale, 'customerId', 100),
            'due_date'       => $this->optionalDate($sale, 'dueDate'),
            'sale_date'      => $this->optionalDate($sale, 'date'),
            'total'          => $this->nonNegativeInteger($sale, 'total'),
            'profit'         => $this->requiredInteger($sale, 'profit'),
            'paid_amount'    => $this->nonNegativeInteger($sale, 'paidAmount'),
            'cash_received'  => $this->nullableNonNegativeInteger($sale, 'cashReceived'),
            'change_amount'  => $this->nonNegativeInteger($sale, 'change'),
            'user_id'        => $this->requiredIdentifier($sale, 'userId', 100),
            'user_name'      => $this->requiredString($sale, 'userName', 80),
            'created_at'     => $this->requiredString($sale, 'createdAt', 40),
        ];
    }

    /**
     * @param array<string, mixed> $sale
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function saleItemRow(array $sale, array $item, int $position, string $id): array
    {
        return [
            'id'           => $id,
            'sale_id'      => $sale['id'],
            'position'     => $position,
            'product_id'   => $this->requiredString($item, 'productId', 100),
            'product_code' => $this->requiredString($item, 'code', 50),
            'product_name' => $this->requiredString($item, 'name', 120),
            'unit'         => $this->requiredString($item, 'unit', 30),
            'quantity'     => $this->positiveInteger($item, 'quantity'),
            'price'        => $this->nonNegativeInteger($item, 'price'),
            'subtotal'     => $this->nonNegativeInteger($item, 'subtotal'),
            'cost'         => $this->nonNegativeInteger($item, 'cost'),
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredString(array $record, string $key, int $maxLength): string
    {
        $value = $record[$key] ?? null;
        if (! is_string($value) || trim($value) === '' || strlen($value) > $maxLength) {
            throw new \RuntimeException("Invalid {$key} in sales data.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredIdentifier(array $record, string $key, int $maxLength): string
    {
        $value = $record[$key] ?? null;
        if ((! is_string($value) && ! is_int($value)) || (string) $value === '' || strlen((string) $value) > $maxLength) {
            throw new \RuntimeException("Invalid {$key} in sales data.");
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalString(array $record, string $key, int $maxLength): ?string
    {
        $value = $record[$key] ?? null;
        if ($value !== null && (! is_string($value) || strlen($value) > $maxLength)) {
            throw new \RuntimeException("Invalid {$key} in sales data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalDate(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            || \DateTimeImmutable::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') !== $value) {
            throw new \RuntimeException("Invalid {$key} in sales data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredInteger(array $record, string $key): int
    {
        if (! is_int($record[$key] ?? null)) {
            throw new \RuntimeException("Invalid {$key} in sales data.");
        }

        return $record[$key];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function nonNegativeInteger(array $record, string $key): int
    {
        $value = $this->requiredInteger($record, $key);
        if ($value < 0) {
            throw new \RuntimeException("Invalid {$key} in sales data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function positiveInteger(array $record, string $key): int
    {
        $value = $this->nonNegativeInteger($record, $key);
        if ($value === 0) {
            throw new \RuntimeException("Invalid {$key} in sales data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function nullableNonNegativeInteger(array $record, string $key): ?int
    {
        if (($record[$key] ?? null) === null) {
            return null;
        }

        return $this->nonNegativeInteger($record, $key);
    }
}
