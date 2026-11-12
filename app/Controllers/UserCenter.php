<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

class UserCenter extends BaseController
{
    public function profile(): string
    {
        helper('form');
        $user = db_connect()->table('pengguna AS u')
            ->select('u.user_id, u.username, u.full_name, r.role_name')
            ->join('peran AS r', 'r.role_id = u.role_id')
            ->where('u.user_id', (int) session()->get('user_id'))
            ->get()
            ->getRowArray();
        if ($user === null) {
            throw new RuntimeException('Akun aktif tidak ditemukan.');
        }

        return view('user_center/profile', [
            'user' => $user,
            'error' => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
        ]);
    }

    public function saveProfile(): RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $name = trim($this->postString('full_name'));
        $username = trim($this->postString('username'));
        $currentPassword = $this->postString('current_password');
        $newPassword = $this->postString('new_password');
        $confirmPassword = $this->postString('confirm_password');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150
            || preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username) !== 1
            || ($newPassword !== '' && (strlen($newPassword) < 6 || strlen($newPassword) > 255))) {
            return redirect()->back()->withInput()->with('error', 'Periksa nama, username, atau kata sandi baru (minimal 6 karakter).');
        }
        if ($newPassword !== '' && $newPassword !== $confirmPassword) {
            return redirect()->back()->withInput()->with('error', 'Konfirmasi kata sandi baru tidak sama.');
        }
        if ($newPassword !== '' && $currentPassword === '') {
            return redirect()->back()->withInput()->with('error', 'Kata sandi saat ini wajib diisi untuk mengganti kata sandi.');
        }

        $db = db_connect();
        $user = $db->table('pengguna')
            ->select('username, full_name, password_hash')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();
        if ($user === null) {
            throw new RuntimeException('Akun aktif tidak ditemukan.');
        }
        if ($db->table('pengguna')->where('username', $username)->where('user_id !=', $userId)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'Username sudah digunakan.');
        }
        if ($newPassword !== '' && ! password_verify($currentPassword, $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Kata sandi saat ini tidak sesuai.');
        }

        $before = ['username' => $user['username'], 'full_name' => $user['full_name']];
        $after = ['username' => $username, 'full_name' => $name];
        $update = ['username' => $username, 'full_name' => $name];
        if ($newPassword !== '') {
            $update['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $db->transBegin();
        try {
            if (! $db->table('pengguna')->where('user_id', $userId)->update($update)) {
                throw new RuntimeException('Gagal memperbarui profil.');
            }
            if (! $db->table('log_aktivitas')->insert([
                'user_id' => $userId,
                'action' => 'UPDATE_PROFILE',
                'entity_type' => 'PENGGUNA',
                'entity_id' => (string) $userId,
                'before_data' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_data' => json_encode($after, JSON_THROW_ON_ERROR),
            ])) {
                throw new RuntimeException('Gagal mencatat perubahan profil.');
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pembaruan profil gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        session()->set('full_name', $name);

        return redirect()->to('/profil')->with('success', 'Profil berhasil diperbarui.');
    }

    public function notifications(): string
    {
        helper('form');
        $userId = (int) session()->get('user_id');
        $db = db_connect();

        return view('user_center/notifications', [
            'notifications' => $db->table('notifikasi_pengguna')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'DESC')
                ->orderBy('notification_id', 'DESC')
                ->limit(100)
                ->get()
                ->getResultArray(),
            'unreadCount' => $db->table('notifikasi_pengguna')
                ->where('user_id', $userId)
                ->where('is_read', false)
                ->countAllResults(),
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function markNotificationRead(string $id): RedirectResponse
    {
        $notificationId = ctype_digit($id) ? (int) $id : 0;
        $db = db_connect();
        $notification = $db->table('notifikasi_pengguna')
            ->select('notification_id, is_read')
            ->where('notification_id', $notificationId)
            ->where('user_id', (int) session()->get('user_id'))
            ->get()
            ->getRowArray();
        if ($notification === null) {
            return redirect()->to('/notifikasi')->with('error', 'Notifikasi tidak ditemukan.');
        }
        if (! (bool) $notification['is_read']
            && ! $db->table('notifikasi_pengguna')
                ->where('notification_id', $notificationId)
                ->where('user_id', (int) session()->get('user_id'))
                ->update(['is_read' => true, 'read_at' => date('Y-m-d H:i:s')])) {
            throw new RuntimeException('Gagal menandai notifikasi sudah dibaca.');
        }

        return redirect()->to('/notifikasi');
    }

    public function markAllNotificationsRead(): RedirectResponse
    {
        $db = db_connect();
        if (! $db->table('notifikasi_pengguna')
            ->where('user_id', (int) session()->get('user_id'))
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => date('Y-m-d H:i:s')])) {
            throw new RuntimeException('Gagal menandai semua notifikasi sudah dibaca.');
        }

        return redirect()->to('/notifikasi')->with('success', 'Semua notifikasi sudah ditandai dibaca.');
    }

    private function postString(string $key): string
    {
        $value = $this->request->getPost($key);

        return is_string($value) ? $value : '';
    }
}
