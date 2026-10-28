<?php

namespace App\Models;

use CodeIgniter\Database\BaseConnection;

class ActivityDistributorsModel
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activity(): array
    {
        return array_map(static function (array $row): array {
            $item = ['id' => $row['id']];
            if ($row['user_id'] !== null) {
                $item['userId'] = ctype_digit($row['user_id']) ? (int) $row['user_id'] : $row['user_id'];
            }
            $item['name'] = $row['name'];
            $item['role'] = $row['role'];
            $item['message'] = $row['message'];
            $item['createdAt'] = $row['created_at'];

            return $item;
        }, $this->db->table('activity_logs')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function distributors(): array
    {
        return array_map(static fn (array $row): array => [
            'id'    => $row['id'],
            'name'  => $row['name'],
            'phone' => $row['phone'],
        ], $this->db->table('distributors')->orderBy('position')->get()->getResultArray());
    }

    /**
     * @param list<array<string, mixed>> $activity
     */
    public function validateActivity(array $activity): void
    {
        $this->activityRows($activity);
    }

    /**
     * @param list<array<string, mixed>> $distributors
     */
    public function validateDistributors(array $distributors): void
    {
        $this->distributorRows($distributors);
    }

    /**
     * @param list<array<string, mixed>> $activity
     */
    public function replaceActivity(array $activity): void
    {
        $rows = $this->activityRows($activity);
        $this->db->table('activity_logs')->where('id !=', '')->delete();
        if ($rows !== [] && $this->db->table('activity_logs')->insertBatch($rows) === false) {
            throw new \RuntimeException('Failed to persist activity logs.');
        }
    }

    /**
     * @param list<array<string, mixed>> $distributors
     */
    public function replaceDistributors(array $distributors): void
    {
        $rows = $this->distributorRows($distributors);
        $this->db->table('distributors')->where('id !=', '')->delete();
        if ($rows !== [] && $this->db->table('distributors')->insertBatch($rows) === false) {
            throw new \RuntimeException('Failed to persist distributors.');
        }
    }

    /**
     * @param list<array<string, mixed>> $activity
     * @return list<array<string, mixed>>
     */
    private function activityRows(array $activity): array
    {
        if (! array_is_list($activity)) {
            throw new \InvalidArgumentException('Activity data must be a list.');
        }
        $rows = [];
        $ids = [];
        foreach ($activity as $position => $item) {
            if (! is_array($item)) {
                throw new \InvalidArgumentException('Invalid activity record.');
            }
            $id = $this->requiredString($item, 'id', 100);
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException('Duplicate activity ID.');
            }
            $ids[$id] = true;
            $userId = $item['userId'] ?? null;
            if ($userId !== null && (! is_string($userId) && ! is_int($userId)
                || trim((string) $userId) === '' || strlen((string) $userId) > 100)) {
                throw new \InvalidArgumentException('Invalid activity userId.');
            }
            $rows[] = [
                'id'         => $id,
                'position'   => $position,
                'user_id'    => $userId === null ? null : (string) $userId,
                'name'       => $this->requiredString($item, 'name', 80),
                'role'       => $this->requiredString($item, 'role', 32),
                'message'    => $this->requiredString($item, 'message', 10000),
                'created_at' => $this->requiredString($item, 'createdAt', 40),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $distributors
     * @return list<array<string, mixed>>
     */
    private function distributorRows(array $distributors): array
    {
        if (! array_is_list($distributors)) {
            throw new \InvalidArgumentException('Distributor data must be a list.');
        }
        $rows = [];
        $ids = [];
        foreach ($distributors as $position => $distributor) {
            if (! is_array($distributor)) {
                throw new \InvalidArgumentException('Invalid distributor record.');
            }
            $id = $this->requiredString($distributor, 'id', 100);
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException('Duplicate distributor ID.');
            }
            $ids[$id] = true;
            $rows[] = [
                'id'       => $id,
                'position' => $position,
                'name'     => $this->requiredString($distributor, 'name', 100),
                'phone'    => $this->optionalString($distributor, 'phone', 40),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function requiredString(array $record, string $field, int $maxLength): string
    {
        $value = $record[$field] ?? null;
        if (! is_string($value) || trim($value) === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$field} in operational data.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function optionalString(array $record, string $field, int $maxLength): string
    {
        $value = $record[$field] ?? '';
        if (! is_string($value) || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$field} in operational data.");
        }

        return trim($value);
    }
}
