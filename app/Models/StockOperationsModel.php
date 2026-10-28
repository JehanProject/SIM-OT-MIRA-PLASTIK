<?php

namespace App\Models;

use CodeIgniter\Database\BaseConnection;

class StockOperationsModel
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function movements(): array
    {
        return array_map(static fn (array $row): array => [
            'id'          => $row['id'],
            'productId'   => $row['product_id'],
            'productCode' => $row['product_code'],
            'productName' => $row['product_name'],
            'type'        => $row['type'],
            'location'    => $row['location'],
            'delta'       => (int) $row['delta'],
            'unit'        => $row['unit'],
            'loss'        => (int) $row['loss'],
            'reason'      => $row['reason'],
            'userName'    => $row['user_name'],
            'createdAt'   => $row['created_at'],
        ], $this->db->table('stock_movements')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function receipts(): array
    {
        return array_map(static function (array $row): array {
            $receipt = [
                'id'          => $row['id'],
                'productId'   => $row['product_id'],
                'productCode' => $row['product_code'],
                'productName' => $row['product_name'],
                'unit'        => $row['unit'],
                'distributor' => $row['distributor'],
                'invoice'     => $row['invoice'],
                'quantity'    => (int) $row['quantity'],
                'price'       => (int) $row['price'],
                'total'       => (int) $row['total'],
                'date'        => $row['receipt_date'],
                'createdAt'   => $row['created_at'],
                'userName'    => $row['user_name'],
            ];
            if ($row['method'] !== null) {
                $receipt['method'] = $row['method'];
            }

            return $receipt;
        }, $this->db->table('stock_receipts')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @param list<array<string, mixed>> $movements
     */
    public function validateMovements(array $movements): void
    {
        $this->movementRows($movements);
    }

    /**
     * @param list<array<string, mixed>> $receipts
     */
    public function validateReceipts(array $receipts): void
    {
        $this->receiptRows($receipts);
    }

    /**
     * @param list<array<string, mixed>> $movements
     */
    public function replaceMovements(array $movements): void
    {
        $rows = $this->movementRows($movements);
        $this->db->table('stock_movements')->where('id !=', '')->delete();
        if ($rows !== [] && $this->db->table('stock_movements')->insertBatch($rows) === false) {
            throw new \RuntimeException('Failed to persist stock movements.');
        }
    }

    /**
     * @param list<array<string, mixed>> $receipts
     */
    public function replaceReceipts(array $receipts): void
    {
        $rows = $this->receiptRows($receipts);
        $this->db->table('stock_receipts')->where('id !=', '')->delete();
        if ($rows !== [] && $this->db->table('stock_receipts')->insertBatch($rows) === false) {
            throw new \RuntimeException('Failed to persist stock receipts.');
        }
    }

    /**
     * @param list<array<string, mixed>> $movements
     * @return list<array<string, mixed>>
     */
    private function movementRows(array $movements): array
    {
        if (! array_is_list($movements)) {
            throw new \InvalidArgumentException('Stock movements must be a list.');
        }
        $rows = [];
        $ids = [];
        foreach ($movements as $position => $movement) {
            if (! is_array($movement)) {
                throw new \InvalidArgumentException('Invalid stock movement record.');
            }
            $id = $this->string($movement, 'id', 100);
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException('Duplicate stock movement ID.');
            }
            $ids[$id] = true;
            $rows[] = [
                'id'           => $id,
                'position'     => $position,
                'product_id'   => $this->string($movement, 'productId', 100),
                'product_code' => $this->string($movement, 'productCode', 50),
                'product_name' => $this->string($movement, 'productName', 120),
                'type'         => $this->string($movement, 'type', 40),
                'location'     => $this->string($movement, 'location', 40),
                'delta'        => $this->integer($movement, 'delta'),
                'unit'         => $this->string($movement, 'unit', 30),
                'loss'         => $this->nonNegative($movement, 'loss'),
                'reason'       => $this->string($movement, 'reason', 1000),
                'user_name'    => $this->string($movement, 'userName', 80),
                'created_at'   => $this->string($movement, 'createdAt', 40),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $receipts
     * @return list<array<string, mixed>>
     */
    private function receiptRows(array $receipts): array
    {
        if (! array_is_list($receipts)) {
            throw new \InvalidArgumentException('Stock receipts must be a list.');
        }
        $rows = [];
        $ids = [];
        $invoices = [];
        foreach ($receipts as $position => $receipt) {
            if (! is_array($receipt)) {
                throw new \InvalidArgumentException('Invalid stock receipt record.');
            }
            $id = $this->string($receipt, 'id', 100);
            $invoice = $this->string($receipt, 'invoice', 50);
            if (isset($ids[$id]) || isset($invoices[mb_strtolower($invoice)])) {
                throw new \InvalidArgumentException('Duplicate stock receipt ID or invoice.');
            }
            $ids[$id] = true;
            $invoices[mb_strtolower($invoice)] = true;
            $quantity = $this->positive($receipt, 'quantity');
            $price = $this->nonNegative($receipt, 'price');
            $total = $this->nonNegative($receipt, 'total');
            if ($quantity > 4294967295
                || $quantity > intdiv(PHP_INT_MAX, max(1, $price))
                || $quantity * $price !== $total) {
                throw new \InvalidArgumentException('Stock receipt total does not match its quantity and price.');
            }
            $rows[] = [
                'id'           => $id,
                'position'     => $position,
                'product_id'   => $this->string($receipt, 'productId', 100),
                'product_code' => $this->string($receipt, 'productCode', 50),
                'product_name' => $this->string($receipt, 'productName', 120),
                'unit'         => $this->string($receipt, 'unit', 30),
                'distributor'  => $this->string($receipt, 'distributor', 100),
                'invoice'      => $invoice,
                'quantity'     => $quantity,
                'price'        => $price,
                'total'        => $total,
                'receipt_date' => $this->date($receipt, 'date'),
                'created_at'   => $this->string($receipt, 'createdAt', 40),
                'user_name'    => $this->string($receipt, 'userName', 80),
                'method'       => $this->optionalString($receipt, 'method', 20),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function string(array $record, string $field, int $maxLength): string
    {
        $value = $record[$field] ?? null;
        if (! is_string($value) || trim($value) === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$field} in stock data.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalString(array $record, string $field, int $maxLength): ?string
    {
        $value = $record[$field] ?? null;
        if ($value !== null && (! is_string($value) || strlen($value) > $maxLength)) {
            throw new \InvalidArgumentException("Invalid {$field} in stock data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function integer(array $record, string $field): int
    {
        if (! is_int($record[$field] ?? null)) {
            throw new \InvalidArgumentException("Invalid {$field} in stock data.");
        }

        return $record[$field];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function nonNegative(array $record, string $field): int
    {
        $value = $this->integer($record, $field);
        if ($value < 0) {
            throw new \InvalidArgumentException("Invalid {$field} in stock data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function positive(array $record, string $field): int
    {
        $value = $this->nonNegative($record, $field);
        if ($value === 0) {
            throw new \InvalidArgumentException("Invalid {$field} in stock data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function date(array $record, string $field): string
    {
        $value = $record[$field] ?? null;
        $date = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value)
            : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException("Invalid {$field} in stock data.");
        }

        return $value;
    }
}
