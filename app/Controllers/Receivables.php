<?php

namespace App\Controllers;

use App\Libraries\NotificationService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use RuntimeException;
use Throwable;

class Receivables extends BaseController
{
    public function index(): string
    {
        helper('form');
        $db = db_connect();
        $receivables = $db->query(
            "SELECT piutang.receivable_id, piutang.customer_id, piutang.sale_id,
                    pelanggan.customer_code, pelanggan.customer_name, pelanggan.business_name,
                    penjualan.invoice_number, piutang.original_amount, piutang.due_date, piutang.status,
                    COALESCE(payments.paid_amount, 0.00) AS paid_amount,
                    COALESCE(adjustments.adjusted_amount, 0.00) AS adjusted_amount,
                    piutang.original_amount - COALESCE(payments.paid_amount, 0.00)
                        - COALESCE(adjustments.adjusted_amount, 0.00) AS outstanding_amount
             FROM piutang
             JOIN pelanggan ON pelanggan.customer_id = piutang.customer_id
             LEFT JOIN penjualan ON penjualan.sale_id = piutang.sale_id
             LEFT JOIN (
                 SELECT receivable_id, SUM(amount) AS paid_amount
                 FROM pembayaran_piutang GROUP BY receivable_id
             ) AS payments ON payments.receivable_id = piutang.receivable_id
             LEFT JOIN (
                 SELECT receivable_id, SUM(amount) AS adjusted_amount
                 FROM penyesuaian_piutang GROUP BY receivable_id
             ) AS adjustments ON adjustments.receivable_id = piutang.receivable_id
             WHERE piutang.status IN ('OPEN', 'PARTIALLY_PAID')
             ORDER BY (piutang.due_date IS NULL), piutang.due_date, piutang.receivable_id",
        )->getResultArray();
        $today = date('Y-m-d');
        $reminderCutoff = (new \DateTimeImmutable($today))->modify('+7 days')->format('Y-m-d');
        $reminders = [
            'overdue' => [],
            'due_today' => [],
            'upcoming' => [],
        ];
        foreach ($receivables as $receivable) {
            if ($receivable['due_date'] === null || (float) $receivable['outstanding_amount'] <= 0) {
                continue;
            }

            if ($receivable['due_date'] < $today) {
                $reminders['overdue'][] = $receivable;
            } elseif ($receivable['due_date'] === $today) {
                $reminders['due_today'][] = $receivable;
            } elseif ($receivable['due_date'] <= $reminderCutoff) {
                $reminders['upcoming'][] = $receivable;
            }
        }

        return view('receivables/index', [
            'receivables' => $receivables,
            'reminders' => $reminders,
            'reminderCutoff' => $reminderCutoff,
            'accounts' => $db->table('akun_kas')
                ->where('is_active', true)
                ->orderBy('account_type')
                ->orderBy('account_name')
                ->get()
                ->getResultArray(),
            'recentPayments' => $db->query(
                "SELECT pembayaran_piutang.amount, pembayaran_piutang.payment_method,
                        pembayaran_piutang.paid_at, pengguna.full_name, pelanggan.customer_name,
                        penjualan.invoice_number, akun_kas.account_name
                 FROM pembayaran_piutang
                 JOIN piutang ON piutang.receivable_id = pembayaran_piutang.receivable_id
                 JOIN pelanggan ON pelanggan.customer_id = piutang.customer_id
                 JOIN pengguna ON pengguna.user_id = pembayaran_piutang.recorded_by
                 LEFT JOIN penjualan ON penjualan.sale_id = piutang.sale_id
                 JOIN mutasi_kas ON mutasi_kas.receivable_payment_id = pembayaran_piutang.receivable_payment_id
                 JOIN akun_kas ON akun_kas.cash_account_id = mutasi_kas.cash_account_id
                 ORDER BY pembayaran_piutang.receivable_payment_id DESC
                 LIMIT 20",
            )->getResultArray(),
            'roleCode' => (string) session()->get('role_code'),
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
            'formErrors' => session()->getFlashdata('form_errors') ?? [],
        ]);
    }

    public function postPayment(string $id): RedirectResponse
    {
        if (! ctype_digit($id) || (int) $id < 1) {
            return redirect()->to('/piutang')->with('error', 'Data piutang tidak ditemukan.');
        }

        $amount = $this->postString('amount');
        $paymentMethod = $this->postString('payment_method');
        $accountId = $this->postString('cash_account_id');
        if (! $this->isMoney($amount, true)
            || ! in_array($paymentMethod, ['TUNAI', 'TRANSFER'], true)
            || ! ctype_digit($accountId) || (int) $accountId < 1) {
            return redirect()->to('/piutang')->withInput()->with('error', 'Periksa jumlah pembayaran, metode, dan akun kas/bank.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $receivable = $db->query(
                "SELECT receivable_id, customer_id, sale_id, original_amount, status
                 FROM piutang WHERE receivable_id = ? FOR UPDATE",
                [(int) $id],
            )->getRowArray();
            if ($receivable === null || ! in_array($receivable['status'], ['OPEN', 'PARTIALLY_PAID'], true)) {
                throw new DomainException('Piutang ini tidak terbuka untuk pembayaran.');
            }

            $account = $db->table('akun_kas')
                ->where('cash_account_id', (int) $accountId)
                ->where('account_type', $paymentMethod === 'TUNAI' ? 'TUNAI' : 'BANK')
                ->where('is_active', true)
                ->get()
                ->getRowArray();
            if ($account === null) {
                throw new DomainException('Akun kas/bank tidak aktif atau tidak sesuai dengan metode pembayaran.');
            }

            $balance = $db->query(
                "SELECT ? - COALESCE((
                            SELECT SUM(amount) FROM pembayaran_piutang WHERE receivable_id = ?
                        ), 0.00) - COALESCE((
                            SELECT SUM(amount) FROM penyesuaian_piutang WHERE receivable_id = ?
                        ), 0.00) AS outstanding_amount",
                [(string) $receivable['original_amount'], (int) $id, (int) $id],
            )->getRowArray();
            $outstandingCents = $this->scaledInteger((string) $balance['outstanding_amount']);
            $paymentCents = $this->scaledInteger($amount);
            if ($outstandingCents === null || $paymentCents === null || $paymentCents > $outstandingCents) {
                throw new DomainException('Pembayaran melebihi sisa piutang.');
            }

            if (! $db->table('pembayaran_piutang')->insert([
                'receivable_id' => (int) $id,
                'recorded_by' => (int) session()->get('user_id'),
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'notes' => 'Pembayaran piutang ' . ($receivable['sale_id'] === null ? 'saldo awal' : 'penjualan'),
            ])) {
                throw new RuntimeException('Gagal mencatat pembayaran piutang.');
            }
            $paymentId = (int) $db->insertID();

            if (! $db->table('mutasi_kas')->insert([
                'cash_account_id' => (int) $accountId,
                'receivable_payment_id' => $paymentId,
                'recorded_by' => (int) session()->get('user_id'),
                'direction' => 'MASUK',
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'notes' => 'Pembayaran piutang #' . $id,
            ])) {
                throw new RuntimeException('Gagal mencatat penerimaan pembayaran pada buku kas.');
            }

            $status = $paymentCents === $outstandingCents ? 'PAID' : 'PARTIALLY_PAID';
            if (! $db->table('piutang')->where('receivable_id', (int) $id)->update([
                'status' => $status,
            ])) {
                throw new RuntimeException('Gagal memperbarui status piutang.');
            }
            if (! $db->table('log_aktivitas')->insert([
                'user_id' => (int) session()->get('user_id'),
                'action' => 'RECEIVE_RECEIVABLE_PAYMENT',
                'entity_type' => 'PIUTANG',
                'entity_id' => (string) $id,
                'after_data' => json_encode([
                    'payment_id' => $paymentId,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'status' => $status,
                ], JSON_THROW_ON_ERROR),
            ])) {
                throw new RuntimeException('Gagal mencatat aktivitas pembayaran piutang.');
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pembayaran piutang gagal.');
            }
            $db->transCommit();
        } catch (DomainException $exception) {
            $db->transRollback();
            return redirect()->to('/piutang')->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/piutang')->with('success', 'Pembayaran piutang berhasil dicatat.');
    }

    public function customers(): string
    {
        helper('form');
        return view('receivables/customers', [
            'customers' => db_connect()->query(
                "SELECT pelanggan.*,
                        COALESCE(open_receivables.outstanding_amount, 0.00) AS outstanding_amount
                 FROM pelanggan
                 LEFT JOIN (
                     SELECT piutang.customer_id,
                            SUM(piutang.original_amount - COALESCE(payments.paid_amount, 0.00)
                                - COALESCE(adjustments.adjusted_amount, 0.00)) AS outstanding_amount
                     FROM piutang
                     LEFT JOIN (
                         SELECT receivable_id, SUM(amount) AS paid_amount
                         FROM pembayaran_piutang GROUP BY receivable_id
                     ) AS payments ON payments.receivable_id = piutang.receivable_id
                     LEFT JOIN (
                         SELECT receivable_id, SUM(amount) AS adjusted_amount
                         FROM penyesuaian_piutang GROUP BY receivable_id
                     ) AS adjustments ON adjustments.receivable_id = piutang.receivable_id
                     WHERE piutang.status IN ('OPEN', 'PARTIALLY_PAID')
                     GROUP BY piutang.customer_id
                 ) AS open_receivables ON open_receivables.customer_id = pelanggan.customer_id
                 ORDER BY pelanggan.customer_name",
            )->getResultArray(),
            'roleCode' => (string) session()->get('role_code'),
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
            'formErrors' => session()->getFlashdata('form_errors') ?? [],
        ]);
    }

    public function createCustomer(): RedirectResponse
    {
        $returnToCashier = $this->postString('return_to') === 'kasir';
        $data = [
            'customer_code' => strtoupper(trim($this->postString('customer_code'))),
            'customer_name' => trim($this->postString('customer_name')),
            'business_name' => trim($this->postString('business_name')),
            'phone' => trim($this->postString('phone')),
            'address' => trim($this->postString('address')),
            'credit_limit' => trim($this->postString('credit_limit')),
            'is_active' => true,
        ];
        if ($data['customer_code'] === '' || mb_strlen($data['customer_code']) > 80
            || $data['customer_name'] === '' || mb_strlen($data['customer_name']) > 150
            || mb_strlen($data['business_name']) > 150 || mb_strlen($data['phone']) > 40
            || ! $this->isMoney($data['credit_limit'], false)) {
            $redirect = $returnToCashier
                ? redirect()->to('/kasir?sale_type=GROSIR&customer_form=1')
                : redirect()->back();

            return $redirect->withInput()->with('customer_form_errors', [
                'customer' => 'Kode dan nama pelanggan wajib diisi; periksa panjang kontak serta plafon bon yang tidak boleh negatif.',
            ]);
        }

        $db = db_connect();
        if ($db->table('pelanggan')->where('customer_code', $data['customer_code'])->countAllResults() > 0) {
            $redirect = $returnToCashier
                ? redirect()->to('/kasir?sale_type=GROSIR&customer_form=1')
                : redirect()->back();

            return $redirect->withInput()->with('customer_form_errors', [
                'customer_code' => 'Kode pelanggan sudah digunakan.',
            ]);
        }

        $db->transBegin();
        try {
            if (! $db->table('pelanggan')->insert($data)) {
                throw new RuntimeException('Gagal menyimpan pelanggan.');
            }
            $customerId = (int) $db->insertID();
            if (! $db->table('log_aktivitas')->insert([
                'user_id' => (int) session()->get('user_id'),
                'action' => 'CREATE',
                'entity_type' => 'PELANGGAN',
                'entity_id' => (string) $customerId,
                'after_data' => json_encode([
                    'customer_code' => $data['customer_code'],
                    'customer_name' => $data['customer_name'],
                    'business_name' => $data['business_name'],
                    'credit_limit' => $data['credit_limit'],
                ], JSON_THROW_ON_ERROR),
            ])) {
                throw new RuntimeException('Gagal mencatat aktivitas pelanggan.');
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penyimpanan pelanggan gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        if ($returnToCashier) {
            $this->preserveCheckoutCart();
        }

        if ($returnToCashier || session()->get('role_code') === 'KASIR') {
            $cashierUrl = '/kasir?sale_type=GROSIR&customer_id=' . $customerId;
            $paymentMethod = $this->postString('payment_method');
            $dueDate = $this->postString('due_date');
            if (in_array($paymentMethod, ['TUNAI', 'TRANSFER', 'BON'], true)) {
                $cashierUrl .= '&payment_method=' . rawurlencode($paymentMethod);
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                $cashierUrl .= '&due_date=' . rawurlencode($dueDate);
            }

            return redirect()->to($cashierUrl)
                ->with('success', 'Pelanggan berhasil ditambahkan dan dipilih untuk transaksi.');
        }

        return redirect()->to('/piutang/pelanggan')->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function updateCustomerLimit(string $id): RedirectResponse
    {
        if (! ctype_digit($id) || (int) $id < 1) {
            return redirect()->to('/piutang/pelanggan')->with('error', 'Pelanggan tidak ditemukan.');
        }

        $creditLimit = trim($this->postString('credit_limit'));
        if (! $this->isMoney($creditLimit, false)) {
            return redirect()->to('/piutang/pelanggan')->with('error', 'Plafon bon harus berupa angka dengan maksimal dua angka desimal.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $customer = $db->query(
                'SELECT customer_id, customer_name, credit_limit
                 FROM pelanggan WHERE customer_id = ? FOR UPDATE',
                [(int) $id],
            )->getRowArray();
            if ($customer === null) {
                throw new DomainException('Pelanggan tidak ditemukan.');
            }

            $outstanding = $db->query(
                "SELECT COALESCE(SUM(piutang.original_amount - COALESCE(payments.paid_amount, 0.00)
                           - COALESCE(adjustments.adjusted_amount, 0.00)), 0.00) AS outstanding_amount
                 FROM piutang
                 LEFT JOIN (
                     SELECT receivable_id, SUM(amount) AS paid_amount
                     FROM pembayaran_piutang GROUP BY receivable_id
                 ) AS payments ON payments.receivable_id = piutang.receivable_id
                 LEFT JOIN (
                     SELECT receivable_id, SUM(amount) AS adjusted_amount
                     FROM penyesuaian_piutang GROUP BY receivable_id
                 ) AS adjustments ON adjustments.receivable_id = piutang.receivable_id
                 WHERE piutang.customer_id = ? AND piutang.status IN ('OPEN', 'PARTIALLY_PAID')",
                [(int) $id],
            )->getRowArray();
            $limitCents = $this->scaledInteger($creditLimit);
            $outstandingCents = $this->scaledInteger((string) $outstanding['outstanding_amount']);
            if ($outstandingCents === null) {
                throw new DomainException('Total piutang pelanggan melampaui batas perhitungan plafon. Periksa data piutang terlebih dahulu.');
            }
            if ($limitCents === null || $limitCents < $outstandingCents) {
                throw new DomainException('Plafon tidak boleh lebih rendah daripada total piutang yang masih terbuka.');
            }

            if (! $db->table('pelanggan')->where('customer_id', (int) $id)->update([
                'credit_limit' => $creditLimit,
            ])) {
                throw new RuntimeException('Gagal memperbarui plafon bon pelanggan.');
            }
            if (! $db->table('log_aktivitas')->insert([
                'user_id' => (int) session()->get('user_id'),
                'action' => 'UPDATE_CREDIT_LIMIT',
                'entity_type' => 'PELANGGAN',
                'entity_id' => (string) $id,
                'before_data' => json_encode(['credit_limit' => $customer['credit_limit']], JSON_THROW_ON_ERROR),
                'after_data' => json_encode(['credit_limit' => $creditLimit], JSON_THROW_ON_ERROR),
            ])) {
                throw new RuntimeException('Gagal mencatat perubahan plafon bon.');
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi perubahan plafon bon gagal.');
            }
            $db->transCommit();
        } catch (DomainException $exception) {
            $db->transRollback();
            return redirect()->to('/piutang/pelanggan')->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/piutang/pelanggan')->with('success', 'Plafon bon pelanggan berhasil diperbarui.');
    }

    public function deactivateCustomer(string $id): RedirectResponse
    {
        if (! ctype_digit($id) || (int) $id < 1) {
            return redirect()->to('/piutang/pelanggan')->with('error', 'Pelanggan tidak ditemukan.');
        }

        $db = db_connect();
        $customer = $db->table('pelanggan')->where('customer_id', (int) $id)->get()->getRowArray();
        if ($customer === null) {
            return redirect()->to('/piutang/pelanggan')->with('error', 'Pelanggan tidak ditemukan.');
        }
        if (! $customer['is_active']) {
            return redirect()->to('/piutang/pelanggan')->with('success', 'Pelanggan sudah nonaktif.');
        }

        $db->transBegin();
        try {
            if (! $db->table('pelanggan')->where('customer_id', (int) $id)->update(['is_active' => false])) {
                throw new RuntimeException('Gagal menonaktifkan pelanggan.');
            }
            if (! $db->table('log_aktivitas')->insert([
                'user_id' => (int) session()->get('user_id'),
                'action' => 'DEACTIVATE',
                'entity_type' => 'PELANGGAN',
                'entity_id' => (string) $id,
                'before_data' => json_encode(['is_active' => true], JSON_THROW_ON_ERROR),
                'after_data' => json_encode(['is_active' => false], JSON_THROW_ON_ERROR),
            ])) {
                throw new RuntimeException('Gagal mencatat aktivitas pelanggan.');
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penonaktifan pelanggan gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/piutang/pelanggan')->with('success', 'Pelanggan dinonaktifkan. Riwayat transaksi tetap tersimpan.');
    }

    public function policy(): string
    {
        helper('form');
        $today = date('Y-m-d');
        $db = db_connect();

        return view('receivables/policy', [
            'activePolicy' => $db->table('kebijakan_kredit')
                ->where('effective_from <=', $today)
                ->groupStart()
                    ->where('effective_to', null)
                    ->orWhere('effective_to >=', $today)
                ->groupEnd()
                ->orderBy('effective_from', 'DESC')
                ->orderBy('credit_policy_id', 'DESC')
                ->get()
                ->getRowArray(),
            'policies' => $db->table('kebijakan_kredit')
                ->select('kebijakan_kredit.*, pengguna.full_name')
                ->join('pengguna', 'pengguna.user_id = kebijakan_kredit.set_by')
                ->orderBy('kebijakan_kredit.credit_policy_id', 'DESC')
                ->limit(20)
                ->get()
                ->getResultArray(),
            'roleCode' => (string) session()->get('role_code'),
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function savePolicy(): RedirectResponse
    {
        $returnToCashier = $this->postString('return_to') === 'kasir';
        $cashierUrl = '/kasir?sale_type=GROSIR&payment_method=BON';
        if ($returnToCashier) {
            $customerId = $this->postString('customer_id');
            $dueDate = $this->postString('due_date');
            if (ctype_digit($customerId) && (int) $customerId > 0) {
                $cashierUrl .= '&customer_id=' . rawurlencode($customerId);
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                $cashierUrl .= '&due_date=' . rawurlencode($dueDate);
            }
            $this->preserveCheckoutCart();
        }

        $days = $this->postString('max_tenor_days');
        if (! ctype_digit($days) || (int) $days < 1 || (int) $days > 3650) {
            $redirectUrl = $returnToCashier ? $cashierUrl . '&policy_form=1' : '/piutang/kebijakan';

            return redirect()->to($redirectUrl)->withInput()->with('error', 'Tenor maksimum harus berupa bilangan bulat antara 1 sampai 3650 hari.');
        }

        $db = db_connect();
        $today = date('Y-m-d');
        $db->transBegin();
        try {
            $currentPolicies = $db->query(
                'SELECT credit_policy_id, effective_from
                 FROM kebijakan_kredit
                 WHERE effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
                 FOR UPDATE',
                [$today, $today],
            )->getResultArray();
            foreach ($currentPolicies as $currentPolicy) {
                $endDate = $currentPolicy['effective_from'] < $today ? date('Y-m-d', strtotime('-1 day')) : $today;
                if (! $db->table('kebijakan_kredit')
                    ->where('credit_policy_id', (int) $currentPolicy['credit_policy_id'])
                    ->update(['effective_to' => $endDate])) {
                    throw new RuntimeException('Gagal mengakhiri masa berlaku kebijakan tenor sebelumnya.');
                }
            }
            if (! $db->table('kebijakan_kredit')->insert([
                'set_by' => (int) session()->get('user_id'),
                'max_tenor_days' => (int) $days,
                'effective_from' => $today,
                'effective_to' => null,
            ])) {
                throw new RuntimeException('Gagal menyimpan kebijakan tenor bon.');
            }
            $policyId = (int) $db->insertID();
            if (! $db->table('log_aktivitas')->insert([
                'user_id' => (int) session()->get('user_id'),
                'action' => 'SET_CREDIT_POLICY',
                'entity_type' => 'KEBIJAKAN_KREDIT',
                'entity_id' => (string) $policyId,
                'after_data' => json_encode([
                    'max_tenor_days' => (int) $days,
                    'effective_from' => $today,
                ], JSON_THROW_ON_ERROR),
            ])) {
                throw new RuntimeException('Gagal mencatat perubahan kebijakan tenor.');
            }
            NotificationService::notifyRoles(
                $db,
                ['OWNER', 'KASIR'],
                'CREDIT_POLICY_UPDATED',
                'Kebijakan tenor Bon diperbarui',
                'Batas tenor Bon toko sekarang ' . (int) $days . ' hari.',
                'piutang/kebijakan',
            );
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pengaturan kebijakan tenor gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to($returnToCashier ? $cashierUrl : '/piutang/kebijakan')
            ->with('success', 'Kebijakan tenor bon berhasil diperbarui.');
    }

    private function preserveCheckoutCart(): void
    {
        $cartProductIds = $this->request->getPost('cart_product_id');
        $cartQuantities = $this->request->getPost('cart_quantity');
        if (! is_array($cartProductIds) || ! is_array($cartQuantities)
            || count($cartProductIds) !== count($cartQuantities)
            || count($cartProductIds) > 100) {
            return;
        }

        $preservedProductIds = [];
        $preservedQuantities = [];
        $cartQuantities = array_values($cartQuantities);
        foreach (array_values($cartProductIds) as $index => $productId) {
            $quantity = $cartQuantities[$index] ?? null;
            if (! is_scalar($productId) || ! ctype_digit((string) $productId)
                || (int) $productId < 1 || ! is_scalar($quantity)
                || ! preg_match('/^\d{1,11}(?:\.\d{1,3})?$/', (string) $quantity)
                || (float) $quantity <= 0) {
                continue;
            }
            $preservedProductIds[] = (string) $productId;
            $preservedQuantities[] = (string) $quantity;
        }
        session()->setFlashdata('checkout_cart', [
            'product_ids' => $preservedProductIds,
            'quantities' => $preservedQuantities,
        ]);
    }

    private function isMoney(string $value, bool $mustBePositive): bool
    {
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $value)) {
            return false;
        }
        $amount = $this->scaledInteger($value);

        return $amount !== null && (! $mustBePositive || $amount > 0);
    }

    private function postString(string $key): string
    {
        $value = $this->request->getPost($key);

        return is_string($value) ? $value : '';
    }

    private function scaledInteger(string $value): ?int
    {
        if (! preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $value, $matches)) {
            return null;
        }

        return (int) ($matches[1] . str_pad($matches[2] ?? '', 2, '0'));
    }
}
