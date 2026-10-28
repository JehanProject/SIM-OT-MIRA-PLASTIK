export function createOwnerAdministrationModule(context) {
    const { data, escapeHtml, pageHeading, formatRupiah, dateTime } = context;

    function customersPage() {
        const customerEdit = data.customers.find(customer => customer.id === data.customerEditId);
        const customerBalance = customer => data.receivables.filter(item => item.customerId === customer.id)
            .reduce((sum, item) => sum + Math.max(0, Number(item.total) - Number(item.paid)
                - data.receivableWriteoffs.filter(writeoff => writeoff.receivableId === item.id)
                    .reduce((total, writeoff) => total + Number(writeoff.amount), 0)), 0);
        return `${pageHeading('Pelanggan Grosir', 'Kelola kontak pelanggan, plafon piutang, dan tenor pembayaran.')}
            <article class="panel">
                <div class="panel-heading"><div><h2>Tambah Pelanggan</h2><p>Pelanggan aktif dapat dipilih pada transaksi grosir dengan pembayaran Bon.</p></div></div>
                <form id="customer-form">
                    <div class="form-grid">
                        <div class="field"><label for="customer-name">Nama pelanggan / usaha</label><input id="customer-name" name="name" maxlength="100" required></div>
                        <div class="field"><label for="customer-phone">Nomor telepon</label><input id="customer-phone" name="phone" maxlength="30"></div>
                        <div class="field"><label for="customer-limit">Plafon kredit (Rp)</label><input id="customer-limit" name="creditLimit" type="number" min="0" step="1" required></div>
                        <div class="field"><label for="customer-days">Tenor (hari)</label><input id="customer-days" name="creditDays" type="number" min="1" max="90" step="1" value="7" required></div>
                    </div>
                    <div class="form-actions"><button class="button" type="submit">Simpan Pelanggan</button></div>
                </form>
            </article>
            ${customerEdit ? `<article class="panel">
                <div class="panel-heading"><div><h2>Atur Limit Kredit</h2><p>${escapeHtml(customerEdit.name)} memiliki saldo piutang ${formatRupiah(customerBalance(customerEdit))}.</p></div></div>
                <form id="customer-credit-form">
                    <input type="hidden" name="customerId" value="${escapeHtml(customerEdit.id)}">
                    <div class="form-grid">
                        <div class="field"><label for="customer-edit-limit">Plafon kredit (Rp)</label><input id="customer-edit-limit" name="creditLimit" type="number" min="${customerBalance(customerEdit)}" step="1" value="${Number(customerEdit.creditLimit)}" required></div>
                        <div class="field"><label for="customer-edit-days">Tenor (hari)</label><input id="customer-edit-days" name="creditDays" type="number" min="1" max="90" step="1" value="${Number(customerEdit.creditDays)}" required></div>
                    </div>
                    <div class="form-actions"><button class="button button-quiet" type="button" data-action="cancel-customer-credit">Batal</button><button class="button" type="submit">Simpan Limit</button></div>
                </form>
            </article>` : ''}
            <article class="panel"><div class="panel-heading"><div><h2>Daftar Pelanggan</h2><p>${data.customers.length} pelanggan tercatat.</p></div></div>
                ${data.customers.length ? `<div class="table-wrap"><table class="compact-table"><thead><tr><th>Nama</th><th>Telepon</th><th>Plafon</th><th>Tenor</th><th>Saldo piutang</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${data.customers.map(customer => {
            const balance = customerBalance(customer);
            return `<tr><td class="product-name">${escapeHtml(customer.name)}</td><td>${escapeHtml(customer.phone || '—')}</td>
                <td>${formatRupiah(customer.creditLimit)}</td><td>${Number(customer.creditDays)} hari</td><td>${formatRupiah(balance)}</td>
                <td>${customer.active === false ? 'Nonaktif' : Number(customer.creditLimit) === 0 ? 'Aktif · Bon belum diaktifkan' : 'Aktif'}</td>
                <td><button class="action-button" type="button" data-action="edit-customer-credit" data-id="${escapeHtml(customer.id)}">Atur limit</button>
                <button class="action-button" type="button" data-action="toggle-customer" data-id="${escapeHtml(customer.id)}">${customer.active === false ? 'Aktifkan' : 'Nonaktifkan'}</button></td></tr>`;
        }).join('')}</tbody></table></div>` : '<div class="empty">Belum ada pelanggan grosir.</div>'}
            </article>`;
    }

    function usersPage() {
        const usersPageContent = `${pageHeading('Manajemen Pengguna', 'Owner dapat membuat dan menonaktifkan akun Kasir serta Staf Gudang.', '<button class="button" data-action="new-user">＋ Tambah Pengguna</button>')}
            <article class="panel">
                <div class="panel-heading"><div><h2>Daftar Akun</h2><p>Akun Owner utama tidak dapat dinonaktifkan.</p></div></div>
                ${data.userForm ? `
                    <form class="inline-form" id="user-form">
                        <h3>Buat akun staf</h3>
                        <div class="form-grid">
                            <div class="field"><label for="account-name">Nama lengkap</label><input id="account-name" name="name" required maxlength="80"></div>
                            <div class="field"><label for="account-username">Username</label><input id="account-username" name="username" required minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]+" autocomplete="off"></div>
                            <div class="field"><label for="account-password">Password awal</label><input id="account-password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password"></div>
                            <div class="field"><label for="account-role">Peran</label><select id="account-role" name="role"><option>Kasir</option><option>Staf Gudang</option></select></div>
                        </div>
                        <div class="form-actions"><button type="button" class="button button-quiet" data-action="cancel-user">Batal</button><button class="button" type="submit">Buat Akun</button></div>
                    </form>` : ''}
                <div class="table-wrap"><table><thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>${data.users.map(account => `<tr>
                        <td class="product-name">${escapeHtml(account.name)}</td>
                        <td>${escapeHtml(account.username)}</td>
                        <td>${escapeHtml(account.role)}</td>
                        <td><span class="badge ${account.active ? '' : 'badge-neutral'}">${account.active ? 'Aktif' : 'Nonaktif'}</span></td>
                        <td>${account.role === 'Owner' ? 'Akun utama' : `<button class="action-button ${account.active ? 'danger' : ''}" data-action="toggle-user" data-id="${escapeHtml(account.id)}">${account.active ? 'Nonaktifkan' : 'Aktifkan'}</button>`}</td>
                    </tr>`).join('')}</tbody>
                </table></div>
            </article>`;
        return `${usersPageContent}<article class="panel backup-panel">
            <div class="panel-heading"><div><h2>Backup & Pemulihan Data</h2><p>Simpan salinan data operasional atau pulihkan dari file backup.</p></div></div>
            <div class="backup-actions">
                <button class="button" id="export-backup" type="button">Unduh Backup</button>
                <form class="backup-restore-form" id="backup-restore-form">
                    <label class="field" for="backup-file"><span>Pilih file backup JSON</span><input id="backup-file" name="backup" type="file" accept=".json,application/json" required></label>
                    <button class="button button-danger" type="submit">Pulihkan Backup</button>
                </form>
                <form class="backup-restore-form" id="backup-merge-form">
                    <label class="field" for="backup-merge-file"><span>Pilih file backup untuk digabung</span><input id="backup-merge-file" name="backup" type="file" accept=".json,application/json" required></label>
                    <button class="button button-quiet" type="submit">Gabungkan Data</button>
                </form>
            </div>
            <p class="muted backup-warning">Backup mencakup data operasional dan daftar akun tanpa password. Pemulihan mengganti data operasional di server; penggabungan menambahkan catatan baru, melewati duplikat identik, dan membatalkan seluruh proses jika menemukan konflik. Akun server tidak berubah.</p>
        </article>`;
    }


    function activityPage() {
        return `${pageHeading('Log Aktivitas', 'Riwayat login dan perubahan akun untuk kebutuhan pengawasan Owner.')}
            <article class="panel"><div class="panel-heading"><div><h2>Aktivitas Sistem</h2><p>${data.activity.length} aktivitas terbaru tersimpan di server.</p></div></div>
                ${data.activity.length ? `<div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pengguna</th><th>Peran</th><th>Aktivitas</th></tr></thead><tbody>${data.activity.map(item => `
                    <tr><td>${dateTime(item.createdAt)}</td><td class="product-name">${escapeHtml(item.name)}</td><td>${escapeHtml(item.role)}</td><td>${escapeHtml(item.message)}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada aktivitas.</div>'}
            </article>`;
    }

    return { usersPage, activityPage, customersPage };
}
