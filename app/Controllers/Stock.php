<?php

namespace App\Controllers;

use App\Libraries\NotificationService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use RuntimeException;
use Throwable;

class Stock extends BaseController
{
    public function index(): string
    {
        helper('form');
        helper('inventory');
        $db = db_connect();

        $balances = $db->query(
            "SELECT barang.product_id, barang.product_code, barang.product_name, barang.min_stock, barang.is_active,
                    kategori.category_name, satuan.unit_symbol,
                    COALESCE(SUM(CASE WHEN lokasi_persediaan.location_code = 'TOKO' THEN saldo_persediaan.quantity ELSE 0 END), 0) AS store_stock,
                    COALESCE(SUM(CASE WHEN lokasi_persediaan.location_code = 'GUDANG' THEN saldo_persediaan.quantity ELSE 0 END), 0) AS warehouse_stock
             FROM barang
             JOIN kategori ON kategori.category_id = barang.category_id
             JOIN satuan ON satuan.unit_id = barang.unit_id
             LEFT JOIN saldo_persediaan ON saldo_persediaan.product_id = barang.product_id
             LEFT JOIN lokasi_persediaan ON lokasi_persediaan.location_id = saldo_persediaan.location_id
             GROUP BY barang.product_id, barang.product_code, barang.product_name, barang.min_stock, barang.is_active,
                      kategori.category_name, satuan.unit_symbol
             ORDER BY barang.product_name",
        )->getResultArray();

        $products = [];
        foreach ($balances as $balance) {
            $productId = (int) $balance['product_id'];
            $products[$productId] = [
                'product_code' => $balance['product_code'],
                'product_name' => $balance['product_name'],
                'category_name' => $balance['category_name'],
                'unit_symbol' => $balance['unit_symbol'],
                'min_stock' => (float) $balance['min_stock'],
                'is_active' => (bool) $balance['is_active'],
                'TOKO' => (float) $balance['store_stock'],
                'GUDANG' => (float) $balance['warehouse_stock'],
            ];
        }
        $searchInput = $this->request->getGet('q');
        $search = trim(is_string($searchInput) ? $searchInput : '');
        if ($search !== '') {
            $products = array_filter($products, static fn (array $product): bool =>
                stripos($product['product_code'], $search) !== false
                || stripos($product['product_name'], $search) !== false
                || stripos($product['category_name'], $search) !== false,
            );
        }

        return view('stock/index', [
            'products' => $products,
            'search' => $search,
            'restockCount' => count(array_filter($products, static fn (array $product): bool => ($product['TOKO'] + $product['GUDANG']) <= $product['min_stock'])),
            'outOfStockCount' => count(array_filter($products, static fn (array $product): bool => ($product['TOKO'] + $product['GUDANG']) <= 0)),
            'canManage' => in_array(session()->get('role_code'), ['OWNER', 'STAF_GUDANG'], true),
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function transfer(): string
    {
        helper('form');
        helper('inventory');
        $db = db_connect();

        return view('stock/transfer', [
            'products' => $db->query(
                "SELECT b.product_id, b.product_code, b.product_name, b.avg_purchase_cost, u.unit_symbol,
                        COALESCE(SUM(CASE WHEN l.location_code = 'GUDANG' THEN g.quantity ELSE 0 END), 0) AS warehouse_stock
                 FROM barang AS b
                 JOIN satuan AS u ON u.unit_id = b.unit_id
                 LEFT JOIN saldo_persediaan AS g ON g.product_id = b.product_id
                 LEFT JOIN lokasi_persediaan AS l ON l.location_id = g.location_id
                 WHERE b.is_active = 1
                 GROUP BY b.product_id, b.product_code, b.product_name, b.avg_purchase_cost, u.unit_symbol
                 ORDER BY b.product_name",
            )->getResultArray(),
            'transferDate' => date('Y-m-d'),
            'errors' => session()->getFlashdata('form_errors') ?? [],
            'error' => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
        ]);
    }

    public function postTransfer(): RedirectResponse
    {
        $lines = $this->readLines(['product_id', 'quantity']);
        if ($lines === null) {
            return redirect()->back()->withInput()->with('error', 'Tambahkan setidaknya satu barang untuk dimutasi.');
        }

        $normalized = [];
        $seenProducts = [];
        foreach ($lines as $index => $line) {
            if ($line['product_id'] === '' && $line['quantity'] === '') {
                continue;
            }
            if (! ctype_digit($line['product_id']) || (int) $line['product_id'] < 1 || ! $this->isDecimal($line['quantity'], 3, true)) {
                return redirect()->back()->withInput()->with('error', 'Periksa baris ' . ($index + 1) . ': barang dan jumlah mutasi harus valid.');
            }
            $productId = (int) $line['product_id'];
            if (isset($seenProducts[$productId])) {
                return redirect()->back()->withInput()->with('error', 'Barang tidak boleh diulang dalam satu mutasi.');
            }
            $seenProducts[$productId] = true;
            $normalized[] = ['product_id' => $productId, 'quantity' => $line['quantity']];
        }
        if ($normalized === []) {
            return redirect()->back()->withInput()->with('error', 'Tambahkan setidaknya satu barang untuk dimutasi.');
        }

        $notesInput = $this->request->getPost('notes');
        $notes = is_string($notesInput) ? trim($notesInput) : '';
        if (mb_strlen($notes) > 1000) {
            return redirect()->back()->withInput()->with('error', 'Catatan mutasi maksimal 1.000 karakter.');
        }
        $transferDateInput = $this->request->getPost('transfer_date');
        $transferDate = is_string($transferDateInput) ? $transferDateInput : '';
        $parsedTransferDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $transferDate);
        if ($parsedTransferDate === false
            || $parsedTransferDate->format('Y-m-d') !== $transferDate
            || $transferDate > date('Y-m-d')) {
            return redirect()->back()->withInput()->with('error', 'Tanggal mutasi tidak valid.');
        }

        $db = db_connect();
        $locations = $db->table('lokasi_persediaan')->select('location_id, location_code')->whereIn('location_code', ['TOKO', 'GUDANG'])->get()->getResultArray();
        $locationIds = [];
        foreach ($locations as $location) {
            $locationIds[$location['location_code']] = (int) $location['location_id'];
        }
        if (! isset($locationIds['TOKO'], $locationIds['GUDANG'])) {
            throw new RuntimeException('Lokasi Toko atau Gudang belum tersedia.');
        }

        $db->transBegin();
        try {
            foreach ($normalized as $line) {
                $product = $db->query(
                    'SELECT product_id, avg_purchase_cost FROM barang WHERE product_id = ? AND is_active = 1 FOR UPDATE',
                    [$line['product_id']],
                )->getRowArray();
                if ($product === null) {
                    throw new DomainException('Salah satu barang tidak aktif atau tidak ditemukan.');
                }
                $balance = $db->query(
                    'SELECT quantity FROM saldo_persediaan WHERE product_id = ? AND location_id = ? FOR UPDATE',
                    [$line['product_id'], $locationIds['GUDANG']],
                )->getRowArray();
                if ($balance === null || (float) $balance['quantity'] < (float) $line['quantity']) {
                    throw new DomainException('Stok gudang tidak mencukupi untuk salah satu barang.');
                }
            }

            if (! $db->table('mutasi_stok')->insert([
                'from_location_id' => $locationIds['GUDANG'],
                'to_location_id' => $locationIds['TOKO'],
                'recorded_by' => (int) session()->get('user_id'),
                'transferred_at' => $transferDate . ' ' . date('H:i:s'),
                'status' => 'POSTED',
                'notes' => $notes === '' ? null : $notes,
            ])) {
                throw new RuntimeException('Gagal menyimpan header mutasi stok.');
            }
            $transferId = (int) $db->insertID();
            foreach ($normalized as $line) {
                $product = $db->table('barang')
                    ->select('avg_purchase_cost')
                    ->where('product_id', $line['product_id'])
                    ->get()
                    ->getRowArray();
                if ($product === null || ! $db->table('detail_mutasi_stok')->insert([
                    'stock_transfer_id' => $transferId,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost_snapshot' => $product['avg_purchase_cost'],
                ])) {
                    throw new RuntimeException('Gagal menyimpan detail mutasi stok.');
                }
                $detailId = (int) $db->insertID();
                $this->addBalance($db, $line['product_id'], $locationIds['GUDANG'], '-' . $line['quantity']);
                $this->addBalance($db, $line['product_id'], $locationIds['TOKO'], $line['quantity']);
                $source = ['stock_transfer_item_id' => $detailId];
                $this->insertStockMovement($db, $line['product_id'], $locationIds['GUDANG'], $source, '-' . $line['quantity'], (string) $product['avg_purchase_cost']);
                $this->insertStockMovement($db, $line['product_id'], $locationIds['TOKO'], $source, $line['quantity'], (string) $product['avg_purchase_cost']);
            }
            $this->writeActivity($db, 'POST_STOCK_TRANSFER', 'MUTASI_STOK', $transferId, null, [
                'line_count' => count($normalized),
                'from_location' => 'GUDANG',
                'to_location' => 'TOKO',
            ]);
            NotificationService::notifyRoles(
                $db,
                ['OWNER', 'KASIR', 'STAF_GUDANG'],
                'STOCK_TRANSFER',
                'Stok masuk ke Toko',
                count($normalized) . ' barang dimutasi dari Gudang ke Toko.',
                'stok/riwayat',
            );
            NotificationService::notifyLowStock($db, array_column($normalized, 'product_id'));
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi mutasi stok gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            if ($exception instanceof DomainException) {
                return redirect()->back()->withInput()->with('error', $exception->getMessage());
            }
            throw $exception;
        }

        return redirect()->to('/stok')->with('success', 'Mutasi stok berhasil dicatat. Saldo Toko dan Gudang sudah diperbarui.');
    }

    public function adjustment(): string
    {
        helper('form');
        $db = db_connect();
        $requestedProductId = $this->request->getGet('product_id');
        $selectedProductId = is_string($requestedProductId)
            && ctype_digit($requestedProductId)
            && (int) $requestedProductId > 0
                ? (int) $requestedProductId
                : null;

        return view('stock/adjustment', [
            'products' => $db->table('barang')
                ->select('barang.product_id, barang.product_code, barang.product_name, satuan.unit_symbol')
                ->join('satuan', 'satuan.unit_id = barang.unit_id')
                ->where('barang.is_active', true)
                ->orderBy('barang.product_name')
                ->get()
                ->getResultArray(),
            'locations' => $db->table('lokasi_persediaan')->orderBy('location_name')->get()->getResultArray(),
            'selectedProductId' => $selectedProductId,
            'errors' => session()->getFlashdata('form_errors') ?? [],
            'error' => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
        ]);
    }

    public function postAdjustment(): RedirectResponse
    {
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '' || mb_strlen($reason) > 1000) {
            return redirect()->back()->withInput()->with('error', 'Alasan penyesuaian wajib diisi dan maksimal 1.000 karakter.');
        }

        $lines = $this->readLines(['product_id', 'location_id', 'quantity_delta']);
        if ($lines === null) {
            return redirect()->back()->withInput()->with('error', 'Isi setidaknya satu baris barang, lokasi, dan perubahan jumlah.');
        }

        $normalized = [];
        $seenPairs = [];
        foreach ($lines as $index => $line) {
            if ($line['product_id'] === '' && $line['location_id'] === '' && $line['quantity_delta'] === '') {
                continue;
            }
            if (! ctype_digit($line['product_id']) || (int) $line['product_id'] < 1
                || ! ctype_digit($line['location_id']) || (int) $line['location_id'] < 1
                || ! $this->isAdjustmentDelta($line['quantity_delta'])) {
                return redirect()->back()->withInput()->with('error', 'Periksa baris ' . ($index + 1) . ': barang, lokasi, dan perubahan jumlah harus valid serta tidak boleh nol.');
            }

            $pair = $line['product_id'] . ':' . $line['location_id'];
            if (isset($seenPairs[$pair])) {
                return redirect()->back()->withInput()->with('error', 'Barang dan lokasi tidak boleh diulang dalam satu penyesuaian.');
            }
            $seenPairs[$pair] = true;
            $normalized[] = [
                'product_id' => (int) $line['product_id'],
                'location_id' => (int) $line['location_id'],
                'quantity_delta' => $line['quantity_delta'],
            ];
        }

        if ($normalized === []) {
            return redirect()->back()->withInput()->with('error', 'Isi setidaknya satu baris perubahan stok.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $products = [];
            foreach ($normalized as $line) {
                $product = $db->query(
                    'SELECT product_id, avg_purchase_cost
                     FROM barang WHERE product_id = ? AND is_active = 1 FOR UPDATE',
                    [$line['product_id']],
                )->getRowArray();
                if ($product === null) {
                    throw new DomainException('Salah satu barang tidak aktif atau tidak ditemukan.');
                }

                $location = $db->table('lokasi_persediaan')
                    ->where('location_id', $line['location_id'])
                    ->get()
                    ->getRowArray();
                if ($location === null) {
                    throw new DomainException('Salah satu lokasi stok tidak ditemukan.');
                }

                $balance = $db->query(
                    'SELECT quantity FROM saldo_persediaan
                     WHERE product_id = ? AND location_id = ? FOR UPDATE',
                    [$line['product_id'], $line['location_id']],
                )->getRowArray();
                if ($balance === null) {
                    throw new RuntimeException('Saldo barang untuk lokasi tersebut belum diinisialisasi.');
                }
                $products[$line['product_id']] = $product;
            }

            if (! $db->table('penyesuaian_stok')->insert([
                'recorded_by' => (int) session()->get('user_id'),
                'reason' => $reason,
                'status' => 'POSTED',
            ])) {
                throw new RuntimeException('Gagal membuat catatan penyesuaian stok.');
            }
            $adjustmentId = (int) $db->insertID();

            foreach ($normalized as $line) {
                $db->query(
                    'UPDATE saldo_persediaan
                     SET quantity = quantity + ?
                     WHERE product_id = ? AND location_id = ? AND quantity + ? >= 0',
                    [
                        $line['quantity_delta'],
                        $line['product_id'],
                        $line['location_id'],
                        $line['quantity_delta'],
                    ],
                );
                if ($db->affectedRows() !== 1) {
                    throw new DomainException('Stok tidak cukup untuk pengurangan pada salah satu barang/lokasi.');
                }

                if (! $db->table('detail_penyesuaian_stok')->insert([
                    'stock_adjustment_id' => $adjustmentId,
                    'product_id' => $line['product_id'],
                    'location_id' => $line['location_id'],
                    'quantity_delta' => $line['quantity_delta'],
                    'unit_cost_snapshot' => $products[$line['product_id']]['avg_purchase_cost'],
                ])) {
                    throw new RuntimeException('Gagal menyimpan rincian penyesuaian stok.');
                }
                $itemId = (int) $db->insertID();
                $this->insertStockMovement(
                    $db,
                    $line['product_id'],
                    $line['location_id'],
                    ['stock_adjustment_item_id' => $itemId],
                    $line['quantity_delta'],
                    (string) $products[$line['product_id']]['avg_purchase_cost'],
                );
            }

            $this->writeActivity($db, 'POST_STOCK_ADJUSTMENT', 'PENYESUAIAN_STOK', $adjustmentId, null, [
                'reason' => $reason,
                'line_count' => count($normalized),
                'items' => $normalized,
            ]);
            NotificationService::notifyRoles(
                $db,
                ['OWNER', 'STAF_GUDANG'],
                'STOCK_ADJUSTMENT',
                'Penyesuaian stok dicatat',
                count($normalized) . ' baris stok telah disesuaikan.',
                'stok/riwayat',
            );
            NotificationService::notifyLowStock($db, array_column($normalized, 'product_id'));
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penyesuaian stok gagal.');
            }
            $db->transCommit();
        } catch (DomainException $exception) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/stok')->with('success', 'Penyesuaian stok berhasil diposting dan masuk ke riwayat pergerakan.');
    }

    public function opening(): string
    {
        helper('form');
        return view('stock/opening', [
            'products' => db_connect()->table('barang')
                ->where('is_active', true)
                ->orderBy('product_name')
                ->get()
                ->getResultArray(),
            'locations' => db_connect()->table('lokasi_persediaan')
                ->orderBy('location_name')
                ->get()
                ->getResultArray(),
            'errors' => session()->getFlashdata('form_errors') ?? [],
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function postOpening(): RedirectResponse
    {
        $lines = $this->readLines(['product_id', 'location_id', 'quantity', 'unit_cost']);
        if ($lines === null) {
            return redirect()->back()->withInput()->with('error', 'Isi setidaknya satu baris barang, lokasi, jumlah, dan biaya per unit.');
        }

        $normalized = [];
        $pairs = [];
        foreach ($lines as $index => $line) {
            if ($line['product_id'] === '' && $line['location_id'] === '' && $line['quantity'] === '' && $line['unit_cost'] === '') {
                continue;
            }
            if (! ctype_digit($line['product_id']) || (int) $line['product_id'] < 1
                || ! ctype_digit($line['location_id']) || (int) $line['location_id'] < 1
                || ! $this->isDecimal($line['quantity'], 3, true)
                || ! $this->isDecimal($line['unit_cost'], 2, true)) {
                return redirect()->back()->withInput()->with('error', 'Periksa baris ' . ($index + 1) . ': semua nilai harus valid dan jumlah/biaya harus lebih dari nol.');
            }

            $key = $line['product_id'] . ':' . $line['location_id'];
            if (isset($pairs[$key])) {
                return redirect()->back()->withInput()->with('error', 'Barang dan lokasi tidak boleh diulang dalam satu batch saldo awal.');
            }
            $pairs[$key] = true;
            $normalized[] = [
                'product_id' => (int) $line['product_id'],
                'location_id' => (int) $line['location_id'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
            ];
        }

        if ($normalized === []) {
            return redirect()->back()->withInput()->with('error', 'Isi setidaknya satu baris saldo awal.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            foreach ($normalized as $line) {
                $product = $db->query(
                    'SELECT product_id FROM barang WHERE product_id = ? AND is_active = 1 FOR UPDATE',
                    [$line['product_id']],
                )->getRowArray();
                $location = $db->table('lokasi_persediaan')->where('location_id', $line['location_id'])->get()->getRowArray();
                if ($product === null || $location === null) {
                    throw new RuntimeException('Barang aktif atau lokasi stok tidak ditemukan. Periksa kembali pilihan form.');
                }

                $alreadyOpened = $db->table('pergerakan_persediaan')
                    ->join('detail_stok_awal', 'detail_stok_awal.opening_stock_item_id = pergerakan_persediaan.opening_stock_item_id')
                    ->where('pergerakan_persediaan.product_id', $line['product_id'])
                    ->where('pergerakan_persediaan.location_id', $line['location_id'])
                    ->countAllResults();
                if ($alreadyOpened > 0) {
                    throw new DomainException('Saldo awal untuk salah satu barang/lokasi sudah pernah diposting. Koreksi berikutnya harus memakai penyesuaian stok.');
                }

                $balance = $db->query(
                    'SELECT quantity FROM saldo_persediaan WHERE product_id = ? AND location_id = ? FOR UPDATE',
                    [$line['product_id'], $line['location_id']],
                )->getRowArray();
                if ($balance === null) {
                    throw new RuntimeException('Baris saldo barang/lokasi belum tersedia. Periksa data stok barang.');
                }
            }

            if (! $db->table('batch_saldo_awal')->insert([
                'created_by' => session()->get('user_id'),
                'posted_by' => session()->get('user_id'),
                'opening_date' => date('Y-m-d'),
                'status' => 'POSTED',
                'notes' => 'Saldo awal persediaan',
            ])) {
                throw new RuntimeException('Gagal membuat batch saldo awal.');
            }

            $batchId = (int) $db->insertID();
            $productIds = [];
            $openingItems = [];
            foreach ($normalized as $line) {
                if (! $db->table('detail_stok_awal')->insert([
                    'opening_batch_id' => $batchId,
                    'product_id' => $line['product_id'],
                    'location_id' => $line['location_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ])) {
                    throw new RuntimeException('Gagal menyimpan rincian saldo awal.');
                }

                $openingItemId = (int) $db->insertID();
                $openingItems[] = $line + ['opening_stock_item_id' => $openingItemId];
                $productIds[$line['product_id']] = true;
            }

            foreach (array_keys($productIds) as $productId) {
                $this->updateOpeningAverageCost($db, (int) $productId, $batchId);
            }

            foreach ($openingItems as $line) {
                $this->addBalance($db, $line['product_id'], $line['location_id'], $line['quantity']);
                $this->insertStockMovement(
                    $db,
                    $line['product_id'],
                    $line['location_id'],
                    ['opening_stock_item_id' => $line['opening_stock_item_id']],
                    $line['quantity'],
                    $line['unit_cost'],
                );
            }

            $this->writeActivity($db, 'POST_OPENING_STOCK', 'BATCH_SALDO_AWAL', $batchId, null, [
                'line_count' => count($normalized),
            ]);
            NotificationService::notifyRoles(
                $db,
                ['OWNER', 'STAF_GUDANG'],
                'OPENING_STOCK',
                'Saldo awal stok dicatat',
                count($normalized) . ' baris saldo awal telah dicatat.',
                'stok',
            );
            NotificationService::notifyLowStock($db, array_map('intval', array_keys($productIds)));
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi posting saldo awal gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();

            if ($exception instanceof DomainException) {
                return redirect()->back()->withInput()->with('error', $exception->getMessage());
            }

            throw $exception;
        }

        return redirect()->to('/stok')->with('success', 'Saldo awal berhasil diposting dan masuk ke riwayat stok.');
    }

    public function receipt(): string
    {
        helper('form');
        $db = db_connect();

        return view('stock/receipt', [
            'products' => $db->table('barang')->where('is_active', true)->orderBy('product_name')->get()->getResultArray(),
            'suppliers' => $db->table('pemasok')->where('is_active', true)->orderBy('supplier_name')->get()->getResultArray(),
            'accounts' => $db->table('akun_kas')->where('is_active', true)->orderBy('account_name')->get()->getResultArray(),
            'errors' => session()->getFlashdata('form_errors') ?? [],
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function postReceipt(): RedirectResponse
    {
        $supplierId = trim((string) $this->request->getPost('supplier_id'));
        $accountId = trim((string) $this->request->getPost('cash_account_id'));
        $receivedDate = trim((string) $this->request->getPost('received_date'));
        $invoice = trim((string) $this->request->getPost('invoice_number'));

        if (! ctype_digit($supplierId) || (int) $supplierId < 1
            || ! ctype_digit($accountId) || (int) $accountId < 1
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)
            || ! checkdate((int) substr($receivedDate, 5, 2), (int) substr($receivedDate, 8, 2), (int) substr($receivedDate, 0, 4))
            || strlen($invoice) > 100) {
            return redirect()->back()->withInput()->with('error', 'Pemasok, akun pembayaran, tanggal, atau nomor faktur tidak valid.');
        }

        $lines = $this->readLines(['product_id', 'quantity', 'unit_purchase_cost']);
        if ($lines === null) {
            return redirect()->back()->withInput()->with('error', 'Isi setidaknya satu baris barang, jumlah, dan harga beli.');
        }

        $normalized = [];
        $seenProducts = [];
        foreach ($lines as $index => $line) {
            if ($line['product_id'] === '' && $line['quantity'] === '' && $line['unit_purchase_cost'] === '') {
                continue;
            }
            if (! ctype_digit($line['product_id']) || (int) $line['product_id'] < 1
                || ! $this->isDecimal($line['quantity'], 3, true)
                || ! $this->isDecimal($line['unit_purchase_cost'], 2, true)) {
                return redirect()->back()->withInput()->with('error', 'Periksa baris ' . ($index + 1) . ': jumlah dan harga beli harus angka lebih dari nol.');
            }
            $productId = (int) $line['product_id'];
            if (isset($seenProducts[$productId])) {
                return redirect()->back()->withInput()->with('error', 'Setiap barang hanya boleh muncul sekali dalam satu penerimaan.');
            }
            $seenProducts[$productId] = true;
            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $line['quantity'],
                'unit_purchase_cost' => $line['unit_purchase_cost'],
            ];
        }

        if ($normalized === []) {
            return redirect()->back()->withInput()->with('error', 'Isi setidaknya satu baris barang yang diterima.');
        }

        $db = db_connect();
        $supplier = $db->table('pemasok')
            ->where('supplier_id', (int) $supplierId)
            ->where('is_active', true)
            ->get()
            ->getRowArray();
        $account = $db->table('akun_kas')
            ->where('cash_account_id', (int) $accountId)
            ->where('is_active', true)
            ->get()
            ->getRowArray();
        if ($supplier === null || $account === null) {
            return redirect()->back()->withInput()->with('error', 'Pemasok atau akun kas/bank tidak aktif atau tidak ditemukan.');
        }

        $paymentMethod = $account['account_type'] === 'BANK' ? 'TRANSFER' : 'TUNAI';
        $db->transBegin();
        try {
            foreach ($normalized as $line) {
                $product = $db->query(
                    'SELECT product_id FROM barang WHERE product_id = ? AND is_active = 1 FOR UPDATE',
                    [$line['product_id']],
                )->getRowArray();
                if ($product === null) {
                    throw new RuntimeException('Salah satu barang tidak aktif atau tidak ditemukan.');
                }

                $balances = $db->query(
                    'SELECT location_id, quantity FROM saldo_persediaan WHERE product_id = ? FOR UPDATE',
                    [$line['product_id']],
                )->getResultArray();
                if ($balances === []) {
                    throw new RuntimeException('Saldo lokasi barang belum diinisialisasi.');
                }
            }

            if (! $db->table('penerimaan_barang')->insert([
                'supplier_id' => (int) $supplierId,
                'received_by' => session()->get('user_id'),
                'invoice_number' => $invoice === '' ? null : $invoice,
                'received_date' => $receivedDate,
                'total_amount' => 0,
            ])) {
                throw new RuntimeException('Gagal membuat penerimaan barang.');
            }

            $receiptId = (int) $db->insertID();
            foreach ($normalized as $line) {
                $db->query(
                    'INSERT INTO detail_penerimaan_barang (goods_receipt_id, product_id, quantity, unit_purchase_cost, line_total)
                     VALUES (?, ?, ?, ?, ROUND(? * ?, 2))',
                    [
                        $receiptId,
                        $line['product_id'],
                        $line['quantity'],
                        $line['unit_purchase_cost'],
                        $line['quantity'],
                        $line['unit_purchase_cost'],
                    ],
                );
                if ($db->affectedRows() !== 1) {
                    throw new RuntimeException('Gagal menyimpan rincian barang diterima.');
                }

                $receiptItemId = (int) $db->insertID();
                $location = $db->table('lokasi_persediaan')
                    ->where('location_code', 'GUDANG')
                    ->get()
                    ->getRowArray();
                if ($location === null) {
                    throw new RuntimeException('Lokasi GUDANG tidak ditemukan.');
                }

                $this->updateWeightedAverage($db, $line['product_id'], $line['quantity'], $line['unit_purchase_cost']);
                $this->addBalance($db, $line['product_id'], (int) $location['location_id'], $line['quantity']);
                $this->insertStockMovement(
                    $db,
                    $line['product_id'],
                    (int) $location['location_id'],
                    ['goods_receipt_item_id' => $receiptItemId],
                    $line['quantity'],
                    $line['unit_purchase_cost'],
                );
            }

            $total = $db->query(
                'SELECT COALESCE(SUM(line_total), 0.00) AS total_amount FROM detail_penerimaan_barang WHERE goods_receipt_id = ?',
                [$receiptId],
            )->getRowArray()['total_amount'];
            if ((float) $total <= 0) {
                throw new RuntimeException('Total penerimaan harus lebih besar dari nol.');
            }

            if (! $db->table('penerimaan_barang')->where('goods_receipt_id', $receiptId)->update([
                'total_amount' => $total,
            ])) {
                throw new RuntimeException('Gagal memperbarui total penerimaan.');
            }

            if (! $db->table('mutasi_kas')->insert([
                'cash_account_id' => (int) $accountId,
                'goods_receipt_id' => $receiptId,
                'recorded_by' => session()->get('user_id'),
                'direction' => 'KELUAR',
                'payment_method' => $paymentMethod,
                'amount' => $total,
                'occurred_at' => $receivedDate . ' ' . date('H:i:s'),
                'notes' => 'Pembayaran penerimaan barang dari ' . $supplier['supplier_name'],
            ])) {
                throw new RuntimeException('Gagal mencatat pengeluaran pembelian di buku kas.');
            }

            $this->writeActivity($db, 'POST_GOODS_RECEIPT', 'PENERIMAAN_BARANG', $receiptId, null, [
                'supplier_id' => (int) $supplierId,
                'total_amount' => $total,
                'line_count' => count($normalized),
            ]);
            NotificationService::notifyRoles(
                $db,
                ['OWNER', 'STAF_GUDANG'],
                'GOODS_RECEIVED',
                'Barang masuk ke Gudang',
                count($normalized) . ' baris barang diterima dari ' . $supplier['supplier_name'] . '.',
                'stok/riwayat',
            );
            NotificationService::notifyLowStock($db, array_column($normalized, 'product_id'));
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penerimaan barang gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/stok')->with('success', 'Penerimaan berhasil diposting. Stok masuk ke gudang dan arus kas diperbarui.');
    }

    public function createSupplier(): RedirectResponse
    {
        $data = [
            'supplier_code' => strtoupper(trim((string) $this->request->getPost('supplier_code'))),
            'supplier_name' => trim((string) $this->request->getPost('supplier_name')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'address' => trim((string) $this->request->getPost('address')),
            'is_active' => true,
        ];

        $validation = service('validation');
        $validation->setRules([
            'supplier_code' => 'required|min_length[1]|max_length[80]|alpha_numeric_punct',
            'supplier_name' => 'required|min_length[2]|max_length[150]',
            'phone' => 'permit_empty|max_length[40]',
            'address' => 'permit_empty|max_length[65535]',
        ]);
        if (! $validation->run($data)) {
            return redirect()->to('/stok/penerimaan')->withInput()->with('form_errors', $validation->getErrors());
        }

        $db = db_connect();
        if ($db->table('pemasok')->where('supplier_code', $data['supplier_code'])->countAllResults() > 0) {
            return redirect()->to('/stok/penerimaan')->withInput()->with('error', 'Kode pemasok sudah digunakan.');
        }

        $db->transBegin();
        try {
            if (! $db->table('pemasok')->insert($data)) {
                throw new RuntimeException('Gagal menyimpan pemasok.');
            }
            $supplierId = (int) $db->insertID();
            $this->writeActivity($db, 'CREATE', 'PEMASOK', $supplierId, null, $data);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penyimpanan pemasok gagal.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/stok/penerimaan')->with('success', 'Pemasok berhasil ditambahkan. Sekarang pilih pemasok tersebut pada form penerimaan.');
    }

    /**
     * @param list<string> $keys
     *
     * @return list<array<string, string>>|null
     */
    private function readLines(array $keys): ?array
    {
        $posted = [];
        foreach ($keys as $key) {
            $value = $this->request->getPost($key);
            if (! is_array($value)) {
                return null;
            }
            $posted[$key] = array_values($value);
        }

        $count = count($posted[$keys[0]]);
        foreach ($posted as $values) {
            if (count($values) !== $count) {
                return null;
            }
        }

        $lines = [];
        for ($index = 0; $index < $count; $index++) {
            $line = [];
            foreach ($keys as $key) {
                if (! is_scalar($posted[$key][$index])) {
                    return null;
                }
                $line[$key] = trim((string) $posted[$key][$index]);
            }
            $lines[] = $line;
        }

        return $lines;
    }

    private function isDecimal(string $value, int $scale, bool $mustBePositive): bool
    {
        $pattern = '/^\d+(?:\.\d{1,' . $scale . '})?$/';
        if (! preg_match($pattern, $value)) {
            return false;
        }

        return ! $mustBePositive || (float) $value > 0;
    }

    private function isAdjustmentDelta(string $value): bool
    {
        if (! preg_match('/^-?\d{1,11}(?:\.\d{1,3})?$/', $value)) {
            return false;
        }

        return (float) $value !== 0.0;
    }

    private function addBalance($db, int $productId, int $locationId, string $quantity): void
    {
        $db->query(
            'UPDATE saldo_persediaan SET quantity = quantity + ? WHERE product_id = ? AND location_id = ?',
            [$quantity, $productId, $locationId],
        );
        if ($db->affectedRows() !== 1) {
            throw new RuntimeException('Gagal memperbarui saldo barang pada salah satu lokasi.');
        }
    }

    /**
     * @param array<string, int> $source
     */
    private function insertStockMovement(
        $db,
        int $productId,
        int $locationId,
        array $source,
        string $quantity,
        string $unitCost,
    ): void {
        $data = [
            'product_id' => $productId,
            'location_id' => $locationId,
            'recorded_by' => session()->get('user_id'),
            'quantity_delta' => $quantity,
            'unit_cost_snapshot' => $unitCost,
        ] + $source;

        if (! $db->table('pergerakan_persediaan')->insert($data)) {
            throw new RuntimeException('Gagal menulis ledger pergerakan stok.');
        }
    }

    private function updateOpeningAverageCost($db, int $productId, int $batchId): void
    {
        $existing = $db->query(
            'SELECT GREATEST(
                COALESCE(SUM(saldo_persediaan.quantity), 0.000) - COALESCE((
                    SELECT SUM(detail_stok_awal.quantity)
                    FROM detail_stok_awal
                    WHERE detail_stok_awal.opening_batch_id = ?
                      AND detail_stok_awal.product_id = ?
                ), 0.000),
                0.000
             ) AS quantity
             FROM saldo_persediaan WHERE product_id = ?',
            [$batchId, $productId, $productId],
        )->getRowArray()['quantity'];
        $incoming = $db->query(
            'SELECT COALESCE(SUM(quantity), 0.000) AS quantity,
                    COALESCE(SUM(quantity * unit_cost), 0.00) AS cost_total
             FROM detail_stok_awal WHERE opening_batch_id = ? AND product_id = ?',
            [$batchId, $productId],
        )->getRowArray();

        $result = $db->query(
            'UPDATE barang
             SET avg_purchase_cost = ROUND((? * avg_purchase_cost + ?) / (? + ?), 2)
             WHERE product_id = ?',
            [$existing, $incoming['cost_total'], $existing, $incoming['quantity'], $productId],
        );
        if ($result === false) {
            throw new RuntimeException('Gagal memperbarui biaya rata-rata awal barang.');
        }
    }

    private function updateWeightedAverage($db, int $productId, string $quantity, string $unitCost): void
    {
        $existingQuantity = $db->query(
            'SELECT COALESCE(SUM(quantity), 0.000) AS quantity
             FROM saldo_persediaan WHERE product_id = ?',
            [$productId],
        )->getRowArray()['quantity'];

        $result = $db->query(
            'UPDATE barang
             SET avg_purchase_cost = CASE
                 WHEN (? + ?) = 0 THEN 0
                 ELSE ROUND((? * avg_purchase_cost + ? * ?) / (? + ?), 2)
             END
             WHERE product_id = ?',
            [
                $existingQuantity,
                $quantity,
                $existingQuantity,
                $quantity,
                $unitCost,
                $existingQuantity,
                $quantity,
                $productId,
            ],
        );
        if ($result === false) {
            throw new RuntimeException('Gagal memperbarui rata-rata HPP barang.');
        }
    }

    private function writeActivity($db, string $action, string $entity, int $entityId, ?array $before, ?array $after): void
    {
        if (! $db->table('log_aktivitas')->insert([
            'user_id' => session()->get('user_id'),
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => (string) $entityId,
            'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_data' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        ])) {
            throw new RuntimeException('Gagal mencatat aktivitas ke log.');
        }
    }
}
