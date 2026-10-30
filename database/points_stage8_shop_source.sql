-- Stage 8: preserve whether Point reward's display item comes from data_item or data_shop.
-- Run after points_stage1_reward_display.sql. Additive, keeps all old reward data.
-- MariaDB 10.4+ supports ADD COLUMN IF NOT EXISTS.
ALTER TABLE `kpah_points`.`kp_points_reward_display`
    ADD COLUMN IF NOT EXISTS item_source ENUM('data_item','data_shop') NOT NULL DEFAULT 'data_item' AFTER item_id;
