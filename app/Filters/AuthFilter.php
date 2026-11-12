<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $userId  = $session->get('user_id');

        if (! is_numeric($userId)) {
            return redirect()->to('/login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        $user = db_connect()->table('pengguna')
            ->select('pengguna.user_id, pengguna.full_name, peran.role_code, peran.role_name')
            ->join('peran', 'peran.role_id = pengguna.role_id')
            ->where('pengguna.user_id', (int) $userId)
            ->where('pengguna.is_active', true)
            ->get()
            ->getRowArray();

        if ($user === null) {
            $session->destroy();

            return redirect()->to('/login')->with('error', 'Akun tidak aktif atau tidak ditemukan.');
        }

        $session->set([
            'full_name' => $user['full_name'],
            'role_code' => $user['role_code'],
            'role_name' => $user['role_name'],
        ]);

        if ($arguments !== null && $arguments !== [] && ! in_array($user['role_code'], $arguments, true)) {
            return service('response')->setStatusCode(403)->setBody('Anda tidak memiliki akses ke halaman ini.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
