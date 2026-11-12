-- MySQL 8.0.16+ / MariaDB 10.4+ schema for the Mira Plastik operations system.
-- This script is non-destructive: it does not drop existing databases or tables.

CREATE DATABASE IF NOT EXISTS mira_plastik
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mira_plastik;

CREATE TABLE IF NOT EXISTS peran (
    role_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_code VARCHAR(30) NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_peran_role_code (role_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pengguna (
    user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_pengguna_username (username),
    KEY idx_pengguna_role (role_id),
    CONSTRAINT fk_pengguna_peran FOREIGN KEY (role_id)
        REFERENCES peran (role_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS log_aktivitas (
    activity_log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id VARCHAR(100) NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (activity_log_id),
    KEY idx_log_aktivitas_user_created (user_id, created_at),
    KEY idx_log_aktivitas_entity (entity_type, entity_id),
    CONSTRAINT fk_log_aktivitas_pengguna FOREIGN KEY (user_id)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifikasi_pengguna (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL,
    target_url VARCHAR(255) NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (notification_id),
    KEY idx_notifikasi_user_read_created (user_id, is_read, created_at),
    CONSTRAINT fk_notifikasi_pengguna_user FOREIGN KEY (user_id)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kategori (
    category_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_kategori_name (category_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS satuan (
    unit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_name VARCHAR(100) NOT NULL,
    unit_symbol VARCHAR(30) NOT NULL,
    sale_type VARCHAR(20) NOT NULL,
    PRIMARY KEY (unit_id),
    UNIQUE KEY uq_satuan_name (unit_name),
    UNIQUE KEY uq_satuan_symbol (unit_symbol),
    CONSTRAINT chk_satuan_sale_type CHECK (sale_type IN ('ECERAN', 'GROSIR'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS barang (
    product_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    retail_unit_id BIGINT UNSIGNED NOT NULL,
    retail_units_per_stock_unit DECIMAL(14,3) NOT NULL,
    product_code VARCHAR(80) NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    avg_purchase_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    retail_price DECIMAL(14,2) NOT NULL,
    wholesale_price DECIMAL(14,2) NOT NULL,
    wholesale_min_qty DECIMAL(14,3) NOT NULL,
    min_stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_barang_code (product_code),
    KEY idx_barang_category (category_id),
    KEY idx_barang_unit (unit_id),
    KEY idx_barang_retail_unit (retail_unit_id),
    CONSTRAINT chk_barang_cost_nonnegative CHECK (avg_purchase_cost >= 0),
    CONSTRAINT chk_barang_retail_nonnegative CHECK (retail_price >= 0),
    CONSTRAINT chk_barang_wholesale_nonnegative CHECK (wholesale_price >= 0),
    CONSTRAINT chk_barang_wholesale_min_positive CHECK (wholesale_min_qty > 0),
    CONSTRAINT chk_barang_min_stock_nonnegative CHECK (min_stock >= 0),
    CONSTRAINT chk_barang_retail_conversion_positive CHECK (retail_units_per_stock_unit >= 1),
    CONSTRAINT fk_barang_kategori FOREIGN KEY (category_id)
        REFERENCES kategori (category_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_barang_satuan FOREIGN KEY (unit_id)
        REFERENCES satuan (unit_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_barang_retail_satuan FOREIGN KEY (retail_unit_id)
        REFERENCES satuan (unit_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lokasi_persediaan (
    location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_code VARCHAR(30) NOT NULL,
    location_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (location_id),
    UNIQUE KEY uq_lokasi_code (location_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saldo_persediaan (
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, location_id),
    KEY idx_saldo_location (location_id),
    CONSTRAINT chk_saldo_quantity_nonnegative CHECK (quantity >= 0),
    CONSTRAINT fk_saldo_barang FOREIGN KEY (product_id)
        REFERENCES barang (product_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_saldo_lokasi FOREIGN KEY (location_id)
        REFERENCES lokasi_persediaan (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pelanggan (
    customer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_code VARCHAR(80) NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    business_name VARCHAR(150) NULL,
    phone VARCHAR(40) NULL,
    address TEXT NULL,
    credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (customer_id),
    UNIQUE KEY uq_pelanggan_code (customer_code),
    CONSTRAINT chk_pelanggan_credit_limit CHECK (credit_limit >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pemasok (
    supplier_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_code VARCHAR(80) NOT NULL,
    supplier_name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) NULL,
    address TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (supplier_id),
    UNIQUE KEY uq_pemasok_code (supplier_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penjualan (
    sale_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cashier_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    invoice_number VARCHAR(80) NOT NULL,
    sale_type VARCHAR(20) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
    sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(14,2) NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL,
    amount_received DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    change_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    PRIMARY KEY (sale_id),
    UNIQUE KEY uq_penjualan_invoice (invoice_number),
    KEY idx_penjualan_cashier_sold (cashier_id, sold_at),
    KEY idx_penjualan_customer (customer_id),
    KEY idx_penjualan_status_sold (status, sold_at),
    CONSTRAINT chk_penjualan_type CHECK (sale_type IN ('ECERAN', 'GROSIR')),
    CONSTRAINT chk_penjualan_payment CHECK (payment_method IN ('TUNAI', 'TRANSFER', 'BON')),
    CONSTRAINT chk_penjualan_status CHECK (status IN ('POSTED', 'VOIDED', 'PARTIALLY_RETURNED', 'RETURNED')),
    CONSTRAINT chk_penjualan_amounts CHECK (
        subtotal >= 0 AND total_amount >= 0 AND amount_received >= 0 AND change_amount >= 0
    ),
    CONSTRAINT chk_penjualan_bon_customer CHECK (
        (payment_method = 'BON' AND sale_type = 'GROSIR' AND customer_id IS NOT NULL)
        OR (payment_method <> 'BON' AND customer_id IS NOT NULL)
        OR (payment_method <> 'BON' AND customer_id IS NULL)
    ),
    CONSTRAINT fk_penjualan_kasir FOREIGN KEY (cashier_id)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_penjualan_pelanggan FOREIGN KEY (customer_id)
        REFERENCES pelanggan (customer_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_penjualan (
    sale_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_code_snapshot VARCHAR(80) NOT NULL,
    product_name_snapshot VARCHAR(200) NOT NULL,
    unit_snapshot VARCHAR(30) NOT NULL,
    sale_unit_snapshot VARCHAR(30) NOT NULL,
    price_unit_snapshot VARCHAR(30) NOT NULL,
    quantity DECIMAL(18,6) NOT NULL,
    stock_quantity_snapshot DECIMAL(18,6) NOT NULL,
    retail_units_per_stock_unit_snapshot DECIMAL(14,3) NULL,
    price_type VARCHAR(20) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL,
    line_total DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (sale_item_id),
    KEY idx_detail_penjualan_sale (sale_id),
    KEY idx_detail_penjualan_product (product_id),
    CONSTRAINT chk_detail_penjualan_quantity CHECK (quantity > 0),
    CONSTRAINT chk_detail_penjualan_stock_quantity CHECK (stock_quantity_snapshot > 0),
    CONSTRAINT chk_detail_penjualan_conversion CHECK (
        (retail_units_per_stock_unit_snapshot IS NOT NULL AND retail_units_per_stock_unit_snapshot >= 1)
        OR (retail_units_per_stock_unit_snapshot IS NULL AND price_type = 'GROSIR')
    ),
    CONSTRAINT chk_detail_penjualan_price_type CHECK (price_type IN ('ECERAN', 'GROSIR')),
    CONSTRAINT chk_detail_penjualan_amounts CHECK (unit_price >= 0 AND line_total >= 0),
    CONSTRAINT fk_detail_penjualan_penjualan FOREIGN KEY (sale_id)
        REFERENCES penjualan (sale_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_detail_penjualan_barang FOREIGN KEY (product_id)
        REFERENCES barang (product_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alokasi_stok_penjualan (
    allocation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_item_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(18,6) NOT NULL,
    PRIMARY KEY (allocation_id),
    KEY idx_alokasi_sale_item (sale_item_id),
    KEY idx_alokasi_location (location_id),
    CONSTRAINT chk_alokasi_quantity_positive CHECK (quantity > 0),
    CONSTRAINT fk_alokasi_detail_penjualan FOREIGN KEY (sale_item_id)
        REFERENCES detail_penjualan (sale_item_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_alokasi_lokasi FOREIGN KEY (location_id)
        REFERENCES lokasi_persediaan (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penyesuaian_penjualan (
    sale_adjustment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    adjustment_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    reason TEXT NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME NULL,
    PRIMARY KEY (sale_adjustment_id),
    KEY idx_penyesuaian_penjualan_sale (sale_id),
    KEY idx_penyesuaian_penjualan_status (status, requested_at),
    KEY idx_penyesuaian_penjualan_requester (requested_by),
    KEY idx_penyesuaian_penjualan_approver (approved_by),
    CONSTRAINT chk_penyesuaian_penjualan_type CHECK (adjustment_type IN ('VOID', 'RETUR')),
    CONSTRAINT chk_penyesuaian_penjualan_status CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED')),
    CONSTRAINT fk_penyesuaian_penjualan_sale FOREIGN KEY (sale_id)
        REFERENCES penjualan (sale_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_penyesuaian_penjualan_requester FOREIGN KEY (requested_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_penyesuaian_penjualan_approver FOREIGN KEY (approved_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_penyesuaian_penjualan (
    sale_adjustment_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_adjustment_id BIGINT UNSIGNED NOT NULL,
    sale_allocation_id BIGINT UNSIGNED NOT NULL,
    restock_location_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(18,6) NOT NULL,
    refund_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (sale_adjustment_item_id),
    KEY idx_detail_penyesuaian_header (sale_adjustment_id),
    KEY idx_detail_penyesuaian_allocation (sale_allocation_id),
    KEY idx_detail_penyesuaian_location (restock_location_id),
    CONSTRAINT chk_detail_penyesuaian_quantity CHECK (quantity > 0),
    CONSTRAINT chk_detail_penyesuaian_refund CHECK (refund_amount >= 0),
    CONSTRAINT fk_detail_penyesuaian_header FOREIGN KEY (sale_adjustment_id)
        REFERENCES penyesuaian_penjualan (sale_adjustment_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_detail_penyesuaian_alokasi FOREIGN KEY (sale_allocation_id)
        REFERENCES alokasi_stok_penjualan (allocation_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_detail_penyesuaian_lokasi FOREIGN KEY (restock_location_id)
        REFERENCES lokasi_persediaan (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kebijakan_kredit (
    credit_policy_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    set_by BIGINT UNSIGNED NOT NULL,
    max_tenor_days INT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (credit_policy_id),
    KEY idx_kebijakan_kredit_effective (effective_from, effective_to),
    CONSTRAINT chk_kebijakan_kredit_tenor CHECK (max_tenor_days > 0),
    CONSTRAINT chk_kebijakan_kredit_dates CHECK (effective_to IS NULL OR effective_to >= effective_from),
    CONSTRAINT fk_kebijakan_kredit_pengguna FOREIGN KEY (set_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batch_saldo_awal (
    opening_batch_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_by BIGINT UNSIGNED NOT NULL,
    posted_by BIGINT UNSIGNED NULL,
    opening_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    PRIMARY KEY (opening_batch_id),
    KEY idx_batch_saldo_awal_status_date (status, opening_date),
    CONSTRAINT chk_batch_saldo_awal_status CHECK (status IN ('DRAFT', 'POSTED', 'CANCELLED')),
    CONSTRAINT fk_batch_saldo_awal_creator FOREIGN KEY (created_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_batch_saldo_awal_poster FOREIGN KEY (posted_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS piutang_awal (
    opening_receivable_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opening_batch_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    outstanding_amount DECIMAL(14,2) NOT NULL,
    due_date DATE NULL,
    notes TEXT NULL,
    PRIMARY KEY (opening_receivable_id),
    KEY idx_piutang_awal_batch (opening_batch_id),
    KEY idx_piutang_awal_customer (customer_id),
    CONSTRAINT chk_piutang_awal_amount CHECK (outstanding_amount > 0),
    CONSTRAINT fk_piutang_awal_batch FOREIGN KEY (opening_batch_id)
        REFERENCES batch_saldo_awal (opening_batch_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_piutang_awal_customer FOREIGN KEY (customer_id)
        REFERENCES pelanggan (customer_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS piutang (
    receivable_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    opening_receivable_id BIGINT UNSIGNED NULL,
    credit_policy_id BIGINT UNSIGNED NULL,
    original_amount DECIMAL(14,2) NOT NULL,
    due_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    PRIMARY KEY (receivable_id),
    UNIQUE KEY uq_piutang_sale (sale_id),
    UNIQUE KEY uq_piutang_opening (opening_receivable_id),
    KEY idx_piutang_customer_status (customer_id, status),
    KEY idx_piutang_due_date (due_date, status),
    KEY idx_piutang_credit_policy (credit_policy_id),
    CONSTRAINT chk_piutang_one_source CHECK (
        (sale_id IS NOT NULL AND opening_receivable_id IS NULL)
        OR (sale_id IS NULL AND opening_receivable_id IS NOT NULL)
    ),
    CONSTRAINT chk_piutang_amount CHECK (original_amount > 0),
    CONSTRAINT chk_piutang_status CHECK (status IN ('OPEN', 'PARTIALLY_PAID', 'PAID', 'WRITTEN_OFF', 'ADJUSTED')),
    CONSTRAINT fk_piutang_customer FOREIGN KEY (customer_id)
        REFERENCES pelanggan (customer_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_piutang_sale FOREIGN KEY (sale_id)
        REFERENCES penjualan (sale_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_piutang_opening FOREIGN KEY (opening_receivable_id)
        REFERENCES piutang_awal (opening_receivable_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_piutang_policy FOREIGN KEY (credit_policy_id)
        REFERENCES kebijakan_kredit (credit_policy_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembayaran_piutang (
    receivable_payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receivable_id BIGINT UNSIGNED NOT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    PRIMARY KEY (receivable_payment_id),
    KEY idx_pembayaran_piutang_receivable_paid (receivable_id, paid_at),
    KEY idx_pembayaran_piutang_user (recorded_by),
    CONSTRAINT chk_pembayaran_piutang_amount CHECK (amount > 0),
    CONSTRAINT chk_pembayaran_piutang_method CHECK (payment_method IN ('TUNAI', 'TRANSFER')),
    CONSTRAINT fk_pembayaran_piutang_receivable FOREIGN KEY (receivable_id)
        REFERENCES piutang (receivable_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pembayaran_piutang_user FOREIGN KEY (recorded_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS perubahan_jatuh_tempo_piutang (
    due_date_change_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receivable_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    old_due_date DATE NOT NULL,
    new_due_date DATE NOT NULL,
    reason TEXT NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (due_date_change_id),
    KEY idx_perubahan_tempo_receivable (receivable_id, changed_at),
    KEY idx_perubahan_tempo_user (changed_by),
    CONSTRAINT chk_perubahan_tempo_date CHECK (new_due_date <> old_due_date),
    CONSTRAINT fk_perubahan_tempo_receivable FOREIGN KEY (receivable_id)
        REFERENCES piutang (receivable_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_perubahan_tempo_user FOREIGN KEY (changed_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penyesuaian_piutang (
    receivable_adjustment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receivable_id BIGINT UNSIGNED NOT NULL,
    sale_adjustment_id BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NOT NULL,
    adjustment_type VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    reason TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (receivable_adjustment_id),
    KEY idx_penyesuaian_piutang_receivable (receivable_id, created_at),
    KEY idx_penyesuaian_piutang_sale_adjustment (sale_adjustment_id),
    KEY idx_penyesuaian_piutang_approver (approved_by),
    CONSTRAINT chk_penyesuaian_piutang_type CHECK (adjustment_type IN ('RETUR', 'WRITE_OFF')),
    CONSTRAINT chk_penyesuaian_piutang_amount CHECK (amount > 0),
    CONSTRAINT fk_penyesuaian_piutang_receivable FOREIGN KEY (receivable_id)
        REFERENCES piutang (receivable_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_penyesuaian_piutang_sale_adjustment FOREIGN KEY (sale_adjustment_id)
        REFERENCES penyesuaian_penjualan (sale_adjustment_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_penyesuaian_piutang_approver FOREIGN KEY (approved_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penerimaan_barang (
    goods_receipt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id BIGINT UNSIGNED NOT NULL,
    received_by BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(100) NULL,
    received_date DATE NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (goods_receipt_id),
    KEY idx_penerimaan_supplier_date (supplier_id, received_date),
    KEY idx_penerimaan_user_date (received_by, received_date),
    CONSTRAINT chk_penerimaan_total CHECK (total_amount >= 0),
    CONSTRAINT fk_penerimaan_pemasok FOREIGN KEY (supplier_id)
        REFERENCES pemasok (supplier_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_penerimaan_pengguna FOREIGN KEY (received_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_penerimaan_barang (
    goods_receipt_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    goods_receipt_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_purchase_cost DECIMAL(14,2) NOT NULL,
    line_total DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (goods_receipt_item_id),
    KEY idx_detail_penerimaan_header (goods_receipt_id),
    KEY idx_detail_penerimaan_product (product_id),
    CONSTRAINT chk_detail_penerimaan_quantity CHECK (quantity > 0),
    CONSTRAINT chk_detail_penerimaan_amounts CHECK (unit_purchase_cost >= 0 AND line_total >= 0),
    CONSTRAINT fk_detail_penerimaan_header FOREIGN KEY (goods_receipt_id)
        REFERENCES penerimaan_barang (goods_receipt_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_detail_penerimaan_barang FOREIGN KEY (product_id)
        REFERENCES barang (product_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mutasi_stok (
    stock_transfer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_location_id BIGINT UNSIGNED NOT NULL,
    to_location_id BIGINT UNSIGNED NOT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    transferred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
    notes TEXT NULL,
    PRIMARY KEY (stock_transfer_id),
    KEY idx_mutasi_asal (from_location_id),
    KEY idx_mutasi_tujuan (to_location_id),
    KEY idx_mutasi_user_date (recorded_by, transferred_at),
    CONSTRAINT chk_mutasi_status CHECK (status IN ('DRAFT', 'POSTED', 'CANCELLED')),
    CONSTRAINT chk_mutasi_lokasi_berbeda CHECK (from_location_id <> to_location_id),
    CONSTRAINT fk_mutasi_lokasi_asal FOREIGN KEY (from_location_id)
        REFERENCES lokasi_persediaan (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_lokasi_tujuan FOREIGN KEY (to_location_id)
        REFERENCES lokasi_persediaan (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_pengguna FOREIGN KEY (recorded_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_mutasi_stok (
    stock_transfer_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_transfer_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_cost_snapshot DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (stock_transfer_item_id),
    KEY idx_detail_mutasi_header (stock_transfer_id),
    KEY idx_detail_mutasi_product (product_id),
    CONSTRAINT chk_detail_mutasi_quantity CHECK (quantity > 0),
    CONSTRAINT chk_detail_mutasi_cost CHECK (unit_cost_snapshot >= 0),
    CONSTRAINT fk_detail_mutasi_header FOREIGN KEY (stock_transfer_id)
        REFERENCES mutasi_stok (stock_transfer_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_detail_mutasi_barang FOREIGN KEY (product_id)
        REFERENCES barang (product_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penyesuaian_stok (
    stock_adjustment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recorded_by BIGINT UNSIGNED NOT NULL,
    adjusted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
    PRIMARY KEY (stock_adjustment_id),
    KEY idx_penyesuaian_stok_user_date (recorded_by, adjusted_at),
    CONSTRAINT chk_penyesuaian_stok_status CHECK (status IN ('DRAFT', 'POSTED', 'CANCELLED')),
    CONSTRAINT fk_penyesuaian_stok_pengguna FOREIGN KEY (recorded_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_penyesuaian_stok (
    stock_adjustment_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_adjustment_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity_delta DECIMAL(14,3) NOT NULL,
    unit_cost_snapshot DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (stock_adjustment_item_id),
    KEY idx_detail_penyesuaian_stok_header (stock_adjustment_id),
    KEY idx_detail_penyesuaian_stok_product (product_id),
    KEY idx_detail_penyesuaian_stok_location (location_id),
    CONSTRAINT chk_detail_penyesuaian_stok_delta CHECK (quantity_delta <> 0),
    CONSTRAINT chk_detail_penyesuaian_stok_cost CHECK (unit_cost_snapshot >= 0),
    CONSTRAINT fk_detail_penyesuaian_stok_header FOREIGN KEY (stock_adjustment_id)
        REFERENCES penyesuaian_stok (stock_adjustment_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_detail_penyesuaian_stok_barang FOREIGN KEY (product_id)
        REFERENCES barang (product_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_detail_penyesuaian_stok_lokasi FOREIGN KEY (location_id)
        REFERENCES lokasi_persediaan (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_stok_awal (
    opening_stock_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opening_batch_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (opening_stock_item_id),
    UNIQUE KEY uq_stok_awal_batch_product_location (opening_batch_id, product_id, location_id),
    KEY idx_stok_awal_barang (product_id),
    KEY idx_stok_awal_lokasi (location_id),
    CONSTRAINT chk_stok_awal_quantity CHECK (quantity >= 0),
    CONSTRAINT chk_stok_awal_cost CHECK (unit_cost >= 0),
    CONSTRAINT fk_stok_awal_batch FOREIGN KEY (opening_batch_id)
        REFERENCES batch_saldo_awal (opening_batch_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_stok_awal_barang FOREIGN KEY (product_id)
        REFERENCES barang (product_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_stok_awal_lokasi FOREIGN KEY (location_id)
        REFERENCES lokasi_persediaan (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pergerakan_persediaan (
    inventory_movement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    sale_allocation_id BIGINT UNSIGNED NULL,
    sale_adjustment_item_id BIGINT UNSIGNED NULL,
    goods_receipt_item_id BIGINT UNSIGNED NULL,
    stock_transfer_item_id BIGINT UNSIGNED NULL,
    stock_adjustment_item_id BIGINT UNSIGNED NULL,
    opening_stock_item_id BIGINT UNSIGNED NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    quantity_delta DECIMAL(18,6) NOT NULL,
    unit_cost_snapshot DECIMAL(14,2) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (inventory_movement_id),
    UNIQUE KEY uq_pergerakan_alokasi_sale (sale_allocation_id),
    UNIQUE KEY uq_pergerakan_retur (sale_adjustment_item_id),
    UNIQUE KEY uq_pergerakan_penerimaan (goods_receipt_item_id),
    KEY idx_pergerakan_transfer_item (stock_transfer_item_id),
    KEY idx_pergerakan_adjustment_item (stock_adjustment_item_id),
    UNIQUE KEY uq_pergerakan_stok_awal (opening_stock_item_id),
    KEY idx_pergerakan_barang_lokasi_waktu (product_id, location_id, occurred_at),
    KEY idx_pergerakan_user (recorded_by),
    CONSTRAINT chk_pergerakan_one_source CHECK (
        (sale_allocation_id IS NOT NULL)
        + (sale_adjustment_item_id IS NOT NULL)
        + (goods_receipt_item_id IS NOT NULL)
        + (stock_transfer_item_id IS NOT NULL)
        + (stock_adjustment_item_id IS NOT NULL)
        + (opening_stock_item_id IS NOT NULL) = 1
    ),
    CONSTRAINT chk_pergerakan_quantity CHECK (quantity_delta <> 0),
    CONSTRAINT chk_pergerakan_cost CHECK (unit_cost_snapshot >= 0),
    CONSTRAINT fk_pergerakan_barang FOREIGN KEY (product_id)
        REFERENCES barang (product_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pergerakan_lokasi FOREIGN KEY (location_id)
        REFERENCES lokasi_persediaan (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pergerakan_alokasi_sale FOREIGN KEY (sale_allocation_id)
        REFERENCES alokasi_stok_penjualan (allocation_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pergerakan_retur FOREIGN KEY (sale_adjustment_item_id)
        REFERENCES detail_penyesuaian_penjualan (sale_adjustment_item_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pergerakan_penerimaan FOREIGN KEY (goods_receipt_item_id)
        REFERENCES detail_penerimaan_barang (goods_receipt_item_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pergerakan_mutasi FOREIGN KEY (stock_transfer_item_id)
        REFERENCES detail_mutasi_stok (stock_transfer_item_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pergerakan_penyesuaian FOREIGN KEY (stock_adjustment_item_id)
        REFERENCES detail_penyesuaian_stok (stock_adjustment_item_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pergerakan_stok_awal FOREIGN KEY (opening_stock_item_id)
        REFERENCES detail_stok_awal (opening_stock_item_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pergerakan_pengguna FOREIGN KEY (recorded_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS akun_kas (
    cash_account_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_code VARCHAR(30) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_type VARCHAR(20) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (cash_account_id),
    UNIQUE KEY uq_akun_kas_code (account_code),
    CONSTRAINT chk_akun_kas_type CHECK (account_type IN ('TUNAI', 'BANK'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kategori_pengeluaran_operasional (
    expense_category_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (expense_category_id),
    UNIQUE KEY uq_kategori_pengeluaran_name (category_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pengeluaran_operasional (
    operating_expense_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_category_id BIGINT UNSIGNED NOT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    payee VARCHAR(150) NULL,
    amount DECIMAL(14,2) NOT NULL,
    spent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (operating_expense_id),
    KEY idx_pengeluaran_category_date (expense_category_id, spent_at),
    KEY idx_pengeluaran_user_date (recorded_by, spent_at),
    CONSTRAINT chk_pengeluaran_amount CHECK (amount > 0),
    CONSTRAINT fk_pengeluaran_kategori FOREIGN KEY (expense_category_id)
        REFERENCES kategori_pengeluaran_operasional (expense_category_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_pengeluaran_pengguna FOREIGN KEY (recorded_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saldo_kas_awal (
    opening_cash_balance_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opening_batch_id BIGINT UNSIGNED NOT NULL,
    cash_account_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (opening_cash_balance_id),
    UNIQUE KEY uq_saldo_kas_awal_batch_account (opening_batch_id, cash_account_id),
    CONSTRAINT chk_saldo_kas_awal_amount CHECK (amount >= 0),
    CONSTRAINT fk_saldo_kas_awal_batch FOREIGN KEY (opening_batch_id)
        REFERENCES batch_saldo_awal (opening_batch_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_saldo_kas_awal_account FOREIGN KEY (cash_account_id)
        REFERENCES akun_kas (cash_account_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS mutasi_kas (
    cash_movement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cash_account_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    receivable_payment_id BIGINT UNSIGNED NULL,
    goods_receipt_id BIGINT UNSIGNED NULL,
    operating_expense_id BIGINT UNSIGNED NULL,
    sale_adjustment_id BIGINT UNSIGNED NULL,
    opening_cash_balance_id BIGINT UNSIGNED NULL,
    manual_cash_transaction_id BIGINT UNSIGNED NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    direction VARCHAR(10) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    PRIMARY KEY (cash_movement_id),
    UNIQUE KEY uq_mutasi_kas_sale (sale_id),
    UNIQUE KEY uq_mutasi_kas_payment (receivable_payment_id),
    UNIQUE KEY uq_mutasi_kas_receipt (goods_receipt_id),
    UNIQUE KEY uq_mutasi_kas_expense (operating_expense_id),
    UNIQUE KEY uq_mutasi_kas_adjustment (sale_adjustment_id),
    UNIQUE KEY uq_mutasi_kas_opening (opening_cash_balance_id),
    UNIQUE KEY uq_mutasi_kas_manual (manual_cash_transaction_id),
    KEY idx_mutasi_kas_account_date (cash_account_id, occurred_at),
    KEY idx_mutasi_kas_user_date (recorded_by, occurred_at),
    CONSTRAINT chk_mutasi_kas_one_source CHECK (
        (sale_id IS NOT NULL)
        + (receivable_payment_id IS NOT NULL)
        + (goods_receipt_id IS NOT NULL)
        + (operating_expense_id IS NOT NULL)
        + (sale_adjustment_id IS NOT NULL)
        + (opening_cash_balance_id IS NOT NULL)
        + (manual_cash_transaction_id IS NOT NULL) = 1
    ),
    CONSTRAINT chk_mutasi_kas_direction CHECK (direction IN ('MASUK', 'KELUAR')),
    CONSTRAINT chk_mutasi_kas_method CHECK (payment_method IN ('TUNAI', 'TRANSFER')),
    CONSTRAINT chk_mutasi_kas_amount CHECK (amount > 0),
    CONSTRAINT fk_mutasi_kas_account FOREIGN KEY (cash_account_id)
        REFERENCES akun_kas (cash_account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_kas_sale FOREIGN KEY (sale_id)
        REFERENCES penjualan (sale_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_kas_payment FOREIGN KEY (receivable_payment_id)
        REFERENCES pembayaran_piutang (receivable_payment_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_kas_receipt FOREIGN KEY (goods_receipt_id)
        REFERENCES penerimaan_barang (goods_receipt_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_kas_expense FOREIGN KEY (operating_expense_id)
        REFERENCES pengeluaran_operasional (operating_expense_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_kas_adjustment FOREIGN KEY (sale_adjustment_id)
        REFERENCES penyesuaian_penjualan (sale_adjustment_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_kas_opening FOREIGN KEY (opening_cash_balance_id)
        REFERENCES saldo_kas_awal (opening_cash_balance_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_kas_manual FOREIGN KEY (manual_cash_transaction_id)
        REFERENCES transaksi_kas_manual (manual_cash_transaction_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mutasi_kas_user FOREIGN KEY (recorded_by)
        REFERENCES pengguna (user_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO peran (role_code, role_name)
VALUES
    ('OWNER', 'Owner'),
    ('KASIR', 'Kasir'),
    ('STAF_GUDANG', 'Staf Gudang')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

INSERT INTO lokasi_persediaan (location_code, location_name)
VALUES
    ('TOKO', 'Toko'),
    ('GUDANG', 'Gudang')
ON DUPLICATE KEY UPDATE location_name = VALUES(location_name);

INSERT INTO akun_kas (account_code, account_name, account_type)
VALUES
    ('KAS_TUNAI', 'Kas Tunai', 'TUNAI'),
    ('BANK_TRANSFER', 'Bank Transfer', 'BANK')
ON DUPLICATE KEY UPDATE
    account_name = VALUES(account_name),
    account_type = VALUES(account_type);
