<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class Operations extends BaseController
{
    public function transactions(): string
    {
        helper('form');
        [$from, $to] = $this->dateRange();
        $searchInput = $this->request->getGet('q');
        $search = trim(is_string($searchInput) ? $searchInput : '');
        $db = db_connect();
        $builder = $db->table('penjualan AS s')
            ->select('s.sale_id, s.invoice_number, s.sold_at, s.sale_type, s.payment_method, s.status, s.total_amount, p.customer_name, u.full_name')
            ->join('pengguna AS u', 'u.user_id = s.cashier_id')
            ->join('pelanggan AS p', 'p.customer_id = s.customer_id', 'left')
            ->where('s.sold_at >=', $from . ' 00:00:00')
            ->where('s.sold_at <=', $to . ' 23:59:59')
            ->orderBy('s.sold_at', 'DESC')
            ->limit(100);

        if ($search !== '') {
            $builder->groupStart()
                ->like('s.invoice_number', $search)
                ->orLike('p.customer_name', $search)
                ->orLike('u.full_name', $search)
                ->groupEnd();
        }

        return view('operations/transactions', [
            'transactions' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
            'search' => $search,
        ]);
    }

    public function movements(): string
    {
        helper(['form', 'inventory']);
        [$from, $to] = $this->dateRange();
        $searchInput = $this->request->getGet('q');
        $search = trim(is_string($searchInput) ? $searchInput : '');
        $db = db_connect();
        $builder = $db->table('pergerakan_persediaan AS m')
            ->select("m.inventory_movement_id, m.occurred_at, m.quantity_delta, b.product_code, b.product_name, u.unit_symbol, l.location_name, usr.full_name, COALESCE(sa.reason, '') AS adjustment_reason, CASE WHEN m.goods_receipt_item_id IS NOT NULL THEN 'Masuk' WHEN m.sale_allocation_id IS NOT NULL THEN 'Keluar' WHEN m.stock_transfer_item_id IS NOT NULL THEN 'Mutasi' WHEN m.stock_adjustment_item_id IS NOT NULL THEN 'Penyesuaian' WHEN m.opening_stock_item_id IS NOT NULL THEN 'Saldo awal' WHEN m.sale_adjustment_item_id IS NOT NULL THEN 'Retur' ELSE 'Pergerakan' END AS movement_type", false)
            ->join('barang AS b', 'b.product_id = m.product_id')
            ->join('satuan AS u', 'u.unit_id = b.unit_id')
            ->join('lokasi_persediaan AS l', 'l.location_id = m.location_id')
            ->join('pengguna AS usr', 'usr.user_id = m.recorded_by')
            ->join('detail_penyesuaian_stok AS si', 'si.stock_adjustment_item_id = m.stock_adjustment_item_id', 'left')
            ->join('penyesuaian_stok AS sa', 'sa.stock_adjustment_id = si.stock_adjustment_id', 'left')
            ->where('m.occurred_at >=', $from . ' 00:00:00')
            ->where('m.occurred_at <=', $to . ' 23:59:59')
            ->orderBy('m.occurred_at', 'DESC')
            ->limit(200);

        if ($search !== '') {
            $builder->groupStart()
                ->like('b.product_code', $search)
                ->orLike('b.product_name', $search)
                ->orLike('usr.full_name', $search)
                ->groupEnd();
        }

        return view('operations/movements', [
            'movements' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
            'search' => $search,
        ]);
    }

    public function salesReport(): string|ResponseInterface
    {
        helper('form');
        [$from, $to] = $this->dateRange();
        $db = db_connect();
        $summary = $db->query(
            "SELECT COUNT(CASE WHEN s.status <> 'VOIDED' THEN 1 END) AS transaction_count,
                    COALESCE(SUM(CASE WHEN s.status = 'VOIDED' THEN 0 ELSE GREATEST(0, s.total_amount - COALESCE(r.refund_total, 0)) END), 0) AS net_sales,
                    COALESCE(SUM(CASE WHEN s.sale_type = 'GROSIR' AND s.status <> 'VOIDED' THEN GREATEST(0, s.total_amount - COALESCE(r.refund_total, 0)) ELSE 0 END), 0) AS wholesale_sales
             FROM penjualan AS s
             LEFT JOIN (
                SELECT a.sale_id, SUM(d.refund_amount) AS refund_total
                FROM penyesuaian_penjualan AS a
                JOIN detail_penyesuaian_penjualan AS d ON d.sale_adjustment_id = a.sale_adjustment_id
                WHERE a.status = 'APPROVED'
                GROUP BY a.sale_id
             ) AS r ON r.sale_id = s.sale_id
             WHERE s.sold_at >= ? AND s.sold_at <= ?",
            [$from . ' 00:00:00', $to . ' 23:59:59'],
        )->getRowArray() ?? [];

        $rows = $db->query(
            "SELECT s.invoice_number, s.sold_at, COALESCE(p.customer_name, 'Pelanggan umum') AS customer_name,
                    s.sale_type, s.payment_method, s.status,
                    CASE WHEN s.status = 'VOIDED' THEN 0 ELSE GREATEST(0, s.total_amount - COALESCE(r.refund_total, 0)) END AS net_amount
             FROM penjualan AS s
             LEFT JOIN pelanggan AS p ON p.customer_id = s.customer_id
             LEFT JOIN (
                SELECT a.sale_id, SUM(d.refund_amount) AS refund_total
                FROM penyesuaian_penjualan AS a
                JOIN detail_penyesuaian_penjualan AS d ON d.sale_adjustment_id = a.sale_adjustment_id
                WHERE a.status = 'APPROVED'
                GROUP BY a.sale_id
             ) AS r ON r.sale_id = s.sale_id
             WHERE s.sold_at >= ? AND s.sold_at <= ?
             ORDER BY s.sold_at DESC
             LIMIT 500",
            [$from . ' 00:00:00', $to . ' 23:59:59'],
        )->getResultArray();

        if ($this->request->getGet('format') === 'csv') {
            $output = fopen('php://temp', 'r+');
            if ($output === false) {
                throw new RuntimeException('Gagal menyiapkan ekspor laporan.');
            }
            fputcsv($output, ['Nomor faktur', 'Tanggal', 'Pelanggan', 'Jenis', 'Metode', 'Status', 'Penjualan bersih']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['invoice_number'],
                    $row['sold_at'],
                    $row['customer_name'],
                    $row['sale_type'],
                    $row['payment_method'],
                    $row['status'],
                    $row['net_amount'],
                ]);
            }
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            if ($csv === false) {
                throw new RuntimeException('Gagal membaca data ekspor laporan.');
            }

            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="laporan-penjualan-' . $from . '-sampai-' . $to . '.csv"')
                ->setBody("\xEF\xBB\xBF" . $csv);
        }

        $daily = $db->query(
            "SELECT DATE(s.sold_at) AS sale_date,
                    COALESCE(SUM(CASE WHEN s.status = 'VOIDED' THEN 0 ELSE GREATEST(0, s.total_amount - COALESCE(r.refund_total, 0)) END), 0) AS net_sales
             FROM penjualan AS s
             LEFT JOIN (
                SELECT a.sale_id, SUM(d.refund_amount) AS refund_total
                FROM penyesuaian_penjualan AS a
                JOIN detail_penyesuaian_penjualan AS d ON d.sale_adjustment_id = a.sale_adjustment_id
                WHERE a.status = 'APPROVED'
                GROUP BY a.sale_id
             ) AS r ON r.sale_id = s.sale_id
             WHERE s.sold_at >= ? AND s.sold_at <= ?
             GROUP BY DATE(s.sold_at)
             ORDER BY sale_date",
            [$from . ' 00:00:00', $to . ' 23:59:59'],
        )->getResultArray();
        $total = (float) ($summary['net_sales'] ?? 0);

        return view('operations/sales_report', [
            'summary' => $summary,
            'rows' => $rows,
            'daily' => $daily,
            'from' => $from,
            'to' => $to,
            'average' => (int) ($summary['transaction_count'] ?? 0) > 0 ? $total / (int) $summary['transaction_count'] : 0,
            'wholesalePercent' => $total > 0 ? (float) $summary['wholesale_sales'] / $total * 100 : 0,
        ]);
    }

    public function annualProfitLoss(): string|\CodeIgniter\HTTP\Response
    {
        helper('form');
        $yearInput = $this->request->getGet('year');
        $yearValue = is_string($yearInput) ? $yearInput : '';
        $year = preg_match('/^\d{4}$/', $yearValue) === 1 && (int) $yearValue >= 2000 && (int) $yearValue <= (int) date('Y')
            ? (int) $yearValue
            : (int) date('Y');
        $db = db_connect();
        $revenue = (float) ($db->query(
            "SELECT COALESCE(SUM(CASE WHEN s.status = 'VOIDED' THEN 0 ELSE GREATEST(0, s.total_amount - COALESCE(r.refund_total, 0)) END), 0) AS total
             FROM penjualan AS s
             LEFT JOIN (
                SELECT a.sale_id, SUM(d.refund_amount) AS refund_total
                FROM penyesuaian_penjualan AS a
                JOIN detail_penyesuaian_penjualan AS d ON d.sale_adjustment_id = a.sale_adjustment_id
                WHERE a.status = 'APPROVED'
                GROUP BY a.sale_id
             ) AS r ON r.sale_id = s.sale_id
             WHERE YEAR(s.sold_at) = ?",
            [$year],
        )->getRowArray()['total'] ?? 0);
        $costOfGoods = (float) ($db->query(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN m.sale_allocation_id IS NOT NULL AND s.status <> 'VOIDED' THEN -m.quantity_delta * m.unit_cost_snapshot
                    WHEN m.sale_adjustment_item_id IS NOT NULL AND a.status = 'APPROVED' THEN -m.quantity_delta * m.unit_cost_snapshot
                    ELSE 0
                END
             ), 0) AS total
             FROM pergerakan_persediaan AS m
             LEFT JOIN alokasi_stok_penjualan AS al ON al.allocation_id = m.sale_allocation_id
             LEFT JOIN detail_penjualan AS si ON si.sale_item_id = al.sale_item_id
             LEFT JOIN penjualan AS s ON s.sale_id = si.sale_id
             LEFT JOIN detail_penyesuaian_penjualan AS ai ON ai.sale_adjustment_item_id = m.sale_adjustment_item_id
             LEFT JOIN penyesuaian_penjualan AS a ON a.sale_adjustment_id = ai.sale_adjustment_id
             WHERE YEAR(m.occurred_at) = ?",
            [$year],
        )->getRowArray()['total'] ?? 0);
        $operatingExpenses = (float) ($db->query(
            "SELECT
                (SELECT COALESCE(SUM(amount), 0) FROM pengeluaran_operasional WHERE YEAR(spent_at) = ?) +
                (SELECT COALESCE(SUM(amount), 0) FROM transaksi_kas_manual WHERE direction = 'KELUAR' AND YEAR(occurred_at) = ?) AS total",
            [$year, $year],
        )->getRowArray()['total'] ?? 0);
        $netProfit = $revenue - $costOfGoods - $operatingExpenses;

        if ($this->request->getGet('format') === 'csv') {
            $csv = "Ringkasan laba rugi tahun {$year}\r\nPendapatan penjualan,{$revenue}\r\nHarga pokok penjualan,{$costOfGoods}\r\nBiaya operasional,{$operatingExpenses}\r\nLaba bersih,{$netProfit}\r\n";

            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="laba-rugi-' . $year . '.csv"')
                ->setBody("\xEF\xBB\xBF" . $csv);
        }

        return view('operations/profit_loss', [
            'year' => $year,
            'revenue' => $revenue,
            'costOfGoods' => $costOfGoods,
            'operatingExpenses' => $operatingExpenses,
            'netProfit' => $netProfit,
            'margin' => $revenue > 0 ? $netProfit / $revenue * 100 : 0,
        ]);
    }

    public function cashFlow(): string
    {
        helper('form');
        [$from, $to] = $this->dateRange();
        $db = db_connect();
        $summary = $db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN direction = 'MASUK' THEN amount ELSE 0 END), 0) AS inflow,
                COALESCE(SUM(CASE WHEN direction = 'KELUAR' THEN amount ELSE 0 END), 0) AS outflow,
                COUNT(*) AS entry_count
             FROM mutasi_kas
             WHERE occurred_at >= ? AND occurred_at <= ?",
            [$from . ' 00:00:00', $to . ' 23:59:59'],
        )->getRowArray() ?? [];

        return view('operations/cash_flow', [
            'accounts' => $db->table('akun_kas')->where('is_active', true)->orderBy('account_name')->get()->getResultArray(),
            'categories' => $db->table('kategori_pengeluaran_operasional')->where('is_active', true)->orderBy('category_name')->get()->getResultArray(),
            'movements' => $db->table('mutasi_kas AS m')
                ->select("m.cash_movement_id, m.occurred_at, m.direction, m.amount, m.payment_method, m.notes, a.account_name, u.full_name, COALESCE(s.invoice_number, '') AS invoice_number, COALESCE(p.category, ec.category_name, '') AS category", false)
                ->join('akun_kas AS a', 'a.cash_account_id = m.cash_account_id')
                ->join('pengguna AS u', 'u.user_id = m.recorded_by')
                ->join('penjualan AS s', 's.sale_id = m.sale_id', 'left')
                ->join('transaksi_kas_manual AS p', 'p.manual_cash_transaction_id = m.manual_cash_transaction_id', 'left')
                ->join('pengeluaran_operasional AS e', 'e.operating_expense_id = m.operating_expense_id', 'left')
                ->join('kategori_pengeluaran_operasional AS ec', 'ec.expense_category_id = e.expense_category_id', 'left')
                ->where('m.occurred_at >=', $from . ' 00:00:00')
                ->where('m.occurred_at <=', $to . ' 23:59:59')
                ->orderBy('m.occurred_at', 'DESC')
                ->limit(100)
                ->get()
                ->getResultArray(),
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
            'formErrors' => session()->getFlashdata('form_errors') ?? [],
        ]);
    }

    public function recordCashTransaction(): RedirectResponse
    {
        $direction = $this->postString('direction');
        $category = trim($this->postString('category'));
        $description = trim($this->postString('description'));
        $amount = trim($this->postString('amount'));
        $occurredAt = $this->postString('occurred_at');
        $accountId = $this->postString('cash_account_id');

        if (! in_array($direction, ['MASUK', 'KELUAR'], true)
            || $category === '' || mb_strlen($category) > 100
            || $description === '' || mb_strlen($description) > 255
            || ! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $amount)
            || (float) $amount <= 0
            || ! ctype_digit($accountId)
            || ! $this->isDateTimeLocal($occurredAt)) {
            return redirect()->back()->withInput()->with('error', 'Lengkapi jenis, kategori, nominal positif, akun, tanggal, dan keterangan dengan benar.');
        }

        $db = db_connect();
        $account = $db->table('akun_kas')->where('cash_account_id', (int) $accountId)->where('is_active', true)->get()->getRowArray();
        if ($account === null) {
            return redirect()->back()->withInput()->with('error', 'Akun kas/bank tidak aktif atau tidak ditemukan.');
        }

        $db->transBegin();
        try {
            if (! $db->table('transaksi_kas_manual')->insert([
                'recorded_by' => (int) session()->get('user_id'),
                'direction' => $direction,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'occurred_at' => str_replace('T', ' ', $occurredAt) . ':00',
            ])) {
                throw new RuntimeException('Gagal menyimpan transaksi kas manual.');
            }
            $manualId = (int) $db->insertID();
            if (! $db->table('mutasi_kas')->insert([
                'cash_account_id' => (int) $accountId,
                'manual_cash_transaction_id' => $manualId,
                'recorded_by' => (int) session()->get('user_id'),
                'direction' => $direction,
                'payment_method' => $account['account_type'] === 'BANK' ? 'TRANSFER' : 'TUNAI',
                'amount' => $amount,
                'occurred_at' => str_replace('T', ' ', $occurredAt) . ':00',
                'notes' => $category . ': ' . $description,
            ])) {
                throw new RuntimeException('Gagal memperbarui buku kas/bank.');
            }
            $this->writeActivity($db, 'CREATE', 'TRANSAKSI_KAS_MANUAL', $manualId, null, [
                'direction' => $direction,
                'category' => $category,
                'amount' => $amount,
                'cash_account_id' => (int) $accountId,
            ]);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi kas tidak berhasil disimpan secara utuh.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/arus-kas')->with('success', 'Transaksi kas berhasil dicatat dan saldo akun diperbarui.');
    }

    public function archive(): string
    {
        helper('form');
        [$from, $to] = $this->dateRange();

        return view('operations/archive', [
            'events' => db_connect()->table('log_aktivitas AS l')
                ->select('l.activity_log_id, l.action, l.entity_type, l.entity_id, l.created_at, l.before_data, l.after_data, u.full_name, r.role_name')
                ->join('pengguna AS u', 'u.user_id = l.user_id')
                ->join('peran AS r', 'r.role_id = u.role_id')
                ->where('l.created_at >=', $from . ' 00:00:00')
                ->where('l.created_at <=', $to . ' 23:59:59')
                ->orderBy('l.created_at', 'DESC')
                ->limit(200)
                ->get()
                ->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dateRange(): array
    {
        $today = date('Y-m-d');
        $defaultFrom = date('Y-m-01');
        $fromInput = $this->request->getGet('from');
        $toInput = $this->request->getGet('to');
        $from = $this->validDate(is_string($fromInput) ? $fromInput : '') ?? $defaultFrom;
        $to = $this->validDate(is_string($toInput) ? $toInput : '') ?? $today;

        return $from <= $to ? [$from, $to] : [$defaultFrom, $today];
    }

    private function validDate(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function isDateTimeLocal(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);

        return $date !== false && $date->format('Y-m-d\TH:i') === $value;
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
            throw new RuntimeException('Gagal mencatat aktivitas transaksi.');
        }
    }
}
