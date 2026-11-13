# Đối chiếu triển khai với đặc tả - Laragon 6.0

Ngày kiểm tra: **12/11/2026**. Tài liệu nguồn: `Clinic_Management_System_Full_Specification.md`.

## Quyết định áp dụng

| Mã | Quyết định trong bản chạy hiện tại |
|---|---|
| ADR-11 | Giữ Laravel 10, Blade, PHP 8.1 để tương thích Laragon 6; MySQL 8 hoặc SQLite, không chuyển sang Express/React/PostgreSQL. |
| ADR-12 | Giữ khóa số và bảng cũ; thêm migration nâng cấp, chuyển lịch đang/đã khám thành Visit và liên kết lại hồ sơ/hóa đơn. Không xóa dữ liệu khi setup. |
| ADR-13 | Năm vai trò trong roles/users.role_id; quyền mặc định tại config/clinic.php, phân công trong branch_user, quyền bổ sung theo branch trong user_permissions. |
| ADR-14 | Khóa bác sĩ cho mọi thay đổi lịch/ca/nghỉ; khóa bệnh nhân khi đặt/đổi lịch. Sức chứa tính từ lịch hiệu lực, không duy trì bộ đếm slot có thể lệch. |
| ADR-15 | Ca lặp theo tuần được tạo thành ca ngày cụ thể trong tối đa 90 ngày. Thời gian MySQL/SQLite lưu và hiển thị theo Asia/Ho_Chi_Minh; chưa triển khai nhiều múi giờ. |
| ADR-16 | Tính tiền bằng số nguyên đơn vị 1/100 đồng, lưu DECIMAL(12,2). Phí khám cấu hình; không thuế/bảo hiểm. Một invoice chính mỗi Visit; ISSUED/VOID tách PAID/PARTIAL/UNPAID tính toán. |
| ADR-17 | Đơn có tùy chọn bán thuốc tại phòng khám. Chỉ đơn SIGNED có đánh dấu này được tính phí; cấp phát sau thanh toán không tính phí lần nữa. |
| ADR-18 | Một kho logic mỗi branch; lượng thuốc nguyên theo đơn vị danh mục. FEFO tự phân bổ lô; dòng cấp phát gộp cả dòng đơn và allocation lô. |
| ADR-19 | notification_jobs vừa là transactional outbox vừa là hàng đợi email. Worker clinic:work một lượt; scheduler mỗi phút. |
| ADR-20 | LIS/Device inbound mock có token băm, giới hạn branch và external_message_id; chưa phải tích hợp LIS/FHIR thật. |
| ADR-21 | Thu ngân ghi giảm giá/hoàn tiền có lý do, hủy invoice khi thu ròng=0. Giữ audit, không xóa payment để hoàn. |
| ADR-22 | Patient chỉ xem hồ sơ công bố/kết quả VERIFIED/chứng từ của mình. Doctor xem Visit được giao và tối đa 10 hồ sơ đã ký trước tại cùng branch; có audit lượt đọc. |

## Ma trận chức năng

| FR | Chức năng đã nối giao diện/backend | Kiểm chứng |
|---|---|---|
| 01 | Đăng ký/login, Argon2id, khóa/vô hiệu hóa, đổi/reset mật khẩu, throttle, scope và audit | Auth, role/locked tests |
| 02 | Hồ sơ không cần tài khoản, mã duy nhất, tìm/phân trang, sửa hành chính, vô hiệu hóa; tự sửa dị ứng/tiền sử; cảnh báo trùng | Patients UI, branch test |
| 03 | Cập nhật/vô hiệu hóa bác sĩ/chuyên khoa; nhiều chuyên khoa/chi nhánh; tra cứu công khai | Role pages, E2E |
| 04 | Ca ngày/tuần, bước/capacity, API giờ trống, đặt hộ/tự đặt có transaction/idempotency | Booking, capacity, concurrency |
| 05 | Check-in một lần, Visit riêng, walk-in, queue và các mốc thời gian | Check-in, walk-in, E2E |
| 06 | Nháp/ký, version, điều chỉnh có snapshot/lý do; đơn có từng dòng và ký | Revision, signing, E2E |
| 07 | Snapshot phí/dịch vụ/thuốc bán, giảm giá, thu từng phần, chuyển khoản xác nhận, hoàn/hủy | Money, refund, idempotency, E2E |
| 08 | Lịch hôm nay, bệnh nhân mới, lượt hoàn tất, thu trừ hoàn, nợ, biểu đồ ngày/chuyên khoa | Revenue assertion, E2E |
| 09 | Chỉ định nhiều dịch vụ, nhập tay/tệp riêng, duyệt/từ chối, doctor xem trạng thái | Verification, E2E |
| 10 | Đổi ngày/giờ cùng bác sĩ/branch, lịch sử; nghỉ toàn bác sĩ, chặn hoặc hủy lịch ảnh hưởng có thông báo | Schedule/time-off tests |
| 11 | PDF SIGNED/ISSUED, DejaVu Sans nhúng, đúng branch, watermark demo | HTTP PDF tests, pdftotext và xem ảnh render |
| 12 | Lọc/phân trang, XLSX theo branch/ngày, tiền là số, chuỗi là text, audit xuất | Export scope, OOXML tests |
| 13 | Outbox đặt/xác nhận/đổi/hủy, nhắc 24h/2h, tùy chọn nhận email, retry 5 lần có backoff | Email success/failure |
| 14 | Thuốc/lô, tồn thấp/hạn dùng, cấp từng phần FEFO, biến động/điều chỉnh | Shortage/expiry, concurrency |
| 15 | Branch, phân công, scope đọc/ghi/báo cáo, chống trùng ca liên branch | Cross-branch, MySQL tests |
| 16 | Đề xuất/chấp nhận/từ chối; consent tự đặt 6 tháng/thu hồi; worker dùng chung booking service | Consent, duplicate tests |
| 17 | API mock LIS/Device; đối chiếu order/patient/visit/branch; chống trùng; kiểm unit, chờ xác minh | Integration tests |

## Phần chưa đủ như thiết kế mở rộng

- Chưa có React SPA, REST API `/api/v1` tổng quát, UUID/PostgreSQL/Docker. Web dùng form + CSRF/session; hợp đồng thực tế trong `API_Laragon.md` và `openapi.yaml`.
- Đổi lịch giữ nguyên bác sĩ/chi nhánh. Chưa điều phối chuyển bác sĩ/chi nhánh với toàn bộ chứng từ.
- Chưa có màn hình hủy từng dòng đơn/chỉ định, sửa đổi đơn đã ký, trả thuốc theo phiếu cấp, nhiều kho mỗi branch hoặc lượng lẻ. Điều chỉnh kho là chứng từ riêng có lý do.
- Doctor xem kết quả khi tải lại trang, chưa có push/WebSocket; chưa quét virus, nhiều observation phức hợp hay ánh xạ FHIR/DICOM.
- LIS mock chỉ nhận kết quả; chưa có adapter gửi chỉ định ra LIS ngoài kèm retry/timeout. Script mock minh họa hợp đồng inbound.
- Chưa nhập sinh hiệu có cấu trúc riêng bằng tay; có thể ghi khám lâm sàng và nhận/xác minh số đo mock.
- SMTP thông thường không bảo đảm exactly-once nếu tiến trình chết sau khi mail server nhận nhưng DB chưa commit. Khóa job/SENT ngăn gửi trùng trong luồng bình thường.
- Bệnh nhân mới: admin đếm toàn hệ thống, nhân viên đếm hồ sơ liên hệ chi nhánh; không tạo hồ sơ trùng cho từng branch. Chưa có biểu đồ lượt theo tuần/tháng và filter doctor riêng trên dashboard.
- Chưa đo tải 50 người dùng/NFR-01; chưa browser E2E tự động. Hiện kiểm HTTP Laravel, PDF và process đồng thời trên MySQL.
- Backup có file/checksum/count manifest; chưa xoay vòng/mã hóa tự động. Chỉ dùng dữ liệu giả lập.
- Backup MySQL khóa đọc các bảng của database phòng khám trong thời gian dump để manifest nhất quán; tài khoản backup cần quyền LOCK TABLES. SQLite kiểm manifest trực tiếp từ bản sao snapshot.

## Kiểm thử đã thực hiện

- PHP **8.1.10**, Laravel **10.50.3**; SQLite và MySQL **8.0.30** đi kèm Laragon.
- **25 tests / 192 assertions**: authentication (kể cả băm cũ và token reset một lần), render trang theo vai trò, P0 xuyên 5 vai trò, scope, revision, kết quả, kho, thu/hoàn tiền, PDF/XLSX, email, tái khám và tích hợp.
- **4 ca đồng thời MySQL**, hai process độc lập/barrier: slot capacity=1; hai đơn tranh lô; hai khoản thu tranh số dư; hai khoản thu trùng key.
- Migration SQLite từ bản cũ chạy sau backup và giữ dữ liệu; schema trống chạy cả SQLite/MySQL qua RefreshDatabase.
- Restore SQLite/MySQL kiểm SHA-256 file và số dòng tất cả bảng vào bản sao mới, không thay DB đang chạy.
- PDF đơn/hóa đơn đã render ảnh và kiểm dấu tiếng Việt/bảng; text kiểm bằng pdftotext.
- Browser automation host không khả dụng, nên chưa xác minh trực quan giao diện trong trình duyệt.

## Luồng demo

1. Patient tra doctor, chọn branch/ngày/giờ trống và đặt.
2. Reception xác nhận, đến đúng ngày thì check-in; hoặc tạo walk-in tại Bệnh nhân.
3. Doctor mở Lượt khám, bắt đầu, lưu nháp/ký hồ sơ và chỉ định.
4. Doctor demo có quyền nhập/duyệt kết quả qua Kết quả; có thể thay bằng API LIS mock.
5. Kê từng dòng thuốc, tick bán tại phòng khám nếu thu tiền, ký đơn, hoàn tất.
6. Cashier phát hành invoice, thu từng phần/toàn bộ; chuyển khoản phải xác nhận đã nhận.
7. Doctor demo có quyền cấp phát mở Kho / cấp thuốc, nhập lượng thực cấp. Tồn chỉ giảm ở đây.
8. Patient xem lịch sử/PDF; admin xem thu thực tế/XLSX. Worker chỉ tự đặt tái khám khi có consent.
