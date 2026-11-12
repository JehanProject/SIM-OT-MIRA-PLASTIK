<?php

namespace App\Commands;

use App\Libraries\DatabaseBackup;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CreateDatabaseBackup extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'backup:create';
    protected $description = 'Create a database backup and remove backups older than 30 days.';
    protected $usage       = 'backup:create';

    public function run(array $params)
    {
        $backup = new DatabaseBackup();
        $path = $backup->create();
        $backup->purgeExpired();

        CLI::write('Database backup created: ' . $path, 'green');

        return EXIT_SUCCESS;
    }
}
