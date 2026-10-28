export function createSharedInventoryModule(context) {
    const { data, escapeHtml, pageHeading, formatRupiah, todayKey, dateAfter, dateTime, saleDate, returnsForSale, returnedQuantity, returnedAmount, netSaleRevenue, netSaleCost, netSaleProfit, inReportRange, canManageSales, canManageProducts, activeProducts, cashMovements, periodForm, downloadCsv, uiState, posCarts } = context;

    function stockPage(user) {
        const query = uiState.productQuery.trim().toLowerCase();
        const products = activeProducts().filter(product =>
            `${product.code} ${product.name} ${product.category}`.toLowerCase().includes(query),
        );
        const canManage = canManageProducts(user);
        const lowCount = activeProducts().filter(product => Number(product.shop) <= Number(product.min)).length;
        const totalShop = activeProducts().reduce((sum, product) => sum + Number(product.shop), 0);
        const totalWarehouse = activeProducts().reduce((sum, product) => sum + Number(product.warehouse), 0);
        const fields = uiState.productFormOpen ? (() => {
            const product = data.products.find(item => item.id === uiState.editingProductId);
            const value = name => escapeHtml(product?.[name] ?? '');
            return `<form class="inline-form inventory-product-form" id="product-form">
                <h3>${product ? 'Ubah Data Barang' : 'Tambah Barang Baru'}</h3>
                <input type="hidden" name="id" value="${escapeHtml(product?.id || '')}">
                <div class="form-grid">
                    <div class="field"><label for="product-code">Kode barang</label><input id="product-code" name="code" value="${value('code')}" required maxlength="32"></div>
                    <div class="field"><label for="product-name">Nama barang</label><input id="product-name" name="name" value="${value('name')}" required maxlength="100"></div>
                    <div class="field"><label for="product-category">Kategori</label><input id="product-category" name="category" value="${value('category')}" required maxlength="60"></div>
                    <div class="field"><label for="product-unit">Satuan</label><input id="product-unit" name="unit" value="${value('unit') || 'pcs'}" required maxlength="20"></div>
                    <div class="field"><label for="product-buy">Harga beli rata-rata (Rp)</label><input id="product-buy" name="buy" type="number" min="0" step="1" value="${value('buy')}" required></div>
                    <div class="field"><label for="product-retail">Harga eceran (Rp)</label><input id="product-retail" name="retail" type="number" min="0" step="1" value="${value('retail')}" required></div>
                    <div class="field"><label for="product-wholesale">Harga grosir (Rp)</label><input id="product-wholesale" name="wholesale" type="number" min="0" step="1" value="${value('wholesale')}" required></div>
                    <div class="field"><label for="product-wholesale-min">Minimum jumlah grosir</label><input id="product-wholesale-min" name="wholesaleMin" type="number" min="1" step="1" value="${value('wholesaleMin') || '1'}" required></div>
                    <div class="field"><label for="product-shop">Stok toko${product ? ' (atur via mutasi)' : ''}</label><input id="product-shop" name="shop" type="number" min="0" step="1" value="${value('shop') || '0'}" ${product ? 'readonly' : ''} required></div>
                    <div class="field"><label for="product-warehouse">Stok gudang${product ? ' (atur via barang masuk)' : ''}</label><input id="product-warehouse" name="warehouse" type="number" min="0" step="1" value="${value('warehouse') || '0'}" ${product ? 'readonly' : ''} required></div>
                    <div class="field"><label for="product-min">Batas minimum stok toko</label><input id="product-min" name="min" type="number" min="0" step="1" value="${value('min') || '0'}" required></div>
                </div>
                <div class="form-actions"><button class="button button-quiet" type="button" data-action="cancel-product">Batal</button><button class="button" type="submit">Simpan Barang</button></div>
            </form>`;
        })() : '';

        return `${pageHeading('Stok Barang', 'Pantau ketersediaan barang di toko dan gudang secara real-time.', canManage ? '<button class="button" data-action="new-product">＋ Tambah Barang</button>' : '')}
            <div class="grid stats-grid inventory-stats">
                <article class="stat-card"><div class="stat-label">Jumlah jenis barang</div><div class="stat-value">${activeProducts().length}</div><div class="stat-foot">SKU aktif</div></article>
                <article class="stat-card"><div class="stat-label">Stok di toko</div><div class="stat-value">${new Intl.NumberFormat('id-ID').format(totalShop)}</div><div class="stat-foot">unit di area penjualan</div></article>
                <article class="stat-card"><div class="stat-label">Stok di gudang</div><div class="stat-value">${new Intl.NumberFormat('id-ID').format(totalWarehouse)}</div><div class="stat-foot">unit tersedia di gudang</div></article>
                <article class="stat-card"><div class="stat-label">Perlu restock</div><div class="stat-value ${lowCount ? 'inventory-warning-value' : ''}">${lowCount}</div><div class="stat-foot">stok toko minimum atau habis</div></article>
            </div>
            ${fields}
            <article class="panel inventory-table-panel">
                <div class="toolbar inventory-toolbar">
                    <input class="control search" id="product-search" type="search" placeholder="Cari nama, kode, atau kategori barang..." value="${escapeHtml(uiState.productQuery)}">
                    <span class="muted inventory-result-count">${products.length} dari ${activeProducts().length} barang</span>
                </div>
                <div class="table-wrap"><table class="inventory-table"><thead><tr>
                    <th>Kode</th><th>Nama barang</th><th>Kategori</th><th>Harga beli</th><th>Eceran</th><th>Grosir</th><th>Stok toko</th><th>Stok gudang</th><th>Min.</th><th>Status</th>${canManage ? '<th>Aksi</th>' : ''}
                </tr></thead><tbody>${products.length ? products.map(product => {
            const low = Number(product.shop) <= Number(product.min);
            return `<tr>
                        <td class="product-code">${escapeHtml(product.code)}</td>
                        <td class="product-name">${escapeHtml(product.name)}<span class="subtext">per ${escapeHtml(product.unit)}</span></td>
                        <td>${escapeHtml(product.category)}</td>
                        <td>${formatRupiah(product.buy)}</td><td>${formatRupiah(product.retail)}</td><td>${formatRupiah(product.wholesale)}<span class="subtext">min. ${Number(product.wholesaleMin)} ${escapeHtml(product.unit)}</span></td>
                        <td class="${low ? 'inventory-low-stock' : ''}">${Number(product.shop)} ${escapeHtml(product.unit)}</td><td>${Number(product.warehouse)} ${escapeHtml(product.unit)}</td><td>${Number(product.min)}</td>
                        <td><span class="badge ${low ? (Number(product.shop) === 0 ? 'badge-danger' : 'badge-warning') : ''}">${low ? (Number(product.shop) === 0 ? 'Habis' : 'Menipis') : 'Aman'}</span></td>
                        ${canManage ? `<td class="inventory-actions"><button class="action-button" data-action="edit-product" data-id="${escapeHtml(product.id)}">Ubah</button><button class="action-button danger" data-action="delete-product" data-id="${escapeHtml(product.id)}">Hapus</button></td>` : ''}
                    </tr>`;
        }).join('') : `<tr><td class="empty" colspan="${canManage ? 11 : 10}">Barang tidak ditemukan.</td></tr>`}</tbody></table></div>
            </article>`;
    }
    return { stockPage };
}
