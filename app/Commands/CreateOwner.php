<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class CreateOwner extends BaseCommand
{
    protected $group       = 'Setup';
    protected $name        = 'owner:create';
    protected $description = 'Create the initial Owner account with a generated password.';
    protected $usage       = 'owner:create';

    public function run(array $params)
    {
        $db = db_connect();

        $ownerRole = $db->table('peran')
            ->where('role_code', 'OWNER')
            ->get()
            ->getRowArray();

        if ($ownerRole === null) {
            CLI::error('Role OWNER was not found. Import the database schema first.');

            return EXIT_ERROR;
        }

        $existingOwner = $db->table('pengguna')
            ->where('role_id', $ownerRole['role_id'])
            ->countAllResults();

        if ($existingOwner > 0) {
            CLI::error('An Owner account already exists. This setup command can only be used once.');

            return EXIT_ERROR;
        }

        $fullName = CLI::prompt('Owner full name', null, 'required|min_length[2]|max_length[150]');
        $username = CLI::prompt(
            'Username (letters, numbers, dots, dashes, or underscores)',
            null,
            'required|alpha_numeric_punct|min_length[3]|max_length[100]',
        );

        if ($db->table('pengguna')->where('username', $username)->countAllResults() > 0) {
            CLI::error('That username is already in use. Run the command again with a different username.');

            return EXIT_ERROR;
        }

        $plainPassword = bin2hex(random_bytes(24));
        $passwordHash  = password_hash($plainPassword, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException('Could not securely hash the Owner password.');
        }

        $inserted = $db->table('pengguna')->insert([
            'role_id'                => $ownerRole['role_id'],
            'username'               => $username,
            'password_hash'          => $passwordHash,
            'full_name'              => $fullName,
            'failed_login_attempts'  => 0,
            'is_active'              => true,
        ]);

        if (! $inserted) {
            throw new RuntimeException('Could not create the Owner account.');
        }

        CLI::newLine();
        CLI::write('Owner account created. Save this generated password now; it will not be shown again.', 'green');
        CLI::write('Username: ' . $username);
        CLI::write('Password: ' . $plainPassword);

        return EXIT_SUCCESS;
    }
}
