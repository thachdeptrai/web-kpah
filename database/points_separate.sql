-- Dedicated KPAH website Point database (11 kp_points_* tables).
-- New installation ONLY: the statements below CREATE EMPTY tables.
-- If you already have wallets, transactions, redemptions or admin settings,
-- EXPORT+IMPORT ALL DATA from old kpah.kp_points_* first; do NOT reset it.
-- Keep the GAME database (team_user, tob_char, kp_bank_orders, notification
-- tables, item catalog) intact on the SAME MySQL/MariaDB server.
-- Web MySQL user must have SELECT on game and read/write on kpah_points.
-- Update the name below if you choose another database name.
CREATE DATABASE IF NOT EXISTS `kpah_points` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kpah_points`;

-- -------- database/loyalty_points.sql --------
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

-- Activation moment is captured when this migration runs on the real database.
-- Historical orders are not credited silently.
CREATE TABLE IF NOT EXISTS kp_points_program (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  points_per_1000 INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO kp_points_program (id, points_per_1000) VALUES (1, 1);

-- Stage 7: global admin-controlled Point reward redemption switch.
-- Only affects NEW requests to exchange Point for gifts.
-- This migration does not change wallets, Point earning, past orders or game tables.
CREATE TABLE IF NOT EXISTS kp_points_redemption_control (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  changed_by INT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO kp_points_redemption_control (id,is_enabled) VALUES (1,1);

-- -------- database/points_stage1_reward_display.sql --------
-- Stage 1: presentation-only metadata for Point reward catalog.
-- Compatible with the existing kp_points_rewards table, no MyISAM writes.
-- Run once after backing up the existing Point tables.
CREATE TABLE IF NOT EXISTS kp_points_reward_display (
  reward_id INT UNSIGNED NOT NULL PRIMARY KEY,
  icon_id INT UNSIGNED DEFAULT NULL,
  item_id INT UNSIGNED DEFAULT NULL,
  option_lines TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------- database/points_stage2_redemption_events.sql --------
-- Stage 2: append-only audit for reward fulfillment state transitions.
-- Safe on existing deployments: no ALTER on the game tables.
CREATE TABLE IF NOT EXISTS kp_points_redemption_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  redemption_id BIGINT UNSIGNED NOT NULL,
  actor_admin_id INT NOT NULL,
  from_status VARCHAR(24) NOT NULL,
  to_status VARCHAR(24) NOT NULL,
  note VARCHAR(200) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_points_events_redemption (redemption_id, id),
  KEY idx_points_events_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------- database/points_stage3_redemption_targets.sql --------
-- Stage 3: store the verified recipient character for each Point redemption.
-- New InnoDB metadata table; game tables remain untouched.
CREATE TABLE IF NOT EXISTS kp_points_redemption_targets (
  redemption_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  char_id INT NOT NULL,
  char_name VARCHAR(100) NOT NULL,
  selected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_points_target_character (char_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------- database/points_stage4_audit.sql --------
-- Stage 4: append-only Point audit metadata. No changes to game tables.
-- Compatible with original InnoDB ledger: ledger remains financial source of truth.
CREATE TABLE IF NOT EXISTS kp_points_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  actor_admin_id INT DEFAULT NULL,
  redemption_id BIGINT UNSIGNED DEFAULT NULL,
  operation VARCHAR(24) NOT NULL,
  reference VARCHAR(80) NOT NULL DEFAULT '',
  points_delta BIGINT NOT NULL DEFAULT 0,
  balance_before BIGINT DEFAULT NULL,
  balance_after BIGINT DEFAULT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  device_type VARCHAR(24) NOT NULL DEFAULT '',
  browser VARCHAR(24) NOT NULL DEFAULT '',
  location_hint VARCHAR(120) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_points_audit_user_time (user_id, id),
  KEY idx_points_audit_time (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------- database/points_stage6_tiers.sql --------
-- Stage 6: minimum member tier per Point reward.
-- Does not change award ratios, existing Point wallets or game tables.
CREATE TABLE IF NOT EXISTS kp_points_reward_tiers (
  reward_id INT UNSIGNED NOT NULL PRIMARY KEY,
  min_tier TINYINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
