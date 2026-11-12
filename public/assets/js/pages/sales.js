(() => {
    const products = Array.from(document.querySelectorAll('#product-select option')).map(option => ({
        id: option.value,
        code: option.dataset.code,
        name: option.dataset.name,
        stockUnit: option.dataset.stockUnit,
        retailUnit: option.dataset.retailUnit,
        conversion: option.dataset.conversion === '' ? null : Number(option.dataset.conversion),
        retail: Number(option.dataset.retail),
        wholesale: Number(option.dataset.wholesale),
        minimum: Number(option.dataset.minimum),
        storeStock: Number(option.dataset.storeStock),
        warehouseStock: Number(option.dataset.warehouseStock),
        minimumStock: Number(option.dataset.minStock)
    }));
    const cart = new Map();
    const productSelect = document.getElementById('product-select');
    const productSearch = document.getElementById('product-search');
    const productCards = document.getElementById('product-cards');
    const cartItems = document.getElementById('cart-items');
    const saleType = document.getElementById('sale-type');
    const paymentMethod = document.getElementById('payment-method');
    const cashAccount = document.getElementById('cash-account');
    const accountField = document.getElementById('account-field');
    const amountReceived = document.getElementById('amount-received');
    const cashReceivedField = document.getElementById('cash-received-field');
    const customerPanel = document.getElementById('wholesale-customer-panel');
    const customerSelect = document.getElementById('customer-id');
    const dueDateField = document.getElementById('due-date-field');
    const dueDate = document.getElementById('due-date');
    const bonSetupGuidance = document.getElementById('bon-setup-guidance');
    const ownerCreditLinks = document.getElementById('owner-credit-links');
    const customerDialog = document.getElementById('customer-dialog');
    const policyDialog = document.getElementById('credit-policy-dialog');
    const customerCartFields = document.getElementById('customer-cart-fields');
    const policyCartFields = document.getElementById('policy-cart-fields');
    const saleForm = document.getElementById('sale-form');
    const hasCreditPolicy = saleForm.dataset.hasCreditPolicy === 'true';
    const hasCustomers = saleForm.dataset.hasCustomers === 'true';
    const totalElement = document.getElementById('cart-total');
    const changeElement = document.getElementById('change-amount');
    const money = value => new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: 'IDR', maximumFractionDigits: 2
    }).format(Number.isFinite(value) ? value : 0);
    const selectedProduct = () => products.find(product => product.id === productSelect.value);
    const retailButtons = document.querySelectorAll('[data-sale-type]');
    const paymentButtons = document.querySelectorAll('[data-payment-method]');
    const addButton = document.getElementById('add-product');
    const checkoutButton = document.getElementById('checkout-button');

    function priceTypeFor(product, quantity) {
        return saleType.value === 'GROSIR' && Number(quantity) >= product.minimum
            ? 'GROSIR'
            : 'ECERAN';
    }

    function priceFor(product, quantity) {
        return priceTypeFor(product, quantity) === 'GROSIR' ? product.wholesale : product.retail;
    }

    function priceUnitFor(product, quantity) {
        return priceTypeFor(product, quantity) === 'GROSIR' ? product.stockUnit : product.retailUnit;
    }

    function priceDescription(product, quantity) {
        const priceType = priceTypeFor(product, quantity);
        if (priceType === 'ECERAN' && product.conversion === null) {
            return 'Eceran · Faktor konversi belum diatur';
        }
        const conversion = saleType.value === 'GROSIR' && priceType === 'ECERAN'
            ? ` · ${product.conversion} ${product.retailUnit}/${product.stockUnit}`
            : '';

        return `${priceType === 'GROSIR' ? 'Grosir' : 'Eceran'} · ${money(priceFor(product, quantity))} / ${priceUnitFor(product, quantity)}${conversion}`;
    }

    function lineTotalFor(product, quantity) {
        const quantityValue = Number(quantity);
        if (!Number.isFinite(quantityValue) || quantityValue <= 0) return null;
        const conversion = saleType.value === 'GROSIR' && priceTypeFor(product, quantity) === 'ECERAN'
            ? product.conversion
            : 1;
        if (conversion === null || !Number.isFinite(conversion)) return null;

        return quantityValue * priceFor(product, quantity) * conversion;
    }

    function displayLineTotal(product, quantity) {
        const total = lineTotalFor(product, quantity);

        return total === null ? '—' : money(total);
    }

    function stockQuantityFor(product, quantity) {
        const quantityValue = Number(quantity);
        if (saleType.value !== 'ECERAN') return quantityValue;
        if (product.conversion === null || !Number.isFinite(product.conversion) || product.conversion < 1) return Infinity;

        return Math.ceil((quantityValue / product.conversion) * 1_000_000) / 1_000_000;
    }

    function availableStockFor(product) {
        return saleType.value === 'ECERAN'
            ? product.storeStock
            : product.warehouseStock + Math.max(0, product.storeStock - product.minimumStock);
    }

    function availableQuantityFor(product) {
        return saleType.value === 'ECERAN'
            ? (product.conversion === null ? 0 : product.storeStock * product.conversion)
            : availableStockFor(product);
    }

    function startingQuantityFor(product) {
        return saleType.value === 'GROSIR' && product.conversion === null
            ? product.minimum
            : 1;
    }

    function hasValidQuantity(product, quantity) {
        const quantityValue = Number(quantity);
        const conversionRequired = saleType.value === 'ECERAN'
            || (saleType.value === 'GROSIR' && quantityValue < product.minimum);

        return Number.isFinite(quantityValue) && quantityValue > 0
            && quantityValue <= availableQuantityFor(product)
            && (!conversionRequired || (product.conversion !== null && Number.isFinite(product.conversion)))
            && stockQuantityFor(product, quantityValue) <= availableStockFor(product)
            && Math.abs(quantityValue * 1000 - Math.round(quantityValue * 1000)) < 0.000001;
    }

    function canAddProduct(product) {
        return product !== undefined && hasValidQuantity(product, startingQuantityFor(product));
    }

    function formatQuantity(value) {
        if (String(value).trim() === '') return '';
        const quantity = Number(value);
        if (!Number.isFinite(quantity) || quantity <= 0) return '';

        return quantity.toFixed(3).replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1');
    }

    function hasValidCart() {
        return cart.size > 0 && Array.from(cart.entries()).every(([id, line]) => {
            const product = products.find(item => item.id === id);
            return product && hasValidQuantity(product, line.quantity);
        });
    }

    function refreshModeButtons() {
        retailButtons.forEach(button => {
            const active = button.dataset.saleType === saleType.value;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        const bonButton = document.querySelector('[data-payment-method="BON"]');
        bonButton.hidden = saleType.value !== 'GROSIR';
        if (bonButton.hidden && paymentMethod.value === 'BON') paymentMethod.value = 'TUNAI';
    }

    function refreshPaymentButtons() {
        paymentButtons.forEach(button => {
            const active = button.dataset.paymentMethod === paymentMethod.value;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    }

    function refreshAccounts() {
        refreshModeButtons();
        refreshPaymentButtons();
        const isBon = paymentMethod.value === 'BON';
        const isCash = paymentMethod.value === 'TUNAI';
        const requiredType = isCash ? 'TUNAI' : 'BANK';
        Array.from(cashAccount.options).forEach(option => {
            option.hidden = option.dataset.type !== requiredType;
            option.disabled = option.hidden;
        });
        const selected = cashAccount.options[cashAccount.selectedIndex];
        if (!selected || selected.disabled) {
            const available = Array.from(cashAccount.options).find(option => !option.disabled);
            if (available) cashAccount.value = available.value;
        }
        cashAccount.disabled = isBon || !Array.from(cashAccount.options).some(option => !option.disabled);
        accountField.hidden = isBon;
        cashReceivedField.hidden = !isCash;
        amountReceived.required = isCash;
        amountReceived.disabled = !isCash;
        const showCustomerFields = isBon && saleType.value === 'GROSIR';
        customerSelect.disabled = !showCustomerFields;
        customerSelect.required = showCustomerFields;
        customerPanel.hidden = saleType.value !== 'GROSIR';
        dueDateField.hidden = !showCustomerFields;
        dueDate.disabled = !showCustomerFields;
        dueDate.required = showCustomerFields;
        bonSetupGuidance.hidden = !isBon || (hasCreditPolicy && hasCustomers);
        if (ownerCreditLinks) ownerCreditLinks.hidden = saleType.value !== 'GROSIR';
        updateTotals();
    }

    function updateTotals() {
        let total = 0;
        let totalQuantity = 0;
        cart.forEach((line, id) => {
            const product = products.find(item => item.id === id);
            const lineTotal = product ? lineTotalFor(product, line.quantity) : null;
            if (lineTotal !== null) total += lineTotal;
            const quantity = Number(line.quantity);
            if (Number.isFinite(quantity) && quantity > 0) totalQuantity += quantity;
        });
        totalElement.textContent = money(total);
        const received = Number(amountReceived.value || 0);
        changeElement.textContent = money(Math.max(0, received - total));
        document.getElementById('cart-count').textContent = String(cart.size);
        document.getElementById('cart-item-count').textContent = `${formatQuantity(totalQuantity) || '0'} barang`;
        document.getElementById('clear-cart').disabled = cart.size === 0;
        const isBon = paymentMethod.value === 'BON';
        checkoutButton.disabled = !hasValidCart() || products.length === 0
            || (isBon && (!hasCreditPolicy || !hasCustomers || !customerSelect.value || !dueDate.value))
            || (!isBon && cashAccount.disabled);
    }

    function syncCustomerCartFields() {
        [customerCartFields, policyCartFields].forEach(fields => {
            fields.replaceChildren();
            cart.forEach((line, id) => {
                [
                    ['cart_product_id[]', id],
                    ['cart_quantity[]', line.quantity]
                ].forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    fields.append(input);
                });
            });
        });
        document.getElementById('policy-customer-id').value = customerSelect.value;
        document.getElementById('policy-due-date').value = dueDate.value;
        document.getElementById('customer-payment-method').value = paymentMethod.value;
        document.getElementById('customer-due-date').value = dueDate.value;
    }

    function renderCart() {
        cartItems.replaceChildren();
        if (cart.size === 0) {
            const empty = document.createElement('p');
            empty.className = 'empty cart-empty';
            empty.textContent = 'Keranjang masih kosong.';
            cartItems.append(empty);
            updateTotals();
            return;
        }

        cart.forEach((line, id) => {
            const product = products.find(item => item.id === id);
            if (!product) return;
            const row = document.createElement('article');
            row.className = 'cart-line';

            const info = document.createElement('div');
            info.className = 'cart-line-info';
            const name = document.createElement('strong');
            name.className = 'cart-line-name';
            name.textContent = product.name;
            const description = document.createElement('span');
            description.className = 'cart-line-description';
            description.textContent = `${product.code} · ${priceDescription(product, line.quantity)}`;
            info.append(name, description);

            const subtotal = document.createElement('strong');
            subtotal.className = 'cart-line-subtotal';
            subtotal.textContent = displayLineTotal(product, line.quantity);

            const actions = document.createElement('div');
            actions.className = 'cart-line-actions';
            const quantityControl = document.createElement('div');
            quantityControl.className = 'quantity-control';
            const decrease = document.createElement('button');
            decrease.type = 'button';
            decrease.textContent = '−';
            decrease.setAttribute('aria-label', `Kurangi ${product.name}`);
            const quantity = document.createElement('input');
            quantity.type = 'number';
            quantity.name = 'quantity[]';
            quantity.min = '0.001';
            quantity.step = '0.001';
            quantity.max = String(availableQuantityFor(product));
            quantity.required = true;
            quantity.value = formatQuantity(line.quantity);
            quantity.setAttribute('aria-label', `Jumlah ${product.name}`);
            const hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.name = 'product_id[]';
            hiddenId.value = product.id;
            quantityControl.append(decrease, quantity, hiddenId);
            const increase = document.createElement('button');
            increase.type = 'button';
            increase.textContent = '+';
            increase.setAttribute('aria-label', `Tambah ${product.name}`);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'cart-remove';
            remove.textContent = 'Hapus';
            remove.addEventListener('click', () => {
                cart.delete(id);
                renderCart();
            });

            const refreshLine = () => {
                line.quantity = quantity.value;
                description.textContent = `${product.code} · ${priceDescription(product, line.quantity)}`;
                subtotal.textContent = displayLineTotal(product, line.quantity);
                updateTotals();
            };
            quantity.addEventListener('input', refreshLine);
            decrease.addEventListener('click', () => {
                const next = Number(line.quantity) - 1;
                if (next <= 0) cart.delete(id);
                else line.quantity = String(Math.round(next * 1000) / 1000);
                renderCart();
            });
            increase.addEventListener('click', () => {
                const next = Number(line.quantity) + 1;
                if (!hasValidQuantity(product, next)) return;
                line.quantity = String(next);
                renderCart();
            });
            actions.append(quantityControl, increase, remove);
            row.append(info, subtotal, actions);
            cartItems.append(row);
        });
        updateTotals();
    }

    function addProduct(product) {
        if (!product || !canAddProduct(product)) return;
        const existing = cart.get(product.id);
        if (existing) {
            const nextQuantity = Number(existing.quantity) + 1;
            if (!hasValidQuantity(product, nextQuantity)) return;
            existing.quantity = String(nextQuantity);
        } else {
            cart.set(product.id, { quantity: String(startingQuantityFor(product)) });
        }
        renderCart();
    }

    function updateSelectedInfo() {
        const product = selectedProduct();
        addButton.disabled = !product || !canAddProduct(product);
        document.getElementById('selected-product-info').textContent = product
            ? `${product.name}, harga eceran ${money(product.retail)} per ${product.retailUnit}, harga grosir ${money(product.wholesale)} per ${product.stockUnit}.`
            : 'Pilih barang yang tersedia.';
        document.querySelectorAll('[data-add-product]').forEach(button => {
            const item = products.find(productItem => productItem.id === button.dataset.addProduct);
            button.disabled = !item || !canAddProduct(item);
        });
    }

    productSearch.addEventListener('input', () => {
        const term = productSearch.value.trim().toLocaleLowerCase('id-ID');
        let visibleCount = 0;
        document.querySelectorAll('[data-product-card]').forEach(card => {
            const visible = card.dataset.search.includes(term);
            card.hidden = !visible;
            if (visible) visibleCount++;
        });
        document.getElementById('product-search-empty').hidden = visibleCount > 0;
        const visibleOption = Array.from(productSelect.options).find(option => option.textContent.toLocaleLowerCase('id-ID').includes(term));
        if (visibleOption) productSelect.value = visibleOption.value;
        updateSelectedInfo();
    });

    document.querySelectorAll('[data-add-product]').forEach(button => {
        button.addEventListener('click', () => {
            const product = products.find(item => item.id === button.dataset.addProduct);
            if (!product) return;
            productSelect.value = product.id;
            addProduct(product);
        });
    });
    addButton.addEventListener('click', () => addProduct(selectedProduct()));
    productSelect.addEventListener('change', updateSelectedInfo);

    retailButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (saleType.value === button.dataset.saleType) return;
            saleType.value = button.dataset.saleType;
            refreshAccounts();
            renderCart();
            updateSelectedInfo();
        });
    });

    paymentButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (button.dataset.paymentMethod === 'BON' && saleType.value !== 'GROSIR') return;
            paymentMethod.value = button.dataset.paymentMethod;
            refreshAccounts();
        });
    });
    paymentMethod.addEventListener('change', refreshAccounts);
    document.getElementById('clear-cart').addEventListener('click', () => {
        cart.clear();
        renderCart();
    });
    document.querySelectorAll('.open-customer-dialog').forEach(button => {
        button.addEventListener('click', () => {
            syncCustomerCartFields();
            customerDialog.showModal();
        });
    });
    customerDialog.querySelectorAll('.dialog-close, .dialog-cancel').forEach(button => {
        button.addEventListener('click', () => customerDialog.close());
    });
    document.querySelectorAll('.open-policy-dialog').forEach(button => {
        button.addEventListener('click', () => {
            syncCustomerCartFields();
            policyDialog.showModal();
        });
    });
    policyDialog.querySelectorAll('.dialog-close, .dialog-cancel').forEach(button => {
        button.addEventListener('click', () => policyDialog.close());
    });
    customerSelect.addEventListener('change', updateTotals);
    dueDate.addEventListener('input', updateTotals);
    amountReceived.addEventListener('input', updateTotals);
    saleForm.addEventListener('submit', event => {
        if (cart.size === 0) {
            event.preventDefault();
            alert('Tambahkan minimal satu barang ke keranjang.');
        }
    });

    const oldCart = JSON.parse(saleForm.dataset.oldCart);
    oldCart.product_ids.forEach((id, index) => {
        const product = products.find(item => item.id === String(id));
        const quantity = Number(oldCart.quantities[index]);
        if (product && hasValidQuantity(product, quantity)) {
            cart.set(product.id, { quantity: String(quantity) });
        }
    });
    refreshAccounts();
    updateSelectedInfo();
    renderCart();
    if (customerDialog.dataset.openOnLoad === 'true') {
        syncCustomerCartFields();
        customerDialog.showModal();
    }
    if (policyDialog.dataset.openOnLoad === 'true') {
        syncCustomerCartFields();
        policyDialog.showModal();
    }
})();
