-- MIGRATE EXISTING FORUM DATA (non-destructive, one-time) from kpah to kpahpoint.
-- ONLY run if the 3 forum tables already existed in kpah and hold real reports.
-- Before: stop forum writes / enable maintenance, back up both schemas and
-- import database/forum_feedback.sql to create the destination tables.
-- If no forum data was ever created in kpah, SKIP this file.
-- Target tables must be EMPTY; do not execute twice.
-- Do not DELETE/DROP source tables here. Use a consistent snapshot while writes are stopped.
START TRANSACTION;
INSERT INTO `kpahpoint`.`kp_forum_topics`
(id,user_id,public_alias,kind,platform,title,body,status,visibility,submission_token,created_at,updated_at)
SELECT id,user_id,public_alias,kind,platform,title,body,status,visibility,submission_token,created_at,updated_at
FROM `kpah`.`kp_forum_topics`;

INSERT INTO `kpahpoint`.`kp_forum_replies`
(id,topic_id,admin_id,body,reply_token,created_at)
SELECT id,topic_id,admin_id,body,reply_token,created_at
FROM `kpah`.`kp_forum_replies`;

INSERT INTO `kpahpoint`.`kp_forum_actions`
(id,topic_id,admin_id,previous_status,new_status,previous_visibility,new_visibility,created_at)
SELECT id,topic_id,admin_id,previous_status,new_status,previous_visibility,new_visibility,created_at
FROM `kpah`.`kp_forum_actions`;
COMMIT;

-- Verify record counts BEFORE restoring forum writes:
SELECT 'topics' AS table_name,
 (SELECT COUNT(*) FROM `kpah`.`kp_forum_topics`) AS source_rows,
 (SELECT COUNT(*) FROM `kpahpoint`.`kp_forum_topics`) AS target_rows
UNION ALL SELECT 'replies',
 (SELECT COUNT(*) FROM `kpah`.`kp_forum_replies`),
 (SELECT COUNT(*) FROM `kpahpoint`.`kp_forum_replies`)
UNION ALL SELECT 'actions',
 (SELECT COUNT(*) FROM `kpah`.`kp_forum_actions`),
 (SELECT COUNT(*) FROM `kpahpoint`.`kp_forum_actions`);
-- After migration, verify per-row content as needed, restore writes and
-- keep source tables as rollback backup until the cutover is confirmed.
