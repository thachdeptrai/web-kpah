-- Mở rộng dung lượng chuỗi phần thưởng cho nhiều item và option.
-- Tương thích ngược: mọi token DB/PT/GEM hiện có vẫn giữ nguyên giá trị.
-- Backup bảng giftcode trước khi chạy trên production.

ALTER TABLE `giftcode`
    MODIFY COLUMN `item` TEXT NULL;
