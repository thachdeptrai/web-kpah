-- ONE-TIME correction: notification tables accidentally imported into kpah_points.
--
-- Only move the shared notification tables. Forum tables kp_forum_topics,
-- kp_forum_replies and kp_forum_actions MUST remain in kpah_points.
--
-- BEFORE RUNNING:
-- 1) Back up both kpah and kpah_points.
-- 2) Stop website/forum writes (maintenance mode) until verification completes.
-- 3) Ensure source tables exist in kpah_points and destination tables DO NOT
--    already exist in kpah (check the SELECT below).
-- 4) Run on a MySQL/MariaDB connection allowed to ALTER/CREATE/DROP tables.
--    Do NOT run database/forum_notifications_kpah.sql before this script.
--
-- RENAME TABLE transfers the ENTIRE table across schemas on the same server,
-- retaining all rows, IDs, AUTO_INCREMENT, is_read states, timestamps & indexes.
-- No rows are erased and there is no DROP TABLE in this migration.
-- The combined RENAME TABLE is atomic with supported MySQL/MariaDB versions;
-- it FAILS instead of overwriting when a destination already exists.

SELECT TABLE_SCHEMA,TABLE_NAME,ENGINE,TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA IN ('kpah','kpah_points')
  AND TABLE_NAME IN ('kp_admin_notifications','kp_user_notifications',
                     'kp_user_security_events')
ORDER BY TABLE_NAME,TABLE_SCHEMA;

-- ONLY if both source notification tables exist and both destinations absent:
RENAME TABLE
  `kpah_points`.`kp_admin_notifications` TO `kpah`.`kp_admin_notifications`,
  `kpah_points`.`kp_user_notifications`  TO `kpah`.`kp_user_notifications`;

-- If user_center.sql also accidentally created kp_user_security_events in
-- kpah_points, and kpah.kp_user_security_events does NOT exist, separately run:
-- RENAME TABLE `kpah_points`.`kp_user_security_events`
--          TO `kpah`.`kp_user_security_events`;
-- If it already exists in kpah, leave both intact and reconcile the data
-- rather than overwriting it.

-- Verify that notification tables are now in kpah, not in kpah_points:
SELECT TABLE_SCHEMA,TABLE_NAME,ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA IN ('kpah','kpah_points')
  AND TABLE_NAME IN ('kp_admin_notifications','kp_user_notifications')
ORDER BY TABLE_NAME,TABLE_SCHEMA;

SELECT COUNT(*) AS admin_total, SUM(is_read=0) AS admin_unread
FROM `kpah`.`kp_admin_notifications`;

SELECT COUNT(*) AS user_total, SUM(is_read=0) AS user_unread
FROM `kpah`.`kp_user_notifications`;

-- Once verified, re-enable forum writes and test:
-- /dien-dan -> submit new ticket -> /quan-tri/thong-bao -> Admin reply
-- -> /thong-bao. Do not move/drop kp_forum_* tables.
