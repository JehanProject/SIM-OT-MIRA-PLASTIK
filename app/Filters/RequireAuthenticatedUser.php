<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RequireAuthenticatedUser implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = service('session');
        $userId  = $session->get('user_id');
        $lastActivity = (int) $session->get('last_activity');

        if ((! is_int($userId) && ! ctype_digit((string) $userId))
            || $lastActivity === 0
            || time() - $lastActivity >= 1800) {
            $session->destroy();

            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['error' => 'Sesi berakhir. Silakan masuk kembali.']);
        }

        $user = (new UserModel())->find((int) $userId);

        if ($user === null || ! (bool) $user['active']) {
            $session->destroy();

            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['error' => 'Akun tidak aktif atau sesi tidak valid.']);
        }

        $session->set('last_activity', time());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
