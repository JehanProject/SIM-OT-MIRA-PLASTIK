<?php

namespace App\Controllers;

use App\Libraries\DatabaseBackup;
use App\Libraries\NotificationService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class Administration extends BaseController
{
    public function distributors(): string
    {
        helper('form');
        $searchInput = $this->request->getGet('q');
        $search = trim(is_string($searchInput) ? $searchInput : '');
        $builder = db_connect()->table('pemasok')
            ->select('supplier_id, supplier_code, supplier_name, phone, address, is_active')
            ->orderBy('supplier_name')
            ->limit(200);
        if ($search !== '') {
            $builder->groupStart()
                ->like('supplier_code', $search)
                ->orLike('supplier_name', $search)
                ->orLike('phone', $search)
                ->groupEnd();
        }

        return view('administration/distributors', [
            'suppliers' => $builder->get()->getResultArray(),
            'search' => $search,
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function createDistributor(): RedirectResponse
    {
        $name = trim($this->postString('supplier_name'));
        $phone = trim($this->postString('phone'));
        $address = trim($this->postString('address'));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150 || mb_strlen($phone) > 40 || mb_strlen($address) > 65535) {
            return redirect()->back()->withInput()->with('error', 'Nama distributor wajib diisi (2–150 karakter); telepon atau alamat terlalu panjang.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $nextId = (int) ($db->query('SELECT COALESCE(MAX(supplier_id), 0) + 1 AS next_id FROM pemasok')->getRowArray()['next_id'] ?? 1);
            $supplierCode = 'DST-' . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
            if (! $db->table('pemasok')->insert([
                'supplier_code' => $supplierCode,
                'supplier_name' => $name,
                'phone' => $phone === '' ? null : $phone,
                'address' => $address === '' ? null : $address,
                'is_active' => true,
            ])) {
                throw new RuntimeException('Gagal menyimpan distributor.');
            }
            $supplierId = (int) $db->insertID();
            $this->writeActivity($db, 'CREATE', 'PEMASOK', $supplierId, null, [
                'supplier_code' => $supplierCode,
                'supplier_name' => $name,
                'phone' => $phone,
            ]);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi distributor tidak berhasil disimpan.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            if (str_contains($exception->getMessage(), 'uq_pemasok_code')) {
                return redirect()->back()->withInput()->with('error', 'Kode distributor sudah digunakan. Silakan coba lagi.');
            }
            throw $exception;
        }

        return redirect()->to('/distributor')->with('success', 'Distributor berhasil ditambahkan.');
    }

    public function deactivateDistributor(string $id): RedirectResponse
    {
        $supplierId = ctype_digit($id) ? (int) $id : 0;
        $db = db_connect();
        $supplier = $db->table('pemasok')->where('supplier_id', $supplierId)->get()->getRowArray();
        if ($supplier === null) {
            return redirect()->to('/distributor')->with('error', 'Distributor tidak ditemukan.');
        }
        if (! (bool) $supplier['is_active']) {
            return redirect()->to('/distributor')->with('success', 'Distributor sudah nonaktif.');
        }

        $db->transBegin();
        try {
            if (! $db->table('pemasok')->where('supplier_id', $supplierId)->update(['is_active' => false])) {
                throw new RuntimeException('Gagal menonaktifkan distributor.');
            }
            $this->writeActivity($db, 'DEACTIVATE', 'PEMASOK', $supplierId, ['is_active' => true], ['is_active' => false]);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penonaktifan distributor gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/distributor')->with('success', 'Distributor dinonaktifkan. Riwayat penerimaan tetap tersimpan.');
    }

    public function users(): string
    {
        helper('form');
        $searchInput = $this->request->getGet('q');
        $search = trim(is_string($searchInput) ? $searchInput : '');
        $builder = db_connect()->table('pengguna AS u')
            ->select('u.user_id, u.username, u.full_name, u.is_active, u.last_login_at, r.role_code, r.role_name')
            ->join('peran AS r', 'r.role_id = u.role_id')
            ->orderBy('u.full_name')
            ->limit(200);
        if ($search !== '') {
            $builder->groupStart()
                ->like('u.username', $search)
                ->orLike('u.full_name', $search)
                ->orLike('r.role_name', $search)
                ->groupEnd();
        }

        return view('administration/users', [
            'users' => $builder->get()->getResultArray(),
            'roles' => db_connect()->table('peran')->orderBy('role_name')->get()->getResultArray(),
            'search' => $search,
            'currentUserId' => (int) session()->get('user_id'),
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function editUser(string $id): string|RedirectResponse
    {
        helper('form');
        $userId = ctype_digit($id) ? (int) $id : 0;
        $user = db_connect()->table('pengguna AS u')
            ->select('u.user_id, u.username, u.full_name, u.role_id, r.role_code')
            ->join('peran AS r', 'r.role_id = u.role_id')
            ->where('u.user_id', $userId)
            ->get()
            ->getRowArray();
        if ($user === null) {
            return redirect()->to('/pengguna')->with('error', 'Akun pengguna tidak ditemukan.');
        }

        return view('administration/user_form', [
            'user' => $user,
            'roles' => db_connect()->table('peran')->orderBy('role_name')->get()->getResultArray(),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function createUser(): RedirectResponse
    {
        $name = trim($this->postString('full_name'));
        $username = trim($this->postString('username'));
        $password = $this->postString('password');
        $roleCode = $this->postString('role_code');
        $db = db_connect();
        $role = $db->table('peran')->where('role_code', $roleCode)->get()->getRowArray();

        if (mb_strlen($name) < 2 || mb_strlen($name) > 150
            || preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username) !== 1
            || strlen($password) < 6 || strlen($password) > 255
            || $role === null) {
            return redirect()->back()->withInput()->with('error', 'Nama, username, atau peran tidak valid. Kata sandi awal minimal 6 karakter.');
        }
        if ($db->table('pengguna')->where('username', $username)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'Username sudah digunakan.');
        }

        $db->transBegin();
        try {
            if (! $db->table('pengguna')->insert([
                'role_id' => (int) $role['role_id'],
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'full_name' => $name,
                'is_active' => true,
            ])) {
                throw new RuntimeException('Gagal membuat akun pengguna.');
            }
            $userId = (int) $db->insertID();
            $this->writeActivity($db, 'CREATE', 'PENGGUNA', $userId, null, [
                'username' => $username,
                'full_name' => $name,
                'role_code' => $roleCode,
            ]);
            NotificationService::notifyRoles(
                $db,
                ['OWNER'],
                'USER_CREATED',
                'Akun pengguna baru dibuat',
                'Akun ' . $name . ' dengan peran ' . $role['role_name'] . ' telah dibuat.',
                'pengguna',
            );
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pembuatan akun gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/pengguna')->with('success', 'Akun pengguna berhasil dibuat.');
    }

    public function updateUser(string $id): RedirectResponse
    {
        $userId = ctype_digit($id) ? (int) $id : 0;
        $name = trim($this->postString('full_name'));
        $username = trim($this->postString('username'));
        $password = $this->postString('password');
        $roleCode = $this->postString('role_code');
        $db = db_connect();
        $user = $db->table('pengguna AS u')
            ->select('u.user_id, u.username, u.full_name, u.is_active, r.role_id, r.role_code')
            ->join('peran AS r', 'r.role_id = u.role_id')
            ->where('u.user_id', $userId)
            ->get()
            ->getRowArray();
        if ($user === null) {
            return redirect()->to('/pengguna')->with('error', 'Akun pengguna tidak ditemukan.');
        }

        $role = $db->table('peran')->where('role_code', $roleCode)->get()->getRowArray();
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150
            || preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username) !== 1
            || ($password !== '' && (strlen($password) < 6 || strlen($password) > 255))
            || $role === null) {
            return redirect()->back()->withInput()->with('error', 'Nama, username, atau peran tidak valid. Kata sandi baru minimal 6 karakter; kosongkan jika tidak ingin mengubahnya.');
        }
        if ($db->table('pengguna')->where('username', $username)->where('user_id !=', $userId)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'Username sudah digunakan.');
        }
        if ((bool) $user['is_active'] && $user['role_code'] === 'OWNER' && $roleCode !== 'OWNER') {
            $otherActiveOwners = $db->table('pengguna AS u')
                ->join('peran AS r', 'r.role_id = u.role_id')
                ->where('r.role_code', 'OWNER')
                ->where('u.is_active', true)
                ->where('u.user_id !=', $userId)
                ->countAllResults();
            if ($otherActiveOwners === 0) {
                return redirect()->back()->withInput()->with('error', 'Peran Owner tidak dapat diubah karena akun ini adalah Owner aktif terakhir.');
            }
        }

        $before = [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role_code' => $user['role_code'],
        ];
        $after = [
            'username' => $username,
            'full_name' => $name,
            'role_code' => $roleCode,
        ];

        $db->transBegin();
        try {
            $update = [
                'role_id' => (int) $role['role_id'],
                'username' => $username,
                'full_name' => $name,
            ];
            if ($password !== '') {
                $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            if (! $db->table('pengguna')->where('user_id', $userId)->update($update)) {
                throw new RuntimeException('Gagal memperbarui akun pengguna.');
            }
            $this->writeActivity($db, 'UPDATE', 'PENGGUNA', $userId, $before, $after);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi perubahan akun tidak berhasil disimpan.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/pengguna')->with('success', 'Data akun pengguna berhasil diperbarui.');
    }

    public function deactivateUser(string $id): RedirectResponse
    {
        $userId = ctype_digit($id) ? (int) $id : 0;
        if ($userId === (int) session()->get('user_id')) {
            return redirect()->to('/pengguna')->with('error', 'Akun yang sedang digunakan tidak dapat dinonaktifkan.');
        }

        $db = db_connect();
        $user = $db->table('pengguna AS u')
            ->select('u.user_id, u.is_active, r.role_code')
            ->join('peran AS r', 'r.role_id = u.role_id')
            ->where('u.user_id', $userId)
            ->get()
            ->getRowArray();
        if ($user === null) {
            return redirect()->to('/pengguna')->with('error', 'Akun pengguna tidak ditemukan.');
        }
        if ((bool) $user['is_active'] && $user['role_code'] === 'OWNER') {
            $activeOwners = $db->table('pengguna AS u')
                ->join('peran AS r', 'r.role_id = u.role_id')
                ->where('r.role_code', 'OWNER')
                ->where('u.is_active', true)
                ->countAllResults();
            if ($activeOwners <= 1) {
                return redirect()->to('/pengguna')->with('error', 'Akun Owner aktif terakhir tidak dapat dinonaktifkan.');
            }
        }
        if (! (bool) $user['is_active']) {
            return redirect()->to('/pengguna')->with('success', 'Akun sudah nonaktif.');
        }

        $db->transBegin();
        try {
            if (! $db->table('pengguna')->where('user_id', $userId)->update(['is_active' => false])) {
                throw new RuntimeException('Gagal menonaktifkan akun.');
            }
            $this->writeActivity($db, 'DEACTIVATE', 'PENGGUNA', $userId, ['is_active' => true], ['is_active' => false]);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penonaktifan akun gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/pengguna')->with('success', 'Akun dinonaktifkan. Riwayat transaksi tetap tersimpan.');
    }

    public function deleteUser(string $id): RedirectResponse
    {
        $userId = ctype_digit($id) ? (int) $id : 0;
        if ($userId === (int) session()->get('user_id')) {
            return redirect()->to('/pengguna')->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        $db = db_connect();
        $user = $db->table('pengguna AS u')
            ->select('u.user_id, u.username, u.full_name, u.is_active, r.role_code')
            ->join('peran AS r', 'r.role_id = u.role_id')
            ->where('u.user_id', $userId)
            ->get()
            ->getRowArray();
        if ($user === null) {
            return redirect()->to('/pengguna')->with('error', 'Akun pengguna tidak ditemukan.');
        }
        if ((bool) $user['is_active'] && $user['role_code'] === 'OWNER') {
            $activeOwners = $db->table('pengguna AS u')
                ->join('peran AS r', 'r.role_id = u.role_id')
                ->where('r.role_code', 'OWNER')
                ->where('u.is_active', true)
                ->countAllResults();
            if ($activeOwners <= 1) {
                return redirect()->to('/pengguna')->with('error', 'Owner aktif terakhir tidak dapat dihapus.');
            }
        }

        $references = $db->query(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'pengguna'
               AND REFERENCED_COLUMN_NAME = 'user_id'",
        )->getResultArray();
        foreach ($references as $reference) {
            if ($reference['TABLE_NAME'] === 'notifikasi_pengguna') {
                continue;
            }
            if ($db->table($reference['TABLE_NAME'])
                ->where($reference['COLUMN_NAME'], $userId)
                ->countAllResults() > 0) {
                return redirect()->to('/pengguna')->with('error', 'Akun ini sudah tercatat pada transaksi atau aktivitas dan tidak dapat dihapus. Nonaktifkan akun agar riwayat tetap utuh.');
            }
        }

        $db->transBegin();
        try {
            $this->writeActivity($db, 'DELETE', 'PENGGUNA', $userId, [
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role_code' => $user['role_code'],
                'is_active' => (bool) $user['is_active'],
            ], null);
            if (! $db->table('pengguna')->where('user_id', $userId)->delete()) {
                throw new RuntimeException('Gagal menghapus akun pengguna.');
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penghapusan akun tidak berhasil disimpan.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/pengguna')->with('success', 'Akun pengguna berhasil dihapus. Catatan audit penghapusan tetap tersimpan.');
    }

    public function activityLog(): string
    {
        helper('form');
        $searchInput = $this->request->getGet('q');
        $search = trim(is_string($searchInput) ? $searchInput : '');
        [$from, $to] = $this->dateRange();
        $builder = db_connect()->table('log_aktivitas AS l')
            ->select('l.activity_log_id, l.action, l.entity_type, l.entity_id, l.created_at, l.before_data, l.after_data, u.full_name, r.role_name')
            ->join('pengguna AS u', 'u.user_id = l.user_id')
            ->join('peran AS r', 'r.role_id = u.role_id')
            ->where('l.created_at >=', $from . ' 00:00:00')
            ->where('l.created_at <=', $to . ' 23:59:59')
            ->orderBy('l.created_at', 'DESC')
            ->limit(200);
        if ($search !== '') {
            $builder->groupStart()
                ->like('l.action', $search)
                ->orLike('l.entity_type', $search)
                ->orLike('u.full_name', $search)
                ->groupEnd();
        }

        return view('administration/activity', [
            'events' => $builder->get()->getResultArray(),
            'search' => $search,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function backups(): string
    {
        helper('form');
        $files = [];
        foreach (glob($this->backupDirectory() . DIRECTORY_SEPARATOR . 'mira-backup-*.json') ?: [] as $path) {
            if (is_file($path)) {
                $files[] = [
                    'name' => basename($path),
                    'size' => filesize($path) ?: 0,
                    'modified' => filemtime($path) ?: 0,
                ];
            }
        }
        usort($files, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return view('administration/backups', [
            'files' => $files,
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function createBackup(): RedirectResponse
    {
        $backup = new DatabaseBackup();
        $path = $backup->create();
        $backup->purgeExpired();

        return redirect()->to('/backup')->with('success', 'Backup berhasil dibuat: ' . basename($path));
    }

    public function downloadBackup(string $name): ResponseInterface|RedirectResponse
    {
        if (preg_match('/^mira-backup-\d{8}-\d{6}\.json$/', $name) !== 1) {
            return redirect()->to('/backup')->with('error', 'Nama file backup tidak valid.');
        }
        $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $name;
        if (! is_file($path)) {
            return redirect()->to('/backup')->with('error', 'File backup tidak ditemukan.');
        }

        return $this->response->download($path, null)->setFileName($name);
    }

    public function restoreBackup(): RedirectResponse
    {
        if ($this->postString('confirmation') !== 'PULIHKAN') {
            return redirect()->back()->with('error', 'Ketik PULIHKAN untuk mengonfirmasi penggantian data.');
        }
        $file = $this->request->getFile('backup_file');
        if ($file === null || ! $file->isValid() || $file->hasMoved() || $file->getSize() > 100 * 1024 * 1024) {
            return redirect()->back()->with('error', 'Pilih file backup JSON valid dengan ukuran maksimal 100 MB.');
        }

        try {
            $contents = file_get_contents($file->getTempName());
            if ($contents === false) {
                throw new RuntimeException('File backup tidak dapat dibaca.');
            }
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new RuntimeException('Format file backup tidak valid.');
            }
            $this->validateBackupPayload($payload);
            $safetyBackup = $this->createBackupFile();
            $this->restorePayload($payload);
        } catch (Throwable $exception) {
            log_message('error', 'Pemulihan backup gagal: {message}', ['message' => $exception->getMessage()]);

            return redirect()->to('/backup')->with('error', 'Pemulihan gagal. Data belum dipulihkan; periksa format file dan log aplikasi.');
        }

        return redirect()->to('/backup')->with('success', 'Pemulihan selesai. Backup pengaman sebelum pemulihan tersimpan sebagai ' . basename($safetyBackup) . '.');
    }

    private function createBackupFile(): string
    {
        return (new DatabaseBackup())->create();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateBackupPayload(array $payload): void
    {
        $db = db_connect();
        if (($payload['format'] ?? null) !== 'mira-plastik-json-backup'
            || ($payload['version'] ?? null) !== 1
            || ($payload['database'] ?? null) !== $db->getDatabase()
            || ! is_array($payload['tables'] ?? null)
            || ! is_array($payload['schema'] ?? null)) {
            throw new RuntimeException('Backup bukan untuk database dan versi skema ini.');
        }

        $currentTables = $db->listTables();
        sort($currentTables);
        $backupTables = array_keys($payload['tables']);
        sort($backupTables);
        if ($currentTables !== $backupTables || array_keys($payload['schema']) !== array_keys($payload['tables'])) {
            throw new RuntimeException('Daftar tabel backup tidak cocok dengan skema saat ini.');
        }

        foreach ($currentTables as $table) {
            $columns = array_map(static fn (object $field): string => $field->name, $db->getFieldData($table));
            if (! is_array($payload['tables'][$table]) || ! is_array($payload['schema'][$table])) {
                throw new RuntimeException('Data salah satu tabel backup tidak valid.');
            }
            $backupColumns = array_map(
                static fn (array $field): string => (string) ($field['name'] ?? ''),
                $payload['schema'][$table],
            );
            if ($columns !== $backupColumns) {
                throw new RuntimeException('Skema tabel ' . $table . ' berbeda dari backup.');
            }
            foreach ($payload['tables'][$table] as $row) {
                if (! is_array($row) || array_diff(array_keys($row), $columns) !== []) {
                    throw new RuntimeException('Baris backup tabel ' . $table . ' tidak valid.');
                }
            }
        }

        $currentOwner = (int) session()->get('user_id');
        $ownerRows = $payload['tables']['pengguna'] ?? [];
        $ownerPresent = false;
        foreach ($ownerRows as $user) {
            if ((int) ($user['user_id'] ?? 0) === $currentOwner && (bool) ($user['is_active'] ?? false)) {
                $ownerPresent = true;
                break;
            }
        }
        if (! $ownerPresent) {
            throw new RuntimeException('Backup tidak memuat akun Owner aktif yang sedang digunakan.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function restorePayload(array $payload): void
    {
        $db = db_connect();
        $tables = $db->listTables();
        sort($tables);
        $db->transBegin();
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (array_reverse($tables) as $table) {
                if (! $db->table($table)->emptyTable()) {
                    throw new RuntimeException('Gagal mengosongkan tabel ' . $table . '.');
                }
            }
            foreach ($tables as $table) {
                foreach ($payload['tables'][$table] as $row) {
                    if ($row !== [] && ! $db->table($table)->insert($row)) {
                        throw new RuntimeException('Gagal memulihkan data tabel ' . $table . '.');
                    }
                }
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Pemulihan data tidak berhasil secara utuh.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function backupDirectory(): string
    {
        return WRITEPATH . 'backups';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dateRange(): array
    {
        $today = date('Y-m-d');
        $fromInput = $this->request->getGet('from');
        $toInput = $this->request->getGet('to');
        $from = $this->validDate(is_string($fromInput) ? $fromInput : '') ?? date('Y-m-01');
        $to = $this->validDate(is_string($toInput) ? $toInput : '') ?? $today;

        return $from <= $to ? [$from, $to] : [date('Y-m-01'), $today];
    }

    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function postString(string $key): string
    {
        $value = $this->request->getPost($key);

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function writeActivity($db, string $action, string $entity, int $entityId, ?array $before, ?array $after): void
    {
        if (! $db->table('log_aktivitas')->insert([
            'user_id' => (int) session()->get('user_id'),
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => (string) $entityId,
            'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_data' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        ])) {
            throw new RuntimeException('Gagal mencatat log aktivitas.');
        }
    }
}
