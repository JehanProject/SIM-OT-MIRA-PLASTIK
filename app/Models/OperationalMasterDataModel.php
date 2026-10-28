<?php

namespace App\Models;

use CodeIgniter\Database\BaseConnection;

class OperationalMasterDataModel
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function products(): array
    {
        return array_map(static fn (array $row): array => [
            'id'           => $row['id'],
            'code'         => $row['code'],
            'name'         => $row['name'],
            'category'     => $row['category'],
            'unit'         => $row['unit'],
            'buy'          => (int) $row['buy'],
            'retail'       => (int) $row['retail'],
            'wholesale'    => (int) $row['wholesale'],
            'wholesaleMin' => (int) $row['wholesale_min'],
            'shop'         => (int) $row['shop'],
            'warehouse'    => (int) $row['warehouse'],
            'min'          => (int) $row['minimum_stock'],
            'active'       => (bool) $row['active'],
        ], $this->db->table('products')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customers(): array
    {
        return array_map(static fn (array $row): array => [
            'id'          => $row['id'],
            'name'        => $row['name'],
            'phone'       => $row['phone'] ?? '',
            'creditLimit' => (int) $row['credit_limit'],
            'creditDays'  => (int) $row['credit_days'],
            'active'      => (bool) $row['active'],
            'createdAt'   => $row['created_at'],
        ], $this->db->table('customers')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    public function replaceProducts(array $products): void
    {
        $rows = $this->productRows($products);
        $this->db->table('products')->where('id IS NOT NULL', null, false)->delete();
        if ($rows !== [] && $this->db->table('products')->insertBatch($rows) === false) {
            throw new \RuntimeException('Failed to persist product master data.');
        }
    }

    /**
     * @param list<array<string, mixed>> $customers
     */
    public function replaceCustomers(array $customers): void
    {
        $rows = $this->customerRows($customers);
        $this->db->table('customers')->where('id IS NOT NULL', null, false)->delete();
        if ($rows !== [] && $this->db->table('customers')->insertBatch($rows) === false) {
            throw new \RuntimeException('Failed to persist customer master data.');
        }
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    public function validateProducts(array $products): void
    {
        $this->productRows($products);
    }

    /**
     * @param list<array<string, mixed>> $customers
     */
    public function validateCustomers(array $customers): void
    {
        $this->customerRows($customers);
    }

    /**
     * @param list<array<string, mixed>> $products
     * @return list<array<string, mixed>>
     */
    private function productRows(array $products): array
    {
        if (! array_is_list($products)) {
            throw new \InvalidArgumentException('Product master data must be a list.');
        }
        $rows = [];
        $ids = [];
        $codes = [];
        foreach ($products as $position => $product) {
            if (! is_array($product)) {
                throw new \InvalidArgumentException('Invalid product record in operational master data.');
            }
            $row = $this->productRow($product, $position);
            if (isset($ids[$row['id']]) || isset($codes[$row['code']])) {
                throw new \InvalidArgumentException('Duplicate product ID or code in operational master data.');
            }
            $ids[$row['id']] = true;
            $codes[$row['code']] = true;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $customers
     * @return list<array<string, mixed>>
     */
    private function customerRows(array $customers): array
    {
        if (! array_is_list($customers)) {
            throw new \InvalidArgumentException('Customer master data must be a list.');
        }
        $rows = [];
        $ids = [];
        foreach ($customers as $position => $customer) {
            if (! is_array($customer)) {
                throw new \InvalidArgumentException('Invalid customer record in operational master data.');
            }
            $row = $this->customerRow($customer, $position);
            if (isset($ids[$row['id']])) {
                throw new \InvalidArgumentException('Duplicate customer ID in operational master data.');
            }
            $ids[$row['id']] = true;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function productRow(array $product, int $position): array
    {
        return [
            'id'            => $this->requiredString($product, 'id', 100),
            'position'      => $position,
            'code'          => $this->requiredString($product, 'code', 50),
            'name'          => $this->requiredString($product, 'name', 120),
            'category'      => $this->requiredString($product, 'category', 100),
            'unit'          => $this->requiredString($product, 'unit', 30),
            'buy'           => $this->requiredNonNegativeInteger($product, 'buy'),
            'retail'        => $this->requiredNonNegativeInteger($product, 'retail'),
            'wholesale'     => $this->requiredNonNegativeInteger($product, 'wholesale'),
            'wholesale_min' => $this->requiredPositiveInteger($product, 'wholesaleMin'),
            'shop'          => $this->requiredNonNegativeInteger($product, 'shop'),
            'warehouse'     => $this->requiredNonNegativeInteger($product, 'warehouse'),
            'minimum_stock' => $this->requiredNonNegativeInteger($product, 'min'),
            'active'        => $this->requiredBoolean($product, 'active'),
        ];
    }

    /**
     * @param array<string, mixed> $customer
     * @return array<string, mixed>
     */
    private function customerRow(array $customer, int $position): array
    {
        return [
            'id'           => $this->requiredString($customer, 'id', 100),
            'position'     => $position,
            'name'         => $this->requiredString($customer, 'name', 120),
            'phone'        => $this->optionalString($customer, 'phone', 40),
            'credit_limit' => $this->requiredNonNegativeInteger($customer, 'creditLimit'),
            'credit_days'  => $this->requiredPositiveInteger($customer, 'creditDays'),
            'active'       => $this->requiredBoolean($customer, 'active'),
            'created_at'   => $this->optionalString($customer, 'createdAt', 40),
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredString(array $record, string $field, int $maxLength): string
    {
        $value = $record[$field] ?? null;
        if (! is_string($value) || trim($value) === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$field} value in operational master data.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalString(array $record, string $field, int $maxLength): ?string
    {
        $value = $record[$field] ?? '';
        if (! is_string($value) || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$field} value in operational master data.");
        }

        return trim($value) === '' ? null : trim($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredNonNegativeInteger(array $record, string $field): int
    {
        $value = $record[$field] ?? null;
        if (! is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Invalid {$field} value in operational master data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredPositiveInteger(array $record, string $field): int
    {
        $value = $this->requiredNonNegativeInteger($record, $field);
        if ($value === 0) {
            throw new \InvalidArgumentException("Invalid {$field} value in operational master data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredBoolean(array $record, string $field): bool
    {
        if (! is_bool($record[$field] ?? null)) {
            throw new \InvalidArgumentException("Invalid {$field} value in operational master data.");
        }

        return $record[$field];
    }
}
