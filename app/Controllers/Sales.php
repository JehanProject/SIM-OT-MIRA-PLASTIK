<?php

namespace App\Controllers;

use App\Libraries\NotificationService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use RuntimeException;
use Throwable;

class Sales extends BaseController
{
    public function index(): string
    {
        helper(['form', 'inventory']);
        $db = db_connect();

        $salesBuilder = $db->table('penjualan')
            ->select('penjualan.sale_id, penjualan.invoice_number, penjualan.sale_type, penjualan.payment_method, penjualan.total_amount, penjualan.sold_at, pengguna.full_name')
            ->join('pengguna', 'pengguna.user_id = penjualan.cashier_id')
            ->where('penjualan.status', 'POSTED')
            ->orderBy('penjualan.sale_id', 'DESC')
            ->limit(20);
        if (session()->get('role_code') === 'KASIR') {
            $salesBuilder->where('penjualan.cashier_id', (int) session()->get('user_id'));
        }

        $oldProductIds = old('product_id');
        $oldQuantities = old('quantity');
        $customerCart = session()->getFlashdata('checkout_cart');
        $cartProductIds = is_array($oldProductIds) ? $oldProductIds : old('cart_product_id');
        $cartQuantities = is_array($oldQuantities) ? $oldQuantities : old('cart_quantity');
        if (! is_array($cartProductIds) && is_array($customerCart)) {
            $cartProductIds = $customerCart['product_ids'] ?? [];
        }
        if (! is_array($cartQuantities) && is_array($customerCart)) {
            $cartQuantities = $customerCart['quantities'] ?? [];
        }
        $creditPolicy = $db->table('kebijakan_kredit')
            ->where('effective_from <=', date('Y-m-d'))
            ->groupStart()
                ->where('effective_to', null)
                ->orWhere('effective_to >=', date('Y-m-d'))
            ->groupEnd()
            ->orderBy('effective_from', 'DESC')
            ->orderBy('credit_policy_id', 'DESC')
            ->get()
            ->getRowArray();
        $requestedCustomerId = $this->request->getGet('customer_id');
        $selectedCustomerId = is_string($requestedCustomerId) && ctype_digit($requestedCustomerId)
            ? $requestedCustomerId
            : '';
        $requestedSaleType = $this->request->getGet('sale_type');
        $selectedSaleType = is_string($requestedSaleType) && in_array($requestedSaleType, ['ECERAN', 'GROSIR'], true)
            ? $requestedSaleType
            : '';
        $requestedPaymentMethod = $this->request->getGet('payment_method');
        $selectedPaymentMethod = is_string($requestedPaymentMethod)
            && in_array($requestedPaymentMethod, ['TUNAI', 'TRANSFER', 'BON'], true)
            ? $requestedPaymentMethod
            : 'TUNAI';

        return view('sales/index', [
            'products' => $db->query(
                "SELECT barang.product_id, barang.product_code, barang.product_name, satuan.unit_symbol,
                    satuan_eceran.unit_symbol AS retail_unit_symbol, barang.retail_units_per_stock_unit,
                    barang.retail_price, barang.wholesale_price, barang.wholesale_min_qty, barang.min_stock,
                        COALESCE(SUM(CASE WHEN lokasi_persediaan.location_code = 'GUDANG' THEN saldo_persediaan.quantity ELSE 0 END), 0) AS warehouse_stock,
                        COALESCE(SUM(CASE WHEN lokasi_persediaan.location_code = 'TOKO' THEN saldo_persediaan.quantity ELSE 0 END), 0) AS store_stock
                 FROM barang
                 JOIN satuan ON satuan.unit_id = barang.unit_id
                 JOIN satuan AS satuan_eceran ON satuan_eceran.unit_id = barang.retail_unit_id
                 LEFT JOIN saldo_persediaan ON saldo_persediaan.product_id = barang.product_id
                 LEFT JOIN lokasi_persediaan ON lokasi_persediaan.location_id = saldo_persediaan.location_id
                 WHERE barang.is_active = 1
                 GROUP BY barang.product_id, barang.product_code, barang.product_name, satuan.unit_symbol,
                          satuan_eceran.unit_symbol, barang.retail_units_per_stock_unit,
                          barang.retail_price, barang.wholesale_price, barang.wholesale_min_qty, barang.min_stock
                 ORDER BY barang.product_name",
            )->getResultArray(),
            'accounts' => $db->table('akun_kas')
                ->where('is_active', true)
                ->orderBy('account_type')
                ->orderBy('account_name')
                ->get()
                ->getResultArray(),
            'customers' => $db->query(
                "SELECT pelanggan.customer_id, pelanggan.customer_code, pelanggan.customer_name,
                        pelanggan.business_name, pelanggan.credit_limit,
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
                 WHERE pelanggan.is_active = 1
                 ORDER BY pelanggan.customer_name",
            )->getResultArray(),
            'creditPolicy' => $creditPolicy,
            'maximumDueDate' => $creditPolicy === null
                ? null
                : date('Y-m-d', strtotime('+' . (int) $creditPolicy['max_tenor_days'] . ' days')),
            'oldCustomerId' => (string) old('customer_id', $selectedCustomerId),
            'oldSaleType' => (string) old('sale_type', $selectedSaleType !== '' ? $selectedSaleType : 'ECERAN'),
            'oldDueDate' => (string) old('due_date', (string) $this->request->getGet('due_date')),
            'oldPaymentMethod' => (string) old('payment_method', $selectedPaymentMethod),
            'roleCode' => (string) session()->get('role_code'),
            'recentSales' => $salesBuilder->get()->getResultArray(),
            'oldCart' => [
                'product_ids' => is_array($cartProductIds) ? array_values($cartProductIds) : [],
                'quantities' => is_array($cartQuantities) ? array_values($cartQuantities) : [],
            ],
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
            'customerFormErrors' => session()->getFlashdata('customer_form_errors') ?? [],
            'customerFormOpen' => $this->request->getGet('customer_form') === '1',
            'policyFormOpen' => $this->request->getGet('policy_form') === '1',
        ]);
    }

    public function checkout(): RedirectResponse
    {
        $productIds = $this->request->getPost('product_id');
        $quantities = $this->request->getPost('quantity');
        $saleType = $this->postString('sale_type');
        $paymentMethod = $this->postString('payment_method');
        $accountId = $this->postString('cash_account_id');
        $amountReceived = $this->postString('amount_received');
        $customerId = $this->postString('customer_id');
        $dueDate = $this->postString('due_date');

        if (! is_array($productIds) || ! is_array($quantities)
            || count($productIds) < 1 || count($productIds) > 100
            || count($productIds) !== count($quantities)
            || ! in_array($saleType, ['ECERAN', 'GROSIR'], true)
            || ! in_array($paymentMethod, ['TUNAI', 'TRANSFER', 'BON'], true)
            || ($paymentMethod !== 'BON' && (! ctype_digit($accountId) || (int) $accountId < 1))
            || ($paymentMethod === 'BON' && $saleType !== 'GROSIR')) {
            return redirect()->back()->withInput()->with('error', 'Periksa kembali barang, jenis penjualan, dan metode pembayaran.');
        }

        $normalized = [];
        foreach ($productIds as $index => $productId) {
            $quantity = $quantities[$index] ?? null;
            if (! is_scalar($productId) || ! ctype_digit((string) $productId) || (int) $productId < 1
                || ! is_scalar($quantity) || ! $this->isDecimal((string) $quantity, 3, true)) {
                return redirect()->back()->withInput()->with('error', 'Barang atau jumlah pada keranjang tidak valid.');
            }
            $id = (int) $productId;
            if (isset($normalized[$id])) {
                return redirect()->back()->withInput()->with('error', 'Barang yang sama tidak boleh muncul lebih dari sekali dalam keranjang.');
            }
            $normalized[$id] = ['product_id' => $id, 'quantity' => (string) $quantity];
        }
        $normalized = array_values($normalized);

        $receivedCents = null;
        if ($paymentMethod === 'TUNAI') {
            if (! $this->isDecimal($amountReceived, 2, false)
                || ($receivedCents = $this->scaledInteger($amountReceived, 2)) === null) {
                return redirect()->back()->withInput()->with('error', 'Jumlah uang diterima harus berupa angka dengan maksimal dua angka desimal.');
            }
        } elseif ($paymentMethod === 'BON') {
            $receivedCents = 0;
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $ids = array_column($normalized, 'product_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $products = $db->query(
                "SELECT barang.product_id, barang.product_code, barang.product_name, barang.avg_purchase_cost,
                        barang.retail_price, barang.wholesale_price, barang.wholesale_min_qty, barang.min_stock,
                        barang.retail_units_per_stock_unit, stock_unit.unit_symbol AS stock_unit_symbol,
                        retail_unit.unit_symbol AS retail_unit_symbol
                 FROM barang
                 JOIN satuan AS stock_unit ON stock_unit.unit_id = barang.unit_id
                 JOIN satuan AS retail_unit ON retail_unit.unit_id = barang.retail_unit_id
                 WHERE barang.is_active = 1 AND barang.product_id IN ({$placeholders})
                 ORDER BY barang.product_id FOR UPDATE",
                $ids,
            )->getResultArray();
            if (count($products) !== count($normalized)) {
                throw new DomainException('Salah satu barang tidak ditemukan atau sudah tidak aktif.');
            }

            $productById = [];
            foreach ($products as $product) {
                $productById[(int) $product['product_id']] = $product;
            }

            $locations = $db->table('lokasi_persediaan')
                ->whereIn('location_code', ['GUDANG', 'TOKO'])
                ->get()
                ->getResultArray();
            $locationByCode = [];
            foreach ($locations as $location) {
                $locationByCode[$location['location_code']] = (int) $location['location_id'];
            }
            if (! isset($locationByCode['GUDANG'], $locationByCode['TOKO'])) {
                throw new RuntimeException('Lokasi GUDANG dan TOKO harus tersedia untuk transaksi kasir.');
            }

            $customer = null;
            $creditPolicy = null;
            $customerOutstandingCents = 0;
            if ($paymentMethod === 'BON') {
                if (! ctype_digit($customerId) || (int) $customerId < 1
                    || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                    throw new DomainException('Pilih pelanggan grosir dan tanggal jatuh tempo yang valid untuk transaksi bon.');
                }
                $customer = $db->query(
                    'SELECT customer_id, customer_name, credit_limit
                     FROM pelanggan WHERE customer_id = ? AND is_active = 1 FOR UPDATE',
                    [(int) $customerId],
                )->getRowArray();
                if ($customer === null) {
                    throw new DomainException('Pelanggan tidak aktif atau tidak ditemukan.');
                }

                $today = date('Y-m-d');
                $creditPolicy = $db->query(
                    'SELECT credit_policy_id, max_tenor_days
                     FROM kebijakan_kredit
                     WHERE effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
                     ORDER BY effective_from DESC, credit_policy_id DESC
                     LIMIT 1 FOR UPDATE',
                    [$today, $today],
                )->getRowArray();
                if ($creditPolicy === null) {
                    throw new DomainException('Kebijakan tenor bon belum disiapkan Owner. Bon belum dapat diproses.');
                }

                $maximumDueDate = date(
                    'Y-m-d',
                    strtotime('+' . (int) $creditPolicy['max_tenor_days'] . ' days', strtotime($today)),
                );
                $dueTimestamp = strtotime($dueDate);
                if ($dueTimestamp === false || date('Y-m-d', $dueTimestamp) !== $dueDate
                    || $dueDate < $today || $dueDate > $maximumDueDate) {
                    throw new DomainException(
                        'Tanggal jatuh tempo harus antara hari ini dan ' . $maximumDueDate
                        . ' sesuai tenor maksimum ' . $creditPolicy['max_tenor_days'] . ' hari.',
                    );
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
                    [(int) $customerId],
                )->getRowArray();
                $customerOutstandingCents = $this->scaledInteger((string) $outstanding['outstanding_amount'], 2);
                if ($customerOutstandingCents === null) {
                    throw new DomainException('Total piutang pelanggan melampaui batas perhitungan plafon. Periksa data piutang terlebih dahulu.');
                }
            }

            $balances = $db->query(
                "SELECT product_id, location_id, quantity FROM saldo_persediaan
                 WHERE product_id IN ({$placeholders}) ORDER BY product_id, location_id FOR UPDATE",
                $ids,
            )->getResultArray();
            $balanceByProduct = [];
            foreach ($balances as $balance) {
                $balanceByProduct[(int) $balance['product_id']][(int) $balance['location_id']] = (string) $balance['quantity'];
            }

            $priceTypeByProduct = [];
            $stockQuantityMicroByProduct = [];
            $hasWholesaleItem = false;
            foreach ($normalized as $line) {
                $product = $productById[$line['product_id']];
                $quantityMilli = $this->scaledInteger($line['quantity'], 3);
                if ($quantityMilli === null) {
                    throw new DomainException('Jumlah barang melewati batas yang dapat diproses.');
                }
                $minimumMilli = $this->scaledInteger((string) $product['wholesale_min_qty'], 3);
                if ($minimumMilli === null) {
                    throw new RuntimeException('Minimum grosir barang tidak valid pada data katalog.');
                }
                $isWholesaleItem = $saleType === 'GROSIR' && $quantityMilli >= $minimumMilli;
                $priceTypeByProduct[$line['product_id']] = $isWholesaleItem ? 'GROSIR' : 'ECERAN';
                $hasWholesaleItem = $hasWholesaleItem || $isWholesaleItem;

                $conversionFactor = $product['retail_units_per_stock_unit'] === null
                    ? null
                    : (string) $product['retail_units_per_stock_unit'];
                if ($conversionFactor === null && ($saleType === 'ECERAN' || ! $isWholesaleItem)) {
                    throw new DomainException(
                        'Faktor konversi satuan eceran untuk ' . $product['product_name']
                        . ' belum diatur. Lengkapi data barang sebelum memakai harga eceran.',
                    );
                }

                $stockQuantityMicro = $saleType === 'ECERAN'
                    ? $this->retailQuantityToStockMicro($line['quantity'], $conversionFactor ?? '')
                    : $quantityMilli * 1000;
                if ($stockQuantityMicro === null || $stockQuantityMicro < 1) {
                    throw new DomainException('Jumlah konversi barang terlalu kecil atau melewati batas sistem.');
                }
                $stockQuantityMicroByProduct[$line['product_id']] = $stockQuantityMicro;

                $warehouseMicro = $this->scaledInteger(
                    $balanceByProduct[$line['product_id']][$locationByCode['GUDANG']] ?? '0',
                    6,
                ) ?? 0;
                $storeMicro = $this->scaledInteger(
                    $balanceByProduct[$line['product_id']][$locationByCode['TOKO']] ?? '0',
                    6,
                ) ?? 0;
                $minimumStockMicro = $this->scaledInteger((string) $product['min_stock'], 6) ?? 0;
                $availableStockMicro = $saleType === 'GROSIR'
                    ? $warehouseMicro + max(0, $storeMicro - $minimumStockMicro)
                    : $storeMicro;
                if ($stockQuantityMicro > $availableStockMicro) {
                    throw new DomainException(
                        $saleType === 'GROSIR'
                            ? 'Stok ' . $product['product_name'] . ' tidak mencukupi tanpa mengurangi stok minimum toko.'
                            : 'Stok ' . $product['product_name'] . ' tidak mencukupi.',
                    );
                }
            }
            if ($saleType === 'GROSIR' && ! $hasWholesaleItem) {
                throw new DomainException('Transaksi grosir harus memiliki setidaknya satu barang dengan jumlah mencapai minimum grosir.');
            }

            if ($paymentMethod !== 'BON') {
                $account = $db->table('akun_kas')
                    ->where('cash_account_id', (int) $accountId)
                    ->where('account_type', $paymentMethod === 'TUNAI' ? 'TUNAI' : 'BANK')
                    ->where('is_active', true)
                    ->get()
                    ->getRowArray();
                if ($account === null) {
                    throw new DomainException('Akun pembayaran tidak aktif atau tidak sesuai dengan metode pembayaran.');
                }
            }

            $invoiceNumber = $this->newInvoiceNumber($db);
            if (! $db->table('penjualan')->insert([
                'cashier_id' => (int) session()->get('user_id'),
                'customer_id' => $paymentMethod === 'BON' ? (int) $customerId : null,
                'invoice_number' => $invoiceNumber,
                'sale_type' => $saleType,
                'payment_method' => $paymentMethod,
                'status' => 'POSTED',
                'subtotal' => 0,
                'total_amount' => 0,
                'amount_received' => 0,
                'change_amount' => 0,
            ])) {
                throw new RuntimeException('Gagal membuat transaksi penjualan.');
            }
            $saleId = (int) $db->insertID();

            $saleItems = [];
            foreach ($normalized as $line) {
                $product = $productById[$line['product_id']];
                $priceType = $priceTypeByProduct[$line['product_id']];
                $unitPrice = $priceType === 'GROSIR' ? $product['wholesale_price'] : $product['retail_price'];
                $unitSnapshot = $saleType === 'ECERAN'
                    ? $product['retail_unit_symbol']
                    : $product['stock_unit_symbol'];
                $priceUnitSnapshot = $priceType === 'GROSIR'
                    ? $product['stock_unit_symbol']
                    : $product['retail_unit_symbol'];
                $pricingConversionFactor = $saleType === 'GROSIR' && $priceType === 'ECERAN'
                    ? (string) $product['retail_units_per_stock_unit']
                    : '1.000';
                $stockQuantity = $this->decimalFromScaledInteger(
                    $stockQuantityMicroByProduct[$line['product_id']],
                    6,
                );
                if (! $db->query(
                    'INSERT INTO detail_penjualan
                        (sale_id, product_id, product_code_snapshot, product_name_snapshot, unit_snapshot,
                         sale_unit_snapshot, price_unit_snapshot, quantity, stock_quantity_snapshot,
                         retail_units_per_stock_unit_snapshot, price_type, unit_price, line_total)
                     SELECT ?, barang.product_id, barang.product_code, barang.product_name, ?, ?, ?, ?, ?, ?, ?, ?,
                            ROUND(? * ? * ?, 2)
                     FROM barang WHERE barang.product_id = ?',
                    [
                        $saleId,
                        $product['stock_unit_symbol'],
                        $unitSnapshot,
                        $priceUnitSnapshot,
                        $line['quantity'],
                        $stockQuantity,
                        $product['retail_units_per_stock_unit'],
                        $priceType,
                        $unitPrice,
                        $line['quantity'],
                        $pricingConversionFactor,
                        $unitPrice,
                        $line['product_id'],
                    ],
                )) {
                    throw new RuntimeException('Gagal menyimpan rincian transaksi.');
                }
                if ($db->affectedRows() !== 1) {
                    throw new RuntimeException('Rincian barang tidak berhasil disimpan.');
                }
                $saleItems[$line['product_id']] = [
                    'sale_item_id' => (int) $db->insertID(),
                    'stock_quantity_micro' => $stockQuantityMicroByProduct[$line['product_id']],
                    'product' => $product,
                ];
            }

            $totalAmount = $db->query(
                'SELECT COALESCE(SUM(line_total), 0.00) AS total_amount
                 FROM detail_penjualan WHERE sale_id = ?',
                [$saleId],
            )->getRowArray()['total_amount'];
            $totalCents = $this->isDecimal((string) $totalAmount, 2, false)
                ? $this->scaledInteger((string) $totalAmount, 2)
                : null;
            if ($totalCents === null || $totalCents < 1) {
                throw new DomainException('Total transaksi harus lebih dari Rp 0 dan berada dalam batas nilai sistem.');
            }
            if ($paymentMethod === 'TRANSFER') {
                $receivedCents = $totalCents;
            }
            if ($paymentMethod !== 'BON' && ($receivedCents === null || $receivedCents < $totalCents)) {
                throw new DomainException('Uang yang diterima kurang dari total transaksi.');
            }

            if ($paymentMethod === 'BON') {
                $creditLimitCents = $this->scaledInteger((string) $customer['credit_limit'], 2);
                if ($creditLimitCents === null || $customerOutstandingCents + $totalCents > $creditLimitCents) {
                    $availableCents = max(0, ($creditLimitCents ?? 0) - $customerOutstandingCents);
                    throw new DomainException(
                        'Plafon bon pelanggan ' . $customer['customer_name'] . ' tidak mencukupi. '
                        . 'Sisa plafon Rp ' . number_format($availableCents / 100, 2, ',', '.')
                        . ', total bon Rp ' . number_format($totalCents / 100, 2, ',', '.') . '.',
                    );
                }
            }

            if (! $db->table('penjualan')->where('sale_id', $saleId)->update([
                'subtotal' => $totalAmount,
                'total_amount' => $totalAmount,
                'amount_received' => $this->decimalFromScaledInteger($receivedCents ?? 0, 2),
                'change_amount' => $this->decimalFromScaledInteger(
                    $paymentMethod === 'BON' ? 0 : ($receivedCents ?? 0) - $totalCents,
                    2,
                ),
            ])) {
                throw new RuntimeException('Gagal memperbarui total transaksi.');
            }

            foreach ($normalized as $line) {
                $productId = $line['product_id'];
                $remainingMicro = $saleItems[$productId]['stock_quantity_micro'];
                $locationOrder = $saleType === 'GROSIR' ? ['GUDANG', 'TOKO'] : ['TOKO'];
                foreach ($locationOrder as $locationCode) {
                    if ($remainingMicro === 0) {
                        break;
                    }
                    $locationId = $locationByCode[$locationCode];
                    $availableMicro = $this->scaledInteger(
                        $balanceByProduct[$productId][$locationId] ?? '0',
                        6,
                    ) ?? 0;
                    if ($locationCode === 'TOKO' && $saleType === 'GROSIR') {
                        $minimumStockMicro = $this->scaledInteger(
                            (string) $productById[$productId]['min_stock'],
                            6,
                        ) ?? 0;
                        $availableMicro = max(0, $availableMicro - $minimumStockMicro);
                    }
                    $allocatedMicro = min($remainingMicro, $availableMicro);
                    if ($allocatedMicro === 0) {
                        continue;
                    }
                    $allocatedQuantity = $this->decimalFromScaledInteger($allocatedMicro, 6);
                    if ($db->query(
                        'UPDATE saldo_persediaan SET quantity = quantity - ?
                         WHERE product_id = ? AND location_id = ? AND quantity >= ?',
                        [$allocatedQuantity, $productId, $locationId, $allocatedQuantity],
                    ) === false || $db->affectedRows() !== 1) {
                        throw new RuntimeException('Gagal mengurangi saldo stok. Transaksi dibatalkan.');
                    }
                    if (! $db->table('alokasi_stok_penjualan')->insert([
                        'sale_item_id' => $saleItems[$productId]['sale_item_id'],
                        'location_id' => $locationId,
                        'quantity' => $allocatedQuantity,
                    ])) {
                        throw new RuntimeException('Gagal menyimpan alokasi stok penjualan.');
                    }
                    $allocationId = (int) $db->insertID();
                    if (! $db->table('pergerakan_persediaan')->insert([
                        'product_id' => $productId,
                        'location_id' => $locationId,
                        'sale_allocation_id' => $allocationId,
                        'recorded_by' => (int) session()->get('user_id'),
                        'quantity_delta' => '-' . $allocatedQuantity,
                        'unit_cost_snapshot' => $saleItems[$productId]['product']['avg_purchase_cost'],
                    ])) {
                        throw new RuntimeException('Gagal mencatat pengurangan stok pada ledger.');
                    }
                    $remainingMicro -= $allocatedMicro;
                }
                if ($remainingMicro !== 0) {
                    throw new RuntimeException('Alokasi stok tidak sesuai dengan jumlah transaksi.');
                }
            }

            if ($paymentMethod === 'BON') {
                if (! $db->table('piutang')->insert([
                    'customer_id' => (int) $customerId,
                    'sale_id' => $saleId,
                    'credit_policy_id' => (int) $creditPolicy['credit_policy_id'],
                    'original_amount' => $totalAmount,
                    'due_date' => $dueDate,
                    'status' => 'OPEN',
                ])) {
                    throw new RuntimeException('Gagal mencatat bon sebagai piutang.');
                }
            } elseif (! $db->table('mutasi_kas')->insert([
                'cash_account_id' => (int) $accountId,
                'sale_id' => $saleId,
                'recorded_by' => (int) session()->get('user_id'),
                'direction' => 'MASUK',
                'payment_method' => $paymentMethod,
                'amount' => $totalAmount,
                'notes' => 'Pembayaran penjualan ' . $invoiceNumber,
            ])) {
                throw new RuntimeException('Gagal mencatat pembayaran pada buku kas.');
            }

            if (! $db->table('log_aktivitas')->insert([
                'user_id' => (int) session()->get('user_id'),
                'action' => 'POST_SALE',
                'entity_type' => 'PENJUALAN',
                'entity_id' => (string) $saleId,
                'after_data' => json_encode([
                    'invoice_number' => $invoiceNumber,
                    'sale_type' => $saleType,
                    'payment_method' => $paymentMethod,
                    'customer_id' => $paymentMethod === 'BON' ? (int) $customerId : null,
                    'due_date' => $paymentMethod === 'BON' ? $dueDate : null,
                    'total_amount' => $totalAmount,
                    'item_count' => count($normalized),
                ], JSON_THROW_ON_ERROR),
            ])) {
                throw new RuntimeException('Gagal mencatat aktivitas transaksi.');
            }

            NotificationService::notifyRoles(
                $db,
                ['OWNER'],
                'SALE_POSTED',
                'Penjualan baru dicatat',
                'Transaksi ' . $invoiceNumber . ' berhasil diproses dengan total Rp ' . number_format((float) $totalAmount, 2, ',', '.') . '.',
                'transaksi',
            );
            NotificationService::notifyLowStock($db, array_map('intval', array_keys($saleItems)));
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penjualan gagal disimpan.');
            }
            $db->transCommit();
        } catch (DomainException $exception) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/kasir/struk/' . $saleId)
            ->with('success', 'Transaksi berhasil disimpan.');
    }

    public function receipt(string $id): string|RedirectResponse
    {
        if (! ctype_digit($id) || (int) $id < 1) {
            return redirect()->to('/kasir')->with('error', 'Transaksi tidak ditemukan.');
        }

        $db = db_connect();
        $saleQuery = $db->table('penjualan')
            ->select('penjualan.*, pengguna.full_name, akun_kas.account_name, pelanggan.customer_name, piutang.due_date, piutang.original_amount AS receivable_amount, piutang.status AS receivable_status')
            ->join('pengguna', 'pengguna.user_id = penjualan.cashier_id')
            ->join('mutasi_kas', 'mutasi_kas.sale_id = penjualan.sale_id', 'left')
            ->join('akun_kas', 'akun_kas.cash_account_id = mutasi_kas.cash_account_id', 'left')
            ->join('pelanggan', 'pelanggan.customer_id = penjualan.customer_id', 'left')
            ->join('piutang', 'piutang.sale_id = penjualan.sale_id', 'left')
            ->where('penjualan.sale_id', (int) $id)
            ->where('penjualan.status', 'POSTED');
        if (session()->get('role_code') === 'KASIR') {
            $saleQuery->where('penjualan.cashier_id', (int) session()->get('user_id'));
        }
        $sale = $saleQuery->get()->getRowArray();
        if ($sale === null) {
            return redirect()->to('/kasir')->with('error', 'Transaksi tidak ditemukan atau Anda tidak memiliki akses.');
        }

        helper(['form', 'inventory']);
        return view('sales/receipt', [
            'sale' => $sale,
            'items' => $db->query(
                    "SELECT detail_penjualan.*, allocations.stock_allocations
                     FROM detail_penjualan
                     LEFT JOIN (
                         SELECT alokasi_stok_penjualan.sale_item_id,
                                GROUP_CONCAT(CONCAT(lokasi_persediaan.location_name, ': ', alokasi_stok_penjualan.quantity)
                                             ORDER BY lokasi_persediaan.location_code SEPARATOR ', ') AS stock_allocations
                         FROM alokasi_stok_penjualan
                         JOIN lokasi_persediaan ON lokasi_persediaan.location_id = alokasi_stok_penjualan.location_id
                         GROUP BY alokasi_stok_penjualan.sale_item_id
                     ) AS allocations ON allocations.sale_item_id = detail_penjualan.sale_item_id
                     WHERE detail_penjualan.sale_id = ?
                     ORDER BY detail_penjualan.sale_item_id",
                [(int) $id],
            )->getResultArray(),
        ]);
    }

    private function newInvoiceNumber($db): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $invoiceNumber = 'MP-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
            if ($db->table('penjualan')->where('invoice_number', $invoiceNumber)->countAllResults() === 0) {
                return $invoiceNumber;
            }
        }

        throw new RuntimeException('Tidak dapat membuat nomor faktur unik.');
    }

    private function postString(string $key): string
    {
        $value = $this->request->getPost($key);

        return is_string($value) ? $value : '';
    }

    private function isDecimal(string $value, int $scale, bool $mustBePositive): bool
    {
        $maximumWholeDigits = $scale === 3 ? 11 : 12;
        $pattern = $scale === 0
            ? '/^\d{1,' . $maximumWholeDigits . '}$/'
            : '/^\d{1,' . $maximumWholeDigits . '}(?:\.\d{1,' . $scale . '})?$/';
        if (! preg_match($pattern, $value)) {
            return false;
        }

        $scaled = $this->scaledInteger($value, $scale);
        return $scaled !== null && (! $mustBePositive || $scaled > 0);
    }

    private function scaledInteger(string $value, int $scale): ?int
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return null;
        }

        $fraction = substr(str_pad($matches[2] ?? '', $scale, '0'), 0, $scale);
        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $digits = ltrim($whole . $fraction, '0');
        $digits = $digits === '' ? '0' : $digits;
        $maximumInteger = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximumInteger)
            || (strlen($digits) === strlen($maximumInteger) && strcmp($digits, $maximumInteger) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    private function retailQuantityToStockMicro(string $retailQuantity, string $conversionFactor): ?int
    {
        $retailMilli = $this->scaledInteger($retailQuantity, 3);
        $factorMilli = $this->scaledInteger($conversionFactor, 3);
        if ($retailMilli === null || $factorMilli === null || $retailMilli < 1 || $factorMilli < 1000) {
            return null;
        }

        $wholeStockUnits = intdiv($retailMilli, $factorMilli);
        $retailRemainderMilli = $retailMilli % $factorMilli;
        $stockMicro = $wholeStockUnits * 1_000_000;
        $fractionNumerator = $retailRemainderMilli * 1_000_000;
        $stockMicro += intdiv($fractionNumerator, $factorMilli);
        if ($fractionNumerator % $factorMilli !== 0) {
            $stockMicro++;
        }

        return $stockMicro;
    }

    private function decimalFromScaledInteger(int $value, int $scale): string
    {
        $divisor = 10 ** $scale;

        return intdiv($value, $divisor) . '.' . str_pad((string) ($value % $divisor), $scale, '0', STR_PAD_LEFT);
    }
}
