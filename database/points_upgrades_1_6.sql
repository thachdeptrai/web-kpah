-- NGHIEN KPAH Point upgrades (1 -> 6). BACK UP first and execute once in Navicat.
-- Safe additive InnoDB tables. No DROP/ALTER of game tables, no changes to wallets.
-- Each CREATE is idempotent and can be reapplied if already installed.
-- Original migrations stay independently versioned under database/.

-- database/points_stage1_reward_display.sql
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

-- database/points_stage2_redemption_events.sql
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

-- database/points_stage3_redemption_targets.sql
-- Stage 3: store the verified recipient character for each Point redemption.
-- New InnoDB metadata table; game tables remain untouched.
CREATE TABLE IF NOT EXISTS kp_points_redemption_targets (
  redemption_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  char_id INT NOT NULL,
  char_name VARCHAR(100) NOT NULL,
  selected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_points_target_character (char_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- database/points_stage4_audit.sql
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

-- database/points_stage6_tiers.sql
-- Stage 6: minimum member tier per Point reward.
-- Does not change award ratios, existing Point wallets or game tables.
CREATE TABLE IF NOT EXISTS kp_points_reward_tiers (
  reward_id INT UNSIGNED NOT NULL PRIMARY KEY,
  min_tier TINYINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
