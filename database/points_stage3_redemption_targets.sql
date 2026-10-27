-- Stage 3: store the verified recipient character for each Point redemption.
-- New InnoDB metadata table; game tables remain untouched.
CREATE TABLE IF NOT EXISTS kp_points_redemption_targets (
  redemption_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  char_id INT NOT NULL,
  char_name VARCHAR(100) NOT NULL,
  selected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_points_target_character (char_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
