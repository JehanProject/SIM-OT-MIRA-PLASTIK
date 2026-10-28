<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class CreateOwner extends BaseCommand
{
    protected $group = 'Application';
    protected $name = 'owner:create';
    protected $description = 'Create the initial production Owner account using a password supplied through the process environment.';
    protected $usage = 'owner:create';

    public function run(array $params)
    {
        if (ENVIRONMENT !== 'production') {
            CLI::error('This command is reserved for production Owner provisioning.');

            return EXIT_ERROR;
        }

        $db = db_connect();
        if ((int) $db->table('users')->where('role', 'Owner')->countAllResults() > 0) {
            CLI::error('An Owner account already exists. Use the authenticated account-management screen instead.');

            return EXIT_ERROR;
        }

        $name = trim(CLI::prompt('Owner full name'));
        if ($name === '' || mb_strlen($name) > 80) {
            CLI::error('Owner full name must contain 1 to 80 characters.');

            return EXIT_ERROR;
        }

        $username = mb_strtolower(trim(CLI::prompt('Owner username')));
        if (! preg_match('/^[a-z0-9._-]{3,32}$/', $username)) {
            CLI::error('Username must be 3-32 characters using letters, numbers, dots, underscores, or hyphens.');

            return EXIT_ERROR;
        }

        $password = getenv('MIRA_OWNER_PASSWORD');
        if (! is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
            CLI::error('Set MIRA_OWNER_PASSWORD through the deployment secret manager; it must contain 12-72 bytes.');

            return EXIT_ERROR;
        }
        $now = date('Y-m-d H:i:s');

        if (! $db->transBegin()) {
            throw new RuntimeException('A transaction could not be started for Owner provisioning.');
        }
        try {
            if ((int) $db->table('users')->where('role', 'Owner')->countAllResults() > 0) {
                throw new RuntimeException('An Owner account was created concurrently; no account was added.');
            }
            if ($db->table('users')->where('username', $username)->countAllResults() > 0) {
                throw new RuntimeException('That username already exists; no account was added.');
            }
            $db->table('users')->insert([
                'username'      => $username,
                'full_name'     => $name,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role'          => 'Owner',
                'active'        => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new RuntimeException('Owner account could not be committed.');
            }
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }

        CLI::write('Owner account created successfully. The password was not printed.', 'green');
        CLI::write('Remove MIRA_OWNER_PASSWORD from the process environment; keep the credential in an approved password manager.');

        return EXIT_SUCCESS;
    }
}
