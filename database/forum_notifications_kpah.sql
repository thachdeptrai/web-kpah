-- Fix SQLSTATE[42S02] Table 'kpah.kp_admin_notifications' doesn't exist.
-- Forum content remains in kpah_points. Shared site notifications belong in kpah.
-- Safe to run multiple times; does not DELETE or overwrite notification rows.
-- Run on the SAME MySQL/MariaDB server that hosts kpah and kpah_points.
-- Back up the database first. CREATE TABLE will not repair an incompatible existing table.

CREATE TABLE IF NOT EXISTS `kpah`.`kp_admin_notifications` (
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

CREATE TABLE IF NOT EXISTS `kpah`.`kp_user_notifications` (
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

-- Both should be InnoDB. Confirm schema and structure match database/user_center.sql.
SELECT TABLE_SCHEMA,TABLE_NAME,ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA='kpah'
  AND TABLE_NAME IN ('kp_admin_notifications','kp_user_notifications')
ORDER BY TABLE_NAME;
