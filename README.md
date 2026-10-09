# Hệ thống quản lý phòng khám tư nhân và đặt lịch khám bệnh

**Bài tập lớn Phân tích và Thiết kế Phần mềm — Lớp N05, Nhóm 3**

Ứng dụng Laravel 10 mô phỏng quy trình từ bệnh nhân đặt lịch đến lễ tân tiếp nhận, bác sĩ lập phiếu khám và thu ngân thanh toán. Đây là dự án mới, tách biệt với `PTTKPM25-26_ClassN05_Nhom2`.

## Chức năng đã triển khai

- Đăng ký/đăng nhập bệnh nhân, phân quyền 5 vai trò.
- Tra cứu bác sĩ, chuyên khoa và ca làm việc.
- Đặt lịch theo ca và bước thời gian; kiểm tra trùng lịch trong transaction.
- Bệnh nhân xem/hủy lịch và xem kết quả khám đã công bố.
- Lễ tân xác nhận, check-in hoặc ghi nhận vắng mặt.
- Bác sĩ bắt đầu ca khám, lập hồ sơ, chẩn đoán, đơn thuốc và chỉ định dịch vụ.
- Thu ngân lập hóa đơn, ghi nhận tiền mặt/chuyển khoản/thẻ và hỗ trợ trả từng phần.
- Quản trị thêm chuyên khoa, bác sĩ, dịch vụ và lịch làm việc.
- Audit log cho các thao tác nghiệp vụ chính.

## Database mới

Database được thiết kế lại hoàn toàn cho phòng khám, gồm:

`roles`, `users`, `patients`, `specialties`, `doctors`, `doctor_schedules`, `appointments`, `medical_records`, `services`, `visit_services`, `invoices`, `invoice_items`, `payments`, `audit_logs` và các bảng hạ tầng Laravel.

Mặc định dự án dùng `database/database.sqlite` để chạy ngay. Muốn dùng MySQL, đổi các biến `DB_*` trong `.env`, ví dụ `DB_DATABASE=phong_kham_nhom3`, sau đó chạy migration.

## Chạy dự án trên Windows

Yêu cầu: PHP 8.1 trở lên với `mbstring`, `openssl`, `pdo_sqlite`, `sqlite3`, `intl`, `dom`, `xml`. Dự án đã được cấu hình tương thích PHP 8.1.10 của Laragon 6.

```powershell
cd C:\Users\ADMIN\Downloads\DanhGiaKiemDinh\PTTKPM25-26_ClassN05_Nhom3
.\setup.ps1
.\dev.ps1
```

Mở <http://127.0.0.1:8000>.

Nếu PHP đã được cấu hình đầy đủ, có thể dùng lệnh Laravel chuẩn:

```powershell
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

## Tài khoản demo

Mật khẩu chung: `Password@123`

| Vai trò | Email |
|---|---|
| Quản trị | `admin@clinic.test` |
| Lễ tân | `letan@clinic.test` |
| Bác sĩ | `bacsi.an@clinic.test` |
| Thu ngân | `thungan@clinic.test` |
| Bệnh nhân | `benhnhan@clinic.test` |

## Kiểm thử

```powershell
.\test.ps1
```

Bộ test kiểm tra đăng ký bệnh nhân, chống đặt trùng và quy tắc bước thời gian của ca khám.

## Phần mã được tái sử dụng

Từ dự án Nhóm 2 chỉ giữ phần khung trung tính, cách tổ chức authentication/session và một số tài nguyên Laravel. Không sao chép model, controller, route, migration hoặc dữ liệu của hệ thống bán điện thoại. Toàn bộ domain và database phòng khám được viết mới; project hiện chạy trên Laravel 10 để tương thích Laragon 6/PHP 8.1.
