<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class DevelopmentUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (ENVIRONMENT !== 'development') {
            throw new RuntimeException('Demo user accounts may only be seeded in development.');
        }

        $accounts = [
            ['Mira Owner', 'owner', 'Owner'],
            ['Rina Kasir', 'kasir', 'Kasir'],
            ['Dedi Gudang', 'gudang', 'Staf Gudang'],
        ];

        foreach ($accounts as [$name, $username, $role]) {
            $existing = $this->db->table('users')
                ->where('username', $username)
                ->countAllResults();

            if ($existing > 0) {
                continue;
            }

            $password = bin2hex(random_bytes(18));
            $this->db->table('users')->insert([
                'username'      => $username,
                'full_name'     => $name,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role'          => $role,
                'active'        => true,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            CLI::write("Created development account {$username}; one-time password: {$password}", 'yellow');
        }
    }
}
