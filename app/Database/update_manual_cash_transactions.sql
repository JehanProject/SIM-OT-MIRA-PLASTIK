-- Apply once to an existing Mira Plastik database.
-- Manual cash entries are linked one-to-one to the cash ledger.

CREATE TABLE IF NOT EXISTS transaksi_kas_manual (
    manual_cash_transaction_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recorded_by BIGINT UNSIGNED NOT NULL,
    direction VARCHAR(10) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (manual_cash_transaction_id),
    KEY idx_transaksi_kas_manual_date (occurred_at),
    KEY idx_transaksi_kas_manual_user (recorded_by, occurred_at),
    CONSTRAINT chk_transaksi_kas_manual_direction CHECK (direction IN ('MASUK', 'KELUAR')),
    CONSTRAINT chk_transaksi_kas_manual_amount CHECK (amount > 0),
    CONSTRAINT fk_transaksi_kas_manual_user FOREIGN KEY (recorded_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

ALTER TABLE mutasi_kas
    DROP CONSTRAINT chk_mutasi_kas_one_source,
    ADD COLUMN manual_cash_transaction_id BIGINT UNSIGNED NULL AFTER opening_cash_balance_id,
    ADD UNIQUE KEY uq_mutasi_kas_manual (manual_cash_transaction_id),
    ADD CONSTRAINT chk_mutasi_kas_one_source CHECK (
        (sale_id IS NOT NULL)
        + (receivable_payment_id IS NOT NULL)
        + (goods_receipt_id IS NOT NULL)
        + (operating_expense_id IS NOT NULL)
        + (sale_adjustment_id IS NOT NULL)
        + (opening_cash_balance_id IS NOT NULL)
        + (manual_cash_transaction_id IS NOT NULL) = 1
    ),
    ADD CONSTRAINT fk_mutasi_kas_manual FOREIGN KEY (manual_cash_transaction_id)
        REFERENCES transaksi_kas_manual (manual_cash_transaction_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
