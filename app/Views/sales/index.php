<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Transaksi Penjualan - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/sales-index.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<?php
$defaultAccountId = (string) old('cash_account_id', (string) ($accounts[0]['cash_account_id'] ?? ''));
$currentPaymentMethod = in_array($oldPaymentMethod, ['TUNAI', 'TRANSFER', 'BON'], true) ? $oldPaymentMethod : 'TUNAI';
$currentSaleType = in_array($oldSaleType, ['ECERAN', 'GROSIR'], true) ? $oldSaleType : 'ECERAN';
?>
<main class="sales-page">
    <div class="sales-heading">
        <div>
            <p class="sales-eyebrow">PENJUALAN</p>
            <h1>Transaksi Baru</h1>
            <p class="muted">Pilih barang, atur jumlah, lalu proses pembayaran.</p>
        </div>
        <div class="sale-type-switch" role="group" aria-label="Jenis penjualan">
            <button class="sale-type-button <?= $currentSaleType === 'ECERAN' ? 'is-active' : '' ?>" type="button" data-sale-type="ECERAN" aria-pressed="<?= $currentSaleType === 'ECERAN' ? 'true' : 'false' ?>">Eceran</button>
            <button class="sale-type-button <?= $currentSaleType === 'GROSIR' ? 'is-active' : '' ?>" type="button" data-sale-type="GROSIR" aria-pressed="<?= $currentSaleType === 'GROSIR' ? 'true' : 'false' ?>">Grosir</button>
        </div>
    </div>

    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <?php if ($products === []): ?><p class="alert alert-warning" role="alert">Belum ada barang aktif. Tambahkan barang terlebih dahulu.</p><?php endif ?>
    <?php if ($accounts === []): ?><p class="alert alert-danger" role="alert">Akun kas/bank aktif belum tersedia.</p><?php endif ?>

    <form method="post" action="<?= site_url('kasir') ?>" id="sale-form"
        data-has-credit-policy="<?= $creditPolicy === null ? 'false' : 'true' ?>"
        data-has-customers="<?= $customers === [] ? 'false' : 'true' ?>"
        data-old-cart="<?= esc(json_encode($oldCart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), 'attr') ?>">
        <?= csrf_field() ?>
        <input type="hidden" id="sale-type" name="sale_type" value="<?= esc($currentSaleType) ?>">

        <div class="checkout-layout" id="checkout-layout">
            <section class="catalog-panel" aria-labelledby="catalog-title">
                <div class="catalog-search-wrap">
                    <label class="visually-hidden" for="product-search">Cari nama atau kode barang</label>
                    <span class="search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                    </span>
                    <input class="form-control" id="product-search" type="search" autocomplete="off" placeholder="Cari nama atau kode barang...">
                    <kbd aria-hidden="true">/</kbd>
                </div>
                <div class="catalog-heading">
                    <div><h2 id="catalog-title">Barang tersedia</h2><p class="muted">Pilih barang untuk menambahkannya ke keranjang.</p></div>
                    <span class="catalog-count"><?= count($products) ?> barang</span>
                </div>
                <select id="product-select" class="product-data-source" aria-hidden="true" tabindex="-1">
                    <?php foreach ($products as $product): ?>
                        <option value="<?= esc($product['product_id']) ?>"
                            data-code="<?= esc($product['product_code']) ?>"
                            data-name="<?= esc($product['product_name']) ?>"
                            data-stock-unit="<?= esc($product['unit_symbol']) ?>"
                            data-retail-unit="<?= esc($product['retail_unit_symbol']) ?>"
                            data-conversion="<?= esc((string) ($product['retail_units_per_stock_unit'] ?? '')) ?>"
                            data-retail="<?= esc($product['retail_price']) ?>"
                            data-wholesale="<?= esc($product['wholesale_price']) ?>"
                            data-minimum="<?= esc($product['wholesale_min_qty']) ?>"
                            data-store-stock="<?= esc((string) $product['store_stock']) ?>"
                            data-warehouse-stock="<?= esc((string) $product['warehouse_stock']) ?>"
                            data-min-stock="<?= esc($product['min_stock']) ?>">
                            <?= esc($product['product_code'] . ' · ' . $product['product_name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <div class="product-grid" id="product-cards">
                    <?php foreach ($products as $product): ?>
                        <article class="product-card" data-product-card data-product-id="<?= esc($product['product_id']) ?>" data-search="<?= esc(mb_strtolower($product['product_code'] . ' ' . $product['product_name'])) ?>">
                            <div class="product-card-top">
                                <span class="product-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4v10l-8 4-8-4z"/><path d="m4 7 8 4 8-4M12 11v10M8 5l8 4"/></svg>
                                </span>
                                <span class="product-code"><?= esc($product['product_code']) ?></span>
                                <button class="product-add" type="button" data-add-product="<?= esc($product['product_id']) ?>" aria-label="Tambah <?= esc($product['product_name']) ?> ke keranjang">+</button>
                            </div>
                            <h3><?= esc($product['product_name']) ?></h3>
                            <p class="product-unit">Satuan stok: <?= esc($product['unit_symbol']) ?></p>
                            <div class="product-prices">
                                <span>Eceran<strong>Rp <?= esc(number_format((float) $product['retail_price'], 0, ',', '.')) ?></strong></span>
                                <span>Grosir<strong>Rp <?= esc(number_format((float) $product['wholesale_price'], 0, ',', '.')) ?></strong></span>
                            </div>
                            <div class="product-stock">
                                <span class="<?= (float) $product['store_stock'] <= 0 ? 'stock-empty' : '' ?>">Toko <?= esc(format_quantity($product['store_stock'], 3)) ?></span>
                                <span>Gudang <?= esc(format_quantity($product['warehouse_stock'], 3)) ?></span>
                            </div>
                            <p class="product-minimum">Minimum grosir <?= esc(format_quantity($product['wholesale_min_qty'], 3)) ?> <?= esc($product['unit_symbol']) ?></p>
                        </article>
                    <?php endforeach ?>
                    <?php if ($products === []): ?><p class="empty catalog-empty">Belum ada barang aktif.</p><?php endif ?>
                </div>
                <p id="product-search-empty" class="empty catalog-empty" hidden>Barang tidak ditemukan.</p>
                <p id="selected-product-info" class="visually-hidden" aria-live="polite"></p>
                <button class="visually-hidden" type="button" id="add-product">Tambah barang dipilih</button>
            </section>

            <aside class="checkout-sidebar">
                <section class="cart-panel" aria-labelledby="cart-title">
                    <div class="cart-heading">
                        <div class="cart-heading-title">
                            <span class="cart-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M3 4h2l2.1 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 1.9-1.4L21 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                            </span>
                            <div><h2 id="cart-title">Keranjang</h2><p><span id="cart-count">0</span> jenis barang</p></div>
                        </div>
                        <button type="button" class="clear-cart" id="clear-cart" disabled>Kosongkan</button>
                    </div>
                    <div class="cart-items" id="cart-items" aria-live="polite">
                        <p class="empty cart-empty">Keranjang masih kosong.</p>
                    </div>

                    <section class="wholesale-customer-panel" id="wholesale-customer-panel" aria-labelledby="wholesale-customer-title" <?= $currentSaleType !== 'GROSIR' ? 'hidden' : '' ?>>
                        <div class="wholesale-customer-heading">
                            <div>
                                <h3 id="wholesale-customer-title">Pelanggan grosir</h3>
                                <p>Pilih pelanggan untuk transaksi Bon atau daftarkan pelanggan baru.</p>
                            </div>
                            <span class="customer-step" aria-hidden="true">01</span>
                        </div>
                        <label for="customer-id">Pilih pelanggan grosir</label>
                        <select class="form-select" id="customer-id" name="customer_id" disabled>
                            <option value="">Pilih pelanggan</option>
                            <?php foreach ($customers as $customer): ?>
                                <?php $availableCredit = max(0, (float) $customer['credit_limit'] - (float) $customer['outstanding_amount']); ?>
                                <option value="<?= esc($customer['customer_id']) ?>" data-available="<?= esc((string) $availableCredit) ?>" <?= $oldCustomerId === (string) $customer['customer_id'] ? 'selected' : '' ?>>
                                    <?= esc($customer['customer_code'] . ' · ' . $customer['customer_name']) ?> — sisa plafon Rp <?= esc(number_format($availableCredit, 0, ',', '.')) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <p class="customer-panel-hint">Pelanggan diperlukan untuk pembayaran Bon. Data pelanggan tunai/transfer tidak perlu dipilih.</p>
                        <button class="customer-add-button open-customer-dialog" id="add-bon-customer" type="button">
                            <span aria-hidden="true">+</span> Tambah pelanggan grosir baru
                        </button>
                    </section>

                    <section class="payment-section" aria-labelledby="payment-title">
                        <h3 id="payment-title">Metode pembayaran</h3>
                        <div class="payment-method-buttons" role="group" aria-label="Pilih metode pembayaran">
                            <button type="button" class="payment-method-button <?= $currentPaymentMethod === 'TUNAI' ? 'is-active' : '' ?>" data-payment-method="TUNAI" aria-pressed="<?= $currentPaymentMethod === 'TUNAI' ? 'true' : 'false' ?>">Tunai</button>
                            <button type="button" class="payment-method-button <?= $currentPaymentMethod === 'TRANSFER' ? 'is-active' : '' ?>" data-payment-method="TRANSFER" aria-pressed="<?= $currentPaymentMethod === 'TRANSFER' ? 'true' : 'false' ?>">Transfer</button>
                            <button type="button" class="payment-method-button <?= $currentPaymentMethod === 'BON' ? 'is-active' : '' ?>" data-payment-method="BON" aria-pressed="<?= $currentPaymentMethod === 'BON' ? 'true' : 'false' ?>" <?= $currentSaleType !== 'GROSIR' ? 'hidden' : '' ?>>Bon</button>
                        </div>
                        <select class="form-select payment-data-source" id="payment-method" name="payment_method" required aria-hidden="true" tabindex="-1">
                            <option value="TUNAI" <?= $currentPaymentMethod === 'TUNAI' ? 'selected' : '' ?>>Tunai</option>
                            <option value="TRANSFER" <?= $currentPaymentMethod === 'TRANSFER' ? 'selected' : '' ?>>Transfer</option>
                            <option value="BON" <?= $currentPaymentMethod === 'BON' ? 'selected' : '' ?>>Bon</option>
                        </select>

                        <div class="payment-fields">
                            <div id="account-field">
                                <label for="cash-account">Akun kas/bank</label>
                                <select class="form-select" id="cash-account" name="cash_account_id" required <?= $accounts === [] ? 'disabled' : '' ?>>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= esc($account['cash_account_id']) ?>" data-type="<?= esc($account['account_type']) ?>" <?= $defaultAccountId === (string) $account['cash_account_id'] ? 'selected' : '' ?>>
                                            <?= esc($account['account_name']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div id="due-date-field" hidden>
                                <p id="credit-hint" class="credit-hint" aria-live="polite">
                                    <?php if ($creditPolicy === null): ?>
                                        Kebijakan tenor Bon belum tersedia. Atur batas tenor toko agar Bon dapat diproses.
                                    <?php else: ?>
                                        Maksimum <?= esc($creditPolicy['max_tenor_days']) ?> hari, paling lambat <?= esc($maximumDueDate) ?>.
                                    <?php endif ?>
                                </p>
                                <button class="link-button open-policy-dialog" type="button">
                                    Atur batas tenor toko (<?= esc($roleCode === 'KASIR' ? 'Kasir' : 'Owner') ?>)
                                </button>
                                <label for="due-date">Tanggal jatuh tempo transaksi ini</label>
                                <input class="form-control" id="due-date" name="due_date" type="date" min="<?= esc(date('Y-m-d')) ?>" <?= $maximumDueDate === null ? '' : 'max="' . esc($maximumDueDate) . '"' ?> value="<?= esc($oldDueDate) ?>" disabled>
                            </div>
                            <div id="cash-received-field">
                                <label for="amount-received">Uang diterima</label>
                                <input class="form-control" id="amount-received" name="amount_received" type="number" min="0" step="0.01" value="<?= esc((string) old('amount_received', '')) ?>" placeholder="Masukkan jumlah uang">
                                <p class="change-row"><span>Kembalian</span><strong id="change-amount">Rp 0</strong></p>
                            </div>
                        </div>
                        <div id="bon-setup-guidance" <?= $currentSaleType !== 'GROSIR' || ($creditPolicy !== null && $customers !== []) ? 'hidden' : '' ?>>
                            <p class="credit-hint" role="status">
                                Bon grosir memerlukan kebijakan tenor aktif dan minimal satu pelanggan aktif.
                                <?php if ($customers === []): ?>Tambahkan pelanggan melalui bagian Pelanggan Grosir pada kartu keranjang.<?php endif ?>
                                <?php if ($creditPolicy === null): ?><button class="link-button open-policy-dialog" type="button">Atur kebijakan tenor</button><?php endif ?>
                            </p>
                        </div>
                    </section>

                    <div class="cart-total-row">
                        <div><span>Total belanja</span><small id="cart-item-count">0 barang</small></div>
                        <strong id="cart-total">Rp 0</strong>
                    </div>
                    <button class="checkout-button" type="submit" id="checkout-button" <?= $products === [] ? 'disabled' : '' ?>>
                        Proses Pembayaran <span aria-hidden="true">→</span>
                    </button>
                    <?php if ($roleCode === 'OWNER'): ?>
                        <p id="owner-credit-links" class="owner-credit-links" <?= $currentSaleType !== 'GROSIR' ? 'hidden' : '' ?>>
                            <a href="<?= site_url('piutang/pelanggan') ?>">Atur pelanggan</a> · <a href="<?= site_url('piutang/kebijakan') ?>">Atur tenor Bon</a>
                        </p>
                    <?php endif ?>
                    <p class="receivable-link"><a href="<?= site_url('piutang') ?>">Lihat piutang dan catat cicilan</a></p>
                </section>
            </aside>
        </div>
    </form>

    <dialog class="customer-dialog" id="customer-dialog" data-open-on-load="<?= $customerFormOpen ? 'true' : 'false' ?>" aria-labelledby="customer-dialog-title">
        <form method="post" action="<?= site_url('piutang/pelanggan') ?>" id="customer-create-form">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="kasir">
            <input type="hidden" name="sale_type" value="GROSIR">
            <input type="hidden" name="payment_method" id="customer-payment-method" value="<?= esc($currentPaymentMethod) ?>">
            <input type="hidden" name="due_date" id="customer-due-date" value="<?= esc($oldDueDate) ?>">
            <div id="customer-cart-fields" hidden></div>
            <div class="customer-dialog-header">
                <div><h2 id="customer-dialog-title">Tambah pelanggan grosir</h2><p class="muted">Isi kode, nama, dan plafon bon awal. Setelah disimpan, pelanggan otomatis dipilih untuk transaksi ini.</p></div>
                <button class="dialog-close" type="button" aria-label="Tutup">×</button>
            </div>
            <?php foreach ($customerFormErrors as $message): ?><p class="alert alert-danger" role="alert"><?= esc($message) ?></p><?php endforeach ?>
            <div class="customer-dialog-fields">
                <div><label for="cashier-customer-code">Kode pelanggan</label><input class="form-control" id="cashier-customer-code" name="customer_code" maxlength="80" value="<?= esc((string) old('customer_code', '')) ?>" required></div>
                <div><label for="cashier-customer-name">Nama pelanggan</label><input class="form-control" id="cashier-customer-name" name="customer_name" maxlength="150" value="<?= esc((string) old('customer_name', '')) ?>" required></div>
                <div><label for="cashier-business-name">Nama usaha</label><input class="form-control" id="cashier-business-name" name="business_name" maxlength="150" value="<?= esc((string) old('business_name', '')) ?>"></div>
                <div><label for="cashier-phone">Nomor telepon</label><input class="form-control" id="cashier-phone" name="phone" maxlength="40" value="<?= esc((string) old('phone', '')) ?>"></div>
                <div><label for="cashier-credit-limit">Plafon bon awal (Rp)</label><input class="form-control" id="cashier-credit-limit" name="credit_limit" type="number" min="0" step="0.01" value="<?= esc((string) old('credit_limit', '0')) ?>" required></div>
                <div><label for="cashier-address">Alamat</label><textarea class="form-control" id="cashier-address" name="address" rows="2"><?= esc((string) old('address', '')) ?></textarea></div>
            </div>
            <div class="customer-dialog-actions">
                <button class="btn btn-primary" type="submit">Simpan dan pilih pelanggan</button>
                <button class="btn btn-secondary dialog-cancel" type="button">Batal</button>
            </div>
        </form>
    </dialog>

    <dialog class="customer-dialog" id="credit-policy-dialog" data-open-on-load="<?= $policyFormOpen ? 'true' : 'false' ?>" aria-labelledby="credit-policy-dialog-title">
        <form method="post" action="<?= site_url('piutang/kebijakan') ?>" id="credit-policy-form">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="kasir">
            <input type="hidden" name="customer_id" id="policy-customer-id" value="<?= esc($oldCustomerId) ?>">
            <input type="hidden" name="due_date" id="policy-due-date" value="<?= esc($oldDueDate) ?>">
            <div id="policy-cart-fields" hidden></div>
            <div class="customer-dialog-header">
                <div>
                    <h2 id="credit-policy-dialog-title">Atur tenor Bon grosir</h2>
                    <p class="muted">Pengaturan ini mengubah batas maksimum tenor untuk seluruh toko, bukan hanya transaksi ini.</p>
                </div>
                <button class="dialog-close" type="button" aria-label="Tutup">×</button>
            </div>
            <?php if ($policyFormOpen && $error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
            <div class="customer-dialog-fields">
                <div>
                    <label for="cashier-max-tenor">Batas tenor maksimum (hari)</label>
                    <input class="form-control" id="cashier-max-tenor" name="max_tenor_days" type="number" min="1" max="3650" step="1" value="<?= esc((string) old('max_tenor_days', (string) ($creditPolicy['max_tenor_days'] ?? ''))) ?>" required>
                </div>
            </div>
            <p class="muted">Tanggal jatuh tempo transaksi Bon tetap dipilih tersendiri dan harus berada dalam batas tenor yang berlaku. Perubahan berlaku mulai hari ini dan dicatat atas akun yang menyimpan.</p>
            <div class="customer-dialog-actions">
                <button class="btn btn-primary" type="submit">Simpan batas tenor toko</button>
                <button class="btn btn-secondary dialog-cancel" type="button">Batal</button>
            </div>
        </form>
    </dialog>

    <section class="recent-sales-panel" aria-labelledby="recent-sales-title">
        <div class="recent-sales-heading"><div><h2 id="recent-sales-title">Transaksi terbaru</h2><p class="muted">Transaksi penjualan yang baru diproses.</p></div><a href="<?= site_url('transaksi') ?>">Lihat semua</a></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Faktur</th><th>Waktu</th><th>Jenis</th><th>Pembayaran</th><th>Total</th><th>Kasir</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td><strong><?= esc($sale['invoice_number']) ?></strong></td>
                        <td><?= esc($sale['sold_at']) ?></td>
                        <td><?= esc($sale['sale_type']) ?></td>
                        <td><?= esc($sale['payment_method']) ?></td>
                        <td>Rp <?= esc(number_format((float) $sale['total_amount'], 2, ',', '.')) ?></td>
                        <td><?= esc($sale['full_name']) ?></td>
                        <td><a href="<?= site_url('kasir/struk/' . $sale['sale_id']) ?>">Lihat struk</a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($recentSales === []): ?><tr><td colspan="7" class="empty">Belum ada transaksi.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</div>
<script src="<?= esc(base_url('assets/js/pages/sales.js')) ?>" defer></script>
</body>
</html>
