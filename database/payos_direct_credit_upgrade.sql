-- Run this once ONLY if kp_bank_orders already exists.
-- Existing queued payments keep char_id=0 and will never be replayed as direct grants.
ALTER TABLE kp_bank_orders ADD COLUMN char_id INT NOT NULL DEFAULT 0 AFTER user_id;
