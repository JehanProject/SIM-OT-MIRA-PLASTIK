-- Run once against a database where update_konversi_satuan_eceran.sql was already applied.
-- Legacy sales used the stock unit without conversion, so their conversion snapshot is 1:1.
USE mira_plastik;

UPDATE detail_penjualan
JOIN penjualan ON penjualan.sale_id = detail_penjualan.sale_id
SET detail_penjualan.retail_units_per_stock_unit_snapshot = 1.000
WHERE detail_penjualan.retail_units_per_stock_unit_snapshot IS NULL
  AND (penjualan.sale_type = 'ECERAN' OR detail_penjualan.price_type = 'ECERAN');

ALTER TABLE detail_penjualan
    DROP CONSTRAINT chk_detail_penjualan_conversion,
    ADD CONSTRAINT chk_detail_penjualan_conversion CHECK (
        (retail_units_per_stock_unit_snapshot IS NOT NULL AND retail_units_per_stock_unit_snapshot >= 1)
        OR (retail_units_per_stock_unit_snapshot IS NULL AND price_type = 'GROSIR')
    );

ALTER TABLE barang
    DROP CONSTRAINT chk_barang_retail_conversion_positive,
    ADD CONSTRAINT chk_barang_retail_conversion_positive CHECK (
        retail_units_per_stock_unit IS NULL OR retail_units_per_stock_unit >= 1
    );
