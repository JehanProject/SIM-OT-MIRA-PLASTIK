<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class Auth extends BaseController
{
    public function login(): RedirectResponse|string
    {
        if (session()->has('user_id')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login', [
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function attemptLogin(): RedirectResponse
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        if ($username === '' || $password === '') {
            return redirect()->to('/login')->with('error', 'Username dan kata sandi wajib diisi.');
        }

        $db = db_connect();
        $user = $db->table('pengguna')
            ->select('pengguna.user_id, pengguna.username, pengguna.password_hash, pengguna.full_name, pengguna.failed_login_attempts, pengguna.locked_until, peran.role_code, peran.role_name')
            ->join('peran', 'peran.role_id = pengguna.role_id')
            ->where('pengguna.username', $username)
            ->where('pengguna.is_active', true)
            ->get()
            ->getRowArray();

        if ($user === null) {
            return redirect()->to('/login')->with('error', 'Username atau kata sandi salah.');
        }

        $now = time();
        $lockedUntil = $user['locked_until'] === null ? false : strtotime($user['locked_until']);

        if ($lockedUntil !== false && $lockedUntil > $now) {
            return redirect()->to('/login')->with('error', 'Akun terkunci sementara. Silakan coba lagi nanti.');
        }

        if (! password_verify($password, $user['password_hash'])) {
            $attempts = $lockedUntil !== false ? 1 : (int) $user['failed_login_attempts'] + 1;
            $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', $now + 900) : null;

            $updated = $db->table('pengguna')
                ->where('user_id', $user['user_id'])
                ->update([
                    'failed_login_attempts' => $attempts,
                    'locked_until'          => $lockUntil,
                ]);

            if (! $updated && $db->affectedRows() === 0) {
                throw new RuntimeException('Could not update login failure state.');
            }

            $message = $lockUntil === null
                ? 'Username atau kata sandi salah.'
                : 'Terlalu banyak percobaan gagal. Akun terkunci selama 15 menit.';

            return redirect()->to('/login')->with('error', $message);
        }

        $update = [
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => date('Y-m-d H:i:s', $now),
        ];

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if (! $db->table('pengguna')->where('user_id', $user['user_id'])->update($update)
            && $db->affectedRows() === 0) {
            throw new RuntimeException('Could not update the Owner account after login.');
        }

        $logged = $db->table('log_aktivitas')->insert([
            'user_id'     => $user['user_id'],
            'action'      => 'LOGIN',
            'entity_type' => 'PENGGUNA',
            'entity_id'   => (string) $user['user_id'],
            'after_data'  => json_encode(['username' => $user['username']], JSON_THROW_ON_ERROR),
        ]);

        if (! $logged) {
            throw new RuntimeException('Could not record successful login activity.');
        }

        session()->regenerate(true);
        session()->set([
            'user_id'   => (int) $user['user_id'],
            'full_name' => $user['full_name'],
            'role_code' => $user['role_code'],
            'role_name' => $user['role_name'],
        ]);

        return redirect()->to('/dashboard');
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to('/login')->with('message', 'Anda telah keluar.');
    }
}
