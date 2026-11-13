# Lịch bác sĩ và độ phủ chuyên khoa

Đợt xếp lịch: **2026-11-14 đến 2026-11-27**; có Chủ nhật.

Mỗi khoa tại mỗi cơ sở có ít nhất 2 bác sĩ. Mỗi buổi sáng 08:00–11:30 và chiều 13:30–17:00 có ít nhất 1 bác sĩ của khoa đó trực tại cơ sở.

Chỉ luân phiên ca tương lai còn trống theo tổng giờ trong tuần. Ca đã có lịch hẹn (kể cả lịch sử), đề nghị đổi ca, ca tùy chỉnh/đã đóng và lịch quá khứ giữ nguyên. Ca dư còn trống được đóng, không xóa. Không chuyển bác sĩ hoặc giờ của lịch hẹn bệnh nhân.

| Cơ sở | Chuyên khoa | Số bác sĩ |
|---|---|---|
| Phòng khám An Tâm - Chi nhánh 1 | Tim mạch | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Nội khoa | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Nhi khoa | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Ngoại khoa | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Sản phụ khoa | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Tai mũi họng | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Răng hàm mặt | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Da liễu | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Mắt | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Sức khỏe tâm thần | 2 |
| Phòng khám An Tâm - Chi nhánh 1 | Tâm lý | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Tim mạch | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Nội khoa | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Nhi khoa | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Ngoại khoa | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Sản phụ khoa | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Tai mũi họng | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Răng hàm mặt | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Da liễu | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Mắt | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Sức khỏe tâm thần | 2 |
| Phòng khám An Tâm - Chi nhánh 2 | Tâm lý | 2 |
| Phòng Khám An Tâm 3 | Tim mạch | 2 |
| Phòng Khám An Tâm 3 | Nội khoa | 2 |
| Phòng Khám An Tâm 3 | Nhi khoa | 2 |
| Phòng Khám An Tâm 3 | Ngoại khoa | 2 |
| Phòng Khám An Tâm 3 | Sản phụ khoa | 2 |
| Phòng Khám An Tâm 3 | Tai mũi họng | 2 |
| Phòng Khám An Tâm 3 | Răng hàm mặt | 2 |
| Phòng Khám An Tâm 3 | Da liễu | 2 |
| Phòng Khám An Tâm 3 | Mắt | 2 |
| Phòng Khám An Tâm 3 | Sức khỏe tâm thần | 2 |
| Phòng Khám An Tâm 3 | Tâm lý | 2 |

## Chạy lại hoặc mở rộng thời gian

```powershell
php artisan clinic:coverage --days=14 --include-sunday
# Xem trước, sau đó lưu:
php artisan clinic:backup
php artisan clinic:coverage --days=14 --include-sunday --apply --report
```

Có thể chỉ định --from=YYYY-MM-DD và --days=1..90, trong phạm vi 90 ngày tới. Bỏ --include-sunday để nghỉ Chủ nhật. Chạy lại không thêm bác sĩ khi đã đủ; không tự đổi mật khẩu, mức lương cũ hoặc bảng lương. Nếu không thể phủ đủ do lịch nghỉ/ca cố định/xung đột, cả đợt bị rollback và báo ô còn thiếu. Không tự chạy hằng ngày; cần chạy lại để mở rộng lịch sau ngày cuối của đợt này.

Bác sĩ mới có đơn giá 500.000 VNĐ/giờ; mật khẩu tài khoản mới là Password@123. [Danh sách nhân sự](Clinic_Demo_Accounts.md).
