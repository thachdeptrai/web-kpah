-- Monitor only. Install: php tools/farmer-monitor.php install
CREATE TABLE IF NOT EXISTS kp_farmer_monitor_state (
  char_id INT UNSIGNED NOT NULL PRIMARY KEY,
  charname VARCHAR(45) NOT NULL,
  user_id INT NOT NULL,
  farmer_points INT NULL,
  luong BIGINT UNSIGNED NOT NULL,
  boxes_today INT NULL,
  farm_ms_today BIGINT UNSIGNED NULL,
  first_sampled_at DATETIME NOT NULL,
  sampled_at DATETIME NOT NULL,
  KEY idx_farm_state_user (user_id),
  KEY idx_farm_state_sample (sampled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kp_farmer_monitor_changes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  char_id INT UNSIGNED NOT NULL,
  charname VARCHAR(45) NOT NULL,
  user_id INT NOT NULL,
  started_at DATETIME NOT NULL,
  observed_at DATETIME NOT NULL,
  interval_seconds INT UNSIGNED NOT NULL,
  points_before INT NULL,
  points_after INT NULL,
  points_delta BIGINT NULL,
  luong_before BIGINT UNSIGNED NOT NULL,
  luong_after BIGINT UNSIGNED NOT NULL,
  luong_delta BIGINT NOT NULL,
  boxes_before INT NULL,
  boxes_after INT NULL,
  farm_ms_before BIGINT UNSIGNED NULL,
  farm_ms_after BIGINT UNSIGNED NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'snapshot',
  KEY idx_farm_changes_char_time (char_id, observed_at),
  KEY idx_farm_changes_time (observed_at),
  KEY idx_farm_changes_points (points_delta, observed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
