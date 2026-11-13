# Đăng ký và xếp lịch làm nhân viên

Áp dụng cho hai vai trò nhân viên hiện có: **lễ tân** và **thu ngân**. Tất cả nằm trong mục **Quản lý thời gian**, không thêm mục trên thanh menu. Tab **Lịch khám bác sĩ** vẫn mở lịch khám hiện có; bác sĩ tiếp tục sử dụng **Lịch của tôi** và luồng ca khám riêng.

## Nhân viên đăng ký

1. Mở **Quản lý thời gian → Lịch làm của tôi → Đăng ký lịch làm**.
2. Chọn cơ sở được phân công, khoảng ngày, các thứ trong tuần và ca sáng/chiều/cả hai; có thể ghi chú nguyện vọng.
3. Gửi đăng ký. Mỗi ca có trạng thái **WAITING — Chờ xếp lịch**, chưa phải lịch làm chính thức. Đăng ký cả hai buổi tạo hai ca riêng.
4. Theo dõi mục đăng ký để xem ca dự kiến, phản hồi của admin và trạng thái. Chỉ những ca **đã chốt** xuất hiện trên bảng lịch chính thức của chính nhân viên đó.

Nhân viên có thể rút đăng ký WAITING hoặc ARRANGED; ca dự kiến liên quan cũng bị hủy. Không thể tự rút ca đã chốt. Đăng ký theo múi giờ Việt Nam, trong tối đa 90 ngày tới; ca đã bắt đầu hoặc đăng ký trùng giờ bị chặn. Gửi nhiều ca trong một lần được lưu toàn bộ hoặc không lưu ca nào nếu có lỗi.

## Admin sắp xếp và chốt

Mở **Quản lý thời gian → Xếp lịch nhân viên**, chọn tuần và cơ sở (hoặc tất cả cơ sở).

Khi chọn **Tất cả cơ sở**, mỗi cơ sở có một bảng tuần riêng, gồm các ca của đúng cơ sở đó. Bấm tab **Cơ sở 1 / Cơ sở 2 / Cơ sở 3** hoặc liên kết **Xếp lịch cơ sở này** để xem và xếp/chốt riêng một nơi. Dòng **Phạm vi xếp và chốt lịch** cho biết các nút tự động/chốt đang tác động đến một cơ sở hay tất cả; việc tách bảng không tự thay đổi phạm vi này.

- **Xếp tự động:** nhập nhu cầu lễ tân và thu ngân cho mỗi ca, từ 1–10 người/vai trò. Hệ thống chọn các đăng ký WAITING đúng cơ sở, ngày và khung giờ, ưu tiên người có ít tổng phút đã được xếp trong tuần; khi bằng nhau, dùng mã nhân viên rồi mã đăng ký để chọn ổn định. Ca dự kiến/đã chốt hiện có được tính vào nhu cầu và giữ nguyên. Đây là cách phân bổ theo số giờ, không cam kết tìm phương án tối ưu toàn cục.
- **Xếp bằng tay:** mở một đăng ký, chọn ngày, giờ, cơ sở được phân công cho nhân viên và ghi chú, rồi lưu ca dự kiến. Có thể chỉnh lại ca dự kiến sau khi chạy tự động.
- **Chốt lịch:** kiểm tra bảng ca dự kiến rồi bấm chốt. Chỉ các ca DRAFT trong tuần và cơ sở đang lọc được công bố; bỏ trống cơ sở nghĩa là tất cả cơ sở. Hệ thống kiểm tra lại toàn bộ trước khi lưu.

Tự động sử dụng khung sáng/chiều ở `config/clinic.php`. Chủ nhật được xét khi có đăng ký trong ngày đó. Vị trí thiếu người được thông báo để admin xử lý; các đăng ký chưa được chọn vẫn WAITING. Nút xếp tự động **không tự chốt lịch**.

Admin có thể từ chối đăng ký chưa chốt và nhập lý do. Với ca đã chốt nhưng chưa bắt đầu, phải **mở chốt**, nhập lý do, chỉnh ca rồi chốt lại; trong thời gian mở chốt ca không còn nằm trên lịch chính thức của nhân viên.

## Trạng thái và kiểm tra

| Đối tượng | Trạng thái | Ý nghĩa |
|---|---|---|
| Đăng ký | WAITING | Đã gửi, đang chờ admin xếp |
| Đăng ký | ARRANGED | Đã có ca dự kiến |
| Đăng ký | CONFIRMED | Ca đã được chốt |
| Đăng ký | REJECTED | Admin từ chối, có lý do |
| Đăng ký | CANCELLED | Nhân viên đã rút |
| Ca làm | DRAFT / CONFIRMED / CANCELLED | Dự kiến / chính thức / đã hủy |

Chặn trùng giờ của cùng nhân viên ở mọi cơ sở và giới hạn mặc định **480 phút/ngày**, tính cả ca dự kiến và chính thức. Kiểm tra lại tài khoản hoạt động, vai trò và phân công cơ sở khi xếp/chốt. Không cho gỡ cơ sở hoặc ngừng cơ sở còn ca nhân viên chính thức sắp tới; cần mở chốt và xử lý ca trước. Việc khóa tài khoản vẫn được phép, và các ca cần điều chỉnh được đánh dấu cho admin.

Các thao tác ghi dữ liệu dùng transaction và khóa chung để tránh xếp trùng đồng thời. Form chỉnh/chốt có phiên bản: nếu tab khác đã sửa, người dùng phải tải lại để xem kế hoạch mới. Thay đổi được ghi audit; giữ bản đăng ký ban đầu và lý do xử lý. Nhân viên chỉ xem lịch/đăng ký của mình; admin có quyền xếp/chốt. Lịch nhân viên không chứa bệnh nhân và không cấp thêm quyền khám hoặc thu tiền.

## Cài đặt và mã nguồn

Migration `2026_11_12_000004_create_staff_roster_tables.php` bổ sung hai bảng `staff_schedule_requests` và `staff_work_shifts`. Chạy `php artisan migrate --force` khi nâng cấp máy khác; không dùng `migrate:fresh` cho dữ liệu đang sử dụng. Không tự tạo đăng ký hoặc lịch chính thức mẫu trong database đang chạy.

- Cấu hình: `config/staff_roster.php`.
- Nghiệp vụ: `app/Services/StaffRosterService.php`.
- Controller: `EmployeeScheduleController`, `AdminRosterController`.
- Giao diện: `resources/views/staff-roster/`, `public/staff-roster.css`, `public/staff-roster.js`.
- Route và hợp đồng HTTP: [API_Laragon.md](API_Laragon.md).
- Kiểm thử: `tests/Feature/StaffRosterTest.php` gồm đăng ký, phân quyền, xếp tự động/thủ công, chốt nguyên đợt, xung đột phiên bản, trùng ca, rút/từ chối và mở chốt.
