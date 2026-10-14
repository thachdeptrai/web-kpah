-- Loyalty Points for the KPAH website. Run in database kpah.
-- New InnoDB tables only: no ALTER on MyISAM game tables or bank orders.
-- Safe to run again (CREATE TABLE IF NOT EXISTS).
-- One account earns 1 Point per 1,000 VND of eligible successful top-ups.

CREATE TABLE IF NOT EXISTS kp_points_wallet (
  user_id INT NOT NULL PRIMARY KEY,
  balance BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_earned BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_spent BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The unique (source_type, source_ref) stops webhook or QR polling from awarding twice.
CREATE TABLE IF NOT EXISTS kp_points_ledger (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  points_delta BIGINT NOT NULL,
  source_type VARCHAR(24) NOT NULL,
  source_ref VARCHAR(80) NOT NULL,
  amount_vnd INT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_points_source (source_type, source_ref),
  KEY idx_points_user_time (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalog is empty by default. Do NOT invent or auto-grant in-game items.
CREATE TABLE IF NOT EXISTS kp_points_rewards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  description TEXT NULL,
  points_cost BIGINT UNSIGNED NOT NULL,
  stock INT UNSIGNED DEFAULT NULL,
  delivery_type VARCHAR(24) NOT NULL DEFAULT 'manual',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_points_rewards_active (is_active, points_cost)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kp_points_redemptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  reward_id INT UNSIGNED NOT NULL,
  reward_title VARCHAR(120) NOT NULL,
  points_cost BIGINT UNSIGNED NOT NULL,
  request_token CHAR(32) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_points_redemption_request (request_token),
  KEY idx_points_redemption_user (user_id, created_at),
  KEY idx_points_redemption_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
