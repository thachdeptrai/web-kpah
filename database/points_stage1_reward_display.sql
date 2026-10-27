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
