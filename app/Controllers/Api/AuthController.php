<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseController
{
    public function login(): ResponseInterface
    {
        helper('security');
        $payload = $this->request->getJSON(true);
        $username = is_array($payload) && is_string($payload['username'] ?? null)
            ? mb_strtolower(trim($payload['username']))
            : '';
        $password = is_array($payload) && is_string($payload['password'] ?? null)
            ? $payload['password']
            : '';

        if ($username === '' || $password === '' || strlen($password) > 4096) {
            return $this->jsonResponse([
                'error'     => 'Username atau password tidak sesuai.',
                'csrfToken' => csrf_hash(),
            ], 401);
        }

        $users = new UserModel();
        $user  = $users->where('username', $username)->first();
        $now   = time();

        if ($user === null || ! (bool) $user['active']) {
            password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');

            return $this->jsonResponse([
                'error'     => 'Username atau password tidak sesuai.',
                'csrfToken' => csrf_hash(),
            ], 401);
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > $now) {
            return $this->jsonResponse([
                'error'     => 'Akun terkunci sementara. Coba kembali setelah 15 menit.',
                'csrfToken' => csrf_hash(),
            ], 429);
        }

        if (! password_verify($password, $user['password_hash'])) {
            $attempts = (int) $user['failed_login_attempts'] + 1;
            $lockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', $now + 900) : null;

            $users->update($user['id'], [
                'failed_login_attempts' => $lockedUntil === null ? $attempts : 0,
                'locked_until'          => $lockedUntil,
            ]);

            return $this->jsonResponse([
                'error'     => $lockedUntil === null
                    ? 'Username atau password tidak sesuai.'
                    : 'Akun terkunci sementara setelah terlalu banyak percobaan. Coba kembali setelah 15 menit.',
                'csrfToken' => csrf_hash(),
            ], $lockedUntil === null ? 401 : 429);
        }

        $users->update($user['id'], [
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => date('Y-m-d H:i:s', $now),
        ]);

        $session = service('session');
        $session->regenerate(true);
        $session->set([
            'user_id'       => (int) $user['id'],
            'authenticated' => true,
            'last_activity' => $now,
        ]);

        return $this->jsonResponse([
            'user'      => $this->publicUser($user),
            'csrfToken' => csrf_hash(),
        ]);
    }

    public function session(): ResponseInterface
    {
        helper('security');
        $session = service('session');
        $userId  = $session->get('user_id');
        $lastActivity = (int) $session->get('last_activity');

        if ((! is_int($userId) && ! ctype_digit((string) $userId))
            || $lastActivity === 0
            || time() - $lastActivity >= 1800) {
            $session->destroy();

            return $this->response->setJSON(['authenticated' => false]);
        }

        $user = (new UserModel())->find((int) $userId);

        if ($user === null || ! (bool) $user['active']) {
            $session->destroy();

            return $this->response->setJSON(['authenticated' => false]);
        }

        $session->set('last_activity', time());

        return $this->response->setJSON([
            'authenticated' => true,
            'user'          => $this->publicUser($user),
        ]);
    }

    public function logout(): ResponseInterface
    {
        helper('security');
        service('session')->destroy();

        return $this->jsonResponse([
            'message'   => 'Sesi berhasil diakhiri.',
            'csrfToken' => csrf_hash(),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array{id: int, username: string, name: string, role: string}
     */
    private function publicUser(array $user): array
    {
        return [
            'id'       => (int) $user['id'],
            'username' => (string) $user['username'],
            'name'     => (string) $user['full_name'],
            'role'     => (string) $user['role'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($payload);
    }
}
