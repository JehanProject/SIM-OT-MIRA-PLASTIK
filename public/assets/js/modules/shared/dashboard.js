export function createSharedDashboardModule(context) {
    const { data, escapeHtml, pageHeading, formatRupiah, todayKey, dateAfter, dateTime, saleDate, returnsForSale, returnedQuantity, returnedAmount, netSaleRevenue, netSaleCost, netSaleProfit, inReportRange, canManageSales, canManageProducts, activeProducts, cashMovements, periodForm, downloadCsv, uiState, posCarts } = context;

    function dashboard(user) {
        const now = new Date();
        const mondayOffset = (now.getDay() + 6) % 7;
        const week = Array.from({ length: 7 }, (_, index) => {
            const day = new Date(now);
            day.setDate(now.getDate() - mondayOffset + index);
            const key = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
            const labels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
            const value = data.sales.filter(sale => saleDate(sale) === key).reduce((sum, sale) => sum + netSaleRevenue(sale), 0) / 1000000;
            return { day: labels[index], value };
        });
        const todaySales = data.sales.filter(sale => saleDate(sale) === todayKey());
        const todayTotal = todaySales.reduce((sum, sale) => sum + netSaleRevenue(sale), 0);
        const todayProfit = todaySales.reduce((sum, sale) => sum + netSaleProfit(sale), 0);
        const todayCash = cashMovements().filter(item => item.date === todayKey()
            && ['Penjualan', 'Pembayaran piutang', 'Pengembalian penjualan'].includes(item.category))
            .reduce((sum, item) => sum + (item.direction === 'Masuk' ? Number(item.amount) : -Number(item.amount)), 0);
        const stockAlerts = data.products
            .filter(item => item.active !== false && Number(item.shop) <= Number(item.min))
            .sort((first, second) => Number(first.shop) - Number(second.shop))
            .slice(0, 3);
        const latestSales = [...data.sales].reverse().slice(0, 5);
        return `<div class="dashboard-page">
            <div class="dashboard-stats">
                <article class="dashboard-stat"><div class="dashboard-stat-label">TOTAL PENJUALAN HARI INI</div><div class="dashboard-stat-value">${formatRupiah(todayTotal)}</div><div class="dashboard-stat-foot">Dari ${todaySales.length} transaksi tercatat</div></article>
                <article class="dashboard-stat"><div class="dashboard-stat-label">LABA KOTOR HARI INI</div><div class="dashboard-stat-value">${formatRupiah(todayProfit)}</div><div class="dashboard-stat-foot">Berdasarkan harga beli barang</div></article>
                <article class="dashboard-stat"><div class="dashboard-stat-label">TOTAL TRANSAKSI</div><div class="dashboard-stat-value">${todaySales.length}</div><div class="dashboard-stat-foot">${todaySales.filter(sale => sale.type === 'Eceran').length} eceran | ${todaySales.filter(sale => sale.type === 'Grosir').length} grosir</div></article>
                <article class="dashboard-stat"><div class="dashboard-stat-label">PEMBAYARAN DITERIMA HARI INI</div><div class="dashboard-stat-value">${formatRupiah(todayCash)}</div><div class="dashboard-stat-foot">Termasuk pembayaran piutang</div></article>
            </div>
            <div class="dashboard-middle">
                <article class="dashboard-panel weekly-panel">
                    <div class="dashboard-panel-heading"><h2>Tren Penjualan Mingguan (juta Rp)</h2></div>
                    <div class="weekly-chart" role="img" aria-label="Grafik penjualan untuk minggu ini.">
                        ${week.map(item => `<div class="chart-column"><span class="chart-value">${item.value.toFixed(2)}</span><div class="chart-bar-wrap"><span class="chart-bar ${item.day === 'Min' ? 'today-bar' : ''}" style="height:${Math.max(item.value ? 6 : 0, Math.round(item.value / Math.max(...week.map(day => day.value), .01) * 100))}%"></span></div><span class="chart-day">${item.day}</span></div>`).join('')}
                    </div>
                </article>
                <div class="dashboard-alerts">
                    <article class="dashboard-panel dashboard-list-panel">
                        <div class="dashboard-panel-heading"><h2>Peringatan Stok</h2><button class="dashboard-link" data-page="stok-barang">Lihat semua</button></div>
                        <div class="dashboard-list">${stockAlerts.length ? stockAlerts.map(item => {
                            const empty = Number(item.shop) <= 0;
                            return `<div class="dashboard-list-row"><div><strong>${escapeHtml(item.name)}</strong><small>Stok toko ${Number(item.shop)} | gudang ${Number(item.warehouse)}</small></div><span class="badge ${empty ? 'badge-danger' : 'badge-warning'}">${empty ? 'Habis' : 'Menipis'}</span></div>`;
                        }).join('') : '<div class="dashboard-empty">Tidak ada stok minimum.</div>'}</div>
                    </article>
                    <article class="dashboard-panel dashboard-list-panel due-panel">
                        <div class="dashboard-panel-heading"><h2>Piutang Aktif</h2>${canManageSales(user) ? '<button class="dashboard-link" data-page="piutang">Lihat semua</button>' : ''}</div>
                        ${data.receivables.filter(item => Number(item.total) > Number(item.paid) + data.receivableWriteoffs.filter(writeoff => writeoff.receivableId === item.id).reduce((sum, writeoff) => sum + Number(writeoff.amount), 0)).length
            ? data.receivables.filter(item => Number(item.total) > Number(item.paid) + data.receivableWriteoffs.filter(writeoff => writeoff.receivableId === item.id).reduce((sum, writeoff) => sum + Number(writeoff.amount), 0)).slice(0, 2).map(item => `<div class="dashboard-list-row"><div><strong>${escapeHtml(item.customer)}</strong><small>${escapeHtml(item.number)} | Sisa ${formatRupiah(Math.max(0, Number(item.total) - Number(item.paid) - data.receivableWriteoffs.filter(writeoff => writeoff.receivableId === item.id).reduce((sum, writeoff) => sum + Number(writeoff.amount), 0)))}</small></div><span class="badge badge-warning">${item.dueDate ? escapeHtml(item.dueDate) : 'Belum lunas'}</span></div>`).join('')
            : '<div class="dashboard-list-row"><div><strong>Tidak ada piutang aktif</strong><small>Transaksi bon akan tercatat di sini.</small></div></div>'}
                    </article>
                </div>
            </div>
            <article class="dashboard-panel transactions-panel">
                <div class="dashboard-panel-heading"><h2>Transaksi Terkini</h2>${canManageSales(user) ? '<button class="dashboard-link" data-page="laporan-penjualan">Lihat semua</button>' : ''}</div>
                <div class="table-wrap"><table class="dashboard-table"><thead><tr><th>No. Struk</th><th>Waktu</th><th>Jenis</th><th>Pembayaran</th><th>Kasir</th><th>Penjualan bersih</th><th>Status</th></tr></thead>
                    <tbody>${latestSales.length ? latestSales.map(sale => `<tr>
                        <td>${escapeHtml(sale.number)}</td><td>${new Date(sale.createdAt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}</td>
                        <td><span class="sale-type ${sale.type === 'Eceran' ? 'retail-type' : 'wholesale-type'}">${escapeHtml(sale.type)}</span></td>
                        <td>${escapeHtml(sale.payment)}</td><td>${escapeHtml(sale.userName)}</td><td>${formatRupiah(netSaleRevenue(sale))}</td>
                        <td><span class="badge ${sale.status === 'Piutang' ? 'badge-warning' : ''}">${escapeHtml(sale.status)}</span></td>
                    </tr>`).join('') : '<tr><td colspan="7" class="dashboard-empty">Belum ada transaksi penjualan.</td></tr>'}</tbody>
                </table></div>
            </article>
        </div>`;
    }

    return { dashboard };
}
