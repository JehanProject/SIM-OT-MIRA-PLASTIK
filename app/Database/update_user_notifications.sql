-- Run once on an existing Mira Plastik database.
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
