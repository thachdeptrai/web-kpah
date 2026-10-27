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
