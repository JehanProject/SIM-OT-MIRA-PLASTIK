-- Run once against an existing Mira Plastik database.
-- Existing products keep their current unit as the retail unit until edited.
USE mira_plastik;

ALTER TABLE barang
    ADD COLUMN retail_unit_id BIGINT UNSIGNED NULL AFTER unit_id,
    ADD KEY idx_barang_retail_unit (retail_unit_id);

UPDATE barang
SET retail_unit_id = unit_id
WHERE retail_unit_id IS NULL;

ALTER TABLE barang
    MODIFY retail_unit_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_barang_retail_satuan FOREIGN KEY (retail_unit_id)
        REFERENCES satuan (unit_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
