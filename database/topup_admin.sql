-- KPAH top-up administration: run ONCE in the existing KPAH database.
-- Game tables remain untouched (tob_char is MyISAM).
-- 10,000 VND = 100 unlocked Luong. Other rates must be explicitly configured.
CREATE TABLE IF NOT EXISTS kpah_topup_rates (
  currency VARCHAR(16) NOT NULL,
  vnd_unit INT UNSIGNED NOT NULL DEFAULT 10000,
  units_per_vnd_unit BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO kpah_topup_rates(currency,vnd_unit,units_per_vnd_unit) VALUES
('gold',10000,0),('luong',10000,100),('luonglock',10000,0);

CREATE TABLE IF NOT EXISTS kpah_topup_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_key CHAR(32) NOT NULL,
  admin_id BIGINT UNSIGNED NOT NULL,
  account_id INT NOT NULL,
  account_username VARCHAR(20) NOT NULL,
  char_id INT NOT NULL,
  charname VARCHAR(45) NOT NULL,
  currency VARCHAR(16) NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  balance_before BIGINT UNSIGNED NOT NULL,
  balance_after BIGINT UNSIGNED NOT NULL,
  note VARCHAR(255) NOT NULL,
  status ENUM('processing','applied','failed','needs_review') NOT NULL DEFAULT 'processing',
  error_note VARCHAR(160) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME DEFAULT NULL,
  PRIMARY KEY(id),
  UNIQUE KEY uq_topup_request_key(request_key),
  KEY idx_topup_char(char_id,created_at),
  KEY idx_topup_admin(admin_id,created_at),
  KEY idx_topup_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
