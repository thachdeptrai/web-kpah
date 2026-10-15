# Giám sát Điểm nông dân và Lượng

Trang admin giám sát biến động lưu trong hai bảng độc lập kp_farmer_monitor_state và kp_farmer_monitor_changes. Không chỉnh bảng game.

## Cài đặt trên VPS Windows / XAMPP

Mở CMD administrator tại C:\xampp\htdocs (hoặc thư mục web):

1. C:\xampp\php\php.exe tools\farmer-monitor.php install
2. C:\xampp\php\php.exe tools\farmer-monitor.php collect
3. Chạy tools\install-farmer-task.bat để lên lịch mỗi 5 phút. Sửa đường dẫn PHP_EXE trong BAT nếu XAMPP đặt nơi khác.
4. C:\xampp\php\php.exe tools\farmer-monitor.php status

Lượt đầu chỉ ghi số dư gốc; các lượt tiếp theo mới lưu biến động. Mặc định giữ các lần biến động 90 ngày, cấu hình KPAH_FARM_MONITOR_RETENTION_DAYS (7–3650) để đổi.

**Lưu ý quan trọng:** số dư Lượng tăng giữa hai mốc không phải chứng cứ farm Lượng. Có thể do nạp, trao đổi hoặc sự kiện; chi tiêu và nhận trong cùng chu kỳ cũng không tách được. Để biết *nguồn* cụ thể cần instrument event logging trong Java server; snapshot web chỉ biết số dư trước/sau. Khi server chưa ghi nhân vật đang online xuống DB, web sẽ chưa phản ánh mức tăng hiện tại.
