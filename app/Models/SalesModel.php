<?php

namespace App\Models;

use CodeIgniter\Database\BaseConnection;

class SalesModel
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $sales = $this->db->table('sales')->orderBy('position')->get()->getResultArray();
        if ($sales === []) {
            return [];
        }

        $itemsBySale = [];
        foreach ($this->db->table('sale_items')->orderBy('sale_id')->orderBy('position')->get()->getResultArray() as $item) {
            $itemsBySale[$item['sale_id']][$item['id']] = [
                'productId' => $item['product_id'],
                'code'      => $item['product_code'],
                'name'      => $item['product_name'],
                'unit'      => $item['unit'],
                'quantity'  => (int) $item['quantity'],
                'price'     => (int) $item['price'],
                'subtotal'  => (int) $item['subtotal'],
                'cost'      => (int) $item['cost'],
                'stockAllocations' => [],
            ];
        }

        $allocationRows = $this->db->table('sale_item_stock_allocations')
            ->orderBy('sale_item_id')
            ->orderBy('position')
            ->get()
            ->getResultArray();
        $itemIdsBySale = [];
        foreach ($itemsBySale as $saleId => $items) {
            foreach ($items as $itemId => $_item) {
                $itemIdsBySale[$itemId] = $saleId;
            }
        }
        foreach ($allocationRows as $allocation) {
            $saleId = $itemIdsBySale[$allocation['sale_item_id']] ?? null;
            if ($saleId === null) {
                throw new \RuntimeException('A stock allocation references a missing sale item.');
            }
            $itemsBySale[$saleId][$allocation['sale_item_id']]['stockAllocations'][] = [
                'location' => $allocation['location'],
                'quantity' => (int) $allocation['quantity'],
            ];
        }

        return array_map(static function (array $sale) use ($itemsBySale): array {
            return [
                'id'           => $sale['id'],
                'number'       => $sale['number'],
                'type'         => $sale['type'],
                'payment'      => $sale['payment'],
                'status'       => $sale['status'],
                'customer'     => $sale['customer'],
                'customerId'   => $sale['customer_id'],
                'dueDate'      => $sale['due_date'],
                'date'         => $sale['sale_date'],
                'total'        => (int) $sale['total'],
                'profit'       => (int) $sale['profit'],
                'paidAmount'   => (int) $sale['paid_amount'],
                'cashReceived' => $sale['cash_received'] === null ? null : (int) $sale['cash_received'],
                'change'       => (int) $sale['change_amount'],
                'userId'       => $sale['user_id'],
                'userName'     => $sale['user_name'],
                'createdAt'    => $sale['created_at'],
                'items'        => array_values($itemsBySale[$sale['id']] ?? []),
            ];
        }, $sales);
    }

    /**
     * @param list<array<string, mixed>> $sales
     */
    public function validate(array $sales): void
    {
        $this->rows($sales);
    }

    /**
     * @param list<array<string, mixed>> $sales
     */
    public function replace(array $sales): void
    {
        [$saleRows, $itemRows, $allocationRows] = $this->rows($sales);

        $this->db->table('sale_item_stock_allocations')->where('sale_item_id IS NOT NULL', null, false)->delete();
        $this->db->table('sale_items')->where('id IS NOT NULL', null, false)->delete();
        $this->db->table('sales')->where('id IS NOT NULL', null, false)->delete();

        if ($saleRows !== [] && $this->db->table('sales')->insertBatch($saleRows) === false) {
            throw new \RuntimeException('Failed to persist sales.');
        }
        if ($itemRows !== [] && $this->db->table('sale_items')->insertBatch($itemRows) === false) {
            throw new \RuntimeException('Failed to persist sale items.');
        }
        if ($allocationRows !== [] && $this->db->table('sale_item_stock_allocations')->insertBatch($allocationRows) === false) {
            throw new \RuntimeException('Failed to persist sale stock allocations.');
        }
    }

    /**
     * @param list<array<string, mixed>> $sales
     * @return array{
     *   0:list<array<string, mixed>>,
     *   1:list<array<string, mixed>>,
     *   2:list<array<string, mixed>>
     * }
     */
    private function rows(array $sales): array
    {
        if (! array_is_list($sales)) {
            throw new \InvalidArgumentException('Sales data must be a list.');
        }

        $saleRows = [];
        $itemRows = [];
        $allocationRows = [];
        $saleIds = [];
        $saleNumbers = [];
        foreach ($sales as $position => $sale) {
            if (! is_array($sale)) {
                throw new \InvalidArgumentException('Invalid sale record.');
            }
            $id = $this->requiredString($sale, 'id', 100);
            $number = $this->requiredString($sale, 'number', 50);
            if (isset($saleIds[$id]) || isset($saleNumbers[$number])) {
                throw new \InvalidArgumentException('Duplicate sale ID or receipt number.');
            }
            $saleIds[$id] = true;
            $saleNumbers[$number] = true;
            $saleRows[] = [
                'id'            => $id,
                'position'      => $position,
                'number'        => $number,
                'type'          => $this->requiredString($sale, 'type', 20),
                'payment'       => $this->requiredString($sale, 'payment', 20),
                'status'        => $this->requiredString($sale, 'status', 30),
                'customer'      => $this->optionalString($sale, 'customer', 120),
                'customer_id'   => $this->optionalString($sale, 'customerId', 100),
                'due_date'      => $this->optionalDate($sale, 'dueDate'),
                'sale_date'     => $this->optionalDate($sale, 'date'),
                'total'         => $this->nonNegativeInteger($sale, 'total'),
                'profit'        => $this->requiredInteger($sale, 'profit'),
                'paid_amount'   => $this->nonNegativeInteger($sale, 'paidAmount'),
                'cash_received' => $this->nullableNonNegativeInteger($sale, 'cashReceived'),
                'change_amount' => $this->nonNegativeInteger($sale, 'change'),
                'user_id'       => $this->requiredIdentifier($sale, 'userId', 100),
                'user_name'     => $this->requiredString($sale, 'userName', 80),
                'created_at'    => $this->requiredString($sale, 'createdAt', 40),
            ];

            $items = $sale['items'] ?? null;
            if (! is_array($items) || ! array_is_list($items) || $items === []) {
                throw new \InvalidArgumentException('Sale items must be a non-empty list.');
            }
            $itemTotal = 0;
            foreach ($items as $itemPosition => $item) {
                if (! is_array($item)) {
                    throw new \InvalidArgumentException('Invalid sale item record.');
                }
                $itemId = $id . ':' . $itemPosition;
                $quantity = $this->positiveInteger($item, 'quantity');
                $price = $this->nonNegativeInteger($item, 'price');
                $cost = $this->nonNegativeInteger($item, 'cost');
                $subtotal = $this->nonNegativeInteger($item, 'subtotal');
                if ($quantity > intdiv(PHP_INT_MAX, max(1, $price)) || $subtotal !== $price * $quantity) {
                    throw new \InvalidArgumentException('Sale item subtotal is inconsistent.');
                }
                $itemTotal += $subtotal;
                $itemRows[] = [
                    'id'            => $itemId,
                    'sale_id'       => $id,
                    'position'      => $itemPosition,
                    'product_id'    => $this->requiredString($item, 'productId', 100),
                    'product_code'  => $this->requiredString($item, 'code', 50),
                    'product_name'  => $this->requiredString($item, 'name', 120),
                    'unit'          => $this->requiredString($item, 'unit', 30),
                    'quantity'      => $quantity,
                    'price'         => $price,
                    'subtotal'      => $subtotal,
                    'cost'          => $cost,
                ];

                $allocations = $item['stockAllocations'] ?? [];
                if (! is_array($allocations) || ! array_is_list($allocations)) {
                    throw new \InvalidArgumentException('Sale stock allocations must be a list.');
                }
                $allocatedQuantity = 0;
                foreach ($allocations as $allocationPosition => $allocation) {
                    if (! is_array($allocation)) {
                        throw new \InvalidArgumentException('Invalid sale stock allocation.');
                    }
                    $location = $this->requiredString($allocation, 'location', 20);
                    if (! in_array($location, ['Toko', 'Gudang'], true)) {
                        throw new \InvalidArgumentException('Invalid sale stock allocation location.');
                    }
                    $allocated = $this->positiveInteger($allocation, 'quantity');
                    $allocatedQuantity += $allocated;
                    $allocationRows[] = [
                        'sale_item_id' => $itemId,
                        'position'     => $allocationPosition,
                        'location'     => $location,
                        'quantity'     => $allocated,
                    ];
                }
                if ($allocations !== [] && $allocatedQuantity !== $quantity) {
                    throw new \InvalidArgumentException('Sale stock allocation quantity is inconsistent.');
                }
            }
            if ($itemTotal !== (int) $sale['total']) {
                throw new \InvalidArgumentException('Sale total does not match its line items.');
            }
        }

        return [$saleRows, $itemRows, $allocationRows];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredString(array $record, string $key, int $maxLength): string
    {
        $value = $record[$key] ?? null;
        if (! is_string($value) || trim($value) === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$key} in sales data.");
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
            throw new \InvalidArgumentException("Invalid {$key} in sales data.");
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
            throw new \InvalidArgumentException("Invalid {$key} in sales data.");
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
            throw new \InvalidArgumentException("Invalid {$key} in sales data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredInteger(array $record, string $key): int
    {
        if (! is_int($record[$key] ?? null)) {
            throw new \InvalidArgumentException("Invalid {$key} in sales data.");
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
            throw new \InvalidArgumentException("Invalid {$key} in sales data.");
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
            throw new \InvalidArgumentException("Invalid {$key} in sales data.");
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
