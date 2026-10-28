export function createCashierSalesModule(context) {
    const { data, escapeHtml, pageHeading, formatRupiah, todayKey, dateAfter, dateTime, saleDate, returnsForSale, returnedQuantity, returnedAmount, netSaleRevenue, netSaleCost, netSaleProfit, inReportRange, canManageSales, canManageProducts, activeProducts, cashMovements, periodForm, downloadCsv, uiState, posCarts } = context;
    const writeoffTotal = receivable => data.receivableWriteoffs
        .filter(item => item.receivableId === receivable.id)
        .reduce((sum, item) => sum + Number(item.amount), 0);

    function posPage(type, user) {
        const cart = posCarts[type];
        const selectedCustomer = type === 'Grosir'
            ? data.customers.find(customer => customer.id === uiState.selectedCustomerId && customer.active !== false)
            : null;
        const query = uiState.posQuery.trim().toLowerCase();
        const products = activeProducts().filter(product =>
            `${product.code} ${product.name} ${product.category}`.toLowerCase().includes(query)
            && (type === 'Grosir'
                ? Number(product.shop) + Number(product.warehouse) > 0
                : Number(product.shop) > 0),
        );
        const availableStock = product => Number(product.shop) + (type === 'Grosir' ? Number(product.warehouse) : 0);
        const lines = cart.map((line, index) => {
            const product = data.products.find(item => item.id === line.productId && item.active !== false);
            const quantity = Number(line.quantity);
            const wholesaleApplied = type === 'Grosir' && product && quantity >= Number(product.wholesaleMin);
            const price = product ? Number(wholesaleApplied ? product.wholesale : product.retail) : 0;
            return { index, line, product, quantity, price, total: quantity * price, wholesaleApplied };
        });
        const total = lines.reduce((sum, line) => sum + line.total, 0);
        const availableReceivables = data.receivables.filter(item => Number(item.total) > Number(item.paid));
        const productOptions = products.map(product => `<option value="${escapeHtml(product.id)}">${escapeHtml(product.code)} — ${escapeHtml(product.name)} (stok ${availableStock(product)} ${escapeHtml(product.unit)}${type === 'Grosir' ? ` · gudang ${Number(product.warehouse)} lebih dulu` : ''})</option>`).join('');
        return `${pageHeading(`Kasir ${type}`, type === 'Eceran'
            ? 'Masukkan barang dan jumlah; harga eceran diterapkan untuk setiap item.'
            : 'Harga grosir berlaku jika jumlah item mencapai minimum grosir barang tersebut.') }
            <div class="pos-layout">
                <article class="panel pos-cart-panel">
                    <div class="panel-heading"><div><h2>${type === 'Grosir' ? 'Barang tersedia di toko / gudang' : 'Barang tersedia di toko'}</h2><p>Pilih barang untuk menambahkannya ke keranjang ${type.toLowerCase()}.</p></div></div>
                    <form class="pos-add-form" id="pos-add-form">
                        <div class="field"><label for="pos-search">Cari barang</label><input id="pos-search" name="search" type="search" autocomplete="off" placeholder="Nama atau kode barang" value="${escapeHtml(uiState.posQuery)}"></div>
                        <div class="pos-add-row">
                            <div class="field"><label for="pos-product">Barang dan stok tersedia</label><select id="pos-product" name="productId" required ${products.length ? '' : 'disabled'}>${productOptions || '<option value="">Tidak ada barang dengan stok</option>'}</select></div>
                            <div class="field"><label for="pos-quantity">Jumlah</label><input id="pos-quantity" name="quantity" type="number" min="1" step="1" value="1" required></div>
                            <button class="button pos-add-button" type="submit" ${products.length ? '' : 'disabled'}>＋ Tambah</button>
                        </div>
                    </form>
                    <div class="panel-heading pos-section-heading"><div><h2>Keranjang</h2><p>${lines.length} jenis barang</p></div><button class="action-button danger" type="button" data-action="pos-clear" data-type="${type}" ${lines.length ? '' : 'disabled'}>Kosongkan</button></div>
                    ${lines.length ? `<div class="table-wrap"><table class="compact-table pos-table"><thead><tr><th>Barang</th><th>Harga satuan</th><th>Jumlah</th><th>Subtotal</th><th></th></tr></thead><tbody>${lines.map(item => `<tr>
                        <td class="product-name">${item.product ? escapeHtml(item.product.name) : 'Barang tidak tersedia'}<span class="subtext">${item.product ? escapeHtml(item.product.code) : 'Hapus item ini untuk melanjutkan'}</span></td>
                        <td>${formatRupiah(item.price)}<span class="subtext">${item.wholesaleApplied ? 'Harga grosir' : 'Harga eceran'}</span></td>
                        <td><input class="control pos-quantity" type="number" min="1" max="${item.product ? availableStock(item.product) : 1}" step="1" value="${item.quantity}" data-cart-index="${item.index}" aria-label="Jumlah ${item.product ? escapeHtml(item.product.name) : 'barang'}"></td>
                        <td>${formatRupiah(item.total)}</td><td><button class="action-button danger" type="button" data-action="pos-remove" data-type="${type}" data-index="${item.index}" aria-label="Hapus barang">Hapus</button></td>
                    </tr>`).join('')}</tbody></table></div>` : '<div class="empty pos-empty">Keranjang masih kosong. Tambahkan barang untuk memulai transaksi.</div>'}
                </article>
                <article class="panel pos-payment-panel">
                    <div class="panel-heading"><div><h2>Pembayaran</h2><p>Periksa total transaksi sebelum disimpan.</p></div></div>
                    <div class="pos-grand-total"><span>Total belanja</span><strong>${formatRupiah(total)}</strong></div>
                    <form id="pos-checkout-form" data-type="${type}" data-total="${total}">
                        <div class="field"><label for="pos-payment">Metode pembayaran</label><select id="pos-payment" name="payment"><option value="Tunai">Tunai</option><option value="Transfer">Transfer</option><option value="Bon">Bon / Piutang</option></select></div>
                        <div class="field" id="pos-cash-field"><label for="pos-cash">Uang diterima (Rp)</label><input id="pos-cash" name="cash" type="number" min="${total}" step="1" value="${total}" required></div>
                        <div class="pos-credit-fields hidden" id="pos-credit-fields">
                            <div class="field"><label for="pos-customer">${type === 'Grosir' ? 'Pelanggan grosir' : 'Nama pelanggan'}</label>${type === 'Grosir'
        ? `<select id="pos-customer" name="customerId"><option value="">Pilih pelanggan</option>${data.customers.filter(customer => customer.active !== false).map(customer => {
            const remainingLimit = Math.max(0, Number(customer.creditLimit) - data.receivables.filter(item => item.customerId === customer.id)
                .reduce((sum, item) => sum + Math.max(0, Number(item.total) - Number(item.paid) - writeoffTotal(item)), 0));
            const creditStatus = Number(customer.creditLimit) > 0 ? `sisa limit ${formatRupiah(remainingLimit)}` : 'Bon belum diaktifkan Owner';
            return `<option value="${escapeHtml(customer.id)}" ${uiState.selectedCustomerId === customer.id ? 'selected' : ''}>${escapeHtml(customer.name)} · ${creditStatus}</option>`;
        }).join('')}</select>`
        : '<input id="pos-customer" name="customer" maxlength="100" autocomplete="off">'}</div>
                            ${type === 'Grosir' ? `<p class="pos-note" id="pos-customer-credit-status" aria-live="polite">${selectedCustomer && Number(selectedCustomer.creditLimit) <= 0 ? 'Bon belum dapat digunakan. Minta Owner menetapkan limit dan tenor pelanggan ini.' : ''}</p>
                                <button class="action-button" type="button" data-action="pos-new-customer">＋ Daftarkan pelanggan baru</button>` : ''}
                            <div class="field"><label for="pos-due-date">Tanggal jatuh tempo</label><input id="pos-due-date" name="dueDate" type="date" min="${todayKey()}" ${selectedCustomer ? `max="${dateAfter(Math.max(1, Number(selectedCustomer.creditDays) || 1))}"` : ''} value="${dateAfter(selectedCustomer ? Math.max(1, Number(selectedCustomer.creditDays) || 1) : 7)}"></div>
                        </div>
                        <div class="pos-change" id="pos-change">Kembalian: ${formatRupiah(0)}</div>
                        <button class="button button-wide pos-checkout-button" type="submit" ${lines.length && lines.every(item => item.product) ? '' : 'disabled'}>Selesaikan Transaksi</button>
                        ${availableReceivables.length && type === 'Eceran' ? '<p class="pos-note">Piutang pelanggan dapat dicatat melalui metode Bon.</p>' : ''}
                    </form>
                    ${type === 'Grosir' ? `<form class="inline-form hidden" id="pos-customer-create-form">
                        <h3>Daftarkan pelanggan grosir</h3>
                        <p class="pos-note">${user?.role === 'Owner' ? 'Tentukan limit dan tenor sekarang, atau biarkan limit Rp0 untuk mengaktifkannya nanti.' : 'Pelanggan baru akan memiliki limit Rp0. Owner perlu menetapkan limit dan tenor sebelum transaksi Bon dapat dilakukan.'}</p>
                        <div class="form-grid">
                            <div class="field"><label for="pos-new-customer-name">Nama pelanggan / usaha</label><input id="pos-new-customer-name" name="name" maxlength="120" required></div>
                            <div class="field"><label for="pos-new-customer-phone">Nomor telepon</label><input id="pos-new-customer-phone" name="phone" maxlength="40"></div>
                            ${user?.role === 'Owner' ? `<div class="field"><label for="pos-new-customer-limit">Plafon kredit (Rp)</label><input id="pos-new-customer-limit" name="creditLimit" type="number" min="0" step="1" value="0" required></div>
                                <div class="field"><label for="pos-new-customer-days">Tenor (hari)</label><input id="pos-new-customer-days" name="creditDays" type="number" min="1" max="90" step="1" value="7" required></div>` : ''}
                        </div>
                        <div class="form-actions"><button class="button button-quiet" type="button" data-action="pos-cancel-new-customer">Batal</button><button class="button" type="submit">Simpan Pelanggan</button></div>
                    </form>` : ''}
                </article>
            </div>`;
    }


    function receivablesPage(user) {
        const isOwner = user?.role === 'Owner';
        const open = data.receivables.filter(item => Number(item.total) > Number(item.paid) + writeoffTotal(item));
        const history = [...data.receivables].reverse();
        const due = item => Math.max(0, Number(item.total) - Number(item.paid) - writeoffTotal(item));
        const reminders = open.filter(item => Math.ceil((new Date(`${item.dueDate}T00:00:00`) - new Date(`${todayKey()}T00:00:00`)) / 86400000) <= 3);
        return `${pageHeading('Piutang', 'Pantau saldo bon pelanggan dan catat pembayaran sebagian maupun pelunasan.') }
            <div class="grid stats-grid receivable-stats">
                <article class="stat-card"><div class="stat-label">Piutang aktif</div><div class="stat-value">${open.length}</div><div class="stat-foot">transaksi belum lunas</div></article>
                <article class="stat-card"><div class="stat-label">Total saldo piutang</div><div class="stat-value">${formatRupiah(open.reduce((sum, item) => sum + due(item), 0))}</div><div class="stat-foot">jumlah yang belum dibayar</div></article>
                <article class="stat-card"><div class="stat-label">Sudah lunas</div><div class="stat-value">${history.filter(item => Number(item.paid) >= Number(item.total)).length}</div><div class="stat-foot">dibayar penuh, tidak termasuk hapus buku</div></article>
                <article class="stat-card"><div class="stat-label">Pembayaran hari ini</div><div class="stat-value">${formatRupiah(data.receivables.reduce((sum, item) => sum + (item.payments || [])
            .filter(payment => (payment.date || String(payment.createdAt).slice(0, 10)) === todayKey())
            .reduce((paid, payment) => paid + Number(payment.amount), 0), 0))}</div><div class="stat-foot">penerimaan dari piutang</div></article>
            </div>
            ${reminders.length ? `<article class="panel"><div class="panel-heading"><div><h2>Pengingat Jatuh Tempo</h2><p>${reminders.length} piutang jatuh tempo dalam 3 hari atau sudah terlambat.</p></div></div>
                <div class="table-wrap"><table class="compact-table"><thead><tr><th>Pelanggan</th><th>Struk</th><th>Jatuh tempo</th><th>Sisa</th><th>Status</th></tr></thead><tbody>
                    ${reminders.map(item => {
            const days = Math.ceil((new Date(`${item.dueDate}T00:00:00`) - new Date(`${todayKey()}T00:00:00`)) / 86400000);
            return `<tr><td>${escapeHtml(item.customer)}</td><td>${escapeHtml(item.number)}</td><td>${escapeHtml(item.dueDate)}</td><td>${formatRupiah(due(item))}</td><td><span class="badge badge-warning">${days < 0 ? `Terlambat ${Math.abs(days)} hari` : days === 0 ? 'Jatuh tempo hari ini' : `Jatuh tempo ${days} hari lagi`}</span></td></tr>`;
        }).join('')}
                </tbody></table></div></article>` : ''}
            ${open.length ? `<article class="panel receivable-payment-panel">
                <div class="panel-heading"><div><h2>Catat Pembayaran Piutang</h2><p>Pembayaran tidak boleh melebihi sisa saldo transaksi.</p></div></div>
                <form id="receivable-payment-form">
                    <div class="form-grid">
                        <div class="field field-full"><label for="receivable-id">Pelanggan / nomor struk</label><select id="receivable-id" name="receivableId" required>${open.map(item => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.customer)} — ${escapeHtml(item.number)} (sisa ${formatRupiah(due(item))})</option>`).join('')}</select></div>
                        <div class="field"><label for="receivable-amount">Jumlah pembayaran (Rp)</label><input id="receivable-amount" name="amount" type="number" min="1" max="${due(open[0])}" step="1" required></div>
                        <div class="field"><label for="receivable-method">Metode pembayaran</label><select id="receivable-method" name="method"><option>Tunai</option><option>Transfer</option></select></div>
                    </div>
                    <div class="form-actions"><button class="button" type="submit">Simpan Pembayaran</button></div>
                </form>
            </article>` : ''}
            <article class="panel receivable-history-panel">
                <div class="panel-heading"><div><h2>Riwayat Piutang</h2><p>${history.length} transaksi bon tercatat.</p></div></div>
                ${history.length ? `<div class="table-wrap"><table class="compact-table receivable-table"><thead><tr><th>Tanggal</th><th>Pelanggan</th><th>No. Struk</th><th>Total</th><th>Dibayar</th><th>Sisa</th><th>Jatuh tempo</th><th>Status</th></tr></thead><tbody>${history.map(item => `<tr>
                    <td>${escapeHtml(dateTime(item.createdAt))}</td><td class="product-name">${escapeHtml(item.customer)}</td><td>${escapeHtml(item.number)}</td>
                    <td>${formatRupiah(item.total)}</td><td>${formatRupiah(item.paid)}</td><td>${formatRupiah(due(item))}</td><td>${escapeHtml(item.dueDate || '—')}</td>
                    <td><span class="badge ${due(item) ? 'badge-warning' : ''}">${due(item) ? 'Belum lunas' : Number(item.paid) >= Number(item.total) ? 'Lunas' : 'Dihapus buku'}</span></td>
                </tr>${(item.payments || []).map(payment => `<tr class="payment-history-row"><td colspan="2">Pembayaran · ${escapeHtml(dateTime(payment.createdAt))}</td><td>${escapeHtml(payment.method)}</td><td colspan="2">${formatRupiah(payment.amount)}</td><td colspan="3">${escapeHtml(payment.userName)}</td></tr>`).join('')}`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada transaksi piutang.</div>'}
            </article>
            ${isOwner && open.length ? `<article class="panel"><div class="panel-heading"><div><h2>Hapus Buku Piutang</h2><p>Hanya Owner yang dapat menghapus sisa piutang yang tidak tertagih.</p></div></div>
                <form id="receivable-writeoff-form"><div class="form-grid">
                    <div class="field field-full"><label for="writeoff-receivable">Pelanggan / struk</label><select id="writeoff-receivable" name="receivableId" required>${open.map(item => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.customer)} · ${escapeHtml(item.number)} · sisa ${formatRupiah(due(item))}</option>`).join('')}</select></div>
                    <div class="field"><label for="writeoff-amount">Jumlah hapus buku (Rp)</label><input id="writeoff-amount" name="amount" type="number" min="1" max="${due(open[0])}" step="1" required></div>
                    <div class="field"><label for="writeoff-reason">Alasan</label><input id="writeoff-reason" name="reason" maxlength="180" required></div>
                </div><div class="form-actions"><button class="button button-danger" type="submit">Catat Hapus Buku</button></div></form>
            </article>` : ''}
            <article class="panel"><div class="panel-heading"><div><h2>Riwayat Hapus Buku</h2><p>${data.receivableWriteoffs.length} catatan write-off tersimpan.</p></div></div>
                ${data.receivableWriteoffs.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Tanggal</th><th>Pelanggan</th><th>Nomor struk</th><th>Nilai</th><th>Alasan</th><th>Owner</th></tr></thead><tbody>${[...data.receivableWriteoffs].reverse().map(item => `<tr><td>${escapeHtml(item.date)}</td><td>${escapeHtml(item.customer)}</td><td>${escapeHtml(item.number)}</td><td>${formatRupiah(item.amount)}</td><td>${escapeHtml(item.reason)}</td><td>${escapeHtml(item.userName)}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada penghapusan buku.</div>'}
            </article>`;
    }


    function voidReturnPage(user) {
        const isOwner = user?.role === 'Owner';
        const pendingAdjustments = data.returns.filter(item => item.status === 'Menunggu Persetujuan')
            .sort((first, second) => new Date(second.createdAt) - new Date(first.createdAt));
        const cancellableSales = [...data.sales].filter(sale => !['Void', 'Retur Penuh'].includes(sale.status))
            .filter(sale => !pendingAdjustments.some(request => request.saleId === sale.id))
            .sort((first, second) => new Date(second.createdAt) - new Date(first.createdAt));
        const selectedSale = cancellableSales.find(sale => sale.id === uiState.selectedReturnSaleId) || cancellableSales[0];
        uiState.selectedReturnSaleId = selectedSale?.id || '';
        const canVoid = selectedSale && !returnsForSale(selectedSale.id).length;
        const remainingItems = (selectedSale?.items || []).map(item => ({
            ...item,
            remaining: Number(item.quantity) - returnedQuantity(selectedSale.id, item.productId),
        })).filter(item => item.remaining > 0);
        const adjustments = [...data.returns].sort((first, second) => new Date(second.createdAt) - new Date(first.createdAt));
        return `${pageHeading('Void / Retur', 'Kasir mengajukan void atau retur; perubahan stok dan saldo baru diproses setelah disetujui Owner.') }
            ${isOwner && pendingAdjustments.length ? `<article class="panel"><div class="panel-heading"><div><h2>Menunggu Persetujuan Owner</h2><p>${pendingAdjustments.length} permintaan perlu ditinjau sebelum diproses.</p></div></div>
                <div class="table-wrap"><table class="compact-table"><thead><tr><th>Waktu</th><th>Jenis</th><th>Struk</th><th>Jumlah</th><th>Alasan</th><th>Kasir</th><th>Aksi</th></tr></thead><tbody>
                    ${pendingAdjustments.map(request => `<tr><td>${escapeHtml(dateTime(request.createdAt))}</td><td>${escapeHtml(request.type)}</td><td>${escapeHtml(request.number)}</td>
                        <td>${formatRupiah(request.amount)}</td><td>${escapeHtml(request.reason)}</td><td>${escapeHtml(request.userName)}</td>
                        <td><button class="action-button" data-action="approve-adjustment" data-id="${escapeHtml(request.id)}">Setujui</button>
                            <button class="action-button danger" data-action="reject-adjustment" data-id="${escapeHtml(request.id)}">Tolak</button></td></tr>`).join('')}
                </tbody></table></div>
            </article>` : ''}
            <article class="panel">
                <div class="panel-heading"><div><h2>${isOwner ? 'Proses Void / Retur' : 'Ajukan Void / Retur'}</h2><p>Stok hanya dikembalikan jika barang dinyatakan layak jual setelah persetujuan.</p></div></div>
                ${cancellableSales.length ? `<form id="sale-adjustment-form">
                    <div class="form-grid">
                        <div class="field field-full"><label for="adjustment-sale">Pilih struk</label><select id="adjustment-sale" name="saleId" required>${cancellableSales.map(sale => `<option value="${escapeHtml(sale.id)}" ${sale.id === selectedSale.id ? 'selected' : ''}>${escapeHtml(sale.number)} · ${escapeHtml(sale.type)} · ${escapeHtml(saleDate(sale))} · ${formatRupiah(netSaleRevenue(sale))} tersisa</option>`).join('')}</select></div>
                        ${selectedSale ? `<div class="field field-full"><span class="muted">Pelanggan: ${escapeHtml(selectedSale.customer || 'Umum')} · Kasir: ${escapeHtml(selectedSale.userName)} · Pembayaran: ${escapeHtml(selectedSale.payment)}</span></div>` : ''}
                    </div>
                    ${remainingItems.length ? `<div class="table-wrap return-items-table"><table><thead><tr><th>Barang</th><th>Harga / unit</th><th>Sisa dapat diretur</th><th>Jumlah retur</th><th>Layak jual?</th></tr></thead><tbody>${remainingItems.map(item => `<tr>
                        <td>${escapeHtml(item.name)}<span class="subtext">${escapeHtml(item.code)}</span></td>
                        <td>${formatRupiah(item.price)}</td><td>${item.remaining} ${escapeHtml(item.unit)}</td>
                        <td><input class="control return-quantity" type="number" min="0" max="${item.remaining}" step="1" value="0" data-product-id="${escapeHtml(item.productId)}" aria-label="Jumlah retur ${escapeHtml(item.name)}"></td>
                        <td><label class="check"><input class="return-restock" type="checkbox" data-product-id="${escapeHtml(item.productId)}" checked> Layak jual</label></td>
                    </tr>`).join('')}</tbody></table></div>` : '<div class="empty">Semua barang pada struk ini sudah diretur.</div>'}
                    <div class="form-grid">
                        <div class="field"><label for="adjustment-method">Metode pengembalian dana</label><select id="adjustment-method" name="refundMethod"><option>Tunai</option><option>Transfer</option></select></div>
                        <div class="field"><label for="adjustment-reason">Alasan</label><input id="adjustment-reason" name="reason" required maxlength="180" placeholder="Tuliskan alasan void / retur"></div>
                    </div>
                    <div class="form-actions">
                        <button class="button button-quiet" type="submit" name="adjustmentType" value="return" ${remainingItems.length ? '' : 'disabled'}>${isOwner ? 'Proses Retur Terpilih' : 'Ajukan Retur Terpilih'}</button>
                        <button class="button button-danger" type="submit" name="adjustmentType" value="void" ${canVoid ? '' : 'disabled'}>${isOwner ? 'Proses Void' : 'Ajukan Void'}</button>
                    </div>
                    ${!canVoid ? '<p class="muted">Void seluruh struk hanya tersedia sebelum ada retur parsial.</p>' : ''}
                </form>` : '<div class="empty">Belum ada struk yang dapat diproses.</div>'}
            </article>
            <article class="panel"><div class="panel-heading"><div><h2>Riwayat Void / Retur</h2><p>${adjustments.length} penyesuaian transaksi tercatat.</p></div></div>
                ${adjustments.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Waktu</th><th>Jenis</th><th>Struk</th><th>Nilai</th><th>Dana kembali</th><th>Alasan</th><th>Petugas</th></tr></thead><tbody>${adjustments.map(item => `<tr>
                    <td>${escapeHtml(dateTime(item.createdAt))}</td><td>${escapeHtml(item.type)}${item.status ? `<span class="subtext">${escapeHtml(item.status)}</span>` : ''}</td><td>${escapeHtml(item.number)}</td>
                    <td>${formatRupiah(item.amount)}</td><td>${formatRupiah(item.cashRefund || 0)}${item.cashRefund ? `<span class="subtext">${escapeHtml(item.refundMethod)}</span>` : ''}</td>
                    <td>${escapeHtml(item.reason)}</td><td>${escapeHtml(item.userName)}</td>
                </tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada void atau retur.</div>'}
            </article>`;
    }


    function salesReportPage() {
        const sales = [...data.sales].filter(sale => inReportRange(saleDate(sale)))
            .sort((first, second) => new Date(second.createdAt) - new Date(first.createdAt));
        const totalRevenue = sales.reduce((sum, sale) => sum + Number(sale.total), 0);
        const netRevenue = sales.reduce((sum, sale) => sum + netSaleRevenue(sale), 0);
        const totalProfit = sales.reduce((sum, sale) => sum + netSaleProfit(sale), 0);
        const byType = ['Eceran', 'Grosir'].map(type => ({
            type,
            count: sales.filter(sale => sale.type === type).length,
            revenue: sales.filter(sale => sale.type === type).reduce((sum, sale) => sum + netSaleRevenue(sale), 0),
            profit: sales.filter(sale => sale.type === type).reduce((sum, sale) => sum + netSaleProfit(sale), 0),
        }));
        const byPayment = ['Tunai', 'Transfer', 'Bon'].map(method => ({
            method,
            count: sales.filter(sale => sale.payment === method).length,
            value: sales.filter(sale => sale.payment === method).reduce((sum, sale) => sum + netSaleRevenue(sale), 0),
        }));
        const topProducts = Object.values(sales.reduce((map, sale) => {
            (sale.items || []).forEach(item => {
                const key = item.productId;
                const base = map[key] || { productId: item.productId, name: item.name, code: item.code, sold: 0, revenue: 0, units: 0 };
                const returned = returnsForSale(sale.id).flatMap(adjustment => adjustment.items)
                    .filter(returnedItem => returnedItem.productId === item.productId)
                    .reduce((sum, returnedItem) => sum + Number(returnedItem.quantity), 0);
                base.sold += Number(item.quantity) > returned ? 1 : 0;
                base.units += Number(item.quantity || 0) - returned;
                base.revenue += Number(item.subtotal || 0)
                    - returnsForSale(sale.id).flatMap(adjustment => adjustment.items)
                        .filter(returnedItem => returnedItem.productId === item.productId)
                        .reduce((sum, returnedItem) => sum + Number(returnedItem.amount), 0);
                map[key] = base;
            });
            return map;
        }, {})).sort((first, second) => second.revenue - first.revenue).slice(0, 5);

        return `${pageHeading('Laporan Penjualan', 'Ringkasan performa penjualan, metode pembayaran, dan produk terlaris.', '<button class="button button-quiet" id="export-sales">Unduh CSV</button><button class="button button-quiet" id="print-report">Cetak</button>')}
            ${periodForm()}
            <div class="grid stats-grid">
                <article class="stat-card"><div class="stat-label">Penjualan bersih</div><div class="stat-value">${formatRupiah(netRevenue)}</div><div class="stat-foot">sebelum retur: ${formatRupiah(totalRevenue)}</div></article>
                <article class="stat-card"><div class="stat-label">Laba kotor</div><div class="stat-value">${formatRupiah(totalProfit)}</div><div class="stat-foot">berdasarkan harga pokok barang</div></article>
                <article class="stat-card"><div class="stat-label">Transaksi eceran</div><div class="stat-value">${byType.find(item => item.type === 'Eceran')?.count || 0}</div><div class="stat-foot">${formatRupiah(byType.find(item => item.type === 'Eceran')?.revenue || 0)}</div></article>
                <article class="stat-card"><div class="stat-label">Transaksi grosir</div><div class="stat-value">${byType.find(item => item.type === 'Grosir')?.count || 0}</div><div class="stat-foot">${formatRupiah(byType.find(item => item.type === 'Grosir')?.revenue || 0)}</div></article>
            </div>
            <div class="dashboard-middle">
                <article class="panel"><div class="panel-heading"><div><h2>Performa per Tipe Penjualan</h2></div></div>
                    <div class="table-wrap"><table class="compact-table"><thead><tr><th>Jenis</th><th>Jumlah</th><th>Revenue</th><th>Laba</th></tr></thead><tbody>${byType.map(item => `<tr><td>${escapeHtml(item.type)}</td><td>${item.count}</td><td>${formatRupiah(item.revenue)}</td><td>${formatRupiah(item.profit)}</td></tr>`).join('')}</tbody></table></div>
                </article>
                <article class="panel"><div class="panel-heading"><div><h2>Metode Pembayaran</h2></div></div>
                    <div class="table-wrap"><table class="compact-table"><thead><tr><th>Pembayaran</th><th>Jumlah</th><th>Nilai</th></tr></thead><tbody>${byPayment.map(item => `<tr><td>${escapeHtml(item.method)}</td><td>${item.count}</td><td>${formatRupiah(item.value)}</td></tr>`).join('')}</tbody></table></div>
                </article>
            </div>
            <article class="panel"><div class="panel-heading"><div><h2>Produk Terlaris</h2><p>5 item dengan revenue tertinggi dari penjualan aktif.</p></div></div>
                <div class="table-wrap"><table class="compact-table"><thead><tr><th>Kode</th><th>Nama produk</th><th>Unit terjual</th><th>Revenue</th></tr></thead><tbody>${topProducts.length ? topProducts.map(item => `<tr><td>${escapeHtml(item.code)}</td><td>${escapeHtml(item.name)}</td><td>${item.units}</td><td>${formatRupiah(item.revenue)}</td></tr>`).join('') : '<tr><td colspan="4" class="empty">Belum ada penjualan.</td></tr>'}</tbody></table></div>
            </article>
            <article class="panel"><div class="panel-heading"><div><h2>Riwayat Penjualan Terbaru</h2><p>${sales.length} transaksi terbaru tersimpan di perangkat ini.</p></div></div>
                ${sales.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Waktu</th><th>Struk</th><th>Jenis</th><th>Pembayaran</th><th>Kasir</th><th>Penjualan bersih</th><th>Status</th></tr></thead><tbody>${sales.slice(0, 10).map(sale => `<tr><td>${escapeHtml(dateTime(sale.createdAt))}</td><td>${escapeHtml(sale.number)}</td><td>${escapeHtml(sale.type)}</td><td>${escapeHtml(sale.payment)}</td><td>${escapeHtml(sale.userName)}</td><td>${formatRupiah(netSaleRevenue(sale))}</td><td>${escapeHtml(sale.status)}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada penjualan pada periode ini.</div>'}
            </article>`;
    }

    return { posPage, receivablesPage, voidReturnPage, salesReportPage };
}
