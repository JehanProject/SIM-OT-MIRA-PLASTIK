<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;

class Products extends BaseController
{
    public function index(): string
    {
        helper('form');
        helper('inventory');
        $db = db_connect();

        $builder = $db->table('barang')
            ->select('barang.*, kategori.category_name,
                satuan.unit_name AS unit_name, satuan.unit_symbol AS unit_symbol,
                satuan_eceran.unit_name AS retail_unit_name, satuan_eceran.unit_symbol AS retail_unit_symbol')
            ->join('kategori', 'kategori.category_id = barang.category_id')
            ->join('satuan', 'satuan.unit_id = barang.unit_id')
            ->join('satuan AS satuan_eceran', 'satuan_eceran.unit_id = barang.retail_unit_id')
            ->orderBy('barang.product_name');

        $search = trim((string) $this->request->getGet('q'));
        if ($search !== '') {
            $builder->groupStart()
                ->like('barang.product_code', $search)
                ->orLike('barang.product_name', $search)
                ->orLike('kategori.category_name', $search)
                ->groupEnd();
        }

        $status = (string) $this->request->getGet('status');
        if ($status !== 'all') {
            $builder->where('barang.is_active', true);
        }

        $products = $builder->get()->getResultArray();
        $productHistory = $db->query(
            'SELECT product_id FROM detail_penjualan
             UNION SELECT product_id FROM detail_penerimaan_barang
             UNION SELECT product_id FROM detail_stok_awal
             UNION SELECT product_id FROM detail_mutasi_stok
             UNION SELECT product_id FROM detail_penyesuaian_stok
             UNION SELECT product_id FROM pergerakan_persediaan
             UNION SELECT product_id FROM saldo_persediaan WHERE quantity <> 0',
        )->getResultArray();
        $productsWithHistory = array_fill_keys(
            array_map('intval', array_column($productHistory, 'product_id')),
            true,
        );
        $balances = $db->table('saldo_persediaan')
            ->select('saldo_persediaan.product_id, lokasi_persediaan.location_code, saldo_persediaan.quantity')
            ->join('lokasi_persediaan', 'lokasi_persediaan.location_id = saldo_persediaan.location_id')
            ->get()
            ->getResultArray();

        $stockByProduct = [];
        foreach ($balances as $balance) {
            $stockByProduct[$balance['product_id']][$balance['location_code']] = $balance['quantity'];
        }

        return view('products/index', [
            'products'      => $products,
            'productsWithHistory' => $productsWithHistory,
            'stockByProduct' => $stockByProduct,
            'categories'    => $db->table('kategori')->orderBy('category_name')->get()->getResultArray(),
            'units'         => $db->table('satuan')->orderBy('sale_type')->orderBy('unit_name')->get()->getResultArray(),
            'search'        => $search,
            'showInactive'  => $status === 'all',
            'roleCode'      => (string) session()->get('role_code'),
            'success'       => session()->getFlashdata('success'),
            'error'         => session()->getFlashdata('error'),
            'formErrors'    => session()->getFlashdata('form_errors') ?? [],
        ]);
    }

    public function new(): string|RedirectResponse
    {
        helper('form');
        return view('products/form', [
            'product'    => null,
            'categories' => db_connect()->table('kategori')->where('is_active', true)->orderBy('category_name')->get()->getResultArray(),
            'retailUnits'    => db_connect()->table('satuan')->where('sale_type', 'ECERAN')->orderBy('unit_name')->get()->getResultArray(),
            'wholesaleUnits' => db_connect()->table('satuan')->where('sale_type', 'GROSIR')->orderBy('unit_name')->get()->getResultArray(),
            'errors'     => session()->getFlashdata('form_errors') ?? [],
        ]);
    }

    public function create(): RedirectResponse
    {
        helper('form');
        $data = $this->validatedProductInput();
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $db = db_connect();
        if ($db->table('barang')->where('product_code', $data['product_code'])->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('form_errors', [
                'product_code' => 'Kode barang sudah digunakan.',
            ]);
        }

        if (! $this->validReferences($data)) {
            return redirect()->back()->withInput()->with('form_errors', [
                'unit_id' => 'Pilih kategori serta satuan eceran dan stok/grosir yang valid.',
            ]);
        }

        $db->transBegin();
        try {
            if (! $db->table('barang')->insert($data + ['is_active' => true])) {
                throw new RuntimeException('Gagal menyimpan barang.');
            }

            $productId = (int) $db->insertID();
            $locations = $db->table('lokasi_persediaan')->select('location_id')->get()->getResultArray();
            if ($locations === []) {
                throw new RuntimeException('Lokasi stok belum tersedia. Periksa data awal lokasi.');
            }

            foreach ($locations as $location) {
                if (! $db->table('saldo_persediaan')->insert([
                    'product_id'  => $productId,
                    'location_id' => $location['location_id'],
                    'quantity'    => 0,
                ])) {
                    throw new RuntimeException('Gagal membuat saldo awal stok barang.');
                }
            }

            $this->writeActivity($db, 'CREATE', 'BARANG', $productId, null, $data + ['is_active' => true]);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penyimpanan barang gagal.');
            }
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/barang')->with('success', 'Barang berhasil ditambahkan. Stok awal saat ini nol.');
    }

    public function edit(string $id): string|RedirectResponse
    {
        helper('form');
        $productId = $this->productId($id);
        if ($productId === null) {
            return redirect()->to('/barang')->with('error', 'Barang tidak ditemukan.');
        }

        $db      = db_connect();
        $product = $db->table('barang')->where('product_id', $productId)->get()->getRowArray();
        if ($product === null) {
            return redirect()->to('/barang')->with('error', 'Barang tidak ditemukan.');
        }

        return view('products/form', [
            'product'    => $product,
            'categories' => $db->table('kategori')->where('is_active', true)->orderBy('category_name')->get()->getResultArray(),
            'retailUnits'    => $db->table('satuan')->where('sale_type', 'ECERAN')->orderBy('unit_name')->get()->getResultArray(),
            'wholesaleUnits' => $db->table('satuan')->where('sale_type', 'GROSIR')->orderBy('unit_name')->get()->getResultArray(),
            'errors'     => session()->getFlashdata('form_errors') ?? [],
        ]);
    }

    public function update(string $id): RedirectResponse
    {
        helper('form');
        $productId = $this->productId($id);
        if ($productId === null) {
            return redirect()->to('/barang')->with('error', 'Barang tidak ditemukan.');
        }

        $db      = db_connect();
        $current = $db->table('barang')->where('product_id', $productId)->get()->getRowArray();
        if ($current === null) {
            return redirect()->to('/barang')->with('error', 'Barang tidak ditemukan.');
        }

        $data = $this->validatedProductInput($current['avg_purchase_cost']);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $duplicate = $db->table('barang')
            ->where('product_code', $data['product_code'])
            ->where('product_id !=', $productId)
            ->countAllResults();
        if ($duplicate > 0) {
            return redirect()->back()->withInput()->with('form_errors', [
                'product_code' => 'Kode barang sudah digunakan.',
            ]);
        }

        if (! $this->validReferences($data)) {
            return redirect()->back()->withInput()->with('form_errors', [
                'unit_id' => 'Pilih kategori serta satuan eceran dan stok/grosir yang valid.',
            ]);
        }

        $db->transBegin();
        try {
            if (! $db->table('barang')->where('product_id', $productId)->update($data)) {
                throw new RuntimeException('Gagal memperbarui barang.');
            }

            $this->writeActivity($db, 'UPDATE', 'BARANG', $productId, $current, $data);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pembaruan barang gagal.');
            }
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/barang')->with('success', 'Data barang berhasil diperbarui.');
    }

    public function deactivate(string $id): RedirectResponse
    {
        $productId = $this->productId($id);
        if ($productId === null) {
            return redirect()->to('/barang')->with('error', 'Barang tidak ditemukan.');
        }

        $db      = db_connect();
        $product = $db->table('barang')->where('product_id', $productId)->get()->getRowArray();
        if ($product === null) {
            return redirect()->to('/barang')->with('error', 'Barang tidak ditemukan.');
        }

        if ((bool) $product['is_active']) {
            $db->transBegin();
            try {
                if (! $db->table('barang')->where('product_id', $productId)->update(['is_active' => false])) {
                    throw new RuntimeException('Gagal menonaktifkan barang.');
                }
                $after = $product;
                $after['is_active'] = false;
                $this->writeActivity($db, 'DEACTIVATE', 'BARANG', $productId, $product, $after);
                if ($db->transStatus() === false) {
                    throw new RuntimeException('Transaksi penonaktifan barang gagal.');
                }
                $db->transCommit();
            } catch (\Throwable $exception) {
                $db->transRollback();
                throw $exception;
            }
        }

        return redirect()->to('/barang?status=all')->with('success', 'Barang dinonaktifkan tanpa menghapus riwayat atau stoknya.');
    }

    public function delete(string $id): RedirectResponse
    {
        $productId = $this->productId($id);
        if ($productId === null) {
            return redirect()->to('/barang?status=all')->with('error', 'Barang tidak ditemukan.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $product = $db->query(
                'SELECT * FROM barang WHERE product_id = ? FOR UPDATE',
                [$productId],
            )->getRowArray();
            if ($product === null) {
                throw new DomainException('Barang tidak ditemukan.');
            }

            foreach ([
                'detail_penjualan',
                'detail_penerimaan_barang',
                'detail_stok_awal',
                'detail_mutasi_stok',
                'detail_penyesuaian_stok',
                'pergerakan_persediaan',
            ] as $table) {
                if ($db->table($table)->where('product_id', $productId)->countAllResults() > 0) {
                    throw new DomainException('Barang yang sudah memiliki riwayat tidak dapat dihapus permanen. Gunakan Nonaktifkan.');
                }
            }

            if ($db->table('saldo_persediaan')
                ->where('product_id', $productId)
                ->where('quantity !=', 0)
                ->countAllResults() > 0) {
                throw new DomainException('Barang yang masih memiliki stok tidak dapat dihapus permanen.');
            }

            if (! $db->table('saldo_persediaan')->where('product_id', $productId)->delete()) {
                throw new RuntimeException('Gagal menghapus saldo kosong barang.');
            }

            $deleted = $db->table('barang')->where('product_id', $productId)->delete();
            if (! $deleted || $db->affectedRows() !== 1) {
                throw new RuntimeException('Gagal menghapus barang.');
            }

            $this->writeActivity($db, 'DELETE', 'BARANG', $productId, $product, null);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penghapusan barang gagal.');
            }
            $db->transCommit();
        } catch (DomainException $exception) {
            $db->transRollback();

            return redirect()->to('/barang?status=all')->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/barang?status=all')->with('success', 'Barang yang belum memiliki riwayat berhasil dihapus permanen.');
    }

    public function createCategory(): RedirectResponse
    {
        helper('form');
        $name = trim((string) $this->request->getPost('category_name'));
        $validation = service('validation');
        $validation->setRules([
            'category_name' => 'required|min_length[2]|max_length[100]',
        ]);
        if (! $validation->run(['category_name' => $name])) {
            return redirect()->to('/barang')->withInput()->with('form_errors', $validation->getErrors());
        }

        $db = db_connect();
        if ($db->table('kategori')->where('category_name', $name)->countAllResults() > 0) {
            return redirect()->to('/barang')->withInput()->with('error', 'Nama kategori sudah ada.');
        }
        $db->transBegin();
        try {
            if (! $db->table('kategori')->insert(['category_name' => $name, 'is_active' => true])) {
                throw new RuntimeException('Gagal menyimpan kategori.');
            }

            $this->writeActivity($db, 'CREATE', 'KATEGORI', (int) $db->insertID(), null, ['category_name' => $name]);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penyimpanan kategori gagal.');
            }
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/barang')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function deactivateCategory(string $id): RedirectResponse
    {
        $categoryId = $this->productId($id);
        if ($categoryId === null) {
            return redirect()->to('/barang')->with('error', 'Kategori tidak ditemukan.');
        }

        $db       = db_connect();
        $category = $db->table('kategori')->where('category_id', $categoryId)->get()->getRowArray();
        if ($category === null) {
            return redirect()->to('/barang')->with('error', 'Kategori tidak ditemukan.');
        }
        if ((bool) $category['is_active']) {
            $db->transBegin();
            try {
                $updated = $db->table('kategori')->where('category_id', $categoryId)->update(['is_active' => false]);
                if (! $updated && $db->affectedRows() === 0) {
                    throw new RuntimeException('Gagal menonaktifkan kategori.');
                }
                $after = $category;
                $after['is_active'] = false;
                $this->writeActivity($db, 'DEACTIVATE', 'KATEGORI', $categoryId, $category, $after);
                if ($db->transStatus() === false) {
                    throw new RuntimeException('Transaksi penonaktifan kategori gagal.');
                }
                $db->transCommit();
            } catch (\Throwable $exception) {
                $db->transRollback();
                throw $exception;
            }
        }

        return redirect()->to('/barang')->with('success', 'Kategori dinonaktifkan dan tetap tersimpan untuk riwayat barang.');
    }

    public function createUnit(): RedirectResponse
    {
        $data = [
            'unit_name'   => trim((string) $this->request->getPost('unit_name')),
            'unit_symbol' => trim((string) $this->request->getPost('unit_symbol')),
            'sale_type'   => (string) $this->request->getPost('sale_type'),
        ];
        $validation = service('validation');
        $validation->setRules([
            'unit_name'   => 'required|min_length[1]|max_length[100]',
            'unit_symbol' => 'required|min_length[1]|max_length[30]',
            'sale_type'   => 'required|in_list[ECERAN,GROSIR]',
        ]);
        if (! $validation->run($data)) {
            return redirect()->to('/barang')->withInput()->with('form_errors', $validation->getErrors());
        }

        $db = db_connect();
        $duplicate = $db->table('satuan')
            ->groupStart()
            ->where('unit_name', $data['unit_name'])
            ->orWhere('unit_symbol', $data['unit_symbol'])
            ->groupEnd()
            ->countAllResults();
        if ($duplicate > 0) {
            return redirect()->to('/barang')->withInput()->with('error', 'Nama atau simbol satuan sudah digunakan.');
        }
        $db->transBegin();
        try {
            if (! $db->table('satuan')->insert($data)) {
                throw new RuntimeException('Gagal menyimpan satuan.');
            }
            $this->writeActivity($db, 'CREATE', 'SATUAN', (int) $db->insertID(), null, $data);
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penyimpanan satuan gagal.');
            }
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return redirect()->to('/barang')->with('success', 'Satuan berhasil ditambahkan.');
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    private function validatedProductInput(?string $existingPurchaseCost = null): array|RedirectResponse
    {
        $data = [
            'product_code'     => strtoupper(trim((string) $this->request->getPost('product_code'))),
            'product_name'     => trim((string) $this->request->getPost('product_name')),
            'category_id'      => (string) $this->request->getPost('category_id'),
            'unit_id'          => (string) $this->request->getPost('unit_id'),
            'retail_unit_id'   => (string) $this->request->getPost('retail_unit_id'),
            'retail_units_per_stock_unit' => trim((string) $this->request->getPost('retail_units_per_stock_unit')),
            'avg_purchase_cost' => $existingPurchaseCost ?? trim((string) $this->request->getPost('avg_purchase_cost')),
            'retail_price'     => trim((string) $this->request->getPost('retail_price')),
            'wholesale_price'  => trim((string) $this->request->getPost('wholesale_price')),
            'wholesale_min_qty' => trim((string) $this->request->getPost('wholesale_min_qty')),
            'min_stock'        => trim((string) $this->request->getPost('min_stock')),
        ];

        $validation = service('validation');
        $validation->setRules([
            'product_code'      => 'required|min_length[1]|max_length[80]|alpha_numeric_punct',
            'product_name'      => 'required|min_length[2]|max_length[200]',
            'category_id'       => 'required|is_natural_no_zero',
            'unit_id'           => 'required|is_natural_no_zero',
            'retail_unit_id'    => 'required|is_natural_no_zero',
            'retail_units_per_stock_unit' => 'required|decimal|greater_than_equal_to[1]|less_than_equal_to[1000000]',
            'retail_price'      => 'required|decimal|greater_than_equal_to[0]',
            'wholesale_price'   => 'required|decimal|greater_than_equal_to[0]',
            'wholesale_min_qty' => 'required|decimal|greater_than[0]',
            'min_stock'         => 'required|decimal|greater_than_equal_to[0]',
        ]);
        if ($existingPurchaseCost === null) {
            $validation->setRule('avg_purchase_cost', 'Harga beli rata-rata', 'required|decimal|greater_than_equal_to[0]');
        }
        if (! $validation->run($data)) {
            return redirect()->back()->withInput()->with('form_errors', $validation->getErrors());
        }

        $data['category_id'] = (int) $data['category_id'];
        $data['unit_id'] = (int) $data['unit_id'];
        $data['retail_unit_id'] = (int) $data['retail_unit_id'];

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validReferences(array $data): bool
    {
        $db = db_connect();

        return $db->table('kategori')
                ->where('category_id', $data['category_id'])
                ->where('is_active', true)
                ->countAllResults() === 1
            && $db->table('satuan')
                ->where('unit_id', $data['unit_id'])
                ->where('sale_type', 'GROSIR')
                ->countAllResults() === 1
            && $db->table('satuan')
                ->where('unit_id', $data['retail_unit_id'])
                ->where('sale_type', 'ECERAN')
                ->countAllResults() === 1;
    }

    private function productId(string $id): ?int
    {
        return ctype_digit($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function writeActivity(BaseConnection $db, string $action, string $entity, int $entityId, ?array $before, ?array $after): void
    {
        $inserted = $db->table('log_aktivitas')->insert([
            'user_id'     => session()->get('user_id'),
            'action'      => $action,
            'entity_type' => $entity,
            'entity_id'   => (string) $entityId,
            'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_data'  => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        ]);

        if (! $inserted) {
            throw new RuntimeException('Gagal mencatat aktivitas ke log.');
        }
    }
}
