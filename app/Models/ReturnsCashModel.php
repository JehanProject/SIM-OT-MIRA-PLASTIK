<?php

namespace App\Models;

use CodeIgniter\Database\BaseConnection;

class ReturnsCashModel
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function returns(): array
    {
        $itemsByReturn = [];
        foreach ($this->db->table('return_items')->orderBy('return_id')->orderBy('position')->get()->getResultArray() as $row) {
            $item = ['productId' => $row['product_id']];
            if ($row['code'] !== null) {
                $item['code'] = $row['code'];
            }
            $item['name'] = $row['name'];
            if ($row['unit'] !== null) {
                $item['unit'] = $row['unit'];
            }
            $item['quantity'] = (int) $row['quantity'];
            $item['price'] = (int) $row['price'];
            $item['cost'] = (int) $row['cost'];
            if ($row['amount'] !== null) {
                $item['amount'] = (int) $row['amount'];
            }
            $item['restocked'] = (bool) $row['restocked'];
            $itemsByReturn[$row['return_id']][] = $item;
        }

        $returns = [];
        foreach ($this->db->table('return_adjustments')->orderBy('position')->get()->getResultArray() as $row) {
            $item = [
                'id'         => $row['id'],
                'saleId'     => $row['sale_id'],
                'number'     => $row['number'],
                'type'       => $row['type'],
                'status'     => $row['status'],
                'amount'     => (int) $row['amount'],
            ];
            $returnItems = $itemsByReturn[$row['id']] ?? [];
            if ($row['cash_refund'] !== null) {
                $item['cashRefund'] = (int) $row['cash_refund'];
                if ($row['refund_method'] !== null) {
                    $item['refundMethod'] = $row['refund_method'];
                }
                $item['items'] = $returnItems;
                $item['reason'] = $row['reason'];
                $item['date'] = $row['return_date'];
                $item['createdAt'] = $row['created_at'];
                if ($row['user_id'] !== null) {
                    $item['userId'] = (int) $row['user_id'];
                }
                $item['userName'] = $row['user_name'];
                foreach (['approved_by' => 'approvedBy', 'approved_at' => 'approvedAt'] as $column => $key) {
                    if ($row[$column] !== null) {
                        $item[$key] = $row[$column];
                    }
                }
            } else {
                $item['items'] = $returnItems;
                $item['reason'] = $row['reason'];
                if ($row['refund_method'] !== null) {
                    $item['refundMethod'] = $row['refund_method'];
                }
                $item['date'] = $row['return_date'];
                $item['createdAt'] = $row['created_at'];
                if ($row['user_id'] !== null) {
                    $item['userId'] = (int) $row['user_id'];
                }
                $item['userName'] = $row['user_name'];
                foreach (['reviewed_by' => 'reviewedBy', 'reviewed_at' => 'reviewedAt'] as $column => $key) {
                    if ($row[$column] !== null) {
                        $item[$key] = $row[$column];
                    }
                }
            }
            $returns[] = $item;
        }

        return $returns;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cashEntries(): array
    {
        return array_map(static fn (array $row): array => [
            'id'          => $row['id'],
            'date'        => $row['entry_date'],
            'createdAt'   => $row['created_at'],
            'direction'   => $row['direction'],
            'category'    => $row['category'],
            'description' => $row['description'],
            'amount'      => (int) $row['amount'],
            'method'      => $row['method'],
            'userName'    => $row['user_name'],
        ], $this->db->table('cash_entries')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @param list<array<string, mixed>> $returns
     */
    public function validateReturns(array $returns): void
    {
        $this->returnRows($returns);
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    public function validateCashEntries(array $entries): void
    {
        $this->cashEntryRows($entries);
    }

    /**
     * @param list<array<string, mixed>> $returns
     */
    public function replaceReturns(array $returns): void
    {
        [$headers, $items] = $this->returnRows($returns);
        $this->db->table('return_items')->where('return_id !=', '')->delete();
        $this->db->table('return_adjustments')->where('id !=', '')->delete();
        if ($headers !== [] && $this->db->table('return_adjustments')->insertBatch($headers) === false) {
            throw new \RuntimeException('Failed to persist return adjustments.');
        }
        if ($items !== [] && $this->db->table('return_items')->insertBatch($items) === false) {
            throw new \RuntimeException('Failed to persist return items.');
        }
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    public function replaceCashEntries(array $entries): void
    {
        $rows = $this->cashEntryRows($entries);
        $this->db->table('cash_entries')->where('id !=', '')->delete();
        if ($rows !== [] && $this->db->table('cash_entries')->insertBatch($rows) === false) {
            throw new \RuntimeException('Failed to persist cash entries.');
        }
    }

    /**
     * @param list<array<string, mixed>> $returns
     * @return array{list<array<string, mixed>>, list<array<string, mixed>>}
     */
    private function returnRows(array $returns): array
    {
        if (! array_is_list($returns)) {
            throw new \InvalidArgumentException('Return adjustments must be a list.');
        }

        $headers = [];
        $items = [];
        $ids = [];
        foreach ($returns as $position => $return) {
            if (! is_array($return)) {
                throw new \InvalidArgumentException('Invalid return adjustment record.');
            }
            $id = $this->string($return, 'id', 100);
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException('Duplicate return adjustment ID.');
            }
            $ids[$id] = true;
            $type = $this->string($return, 'type', 20);
            $status = $this->string($return, 'status', 40);
            if (! in_array($type, ['Void', 'Retur'], true)
                || ! in_array($status, ['Menunggu Persetujuan', 'Disetujui', 'Ditolak'], true)) {
                throw new \InvalidArgumentException('Invalid return adjustment type or status.');
            }
            $returnItems = $return['items'] ?? null;
            if (! is_array($returnItems) || ! array_is_list($returnItems) || $returnItems === []) {
                throw new \InvalidArgumentException('Return adjustment items must be a non-empty list.');
            }
            $amount = $this->positive($return, 'amount');
            $itemTotal = 0;
            foreach ($returnItems as $item) {
                if (! is_array($item)) {
                    throw new \InvalidArgumentException('Invalid return adjustment item.');
                }
                $price = $this->nonNegative($item, 'price');
                $quantity = $this->positive($item, 'quantity');
                if ($quantity > 4294967295
                    || $quantity > intdiv(PHP_INT_MAX, max(1, $price))
                    || $itemTotal > PHP_INT_MAX - ($price * $quantity)) {
                    throw new \InvalidArgumentException('Return adjustment amount exceeds the supported limit.');
                }
                $itemTotal += $price * $quantity;
            }
            $cashRefund = $this->optionalNonNegative($return, 'cashRefund');
            if ($itemTotal !== $amount || ($cashRefund !== null && $cashRefund > $amount)) {
                throw new \InvalidArgumentException('Return adjustment amount does not match its item details.');
            }
            $headers[] = [
                'id'            => $id,
                'position'      => $position,
                'sale_id'       => $this->string($return, 'saleId', 100),
                'number'        => $this->string($return, 'number', 50),
                'type'          => $type,
                'status'        => $status,
                'amount'        => $amount,
                'cash_refund'   => $cashRefund,
                'refund_method' => $this->optionalEnum($return, 'refundMethod', ['Tunai', 'Transfer']),
                'reason'        => $this->string($return, 'reason', 180),
                'return_date'   => $this->date($return, 'date'),
                'created_at'    => $this->string($return, 'createdAt', 40),
                'user_id'       => $this->optionalUserId($return),
                'user_name'     => $this->string($return, 'userName', 80),
                'approved_by'   => $this->optionalString($return, 'approvedBy', 80),
                'approved_at'   => $this->optionalString($return, 'approvedAt', 40),
                'reviewed_by'   => $this->optionalString($return, 'reviewedBy', 80),
                'reviewed_at'   => $this->optionalString($return, 'reviewedAt', 40),
            ];

            foreach ($returnItems as $itemPosition => $item) {
                if (! is_array($item)) {
                    throw new \InvalidArgumentException('Invalid return adjustment item.');
                }
                $itemPrice = $this->nonNegative($item, 'price');
                $quantity = $this->positive($item, 'quantity');
                if ($quantity > 4294967295) {
                    throw new \InvalidArgumentException('Return item quantity exceeds the supported limit.');
                }
                $itemAmount = array_key_exists('amount', $item)
                    ? $this->nonNegative($item, 'amount')
                    : null;
                if ($itemAmount !== null && ($quantity > intdiv(PHP_INT_MAX, max(1, $itemPrice))
                    || $itemAmount !== $itemPrice * $quantity)) {
                    throw new \InvalidArgumentException('Return item amount does not match its quantity and price.');
                }
                $items[] = [
                    'return_id' => $id,
                    'position'  => $itemPosition,
                    'product_id' => $this->string($item, 'productId', 100),
                    'code'      => $this->optionalString($item, 'code', 50),
                    'name'      => $this->string($item, 'name', 120),
                    'unit'      => $this->optionalString($item, 'unit', 30),
                    'quantity'  => $quantity,
                    'price'     => $itemPrice,
                    'cost'      => $this->nonNegative($item, 'cost'),
                    'amount'    => $itemAmount,
                    'restocked' => $this->boolean($item, 'restocked'),
                ];
            }
        }

        return [$headers, $items];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function cashEntryRows(array $entries): array
    {
        if (! array_is_list($entries)) {
            throw new \InvalidArgumentException('Cash entries must be a list.');
        }
        $rows = [];
        $ids = [];
        foreach ($entries as $position => $entry) {
            if (! is_array($entry)) {
                throw new \InvalidArgumentException('Invalid cash entry record.');
            }
            $id = $this->string($entry, 'id', 100);
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException('Duplicate cash entry ID.');
            }
            $ids[$id] = true;
            $direction = $this->string($entry, 'direction', 10);
            $method = $this->string($entry, 'method', 20);
            if (! in_array($direction, ['Masuk', 'Keluar'], true)
                || ! in_array($method, ['Tunai', 'Transfer'], true)) {
                throw new \InvalidArgumentException('Invalid cash entry direction or method.');
            }
            $rows[] = [
                'id'          => $id,
                'position'    => $position,
                'entry_date'  => $this->date($entry, 'date'),
                'created_at'  => $this->string($entry, 'createdAt', 40),
                'direction'   => $direction,
                'category'    => $this->string($entry, 'category', 60),
                'description' => $this->string($entry, 'description', 180),
                'amount'      => $this->positive($entry, 'amount'),
                'method'      => $method,
                'user_name'   => $this->string($entry, 'userName', 80),
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
            throw new \InvalidArgumentException("Invalid {$field} in financial data.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalString(array $record, string $field, int $maxLength): ?string
    {
        if (! array_key_exists($field, $record)) {
            return null;
        }
        $value = $record[$field];
        if (! is_string($value) || trim($value) === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$field} in financial data.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function integer(array $record, string $field): int
    {
        if (! is_int($record[$field] ?? null)) {
            throw new \InvalidArgumentException("Invalid {$field} in financial data.");
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
            throw new \InvalidArgumentException("Invalid {$field} in financial data.");
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
            throw new \InvalidArgumentException("Invalid {$field} in financial data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalNonNegative(array $record, string $field): ?int
    {
        if (! array_key_exists($field, $record)) {
            return null;
        }

        return $this->nonNegative($record, $field);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function boolean(array $record, string $field): bool
    {
        if (! is_bool($record[$field] ?? null)) {
            throw new \InvalidArgumentException("Invalid {$field} in financial data.");
        }

        return $record[$field];
    }

    /**
     * @param array<string, mixed> $record
     * @param list<string> $allowed
     */
    private function optionalEnum(array $record, string $field, array $allowed): ?string
    {
        if (! array_key_exists($field, $record)) {
            return null;
        }
        if (! is_string($record[$field]) || ! in_array($record[$field], $allowed, true)) {
            throw new \InvalidArgumentException("Invalid {$field} in financial data.");
        }

        return $record[$field];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalUserId(array $record): ?int
    {
        if (! array_key_exists('userId', $record)) {
            return null;
        }
        if (! is_int($record['userId']) || $record['userId'] < 1) {
            throw new \InvalidArgumentException('Invalid userId in financial data.');
        }

        return $record['userId'];
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
            throw new \InvalidArgumentException("Invalid {$field} in financial data.");
        }

        return $value;
    }
}
