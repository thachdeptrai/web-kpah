-- KPAH web wallet migration. Backup kpah before running.
-- The user ALREADY added team_user.vnd: do not ADD it again.
-- Run once on the same database used by the website.
ALTER TABLE kp_bank_orders ADD COLUMN delivery_mode VARCHAR(16) NOT NULL DEFAULT 'game' AFTER char_id;
ALTER TABLE kp_bank_orders ADD COLUMN bonus_percent INT UNSIGNED NOT NULL DEFAULT 0 AFTER delivery_mode;

CREATE TABLE IF NOT EXISTS kp_vnd_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 bank_bonus_percent INT UNSIGNED NOT NULL DEFAULT 10,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO kp_vnd_settings(id,bank_bonus_percent) VALUES(1,10);

-- InnoDB authoritative balance; team_user.vnd (MyISAM) is only mirrored by site.
CREATE TABLE IF NOT EXISTS kp_vnd_wallets (
 user_id INT NOT NULL PRIMARY KEY,
 balance BIGINT UNSIGNED NOT NULL DEFAULT 0,
 deposited BIGINT UNSIGNED NOT NULL DEFAULT 0,
 bonus_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
 spent BIGINT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kp_vnd_ledger (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 source_type VARCHAR(24) NOT NULL,
 source_ref VARCHAR(80) NOT NULL,
 delta_vnd BIGINT NOT NULL,
 base_vnd BIGINT UNSIGNED NOT NULL DEFAULT 0,
 bonus_vnd BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_vnd_source (source_type,source_ref),
 KEY idx_vnd_user (user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kp_vnd_exchanges (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 char_id INT NOT NULL,
 currency ENUM('luong','luonglock') NOT NULL,
 amount_vnd INT UNSIGNED NOT NULL,
 units INT UNSIGNED NOT NULL,
 request_token CHAR(32) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'crediting',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_vnd_exchange_token (request_token),
 KEY idx_vnd_exchange_user (user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing bank invoices default to 'game'; do not retroactively credit
-- any existing queued/completed/needs_review invoices into the new wallet.
-- Admin role is VARCHAR(20), so 'key' needs no ALTER.
