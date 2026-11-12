<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Struk <?= esc($sale['invoice_number']) ?> - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/sales-receipt.css')) ?>">
</head>
<body>
<header><a href="<?= site_url('dashboard') ?>">Mira Plastik</a><span>Struk transaksi</span></header>
<main>
    <div class="toolbar">
        <a class="button secondary btn btn-secondary" href="<?= site_url('kasir') ?>">Kembali ke kasir</a>
        <?php if (in_array($sale['status'], ['POSTED', 'PARTIALLY_RETURNED'], true)): ?>
            <a class="button btn btn-secondary" href="<?= site_url('kasir/struk/' . $sale['sale_id'] . '/penyesuaian') ?>">Ajukan Void/Retur</a>
        <?php endif ?>
        <button class="btn btn-primary" id="print-receipt" type="button">Cetak struk</button>
    </div>
    <section class="panel card">
        <h1>Mira Plastik</h1>
        <p class="muted">Bukti transaksi penjualan</p>
        <div class="meta">
            <div><strong>Faktur:</strong> <?= esc($sale['invoice_number']) ?></div>
            <div><strong>Waktu:</strong> <?= esc($sale['sold_at']) ?></div>
            <div><strong>Kasir:</strong> <?= esc($sale['full_name']) ?></div>
            <div><strong>Jenis:</strong> <?= esc($sale['sale_type']) ?></div>
            <div><strong>Pembayaran:</strong> <?= esc($sale['payment_method']) ?><?= $sale['account_name'] ? ' · ' . esc($sale['account_name']) : '' ?></div>
            <?php if ($sale['payment_method'] === 'BON'): ?>
                <div><strong>Pelanggan:</strong> <?= esc($sale['customer_name']) ?></div>
                <div><strong>Jatuh tempo:</strong> <?= esc($sale['due_date']) ?></div>
                <div><strong>Status piutang:</strong> <?= esc($sale['receivable_status']) ?></div>
            <?php endif ?>
        </div>
        <div class="table-wrap">
                <table class="table align-middle">
                <thead><tr><th>Barang</th><th>Jumlah</th><th>Harga/unit</th><th class="amount">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= esc($item['product_code_snapshot']) ?> · <?= esc($item['product_name_snapshot']) ?><br><span class="muted"><?= esc($item['price_type']) ?> · Stok keluar <?= esc(format_quantity($item['stock_quantity_snapshot'], 6)) ?> <?= esc($item['unit_snapshot']) ?> dari <?= esc($item['stock_allocations'] ?? '-') ?></span></td>
                        <td><?= esc(format_quantity($item['quantity'], 3)) ?> <?= esc($item['sale_unit_snapshot']) ?></td>
                        <td>Rp <?= esc(number_format((float) $item['unit_price'], 2, ',', '.')) ?> / <?= esc($item['price_unit_snapshot']) ?></td>
                        <td class="amount">Rp <?= esc(number_format((float) $item['line_total'], 2, ',', '.')) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <p class="total">Total: Rp <?= esc(number_format((float) $sale['total_amount'], 2, ',', '.')) ?></p>
        <?php if ($sale['payment_method'] === 'BON'): ?>
            <p class="amount">Piutang awal: Rp <?= esc(number_format((float) $sale['receivable_amount'], 2, ',', '.')) ?></p>
            <p class="muted">Pembayaran dapat dicatat melalui menu piutang.</p>
        <?php else: ?>
            <p class="amount">Diterima: Rp <?= esc(number_format((float) $sale['amount_received'], 2, ',', '.')) ?></p>
            <p class="amount">Kembalian: Rp <?= esc(number_format((float) $sale['change_amount'], 2, ',', '.')) ?></p>
        <?php endif ?>
        <p class="muted">Terima kasih telah berbelanja.</p>
    </section>
</main>
</body>
</html>
