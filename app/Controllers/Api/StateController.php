<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\OperationalMasterDataModel;
use App\Models\ActivityDistributorsModel;
use App\Models\ReceivablesModel;
use App\Models\ReturnsCashModel;
use App\Models\SalesModel;
use App\Models\StockOperationsModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class StateController extends BaseController
{
    private const COLLECTIONS = [
        'activity',
        'products',
        'distributors',
        'customers',
        'stockMovements',
        'receipts',
        'sales',
        'receivables',
        'receivableWriteoffs',
        'returns',
        'cashEntries',
    ];

    private const ROLE_COLLECTIONS = [
        'Owner' => self::COLLECTIONS,
        'Kasir' => ['activity', 'products', 'distributors', 'customers', 'stockMovements', 'sales', 'receivables', 'receivableWriteoffs', 'returns'],
        'Staf Gudang' => ['activity', 'products', 'distributors', 'stockMovements', 'receipts'],
    ];

    private const ROLE_WRITABLE_COLLECTIONS = [
        'Owner' => self::COLLECTIONS,
        'Kasir' => ['activity', 'products', 'customers', 'stockMovements', 'sales', 'receivables', 'returns'],
        'Staf Gudang' => ['activity', 'products', 'distributors', 'stockMovements', 'receipts'],
    ];

    public function show(): ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Sesi tidak valid.']);
        }

        $db = db_connect();
        $row = $db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Data aplikasi belum diinisialisasi. Jalankan migration terlebih dahulu.');
        }

        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $masterData = new OperationalMasterDataModel($db);
        $activityDistributors = new ActivityDistributorsModel($db);
        $stockOperations = new StockOperationsModel($db);
        $returnsCash = new ReturnsCashModel($db);
        $visibleCollections = self::ROLE_COLLECTIONS[$user['role']] ?? [];
        $state['products'] = $masterData->products();
        $state['customers'] = $masterData->customers();
        if (in_array('activity', $visibleCollections, true)) {
            $state['activity'] = $activityDistributors->activity();
        }
        if (in_array('distributors', $visibleCollections, true)) {
            $state['distributors'] = $activityDistributors->distributors();
        }
        if (in_array('stockMovements', $visibleCollections, true)) {
            $state['stockMovements'] = $stockOperations->movements();
        }
        if (in_array('receipts', $visibleCollections, true)) {
            $state['receipts'] = $stockOperations->receipts();
        }
        if (in_array('returns', $visibleCollections, true)) {
            $state['returns'] = $returnsCash->returns();
        }
        if (in_array('cashEntries', $visibleCollections, true)) {
            $state['cashEntries'] = $returnsCash->cashEntries();
        }
        if (in_array('receivables', $visibleCollections, true)) {
            $receivablesModel = new ReceivablesModel($db);
            $state['receivables'] = $receivablesModel->all();
            $state['receivableWriteoffs'] = $receivablesModel->writeoffs();
        }
        if (in_array('sales', $visibleCollections, true)) {
            $state['sales'] = (new SalesModel($db))->all();
        }
        $visibleState = [];
        foreach ($visibleCollections as $collection) {
            $visibleState[$collection] = $state[$collection] ?? [];
        }

        return $this->response->setJSON([
            'revision' => (int) $row['revision'],
            'data'     => $visibleState,
        ]);
    }

    public function update(): ResponseInterface
    {
        helper('security');
        $user = $this->currentUser();
        if ($user === null) {
            return $this->jsonError('Sesi tidak valid.', 401);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)
            || ! is_int($payload['revision'] ?? null)
            || $payload['revision'] < 0
            || ! is_array($payload['data'] ?? null)) {
            return $this->jsonError('Format data atau nomor revisi tidak valid.', 422);
        }

        $encoded = json_encode($payload['data'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 10 * 1024 * 1024) {
            return $this->jsonError('Ukuran data aplikasi melebihi batas 10 MB.', 413);
        }

        $collections = array_keys($payload['data']);
        if (array_diff($collections, self::COLLECTIONS) !== []) {
            return $this->jsonError('Data berisi koleksi yang tidak dikenal.', 422);
        }

        $writableCollections = self::ROLE_WRITABLE_COLLECTIONS[$user['role']] ?? [];
        foreach ($writableCollections as $collection) {
            $items = $payload['data'][$collection] ?? null;
            if (! is_array($items)) {
                return $this->jsonError("Koleksi {$collection} harus berupa daftar.", 422);
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    return $this->jsonError("Isi koleksi {$collection} tidak valid.", 422);
                }
            }
        }

        $db = db_connect();
        $row = $db->table('application_state')->where('id', 1)->get()->getRowArray();
        if ($row === null) {
            throw new \RuntimeException('Data aplikasi belum diinisialisasi. Jalankan migration terlebih dahulu.');
        }
        if ((int) $row['revision'] !== $payload['revision']) {
            return $this->jsonError('Data berubah di sesi lain. Muat ulang halaman sebelum menyimpan kembali.', 409);
        }
        if ((int) $row['revision'] === 0 && $user['role'] !== 'Owner') {
            return $this->jsonError('Owner harus menginisialisasi data server terlebih dahulu.', 403);
        }

        $state = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $masterData = new OperationalMasterDataModel($db);
        $activityDistributors = new ActivityDistributorsModel($db);
        $salesModel = new SalesModel($db);
        $receivablesModel = new ReceivablesModel($db);
        $currentProducts = $masterData->products();
        $currentCustomers = $masterData->customers();
        $state['products'] = $currentProducts;
        $state['customers'] = $currentCustomers;
        $canReadActivity = in_array('activity', self::ROLE_COLLECTIONS[$user['role']] ?? [], true);
        $currentActivity = $canReadActivity ? $activityDistributors->activity() : [];
        if ($canReadActivity) {
            $state['activity'] = $currentActivity;
        }
        $canReadDistributors = in_array('distributors', self::ROLE_COLLECTIONS[$user['role']] ?? [], true);
        $currentDistributors = $canReadDistributors ? $activityDistributors->distributors() : [];
        if ($canReadDistributors) {
            $state['distributors'] = $currentDistributors;
        }
        $stockOperations = new StockOperationsModel($db);
        $returnsCash = new ReturnsCashModel($db);
        $canReadMovements = in_array('stockMovements', self::ROLE_COLLECTIONS[$user['role']] ?? [], true);
        $currentMovements = $canReadMovements ? $stockOperations->movements() : [];
        if ($canReadMovements) {
            $state['stockMovements'] = $currentMovements;
        }
        $canReadReceipts = in_array('receipts', self::ROLE_COLLECTIONS[$user['role']] ?? [], true);
        $currentReceipts = $canReadReceipts ? $stockOperations->receipts() : [];
        if ($canReadReceipts) {
            $state['receipts'] = $currentReceipts;
        }
        $canReadReturns = in_array('returns', self::ROLE_COLLECTIONS[$user['role']] ?? [], true);
        $currentReturns = $canReadReturns ? $returnsCash->returns() : [];
        if ($canReadReturns) {
            $state['returns'] = $currentReturns;
        }
        $canReadCashEntries = in_array('cashEntries', self::ROLE_COLLECTIONS[$user['role']] ?? [], true);
        $currentCashEntries = $canReadCashEntries ? $returnsCash->cashEntries() : [];
        if ($canReadCashEntries) {
            $state['cashEntries'] = $currentCashEntries;
        }
        $canWriteSales = in_array('sales', $writableCollections, true);
        $currentSales = $canWriteSales ? $salesModel->all() : [];
        if ($canWriteSales) {
            $state['sales'] = $currentSales;
        }
        $canReadReceivables = in_array('receivables', self::ROLE_COLLECTIONS[$user['role']] ?? [], true);
        if ($canReadReceivables) {
            $currentReceivables = $receivablesModel->all();
            $currentWriteoffs = $receivablesModel->writeoffs();
            $state['receivables'] = $currentReceivables;
            $state['receivableWriteoffs'] = $currentWriteoffs;
        } else {
            $currentReceivables = [];
            $currentWriteoffs = [];
        }
        if ($user['role'] === 'Kasir') {
            $validChanges = $this->validCashierChanges($state, $payload['data']);
            $validCreditSales = $this->validCreditSales($state, $payload['data']);
            $validCustomers = $this->validCashierCustomers($currentCustomers, $payload['data']['customers'] ?? null);
            if (! $validChanges || ! $validCreditSales || ! $validCustomers) {
                log_message('warning', 'Rejected cashier state update; validation failed: changes={changes}, credit={credit}, customers={customers}.', [
                    'changes'   => $validChanges ? 'valid' : 'invalid',
                    'credit'    => $validCreditSales ? 'valid' : 'invalid',
                    'customers' => $validCustomers ? 'valid' : 'invalid',
                ]);

                return $this->jsonError('Kasir hanya dapat menambahkan transaksi, mendaftarkan pelanggan baru tanpa limit kredit, mengajukan retur/void, dan mencatat mutasi stok penjualan.', 403);
            }
        }
        foreach ($writableCollections as $collection) {
            $state[$collection] = $payload['data'][$collection];
        }

        try {
            $masterData->validateProducts($state['products']);
            $masterData->validateCustomers($state['customers']);
            if ($canReadActivity && in_array('activity', $writableCollections, true)) {
                $activityDistributors->validateActivity($state['activity']);
            }
            if ($canReadDistributors && in_array('distributors', $writableCollections, true)) {
                $activityDistributors->validateDistributors($state['distributors']);
            }
            if ($canWriteSales) {
                $salesModel->validate($state['sales']);
            }
            if ($canReadReceivables) {
                $receivablesModel->validate($state['receivables'], $state['receivableWriteoffs']);
            }
            if ($canReadMovements && in_array('stockMovements', $writableCollections, true)) {
                $stockOperations->validateMovements($state['stockMovements']);
            }
            if ($canReadReceipts && in_array('receipts', $writableCollections, true)) {
                $stockOperations->validateReceipts($state['receipts']);
            }
            if ($canReadReturns && in_array('returns', $writableCollections, true)) {
                $returnsCash->validateReturns($state['returns']);
            }
            if ($canReadCashEntries && in_array('cashEntries', $writableCollections, true)) {
                $returnsCash->validateCashEntries($state['cashEntries']);
            }
        } catch (\InvalidArgumentException $error) {
            return $this->jsonError($error->getMessage(), 422);
        }

        $productsChanged = $state['products'] !== $currentProducts;
        $customersChanged = $state['customers'] !== $currentCustomers;
        $canWriteActivity = in_array('activity', $writableCollections, true);
        $activityChanged = $canWriteActivity && $state['activity'] !== $currentActivity;
        $canWriteDistributors = in_array('distributors', $writableCollections, true);
        $distributorsChanged = $canWriteDistributors && $state['distributors'] !== $currentDistributors;
        $salesChanged = $canWriteSales && $state['sales'] !== $currentSales;
        $canWriteReceivables = in_array('receivables', $writableCollections, true);
        $canWriteWriteoffs = in_array('receivableWriteoffs', $writableCollections, true);
        $receivablesChanged = $canWriteReceivables && $state['receivables'] !== $currentReceivables;
        $writeoffsChanged = $canWriteWriteoffs && $state['receivableWriteoffs'] !== $currentWriteoffs;
        $canWriteMovements = in_array('stockMovements', $writableCollections, true);
        $movementsChanged = $canWriteMovements && $state['stockMovements'] !== $currentMovements;
        $canWriteReceipts = in_array('receipts', $writableCollections, true);
        $receiptsChanged = $canWriteReceipts && $state['receipts'] !== $currentReceipts;
        $canWriteReturns = in_array('returns', $writableCollections, true);
        $returnsChanged = $canWriteReturns && $state['returns'] !== $currentReturns;
        $canWriteCashEntries = in_array('cashEntries', $writableCollections, true);
        $cashEntriesChanged = $canWriteCashEntries && $state['cashEntries'] !== $currentCashEntries;
        $products = $state['products'];
        $customers = $state['customers'];
        $activity = $activityChanged ? $state['activity'] : [];
        $distributors = $distributorsChanged ? $state['distributors'] : [];
        $sales = $salesChanged ? $state['sales'] : [];
        $receivables = $receivablesChanged ? $state['receivables'] : [];
        $writeoffs = $writeoffsChanged ? $state['receivableWriteoffs'] : [];
        $movements = $movementsChanged ? $state['stockMovements'] : [];
        $receipts = $receiptsChanged ? $state['receipts'] : [];
        $returns = $returnsChanged ? $state['returns'] : [];
        $cashEntries = $cashEntriesChanged ? $state['cashEntries'] : [];
        unset($state['products'], $state['customers']);
        if ($canReadActivity) {
            unset($state['activity']);
        }
        if ($canReadDistributors) {
            unset($state['distributors']);
        }
        if ($canWriteSales) {
            unset($state['sales']);
        }
        if ($canReadReceivables) {
            unset($state['receivables'], $state['receivableWriteoffs']);
        }
        if ($canReadMovements) {
            unset($state['stockMovements']);
        }
        if ($canReadReceipts) {
            unset($state['receipts']);
        }
        if ($canReadReturns) {
            unset($state['returns']);
        }
        if ($canReadCashEntries) {
            unset($state['cashEntries']);
        }
        $updatedPayload = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (! $db->transBegin()) {
            throw new \RuntimeException('Penyimpanan transaksi database tidak dapat dimulai.');
        }
        try {
            $db->table('application_state')
                ->where('id', 1)
                ->where('revision', $payload['revision'])
                ->update([
                    'revision'   => $payload['revision'] + 1,
                    'payload'    => $updatedPayload,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($db->affectedRows() !== 1) {
                $db->transRollback();

                return $this->jsonError('Data berubah di sesi lain. Muat ulang halaman sebelum menyimpan kembali.', 409);
            }

            if ($productsChanged) {
                $masterData->replaceProducts($products);
            }
            if ($customersChanged) {
                $masterData->replaceCustomers($customers);
            }
            if ($activityChanged) {
                $activityDistributors->replaceActivity($activity);
            }
            if ($distributorsChanged) {
                $activityDistributors->replaceDistributors($distributors);
            }
            if ($salesChanged) {
                $salesModel->replace($sales);
            }
            if ($receivablesChanged || $writeoffsChanged) {
                $receivablesModel->replace(
                    $receivablesChanged ? $receivables : $currentReceivables,
                    $writeoffsChanged ? $writeoffs : $currentWriteoffs,
                );
            }
            if ($movementsChanged) {
                $stockOperations->replaceMovements($movements);
            }
            if ($receiptsChanged) {
                $stockOperations->replaceReceipts($receipts);
            }
            if ($returnsChanged) {
                $returnsCash->replaceReturns($returns);
            }
            if ($cashEntriesChanged) {
                $returnsCash->replaceCashEntries($cashEntries);
            }
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new \RuntimeException('Penyimpanan data operasional gagal.');
            }
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }

        return $this->response->setJSON([
            'revision'  => $payload['revision'] + 1,
            'csrfToken' => csrf_hash(),
        ]);
    }

        /**
         * Prevent cashier snapshots from rewriting old transactions, product data,
         * or stock history while allowing new sales to consume allocated stock.
         *
         * @param array<string, mixed> $current
         * @param array<string, mixed> $incoming
         */
        private function validCashierChanges(array $current, array $incoming): bool
        {
            $beforeSales = $current['sales'] ?? [];
            $afterSales  = $incoming['sales'] ?? null;
            if (! is_array($afterSales) || count($afterSales) < count($beforeSales)) {
                return false;
            }
            foreach ($beforeSales as $index => $sale) {
                $updated = $afterSales[$index] ?? null;
                if (! is_array($updated) || $this->withoutSettlement($sale) !== $this->withoutSettlement($updated)) {
                    return false;
                }
            }
            $newSales = array_slice($afterSales, count($beforeSales));
            if (! $this->validNewSales($newSales, $current['products'] ?? [], $beforeSales)) {
                return false;
            }

            $beforeReceivables = $current['receivables'] ?? [];
            $afterReceivables  = $incoming['receivables'] ?? null;
            if (! is_array($afterReceivables) || count($afterReceivables) < count($beforeReceivables)) {
                return false;
            }
            foreach ($beforeReceivables as $index => $receivable) {
                $updated = $afterReceivables[$index] ?? null;
                if (! is_array($updated) || ! $this->validReceivableUpdate($receivable, $updated, $current['receivableWriteoffs'] ?? [])) {
                    return false;
                }
            }
            $newReceivables = array_slice($afterReceivables, count($beforeReceivables));
            $expectedCreditSales = [];
            foreach ($newSales as $sale) {
                if (($sale['payment'] ?? null) === 'Bon') {
                    $expectedCreditSales[(string) ($sale['id'] ?? '')] = $sale;
                }
            }
            if (count($newReceivables) !== count($expectedCreditSales)) {
                return false;
            }
            foreach ($newReceivables as $receivable) {
                $saleId = (string) ($receivable['saleId'] ?? '');
                $sale = $expectedCreditSales[$saleId] ?? null;
                if ($sale === null
                    || (string) ($receivable['id'] ?? '') !== $saleId
                    || (string) ($receivable['number'] ?? '') !== (string) ($sale['number'] ?? '')
                    || (string) ($receivable['customer'] ?? '') !== (string) ($sale['customer'] ?? '')
                    || (string) ($receivable['customerId'] ?? '') !== (string) ($sale['customerId'] ?? '')
                    || (string) ($receivable['dueDate'] ?? '') !== (string) ($sale['dueDate'] ?? '')
                    || (int) ($receivable['total'] ?? -1) !== (int) ($sale['total'] ?? -2)
                    || (int) ($receivable['paid'] ?? -1) !== 0
                    || ($receivable['payments'] ?? null) !== []) {
                    return false;
                }
                unset($expectedCreditSales[$saleId]);
            }
            if ($expectedCreditSales !== []) {
                return false;
            }
            foreach ($beforeSales as $index => $sale) {
                if (($sale['payment'] ?? null) !== 'Bon') {
                    continue;
                }
                $updatedSale = $afterSales[$index];
                $receivable = $this->findReceivable($afterReceivables, (string) ($sale['id'] ?? ''));
                if ($receivable === null
                    || (int) ($updatedSale['paidAmount'] ?? -1) !== (int) ($receivable['paid'] ?? 0)) {
                    return false;
                }
                $hasProcessedReturn = false;
                foreach ($current['returns'] ?? [] as $return) {
                    if ((string) ($return['saleId'] ?? '') === (string) ($sale['id'] ?? '')
                        && ! in_array($return['status'] ?? null, ['Menunggu Persetujuan', 'Ditolak'], true)) {
                        $hasProcessedReturn = true;
                        break;
                    }
                }
                $expectedStatuses = ($receivable['paid'] ?? 0) >= ($receivable['total'] ?? 0)
                    ? ($hasProcessedReturn ? ['Lunas', 'Retur Sebagian', 'Retur Penuh', 'Void'] : ['Lunas'])
                    : ['Piutang', 'Retur Sebagian', 'Retur Penuh', 'Void'];
                if (! in_array($updatedSale['status'] ?? null, $expectedStatuses, true)) {
                    return false;
                }
            }

            $beforeMovements = $current['stockMovements'] ?? [];
            $afterMovements  = $incoming['stockMovements'] ?? null;
            if (! is_array($afterMovements) || array_slice($afterMovements, 0, count($beforeMovements)) !== $beforeMovements) {
                return false;
            }

            $currentReturns = $current['returns'] ?? [];
            $incomingReturns = $incoming['returns'] ?? null;
            if (! is_array($incomingReturns) || array_slice($incomingReturns, 0, count($currentReturns)) !== $currentReturns) {
                return false;
            }
            if (! $this->validReturnRequests($currentReturns, $incomingReturns, $beforeSales)) {
                return false;
            }

            $beforeProducts = $current['products'] ?? [];
            $afterProducts  = $incoming['products'] ?? null;
            if (! is_array($afterProducts) || count($beforeProducts) !== count($afterProducts)) {
                return false;
            }
            foreach ($beforeProducts as $index => $before) {
                $after = $afterProducts[$index] ?? null;
                if (! is_array($after) || ($before['id'] ?? null) !== ($after['id'] ?? null)
                    || $this->withoutStock($before) !== $this->withoutStock($after)) {
                    return false;
                }
            }

            $expectedDeltas = [];
            foreach ($newSales as $sale) {
                if (! is_array($sale) || ! is_array($sale['items'] ?? null)) {
                    return false;
                }
                foreach ($sale['items'] as $item) {
                    if (! is_array($item) || ! is_array($item['stockAllocations'] ?? null)) {
                        return false;
                    }
                    foreach ($item['stockAllocations'] as $allocation) {
                        $productId = (string) ($item['productId'] ?? '');
                        $location  = (string) ($allocation['location'] ?? '');
                        $quantity  = $allocation['quantity'] ?? null;
                        if (! in_array($location, ['Toko', 'Gudang'], true) || ! is_int($quantity) || $quantity < 1) {
                            return false;
                        }
                        // A sale allocation must be offset by a negative stock movement.
                        $expectedDeltas[$productId][$location] = ($expectedDeltas[$productId][$location] ?? 0) + $quantity;
                    }
                }
            }

            $movementAppends = array_slice($afterMovements, count($beforeMovements));
            foreach ($movementAppends as $movement) {
                if (! is_array($movement) || ($movement['type'] ?? null) !== 'Penjualan') {
                    return false;
                }
                $productId = (string) ($movement['productId'] ?? '');
                $location  = (string) ($movement['location'] ?? '');
                $delta     = $movement['delta'] ?? null;
                if (! is_int($delta) || $delta >= 0) {
                    return false;
                }
                $expectedDeltas[$productId][$location] = ($expectedDeltas[$productId][$location] ?? 0) + $delta;
            }

            foreach ($expectedDeltas as $productId => $locations) {
                foreach ($locations as $location => $delta) {
                    if ($delta !== 0) {
                        return false;
                    }
                }
                $before = $this->findProduct($beforeProducts, $productId);
                $after  = $this->findProduct($afterProducts, $productId);
                if ($before === null || $after === null) {
                    return false;
                }
            }

            foreach ($beforeProducts as $index => $before) {
                $after = $afterProducts[$index];
                foreach (['Toko' => 'shop', 'Gudang' => 'warehouse'] as $location => $field) {
                    $expected = (int) ($before[$field] ?? 0) - $this->salesConsumption($newSales, (string) $before['id'], $location);
                    if ((int) ($after[$field] ?? -1) !== $expected) {
                        return false;
                    }
                }
            }

            return true;
        }

        /**
         * @param list<array<string, mixed>> $sales
         * @param list<array<string, mixed>> $products
         * @param list<array<string, mixed>> $existingSales
         */
        private function validNewSales(array $sales, array $products, array $existingSales): bool
        {
            $saleIds = array_fill_keys(array_map(static fn (array $sale): string => (string) ($sale['id'] ?? ''), $existingSales), true);
            $saleNumbers = array_fill_keys(array_map(static fn (array $sale): string => (string) ($sale['number'] ?? ''), $existingSales), true);
            $remainingStock = [];
            foreach ($sales as $sale) {
                $saleId = (string) ($sale['id'] ?? '');
                $number = (string) ($sale['number'] ?? '');
                if ($saleId === '' || isset($saleIds[$saleId])
                    || $number === '' || isset($saleNumbers[$number])
                    || ! in_array($sale['type'] ?? null, ['Eceran', 'Grosir'], true)
                    || ! in_array($sale['payment'] ?? null, ['Tunai', 'Transfer', 'Bon'], true)
                    || ! is_array($sale['items'] ?? null) || $sale['items'] === []) {
                    return false;
                }
                $saleIds[$saleId] = true;
                $saleNumbers[$number] = true;
                $total = 0;
                foreach ($sale['items'] as $item) {
                    if (! is_array($item) || ! is_int($item['quantity'] ?? null) || $item['quantity'] < 1) {
                        return false;
                    }
                    $product = $this->findProduct($products, (string) ($item['productId'] ?? ''));
                    if ($product === null || ($product['active'] ?? true) === false
                        || (string) ($item['code'] ?? '') !== (string) ($product['code'] ?? '')
                        || (string) ($item['name'] ?? '') !== (string) ($product['name'] ?? '')
                        || (string) ($item['unit'] ?? '') !== (string) ($product['unit'] ?? '')) {
                        return false;
                    }
                    $productId = (string) ($item['productId'] ?? '');
                    if (! isset($remainingStock[$productId])) {
                        $remainingStock[$productId] = [
                            'Toko' => (int) ($product['shop'] ?? 0),
                            'Gudang' => (int) ($product['warehouse'] ?? 0),
                        ];
                    }
                    $wholesale = ($sale['type'] ?? null) === 'Grosir'
                        && $item['quantity'] >= (int) ($product['wholesaleMin'] ?? PHP_INT_MAX);
                    $expectedPrice = (int) ($product[$wholesale ? 'wholesale' : 'retail'] ?? -1);
                    if ((int) ($item['price'] ?? -2) !== $expectedPrice
                        || (int) ($item['cost'] ?? -2) !== (int) ($product['buy'] ?? -1)
                        || (int) ($item['subtotal'] ?? -1) !== $expectedPrice * $item['quantity']
                        || ! is_array($item['stockAllocations'] ?? null)) {
                        return false;
                    }
                    $allocated = 0;
                    $allocationsByLocation = ['Toko' => 0, 'Gudang' => 0];
                    foreach ($item['stockAllocations'] as $allocation) {
                        if (! is_array($allocation)
                            || ! in_array($allocation['location'] ?? null, ['Toko', 'Gudang'], true)
                            || ! is_int($allocation['quantity'] ?? null)
                            || $allocation['quantity'] < 1) {
                            return false;
                        }
                        $allocated += $allocation['quantity'];
                        $allocationsByLocation[$allocation['location']] += $allocation['quantity'];
                    }
                    if ($allocated !== $item['quantity']) {
                        return false;
                    }
                    $expectedWarehouse = ($sale['type'] ?? null) === 'Grosir'
                        ? min($remainingStock[$productId]['Gudang'], $item['quantity'])
                        : 0;
                    $expectedShop = $item['quantity'] - $expectedWarehouse;
                    if ($allocationsByLocation['Gudang'] !== $expectedWarehouse
                        || $allocationsByLocation['Toko'] !== $expectedShop
                        || $expectedShop > $remainingStock[$productId]['Toko']) {
                        return false;
                    }
                    $remainingStock[$productId]['Gudang'] -= $expectedWarehouse;
                    $remainingStock[$productId]['Toko'] -= $expectedShop;
                    $total += $expectedPrice * $item['quantity'];
                }
                $payment = $sale['payment'];
                $paidAmount = $payment === 'Bon' ? 0 : $total;
                $expectedStatus = $payment === 'Bon' ? 'Piutang' : 'Lunas';
                $validSettlement = (int) ($sale['paidAmount'] ?? -1) === $paidAmount
                    && ($sale['status'] ?? null) === $expectedStatus;
                if ($payment === 'Tunai') {
                    $cashReceived = $sale['cashReceived'] ?? null;
                    $validSettlement = $validSettlement
                        && is_int($cashReceived)
                        && $cashReceived >= $total
                        && (int) ($sale['change'] ?? -1) === $cashReceived - $total;
                } elseif ($payment === 'Transfer') {
                    $validSettlement = $validSettlement
                        && ($sale['cashReceived'] ?? null) === null
                        && (int) ($sale['change'] ?? -1) === 0;
                } else {
                    $validSettlement = $validSettlement
                        && ($sale['cashReceived'] ?? null) === null
                        && (int) ($sale['change'] ?? -1) === 0
                        && is_string($sale['dueDate'] ?? null)
                        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sale['dueDate']) === 1
                        && $sale['dueDate'] >= date('Y-m-d')
                        && is_string($sale['customer'] ?? null)
                        && trim($sale['customer']) !== '';
                }
                if ((int) ($sale['total'] ?? -1) !== $total || ! $validSettlement) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param list<array<string, mixed>> $currentReturns
         * @param list<array<string, mixed>> $incomingReturns
         * @param list<array<string, mixed>> $sales
         */
        private function validReturnRequests(array $currentReturns, array $incomingReturns, array $sales): bool
        {
            $pendingSales = [];
            foreach ($currentReturns as $return) {
                if (($return['status'] ?? null) === 'Menunggu Persetujuan') {
                    $pendingSales[(string) ($return['saleId'] ?? '')] = true;
                }
            }

            foreach (array_slice($incomingReturns, count($currentReturns)) as $request) {
                if (! is_array($request)
                    || ($request['status'] ?? null) !== 'Menunggu Persetujuan'
                    || ! in_array($request['type'] ?? null, ['Void', 'Retur'], true)
                    || ! in_array($request['refundMethod'] ?? null, ['Tunai', 'Transfer'], true)
                    || ! is_int($request['amount'] ?? null)
                    || $request['amount'] <= 0
                    || ! is_string($request['reason'] ?? null)
                    || trim($request['reason']) === ''
                    || ! is_array($request['items'] ?? null)) {
                    return false;
                }

                $saleId = (string) ($request['saleId'] ?? '');
                if (isset($pendingSales[$saleId])) {
                    return false;
                }
                $sale = null;
                foreach ($sales as $candidate) {
                    if ((string) ($candidate['id'] ?? '') === $saleId) {
                        $sale = $candidate;
                        break;
                    }
                }
                if ($sale === null || in_array($sale['status'] ?? null, ['Void', 'Retur Penuh'], true)) {
                    return false;
                }

                $priorQuantities = [];
                foreach ($currentReturns as $return) {
                    if (($return['saleId'] ?? null) !== $saleId
                        || in_array($return['status'] ?? null, ['Menunggu Persetujuan', 'Ditolak'], true)) {
                        continue;
                    }
                    foreach ($return['items'] ?? [] as $item) {
                        $productId = (string) ($item['productId'] ?? '');
                        $priorQuantities[$productId] = ($priorQuantities[$productId] ?? 0) + (int) ($item['quantity'] ?? 0);
                    }
                }

                $requestedQuantities = [];
                $amount = 0;
                foreach ($request['items'] as $item) {
                    if (! is_array($item)
                        || ! is_int($item['quantity'] ?? null)
                        || $item['quantity'] <= 0
                        || ! is_int($item['price'] ?? null)
                        || ! is_int($item['cost'] ?? null)
                        || ! is_bool($item['restocked'] ?? null)) {
                        return false;
                    }
                    $productId = (string) ($item['productId'] ?? '');
                    $saleItem = null;
                    foreach ($sale['items'] ?? [] as $candidate) {
                        if ((string) ($candidate['productId'] ?? '') === $productId) {
                            $saleItem = $candidate;
                            break;
                        }
                    }
                    if ($saleItem === null
                        || (int) ($item['price'] ?? -1) !== (int) ($saleItem['price'] ?? -2)
                        || (int) ($item['cost'] ?? -1) !== (int) ($saleItem['cost'] ?? -2)) {
                        return false;
                    }
                    $requestedQuantities[$productId] = ($requestedQuantities[$productId] ?? 0) + $item['quantity'];
                    if ($requestedQuantities[$productId] + ($priorQuantities[$productId] ?? 0) > (int) ($saleItem['quantity'] ?? 0)) {
                        return false;
                    }
                    $amount += $item['price'] * $item['quantity'];
                }
                if ($amount !== $request['amount'] || $requestedQuantities === []) {
                    return false;
                }
                if (($request['type'] ?? null) === 'Void') {
                    foreach ($sale['items'] ?? [] as $saleItem) {
                        $productId = (string) ($saleItem['productId'] ?? '');
                        if (($requestedQuantities[$productId] ?? 0) + ($priorQuantities[$productId] ?? 0)
                            !== (int) ($saleItem['quantity'] ?? 0)) {
                            return false;
                        }
                    }
                    if ($priorQuantities !== []) {
                        return false;
                    }
                }
                $pendingSales[$saleId] = true;
            }

            return true;
        }

        /**
         * @param array<string, mixed> $current
         * @param array<string, mixed> $incoming
         */
        private function validCreditSales(array $current, array $incoming): bool
        {
            $oldCount = count($current['sales'] ?? []);
            $newSales = array_slice($incoming['sales'] ?? [], $oldCount);
            $customers = $current['customers'] ?? [];
            $receivables = $current['receivables'] ?? [];
            $writeoffs = $current['receivableWriteoffs'] ?? [];
            $addedBalances = [];

            foreach ($newSales as $sale) {
                if (($sale['payment'] ?? null) !== 'Bon' || ($sale['type'] ?? null) !== 'Grosir') {
                    continue;
                }

                $customerId = (string) ($sale['customerId'] ?? '');
                $customer = $this->findCustomer($customers, $customerId);
                if ($customer === null || ! (bool) ($customer['active'] ?? false)) {
                    return false;
                }

                $date = (string) ($sale['dueDate'] ?? '');
                $maxDate = (new \DateTimeImmutable('today'))->modify('+' . max(1, (int) ($customer['creditDays'] ?? 1)) . ' days')->format('Y-m-d');
                if ($date < date('Y-m-d') || $date > $maxDate) {
                    return false;
                }

                $addedBalances[$customerId] = ($addedBalances[$customerId] ?? 0) + (int) ($sale['total'] ?? 0);
            }

            foreach ($addedBalances as $customerId => $added) {
                $customer = $this->findCustomer($customers, $customerId);
                $outstanding = 0;
                foreach ($receivables as $receivable) {
                    if ((string) ($receivable['customerId'] ?? '') === $customerId) {
                        $writtenOff = 0;
                        foreach ($writeoffs as $writeoff) {
                            if ((string) ($writeoff['receivableId'] ?? '') === (string) ($receivable['id'] ?? '')) {
                                $writtenOff += (int) ($writeoff['amount'] ?? 0);
                            }
                        }
                        $outstanding += max(0, (int) ($receivable['total'] ?? 0) - (int) ($receivable['paid'] ?? 0) - $writtenOff);
                    }
                }
                if ($outstanding + $added > (int) ($customer['creditLimit'] ?? 0)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Cashiers may register customer profiles, but only owners may grant
         * credit limits or change existing customer records.
         *
         * @param list<array<string, mixed>> $current
         * @param mixed $incoming
         */
        private function validCashierCustomers(array $current, mixed $incoming): bool
        {
            if (! is_array($incoming) || ! array_is_list($incoming)
                || count($incoming) < count($current)
                || array_slice($incoming, 0, count($current)) !== $current) {
                return false;
            }

            $ids = [];
            $names = [];
            foreach ($current as $customer) {
                $ids[(string) ($customer['id'] ?? '')] = true;
                $names[strtolower(trim((string) ($customer['name'] ?? '')))] = true;
            }

            foreach (array_slice($incoming, count($current)) as $customer) {
                if (! is_array($customer)
                    || ! is_string($customer['id'] ?? null)
                    || trim($customer['id']) === ''
                    || ! is_string($customer['name'] ?? null)
                    || trim($customer['name']) === ''
                    || ($customer['creditLimit'] ?? null) !== 0
                    || ($customer['creditDays'] ?? null) !== 7
                    || ($customer['active'] ?? null) !== true) {
                    return false;
                }
                $id = trim($customer['id']);
                $name = strtolower(trim($customer['name']));
                if (isset($ids[$id]) || isset($names[$name])) {
                    return false;
                }
                $ids[$id] = true;
                $names[$name] = true;
            }

            return true;
        }

        /**
         * @param array<string, mixed> $current
         * @param array<string, mixed> $incoming
         */
        private function validReceivableUpdate(array $current, array $incoming, array $writeoffs): bool
        {
            $currentPayments = $current['payments'] ?? [];
            $incomingPayments = $incoming['payments'] ?? null;
            if (! is_array($incomingPayments)
                || array_slice($incomingPayments, 0, count($currentPayments)) !== $currentPayments) {
                return false;
            }

            $original = $current;
            $updated = $incoming;
            unset($original['paid'], $original['payments'], $updated['paid'], $updated['payments']);
            if ($original !== $updated) {
                return false;
            }

            $addedPayments = array_slice($incomingPayments, count($currentPayments));
            $sum = 0;
            foreach ($addedPayments as $payment) {
                if (! is_array($payment) || ! in_array($payment['method'] ?? null, ['Tunai', 'Transfer'], true)
                    || ! is_int($payment['amount'] ?? null) || $payment['amount'] <= 0) {
                    return false;
                }
                $sum += $payment['amount'];
            }

            $writeoffTotal = 0;
            foreach ($writeoffs as $writeoff) {
                if ((string) ($writeoff['receivableId'] ?? '') === (string) ($current['id'] ?? '')) {
                    $writeoffTotal += (int) ($writeoff['amount'] ?? 0);
                }
            }

            return (int) ($incoming['paid'] ?? -1) === (int) ($current['paid'] ?? 0) + $sum
                && (int) ($incoming['paid'] ?? 0) <= max(0, (int) ($incoming['total'] ?? 0) - $writeoffTotal);
        }

        /**
         * @param list<array<string, mixed>> $receivables
         *
         * @return array<string, mixed>|null
         */
        private function findReceivable(array $receivables, string $saleId): ?array
        {
            foreach ($receivables as $receivable) {
                if ((string) ($receivable['saleId'] ?? '') === $saleId) {
                    return $receivable;
                }
            }

            return null;
        }

        /**
         * @param array<string, mixed> $sale
         *
         * @return array<string, mixed>
         */
        private function withoutSettlement(array $sale): array
        {
            unset($sale['paidAmount'], $sale['status']);

            return $sale;
        }

        /**
         * @param list<array<string, mixed>> $customers
         *
         * @return array<string, mixed>|null
         */
        private function findCustomer(array $customers, string $id): ?array
        {
            foreach ($customers as $customer) {
                if ((string) ($customer['id'] ?? '') === $id) {
                    return $customer;
                }
            }

            return null;
        }

        /**
         * @param array<string, mixed> $product
         *
         * @return array<string, mixed>
         */
        private function withoutStock(array $product): array
        {
            unset($product['shop'], $product['warehouse']);

            return $product;
        }

        /**
         * @param list<array<string, mixed>> $products
         *
         * @return array<string, mixed>|null
         */
        private function findProduct(array $products, string $id): ?array
        {
            foreach ($products as $product) {
                if ((string) ($product['id'] ?? '') === $id) {
                    return $product;
                }
            }

            return null;
        }

        /**
         * @param list<array<string, mixed>> $sales
         */
        private function salesConsumption(array $sales, string $productId, string $location): int
        {
            $quantity = 0;
            foreach ($sales as $sale) {
                foreach ($sale['items'] ?? [] as $item) {
                    if ((string) ($item['productId'] ?? '') !== $productId) {
                        continue;
                    }
                    foreach ($item['stockAllocations'] ?? [] as $allocation) {
                        if (($allocation['location'] ?? null) === $location) {
                            $quantity += (int) ($allocation['quantity'] ?? 0);
                        }
                    }
                }
            }

            return $quantity;
        }

    /**
     * @return array<string, mixed>|null
     */
    private function currentUser(): ?array
    {
        $userId = service('session')->get('user_id');
        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            return null;
        }

        $user = (new UserModel())->find((int) $userId);
        if ($user === null || ! (bool) $user['active']) {
            return null;
        }

        return $user;
    }

    private function jsonError(string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'error'     => $message,
            'csrfToken' => csrf_hash(),
        ]);
    }
}
