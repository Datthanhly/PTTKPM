# Tiền lương theo giờ hoặc ca làm

Menu của admin, bác sĩ, lễ tân và thu ngân nằm ở **bên trái**. Trên điện thoại, bấm **Menu** để mở/đóng; mục đang mở vẫn được gạch chân. Khách và bệnh nhân giữ giao diện điều hướng hiện có.

## Admin thiết lập và tính lương

1. Mở **Bảng lương**, chọn tháng, tìm nhân viên theo tên/email, vai trò hoặc cơ sở.
2. Mở nhân viên trong phần **Đơn giá từng nhân viên**. Chọn **Theo giờ làm** hoặc **Theo ca làm**, nhập đơn giá VNĐ và lưu. Mỗi người có đơn giá riêng; hệ thống không tự đặt mức lương mẫu.
3. Bấm **Tính lương** cho tháng đã chọn. Mỗi nhân viên chỉ có một bảng lương/tháng, ban đầu **Chưa thanh toán**. Không lập bảng cho tháng tương lai.
4. Kiểm tra các ca nguồn và đối chiếu thời gian làm thực tế. Nếu cần, chỉnh số giờ/phút hoặc số ca, đơn giá tháng này, phụ cấp, thưởng, khấu trừ; bắt buộc ghi lý do.
5. Sau khi đã chi trả thực tế, nhập nội dung xác nhận và mã chứng từ nếu có, bấm **Xác nhận đã thanh toán**. Đây là ghi nhận trong hệ thống, không thực hiện chuyển tiền ngân hàng.

**Công thức:** lương cơ bản = đơn giá × số giờ hoặc số ca. Thực nhận = lương cơ bản + phụ cấp + thưởng − khấu trừ. Lương theo giờ tính chính xác bằng số phút / 60, làm tròn đến đồng gần nhất (nửa đồng làm tròn lên); số ca là số nguyên. Tiền không dùng phép tính số thực. Không cho thực nhận âm.

## Nguồn số công

Lấy các ca thuộc tháng, đã kết thúc tại thời điểm tính theo giờ Việt Nam:

- Bác sĩ: ca khám đang mở (`doctor_schedules.is_open`), bỏ toàn bộ ca nếu trùng bất kỳ khoảng nghỉ đã khai báo.
- Lễ tân/thu ngân: ca nhân viên đã chốt (`staff_work_shifts.status=CONFIRMED`). Không lấy đăng ký WAITING, ca DRAFT hoặc ca đã hủy.
- Ca tương lai và ca đang diễn ra chưa được tính. Chặn nguồn có ca trùng giờ, kể cả giữa cơ sở, để admin kiểm tra lại trước.

Đây là **dữ liệu lịch làm**, chưa phải chấm công. Admin chịu trách nhiệm đối chiếu và điều chỉnh số công thực tế. Bảng lương lưu bản chụp ca nguồn (ngày, giờ, cơ sở, thời lượng), thời điểm tính và đơn giá áp dụng. Bảng tháng đang diễn ra chỉ tính đến thời điểm lấy nguồn.

**Lấy lại số công từ lịch** cập nhật bản chụp và số giờ/ca từ dữ liệu mới nhất, thay số công đã sửa tay; giữ đơn giá, phụ cấp, thưởng và khấu trừ hiện có. Chỉ được làm khi chưa thanh toán và phải nhập lý do. Đổi đơn giá trong hồ sơ không tự sửa bất kỳ bảng lương cũ nào; muốn sửa tháng cũ, mở đúng bảng để điều chỉnh riêng.

## Quyền truy cập và thanh toán

Bác sĩ/lễ tân/thu ngân vào **Lương của tôi** để xem đơn giá hiện tại và lịch sử bảng lương cá nhân. Không thể xem lương người khác bằng ID hoặc tham số URL; không có quyền tạo/sửa lương, tính lại hay đổi trạng thái. Bệnh nhân không truy cập phần lương. Chỉ admin quản lý toàn bộ.

Trạng thái gồm **UNPAID — Chưa thanh toán** và **PAID — Đã thanh toán**. Khi đã thanh toán, số tiền và số công bị khóa. Nếu ghi nhận sai, admin chuyển về chưa thanh toán kèm lý do, điều chỉnh rồi xác nhận lại. Lịch sử audit giữ dữ liệu trước/sau, người thao tác, lý do và chứng từ cũ. Không ghi nhận trùng khi gửi lại trạng thái đã có.

Thao tác dùng transaction và khóa theo nhân viên, có phiên bản tăng sau mỗi thay đổi để chặn tab cũ ghi đè. Bảng lương có ràng buộc duy nhất theo nhân viên/tháng. Trang lương trả `Cache-Control: no-store, private`.

## Nâng cấp và mã nguồn

Migration `2026_11_13_000005_create_payroll_tables.php` chỉ thêm `salary_profiles` và `payroll_entries`; không đổi tài khoản, mật khẩu, ca làm hoặc dữ liệu khám. Máy khác chạy `php artisan migrate --force` sau khi sao lưu, không dùng `migrate:fresh` với dữ liệu đang chạy.

- Nghiệp vụ: `app/Services/PayrollService.php`.
- HTTP: `app/Http/Controllers/PayrollController.php`, `routes/web.php`.
- Giao diện lương: `resources/views/payroll/`, `public/payroll.css`.
- Menu trái: `resources/views/layouts/app.blade.php`, `public/staff-shell.css`, `public/staff-shell.js`.
- Kiểm thử: `tests/Feature/PayrollTest.php`.

Các khoản thuế/bảo hiểm không tự tính. Admin nhập khoản khấu trừ phù hợp sau khi đối chiếu nghiệp vụ thực tế.
