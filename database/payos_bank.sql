-- Run once on the kpah database. Non-destructive migration.
CREATE TABLE IF NOT EXISTS kp_bank_orders (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_code BIGINT UNSIGNED NOT NULL,
 user_id INT NOT NULL,
 username VARCHAR(45) NOT NULL,
 amount INT UNSIGNED NOT NULL,
 currency ENUM('luong','luonglock') NOT NULL,
 units INT UNSIGNED NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'creating',
 checkout_url VARCHAR(600) DEFAULT NULL,
 payment_link_id VARCHAR(100) DEFAULT NULL,
 provider_reference VARCHAR(100) DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 paid_at DATETIME DEFAULT NULL,
 UNIQUE KEY uq_order_code(order_code),
 UNIQUE KEY uq_payment_link(payment_link_id),
 KEY idx_user(user_id,created_at),
 KEY idx_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS kp_bank_webhook_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_hash CHAR(64) NOT NULL,
 order_code BIGINT UNSIGNED NOT NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_event_hash(event_hash),
 KEY idx_order(order_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
