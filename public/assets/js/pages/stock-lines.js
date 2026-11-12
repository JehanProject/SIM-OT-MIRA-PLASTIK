(() => {
    const addLine = document.getElementById('add-line');
    const lines = document.querySelector('#opening-lines, #receipt-lines, #transfer-lines');
    if (!lines) return;

    if (addLine) {
        addLine.addEventListener('click', () => {
            const firstRow = lines.querySelector('tr');
            if (!firstRow) return;
            const row = firstRow.cloneNode(true);
            row.querySelectorAll('select').forEach((field) => { field.selectedIndex = 0; });
            row.querySelectorAll('input').forEach((field) => { field.value = ''; });
            row.querySelectorAll('.transfer-stock').forEach((label) => {
                label.textContent = 'Pilih barang untuk melihat stok terkini';
            });
            lines.appendChild(row);
        });
    }

    if (lines.id === 'transfer-lines') {
        const updateStockLabel = (select) => {
            const option = select.selectedOptions[0];
            const label = select.closest('tr')?.querySelector('.transfer-stock');
            if (label) {
                label.textContent = option?.value
                    ? `Tersedia ${option.dataset.stock} ${option.dataset.unit}`
                    : 'Pilih barang untuk melihat stok terkini';
            }
        };

        lines.querySelectorAll('.transfer-product').forEach(updateStockLabel);
        lines.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLSelectElement) || !target.classList.contains('transfer-product')) return;
            updateStockLabel(target);
        });
    }
})();
