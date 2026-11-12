-- Run once against an existing Mira Plastik database.
-- Existing products need their real retail-units-per-stock-unit factor entered before retail checkout.
-- Historical sales are backfilled as recorded because conversion was not applied before this migration.
USE mira_plastik;

ALTER TABLE barang
    ADD COLUMN retail_units_per_stock_unit DECIMAL(14,3) NULL AFTER retail_unit_id,
    ADD CONSTRAINT chk_barang_retail_conversion_positive
        CHECK (retail_units_per_stock_unit IS NULL OR retail_units_per_stock_unit >= 1);

ALTER TABLE detail_penjualan
    ADD COLUMN sale_unit_snapshot VARCHAR(30) NULL AFTER unit_snapshot,
    ADD COLUMN price_unit_snapshot VARCHAR(30) NULL AFTER unit_snapshot,
    ADD COLUMN stock_quantity_snapshot DECIMAL(14,3) NULL AFTER quantity,
    ADD COLUMN retail_units_per_stock_unit_snapshot DECIMAL(14,3) NULL AFTER stock_quantity_snapshot;

UPDATE detail_penjualan
JOIN penjualan ON penjualan.sale_id = detail_penjualan.sale_id
SET detail_penjualan.sale_unit_snapshot = detail_penjualan.unit_snapshot,
    detail_penjualan.price_unit_snapshot = detail_penjualan.unit_snapshot,
    detail_penjualan.stock_quantity_snapshot = detail_penjualan.quantity,
    retail_units_per_stock_unit_snapshot = CASE
        WHEN penjualan.sale_type = 'ECERAN' OR detail_penjualan.price_type = 'ECERAN' THEN 1.000
        ELSE NULL
    END;

ALTER TABLE detail_penjualan
    MODIFY price_unit_snapshot VARCHAR(30) NOT NULL,
    MODIFY sale_unit_snapshot VARCHAR(30) NOT NULL,
    MODIFY quantity DECIMAL(18,6) NOT NULL,
    MODIFY stock_quantity_snapshot DECIMAL(18,6) NOT NULL,
    MODIFY retail_units_per_stock_unit_snapshot DECIMAL(14,3) NULL,
    ADD CONSTRAINT chk_detail_penjualan_stock_quantity CHECK (stock_quantity_snapshot > 0),
    ADD CONSTRAINT chk_detail_penjualan_conversion CHECK (
        (retail_units_per_stock_unit_snapshot IS NOT NULL AND retail_units_per_stock_unit_snapshot >= 1)
        OR (retail_units_per_stock_unit_snapshot IS NULL AND price_type = 'GROSIR')
    );

ALTER TABLE saldo_persediaan
    MODIFY quantity DECIMAL(18,6) NOT NULL DEFAULT 0.000000;

ALTER TABLE alokasi_stok_penjualan
    MODIFY quantity DECIMAL(18,6) NOT NULL;

ALTER TABLE detail_penyesuaian_penjualan
    MODIFY quantity DECIMAL(18,6) NOT NULL;

ALTER TABLE pergerakan_persediaan
    MODIFY quantity_delta DECIMAL(18,6) NOT NULL;

ALTER TABLE detail_penjualan
    MODIFY stock_quantity_snapshot DECIMAL(18,6) NOT NULL;