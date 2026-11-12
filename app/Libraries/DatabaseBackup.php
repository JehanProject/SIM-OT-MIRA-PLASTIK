<?php

namespace App\Libraries;

use RuntimeException;

class DatabaseBackup
{
    public function create(): string
    {
        $directory = WRITEPATH . 'backups';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori backup tidak dapat dibuat.');
        }

        $db = db_connect();
        $tables = $db->listTables();
        sort($tables);
        $tableData = [];
        $schema = [];
        foreach ($tables as $table) {
            $schema[$table] = array_map(
                static fn (object $field): array => [
                    'name' => $field->name,
                    'type' => $field->type,
                    'max_length' => $field->max_length ?? null,
                    'nullable' => $field->nullable ?? null,
                    'primary_key' => $field->primary_key ?? null,
                ],
                $db->getFieldData($table),
            );
            $tableData[$table] = $db->table($table)->get()->getResultArray();
        }

        $payload = [
            'format' => 'mira-plastik-json-backup',
            'version' => 1,
            'database' => $db->getDatabase(),
            'created_at' => date(DATE_ATOM),
            'schema' => $schema,
            'tables' => $tableData,
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $path = $directory . DIRECTORY_SEPARATOR . 'mira-backup-' . date('Ymd-His') . '.json';
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('File backup tidak dapat ditulis.');
        }
        @chmod($path, 0600);

        return $path;
    }

    public function purgeExpired(): void
    {
        $cutoff = time() - 30 * 86400;
        foreach (glob(WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'mira-backup-*.json') ?: [] as $path) {
            if (is_file($path) && (filemtime($path) ?: 0) < $cutoff && ! unlink($path)) {
                log_message('warning', 'File backup lama tidak dapat dihapus: {path}', ['path' => basename($path)]);
            }
        }
    }
}
