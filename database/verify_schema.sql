-- Chỉ ĐỌC, không sửa dữ liệu. Chạy trên đúng database của game để đối chiếu
-- với những gì module đăng ký đang giả định (xem README, mục "Schema đã xác minh").

-- 1) Cấu trúc & chỉ mục bảng tài khoản: phải có UNIQUE (username) và UNIQUE (userid)
SHOW CREATE TABLE `team_user`;
SHOW INDEX FROM `team_user`;

-- 2) userid KHÔNG tự tăng -> web cấp MAX(userid)+1. Xem giá trị hiện tại:
SELECT MAX(`userid`) AS max_userid, COUNT(*) AS so_tai_khoan FROM `team_user`;

-- 3) Định dạng mật khẩu: tất cả hàng "chuẩn" phải dài 41 ký tự và bắt đầu bằng '*'
SELECT LENGTH(`password`) AS do_dai, LEFT(`password`, 1) AS ky_tu_dau, COUNT(*) AS so_hang
FROM `team_user` GROUP BY 1, 2;

-- 4) Đối chiếu băm: mật khẩu '123456' phải ra đúng giá trị mà PHP tạo (*6BB4837E...)
--    (MariaDB 10.4 còn hàm PASSWORD(); MySQL 8 thì không – khi đó bỏ qua mục này)
SELECT PASSWORD('123456') AS ma_bam_mysql;

-- 5) Quyền tối thiểu cho user DB của web đăng ký (chạy bằng tài khoản quản trị, đổi tên user/host):
-- CREATE USER 'kpah_web'@'localhost' IDENTIFIED BY 'DOI_MAT_KHAU_MANH';
-- GRANT SELECT, INSERT, UPDATE ON `kpah`.`team_user` TO 'kpah_web'@'localhost'; -- UPDATE dùng cho đổi email/SĐT và đặt lại mật khẩu
-- GRANT SELECT ON `kpah`.`bad_word` TO 'kpah_web'@'localhost';   -- chỉ cần nếu bật use_bad_word_table
-- FLUSH PRIVILEGES;
