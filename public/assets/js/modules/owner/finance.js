export function createOwnerFinanceModule(context) {
    const { data, escapeHtml, pageHeading, formatRupiah, todayKey, dateAfter, dateTime, saleDate, returnsForSale, returnedQuantity, returnedAmount, netSaleRevenue, netSaleCost, netSaleProfit, inReportRange, canManageSales, canManageProducts, activeProducts, cashMovements, periodForm, downloadCsv, uiState, posCarts } = context;

    function cashFlowPage() {
        const movements = cashMovements().filter(item => inReportRange(item.date)).reverse();
        const totalIn = movements.filter(item => item.direction === 'Masuk').reduce((sum, item) => sum + Number(item.amount), 0);
        const totalOut = movements.filter(item => item.direction === 'Keluar').reduce((sum, item) => sum + Number(item.amount), 0);
        return `${pageHeading('Arus Kas', 'Pantau penerimaan dan pengeluaran kas aktual, lalu catat transaksi kas lain secara manual.', '<button class="button button-quiet" id="export-cash">Unduh CSV</button>')}
            ${periodForm()}
            <div class="grid stats-grid">
                <article class="stat-card"><div class="stat-label">Kas masuk</div><div class="stat-value">${formatRupiah(totalIn)}</div><div class="stat-foot">penjualan dan pembayaran piutang</div></article>
                <article class="stat-card"><div class="stat-label">Kas keluar</div><div class="stat-value">${formatRupiah(totalOut)}</div><div class="stat-foot">pengembalian dan pengeluaran tercatat</div></article>
                <article class="stat-card"><div class="stat-label">Arus kas bersih</div><div class="stat-value">${formatRupiah(totalIn - totalOut)}</div><div class="stat-foot">${uiState.reportFrom} s.d. ${uiState.reportTo}</div></article>
                <article class="stat-card"><div class="stat-label">Jumlah transaksi</div><div class="stat-value">${movements.length}</div><div class="stat-foot">pada periode yang dipilih</div></article>
            </div>
            <article class="panel">
                <div class="panel-heading"><div><h2>Catat Kas Masuk / Keluar</h2><p>Penerimaan penjualan dan pembayaran piutang tercatat otomatis.</p></div></div>
                <form id="cash-entry-form">
                    <div class="form-grid">
                        <div class="field"><label for="cash-direction">Jenis</label><select id="cash-direction" name="direction"><option value="Keluar">Kas keluar</option><option value="Masuk">Kas masuk lainnya</option></select></div>
                        <div class="field"><label for="cash-category">Kategori</label><input id="cash-category" name="category" maxlength="60" required placeholder="Contoh: listrik, transport"></div>
                        <div class="field"><label for="cash-amount">Jumlah (Rp)</label><input id="cash-amount" name="amount" type="number" min="1" step="1" required></div>
                        <div class="field"><label for="cash-method">Metode</label><select id="cash-method" name="method"><option>Tunai</option><option>Transfer</option></select></div>
                        <div class="field"><label for="cash-date">Tanggal</label><input id="cash-date" name="date" type="date" value="${todayKey()}" required></div>
                        <div class="field"><label for="cash-description">Keterangan</label><input id="cash-description" name="description" maxlength="180" required></div>
                    </div>
                    <div class="form-actions"><button class="button" type="submit">Simpan Catatan Kas</button></div>
                </form>
            </article>
            <article class="panel"><div class="panel-heading"><div><h2>Riwayat Arus Kas</h2><p>${movements.length} transaksi pada periode ini.</p></div></div>
                ${movements.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Tanggal</th><th>Arah</th><th>Kategori</th><th>Keterangan</th><th>Metode</th><th>Jumlah</th><th>Petugas</th></tr></thead><tbody>${movements.map(item => `<tr>
                    <td>${escapeHtml(item.date)}</td><td><span class="badge ${item.direction === 'Keluar' ? 'badge-warning' : ''}">${escapeHtml(item.direction)}</span></td>
                    <td>${escapeHtml(item.category)}</td><td>${escapeHtml(item.description)}</td><td>${escapeHtml(item.method || '—')}</td>
                    <td>${item.direction === 'Keluar' ? '−' : '+'}${formatRupiah(item.amount)}</td><td>${escapeHtml(item.userName || 'Sistem')}</td>
                </tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada transaksi kas pada periode ini.</div>'}
            </article>`;
    }


    function profitArchivePage() {
        const sales = data.sales.filter(sale => inReportRange(saleDate(sale)));
        const revenue = sales.reduce((sum, sale) => sum + netSaleRevenue(sale), 0);
        const cost = sales.reduce((sum, sale) => sum + netSaleCost(sale), 0);
        const otherIncome = data.cashEntries.filter(item => item.direction === 'Masuk' && inReportRange(item.date))
            .reduce((sum, item) => sum + Number(item.amount), 0);
        const expenses = data.cashEntries.filter(item => item.direction === 'Keluar' && inReportRange(item.date))
            .reduce((sum, item) => sum + Number(item.amount), 0);
        const inventoryLoss = data.stockMovements.filter(item => Number(item.loss) > 0 && inReportRange(item.createdAt?.slice(0, 10) || item.date))
            .reduce((sum, item) => sum + Number(item.loss), 0);
        const receivableWriteoffs = data.receivableWriteoffs.filter(item => inReportRange(item.date))
            .reduce((sum, item) => sum + Number(item.amount), 0);
        const grossProfit = revenue - cost;
        const netProfit = grossProfit + otherIncome - expenses - inventoryLoss - receivableWriteoffs;
        const archivedReceipts = [...data.receipts].filter(item => inReportRange(item.date))
            .sort((first, second) => second.date.localeCompare(first.date));
        return `${pageHeading('Laba / Rugi & Arsip', 'Laporan laba rugi dihitung dari penjualan bersih dan harga pokok; arsip transaksi dapat diekspor.', '<button class="button button-quiet" id="export-archive">Unduh Arsip CSV</button><button class="button button-quiet" id="print-report">Cetak</button>')}
            ${periodForm()}
            <div class="grid stats-grid">
                <article class="stat-card"><div class="stat-label">Penjualan bersih</div><div class="stat-value">${formatRupiah(revenue)}</div><div class="stat-foot">${sales.length} struk pada periode ini</div></article>
                <article class="stat-card"><div class="stat-label">Harga pokok penjualan</div><div class="stat-value">${formatRupiah(cost)}</div><div class="stat-foot">dikurangi barang retur layak jual</div></article>
                <article class="stat-card"><div class="stat-label">Laba kotor</div><div class="stat-value">${formatRupiah(grossProfit)}</div><div class="stat-foot">penjualan bersih − HPP</div></article>
                <article class="stat-card"><div class="stat-label">Laba bersih</div><div class="stat-value">${formatRupiah(netProfit)}</div><div class="stat-foot">setelah biaya, kerugian stok, dan hapus buku piutang</div></article>
            </div>
            <article class="panel"><div class="panel-heading"><div><h2>Rincian Laba / Rugi</h2><p>${uiState.reportFrom} s.d. ${uiState.reportTo}</p></div></div>
                <div class="table-wrap"><table class="compact-table"><tbody>
                    <tr><td>Penjualan bersih (setelah retur)</td><td>${formatRupiah(revenue)}</td></tr>
                    <tr><td>Harga pokok penjualan</td><td>− ${formatRupiah(cost)}</td></tr>
                    <tr><td><strong>Laba kotor</strong></td><td><strong>${formatRupiah(grossProfit)}</strong></td></tr>
                    <tr><td>Pemasukan lain</td><td>+ ${formatRupiah(otherIncome)}</td></tr>
                    <tr><td>Pengeluaran operasional tercatat</td><td>− ${formatRupiah(expenses)}</td></tr>
                    <tr><td>Kerugian stok tidak layak / penyesuaian</td><td>− ${formatRupiah(inventoryLoss)}</td></tr>
                    <tr><td>Penghapusan buku piutang</td><td>− ${formatRupiah(receivableWriteoffs)}</td></tr>
                    <tr><td><strong>Laba / rugi bersih</strong></td><td><strong>${formatRupiah(netProfit)}</strong></td></tr>
                </tbody></table></div>
            </article>
            <article class="panel"><div class="panel-heading"><div><h2>Arsip Struk Penjualan</h2><p>Void, retur, dan status pembayaran tercantum pada arsip CSV.</p></div></div>
                ${sales.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Tanggal</th><th>Nomor struk</th><th>Pelanggan</th><th>Jenis</th><th>Jumlah retur</th><th>Penjualan bersih</th><th>Status</th></tr></thead><tbody>${[...sales].sort((first, second) => new Date(second.createdAt) - new Date(first.createdAt)).map(sale => `<tr>
                    <td>${escapeHtml(saleDate(sale))}</td><td>${escapeHtml(sale.number)}</td><td>${escapeHtml(sale.customer || 'Umum')}</td>
                    <td>${escapeHtml(sale.type)}</td><td>${formatRupiah(returnedAmount(sale.id))}</td><td>${formatRupiah(netSaleRevenue(sale))}</td><td>${escapeHtml(sale.status)}</td>
                </tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada struk penjualan dalam periode ini.</div>'}
            </article>
            <article class="panel"><div class="panel-heading"><div><h2>Arsip Barang Masuk</h2><p>Pembelian persediaan tercatat sebagai kas keluar dan tidak dibebankan lagi ke laba rugi sebelum terjual.</p></div></div>
                ${archivedReceipts.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Tanggal</th><th>Faktur</th><th>Barang</th><th>Distributor</th><th>Jumlah</th><th>Nilai</th></tr></thead><tbody>${archivedReceipts.map(item => `<tr>
                    <td>${escapeHtml(item.date)}</td><td>${escapeHtml(item.invoice)}</td><td>${escapeHtml(item.productName)}</td>
                    <td>${escapeHtml(item.distributor)}</td><td>${Number(item.quantity)} ${escapeHtml(item.unit)}</td><td>${formatRupiah(item.total)}</td>
                </tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada penerimaan barang dalam periode ini.</div>'}
            </article>`;
    }

    return { cashFlowPage, profitArchivePage };
}
