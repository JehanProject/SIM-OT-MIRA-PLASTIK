<?php

namespace App\Controllers;

use App\Libraries\NotificationService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use RuntimeException;
use Throwable;

class SaleAdjustments extends BaseController
{
    public function index(): string
    {
        helper('form');
        $db = db_connect();
        $adjustments = $db->query(
            "SELECT adjustment.sale_adjustment_id, adjustment.sale_id, adjustment.adjustment_type,
                    adjustment.status, adjustment.reason, adjustment.requested_at, adjustment.decided_at,
                    adjustment.requested_by, requester.full_name AS requester_name,
                    approver.full_name AS approver_name, sale.invoice_number, sale.payment_method,
                    COALESCE(items.refund_amount, 0.00) AS refund_amount,
                    COALESCE(receivable_adjustments.adjusted_amount, 0.00) AS adjusted_amount,
                    COALESCE(cash_refunds.refunded_amount, 0.00) AS refunded_amount
             FROM penyesuaian_penjualan AS adjustment
             JOIN penjualan AS sale ON sale.sale_id = adjustment.sale_id
             JOIN pengguna AS requester ON requester.user_id = adjustment.requested_by
             LEFT JOIN pengguna AS approver ON approver.user_id = adjustment.approved_by
             LEFT JOIN (
                 SELECT sale_adjustment_id, SUM(refund_amount) AS refund_amount
                 FROM detail_penyesuaian_penjualan GROUP BY sale_adjustment_id
             ) AS items ON items.sale_adjustment_id = adjustment.sale_adjustment_id
             LEFT JOIN (
                 SELECT sale_adjustment_id, SUM(amount) AS adjusted_amount
                 FROM penyesuaian_piutang WHERE sale_adjustment_id IS NOT NULL
                 GROUP BY sale_adjustment_id
             ) AS receivable_adjustments ON receivable_adjustments.sale_adjustment_id = adjustment.sale_adjustment_id
             LEFT JOIN (
                 SELECT sale_adjustment_id, SUM(amount) AS refunded_amount
                 FROM mutasi_kas WHERE sale_adjustment_id IS NOT NULL
                 GROUP BY sale_adjustment_id
             ) AS cash_refunds ON cash_refunds.sale_adjustment_id = adjustment.sale_adjustment_id
             ORDER BY CASE adjustment.status WHEN 'PENDING' THEN 0 ELSE 1 END,
                      adjustment.requested_at DESC, adjustment.sale_adjustment_id DESC",
        )->getResultArray();

        $detailsByAdjustment = [];
        foreach ($db->query(
            "SELECT detail.sale_adjustment_id, item.product_code_snapshot,
                    item.product_name_snapshot, item.unit_snapshot, detail.quantity,
                    detail.refund_amount, location.location_name
             FROM detail_penyesuaian_penjualan AS detail
             JOIN alokasi_stok_penjualan AS allocation
               ON allocation.allocation_id = detail.sale_allocation_id
             JOIN detail_penjualan AS item ON item.sale_item_id = allocation.sale_item_id
             JOIN lokasi_persediaan AS location
               ON location.location_id = detail.restock_location_id
             ORDER BY detail.sale_adjustment_id, detail.sale_adjustment_item_id",
        )->getResultArray() as $detail) {
            $detailsByAdjustment[(int) $detail['sale_adjustment_id']][] = $detail;
        }

        foreach ($adjustments as &$adjustment) {
            $refundCents = $this->moneyToCents((string) $adjustment['refund_amount']) ?? 0;
            $recordedCents = $adjustment['payment_method'] === 'BON'
                ? ($this->moneyToCents((string) $adjustment['adjusted_amount']) ?? 0)
                : ($this->moneyToCents((string) $adjustment['refunded_amount']) ?? 0);
            $adjustment['pending_refund'] = $adjustment['status'] === 'APPROVED'
                ? $this->moneyFromCents(max(0, $refundCents - $recordedCents))
                : '0.00';
            $adjustment['details'] = $detailsByAdjustment[(int) $adjustment['sale_adjustment_id']] ?? [];
        }
        unset($adjustment);

        return view('sale_adjustments/index', [
            'adjustments' => $adjustments,
            'accounts' => $db->table('akun_kas')
                ->where('is_active', true)
                ->orderBy('account_type')
                ->orderBy('account_name')
                ->get()
                ->getResultArray(),
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function requestForm(string $saleId): string|RedirectResponse
    {
        if (! ctype_digit($saleId) || (int) $saleId < 1) {
            return redirect()->to('/kasir')->with('error', 'Transaksi tidak ditemukan.');
        }

        $db = db_connect();
        $sale = $db->query(
            'SELECT sale_id, invoice_number, sale_type, payment_method, status, sold_at
             FROM penjualan WHERE sale_id = ?',
            [(int) $saleId],
        )->getRowArray();
        if ($sale === null) {
            return redirect()->to('/kasir')->with('error', 'Transaksi tidak ditemukan.');
        }

        $allocations = $db->query(
            "SELECT allocation.allocation_id, allocation.quantity AS allocated_quantity,
                    allocation.location_id, location.location_name, location.location_code,
                    item.product_code_snapshot, item.product_name_snapshot, item.unit_snapshot,
                    COALESCE(returns.returned_quantity, 0.000000) AS returned_quantity,
                    allocation.quantity - COALESCE(returns.returned_quantity, 0.000000) AS remaining_quantity
             FROM alokasi_stok_penjualan AS allocation
             JOIN detail_penjualan AS item ON item.sale_item_id = allocation.sale_item_id
             JOIN lokasi_persediaan AS location ON location.location_id = allocation.location_id
             LEFT JOIN (
                 SELECT detail.sale_allocation_id, SUM(detail.quantity) AS returned_quantity
                 FROM detail_penyesuaian_penjualan AS detail
                 JOIN penyesuaian_penjualan AS adjustment
                   ON adjustment.sale_adjustment_id = detail.sale_adjustment_id
                 WHERE adjustment.status = 'APPROVED'
                 GROUP BY detail.sale_allocation_id
             ) AS returns ON returns.sale_allocation_id = allocation.allocation_id
             WHERE item.sale_id = ?
             ORDER BY item.sale_item_id, allocation.allocation_id",
            [(int) $saleId],
        )->getResultArray();
        $pendingCount = $db->table('penyesuaian_penjualan')
            ->where('sale_id', (int) $saleId)
            ->where('status', 'PENDING')
            ->countAllResults();
        $canRequest = in_array($sale['status'], ['POSTED', 'PARTIALLY_RETURNED'], true)
            && $pendingCount === 0
            && $allocations !== [];
        $locations = $db->table('lokasi_persediaan')
            ->whereIn('location_code', ['TOKO', 'GUDANG'])
            ->orderBy('location_name')
            ->get()
            ->getResultArray();

        helper('form');
        return view('sale_adjustments/request', [
            'sale' => $sale,
            'allocations' => $allocations,
            'locations' => $locations,
            'canRequest' => $canRequest,
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function request(string $saleId): RedirectResponse
    {
        if (! ctype_digit($saleId) || (int) $saleId < 1) {
            return redirect()->to('/kasir')->with('error', 'Transaksi tidak ditemukan.');
        }

        $type = $this->postValue('adjustment_type');
        $reason = trim($this->postValue('reason'));
        if (! in_array($type, ['VOID', 'RETUR'], true)
            || $reason === '' || mb_strlen($reason) > 1000) {
            return redirect()->to('/kasir/struk/' . $saleId . '/penyesuaian')
                ->withInput()
                ->with('error', 'Pilih jenis penyesuaian dan isi alasan maksimal 1.000 karakter.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $sale = $db->query(
                'SELECT sale_id, invoice_number, payment_method, status
                 FROM penjualan WHERE sale_id = ? FOR UPDATE',
                [(int) $saleId],
            )->getRowArray();
            if ($sale === null || ! in_array($sale['status'], ['POSTED', 'PARTIALLY_RETURNED'], true)) {
                throw new DomainException('Transaksi ini tidak dapat diajukan untuk Void/Retur.');
            }
            if ($db->table('penyesuaian_penjualan')
                ->where('sale_id', (int) $saleId)
                ->where('status', 'PENDING')
                ->countAllResults() > 0) {
                throw new DomainException('Masih ada pengajuan Void/Retur yang menunggu keputusan untuk transaksi ini.');
            }

            $saleAllocations = $db->query(
                "SELECT allocation.allocation_id, allocation.sale_item_id, allocation.location_id,
                        allocation.quantity, COALESCE(returns.returned_quantity, 0.000000) AS returned_quantity
                 FROM alokasi_stok_penjualan AS allocation
                 JOIN detail_penjualan AS item ON item.sale_item_id = allocation.sale_item_id
                 LEFT JOIN (
                     SELECT detail.sale_allocation_id, SUM(detail.quantity) AS returned_quantity
                     FROM detail_penyesuaian_penjualan AS detail
                     JOIN penyesuaian_penjualan AS adjustment
                       ON adjustment.sale_adjustment_id = detail.sale_adjustment_id
                     WHERE adjustment.status = 'APPROVED'
                     GROUP BY detail.sale_allocation_id
                 ) AS returns ON returns.sale_allocation_id = allocation.allocation_id
                 WHERE item.sale_id = ?
                 ORDER BY allocation.allocation_id
                 FOR UPDATE",
                [(int) $saleId],
            )->getResultArray();

            if ($saleAllocations === []) {
                throw new DomainException('Transaksi ini tidak memiliki alokasi stok yang dapat disesuaikan.');
            }

            $quantities = $this->postArray('quantities');
            $restockLocations = $this->postArray('restock_location_ids');
            $returnLines = [];
            $hasPriorReturns = false;
            foreach ($saleAllocations as $allocation) {
                $allocatedMicros = $this->quantityToMicros((string) $allocation['quantity']);
                $returnedMicros = $this->quantityToMicros((string) $allocation['returned_quantity']);
                if ($allocatedMicros === null || $returnedMicros === null || $returnedMicros > $allocatedMicros) {
                    throw new RuntimeException('Data alokasi stok retur tidak valid.');
                }
                $hasPriorReturns = $hasPriorReturns || $returnedMicros > 0;
                $remainingMicros = $allocatedMicros - $returnedMicros;
                if ($remainingMicros === 0) {
                    continue;
                }

                if ($type === 'VOID') {
                    $quantityMicros = $remainingMicros;
                    $restockLocationId = (int) $allocation['location_id'];
                } else {
                    $rawQuantity = $quantities[(string) $allocation['allocation_id']] ?? '';
                    if ($rawQuantity === '') {
                        continue;
                    }
                    $quantityMicros = $this->quantityToMicros($rawQuantity);
                    if ($quantityMicros === null || $quantityMicros < 1 || $quantityMicros > $remainingMicros) {
                        throw new DomainException('Jumlah retur harus lebih dari nol dan tidak boleh melebihi sisa barang pada alokasi.');
                    }
                    $rawLocation = $restockLocations[(string) $allocation['allocation_id']] ?? '';
                    if (! ctype_digit($rawLocation) || (int) $rawLocation < 1) {
                        throw new DomainException('Pilih lokasi stok untuk setiap barang yang diretur.');
                    }
                    $restockLocationId = (int) $rawLocation;
                }

                $returnLines[] = [
                    'allocation_id' => (int) $allocation['allocation_id'],
                    'sale_item_id' => (int) $allocation['sale_item_id'],
                    'quantity' => $this->quantityFromMicros($quantityMicros),
                    'quantity_micros' => $quantityMicros,
                    'restock_location_id' => $restockLocationId,
                    'prior_returned_micros' => $returnedMicros,
                ];
            }

            if ($type === 'VOID' && ($sale['status'] !== 'POSTED' || $hasPriorReturns)) {
                throw new DomainException('Void hanya dapat diajukan untuk transaksi yang belum pernah diretur.');
            }
            if ($returnLines === []) {
                throw new DomainException('Masukkan jumlah barang yang akan diretur.');
            }

            $validLocationIds = array_map(
                'intval',
                array_column(
                    $db->table('lokasi_persediaan')
                        ->select('location_id')
                        ->whereIn('location_code', ['TOKO', 'GUDANG'])
                        ->get()
                        ->getResultArray(),
                    'location_id',
                ),
            );
            foreach ($returnLines as $line) {
                if (! in_array($line['restock_location_id'], $validLocationIds, true)) {
                    throw new DomainException('Lokasi stok yang dipilih tidak valid.');
                }
            }

            $linesByItem = [];
            foreach ($returnLines as $index => $line) {
                $linesByItem[$line['sale_item_id']][] = $index;
            }
            foreach ($linesByItem as $saleItemId => $lineIndexes) {
                $prior = $db->query(
                    "SELECT item.line_total, item.stock_quantity_snapshot,
                            COALESCE(SUM(previous.quantity), 0.000000) AS returned_quantity,
                            COALESCE(SUM(previous.refund_amount), 0.00) AS refunded_amount
                     FROM detail_penjualan AS item
                    LEFT JOIN (
                        SELECT allocation.sale_item_id, detail.quantity, detail.refund_amount
                        FROM detail_penyesuaian_penjualan AS detail
                        JOIN alokasi_stok_penjualan AS allocation
                          ON allocation.allocation_id = detail.sale_allocation_id
                        JOIN penyesuaian_penjualan AS adjustment
                          ON adjustment.sale_adjustment_id = detail.sale_adjustment_id
                        WHERE adjustment.status = 'APPROVED'
                    ) AS previous ON previous.sale_item_id = item.sale_item_id
                    WHERE item.sale_item_id = ?
                    GROUP BY item.sale_item_id, item.line_total, item.stock_quantity_snapshot",
                    [(int) $saleItemId],
                )->getRowArray();
                if ($prior === null) {
                    throw new RuntimeException('Detail penjualan untuk barang retur tidak ditemukan.');
                }

                $priorQuantityMicros = $this->quantityToMicros((string) $prior['returned_quantity']);
                $stockQuantityMicros = $this->quantityToMicros((string) $prior['stock_quantity_snapshot']);
                $priorRefundCents = $this->moneyToCents((string) $prior['refunded_amount']);
                if ($priorQuantityMicros === null || $stockQuantityMicros === null
                    || $priorRefundCents === null || $stockQuantityMicros < 1) {
                    throw new RuntimeException('Nilai historis retur tidak valid.');
                }

                $cumulativeQuantity = $priorQuantityMicros;
                $cumulativeRefund = $priorRefundCents;
                foreach ($lineIndexes as $lineIndex) {
                    $cumulativeQuantity += $returnLines[$lineIndex]['quantity_micros'];
                    $target = $db->query(
                        'SELECT ROUND(CAST(? AS DECIMAL(14,2)) * CAST(? AS DECIMAL(18,6))
                                      / CAST(? AS DECIMAL(18,6)), 2) AS refund_total',
                        [
                            (string) $prior['line_total'],
                            $this->quantityFromMicros($cumulativeQuantity),
                            (string) $prior['stock_quantity_snapshot'],
                        ],
                    )->getRowArray();
                    $targetCents = $target === null
                        ? null
                        : $this->moneyToCents((string) $target['refund_total']);
                    if ($targetCents === null || $targetCents < $cumulativeRefund) {
                        throw new RuntimeException('Gagal menghitung nilai retur barang.');
                    }
                    $returnLines[$lineIndex]['refund_amount'] = $this->moneyFromCents($targetCents - $cumulativeRefund);
                    $cumulativeRefund = $targetCents;
                }
            }

            if (! $db->table('penyesuaian_penjualan')->insert([
                'sale_id' => (int) $saleId,
                'requested_by' => (int) session()->get('user_id'),
                'adjustment_type' => $type,
                'status' => 'PENDING',
                'reason' => $reason,
            ])) {
                throw new RuntimeException('Gagal menyimpan pengajuan Void/Retur.');
            }
            $adjustmentId = (int) $db->insertID();
            foreach ($returnLines as $line) {
                if (! $db->table('detail_penyesuaian_penjualan')->insert([
                    'sale_adjustment_id' => $adjustmentId,
                    'sale_allocation_id' => $line['allocation_id'],
                    'restock_location_id' => $line['restock_location_id'],
                    'quantity' => $line['quantity'],
                    'refund_amount' => $line['refund_amount'],
                ])) {
                    throw new RuntimeException('Gagal menyimpan rincian barang retur.');
                }
            }

            $this->recordActivity($db, 'REQUEST_SALE_ADJUSTMENT', 'PENYESUAIAN_PENJUALAN', $adjustmentId, null, [
                'sale_id' => (int) $saleId,
                'invoice_number' => $sale['invoice_number'],
                'adjustment_type' => $type,
                'reason' => $reason,
                'item_count' => count($returnLines),
            ]);
            NotificationService::notifyRoles(
                $db,
                ['OWNER', 'KASIR'],
                'SALE_ADJUSTMENT_PENDING',
                'Pengajuan Void/Retur menunggu keputusan',
                'Pengajuan ' . $type . ' untuk transaksi ' . $sale['invoice_number'] . ' menunggu keputusan.',
                'persetujuan',
            );
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pengajuan Void/Retur gagal disimpan.');
            }
            $db->transCommit();
        } catch (DomainException $exception) {
            $db->transRollback();
            return redirect()->to('/kasir/struk/' . $saleId . '/penyesuaian')
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/persetujuan')->with('success', 'Pengajuan Void/Retur berhasil dikirim untuk persetujuan.');
    }

    public function decide(string $adjustmentId): RedirectResponse
    {
        if (! ctype_digit($adjustmentId) || (int) $adjustmentId < 1) {
            return redirect()->to('/persetujuan')->with('error', 'Pengajuan tidak ditemukan.');
        }
        $decision = $this->postValue('decision');
        $decisionReason = trim($this->postValue('decision_reason'));
        if (! in_array($decision, ['APPROVED', 'REJECTED'], true)
            || ($decision === 'REJECTED' && ($decisionReason === '' || mb_strlen($decisionReason) > 1000))) {
            return redirect()->to('/persetujuan')->with('error', 'Pilih keputusan yang valid dan isi alasan penolakan maksimal 1.000 karakter.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $adjustment = $db->query(
                'SELECT * FROM penyesuaian_penjualan WHERE sale_adjustment_id = ? FOR UPDATE',
                [(int) $adjustmentId],
            )->getRowArray();
            if ($adjustment === null || $adjustment['status'] !== 'PENDING') {
                throw new DomainException('Pengajuan ini tidak lagi menunggu keputusan.');
            }
            $sale = $db->query(
                'SELECT sale_id, invoice_number, payment_method, status
                 FROM penjualan WHERE sale_id = ? FOR UPDATE',
                [(int) $adjustment['sale_id']],
            )->getRowArray();
            if ($sale === null) {
                throw new RuntimeException('Transaksi penjualan pengajuan tidak ditemukan.');
            }

            if ($decision === 'REJECTED') {
                if (! $db->table('penyesuaian_penjualan')
                    ->where('sale_adjustment_id', (int) $adjustmentId)
                    ->update([
                        'approved_by' => (int) session()->get('user_id'),
                        'status' => 'REJECTED',
                        'reason' => $adjustment['reason'] . "\n\nAlasan penolakan: " . $decisionReason,
                        'decided_at' => date('Y-m-d H:i:s'),
                    ])) {
                    throw new RuntimeException('Gagal menyimpan keputusan penolakan.');
                }
                $this->recordActivity($db, 'REJECT_SALE_ADJUSTMENT', 'PENYESUAIAN_PENJUALAN', (int) $adjustmentId, [
                    'status' => 'PENDING',
                ], [
                    'status' => 'REJECTED',
                    'decision_reason' => $decisionReason,
                ]);
            } else {
                if (! in_array($sale['status'], ['POSTED', 'PARTIALLY_RETURNED'], true)) {
                    throw new DomainException('Status transaksi sudah berubah sehingga pengajuan tidak dapat disetujui.');
                }

                $details = $db->query(
                    "SELECT detail.sale_adjustment_item_id, detail.sale_allocation_id,
                            detail.restock_location_id, detail.quantity, detail.refund_amount,
                            allocation.sale_item_id, allocation.location_id AS original_location_id,
                            item.product_id, movement.unit_cost_snapshot
                     FROM detail_penyesuaian_penjualan AS detail
                     JOIN alokasi_stok_penjualan AS allocation
                       ON allocation.allocation_id = detail.sale_allocation_id
                     JOIN detail_penjualan AS item ON item.sale_item_id = allocation.sale_item_id
                     LEFT JOIN pergerakan_persediaan AS movement
                       ON movement.sale_allocation_id = allocation.allocation_id
                     WHERE detail.sale_adjustment_id = ?
                     ORDER BY detail.sale_adjustment_item_id
                     FOR UPDATE",
                    [(int) $adjustmentId],
                )->getResultArray();
                if ($details === []) {
                    throw new RuntimeException('Rincian pengajuan Void/Retur tidak ditemukan.');
                }
                foreach ($details as $detail) {
                    if ($detail['unit_cost_snapshot'] === null) {
                        throw new RuntimeException('Biaya historis stok tidak ditemukan; approval dibatalkan agar ledger tetap konsisten.');
                    }
                    if ($db->query(
                        'UPDATE saldo_persediaan SET quantity = quantity + ? WHERE product_id = ? AND location_id = ?',
                        [
                            (string) $detail['quantity'],
                            (int) $detail['product_id'],
                            (int) $detail['restock_location_id'],
                        ],
                    ) === false || $db->affectedRows() !== 1) {
                        throw new RuntimeException('Gagal mengembalikan stok ke lokasi yang dipilih.');
                    }
                    if (! $db->table('pergerakan_persediaan')->insert([
                        'product_id' => (int) $detail['product_id'],
                        'location_id' => (int) $detail['restock_location_id'],
                        'sale_adjustment_item_id' => (int) $detail['sale_adjustment_item_id'],
                        'recorded_by' => (int) session()->get('user_id'),
                        'quantity_delta' => (string) $detail['quantity'],
                        'unit_cost_snapshot' => (string) $detail['unit_cost_snapshot'],
                    ])) {
                        throw new RuntimeException('Gagal mencatat pemulihan stok pada ledger.');
                    }
                }

                $refundCents = 0;
                foreach ($details as $detail) {
                    $lineCents = $this->moneyToCents((string) $detail['refund_amount']);
                    if ($lineCents === null) {
                        throw new RuntimeException('Nilai refund pada rincian pengajuan tidak valid.');
                    }
                    $refundCents += $lineCents;
                }

                $receivableAdjustedCents = 0;
                if ($sale['payment_method'] === 'BON') {
                    $receivable = $db->query(
                        'SELECT receivable_id, original_amount, status FROM piutang WHERE sale_id = ? FOR UPDATE',
                        [(int) $sale['sale_id']],
                    )->getRowArray();
                    if ($receivable === null) {
                        throw new RuntimeException('Data piutang transaksi Bon tidak ditemukan.');
                    }
                    $balance = $db->query(
                        "SELECT COALESCE(payments.paid_amount, 0.00) AS paid_amount,
                                COALESCE(adjustments.adjusted_amount, 0.00) AS adjusted_amount,
                                GREATEST(0.00, receivable.original_amount
                                    - COALESCE(payments.paid_amount, 0.00)
                                    - COALESCE(adjustments.adjusted_amount, 0.00)) AS outstanding_amount
                         FROM piutang AS receivable
                         LEFT JOIN (
                             SELECT receivable_id, SUM(amount) AS paid_amount
                             FROM pembayaran_piutang WHERE receivable_id = ? GROUP BY receivable_id
                         ) AS payments ON payments.receivable_id = receivable.receivable_id
                         LEFT JOIN (
                             SELECT receivable_id, SUM(amount) AS adjusted_amount
                             FROM penyesuaian_piutang WHERE receivable_id = ? GROUP BY receivable_id
                         ) AS adjustments ON adjustments.receivable_id = receivable.receivable_id
                         WHERE receivable.receivable_id = ?",
                        [(int) $receivable['receivable_id'], (int) $receivable['receivable_id'], (int) $receivable['receivable_id']],
                    )->getRowArray();
                    if ($balance === null) {
                        throw new RuntimeException('Gagal menghitung sisa piutang transaksi Bon.');
                    }
                    $outstandingCents = $this->moneyToCents((string) $balance['outstanding_amount']);
                    $paidCents = $this->moneyToCents((string) $balance['paid_amount']);
                    if ($outstandingCents === null || $paidCents === null) {
                        throw new RuntimeException('Saldo piutang transaksi Bon tidak valid.');
                    }
                    $receivableAdjustedCents = min($refundCents, $outstandingCents);
                    if ($receivableAdjustedCents > 0) {
                        if (! $db->table('penyesuaian_piutang')->insert([
                            'receivable_id' => (int) $receivable['receivable_id'],
                            'sale_adjustment_id' => (int) $adjustmentId,
                            'approved_by' => (int) session()->get('user_id'),
                            'adjustment_type' => 'RETUR',
                            'amount' => $this->moneyFromCents($receivableAdjustedCents),
                            'reason' => 'Retur penjualan ' . $sale['invoice_number'],
                        ])) {
                            throw new RuntimeException('Gagal mengoreksi piutang Bon.');
                        }
                    }
                    $remainingCents = $outstandingCents - $receivableAdjustedCents;
                    if ($remainingCents === 0) {
                        $receivableStatus = $receivableAdjustedCents > 0
                            ? 'ADJUSTED'
                            : $receivable['status'];
                    } else {
                        $receivableStatus = $paidCents > 0 ? 'PARTIALLY_PAID' : 'OPEN';
                    }
                    if (! $db->table('piutang')->where('receivable_id', (int) $receivable['receivable_id'])->update([
                        'status' => $receivableStatus,
                    ])) {
                        throw new RuntimeException('Gagal memperbarui status piutang setelah retur.');
                    }
                }

                if (! $db->table('penyesuaian_penjualan')
                    ->where('sale_adjustment_id', (int) $adjustmentId)
                    ->update([
                        'approved_by' => (int) session()->get('user_id'),
                        'status' => 'APPROVED',
                        'decided_at' => date('Y-m-d H:i:s'),
                    ])) {
                    throw new RuntimeException('Gagal menyimpan persetujuan Void/Retur.');
                }

                if ($adjustment['adjustment_type'] === 'VOID') {
                    $saleStatus = 'VOIDED';
                } else {
                    $returned = $db->query(
                        "SELECT COALESCE(SUM(allocation.quantity), 0.000000) AS allocated_quantity,
                                COALESCE(SUM(approved.returned_quantity), 0.000000) AS returned_quantity
                         FROM alokasi_stok_penjualan AS allocation
                         JOIN detail_penjualan AS item ON item.sale_item_id = allocation.sale_item_id
                         LEFT JOIN (
                             SELECT detail.sale_allocation_id, SUM(detail.quantity) AS returned_quantity
                             FROM detail_penyesuaian_penjualan AS detail
                             JOIN penyesuaian_penjualan AS approved
                               ON approved.sale_adjustment_id = detail.sale_adjustment_id
                              AND approved.status = 'APPROVED'
                             GROUP BY detail.sale_allocation_id
                         ) AS approved ON approved.sale_allocation_id = allocation.allocation_id
                         WHERE item.sale_id = ?",
                        [(int) $sale['sale_id']],
                    )->getRowArray();
                    $allocatedMicros = $this->quantityToMicros((string) $returned['allocated_quantity']);
                    $returnedMicros = $this->quantityToMicros((string) $returned['returned_quantity']);
                    if ($allocatedMicros === null || $returnedMicros === null) {
                        throw new RuntimeException('Gagal menghitung jumlah barang yang telah diretur.');
                    }
                    $saleStatus = $returnedMicros >= $allocatedMicros ? 'RETURNED' : 'PARTIALLY_RETURNED';
                }
                if (! $db->table('penjualan')->where('sale_id', (int) $sale['sale_id'])->update([
                    'status' => $saleStatus,
                ])) {
                    throw new RuntimeException('Gagal memperbarui status transaksi penjualan.');
                }

                $this->recordActivity($db, 'APPROVE_SALE_ADJUSTMENT', 'PENYESUAIAN_PENJUALAN', (int) $adjustmentId, [
                    'status' => 'PENDING',
                ], [
                    'status' => 'APPROVED',
                    'sale_status' => $saleStatus,
                    'refund_amount' => $this->moneyFromCents($refundCents),
                    'receivable_adjusted' => $this->moneyFromCents($receivableAdjustedCents),
                    'pending_refund' => $this->moneyFromCents(max(0, $refundCents - $receivableAdjustedCents)),
                ]);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi keputusan Void/Retur gagal.');
            }
            $db->transCommit();
        } catch (DomainException $exception) {
            $db->transRollback();
            return redirect()->to('/persetujuan')->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/persetujuan')->with(
            'success',
            $decision === 'APPROVED' ? 'Pengajuan Void/Retur berhasil disetujui.' : 'Pengajuan berhasil ditolak.',
        );
    }

    public function recordRefund(string $adjustmentId): RedirectResponse
    {
        if (! ctype_digit($adjustmentId) || (int) $adjustmentId < 1) {
            return redirect()->to('/persetujuan')->with('error', 'Pengajuan tidak ditemukan.');
        }
        $accountId = $this->postValue('cash_account_id');
        $paymentMethod = $this->postValue('payment_method');
        if (! ctype_digit($accountId) || (int) $accountId < 1
            || ! in_array($paymentMethod, ['TUNAI', 'TRANSFER'], true)) {
            return redirect()->to('/persetujuan')->with('error', 'Pilih metode dan akun pembayaran refund yang valid.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $adjustment = $db->query(
                "SELECT adjustment.sale_adjustment_id, adjustment.sale_id, adjustment.status,
                        sale.invoice_number, sale.payment_method AS sale_payment_method
                 FROM penyesuaian_penjualan AS adjustment
                 JOIN penjualan AS sale ON sale.sale_id = adjustment.sale_id
                 WHERE adjustment.sale_adjustment_id = ? FOR UPDATE",
                [(int) $adjustmentId],
            )->getRowArray();
            if ($adjustment === null || $adjustment['status'] !== 'APPROVED') {
                throw new DomainException('Refund hanya dapat dicatat untuk retur yang sudah disetujui.');
            }
            if ($db->table('mutasi_kas')->where('sale_adjustment_id', (int) $adjustmentId)->countAllResults() > 0) {
                throw new DomainException('Refund untuk pengajuan ini sudah pernah dicatat.');
            }

            $account = $db->query(
                'SELECT cash_account_id, account_type FROM akun_kas
                 WHERE cash_account_id = ? AND is_active = 1 FOR UPDATE',
                [(int) $accountId],
            )->getRowArray();
            $expectedAccountType = $paymentMethod === 'TUNAI' ? 'TUNAI' : 'BANK';
            if ($account === null || $account['account_type'] !== $expectedAccountType) {
                throw new DomainException('Akun kas/bank tidak aktif atau tidak sesuai dengan metode refund.');
            }

            $totals = $db->query(
                "SELECT COALESCE(items.refund_amount, 0.00) AS refund_amount,
                        COALESCE(receivable_adjustments.adjusted_amount, 0.00) AS adjusted_amount
                 FROM penyesuaian_penjualan AS adjustment
                 LEFT JOIN (
                     SELECT sale_adjustment_id, SUM(refund_amount) AS refund_amount
                     FROM detail_penyesuaian_penjualan GROUP BY sale_adjustment_id
                 ) AS items ON items.sale_adjustment_id = adjustment.sale_adjustment_id
                 LEFT JOIN (
                     SELECT sale_adjustment_id, SUM(amount) AS adjusted_amount
                     FROM penyesuaian_piutang WHERE sale_adjustment_id = ? GROUP BY sale_adjustment_id
                 ) AS receivable_adjustments ON receivable_adjustments.sale_adjustment_id = adjustment.sale_adjustment_id
                 WHERE adjustment.sale_adjustment_id = ?",
                [(int) $adjustmentId, (int) $adjustmentId],
            )->getRowArray();
            if ($totals === null) {
                throw new RuntimeException('Gagal menghitung jumlah refund yang tertunda.');
            }
            $refundCents = $this->moneyToCents((string) $totals['refund_amount']);
            $adjustedCents = $this->moneyToCents((string) $totals['adjusted_amount']);
            if ($refundCents === null || $adjustedCents === null) {
                throw new RuntimeException('Jumlah refund pada ledger tidak valid.');
            }
            $pendingCents = max(0, $refundCents - ($adjustment['sale_payment_method'] === 'BON' ? $adjustedCents : 0));
            if ($pendingCents < 1) {
                throw new DomainException('Tidak ada refund tunai/transfer yang tertunda untuk pengajuan ini.');
            }

            if (! $db->table('mutasi_kas')->insert([
                'cash_account_id' => (int) $accountId,
                'sale_adjustment_id' => (int) $adjustmentId,
                'recorded_by' => (int) session()->get('user_id'),
                'direction' => 'KELUAR',
                'payment_method' => $paymentMethod,
                'amount' => $this->moneyFromCents($pendingCents),
                'notes' => 'Refund retur penjualan ' . $adjustment['invoice_number'],
            ])) {
                throw new RuntimeException('Gagal mencatat refund pada buku kas.');
            }
            $this->recordActivity($db, 'RECORD_SALE_REFUND', 'PENYESUAIAN_PENJUALAN', (int) $adjustmentId, null, [
                'sale_id' => (int) $adjustment['sale_id'],
                'invoice_number' => $adjustment['invoice_number'],
                'amount' => $this->moneyFromCents($pendingCents),
                'payment_method' => $paymentMethod,
                'cash_account_id' => (int) $accountId,
            ]);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pencatatan refund gagal.');
            }
            $db->transCommit();
        } catch (DomainException $exception) {
            $db->transRollback();
            return redirect()->to('/persetujuan')->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/persetujuan')->with('success', 'Refund berhasil dicatat sebagai arus kas keluar.');
    }

    private function postValue(string $key): string
    {
        $value = $this->request->getPost($key);
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array<string, string>
     */
    private function postArray(string $key): array
    {
        $value = $this->request->getPost($key);
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $index => $item) {
            if ((is_string($index) || is_int($index)) && is_string($item)) {
                $result[(string) $index] = trim($item);
            }
        }
        return $result;
    }

    private function quantityToMicros(string $value): ?int
    {
        if (! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $scaled = (int) $whole * 1_000_000 + (int) str_pad($fraction, 6, '0');
        return $scaled >= 0 ? $scaled : null;
    }

    private function quantityFromMicros(int $value): string
    {
        return intdiv($value, 1_000_000) . '.' . str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function moneyToCents(string $value): ?int
    {
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function moneyFromCents(int $value): string
    {
        return intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function recordActivity(
        object $db,
        string $action,
        string $entityType,
        int $entityId,
        ?array $before,
        ?array $after,
    ): void {
        if (! $db->table('log_aktivitas')->insert([
            'user_id' => (int) session()->get('user_id'),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_data' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        ])) {
            throw new RuntimeException('Gagal mencatat aktivitas Void/Retur.');
        }
    }
}
