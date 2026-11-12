document.querySelectorAll('.payment-form').forEach(form => {
    const method = form.querySelector('[name="payment_method"]');
    const account = form.querySelector('[name="cash_account_id"]');
    function syncAccount() {
        Array.from(account.options).forEach(option => {
            option.hidden = option.dataset.type !== (method.value === 'TUNAI' ? 'TUNAI' : 'BANK');
            option.disabled = option.hidden;
        });
        if (account.options[account.selectedIndex]?.disabled) {
            const available = Array.from(account.options).find(option => !option.disabled);
            if (available) account.value = available.value;
        }
        account.disabled = !Array.from(account.options).some(option => !option.disabled);
    }
    method.addEventListener('change', syncAccount);
    syncAccount();
});
