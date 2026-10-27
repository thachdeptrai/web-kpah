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
