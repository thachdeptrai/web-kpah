-- User center, append-only security history and personal notifications.
-- Run once in your configured KPAH database. Does not change game tables.
CREATE TABLE IF NOT EXISTS kp_user_notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 title VARCHAR(120) NOT NULL,
 body VARCHAR(500) NOT NULL,
 category VARCHAR(20) NOT NULL DEFAULT 'system',
 link_path VARCHAR(180) NOT NULL DEFAULT '',
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_user_unread (user_id,is_read,id),
 KEY idx_user_newest (user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kp_user_security_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 event_type VARCHAR(32) NOT NULL,
 device_label VARCHAR(120) NOT NULL DEFAULT '',
 ip_hint VARCHAR(72) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_user_recent (user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared operations inbox for administrators. No player data is copied here.
CREATE TABLE IF NOT EXISTS kp_admin_notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(120) NOT NULL,
 body VARCHAR(500) NOT NULL,
 category VARCHAR(20) NOT NULL DEFAULT 'system',
 link_path VARCHAR(180) NOT NULL DEFAULT '',
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_admin_unread (is_read,id),
 KEY idx_admin_newest (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
