<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

class ResetOwnerPassword extends BaseCommand
{
    protected $group       = 'Setup';
    protected $name        = 'owner:password';
    protected $description = 'Set a new password for an existing Owner account.';
    protected $usage       = 'owner:password';

    public function run(array $params)
    {
        $db       = db_connect();
        $username = CLI::prompt('Owner username', null, 'required|min_length[3]|max_length[100]');
        $owner    = $db->table('pengguna')
            ->select('pengguna.user_id')
            ->join('peran', 'peran.role_id = pengguna.role_id')
            ->where('peran.role_code', 'OWNER')
            ->where('pengguna.username', $username)
            ->get()
            ->getRowArray();

        if ($owner === null) {
            CLI::error('No Owner account was found with that username.');

            return EXIT_ERROR;
        }

        [$password, $confirmation] = PHP_OS_FAMILY === 'Windows'
            ? $this->readWindowsPasswords()
            : $this->readUnixPasswords();

        if (! hash_equals($password, $confirmation)) {
            CLI::error('The passwords do not match. No changes were made.');

            return EXIT_ERROR;
        }

        if (strlen($password) < 6) {
            CLI::error('Use a password with at least 6 characters. No changes were made.');

            return EXIT_ERROR;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new RuntimeException('Could not securely hash the new password.');
        }

        $db->transBegin();

        try {
            $updated = $db->table('pengguna')
                ->where('user_id', $owner['user_id'])
                ->update([
                    'password_hash'         => $hash,
                    'failed_login_attempts' => 0,
                    'locked_until'          => null,
                ]);

            if (! $updated && $db->affectedRows() === 0) {
                throw new RuntimeException('Could not update the Owner password.');
            }

            if (! $db->table('log_aktivitas')->insert([
                'user_id'     => $owner['user_id'],
                'action'      => 'PASSWORD_RESET',
                'entity_type' => 'PENGGUNA',
                'entity_id'   => (string) $owner['user_id'],
            ])) {
                throw new RuntimeException('Could not record the password reset activity.');
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('The password reset transaction failed.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }

        CLI::write('Owner password updated successfully. The password was not displayed or logged.', 'green');

        return EXIT_SUCCESS;
    }

    /**
     * @return array{string, string}
     */
    private function readWindowsPasswords(): array
    {
        $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
function Read-PasswordAsBase64([string] $prompt) {
    $secure = Read-Host $prompt -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try {
        $plain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
        [Console]::Out.WriteLine([Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($plain)))
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}
Read-PasswordAsBase64 'Kata sandi baru (min. 6 karakter)'
Read-PasswordAsBase64 'Ulangi kata sandi baru'
POWERSHELL;
        $encodedScript = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
        $command       = 'powershell.exe -NoLogo -NoProfile -EncodedCommand ' . $encodedScript;
        $process       = proc_open($command, [
            0 => ['file', 'php://stdin', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', 'php://stderr', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the secure password prompt.');
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);

        if ($status !== 0 || $output === false) {
            throw new RuntimeException('The secure password prompt failed.');
        }

        $encodedPasswords = array_values(array_filter(
            preg_split('/\R/', trim($output)) ?: [],
            static fn (string $line): bool => $line !== '',
        ));

        if (count($encodedPasswords) !== 2) {
            throw new RuntimeException('The secure password prompt did not return both entries.');
        }

        $password     = base64_decode($encodedPasswords[0], true);
        $confirmation = base64_decode($encodedPasswords[1], true);

        if ($password === false || $confirmation === false) {
            throw new RuntimeException('Could not decode the password prompt response.');
        }

        return [$password, $confirmation];
    }

    /**
     * @return array{string, string}
     */
    private function readUnixPasswords(): array
    {
        if (! function_exists('shell_exec') || shell_exec('stty -g 2>/dev/null') === null) {
            throw new RuntimeException('A terminal supporting hidden password input is required.');
        }

        $terminalMode = trim((string) shell_exec('stty -g 2>/dev/null'));

        if ($terminalMode === '') {
            throw new RuntimeException('Could not read terminal settings.');
        }

        try {
            if (shell_exec('stty -echo 2>/dev/null') === null) {
                throw new RuntimeException('Could not hide terminal input.');
            }

            fwrite(STDOUT, 'Kata sandi baru (min. 6 karakter): ');
            $password = fgets(STDIN);
            fwrite(STDOUT, PHP_EOL . 'Ulangi kata sandi baru: ');
            $confirmation = fgets(STDIN);
            fwrite(STDOUT, PHP_EOL);
        } finally {
            exec('stty ' . escapeshellarg($terminalMode) . ' 2>/dev/null');
        }

        if ($password === false || $confirmation === false) {
            throw new RuntimeException('Could not read both password entries.');
        }

        return [rtrim($password, "\r\n"), rtrim($confirmation, "\r\n")];
    }
}
