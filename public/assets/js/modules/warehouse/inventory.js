export function createWarehouseInventoryModule(context) {
    const { data, escapeHtml, pageHeading, formatRupiah, dateTime, activeProducts } = context;

    function inventoryMovementPage() {
        const history = [...data.stockMovements].reverse().slice(0, 25);
        const receipts = [...data.receipts].reverse().slice(0, 10);
        return `${pageHeading('Barang Masuk & Mutasi', 'Catat penerimaan dari distributor, mutasi ke toko, dan penyesuaian stok.')}
            <div class="inventory-forms-grid">
                <article class="panel inventory-form-panel">
                    <div class="panel-heading"><div><h2>Barang Masuk</h2><p>Stok gudang dan harga beli rata-rata diperbarui saat disimpan.</p></div></div>
                    <form id="receipt-form">
                        <div class="form-grid">
                            <div class="field field-full"><label for="receipt-product">Barang</label><select id="receipt-product" name="productId" required>${activeProducts().map(product => `<option value="${escapeHtml(product.id)}">${escapeHtml(product.code)} — ${escapeHtml(product.name)}</option>`).join('')}</select></div>
                            <div class="field"><label for="receipt-distributor">Distributor</label><input id="receipt-distributor" name="distributor" list="distributor-list" maxlength="100" placeholder="Nama distributor" required><datalist id="distributor-list">${data.distributors.map(distributor => `<option value="${escapeHtml(distributor.name)}">`).join('')}</datalist></div>
                            <div class="field"><label for="receipt-invoice">Nomor faktur</label><input id="receipt-invoice" name="invoice" maxlength="50" required></div>
                            <div class="field"><label for="receipt-quantity">Jumlah diterima</label><input id="receipt-quantity" name="quantity" type="number" min="1" step="1" required></div>
                            <div class="field"><label for="receipt-price">Harga beli per unit (Rp)</label><input id="receipt-price" name="price" type="number" min="0" step="1" required></div>
                            <div class="field field-full"><label for="receipt-date">Tanggal penerimaan</label><input id="receipt-date" name="date" type="date" value="${new Date().toISOString().slice(0, 10)}" required></div>
                        </div>
                        <div class="form-actions"><button class="button" type="submit">Simpan Barang Masuk</button></div>
                    </form>
                    <div class="receipt-history"><h3>Penerimaan Terakhir</h3>${receipts.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Tanggal / Faktur</th><th>Barang</th><th>Distributor</th><th>Jumlah</th><th>Total</th></tr></thead><tbody>${receipts.map(receipt => `<tr><td>${escapeHtml(receipt.date)}<span class="subtext">${escapeHtml(receipt.invoice)}</span></td><td>${escapeHtml(receipt.productName)}</td><td>${escapeHtml(receipt.distributor)}</td><td>${Number(receipt.quantity)} ${escapeHtml(receipt.unit)}</td><td>${formatRupiah(receipt.total)}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada penerimaan barang.</div>'}</div>
                </article>
                <article class="panel inventory-form-panel">
                    <div class="panel-heading"><div><h2>Mutasi & Penyesuaian</h2><p>Perubahan stok tercatat lengkap pada riwayat pergerakan.</p></div></div>
                    <form id="movement-form">
                        <div class="form-grid">
                            <div class="field field-full"><label for="movement-product">Barang</label><select id="movement-product" name="productId" required>${activeProducts().map(product => `<option value="${escapeHtml(product.id)}">${escapeHtml(product.code)} — ${escapeHtml(product.name)}</option>`).join('')}</select></div>
                            <div class="field"><label for="movement-type">Jenis proses</label><select id="movement-type" name="type"><option value="Mutasi">Mutasi gudang ke toko</option><option value="Penyesuaian">Penyesuaian stok fisik</option></select></div>
                            <div class="field movement-quantity-field"><label for="movement-quantity">Jumlah dimutasi</label><input id="movement-quantity" name="quantity" type="number" min="1" step="1" required></div>
                            <div class="field movement-location-field hidden"><label for="movement-location">Lokasi stok opname</label><select id="movement-location" name="location"><option value="Toko">Toko</option><option value="Gudang">Gudang</option></select></div>
                            <div class="field movement-count-field hidden"><label for="movement-count">Jumlah fisik hasil opname</label><input id="movement-count" name="physicalCount" type="number" min="0" step="1"></div>
                            <div class="field field-full"><label for="movement-reason">Alasan / keterangan</label><input id="movement-reason" name="reason" maxlength="180" required placeholder="Contoh: isi ulang rak, barang rusak, atau hasil stock opname"></div>
                        </div>
                        <div class="form-actions"><button class="button" type="submit">Simpan Pergerakan</button></div>
                    </form>
                </article>
            </div>
            <article class="panel movement-history-panel"><div class="panel-heading"><div><h2>Riwayat Pergerakan Stok</h2><p>Mutasi, penerimaan barang, dan penyesuaian terbaru.</p></div><span class="muted">${history.length} catatan terbaru</span></div>
                ${history.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Tanggal & Waktu</th><th>Barang</th><th>Jenis</th><th>Lokasi</th><th>Perubahan</th><th>Nilai Selisih</th><th>Alasan</th><th>Petugas</th></tr></thead><tbody>${history.map(item => `<tr>
                    <td>${escapeHtml(dateTime(item.createdAt))}</td><td class="product-name">${escapeHtml(item.productName)}<span class="subtext">${escapeHtml(item.productCode)}</span></td><td>${escapeHtml(item.type)}</td><td>${escapeHtml(item.location || 'Gudang / Toko')}</td>
                    <td class="${Number(item.delta) < 0 ? 'inventory-low-stock' : ''}">${Number(item.delta) > 0 ? '+' : ''}${Number(item.delta)} ${escapeHtml(item.unit)}</td><td>${item.loss ? formatRupiah(item.loss) : '—'}</td><td>${escapeHtml(item.reason)}</td><td>${escapeHtml(item.userName)}</td>
                </tr>`).join('')}</tbody></table></div>` : '<div class="empty">Riwayat pergerakan akan muncul setelah stok dicatat.</div>'}
            </article>`;
    }

    return { inventoryMovementPage };
}
