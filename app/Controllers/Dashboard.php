<?php

namespace App\Controllers;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $roleCode = (string) session()->get('role_code');
        $canViewFinancials = in_array($roleCode, ['OWNER', 'KASIR'], true);
        $today = date('Y-m-d');

        $stock = $db->query(
            "SELECT COUNT(CASE WHEN COALESCE(stock.total_quantity, 0) <= 0 THEN 1 END) AS out_of_stock,
                    COUNT(CASE WHEN barang.min_stock > 0
                        AND COALESCE(stock.total_quantity, 0) <= barang.min_stock THEN 1 END) AS needs_restock
             FROM barang
             LEFT JOIN (
                 SELECT product_id, SUM(quantity) AS total_quantity
                 FROM saldo_persediaan
                 GROUP BY product_id
             ) AS stock ON stock.product_id = barang.product_id
             WHERE barang.is_active = 1",
        )->getRowArray();

        $metrics = [
            'outOfStock' => (int) ($stock['out_of_stock'] ?? 0),
            'needsRestock' => (int) ($stock['needs_restock'] ?? 0),
        ];
        if ($canViewFinancials) {
            $salesParams = [
                $today . ' 00:00:00',
                (new \DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
            ];
            $salesCashierFilter = '';
            if ($roleCode === 'KASIR') {
                $salesCashierFilter = ' AND penjualan.cashier_id = ?';
                $salesParams[] = (int) session()->get('user_id');
            }
            $sales = $db->query(
                "SELECT COUNT(*) AS sale_count,
                        COALESCE(SUM(penjualan.total_amount - COALESCE(refunds.refund_amount, 0.00)), 0.00) AS total_amount,
                        COUNT(CASE WHEN sale_type = 'ECERAN' THEN 1 END) AS retail_count,
                        COUNT(CASE WHEN sale_type = 'GROSIR' THEN 1 END) AS wholesale_count
                 FROM penjualan
                 LEFT JOIN (
                     SELECT penyesuaian_penjualan.sale_id, SUM(detail_penyesuaian_penjualan.refund_amount) AS refund_amount
                     FROM penyesuaian_penjualan
                     JOIN detail_penyesuaian_penjualan
                         ON detail_penyesuaian_penjualan.sale_adjustment_id = penyesuaian_penjualan.sale_adjustment_id
                     WHERE penyesuaian_penjualan.status = 'APPROVED'
                       AND penyesuaian_penjualan.adjustment_type = 'RETUR'
                     GROUP BY penyesuaian_penjualan.sale_id
                 ) AS refunds ON refunds.sale_id = penjualan.sale_id
                 WHERE penjualan.status IN ('POSTED', 'PARTIALLY_RETURNED')
                   AND penjualan.sold_at >= ? AND penjualan.sold_at < ?" . $salesCashierFilter,
                $salesParams,
            )->getRowArray();

            $receivables = $db->query(
                "SELECT
                    COALESCE(SUM(GREATEST(
                        piutang.original_amount
                            - COALESCE(payments.paid_amount, 0.00)
                            - COALESCE(adjustments.adjusted_amount, 0.00),
                        0.00
                    )), 0.00) AS outstanding_amount,
                    COUNT(CASE WHEN piutang.due_date < ? AND
                        piutang.original_amount
                            - COALESCE(payments.paid_amount, 0.00)
                            - COALESCE(adjustments.adjusted_amount, 0.00) > 0
                        THEN 1 END) AS overdue_count
                 FROM piutang
                 LEFT JOIN (
                     SELECT receivable_id, SUM(amount) AS paid_amount
                     FROM pembayaran_piutang
                     GROUP BY receivable_id
                 ) AS payments ON payments.receivable_id = piutang.receivable_id
                 LEFT JOIN (
                     SELECT receivable_id, SUM(amount) AS adjusted_amount
                     FROM penyesuaian_piutang
                     GROUP BY receivable_id
                 ) AS adjustments ON adjustments.receivable_id = piutang.receivable_id
                 WHERE piutang.status IN ('OPEN', 'PARTIALLY_PAID')",
                [$today],
            )->getRowArray();

            $metrics['saleCount'] = (int) ($sales['sale_count'] ?? 0);
            $metrics['salesToday'] = (float) ($sales['total_amount'] ?? 0);
            $metrics['retailCount'] = (int) ($sales['retail_count'] ?? 0);
            $metrics['wholesaleCount'] = (int) ($sales['wholesale_count'] ?? 0);
            $metrics['outstandingReceivables'] = (float) ($receivables['outstanding_amount'] ?? 0);
            $metrics['overdueCount'] = (int) ($receivables['overdue_count'] ?? 0);

            $adjustments = $db->query(
                "SELECT COUNT(*) AS pending_count,
                        COUNT(CASE WHEN adjustment_type = 'VOID' THEN 1 END) AS pending_voids,
                        COUNT(CASE WHEN adjustment_type = 'RETUR' THEN 1 END) AS pending_returns
                 FROM penyesuaian_penjualan
                 WHERE status = 'PENDING'",
            )->getRowArray();
            $metrics['pendingAdjustments'] = (int) ($adjustments['pending_count'] ?? 0);
            $metrics['pendingVoids'] = (int) ($adjustments['pending_voids'] ?? 0);
            $metrics['pendingReturns'] = (int) ($adjustments['pending_returns'] ?? 0);

            $trendStart = (new \DateTimeImmutable($today))->modify('-6 days');
            $trendParams = [
                $trendStart->format('Y-m-d') . ' 00:00:00',
                (new \DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
            ];
            $trendCashierFilter = '';
            if ($roleCode === 'KASIR') {
                $trendCashierFilter = ' AND penjualan.cashier_id = ?';
                $trendParams[] = (int) session()->get('user_id');
            }
            $trendRows = $db->query(
                "SELECT DATE(penjualan.sold_at) AS sale_date,
                        SUM(GREATEST(penjualan.total_amount - COALESCE(refunds.refund_amount, 0.00), 0.00)) AS net_sales
                 FROM penjualan
                 LEFT JOIN (
                     SELECT penyesuaian_penjualan.sale_id,
                            SUM(detail_penyesuaian_penjualan.refund_amount) AS refund_amount
                     FROM penyesuaian_penjualan
                     JOIN detail_penyesuaian_penjualan
                       ON detail_penyesuaian_penjualan.sale_adjustment_id = penyesuaian_penjualan.sale_adjustment_id
                     WHERE penyesuaian_penjualan.status = 'APPROVED'
                     GROUP BY penyesuaian_penjualan.sale_id
                 ) AS refunds ON refunds.sale_id = penjualan.sale_id
                 WHERE penjualan.status IN ('POSTED', 'PARTIALLY_RETURNED', 'RETURNED')
                   AND penjualan.sold_at >= ? AND penjualan.sold_at < ?" . $trendCashierFilter . "
                 GROUP BY DATE(penjualan.sold_at)
                 ORDER BY sale_date",
                $trendParams,
            )->getResultArray();
            $salesByDate = [];
            foreach ($trendRows as $trendRow) {
                $salesByDate[$trendRow['sale_date']] = (float) $trendRow['net_sales'];
            }

            $weekdayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
            $metrics['salesTrend'] = [];
            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $date = $trendStart->modify('+' . $dayOffset . ' days');
                $dateKey = $date->format('Y-m-d');
                $metrics['salesTrend'][] = [
                    'date' => $dateKey,
                    'label' => $weekdayNames[(int) $date->format('N') - 1],
                    'day' => $date->format('d'),
                    'amount' => $salesByDate[$dateKey] ?? 0.0,
                ];
            }
            $maxTrendAmount = max(array_column($metrics['salesTrend'], 'amount') ?: [0.0]);
            foreach ($metrics['salesTrend'] as &$trendDay) {
                $trendDay['height'] = $maxTrendAmount > 0
                    ? max(8, (int) round(($trendDay['amount'] / $maxTrendAmount) * 100))
                    : 8;
            }
            unset($trendDay);

            $metrics['salesTrendTotal'] = array_sum(array_column($metrics['salesTrend'], 'amount'));

            if ($roleCode === 'OWNER') {
                $recentActivity = $db->query(
                    "SELECT log.created_at, log.action, log.entity_type, log.entity_id,
                            pengguna.full_name, peran.role_name
                     FROM log_aktivitas AS log
                     JOIN pengguna ON pengguna.user_id = log.user_id
                     JOIN peran ON peran.role_id = pengguna.role_id
                     ORDER BY log.created_at DESC, log.activity_log_id DESC
                     LIMIT 6",
                )->getResultArray();
            } else {
                $recentActivity = [];
            }

            if ($roleCode === 'OWNER') {
                $cash = $db->query(
                    "SELECT
                        COALESCE((
                            SELECT SUM(CASE WHEN mutasi_kas.direction = 'MASUK'
                                THEN mutasi_kas.amount ELSE -mutasi_kas.amount END)
                            FROM mutasi_kas
                            WHERE mutasi_kas.opening_cash_balance_id IS NULL
                        ), 0.00)
                        + COALESCE((
                            SELECT SUM(saldo_kas_awal.amount)
                            FROM saldo_kas_awal
                            JOIN batch_saldo_awal
                                ON batch_saldo_awal.opening_batch_id = saldo_kas_awal.opening_batch_id
                            WHERE batch_saldo_awal.status = 'POSTED'
                        ), 0.00) AS cash_balance",
                )->getRowArray();
                $metrics['cashBalance'] = (float) ($cash['cash_balance'] ?? 0);
            }
        }

        $hour = (int) date('G');
        $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));

        return view('dashboard/index', [
            'fullName' => (string) session()->get('full_name'),
            'roleName' => (string) session()->get('role_name'),
            'roleCode' => $roleCode,
            'canViewFinancials' => $canViewFinancials,
            'metrics' => $metrics,
            'recentActivity' => $recentActivity ?? [],
            'greeting' => $greeting,
            'today' => $today,
        ]);
    }
}
