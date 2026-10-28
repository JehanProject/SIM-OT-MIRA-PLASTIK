import { createSharedDashboardModule } from '../shared/dashboard.js';
import { createSharedInventoryModule } from '../shared/inventory.js';
import { createCashierSalesModule } from '../cashier/sales.js';
import { createWarehouseInventoryModule } from '../warehouse/inventory.js';
import { createOwnerFinanceModule } from '../owner/finance.js';
import { createOwnerAdministrationModule } from '../owner/administration.js';

(() => {
    'use strict';

    const DATA_KEY = 'mira-plastik-phase-1';
    const BACKUP_FORMAT = 'toko-mira-plastik-backup';
    const BACKUP_COLLECTIONS = ['users', 'activity', 'products', 'distributors', 'customers', 'stockMovements', 'receipts', 'sales', 'receivables', 'receivableWriteoffs', 'returns', 'cashEntries'];
    const STATE_COLLECTIONS = BACKUP_COLLECTIONS.filter(key => key !== 'users');
    const logoUrl = new URL('../../../images/logo-toko-mira-plastik.jpg', import.meta.url).href;
    const app = document.getElementById('app');
    const toast = document.getElementById('toast');
    const apiBase = document.querySelector('meta[name="mira-api-base"]')?.content || '/api';
    const roles = ['Owner', 'Kasir', 'Staf Gudang'];
    const demoUsers = [
        { id: 'owner-1', name: 'Mira Owner', username: 'owner', role: 'Owner', active: true },
        { id: 'cashier-1', name: 'Rina Kasir', username: 'kasir', role: 'Kasir', active: true },
        { id: 'warehouse-1', name: 'Dedi Gudang', username: 'gudang', role: 'Staf Gudang', active: true },
    ];
    const demoProducts = [
        { id: 'product-1', code: 'KP-M-001', name: 'Kantong Merah (S)', category: 'Kantong Plastik', unit: 'pak', buy: 8000, retail: 11000, wholesale: 9500, wholesaleMin: 10, shop: 0, warehouse: 12, min: 12, active: true },
        { id: 'product-2', code: 'KM-B-002', name: 'Kotak Makan Bening', category: 'Kemasan Makanan', unit: 'pak', buy: 18000, retail: 24000, wholesale: 21000, wholesaleMin: 8, shop: 8, warehouse: 28, min: 15, active: true },
        { id: 'product-3', code: 'SK-003', name: 'Sedotan Kopi', category: 'Perlengkapan Minum', unit: 'pak', buy: 6000, retail: 8500, wholesale: 7500, wholesaleMin: 12, shop: 14, warehouse: 30, min: 20, active: true },
        { id: 'product-4', code: 'GM-016', name: 'Gelas Plastik 16 oz', category: 'Gelas & Cup', unit: 'slop', buy: 18500, retail: 23000, wholesale: 21000, wholesaleMin: 8, shop: 18, warehouse: 42, min: 10, active: true },
        { id: 'product-5', code: 'KN-004', name: 'Kertas Nasi Cokelat', category: 'Kemasan Makanan', unit: 'pak', buy: 14500, retail: 18000, wholesale: 16000, wholesaleMin: 10, shop: 16, warehouse: 35, min: 10, active: true },
        { id: 'product-6', code: 'MK-500', name: 'Mika Kotak 500 ml', category: 'Kemasan Makanan', unit: 'pak', buy: 21000, retail: 26000, wholesale: 23500, wholesaleMin: 6, shop: 6, warehouse: 14, min: 8, active: true },
    ];

    const makeInitialData = () => ({
        users: demoUsers.map(user => ({ ...user })),
        activity: [],
        products: demoProducts.map(product => ({ ...product })),
        customers: [],
        distributors: [
            { id: 'dist-1', name: 'CV Plastik Sejahtera', phone: '0751-700123' },
            { id: 'dist-2', name: 'UD Sumber Kemasan', phone: '0812-7000-8899' },
        ],
        stockMovements: [],
        receipts: [],
        sales: [],
        receivables: [],
        receivableWriteoffs: [],
        returns: [],
        cashEntries: [],
    });
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
    let data;
    let hasLegacyData = false;
    let canClearLegacyStorage = false;
    let authenticatedUser = null;
    let serverRevision = 0;
    let serverReady = false;
    let stateSyncFailed = false;
    let syncQueue = Promise.resolve(true);
    let toastTimer;
    const uiState = {
        productQuery: '',
        productFormOpen: false,
        editingProductId: '',
        posQuery: '',
        selectedReturnSaleId: '',
        reportFrom: `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}-01`,
        reportTo: '',
    };
    const posCarts = { Eceran: [], Grosir: [] };

    try {
        const savedData = localStorage.getItem(DATA_KEY);
        hasLegacyData = savedData !== null;
        canClearLegacyStorage = !hasLegacyData;
        data = JSON.parse(savedData) || makeInitialData();
        if (!Array.isArray(data.users) || !Array.isArray(data.activity)) throw new Error('Format data tidak dikenal.');
        const initialData = makeInitialData();
        data.users = data.users.map(({ password, ...user }) => user);
        ['products', 'distributors', 'customers', 'stockMovements', 'receipts', 'sales', 'receivables', 'receivableWriteoffs', 'returns', 'cashEntries'].forEach(key => {
            if (!Array.isArray(data[key])) data[key] = initialData[key];
        });
    } catch (error) {
        console.error('Data tahap pertama tidak dapat dibaca.', error);
        data = makeInitialData();
    }
    if (hasLegacyData) {
        try {
            localStorage.setItem(DATA_KEY, JSON.stringify(data));
        } catch (error) {
            console.error('Data lama tidak dapat dibersihkan dari penyimpanan browser.', error);
        }
    }

    const validateBackup = backup => {
        if (!backup || typeof backup !== 'object' || Array.isArray(backup)
            || backup.format !== BACKUP_FORMAT || ![1, 2].includes(backup.version)
            || !backup.data || typeof backup.data !== 'object' || Array.isArray(backup.data)) {
            throw new Error('Format atau versi file backup tidak didukung.');
        }
        const restoredData = {};
        BACKUP_COLLECTIONS.forEach(key => {
            const collection = backup.data[key] ?? (key === 'customers' || key === 'receivableWriteoffs' ? [] : null);
            if (!Array.isArray(collection) || collection.some(item => !item || typeof item !== 'object' || Array.isArray(item))) {
                throw new Error(`Data "${key}" dalam file backup tidak valid.`);
            }
            restoredData[key] = collection;
        });
        const usernames = new Set();
        const userIds = new Set();
        restoredData.users.forEach(account => {
            if (!(typeof account.id === 'string' && account.id)
                && !(Number.isSafeInteger(account.id) && account.id > 0)
                || typeof account.name !== 'string' || !account.name.trim()
                || typeof account.username !== 'string' || !account.username.trim()
                || !roles.includes(account.role) || typeof account.active !== 'boolean') {
                throw new Error('Data akun dalam file backup tidak valid.');
            }
            const username = account.username.trim().toLowerCase();
            if (userIds.has(account.id) || usernames.has(username)) {
                throw new Error('File backup memiliki ID atau username akun yang duplikat.');
            }
            userIds.add(account.id);
            usernames.add(username);
        });
        if (!restoredData.users.some(account => account.role === 'Owner' && account.active)) {
            throw new Error('File backup harus memiliki setidaknya satu akun Owner aktif.');
        }
        return restoredData;
    };
    const canonicalRecord = value => {
        if (Array.isArray(value)) return value.map(canonicalRecord);
        if (value && typeof value === 'object') {
            return Object.fromEntries(Object.keys(value).sort().map(key => [key, canonicalRecord(value[key])]));
        }
        return value;
    };
    const collectionIdentities = (collection, record) => {
        const identities = [];
        if (typeof record.id === 'string' || Number.isSafeInteger(record.id)) {
            identities.push(`id:${record.id}`);
        }
        const uniqueField = {
            products: 'code',
            receipts: 'invoice',
            sales: 'number',
            receivables: 'saleId',
        }[collection];
        const uniqueValue = uniqueField ? record[uniqueField] : null;
        if (typeof uniqueValue === 'string' && uniqueValue.trim()) {
            identities.push(`${uniqueField}:${uniqueValue.trim().toLowerCase()}`);
        }
        return identities;
    };
    const mergeBackupData = (currentData, importedData) => {
        const merged = {};
        let added = 0;
        let duplicates = 0;
        for (const collection of STATE_COLLECTIONS) {
            const existing = currentData[collection];
            const imported = importedData[collection];
            if (!Array.isArray(existing) || !Array.isArray(imported)) {
                throw new Error(`Data "${collection}" tidak valid untuk digabung.`);
            }
            const records = [...existing];
            const identityIndex = new Map();
            const canonical = new Map();
            const addToIndex = (record, index) => {
                const identities = collectionIdentities(collection, record);
                if (!identities.some(identity => identity.startsWith('id:'))) {
                    throw new Error(`Ada catatan tanpa ID pada koleksi "${collection}".`);
                }
                for (const identity of identities) {
                    const existingIndex = identityIndex.get(identity);
                    if (existingIndex !== undefined) {
                        if (canonical.get(existingIndex) !== JSON.stringify(canonicalRecord(record))) {
                            throw new Error(`Data server memiliki konflik pada koleksi "${collection}".`);
                        }
                        throw new Error(`Data server memiliki ID atau nomor unik duplikat pada koleksi "${collection}".`);
                    }
                    identityIndex.set(identity, index);
                }
                canonical.set(index, JSON.stringify(canonicalRecord(record)));
            };
            existing.forEach((record, index) => addToIndex(record, index));
            imported.forEach(record => {
                const identities = collectionIdentities(collection, record);
                if (!identities.some(identity => identity.startsWith('id:'))) {
                    throw new Error(`Ada catatan tanpa ID pada koleksi "${collection}".`);
                }
                const matchedIndexes = [...new Set(identities
                    .map(identity => identityIndex.get(identity))
                    .filter(index => index !== undefined))];
                if (matchedIndexes.length > 0) {
                    if (matchedIndexes.length !== 1
                        || canonical.get(matchedIndexes[0]) !== JSON.stringify(canonicalRecord(record))) {
                        throw new Error(`Konflik data ditemukan pada koleksi "${collection}". Tidak ada data yang digabung.`);
                    }
                    duplicates += 1;
                    return;
                }
                const index = records.length;
                records.push(record);
                for (const identity of identities) identityIndex.set(identity, index);
                canonical.set(index, JSON.stringify(canonicalRecord(record)));
                added += 1;
            });
            merged[collection] = records;
        }
        merged.users = currentData.users;
        return { data: merged, added, duplicates };
    };
    const currentUser = () => authenticatedUser;
    const updateCsrfToken = payload => {
        if (typeof payload.csrfToken === 'string') {
            const token = document.querySelector('meta[name="csrf-token"]');
            if (token) token.content = payload.csrfToken;
        }
    };
    const requestApi = async (path, body, method = 'POST') => {
        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const options = { method: body === undefined ? 'GET' : method, credentials: 'same-origin', headers };
        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content || '';
            options.body = JSON.stringify(body);
        }
        const response = await fetch(`${apiBase}/${path}`, options);
        const payload = await response.json();
        updateCsrfToken(payload);
        if (!response.ok) {
            const error = new Error(payload.error || `Permintaan gagal (${response.status}).`);
            error.status = response.status;
            throw error;
        }
        return payload;
    };
    const clearLegacyStorage = () => {
        if (!canClearLegacyStorage) return;
        try {
            localStorage.removeItem(DATA_KEY);
        } catch (error) {
            console.error('Data browser lama tidak dapat dihapus setelah sinkronisasi.', error);
            notify('Data server tersimpan, tetapi data lama masih ada di browser ini.');
        }
    };
    const save = () => {
        if (!serverReady) return Promise.resolve(true);
        syncQueue = syncQueue.then(async () => {
            if (stateSyncFailed) return false;
            const state = Object.fromEntries(STATE_COLLECTIONS.map(key => [key, data[key]]));
            try {
                const result = await requestApi('state', { revision: serverRevision, data: state }, 'PUT');
                serverRevision = result.revision;
                clearLegacyStorage();
                return true;
            } catch (error) {
                stateSyncFailed = true;
                console.error('Penyimpanan data server gagal.', error);
                if (error.status === 401) {
                    serverReady = false;
                    authenticatedUser = null;
                    renderLogin();
                    notify('Sesi berakhir. Perubahan terakhir belum tersimpan; silakan masuk kembali.');
                    return false;
                }
                notify(error.message || 'Data belum tersimpan di server. Muat ulang sebelum melanjutkan.');
                return false;
            }
        });
        return syncQueue;
    };
    const applyServerState = result => {
        STATE_COLLECTIONS.forEach(key => {
            data[key] = Array.isArray(result.data[key]) ? result.data[key] : [];
        });
        serverRevision = result.revision;
        serverReady = true;
        if (result.revision > 0 || !hasLegacyData) {
            clearLegacyStorage();
        }
    };
    const loadServerState = async () => {
        let result = await requestApi('state');
        if (result.revision === 0 && authenticatedUser.role === 'Owner') {
            if (hasLegacyData) {
                data.activity.unshift({
                    id: `${Date.now()}-import-${Math.random().toString(36).slice(2, 7)}`,
                    userId: authenticatedUser.id,
                    name: authenticatedUser.name,
                    role: authenticatedUser.role,
                    message: 'Mengimpor data operasional lama ke penyimpanan server.',
                    createdAt: new Date().toISOString(),
                });
            }
            const importedState = Object.fromEntries(STATE_COLLECTIONS.map(key => [
                key,
                hasLegacyData ? data[key] : [],
            ]));
            await requestApi('state', { revision: 0, data: importedState }, 'PUT');
            if (hasLegacyData) canClearLegacyStorage = true;
            result = await requestApi('state');
            if (hasLegacyData) notify('Data lama berhasil diimpor ke server.');
        } else if (result.revision === 0 && hasLegacyData) {
            notify('Data lama menunggu impor oleh Owner. Data server masih kosong.');
        } else if (result.revision > 0 && hasLegacyData) {
            notify('Server sudah memiliki data. Salinan lama tetap disimpan di browser dan tidak ditimpa.');
        }
        applyServerState(result);
    };
    const loadOwnerUsers = async () => {
        const result = await requestApi('users');
        data.users = result.users;
    };
    const notify = message => {
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
    };
    const addActivity = message => {
        const user = currentUser();
        data.activity.unshift({
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
            userId: user?.id || null,
            name: user?.name || 'Sistem',
            role: user?.role || 'Sistem',
            message,
            createdAt: new Date().toISOString(),
        });
        data.activity = data.activity.slice(0, 100);
        return save();
    };
    const isOwner = user => user?.role === 'Owner';
    const canSell = user => isOwner(user) || user?.role === 'Kasir';
    const canManageSales = user => isOwner(user) || user?.role === 'Kasir';
    const todayKey = () => {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    };
    uiState.reportTo = todayKey();
    const dateAfter = days => {
        const date = new Date();
        date.setDate(date.getDate() + days);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    };
    const saleDate = sale => sale.date || String(sale.createdAt || '').slice(0, 10);
    const returnsForSale = saleId => data.returns.filter(item => item.saleId === saleId && item.status !== 'Menunggu Persetujuan' && item.status !== 'Ditolak');
    const returnedQuantity = (saleId, productId) => returnsForSale(saleId)
        .reduce((sum, adjustment) => sum + adjustment.items
            .filter(item => item.productId === productId)
            .reduce((quantity, item) => quantity + Number(item.quantity), 0), 0);
    const returnedAmount = saleId => returnsForSale(saleId)
        .reduce((sum, adjustment) => sum + Number(adjustment.amount), 0);
    const netSaleRevenue = sale => Math.max(0, Number(sale.total) - returnedAmount(sale.id));
    const netSaleCost = sale => Math.max(0, (sale.items || []).reduce((sum, item) =>
        sum + Number(item.cost || 0) * Number(item.quantity || 0), 0) - returnsForSale(sale.id)
        .reduce((sum, adjustment) => sum + adjustment.items
            .reduce((cost, item) => cost + Number(item.cost) * Number(item.quantity), 0), 0));
    const netSaleProfit = sale => netSaleRevenue(sale) - netSaleCost(sale);
    const inReportRange = date => date >= uiState.reportFrom && date <= uiState.reportTo;
    const dateTime = value => new Date(value).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });

    function renderLogin() {
        app.innerHTML = `
            <section class="login-page">
                <span class="orb orb-one"></span><span class="orb orb-two"></span><span class="orb orb-three"></span>
                <form class="login-card" id="login-form">
            <div class="login-brand">
                <img class="brand-logo login-logo" src="${escapeHtml(logoUrl)}" alt="Logo Toko Mira Plastik">
                    </div>
                    <h1>Selamat Datang Kembali!</h1>
                    <p class="login-subtitle">Silakan masuk untuk mengakses akun Anda.</p>
                    <div class="field">
                        <label for="username">Username atau Email</label>
                        <input id="username" name="username" autocomplete="username" placeholder="Masukkan username" required>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required>
                            <button class="text-action" type="button" data-action="toggle-password">Tampilkan</button>
                        </div>
                    </div>
                    <div class="login-options">
                        <label class="check"><input type="checkbox" name="remember"> Ingat Saya</label>
                        <button class="link-button" type="button" data-action="forgot-password">Lupa Password?</button>
                    </div>
                    <button class="button button-wide" type="submit">Masuk</button>
                    <div class="login-footer">© ${new Date().getFullYear()} Toko Mira Plastik. Hak cipta dilindungi.</div>
                </form>
            </section>`;
    }

    function navigation(user) {
        const canManageStock = isOwner(user) || user?.role === 'Staf Gudang';
        const items = [
            { page: 'dashboard', icon: '▦', title: 'Dashboard' },
            { page: 'kasir-eceran', icon: '▣', title: 'Kasir Eceran', available: canSell(user) },
            { page: 'kasir-grosir', icon: '▣', title: 'Kasir Grosir', available: canSell(user) },
            { page: 'laporan-penjualan', icon: '▤', title: 'Laporan Penjualan', available: canManageSales(user) },
            { page: 'void-retur', icon: '↶', title: 'Void / Retur', available: canManageSales(user) },
            { page: 'piutang', icon: '◷', title: 'Piutang', available: canManageSales(user) },
            { page: 'stok-barang', icon: '▧', title: 'Stok Barang', available: true },
            { page: 'barang-masuk-mutasi', icon: '⇄', title: 'Barang Masuk & Mutasi', available: canManageStock },
            { page: 'arus-kas', icon: '↕', title: 'Arus Kas', available: isOwner(user) },
            { page: 'laba-rugi-arsip', icon: '▥', title: 'Laba/Rugi & Arsip', available: isOwner(user) },
        ];
        if (isOwner(user)) {
            items.push(
                { section: 'Administrasi' },
                { page: 'customers', icon: '♧', title: 'Pelanggan Grosir' },
                { page: 'users', icon: '♙', title: 'Akun, Log & Backup' },
                { page: 'activity', icon: '◉', title: 'Log Aktivitas' },
            );
        }
        return items.filter(item => item.section || item.available !== false);
    }

    function renderShell(user, page = 'dashboard') {
        app.innerHTML = `
            <div class="app-shell">
                <aside class="sidebar">
                    <a class="brand sidebar-brand" href="#" data-page="dashboard">
                        <img class="brand-logo sidebar-logo" src="${escapeHtml(logoUrl)}" alt="Logo Toko Mira Plastik">
                    </a>
                    <nav class="nav-list">
                        ${navigation(user).map(item => item.section
                            ? `<div class="nav-section">${escapeHtml(item.section)}</div>`
                            : `<a class="nav-link ${page === item.page ? 'active' : ''}" href="#" data-page="${item.page}">
                                <span class="nav-icon">${item.icon}</span><span>${escapeHtml(item.title)}</span>
                            </a>`).join('')}
                    </nav>
                    <div class="sidebar-bottom">
                        <div class="user-chip sidebar-user">
                            <span class="avatar">${escapeHtml(user.name.slice(0, 1).toUpperCase())}</span>
                            <span class="user-copy"><strong>${escapeHtml(user.name)}</strong><small>${escapeHtml(user.role)}</small></span>
                        </div>
                        <button class="nav-link sidebar-logout" data-action="logout"><span class="nav-icon">↪</span><span>Keluar</span></button>
                    </div>
                </aside>
                <div class="app-main">
                    <header class="topbar">
                        <div class="topbar-title">${page === 'dashboard'
        ? '<strong>Dashboard Ringkasan</strong><small>Pantau kondisi toko hari ini secara real-time</small>'
        : escapeHtml({
            users: 'Manajemen Pengguna',
            activity: 'Log Aktivitas',
            customers: 'Pelanggan Grosir',
            'stok-barang': 'Stok Barang',
            'barang-masuk-mutasi': 'Barang Masuk & Mutasi',
            'kasir-eceran': 'Kasir Eceran',
            'kasir-grosir': 'Kasir Grosir',
            'laporan-penjualan': 'Laporan Penjualan',
            'void-retur': 'Void / Retur',
            'arus-kas': 'Arus Kas',
            'laba-rugi-arsip': 'Laba / Rugi & Arsip',
            piutang: 'Piutang',
        }[page] || 'Toko Mira Plastik')}</div>
                        <div class="topbar-right">
                            <span class="today">${new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })} &nbsp;|&nbsp; ${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}</span>
                            <span class="online-pill"><span></span> Demo</span>
                            <button class="button button-quiet mobile-logout" data-action="logout">Keluar</button>
                        </div>
                    </header>
                    <section class="main-content" id="page-content"></section>
                </div>
            </div>`;
        renderPage(user, page);
    }

    function pageHeading(title, subtitle, actions = '') {
        return `<div class="page-heading"><div><div class="eyebrow">Toko Mira Plastik</div><h1>${title}</h1><p>${subtitle}</p></div><div class="heading-actions">${actions}</div></div>`;
    }

    function canManageProducts(user) {
        return isOwner(user) || user?.role === 'Staf Gudang';
    }

    function formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(Number(value) || 0);
    }

    function cashMovements() {
        const sales = data.sales.filter(sale => sale.payment !== 'Bon').map(sale => ({
            id: `sale-${sale.id}`,
            date: saleDate(sale),
            createdAt: sale.createdAt,
            direction: 'Masuk',
            category: 'Penjualan',
            description: `Struk ${sale.number} (${sale.type})`,
            amount: Number(sale.total),
            method: sale.payment,
            userName: sale.userName,
        }));
        const collections = data.receivables.flatMap(receivable => (receivable.payments || []).map(payment => ({
            id: `collection-${payment.id}`,
            date: payment.date || String(payment.createdAt).slice(0, 10),
            createdAt: payment.createdAt,
            direction: 'Masuk',
            category: 'Pembayaran piutang',
            description: `Pembayaran struk ${receivable.number} · ${receivable.customer}`,
            amount: Number(payment.amount),
            method: payment.method,
            userName: payment.userName,
        })));
        const refunds = data.returns.filter(item => item.status !== 'Menunggu Persetujuan' && item.status !== 'Ditolak'
            && Number(item.cashRefund) > 0).map(item => ({
            id: `refund-${item.id}`,
            date: item.date,
            createdAt: item.createdAt,
            direction: 'Keluar',
            category: 'Pengembalian penjualan',
            description: `${item.type} struk ${item.number}`,
            amount: Number(item.cashRefund),
            method: item.refundMethod,
            userName: item.userName,
        }));
        const purchases = data.receipts.map(receipt => ({
            id: `purchase-${receipt.id}`,
            date: receipt.date,
            createdAt: receipt.createdAt,
            direction: 'Keluar',
            category: 'Pembelian persediaan',
            description: `Faktur ${receipt.invoice} · ${receipt.productName} · ${receipt.distributor}`,
            amount: Number(receipt.total),
            method: receipt.method || '—',
            userName: receipt.userName,
        }));
        return [...sales, ...collections, ...refunds, ...purchases, ...data.cashEntries].sort((first, second) =>
            `${first.date} ${first.createdAt}`.localeCompare(`${second.date} ${second.createdAt}`));
    }

    function recordSaleAdjustment(sale, type, items, reason, refundMethod, requestId = null) {
        const amount = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
        if (!Number.isSafeInteger(amount) || amount <= 0) return false;
        const now = new Date().toISOString();
        const request = requestId ? data.returns.find(item => item.id === requestId) : null;
        let cashRefund = amount;
        const receivable = sale.payment === 'Bon' ? data.receivables.find(item => item.saleId === sale.id) : null;
        if (sale.payment === 'Bon') {
            if (!receivable) return false;
            const writtenOff = data.receivableWriteoffs.filter(item => item.receivableId === receivable.id)
                .reduce((sum, item) => sum + Number(item.amount), 0);
            const outstanding = Math.max(0, Number(receivable.total) - Number(receivable.paid) - writtenOff);
            cashRefund = Math.max(0, amount - outstanding);
            receivable.total = Math.max(0, Number(receivable.total) - amount);
            receivable.paid = Math.max(0, Number(receivable.paid) - cashRefund);
            sale.paidAmount = Math.max(0, Number(sale.paidAmount) - cashRefund);
            sale.status = receivable.total <= receivable.paid ? 'Lunas' : 'Piutang';
        }
        const adjustment = {
            id: requestId || `${Date.now()}-return-${Math.random().toString(36).slice(2, 7)}`,
            saleId: sale.id,
            number: sale.number,
            type,
            status: 'Disetujui',
            amount,
            cashRefund,
            refundMethod,
            items: items.map(item => ({
                productId: item.productId,
                name: item.name,
                quantity: item.quantity,
                price: item.price,
                cost: item.cost,
                amount: item.price * item.quantity,
                restocked: item.restocked,
            })),
            reason,
            date: todayKey(),
            createdAt: now,
            userId: request?.userId || currentUser().id,
            userName: request?.userName || currentUser().name,
            approvedBy: currentUser().name,
            approvedAt: now,
        };
        items.forEach(item => {
            const product = data.products.find(entry => entry.id === item.productId);
            const delta = item.restocked ? item.quantity : 0;
            const location = sale.type === 'Grosir' ? 'Gudang' : 'Toko';
            if (product && delta) {
                const stockField = location === 'Gudang' ? 'warehouse' : 'shop';
                product[stockField] = Number(product[stockField]) + delta;
            }
            data.stockMovements.push({
                id: `${adjustment.id}-${item.productId}`,
                productId: item.productId,
                productCode: product?.code || item.code,
                productName: item.name,
                type: type === 'Void' ? 'Void penjualan' : 'Retur penjualan',
                location,
                delta,
                unit: item.unit,
                loss: item.restocked ? 0 : item.cost * item.quantity,
                reason: `${sale.number} · ${reason}${item.restocked ? '' : ' · barang tidak layak jual'}`,
                userName: currentUser().name,
                createdAt: now,
            });
        });
        const requestIndex = requestId ? data.returns.findIndex(item => item.id === requestId) : -1;
        if (requestIndex >= 0) data.returns[requestIndex] = adjustment;
        else data.returns.push(adjustment);
        if (type === 'Void') {
            sale.status = 'Void';
        } else if (returnedAmount(sale.id) >= Number(sale.total)) {
            sale.status = 'Retur Penuh';
        } else {
            sale.status = receivable && receivable.total > receivable.paid ? 'Piutang' : 'Retur Sebagian';
        }
        return adjustment;
    }

    function periodForm() {
        return `<form class="toolbar report-period-form" id="report-period-form">
            <label class="muted" for="report-from">Periode</label>
            <input class="control" id="report-from" name="from" type="date" value="${escapeHtml(uiState.reportFrom)}" required>
            <span class="muted">s.d.</span>
            <input class="control" id="report-to" name="to" type="date" value="${escapeHtml(uiState.reportTo)}" required>
            <button class="button button-quiet" type="submit">Terapkan</button>
        </form>`;
    }

    function downloadCsv(filename, rows) {
        const csv = rows.map(row => row.map(value => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
        const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 0);
    }

    function activeProducts() {
        return data.products.filter(product => product.active !== false);
    }

    const viewContext = {
        data,
        escapeHtml,
        pageHeading,
        formatRupiah,
        todayKey,
        dateAfter,
        dateTime,
        saleDate,
        returnsForSale,
        returnedQuantity,
        returnedAmount,
        netSaleRevenue,
        netSaleCost,
        netSaleProfit,
        inReportRange,
        canManageSales,
        canManageProducts,
        activeProducts,
        cashMovements,
        periodForm,
        downloadCsv,
        uiState,
        posCarts,
    };
    const dashboardViews = createSharedDashboardModule(viewContext);
    const inventoryViews = createSharedInventoryModule(viewContext);
    const salesViews = createCashierSalesModule(viewContext);
    const warehouseViews = createWarehouseInventoryModule(viewContext);
    const financeViews = createOwnerFinanceModule(viewContext);
    const administrationViews = createOwnerAdministrationModule(viewContext);

    function renderPage(user, page) {
        const content = document.getElementById('page-content');
        if (!content) return;
        if (page === 'users' && isOwner(user)) content.innerHTML = administrationViews.usersPage();
        else if (page === 'activity' && isOwner(user)) content.innerHTML = administrationViews.activityPage();
        else if (page === 'customers' && isOwner(user)) content.innerHTML = administrationViews.customersPage();
        else if (page === 'kasir-eceran' && canSell(user)) content.innerHTML = salesViews.posPage('Eceran', user);
        else if (page === 'kasir-grosir' && canSell(user)) content.innerHTML = salesViews.posPage('Grosir', user);
        else if (page === 'laporan-penjualan' && canManageSales(user)) content.innerHTML = salesViews.salesReportPage();
        else if (page === 'void-retur' && canManageSales(user)) content.innerHTML = salesViews.voidReturnPage(user);
        else if (page === 'piutang' && canManageSales(user)) content.innerHTML = salesViews.receivablesPage(user);
        else if (page === 'stok-barang') content.innerHTML = inventoryViews.stockPage(user);
        else if (page === 'barang-masuk-mutasi' && canManageProducts(user)) content.innerHTML = warehouseViews.inventoryMovementPage();
        else if (page === 'arus-kas' && isOwner(user)) content.innerHTML = financeViews.cashFlowPage();
        else if (page === 'laba-rugi-arsip' && isOwner(user)) content.innerHTML = financeViews.profitArchivePage();
        else content.innerHTML = dashboardViews.dashboard(user);
        bindPageEvents(user, page);
        if (page !== 'dashboard') {
            content.insertAdjacentHTML('beforeend', `<div class="footer">© ${new Date().getFullYear()} Toko Mira Plastik · Tahap 3: Penjualan Eceran & Grosir</div>`);
        }
    }

    function refresh(page = 'dashboard') {
        save();
        const user = currentUser();
        if (!user) return renderLogin();
        if ((page === 'users' || page === 'activity' || page === 'customers') && !isOwner(user)) page = 'dashboard';
        if (page === 'barang-masuk-mutasi' && !canManageProducts(user)) page = 'dashboard';
        if ((page === 'kasir-eceran' || page === 'kasir-grosir' || page === 'piutang' || page === 'laporan-penjualan' || page === 'void-retur') && !canManageSales(user)) page = 'dashboard';
        if ((page === 'arus-kas' || page === 'laba-rugi-arsip') && !isOwner(user)) page = 'dashboard';
        renderShell(user, page);
    }

    function bindPageEvents(user, page) {
        document.getElementById('customer-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat mengelola pelanggan.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const creditLimit = Number(values.creditLimit);
            const creditDays = Number(values.creditDays);
            if (!values.name.trim() || !Number.isSafeInteger(creditLimit) || creditLimit < 0
                || !Number.isInteger(creditDays) || creditDays < 1 || creditDays > 90
                || data.customers.some(customer => customer.name.trim().toLowerCase() === values.name.trim().toLowerCase())) {
                return notify('Nama pelanggan harus unik; plafon dan tenor harus bernilai valid.');
            }
            data.customers.push({
                id: `${Date.now()}-customer-${Math.random().toString(36).slice(2, 7)}`,
                name: values.name.trim(),
                phone: values.phone.trim(),
                creditLimit,
                creditDays,
                active: true,
                createdAt: new Date().toISOString(),
            });
            addActivity(`Menambahkan pelanggan grosir ${values.name.trim()} dengan plafon ${formatRupiah(creditLimit)}.`);
            notify('Pelanggan grosir berhasil ditambahkan.');
            refresh('customers');
        });
        document.getElementById('customer-credit-form')?.addEventListener('submit', async event => {
            event.preventDefault();
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat mengatur limit pelanggan.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const customer = data.customers.find(item => item.id === values.customerId);
            const creditLimit = Number(values.creditLimit);
            const creditDays = Number(values.creditDays);
            const outstanding = customer ? data.receivables
                .filter(item => item.customerId === customer.id)
                .reduce((sum, item) => sum + Math.max(0, Number(item.total) - Number(item.paid)
                    - data.receivableWriteoffs.filter(writeoff => writeoff.receivableId === item.id)
                        .reduce((total, writeoff) => total + Number(writeoff.amount), 0)), 0) : 0;
            if (!customer || !Number.isSafeInteger(creditLimit) || creditLimit < outstanding
                || !Number.isInteger(creditDays) || creditDays < 1 || creditDays > 90) {
                return notify(`Limit harus berupa bilangan bulat minimal sebesar saldo piutang (${formatRupiah(outstanding)}); tenor harus 1–90 hari.`);
            }
            customer.creditLimit = creditLimit;
            customer.creditDays = creditDays;
            data.customerEditId = '';
            await addActivity(`Mengatur plafon ${formatRupiah(creditLimit)} dan tenor ${creditDays} hari untuk pelanggan ${customer.name}.`);
            if (stateSyncFailed) return;
            notify('Limit dan tenor pelanggan berhasil diperbarui.');
            refresh('customers');
        });
        document.getElementById('pos-customer-create-form')?.addEventListener('submit', async event => {
            event.preventDefault();
            const user = currentUser();
            if (!canSell(user) || page !== 'kasir-grosir') return notify('Pendaftaran pelanggan hanya tersedia untuk transaksi grosir.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const name = values.name.trim();
            const owner = isOwner(user);
            const creditLimit = owner ? Number(values.creditLimit) : 0;
            const creditDays = owner ? Number(values.creditDays) : 7;
            if (!name || data.customers.some(customer => customer.name.trim().toLowerCase() === name.toLowerCase())
                || !Number.isSafeInteger(creditLimit) || creditLimit < 0
                || !Number.isInteger(creditDays) || creditDays < 1 || creditDays > 90) {
                return notify('Nama pelanggan harus unik; limit dan tenor harus bernilai valid.');
            }
            const customer = {
                id: `${Date.now()}-customer-${Math.random().toString(36).slice(2, 7)}`,
                name,
                phone: values.phone.trim(),
                creditLimit,
                creditDays,
                active: true,
                createdAt: new Date().toISOString(),
            };
            data.customers.push(customer);
            uiState.selectedCustomerId = customer.id;
            await addActivity(`${owner ? 'Menambahkan' : 'Mendaftarkan'} pelanggan grosir ${name}${owner ? ` dengan plafon ${formatRupiah(creditLimit)}.` : ' dengan limit kredit menunggu persetujuan Owner.'}`);
            if (stateSyncFailed) return;
            notify(owner && creditLimit > 0
                ? 'Pelanggan berhasil ditambahkan dan limit kredit diaktifkan.'
                : 'Pelanggan berhasil didaftarkan. Owner perlu menetapkan limit sebelum transaksi Bon.');
            renderPage(currentUser(), 'kasir-grosir');
        });
        document.getElementById('export-backup')?.addEventListener('click', () => {
            const owner = currentUser();
            if (!isOwner(owner)) return notify('Hanya Owner yang dapat membuat backup data.');
            const backup = {
                format: BACKUP_FORMAT,
                version: 2,
                exportedAt: new Date().toISOString(),
                data: Object.fromEntries(BACKUP_COLLECTIONS.map(key => [
                    key,
                    key === 'users' ? data.users.map(({ password, ...user }) => user) : data[key],
                ])),
            };
            const blob = new Blob([JSON.stringify(backup, null, 2)], { type: 'application/json;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const now = new Date();
            const stamp = `${todayKey()}-${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}`;
            link.href = url;
            link.download = `backup-toko-mira-plastik-${stamp}.json`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            notify('Backup berhasil diunduh. Simpan file di tempat yang aman.');
        });
        document.getElementById('backup-restore-form')?.addEventListener('submit', async event => {
            event.preventDefault();
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat memulihkan backup data.');
            const file = document.getElementById('backup-file')?.files?.[0];
            if (!file) return notify('Pilih file backup terlebih dahulu.');
            if (file.size > 20 * 1024 * 1024) return notify('Ukuran file backup tidak boleh lebih dari 20 MB.');
            let backup;
            try {
                backup = JSON.parse(await file.text());
            } catch (error) {
                console.error('File backup tidak dapat dibaca sebagai JSON.', error);
                return notify('File backup tidak dapat dibaca. Pastikan file JSON valid.');
            }
            let restoredData;
            try {
                restoredData = validateBackup(backup);
            } catch (error) {
                return notify(error.message);
            }
            if (!isOwner(currentUser())) return notify('Sesi Owner tidak lagi aktif. Silakan masuk kembali.');
            const recordCount = BACKUP_COLLECTIONS.reduce((sum, key) => sum + restoredData[key].length, 0);
            if (!window.confirm(`Pulihkan ${recordCount} catatan dari backup? Data operasional di server akan diganti. Akun server tidak berubah.`)) return;
            const owner = currentUser();
            restoredData.users = data.users;
            restoredData.activity.unshift({
                id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
                userId: owner.id,
                name: owner.name,
                role: owner.role,
                message: `Memulihkan data dari backup ${file.name}.`,
                createdAt: new Date().toISOString(),
            });
            restoredData.activity = restoredData.activity.slice(0, 100);
            const previousData = Object.fromEntries(Object.keys(data).map(key => [key, data[key]]));
            Object.keys(data).forEach(key => delete data[key]);
            Object.assign(data, restoredData, { userForm: false });
            if (!await save()) {
                Object.keys(data).forEach(key => delete data[key]);
                Object.assign(data, previousData);
                renderShell(owner, 'users');
                return;
            }
            notify('Data server berhasil dipulihkan. Akun server tidak diubah.');
            renderShell(owner, 'users');
        });
        document.getElementById('backup-merge-form')?.addEventListener('submit', async event => {
            event.preventDefault();
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat menggabungkan backup.');
            const file = document.getElementById('backup-merge-file')?.files?.[0];
            if (!file) return notify('Pilih file backup terlebih dahulu.');
            if (file.size > 20 * 1024 * 1024) return notify('Ukuran file backup tidak boleh lebih dari 20 MB.');

            let importedData;
            try {
                importedData = validateBackup(JSON.parse(await file.text()));
            } catch (error) {
                console.error('Backup tidak dapat divalidasi untuk penggabungan.', error);
                return notify(error.message || 'File backup tidak valid.');
            }

            let result;
            try {
                result = mergeBackupData(data, importedData);
            } catch (error) {
                return notify(error.message || 'Backup tidak dapat digabungkan karena ada konflik data.');
            }
            if (result.added === 0) {
                return notify(`Tidak ada catatan baru. ${result.duplicates} catatan identik sudah ada di server.`);
            }
            if (!isOwner(currentUser())) return notify('Sesi Owner tidak lagi aktif. Silakan masuk kembali.');
            if (!window.confirm(
                `Gabungkan ${result.added} catatan baru dari ${file.name}? `
                + `${result.duplicates} catatan identik akan dilewati. Data server tidak akan ditimpa.`,
            )) return;

            const owner = currentUser();
            const previousData = Object.fromEntries(STATE_COLLECTIONS.map(key => [key, data[key]]));
            result.data.activity.unshift({
                id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
                userId: owner.id,
                name: owner.name,
                role: owner.role,
                message: `Menggabungkan ${result.added} catatan dari backup ${file.name}.`,
                createdAt: new Date().toISOString(),
            });
            Object.assign(data, result.data);
            if (!await save()) {
                STATE_COLLECTIONS.forEach(key => {
                    data[key] = previousData[key];
                });
                renderShell(owner, 'users');
                return;
            }
            notify(`${result.added} catatan baru berhasil digabungkan; ${result.duplicates} duplikat identik dilewati.`);
            renderShell(owner, 'users');
        });
        document.getElementById('report-period-form')?.addEventListener('submit', event => {
            event.preventDefault();
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            if (!values.from || !values.to || values.from > values.to) return notify('Rentang tanggal laporan tidak valid.');
            uiState.reportFrom = values.from;
            uiState.reportTo = values.to;
            renderPage(currentUser(), page);
        });
        document.getElementById('sale-adjustment-form')?.addEventListener('change', event => {
            if (event.target.id !== 'adjustment-sale') return;
            uiState.selectedReturnSaleId = event.target.value;
            renderPage(currentUser(), page);
        });
        document.getElementById('sale-adjustment-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!canManageSales(currentUser())) return notify('Anda tidak memiliki akses untuk memproses void atau retur.');
            const type = event.submitter?.value;
            if (!['return', 'void'].includes(type)) return notify('Pilih jenis penyesuaian yang valid.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const sale = data.sales.find(item => item.id === values.saleId && item.status !== 'Void');
            const reason = values.reason.trim();
            if (!sale || !reason || !['Tunai', 'Transfer'].includes(values.refundMethod)) {
                return notify('Pilih struk, metode pengembalian dana, dan isi alasan void / retur.');
            }
            if (type === 'void' && returnsForSale(sale.id).length) {
                return notify('Struk yang sudah memiliki retur parsial tidak dapat di-void seluruhnya.');
            }
            const restockByProduct = new Map([...document.querySelectorAll('.return-restock')]
                .map(input => [input.dataset.productId, input.checked]));
            const selectedItems = [];
            for (const item of sale.items || []) {
                const remaining = Number(item.quantity) - returnedQuantity(sale.id, item.productId);
                const quantityInput = [...document.querySelectorAll('.return-quantity')]
                    .find(input => input.dataset.productId === item.productId);
                const quantity = type === 'void' ? remaining : Number(quantityInput?.value || 0);
                if (!Number.isInteger(quantity) || quantity < 0 || quantity > remaining) {
                    return notify(`Jumlah retur untuk ${item.name} tidak valid.`);
                }
                if (quantity) {
                    selectedItems.push({
                        productId: item.productId,
                        code: item.code,
                        name: item.name,
                        unit: item.unit,
                        quantity,
                        price: Number(item.price),
                        cost: Number(item.cost || 0),
                        restocked: restockByProduct.get(item.productId) !== false,
                    });
                }
            }
            if (!selectedItems.length) return notify('Masukkan setidaknya satu jumlah barang yang akan diretur.');
            const owner = isOwner(currentUser());
            if (type === 'void' && !window.confirm(`${owner ? 'Proses' : 'Ajukan'} void seluruh transaksi ${sale.number}?`)) return;
            if (!owner) {
                const amount = selectedItems.reduce((sum, item) => sum + item.price * item.quantity, 0);
                data.returns.push({
                    id: `${Date.now()}-request-${Math.random().toString(36).slice(2, 7)}`,
                    saleId: sale.id,
                    number: sale.number,
                    type: type === 'void' ? 'Void' : 'Retur',
                    status: 'Menunggu Persetujuan',
                    amount,
                    items: selectedItems.map(item => ({
                        productId: item.productId,
                        code: item.code,
                        name: item.name,
                        unit: item.unit,
                        quantity: item.quantity,
                        price: item.price,
                        cost: item.cost,
                        restocked: item.restocked,
                    })),
                    reason,
                    refundMethod: values.refundMethod,
                    date: todayKey(),
                    createdAt: new Date().toISOString(),
                    userId: currentUser().id,
                    userName: currentUser().name,
                });
                addActivity(`Mengajukan ${type === 'void' ? 'void' : 'retur'} struk ${sale.number} sebesar ${formatRupiah(amount)} untuk persetujuan Owner.`);
                notify('Permintaan tersimpan dan menunggu persetujuan Owner.');
                uiState.selectedReturnSaleId = '';
                refresh('void-retur');
                return;
            }
            const adjustment = recordSaleAdjustment(
                sale,
                type === 'void' ? 'Void' : 'Retur',
                selectedItems,
                reason,
                values.refundMethod,
            );
            if (!adjustment) return notify('Penyesuaian gagal diproses. Periksa status piutang dan nilai transaksi.');
            addActivity(`${type === 'void' ? 'Membatalkan' : 'Mencatat retur'} struk ${sale.number} sebesar ${formatRupiah(adjustment.amount)}; pengembalian dana ${formatRupiah(adjustment.cashRefund)}.`);
            notify(`${type === 'void' ? 'Void' : 'Retur'} ${sale.number} berhasil dicatat.`);
            uiState.selectedReturnSaleId = '';
            refresh('void-retur');
        });
        document.getElementById('cash-entry-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat mencatat arus kas manual.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const amount = Number(values.amount);
            const category = values.category.trim();
            const description = values.description.trim();
            if (!['Masuk', 'Keluar'].includes(values.direction) || !['Tunai', 'Transfer'].includes(values.method)
                || !Number.isSafeInteger(amount) || amount <= 0 || !category || !description
                || !/^\d{4}-\d{2}-\d{2}$/.test(values.date)) {
                return notify('Lengkapi data kas dengan tanggal dan jumlah yang valid.');
            }
            const now = new Date().toISOString();
            data.cashEntries.push({
                id: `${Date.now()}-cash-${Math.random().toString(36).slice(2, 7)}`,
                date: values.date,
                createdAt: now,
                direction: values.direction,
                category,
                description,
                amount,
                method: values.method,
                userName: currentUser().name,
            });
            addActivity(`Mencatat kas ${values.direction.toLowerCase()} ${formatRupiah(amount)} (${category}).`);
            notify('Catatan arus kas berhasil disimpan.');
            refresh('arus-kas');
        });
        document.getElementById('export-sales')?.addEventListener('click', () => {
            const rows = [['Tanggal', 'Nomor struk', 'Jenis', 'Pembayaran', 'Kasir', 'Penjualan bersih', 'Laba kotor', 'Status']];
            data.sales.filter(sale => inReportRange(saleDate(sale))).forEach(sale => rows.push([
                saleDate(sale), sale.number, sale.type, sale.payment, sale.userName,
                netSaleRevenue(sale), netSaleProfit(sale), sale.status,
            ]));
            downloadCsv(`laporan-penjualan-${uiState.reportFrom}-${uiState.reportTo}.csv`, rows);
        });
        document.getElementById('export-cash')?.addEventListener('click', () => {
            const rows = [['Tanggal', 'Arah', 'Kategori', 'Keterangan', 'Metode', 'Jumlah', 'Petugas']];
            cashMovements().filter(item => inReportRange(item.date)).forEach(item => rows.push([
                item.date, item.direction, item.category, item.description, item.method, item.amount, item.userName,
            ]));
            downloadCsv(`arus-kas-${uiState.reportFrom}-${uiState.reportTo}.csv`, rows);
        });
        document.getElementById('export-archive')?.addEventListener('click', () => {
            const rows = [['Tanggal', 'Jenis dokumen', 'Nomor', 'Rincian', 'Nilai', 'Status']];
            data.sales.filter(sale => inReportRange(saleDate(sale))).forEach(sale => rows.push([
                saleDate(sale), 'Penjualan', sale.number, sale.customer || 'Umum', netSaleRevenue(sale), sale.status,
            ]));
            data.returns.filter(item => inReportRange(item.date)).forEach(item => rows.push([
                item.date, item.type, item.number, item.reason, item.amount, `Pengembalian ${item.cashRefund}`,
            ]));
            data.receipts.filter(item => inReportRange(item.date)).forEach(item => rows.push([
                item.date, 'Barang masuk', item.invoice, `${item.productName} · ${item.distributor}`, item.total, 'Tercatat',
            ]));
            downloadCsv(`arsip-operasional-${uiState.reportFrom}-${uiState.reportTo}.csv`, rows);
        });
        document.getElementById('print-report')?.addEventListener('click', () => window.print());
        document.getElementById('pos-search')?.addEventListener('input', event => {
            uiState.posQuery = event.target.value;
            const cursor = event.target.selectionStart;
            renderPage(user, page);
            const search = document.getElementById('pos-search');
            search?.focus();
            search?.setSelectionRange(cursor, cursor);
        });
        document.getElementById('pos-add-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!canSell(currentUser())) return notify('Anda tidak memiliki akses untuk membuat transaksi.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const product = data.products.find(item => item.id === values.productId && item.active !== false);
            const type = page === 'kasir-grosir' ? 'Grosir' : 'Eceran';
            const quantity = Number(values.quantity);
            if (!product || !Number.isInteger(quantity) || quantity < 1) return notify('Pilih barang dan masukkan jumlah bilangan bulat yang valid.');
            const cart = posCarts[type];
            const existing = cart.find(item => item.productId === product.id);
            const available = Number(product.shop) + (type === 'Grosir' ? Number(product.warehouse) : 0);
            if (quantity + Number(existing?.quantity || 0) > available) {
                return notify(`Stok ${product.name} hanya ${available} ${product.unit}${type === 'Grosir' ? ' (gudang diprioritaskan).' : '.'}`);
            }
            if (existing) existing.quantity = Number(existing.quantity) + quantity;
            else cart.push({ productId: product.id, quantity });
            renderPage(currentUser(), page);
        });
        document.querySelectorAll('.pos-quantity').forEach(input => input.addEventListener('change', event => {
            if (!canSell(currentUser())) return notify('Anda tidak memiliki akses untuk mengubah transaksi.');
            const type = page === 'kasir-grosir' ? 'Grosir' : 'Eceran';
            const index = Number(event.target.dataset.cartIndex);
            const line = posCarts[type][index];
            const product = data.products.find(item => item.id === line?.productId && item.active !== false);
            const quantity = Number(event.target.value);
            const available = product ? Number(product.shop) + (type === 'Grosir' ? Number(product.warehouse) : 0) : 0;
            if (!line || !product || !Number.isInteger(quantity) || quantity < 1 || quantity > available) {
                notify('Jumlah barang harus bilangan bulat dan tidak boleh melebihi stok yang tersedia.');
                return renderPage(currentUser(), page);
            }
            line.quantity = quantity;
            renderPage(currentUser(), page);
        }));
        document.getElementById('pos-payment')?.addEventListener('change', event => {
            const onCredit = event.target.value === 'Bon';
            const onCash = event.target.value === 'Tunai';
            document.getElementById('pos-cash-field')?.classList.toggle('hidden', !onCash);
            document.getElementById('pos-credit-fields')?.classList.toggle('hidden', !onCredit);
            const cash = document.getElementById('pos-cash');
            const customer = document.getElementById('pos-customer');
            const dueDate = document.getElementById('pos-due-date');
            if (cash) cash.required = onCash;
            if (customer) customer.required = onCredit;
            if (dueDate) dueDate.required = onCredit;
            const change = document.getElementById('pos-change');
            if (change) change.classList.toggle('hidden', !onCash);
        });
        document.getElementById('pos-customer')?.addEventListener('change', event => {
            const customer = data.customers.find(item => item.id === event.target.value && item.active !== false);
            if (page === 'kasir-grosir') {
                uiState.selectedCustomerId = customer?.id || '';
                const creditStatus = document.getElementById('pos-customer-credit-status');
                if (creditStatus) {
                    creditStatus.textContent = customer && Number(customer.creditLimit) <= 0
                        ? 'Bon belum dapat digunakan. Minta Owner menetapkan limit dan tenor pelanggan ini.'
                        : '';
                }
            }
            const dueDate = document.getElementById('pos-due-date');
            if (!customer || !dueDate) return;
            const maxDueDate = dateAfter(Math.max(1, Number(customer.creditDays) || 1));
            dueDate.max = maxDueDate;
            if (dueDate.value > maxDueDate) dueDate.value = maxDueDate;
        });
        document.getElementById('pos-cash')?.addEventListener('input', event => {
            const total = Number(document.getElementById('pos-checkout-form')?.dataset.total || 0);
            const change = Math.max(0, Number(event.target.value || 0) - total);
            const output = document.getElementById('pos-change');
            if (output) output.textContent = `Kembalian: ${formatRupiah(change)}`;
        });
        document.getElementById('pos-checkout-form')?.addEventListener('submit', event => {
            event.preventDefault();
            const cashier = currentUser();
            if (!canSell(cashier)) return notify('Anda tidak memiliki akses untuk membuat transaksi.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const type = event.currentTarget.dataset.type;
            const cart = posCarts[type];
            if (!cart?.length) return notify('Keranjang masih kosong.');
            const lines = cart.map(line => {
                const product = data.products.find(item => item.id === line.productId && item.active !== false);
                const wholesale = type === 'Grosir' && product && Number(line.quantity) >= Number(product.wholesaleMin);
                return {
                    product,
                    quantity: Number(line.quantity),
                    price: product ? Number(wholesale ? product.wholesale : product.retail) : 0,
                };
            });
            if (lines.some(line => !line.product || !Number.isInteger(line.quantity) || line.quantity < 1)) {
                return notify('Keranjang memiliki barang tidak tersedia atau jumlah tidak valid.');
            }
            const quantities = new Map();
            lines.forEach(line => quantities.set(line.product.id, (quantities.get(line.product.id) || 0) + line.quantity));
            for (const [productId, quantity] of quantities) {
                const product = data.products.find(item => item.id === productId);
                const available = Number(product.shop) + (type === 'Grosir' ? Number(product.warehouse) : 0);
                if (quantity > available) return notify(`Stok tidak cukup untuk ${product.name}; tersedia ${available} ${product.unit}.`);
            }
            const total = lines.reduce((sum, line) => sum + line.price * line.quantity, 0);
            if (!Number.isSafeInteger(total) || total <= 0) return notify('Total transaksi tidak valid.');
            const payment = values.payment;
            if (!['Tunai', 'Transfer', 'Bon'].includes(payment)) return notify('Pilih metode pembayaran yang valid.');
            const cashReceived = payment === 'Tunai' ? Number(values.cash) : 0;
            if (payment === 'Tunai' && (!Number.isSafeInteger(cashReceived) || cashReceived < total)) {
                return notify('Uang diterima harus berupa bilangan bulat dan minimal sebesar total belanja.');
            }
            const customerRecord = data.customers.find(item => item.id === values.customerId && item.active !== false);
            const customer = customerRecord?.name || (type === 'Eceran' ? values.customer?.trim() || '' : '');
            if (payment === 'Bon' && type === 'Grosir' && customerRecord && Number(customerRecord.creditLimit) <= 0) {
                return notify('Pelanggan ini belum memiliki limit kredit. Minta Owner menetapkan limit dan tenor terlebih dahulu.');
            }
            if (payment === 'Bon' && (!customer || !values.dueDate || values.dueDate < todayKey())) {
                return notify(type === 'Grosir'
                    ? 'Bon grosir harus menggunakan pelanggan terdaftar dan tanggal jatuh tempo yang valid.'
                    : 'Isi nama pelanggan dan tanggal jatuh tempo hari ini atau setelahnya.');
            }
            if (payment === 'Bon' && type === 'Grosir') {
                const outstanding = data.receivables
                    .filter(item => item.customerId === customerRecord.id)
                    .reduce((sum, item) => {
                        const writtenOff = data.receivableWriteoffs.filter(writeoff => writeoff.receivableId === item.id)
                            .reduce((totalWrittenOff, writeoff) => totalWrittenOff + Number(writeoff.amount), 0);
                        return sum + Math.max(0, Number(item.total) - Number(item.paid) - writtenOff);
                    }, 0);
                if (outstanding + total > Number(customerRecord.creditLimit)) {
                    return notify(`Transaksi melewati sisa plafon pelanggan ${customerRecord.name}.`);
                }
                const maxDue = dateAfter(Math.max(1, Number(customerRecord.creditDays) || 1));
                if (values.dueDate > maxDue) return notify(`Tenor kredit pelanggan maksimal sampai ${maxDue}.`);
            }
            const number = `TMP-${String(data.sales.length + 1).padStart(6, '0')}`;
            const now = new Date().toISOString();
            const saleItems = lines.map(line => {
                const warehouseQuantity = type === 'Grosir'
                    ? Math.min(Number(line.product.warehouse), line.quantity)
                    : 0;
                const shopQuantity = line.quantity - warehouseQuantity;
                const stockAllocations = [
                    ...(warehouseQuantity ? [{ location: 'Gudang', quantity: warehouseQuantity }] : []),
                    ...(shopQuantity ? [{ location: 'Toko', quantity: shopQuantity }] : []),
                ];
                return {
                    productId: line.product.id,
                    code: line.product.code,
                    name: line.product.name,
                    unit: line.product.unit,
                    quantity: line.quantity,
                    price: line.price,
                    subtotal: line.price * line.quantity,
                    cost: Number(line.product.buy),
                    stockAllocations,
                };
            });
            const profit = saleItems.reduce((sum, item) => sum + (item.price - item.cost) * item.quantity, 0);
            const sale = {
                id: `${Date.now()}-sale-${Math.random().toString(36).slice(2, 7)}`,
                number,
                type,
                payment,
                status: payment === 'Bon' ? 'Piutang' : 'Lunas',
                customer: customer || null,
                customerId: customerRecord?.id || null,
                dueDate: payment === 'Bon' ? values.dueDate : null,
                date: todayKey(),
                total,
                profit,
                paidAmount: payment === 'Bon' ? 0 : total,
                cashReceived: payment === 'Tunai' ? cashReceived : null,
                change: payment === 'Tunai' ? cashReceived - total : 0,
                items: saleItems,
                userId: cashier.id,
                userName: cashier.name,
                createdAt: now,
            };
            lines.forEach(line => {
                const item = saleItems.find(saleItem => saleItem.productId === line.product.id);
                item.stockAllocations.forEach(allocation => {
                    const stockField = allocation.location === 'Gudang' ? 'warehouse' : 'shop';
                    line.product[stockField] = Number(line.product[stockField]) - allocation.quantity;
                    data.stockMovements.push({
                        id: `${sale.id}-${line.product.id}-${allocation.location}`,
                        productId: line.product.id,
                        productCode: line.product.code,
                        productName: line.product.name,
                        type: 'Penjualan',
                        location: allocation.location,
                        delta: -allocation.quantity,
                        unit: line.product.unit,
                        loss: 0,
                        reason: `Struk ${number}`,
                        userName: cashier.name,
                        createdAt: now,
                    });
                });
            });
            data.sales.push(sale);
            if (payment === 'Bon') {
                data.receivables.push({
                    id: sale.id,
                    saleId: sale.id,
                    number,
                    customer,
                    customerId: customerRecord?.id || null,
                    total,
                    paid: 0,
                    dueDate: values.dueDate,
                    date: todayKey(),
                    createdAt: now,
                    payments: [],
                });
            }
            posCarts[type] = [];
            if (type === 'Grosir') uiState.selectedCustomerId = '';
            uiState.posQuery = '';
            addActivity(`Mencatat penjualan ${type.toLowerCase()} ${number} senilai ${formatRupiah(total)} melalui ${payment}${customer ? ` untuk ${customer}` : ''}.`);
            notify(`Transaksi ${number} berhasil disimpan. ${payment === 'Tunai' ? `Kembalian ${formatRupiah(sale.change)}.` : ''}`);
            refresh(page);
        });
        document.getElementById('receivable-id')?.addEventListener('change', event => {
            const item = data.receivables.find(entry => entry.id === event.target.value);
            const amount = document.getElementById('receivable-amount');
            if (amount && item) {
                const writtenOff = data.receivableWriteoffs.filter(entry => entry.receivableId === item.id)
                    .reduce((sum, entry) => sum + Number(entry.amount), 0);
                amount.max = String(Math.max(0, Number(item.total) - Number(item.paid) - writtenOff));
            }
        });
        document.getElementById('receivable-payment-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!canManageSales(currentUser())) return notify('Anda tidak memiliki akses untuk mencatat pembayaran piutang.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const receivable = data.receivables.find(item => item.id === values.receivableId);
            const amount = Number(values.amount);
            const writtenOff = receivable ? data.receivableWriteoffs.filter(item => item.receivableId === receivable.id)
                .reduce((sum, item) => sum + Number(item.amount), 0) : 0;
            const outstanding = receivable ? Number(receivable.total) - Number(receivable.paid) - writtenOff : 0;
            if (!receivable || !['Tunai', 'Transfer'].includes(values.method)
                || !Number.isSafeInteger(amount) || amount <= 0 || amount > outstanding) {
                return notify('Jumlah pembayaran harus lebih dari nol dan tidak boleh melebihi sisa piutang.');
            }
            const userNow = currentUser();
            receivable.paid = Number(receivable.paid) + amount;
            receivable.payments.push({
                id: `${Date.now()}-payment-${Math.random().toString(36).slice(2, 6)}`,
                amount,
                method: values.method,
                userName: userNow.name,
                date: todayKey(),
                createdAt: new Date().toISOString(),
            });
            const sale = data.sales.find(item => item.id === receivable.saleId);
            if (sale) {
                sale.paidAmount = Number(sale.paidAmount) + amount;
                if (receivable.paid >= receivable.total) sale.status = 'Lunas';
            }
            addActivity(`Mencatat pembayaran piutang ${receivable.number} sebesar ${formatRupiah(amount)} melalui ${values.method}.`);
            notify(receivable.paid >= receivable.total ? 'Pembayaran tersimpan; piutang telah lunas.' : 'Pembayaran sebagian berhasil dicatat.');
            refresh('piutang');
        });
        document.getElementById('writeoff-receivable')?.addEventListener('change', event => {
            const receivable = data.receivables.find(item => item.id === event.target.value);
            const amount = document.getElementById('writeoff-amount');
            if (!receivable || !amount) return;
            const paidWriteoffs = data.receivableWriteoffs.filter(item => item.receivableId === receivable.id)
                .reduce((sum, item) => sum + Number(item.amount), 0);
            amount.max = String(Math.max(0, Number(receivable.total) - Number(receivable.paid) - paidWriteoffs));
        });
        document.getElementById('receivable-writeoff-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat menghapus buku piutang.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const receivable = data.receivables.find(item => item.id === values.receivableId);
            const amount = Number(values.amount);
            const alreadyWrittenOff = receivable ? data.receivableWriteoffs.filter(item => item.receivableId === receivable.id)
                .reduce((sum, item) => sum + Number(item.amount), 0) : 0;
            const outstanding = receivable
                ? Number(receivable.total) - Number(receivable.paid) - alreadyWrittenOff
                : 0;
            const reason = values.reason.trim();
            if (!receivable || !Number.isSafeInteger(amount) || amount <= 0 || amount > outstanding || !reason) {
                return notify('Jumlah hapus buku harus valid dan tidak melebihi sisa piutang.');
            }
            data.receivableWriteoffs.push({
                id: `${Date.now()}-writeoff-${Math.random().toString(36).slice(2, 7)}`,
                receivableId: receivable.id,
                saleId: receivable.saleId,
                number: receivable.number,
                customer: receivable.customer,
                amount,
                reason,
                date: todayKey(),
                createdAt: new Date().toISOString(),
                userName: currentUser().name,
            });
            addActivity(`Menghapus buku piutang ${receivable.number} sebesar ${formatRupiah(amount)}.`);
            notify('Penghapusan buku piutang tercatat.');
            refresh('piutang');
        });
        document.getElementById('product-search')?.addEventListener('input', event => {
            uiState.productQuery = event.target.value;
            const cursor = event.target.selectionStart;
            renderPage(user, 'stok-barang');
            const search = document.getElementById('product-search');
            search?.focus();
            search?.setSelectionRange(cursor, cursor);
        });
        document.getElementById('product-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!canManageProducts(currentUser())) return notify('Anda tidak memiliki akses untuk mengubah barang.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const id = values.id || `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
            const requiredText = ['code', 'name', 'category', 'unit'];
            if (requiredText.some(key => !values[key].trim())) return notify('Lengkapi seluruh data barang.');
            if (data.products.some(product => product.code.toLowerCase() === values.code.trim().toLowerCase() && product.id !== id)) {
                return notify('Kode barang sudah dipakai produk lain.');
            }
            const product = {
                id,
                code: values.code.trim(),
                name: values.name.trim(),
                category: values.category.trim(),
                unit: values.unit.trim(),
                buy: Number(values.buy),
                retail: Number(values.retail),
                wholesale: Number(values.wholesale),
                wholesaleMin: Number(values.wholesaleMin),
                shop: Number(values.shop),
                warehouse: Number(values.warehouse),
                min: Number(values.min),
                active: true,
            };
            const nonNegative = ['buy', 'retail', 'wholesale', 'shop', 'warehouse', 'min'];
            if (nonNegative.some(key => !Number.isFinite(product[key]) || product[key] < 0)
                || !Number.isInteger(product.shop)
                || !Number.isInteger(product.warehouse)
                || !Number.isInteger(product.min)
                || !Number.isInteger(product.wholesaleMin)
                || product.wholesaleMin < 1) {
                return notify('Harga dan jumlah harus bernilai valid; stok harus bilangan bulat nonnegatif.');
            }
            const existing = data.products.findIndex(item => item.id === id);
            if (existing >= 0) {
                data.products[existing] = product;
            } else {
                data.products.push(product);
                const createdAt = new Date().toISOString();
                [['Toko', product.shop], ['Gudang', product.warehouse]].forEach(([location, quantity]) => {
                    if (Number(quantity) > 0) {
                        data.stockMovements.push({
                            id: `${Date.now()}-opening-${Math.random().toString(36).slice(2, 6)}`,
                            productId: id,
                            productCode: product.code,
                            productName: product.name,
                            type: 'Stok awal',
                            location,
                            delta: Number(quantity),
                            unit: product.unit,
                            loss: 0,
                            reason: 'Pencatatan stok awal barang',
                            userName: currentUser().name,
                            createdAt,
                        });
                    }
                });
            }
            uiState.productFormOpen = false;
            uiState.editingProductId = '';
            addActivity(`${existing >= 0 ? 'Memperbarui' : 'Menambahkan'} data barang ${product.code} — ${product.name}.`);
            notify(existing >= 0 ? 'Data barang berhasil diperbarui.' : 'Barang baru berhasil ditambahkan.');
            refresh('stok-barang');
        });
        document.getElementById('receipt-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!canManageProducts(currentUser())) return notify('Anda tidak memiliki akses untuk mencatat barang masuk.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const product = data.products.find(item => item.id === values.productId && item.active !== false);
            const quantity = Number(values.quantity);
            const price = Number(values.price);
            if (!product || !Number.isInteger(quantity) || quantity <= 0 || !Number.isFinite(price) || price < 0) {
                return notify('Barang, jumlah, atau harga pembelian tidak valid.');
            }
            if (data.receipts.some(receipt => receipt.invoice.toLowerCase() === values.invoice.trim().toLowerCase())) {
                return notify('Nomor faktur tersebut sudah pernah dicatat.');
            }
            const totalBefore = Number(product.shop) + Number(product.warehouse);
            product.buy = Math.round((Number(product.buy) * totalBefore + price * quantity) / (totalBefore + quantity));
            product.warehouse = Number(product.warehouse) + quantity;
            const distributorName = values.distributor.trim();
            let distributor = data.distributors.find(item => item.name.toLowerCase() === distributorName.toLowerCase());
            if (!distributor) {
                distributor = { id: `${Date.now()}-dist-${Math.random().toString(36).slice(2, 6)}`, name: distributorName, phone: '' };
                data.distributors.push(distributor);
            }
            const now = new Date().toISOString();
            const receipt = {
                id: `${Date.now()}-receipt-${Math.random().toString(36).slice(2, 6)}`,
                productId: product.id,
                productCode: product.code,
                productName: product.name,
                unit: product.unit,
                distributor: distributorName,
                invoice: values.invoice.trim(),
                quantity,
                price,
                total: quantity * price,
                date: values.date,
                createdAt: now,
                userName: currentUser().name,
            };
            data.receipts.push(receipt);
            data.stockMovements.push({
                id: receipt.id,
                productId: product.id,
                productCode: product.code,
                productName: product.name,
                type: 'Barang masuk',
                location: 'Gudang',
                delta: quantity,
                unit: product.unit,
                loss: 0,
                reason: `Faktur ${receipt.invoice} · ${distributorName}`,
                userName: receipt.userName,
                createdAt: now,
            });
            addActivity(`Mencatat penerimaan ${quantity} ${product.unit} ${product.name} dari ${distributorName}; faktur ${receipt.invoice}.`);
            notify('Barang masuk tercatat; stok gudang dan harga beli rata-rata diperbarui.');
            refresh('barang-masuk-mutasi');
        });
        document.getElementById('movement-type')?.addEventListener('change', event => {
            const isAdjustment = event.target.value === 'Penyesuaian';
            document.querySelector('.movement-quantity-field')?.classList.toggle('hidden', isAdjustment);
            document.querySelector('.movement-location-field')?.classList.toggle('hidden', !isAdjustment);
            document.querySelector('.movement-count-field')?.classList.toggle('hidden', !isAdjustment);
            const quantity = document.getElementById('movement-quantity');
            const physicalCount = document.getElementById('movement-count');
            if (quantity) quantity.required = !isAdjustment;
            if (physicalCount) physicalCount.required = isAdjustment;
        });
        document.getElementById('movement-form')?.addEventListener('submit', event => {
            event.preventDefault();
            if (!canManageProducts(currentUser())) return notify('Anda tidak memiliki akses untuk mengubah stok.');
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const product = data.products.find(item => item.id === values.productId && item.active !== false);
            if (!product || !values.reason.trim()) return notify('Pilih barang dan isi alasan pergerakan.');
            let delta;
            let location;
            let type;
            if (values.type === 'Mutasi') {
                const quantity = Number(values.quantity);
                if (!Number.isInteger(quantity) || quantity <= 0) return notify('Jumlah mutasi harus lebih dari nol.');
                if (Number(product.warehouse) < quantity) return notify('Stok gudang tidak cukup untuk jumlah mutasi tersebut.');
                product.warehouse = Number(product.warehouse) - quantity;
                product.shop = Number(product.shop) + quantity;
                delta = quantity;
                location = 'Gudang → Toko';
                type = 'Mutasi';
            } else {
                const counted = Number(values.physicalCount);
                if (!Number.isInteger(counted) || counted < 0) return notify('Stok fisik harus berupa bilangan bulat nol atau lebih.');
                location = values.location;
                const field = location === 'Gudang' ? 'warehouse' : 'shop';
                delta = counted - Number(product[field]);
                if (delta === 0) return notify('Jumlah fisik sesuai catatan; tidak ada penyesuaian yang perlu disimpan.');
                product[field] = counted;
                type = 'Penyesuaian';
            }
            const now = new Date().toISOString();
            const loss = type === 'Penyesuaian' && delta < 0 ? Math.abs(delta) * Number(product.buy) : 0;
            data.stockMovements.push({
                id: `${Date.now()}-move-${Math.random().toString(36).slice(2, 6)}`,
                productId: product.id,
                productCode: product.code,
                productName: product.name,
                type,
                location,
                delta,
                unit: product.unit,
                loss,
                reason: values.reason.trim(),
                userName: currentUser().name,
                createdAt: now,
            });
            addActivity(`${type} ${product.name}: ${delta > 0 ? '+' : ''}${delta} ${product.unit} (${location}).`);
            notify(type === 'Mutasi' ? 'Mutasi gudang ke toko berhasil dicatat.' : 'Penyesuaian stok berhasil dicatat.');
            refresh('barang-masuk-mutasi');
        });
        document.getElementById('user-form')?.addEventListener('submit', async event => {
            event.preventDefault();
            const values = Object.fromEntries(new FormData(event.currentTarget).entries());
            const username = values.username.trim();
            if (!roles.includes(values.role) || values.role === 'Owner') return notify('Pilih peran staf yang valid.');
            if (data.users.some(account => account.username.toLowerCase() === username.toLowerCase())) return notify('Username sudah digunakan.');
            try {
                const result = await requestApi('users', {
                    name: values.name.trim(),
                    username,
                    password: values.password,
                    role: values.role,
                });
                data.users.push(result.user);
                data.userForm = false;
                addActivity(`Membuat akun ${values.role} untuk ${values.name.trim()}.`);
                notify('Akun berhasil dibuat.');
                refresh('users');
            } catch (error) {
                console.error('Pembuatan akun server gagal.', error);
                notify(error.message || 'Akun tidak dapat dibuat.');
            }
        });
    }

    app.addEventListener('click', async event => {
        const pageLink = event.target.closest('[data-page]');
        if (pageLink) {
            event.preventDefault();
            const user = currentUser();
            if (!user) return;
            const page = pageLink.dataset.page;
            if (['kasir-eceran', 'kasir-grosir', 'piutang', 'laporan-penjualan', 'void-retur'].includes(page) && !canManageSales(user)) {
                return notify('Hanya Owner dan Kasir yang dapat mengakses penjualan, laporan, piutang, dan retur.');
            }
            if (['arus-kas', 'laba-rugi-arsip'].includes(page) && !isOwner(user)) {
                return notify('Hanya Owner yang dapat mengakses arus kas dan laporan laba rugi.');
            }
            const availablePages = ['dashboard', 'stok-barang'];
            if (canManageSales(user)) availablePages.push('kasir-eceran', 'kasir-grosir', 'laporan-penjualan', 'void-retur', 'piutang');
            if (isOwner(user)) availablePages.push('arus-kas', 'laba-rugi-arsip');
            if (canManageProducts(user)) availablePages.push('barang-masuk-mutasi');
            if (isOwner(user)) availablePages.push('users', 'activity', 'customers');
            if (!availablePages.includes(page)) {
                return notify(page === 'users' || page === 'activity'
                    ? 'Anda tidak memiliki akses ke menu tersebut.'
                    : 'Modul ini akan dibuat pada tahap berikutnya.');
            }
            if ((page === 'users' || page === 'activity' || page === 'customers') && !isOwner(user)) return notify('Anda tidak memiliki akses ke menu tersebut.');
            if (page === 'barang-masuk-mutasi' && !canManageProducts(user)) return notify('Hanya Owner dan Staf Gudang yang dapat mencatat pergerakan stok.');
            renderShell(user, page);
            return;
        }
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const action = button.dataset.action;
        if (action === 'toggle-password') {
            const password = document.getElementById('password');
            password.type = password.type === 'password' ? 'text' : 'password';
            button.textContent = password.type === 'password' ? 'Tampilkan' : 'Sembunyikan';
        } else if (action === 'forgot-password') {
            notify('Hubungi Owner untuk mengatur ulang password akun.');
        } else if (action === 'coming-soon') {
            notify('Data operasional akan tersedia setelah tahap modul terkait selesai.');
        } else if (action === 'pos-remove' || action === 'pos-clear') {
            if (!canSell(currentUser())) return notify('Anda tidak memiliki akses untuk mengubah transaksi.');
            const type = button.dataset.type;
            if (!posCarts[type]) return;
            if (action === 'pos-clear') posCarts[type] = [];
            else posCarts[type].splice(Number(button.dataset.index), 1);
            renderPage(currentUser(), type === 'Grosir' ? 'kasir-grosir' : 'kasir-eceran');
        } else if (action === 'pos-new-customer') {
            if (!canSell(currentUser())) return notify('Anda tidak memiliki akses untuk mendaftarkan pelanggan.');
            const form = document.getElementById('pos-customer-create-form');
            if (!form) return notify('Pendaftaran pelanggan hanya tersedia untuk transaksi grosir.');
            form?.classList.toggle('hidden');
            if (!form?.classList.contains('hidden')) document.getElementById('pos-new-customer-name')?.focus();
        } else if (action === 'pos-cancel-new-customer') {
            document.getElementById('pos-customer-create-form')?.classList.add('hidden');
        } else if (action === 'edit-customer-credit') {
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat mengatur limit pelanggan.');
            const customer = data.customers.find(item => item.id === button.dataset.id);
            if (!customer) return notify('Pelanggan tidak ditemukan.');
            data.customerEditId = customer.id;
            renderPage(currentUser(), 'customers');
        } else if (action === 'cancel-customer-credit') {
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat mengatur limit pelanggan.');
            data.customerEditId = '';
            renderPage(currentUser(), 'customers');
        } else if (action === 'new-product') {
            if (!canManageProducts(currentUser())) return notify('Hanya Owner dan Staf Gudang yang dapat mengelola data barang.');
            uiState.productFormOpen = true;
            uiState.editingProductId = '';
            renderPage(currentUser(), 'stok-barang');
        } else if (action === 'edit-product') {
            if (!canManageProducts(currentUser())) return notify('Anda tidak memiliki akses untuk mengubah barang.');
            uiState.editingProductId = button.dataset.id;
            uiState.productFormOpen = true;
            renderPage(currentUser(), 'stok-barang');
        } else if (action === 'cancel-product') {
            uiState.productFormOpen = false;
            uiState.editingProductId = '';
            renderPage(currentUser(), 'stok-barang');
        } else if (action === 'delete-product') {
            if (!canManageProducts(currentUser())) return notify('Anda tidak memiliki akses untuk menghapus barang.');
            const product = data.products.find(item => item.id === button.dataset.id);
            if (!product) return notify('Barang tidak ditemukan.');
            if (Number(product.shop) > 0 || Number(product.warehouse) > 0) return notify('Barang masih memiliki stok. Habiskan atau sesuaikan stok sebelum menghapus.');
            if (data.stockMovements.some(item => item.productId === product.id) || data.receipts.some(item => item.productId === product.id)) {
                return notify('Barang yang memiliki riwayat pergerakan tidak dapat dihapus agar audit tetap utuh.');
            }
            if (!window.confirm(`Hapus ${product.code} — ${product.name} dari katalog?`)) return;
            data.products = data.products.filter(item => item.id !== product.id);
            addActivity(`Menghapus data barang ${product.code} — ${product.name}.`);
            notify('Barang berhasil dihapus.');
            refresh('stok-barang');
        } else if (action === 'logout') {
            try {
                addActivity('Keluar dari sistem.');
                if (!await syncQueue || stateSyncFailed) {
                    return notify('Perubahan belum tersimpan. Periksa koneksi dan coba kembali sebelum keluar.');
                }
                await requestApi('auth/logout', {});
                authenticatedUser = null;
                renderLogin();
            } catch (error) {
                console.error('Logout server gagal.', error);
                notify(error.message || 'Tidak dapat mengakhiri sesi. Coba kembali.');
            }
        } else if (action === 'new-user') {
            const user = currentUser();
            if (!isOwner(user)) return notify('Hanya Owner yang dapat mengelola akun.');
            data.userForm = true;
            renderPage(user, 'users');
        } else if (action === 'cancel-user') {
            data.userForm = false;
            renderPage(currentUser(), 'users');
        } else if (action === 'toggle-user') {
            const owner = currentUser();
            if (!isOwner(owner)) return notify('Hanya Owner yang dapat mengelola akun.');
            const account = data.users.find(user => String(user.id) === button.dataset.id);
            if (!account || account.role === 'Owner') return;
            try {
                const updated = await requestApi(`users/${account.id}/active`, { active: !account.active }, 'PATCH');
                Object.assign(account, updated.user);
                addActivity(`${account.active ? 'Mengaktifkan' : 'Menonaktifkan'} akun ${account.name}.`);
                notify(`Akun ${account.active ? 'diaktifkan' : 'dinonaktifkan'}.`);
                refresh('users');
            } catch (error) {
                console.error('Perubahan status akun server gagal.', error);
                notify(error.message || 'Status akun tidak dapat diubah.');
            }
        } else if (action === 'toggle-customer') {
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat mengelola pelanggan.');
            const customer = data.customers.find(item => item.id === button.dataset.id);
            if (!customer) return notify('Pelanggan tidak ditemukan.');
            customer.active = customer.active === false;
            addActivity(`${customer.active ? 'Mengaktifkan' : 'Menonaktifkan'} pelanggan ${customer.name}.`);
            notify(`Pelanggan ${customer.active ? 'diaktifkan' : 'dinonaktifkan'}.`);
            refresh('customers');
        } else if (action === 'approve-adjustment' || action === 'reject-adjustment') {
            if (!isOwner(currentUser())) return notify('Hanya Owner yang dapat meninjau permintaan void / retur.');
            const request = data.returns.find(item => item.id === button.dataset.id && item.status === 'Menunggu Persetujuan');
            if (!request) return notify('Permintaan sudah diproses atau tidak ditemukan.');
            const sale = data.sales.find(item => item.id === request.saleId);
            if (action === 'reject-adjustment') {
                if (!window.confirm(`Tolak permintaan ${request.type} untuk struk ${request.number}?`)) return;
                request.status = 'Ditolak';
                request.reviewedBy = currentUser().name;
                request.reviewedAt = new Date().toISOString();
                addActivity(`Menolak permintaan ${request.type.toLowerCase()} struk ${request.number}.`);
                notify('Permintaan ditolak; transaksi dan stok tidak berubah.');
                return refresh('void-retur');
            }
            if (!sale || ['Void', 'Retur Penuh'].includes(sale.status)) return notify('Struk tidak lagi dapat disesuaikan.');
            if (!window.confirm(`Setujui ${request.type.toLowerCase()} struk ${request.number} sebesar ${formatRupiah(request.amount)}?`)) return;
            const adjustment = recordSaleAdjustment(
                sale,
                request.type,
                request.items,
                request.reason,
                request.refundMethod,
                request.id,
            );
            if (!adjustment) return notify('Permintaan tidak dapat diproses. Periksa saldo piutang dan stok.');
            addActivity(`Menyetujui ${request.type.toLowerCase()} struk ${request.number} sebesar ${formatRupiah(adjustment.amount)}.`);
            notify('Permintaan disetujui dan penyesuaian sudah diproses.');
            refresh('void-retur');
        }
    });

    app.addEventListener('submit', async event => {
        if (event.target.id !== 'login-form') return;
        event.preventDefault();
        const values = Object.fromEntries(new FormData(event.target).entries());
        try {
            const result = await requestApi('auth/login', {
                username: values.username.trim(),
                password: values.password,
            });

            authenticatedUser = result.user;
            await loadServerState();
            if (authenticatedUser.role === 'Owner') await loadOwnerUsers();
            addActivity('Login ke sistem.');
            renderShell(authenticatedUser);
        } catch (error) {
            console.error('Login server gagal.', error);
            notify(error.message || 'Tidak dapat masuk. Pastikan server dan database tersedia.');
        }
    });

    app.addEventListener('change', event => {
        if (event.target.name !== 'remember') return;
        if (event.target.checked) localStorage.setItem('mira-plastik-remembered-user', document.getElementById('username')?.value.trim() || '');
        else localStorage.removeItem('mira-plastik-remembered-user');
    });

    const restoreSession = async () => {
        try {
            const result = await requestApi('auth/session');
            if (result.authenticated) {
                authenticatedUser = result.user;
                await loadServerState();
                if (authenticatedUser.role === 'Owner') await loadOwnerUsers();
                renderShell(authenticatedUser);
                return;
            }
        } catch (error) {
            console.error('Validasi sesi server gagal.', error);
            notify('Server belum dapat dihubungi. Periksa koneksi database lalu muat ulang.');
        }
        renderLogin();
        const remembered = localStorage.getItem('mira-plastik-remembered-user');
        if (remembered) {
            const username = document.getElementById('username');
            if (username) username.value = remembered;
        }
    };
    restoreSession();
})();
