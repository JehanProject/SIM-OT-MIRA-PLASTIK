-- Run once against an existing Mira Plastik database.
-- Legacy units used by inventory are categorized as GROSIR. Review unused
-- units after migration and recreate them with the intended type if needed.
-- Run after update_satuan_eceran_grosir.sql so barang.retail_unit_id exists.
USE mira_plastik;

ALTER TABLE satuan
    ADD COLUMN sale_type VARCHAR(20) NULL AFTER unit_symbol;

UPDATE satuan AS unit
LEFT JOIN barang AS wholesale_product ON wholesale_product.unit_id = unit.unit_id
LEFT JOIN barang AS retail_product ON retail_product.retail_unit_id = unit.unit_id
SET unit.sale_type = CASE
    WHEN wholesale_product.product_id IS NOT NULL THEN 'GROSIR'
    WHEN retail_product.product_id IS NOT NULL THEN 'ECERAN'
    ELSE 'GROSIR'
END;

ALTER TABLE satuan
    MODIFY sale_type VARCHAR(20) NOT NULL,
    ADD CONSTRAINT chk_satuan_sale_type CHECK (sale_type IN ('ECERAN', 'GROSIR'));