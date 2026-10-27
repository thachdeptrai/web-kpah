-- Stage 6: minimum member tier per Point reward.
-- Does not change award ratios, existing Point wallets or game tables.
CREATE TABLE IF NOT EXISTS kp_points_reward_tiers (
  reward_id INT UNSIGNED NOT NULL PRIMARY KEY,
  min_tier TINYINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
