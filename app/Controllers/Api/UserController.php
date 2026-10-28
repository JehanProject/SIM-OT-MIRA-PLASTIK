<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class UserController extends BaseController
{
    public function index(): ResponseInterface
    {
        if (! $this->isOwner()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Akses Owner diperlukan.']);
        }

        $users = (new UserModel())->orderBy('id')->findAll();

        return $this->response->setJSON([
            'users' => array_map($this->publicUser(...), $users),
        ]);
    }

    public function create(): ResponseInterface
    {
        helper('security');

        if (! $this->isOwner()) {
            return $this->jsonError('Akses Owner diperlukan.', 403);
        }

        $payload  = $this->request->getJSON(true);
        $name     = is_array($payload) && is_string($payload['name'] ?? null) ? trim($payload['name']) : '';
        $username = is_array($payload) && is_string($payload['username'] ?? null)
            ? mb_strtolower(trim($payload['username']))
            : '';
        $password = is_array($payload) && is_string($payload['password'] ?? null) ? $payload['password'] : '';
        $role     = is_array($payload) && is_string($payload['role'] ?? null) ? $payload['role'] : '';

        if ($name === '' || mb_strlen($name) > 80
            || ! preg_match('/^[a-z0-9._-]{3,32}$/', $username)
            || strlen($password) < 8 || strlen($password) > 72
            || ! in_array($role, ['Kasir', 'Staf Gudang'], true)) {
            return $this->jsonError('Data akun tidak valid. Periksa nama, username, password, dan peran.', 422);
        }

        $users = new UserModel();
        if ($users->where('username', $username)->first() !== null) {
            return $this->jsonError('Username sudah digunakan.', 409);
        }

        $id = $users->insert([
            'username'      => $username,
            'full_name'     => $name,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role'          => $role,
            'active'        => true,
        ], true);
        if ($id === false) {
            throw new RuntimeException('Akun baru gagal disimpan ke database.');
        }

        $user = $users->find($id);
        if ($user === null) {
            throw new RuntimeException('Akun tersimpan tetapi tidak dapat dibaca kembali.');
        }

        return $this->response->setStatusCode(201)->setJSON([
            'user'      => $this->publicUser($user),
            'csrfToken' => csrf_hash(),
        ]);
    }

    public function setActive(int $id): ResponseInterface
    {
        helper('security');

        if (! $this->isOwner()) {
            return $this->jsonError('Akses Owner diperlukan.', 403);
        }

        $payload = $this->request->getJSON(true);
        $active  = is_array($payload) && is_bool($payload['active'] ?? null) ? $payload['active'] : null;
        $users   = new UserModel();
        $user    = $users->find($id);

        if ($active === null || $user === null) {
            return $this->jsonError('Akun atau status akun tidak valid.', 422);
        }

        if (! $active && $user['role'] === 'Owner') {
            return $this->jsonError('Akun Owner tidak dapat dinonaktifkan dari panel pengguna.', 422);
        }

        $users->update($id, ['active' => $active]);
        $user = $users->find($id);
        if ($user === null) {
            throw new RuntimeException('Status akun berubah tetapi akun tidak dapat dibaca kembali.');
        }

        return $this->response->setJSON([
            'user'      => $this->publicUser($user),
            'csrfToken' => csrf_hash(),
        ]);
    }

    private function isOwner(): bool
    {
        $userId = service('session')->get('user_id');
        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            return false;
        }

        $user = (new UserModel())->find((int) $userId);

        return $user !== null && (bool) $user['active'] && $user['role'] === 'Owner';
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array{id: int, name: string, username: string, role: string, active: bool}
     */
    private function publicUser(array $user): array
    {
        return [
            'id'       => (int) $user['id'],
            'name'     => (string) $user['full_name'],
            'username' => (string) $user['username'],
            'role'     => (string) $user['role'],
            'active'   => (bool) $user['active'],
        ];
    }

    private function jsonError(string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'error'     => $message,
            'csrfToken' => csrf_hash(),
        ]);
    }
}
