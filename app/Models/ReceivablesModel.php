<?php

namespace App\Models;

use CodeIgniter\Database\BaseConnection;

class ReceivablesModel
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
        $paymentsByReceivable = [];
        foreach ($this->db->table('receivable_payments')->orderBy('receivable_id')->orderBy('position')->get()->getResultArray() as $payment) {
            $paymentsByReceivable[$payment['receivable_id']][] = [
                'id'        => $payment['id'],
                'amount'    => (int) $payment['amount'],
                'method'    => $payment['method'],
                'userName'  => $payment['user_name'],
                'date'      => $payment['payment_date'],
                'createdAt' => $payment['created_at'],
            ];
        }

        return array_map(static fn (array $row): array => [
            'id'         => $row['id'],
            'saleId'     => $row['sale_id'],
            'number'     => $row['number'],
            'customer'   => $row['customer'],
            'customerId' => $row['customer_id'],
            'total'      => (int) $row['total'],
            'paid'       => (int) $row['paid'],
            'dueDate'    => $row['due_date'],
            'date'       => $row['sale_date'],
            'createdAt'  => $row['created_at'],
            'payments'   => $paymentsByReceivable[$row['id']] ?? [],
        ], $this->db->table('receivables')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function writeoffs(): array
    {
        return array_map(static fn (array $row): array => [
            'id'           => $row['id'],
            'receivableId' => $row['receivable_id'],
            'saleId'       => $row['sale_id'],
            'number'       => $row['number'],
            'customer'     => $row['customer'],
            'amount'       => (int) $row['amount'],
            'reason'       => $row['reason'],
            'date'         => $row['writeoff_date'],
            'createdAt'    => $row['created_at'],
            'userName'     => $row['user_name'],
        ], $this->db->table('receivable_writeoffs')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @param list<array<string, mixed>> $receivables
     * @param list<array<string, mixed>> $writeoffs
     */
    public function validate(array $receivables, array $writeoffs): void
    {
        $this->rows($receivables, $writeoffs);
    }

    /**
     * @param list<array<string, mixed>> $receivables
     * @param list<array<string, mixed>> $writeoffs
     */
    public function replace(array $receivables, array $writeoffs): void
    {
        [$receivableRows, $paymentRows, $writeoffRows] = $this->rows($receivables, $writeoffs);

        $this->db->table('receivable_writeoffs')->where('id IS NOT NULL', null, false)->delete();
        $this->db->table('receivable_payments')->where('id IS NOT NULL', null, false)->delete();
        $this->db->table('receivables')->where('id IS NOT NULL', null, false)->delete();

        if ($receivableRows !== [] && $this->db->table('receivables')->insertBatch($receivableRows) === false) {
            throw new \RuntimeException('Failed to persist receivables.');
        }
        if ($paymentRows !== [] && $this->db->table('receivable_payments')->insertBatch($paymentRows) === false) {
            throw new \RuntimeException('Failed to persist receivable payments.');
        }
        if ($writeoffRows !== [] && $this->db->table('receivable_writeoffs')->insertBatch($writeoffRows) === false) {
            throw new \RuntimeException('Failed to persist receivable write-offs.');
        }
    }

    /**
     * @param list<array<string, mixed>> $receivables
     * @param list<array<string, mixed>> $writeoffs
     * @return array{
     *   0:list<array<string, mixed>>,
     *   1:list<array<string, mixed>>,
     *   2:list<array<string, mixed>>
     * }
     */
    private function rows(array $receivables, array $writeoffs): array
    {
        if (! array_is_list($receivables) || ! array_is_list($writeoffs)) {
            throw new \InvalidArgumentException('Receivable data must be lists.');
        }

        $receivableRows = [];
        $paymentRows = [];
        $writeoffRows = [];
        $receivablesById = [];
        $saleIds = [];
        $paymentIds = [];
        foreach ($receivables as $position => $receivable) {
            if (! is_array($receivable)) {
                throw new \InvalidArgumentException('Invalid receivable record.');
            }
            $id = $this->requiredString($receivable, 'id', 100);
            $saleId = $this->requiredString($receivable, 'saleId', 100);
            if (isset($receivablesById[$id]) || isset($saleIds[$saleId])) {
                throw new \InvalidArgumentException('Duplicate receivable or sale ID.');
            }
            $receivablesById[$id] = $receivable;
            $saleIds[$saleId] = true;
            $total = $this->nonNegativeInteger($receivable, 'total');
            $paid = $this->nonNegativeInteger($receivable, 'paid');
            $payments = $receivable['payments'] ?? null;
            if (! is_array($payments) || ! array_is_list($payments)) {
                throw new \InvalidArgumentException('Receivable payments must be a list.');
            }
            $paymentSum = 0;
            foreach ($payments as $paymentPosition => $payment) {
                if (! is_array($payment)) {
                    throw new \InvalidArgumentException('Invalid receivable payment record.');
                }
                $paymentId = $this->requiredString($payment, 'id', 100);
                if (isset($paymentIds[$paymentId])) {
                    throw new \InvalidArgumentException('Duplicate receivable payment ID.');
                }
                $paymentIds[$paymentId] = true;
                $amount = $this->positiveInteger($payment, 'amount');
                $method = $this->requiredString($payment, 'method', 20);
                if (! in_array($method, ['Tunai', 'Transfer'], true)) {
                    throw new \InvalidArgumentException('Invalid receivable payment method.');
                }
                $paymentSum += $amount;
                $paymentRows[] = [
                    'id'            => $paymentId,
                    'receivable_id' => $id,
                    'position'      => $paymentPosition,
                    'amount'        => $amount,
                    'method'        => $method,
                    'user_name'     => $this->requiredString($payment, 'userName', 80),
                    'payment_date'  => $this->optionalDate($payment, 'date'),
                    'created_at'    => $this->requiredString($payment, 'createdAt', 40),
                ];
            }
            if ($paymentSum < $paid || $paid > $total) {
                throw new \InvalidArgumentException('Receivable paid balance does not match its payment history.');
            }
            $receivableRows[] = [
                'id'          => $id,
                'position'    => $position,
                'sale_id'     => $saleId,
                'number'      => $this->requiredString($receivable, 'number', 50),
                'customer'    => $this->requiredString($receivable, 'customer', 120),
                'customer_id' => $this->optionalString($receivable, 'customerId', 100),
                'total'       => $total,
                'paid'        => $paid,
                'due_date'    => $this->optionalDate($receivable, 'dueDate'),
                'sale_date'   => $this->optionalDate($receivable, 'date'),
                'created_at'  => $this->requiredString($receivable, 'createdAt', 40),
            ];
        }

        $writeoffIds = [];
        $writeoffTotals = [];
        foreach ($writeoffs as $position => $writeoff) {
            if (! is_array($writeoff)) {
                throw new \InvalidArgumentException('Invalid receivable write-off record.');
            }
            $id = $this->requiredString($writeoff, 'id', 100);
            $receivableId = $this->requiredString($writeoff, 'receivableId', 100);
            if (isset($writeoffIds[$id]) || ! isset($receivablesById[$receivableId])) {
                throw new \InvalidArgumentException('Duplicate or orphaned receivable write-off.');
            }
            $receivable = $receivablesById[$receivableId];
            if ((string) ($writeoff['saleId'] ?? '') !== (string) ($receivable['saleId'] ?? '')
                || (string) ($writeoff['number'] ?? '') !== (string) ($receivable['number'] ?? '')
                || (string) ($writeoff['customer'] ?? '') !== (string) ($receivable['customer'] ?? '')) {
                throw new \InvalidArgumentException('Receivable write-off does not match its receivable.');
            }
            $writeoffIds[$id] = true;
            $amount = $this->positiveInteger($writeoff, 'amount');
            $writeoffTotals[$receivableId] = ($writeoffTotals[$receivableId] ?? 0) + $amount;
            $writeoffRows[] = [
                'id'            => $id,
                'position'      => $position,
                'receivable_id' => $receivableId,
                'sale_id'       => $this->requiredString($writeoff, 'saleId', 100),
                'number'        => $this->requiredString($writeoff, 'number', 50),
                'customer'      => $this->requiredString($writeoff, 'customer', 120),
                'amount'        => $amount,
                'reason'        => $this->requiredString($writeoff, 'reason', 180),
                'writeoff_date' => $this->optionalDate($writeoff, 'date'),
                'created_at'    => $this->requiredString($writeoff, 'createdAt', 40),
                'user_name'     => $this->requiredString($writeoff, 'userName', 80),
            ];
        }
        foreach ($writeoffTotals as $receivableId => $writtenOff) {
            $receivable = $receivablesById[$receivableId];
            if ((int) $receivable['paid'] + $writtenOff > (int) $receivable['total']) {
                throw new \InvalidArgumentException('Receivable payments and write-offs exceed the balance.');
            }
        }

        return [$receivableRows, $paymentRows, $writeoffRows];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredString(array $record, string $key, int $maxLength): string
    {
        $value = $record[$key] ?? null;
        if (! is_string($value) || trim($value) === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$key} in receivable data.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalString(array $record, string $key, int $maxLength): ?string
    {
        $value = $record[$key] ?? null;
        if ($value !== null && (! is_string($value) || strlen($value) > $maxLength)) {
            throw new \InvalidArgumentException("Invalid {$key} in receivable data.");
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
            throw new \InvalidArgumentException("Invalid {$key} in receivable data.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function nonNegativeInteger(array $record, string $key): int
    {
        $value = $record[$key] ?? null;
        if (! is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Invalid {$key} in receivable data.");
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
            throw new \InvalidArgumentException("Invalid {$key} in receivable data.");
        }

        return $value;
    }
}
