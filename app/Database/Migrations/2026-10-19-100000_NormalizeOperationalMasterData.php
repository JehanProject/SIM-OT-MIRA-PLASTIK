<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeOperationalMasterData extends Migration
{
    public function up(): void
    {
        $this->createProductsTable();
        $this->createCustomersTable();

        $row = $this->db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Operational state row is missing; master data cannot be migrated.');
        }

        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        foreach (['products', 'customers'] as $collection) {
            if (! is_array($state[$collection] ?? null) || ! array_is_list($state[$collection])) {
                throw new \RuntimeException("The {$collection} collection is invalid; master data cannot be migrated.");
            }
        }

        if (! $this->db->transBegin()) {
            throw new \RuntimeException('A transaction could not be started for master data migration.');
        }
        try {
            foreach ($state['products'] as $position => $product) {
                if (! is_array($product)) {
                    throw new \RuntimeException('A product record is invalid; master data cannot be migrated.');
                }
                $this->db->table('products')->insert($this->productRow($product, $position));
            }
            foreach ($state['customers'] as $position => $customer) {
                if (! is_array($customer)) {
                    throw new \RuntimeException('A customer record is invalid; master data cannot be migrated.');
                }
                $this->db->table('customers')->insert($this->customerRow($customer, $position));
            }

            unset($state['products'], $state['customers']);
            $this->db->table('application_state')->where('id', 1)->update([
                'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Master data migration failed; the original state was preserved.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Master data migration could not be committed.');
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
            throw new \RuntimeException('Operational state row is missing; master data cannot be restored.');
        }

        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $state['products'] = array_map(
            static fn (array $product): array => [
                'id'           => $product['id'],
                'code'         => $product['code'],
                'name'         => $product['name'],
                'category'     => $product['category'],
                'unit'         => $product['unit'],
                'buy'          => (int) $product['buy'],
                'retail'       => (int) $product['retail'],
                'wholesale'    => (int) $product['wholesale'],
                'wholesaleMin' => (int) $product['wholesale_min'],
                'shop'         => (int) $product['shop'],
                'warehouse'    => (int) $product['warehouse'],
                'min'          => (int) $product['minimum_stock'],
                'active'       => (bool) $product['active'],
            ],
            $this->db->table('products')->orderBy('position')->get()->getResultArray(),
        );
        $state['customers'] = array_map(
            static fn (array $customer): array => [
                'id'          => $customer['id'],
                'name'        => $customer['name'],
                'phone'       => $customer['phone'],
                'creditLimit' => (int) $customer['credit_limit'],
                'creditDays'  => (int) $customer['credit_days'],
                'active'      => (bool) $customer['active'],
                'createdAt'   => $customer['created_at'],
            ],
            $this->db->table('customers')->orderBy('position')->get()->getResultArray(),
        );
        $this->db->table('application_state')->where('id', 1)->update([
            'payload'    => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->forge->dropTable('customers', true);
        $this->forge->dropTable('products', true);
    }

    private function createProductsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 30],
            'buy' => ['type' => 'BIGINT', 'constraint' => 20],
            'retail' => ['type' => 'BIGINT', 'constraint' => 20],
            'wholesale' => ['type' => 'BIGINT', 'constraint' => 20],
            'wholesale_min' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'shop' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'warehouse' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'minimum_stock' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'active' => ['type' => 'BOOLEAN', 'default' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code', 'products_code_unique');
        $this->forge->addKey('position', false, false, 'products_position_index');
        $this->forge->createTable('products', true);
    }

    private function createCustomersTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'position' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'credit_limit' => ['type' => 'BIGINT', 'constraint' => 20, 'default' => 0],
            'credit_days' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 1],
            'active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('position', false, false, 'customers_position_index');
        $this->forge->createTable('customers', true);
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function productRow(array $product, int $position): array
    {
        return [
            'id'            => $this->requiredString($product, 'id'),
            'position'      => $position,
            'code'          => $this->requiredString($product, 'code'),
            'name'          => $this->requiredString($product, 'name'),
            'category'      => $this->requiredString($product, 'category'),
            'unit'          => $this->requiredString($product, 'unit'),
            'buy'           => $this->requiredInteger($product, 'buy'),
            'retail'        => $this->requiredInteger($product, 'retail'),
            'wholesale'     => $this->requiredInteger($product, 'wholesale'),
            'wholesale_min' => $this->requiredPositiveInteger($product, 'wholesaleMin'),
            'shop'          => $this->requiredInteger($product, 'shop'),
            'warehouse'     => $this->requiredInteger($product, 'warehouse'),
            'minimum_stock' => $this->requiredInteger($product, 'min'),
            'active'        => $this->optionalBoolean($product, 'active'),
        ];
    }

    /**
     * @param array<string, mixed> $customer
     * @return array<string, mixed>
     */
    private function customerRow(array $customer, int $position): array
    {
        return [
            'id'           => $this->requiredString($customer, 'id'),
            'position'     => $position,
            'name'         => $this->requiredString($customer, 'name'),
            'phone'        => isset($customer['phone']) ? (string) $customer['phone'] : null,
            'credit_limit' => $this->requiredInteger($customer, 'creditLimit'),
            'credit_days'  => $this->requiredPositiveInteger($customer, 'creditDays'),
            'active'       => $this->optionalBoolean($customer, 'active'),
            'created_at'   => $this->optionalString($customer, 'createdAt'),
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredString(array $record, string $key): string
    {
        if (! is_string($record[$key] ?? null) || trim($record[$key]) === '') {
            throw new \RuntimeException("Invalid {$key} in operational master data.");
        }

        return $record[$key];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredInteger(array $record, string $key): int
    {
        if (! is_int($record[$key] ?? null) || $record[$key] < 0) {
            throw new \RuntimeException("Invalid {$key} in operational master data.");
        }

        return $record[$key];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredPositiveInteger(array $record, string $key): int
    {
        $value = $this->requiredInteger($record, $key);
        if ($value === 0) {
            throw new \RuntimeException("Invalid {$key} in operational master data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalBoolean(array $record, string $key): bool
    {
        $value = $record[$key] ?? true;
        if (! is_bool($value)) {
            throw new \RuntimeException("Invalid {$key} in operational master data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalString(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new \RuntimeException("Invalid {$key} in operational master data.");
        }

        return $value;
    }
}
