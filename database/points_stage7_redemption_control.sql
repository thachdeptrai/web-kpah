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
