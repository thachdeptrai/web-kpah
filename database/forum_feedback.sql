-- Forum reports, replies and admin actions live in kpah_points, not kpah.
-- Both schemas must be on the SAME MySQL/MariaDB server used by kp_db().
-- New installation: create empty tables. For existing live reports use
-- database/forum_move_existing_to_kpah_points.sql AFTER creating these tables.
-- Non-destructive schema creation. Import into kpah_points, not kpah.
-- Database `kpah_points` must already exist; no CREATE DATABASE privilege required.
CREATE TABLE IF NOT EXISTS `kpah_points`.`kp_forum_topics` (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 public_alias VARCHAR(16) NOT NULL,
 kind ENUM('bug','suggestion') NOT NULL,
 platform VARCHAR(24) NOT NULL DEFAULT 'Khác',
 title VARCHAR(140) NOT NULL,
 body TEXT NOT NULL,
 status ENUM('new','reviewing','resolved','declined') NOT NULL DEFAULT 'new',
 visibility ENUM('public','hidden') NOT NULL DEFAULT 'public',
 submission_token CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_submission_token (submission_token),
 KEY idx_public (visibility,id),
 KEY idx_status (status,id),
 KEY idx_owner (user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kpah_points`.`kp_forum_replies` (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 topic_id BIGINT UNSIGNED NOT NULL,
 admin_id INT NOT NULL,
 body TEXT NOT NULL,
 reply_token CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_reply_token (reply_token),
 KEY idx_topic (topic_id,id),
 CONSTRAINT fk_kp_forum_reply_topic FOREIGN KEY (topic_id)
   REFERENCES `kpah_points`.`kp_forum_topics`(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kpah_points`.`kp_forum_actions` (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 topic_id BIGINT UNSIGNED NOT NULL,
 admin_id INT NOT NULL,
 previous_status VARCHAR(20) NOT NULL,
 new_status VARCHAR(20) NOT NULL,
 previous_visibility VARCHAR(20) NOT NULL,
 new_visibility VARCHAR(20) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_topic (topic_id,id),
 CONSTRAINT fk_kp_forum_action_topic FOREIGN KEY (topic_id)
   REFERENCES `kpah_points`.`kp_forum_topics`(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
