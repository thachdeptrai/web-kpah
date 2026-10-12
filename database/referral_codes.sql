-- KPAH referral-code system.
-- Import this file into the same database configured in config.register.local.php.
-- count = total number of registrations permitted for a code.
-- used_count = successful registrations already completed.
-- Remaining uses = GREATEST(count - used_count, 0).
-- date = expiry date; NULL means no expiry.

CREATE TABLE IF NOT EXISTS `kpah_referral_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 1,
  `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `date` DATETIME NULL DEFAULT NULL,
  `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kpah_referral_code` (`code`),
  KEY `idx_kpah_referral_expiry` (`date`),
  KEY `idx_kpah_referral_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kpah_referral_code_uses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referral_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `username` VARCHAR(20) NOT NULL,
  `used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_hash` CHAR(64) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kpah_referral_username` (`username`),
  KEY `idx_kpah_referral_uses_code` (`referral_id`),
  KEY `idx_kpah_referral_uses_date` (`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin logins for the registration module. Admin password is stored with password_hash().
CREATE TABLE IF NOT EXISTS `kpah_web_admins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kpah_web_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the first admin hash on your server:
-- php -r "echo password_hash('REPLACE_WITH_A_STRONG_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
-- Then insert the returned hash (do NOT store the plain password):
-- INSERT INTO kpah_web_admins (username, password_hash, role)
-- VALUES ('admin', 'PASTE_HASH_HERE', 'admin');
