# Hợp đồng HTTP bản Laragon

Web dùng session cookie và CSRF. Form thành công trả 302; validation trả form kèm lỗi hoặc 422 với `Accept: application/json`; thiếu quyền 403; conflict trạng thái/idempotency 409. Quyền lấy từ session/database, không từ body.

| Nhóm | Method / URL | Payload / phạm vi |
|---|---|---|
| Công khai | GET /bac-si, /bac-si/{id} | Lọc branch_id/specialty_id; chỉ doctor hoạt động |
| Auth | POST /dang-ky, /dang-nhap, /dang-xuat | CSRF; login/register có throttle |
| Mật khẩu | POST /quen-mat-khau, /dat-lai-mat-khau; PUT /doi-mat-khau | Token một lần 60 phút hoặc current_password/password/password_confirmation |
| Giờ trống | GET /lich-kham/kha-dung | branch_id, doctor_id, date=YYYY-MM-DD; patient/reception |
| Đặt | POST /lich-kham | branch_id/doctor_id/appointment_date/start_time/reason/idempotency_key; lễ tân thêm patient_id |
| Đổi/hủy | PATCH /lich-kham/{id}/doi, /huy | Own hoặc APPOINTMENT_RESCHEDULE theo branch; đổi ngày/giờ/reason; hủy cancel_reason |
| Lịch tuần | GET /nhan-vien/lich-hen | Nhân viên; date chọn tuần chứa ngày, branch_id/doctor_id lọc theo phạm vi; bác sĩ chỉ xem ca của mình. HTML ban đầu không chứa danh sách bệnh nhân. |
| Bác sĩ đăng ký ca | POST /nhan-vien/dang-ky-lich-lam | Form nằm trong Lịch của tôi. branch_id, work_date, session=morning/afternoon/both/custom, slot_minutes, capacity, repeat_until tùy chọn; custom thêm start_time/end_time. doctor_id luôn lấy từ tài khoản đăng nhập. Kiểm tra phân công, giờ tương lai, trùng ca/lịch nghỉ; đăng ký lặp trong 90 ngày lưu nguyên đợt. Thành công về lịch tuần có ca mới; lỗi web dùng error bag doctorSchedule và mở lại form tại chỗ. GET đường dẫn này chuyển về Lịch của tôi#dang-ky-ca. |
| Lịch hẹn trong ca | GET /nhan-vien/ca-lam/{schedule} | Kiểm tra quyền theo bác sĩ/chi nhánh trước khi tải bệnh nhân; q/status lọc trong ca. X-Requested-With: XMLHttpRequest trả HTML phần nội dung cho cửa sổ chi tiết; truy cập thường trả trang đầy đủ. |
| Bệnh nhân | GET/POST /benh-nhan; PUT /benh-nhan/{id} | Reception theo branch; không trả bệnh án |
| Walk-in | POST /tiep-nhan/vang-lai | patient_id/doctor_id/branch_id/reason; VISIT_CHECK_IN |
| Xác nhận / tiếp nhận | PATCH /nhan-vien/lich-hen/{id}/trang-thai | Lễ tân theo chi nhánh: status=CONFIRMED/CHECKED_IN/NO_SHOW; PENDING → CONFIRMED; CONFIRMED → CHECKED_IN đúng ngày hoặc NO_SHOW sau giờ kết thúc. Form web quay lại kèm thông báo/lỗi validation; JSON thành công 200 kèm message, xung đột trạng thái 409, sai thời điểm 422, sai quyền 403. Bác sĩ dùng IN_PROGRESS cho lượt khám được giao; JSON thành công kèm redirect đến lượt khám. |
| Visit | GET /luot-kham, /luot-kham/{id}; PATCH /luot-kham/{id}/trang-thai | Chi tiết/thao tác chỉ doctor được giao; danh sách hành chính theo role/branch |
| Hồ sơ | PUT /luot-kham/{id}/ho-so | version, action=draft/sign, symptoms/findings/diagnosis/conclusion/notes; sửa đã ký thêm amendment_reason |
| Đơn | POST /luot-kham/{id}/don-thuoc, /don-thuoc/{id}/ky | items[]: medicine_id/dosage/route/frequency/duration_days/quantity/instructions; bill_at_clinic=1 nếu bán |
| Dịch vụ | POST /luot-kham/{id}/dich-vu | service_ids[]; assigned + SERVICE_ORDER_CREATE |
| Kết quả | GET /chan-doan; POST /chi-dinh/{id}/ket-qua; POST /ket-qua/{id}/duyet | LAB_RESULT_ENTER/VERIFY; value_text/unit/attachment; duyệt status=VERIFIED/REJECTED |
| Tệp | GET /ket-qua/{id}/tep | Quyền theo Visit/result; own patient chỉ VERIFIED |
| Sinh hiệu | POST /so-do/{id}/duyet | Assigned doctor; status=VERIFIED/REJECTED |
| Invoice | GET /thu-ngan/luot/{id}; POST /thu-ngan/luot/{id}/hoa-don | INVOICE_ISSUE; discount/discount_reason tùy chọn |
| Thu | POST /thu-ngan/hoa-don/{id}/thanh-toan | PAYMENT_COLLECT; amount/method=CASH,TRANSFER/reference/idempotency_key; confirmed=1 khi transfer; hỗ trợ header Idempotency-Key |
| Hoàn | POST /thu-ngan/giao-dich/{id}/hoan-tien | PAYMENT_COLLECT; amount/reason/idempotency_key |
| Hủy invoice | POST /thu-ngan/hoa-don/{id}/huy | INVOICE_ISSUE; reason; thu ròng phải bằng 0 |
| Kho | GET /kho; POST /kho/nhap; POST /kho/lo/{id}/dieu-chinh | INVENTORY_MANAGE; nhập branch_id/medicine_id/lot_number/expiry_date/quantity/reference; chỉnh delta/reason |
| Cấp | POST /kho/don/{id}/cap-phat | MEDICINE_DISPENSE; quantities[prescription_item_id], idempotency_key |
| PDF | GET /don-thuoc/{id}/pdf, /hoa-don/{id}/pdf | Own hoặc quyền phù hợp, chỉ SIGNED/ISSUED |
| Reports | GET /bao-cao; /bao-cao/{type}/excel | REPORT_VIEW/EXPORT; branch_id/from/to; cashier chỉ export revenue |
| Tái khám | POST /luot-kham/{id}/tai-kham; /tai-kham/{id} | Doctor: suggested_from/suggested_to/reason; own: action=accept/decline, date/start_time nếu accept |
| Quản trị | /quan-tri/cau-hinh, /quan-tri/danh-muc và form con | ADMIN, không mặc định quyền y tế |

## Đề nghị nhận ca của bác sĩ

Các endpoint dùng phiên đăng nhập web và CSRF; chỉ dành cho vai trò doctor:

| Chức năng | Endpoint | Dữ liệu/quyền |
|---|---|---|
| Thông báo | GET /bang-dieu-khien | Đề nghị gửi/nhận của chính bác sĩ; không tiết lộ bệnh nhân trong ca chưa nhận |
| Gửi đề nghị | POST /nhan-vien/ca-lam/{schedule}/doi-bac-si | Chủ ca; receiver_doctor_id, note bắt buộc tối đa 200 ký tự |
| Xử lý | PATCH /nhan-vien/doi-ca/{transfer} | decision=accept/reject chỉ người nhận; decision=cancel chỉ người gửi |

Một ca chỉ có một đề nghị đang chờ. Người nhận phải cùng chuyên khoa, hoạt động và được phân công ở chi nhánh của ca, không trùng ca/lịch nghỉ/lịch hẹn và không có lượt khám đang xử lý trong ngày. Kiểm tra lại khi đồng ý. Ca đã bắt đầu, đóng hoặc có bệnh nhân được tiếp nhận/hồ sơ điều trị không được chuyển. Khi đồng ý, chuyển chủ ca và lịch hẹn PENDING/CONFIRMED nguyên tử, ghi lịch sử/audit và tạo email nếu bệnh nhân bật nhận email. Không đổi thời gian hoặc chi nhánh.

POST nhận Accept: application/json trả 200 kèm message khi thành công; lỗi nghiệp vụ trả 422 (errors.transfer). Form thông thường chuyển về lịch/thông báo với flash message hoặc lỗi xác thực; truy cập sai người trả 403. Xử lý lại đề nghị đã hoàn tất trả 422.

## Đăng ký và xếp lịch nhân viên

Các endpoint dưới đây dùng session web và CSRF; nhân viên là lễ tân/thu ngân. Quyền và chủ đăng ký lấy từ tài khoản đăng nhập, không lấy từ body. [Hướng dẫn nghiệp vụ](Staff_Roster_Workflow.md).

| Chức năng | Endpoint | Dữ liệu / phạm vi |
|---|---|---|
| Lịch của nhân viên | GET /nhan-vien/lich-lam-viec | date=YYYY-MM-DD, branch_id tùy chọn; chỉ đăng ký và ca chính thức của chính mình |
| Gửi đăng ký | POST /nhan-vien/lich-lam-viec | branch_id, from_date, to_date, weekdays[] (1=Thứ Hai…7=Chủ nhật), session=morning/afternoon/both, note tùy chọn; trạng thái luôn WAITING |
| Rút đăng ký | PATCH /nhan-vien/dang-ky-ca/{registration}/rut | Chỉ chủ đăng ký WAITING/ARRANGED; hủy ca dự kiến liên quan |
| Admin xem kế hoạch | GET /quan-tri/lich-nhan-vien | date, branch_id tùy chọn; focus tùy chọn để mở một đăng ký; trả fingerprint plan_version cho tập ca dự kiến |
| Xếp tự động | POST /quan-tri/lich-nhan-vien/tu-dong | date, branch_id tùy chọn, receptionist_count/cashier_count (1–10); tạo DRAFT, không tự chốt |
| Xếp/chỉnh bằng tay | POST /quan-tri/lich-nhan-vien/{registration}/xep | branch_id, work_date, start_time/end_time=HH:mm, note tùy chọn, plan_version=new khi chưa có ca hoặc SHA-256 phiên bản ca hiện tại |
| Chốt kế hoạch | POST /quan-tri/lich-nhan-vien/chot | date, branch_id tùy chọn, plan_version fingerprint tập ca DRAFT trong phạm vi; chốt nguyên đợt hoặc không chốt ca nào nếu lỗi |
| Từ chối | PATCH /quan-tri/lich-nhan-vien/{registration}/tu-choi | reason bắt buộc; chỉ đăng ký chưa chốt |
| Mở chốt | PATCH /quan-tri/ca-nhan-vien/{shift}/mo-chot | reason và plan_version của ca; chỉ ca CONFIRMED chưa bắt đầu, chuyển về DRAFT để điều chỉnh và chốt lại |

Form thành công chuyển hướng 302 kèm thông báo. Validation/nghiệp vụ, gồm trùng giờ và phiên bản cũ, chuyển về form kèm lỗi; với `Accept: application/json` trả 422. Đăng ký dùng error bag `staffRegistration`, lỗi nghiệp vụ dùng khóa `roster`. Sai vai trò/chủ đăng ký trả 403; lọc cơ sở ngoài phạm vi trả 404. POST thành công vẫn chuyển hướng theo luồng web.

## Bảng lương nhân viên

Session web và CSRF; các endpoint quản trị chỉ admin truy cập. Trang HTML trả `Cache-Control: no-store, private`. Tiền là số nguyên VNĐ. [Quy tắc tính và nguồn số công](Payroll_Workflow.md).

| Chức năng | Endpoint | Dữ liệu / quyền |
|---|---|---|
| Lương cá nhân | GET /luong-cua-toi | doctor/receptionist/cashier; month=YYYY-MM tùy chọn, chỉ bảng của tài khoản đăng nhập |
| Xem chi tiết cá nhân | GET /luong-cua-toi/{payroll} | Kiểm tra chủ sở hữu, tài khoản khác trả 403 |
| Quản lý | GET /quan-tri/bang-luong | month, q (tên/email), role, branch_id, status=UNPAID/PAID; phân trang độc lập staff_page/payroll_page |
| Đơn giá riêng | PUT /quan-tri/don-gia-luong/{employee} | pay_basis=HOUR/SHIFT, unit_rate (1–100000000), version (0 khi chưa có hồ sơ); không sửa bảng lương đã tạo |
| Lập bảng tháng | POST /quan-tri/bang-luong/nhan-vien/{employee} | month=YYYY-MM (2000-01 đến tháng hiện tại), profile_version; duy nhất nhân viên/tháng, nguồn ca đã kết thúc, trạng thái UNPAID |
| Chi tiết quản trị | GET /quan-tri/bang-luong/{payroll} | Toàn bộ bảng, bản chụp nguồn và thao tác admin |
| Điều chỉnh | PUT /quan-tri/bang-luong/{payroll} | version, unit_rate, hours (0–744), minutes (0–59), worked_shifts (0–1000), allowance/bonus/deduction (0–1000000000), reason bắt buộc, note tùy chọn; chỉ UNPAID |
| Lấy lại số công | POST /quan-tri/bang-luong/{payroll}/tinh-lai | version, reason; chỉ UNPAID, giữ đơn giá và các khoản cộng/trừ |
| Trạng thái trả lương | PATCH /quan-tri/bang-luong/{payroll}/thanh-toan | version, status=PAID/UNPAID, reason bắt buộc, reference tối đa 200 ký tự tùy chọn; lưu người/thời điểm thanh toán và audit |

Form thành công chuyển hướng 302 kèm thông báo. Dữ liệu sai, trùng kỳ lương, phiên bản cũ, nguồn ca trùng, khấu trừ quá mức hoặc chỉnh bảng đã thanh toán trả lỗi validation (422 khi yêu cầu JSON, lỗi nghiệp vụ khóa `payroll`). Sai vai trò hoặc chủ sở hữu trả 403. Các trường user_id, status hoặc tổng tiền ngoài hợp đồng không được dùng để quyết định quyền, số tiền hay chủ bảng lương.

## Machine API

POST `/api/v1/integrations/lis/results` hoặc `/api/v1/integrations/device/results`, JSON, không cookie/CSRF. Header: `X-System-Code`, `Authorization: Bearer <token>`. Đăng ký nguồn/chi nhánh/token tại Cấu hình; token chỉ lưu SHA-256.

LIS:

```json
{"external_message_id":"LIS-DEMO-001","external_order_code":"ORDER-mã-trên-phiếu","patient_id":1,"visit_id":1,"value_text":"Kết quả giả lập","unit":"demo_unit"}
```

Device:

```json
{"external_message_id":"DEVICE-DEMO-001","visit_id":1,"device_code":"MOCK-01","measurement_type":"temperature","value":36,"unit":"Cel","measured_at":"2026-11-12T09:00:00+07:00"}
```

Type/unit: `temperature` / `Cel`; `pulse` / `/min`; `weight` / `kg`; `systolic` hoặc `diastolic` / `mmHg`. Giá trị 0–1000 chỉ là giới hạn format demo, không phải ngưỡng y khoa. Số đo mới RECEIVED, cần doctor xác minh.

201 = nhận mới; 200 = message giống đã nhận; 409 = cùng ID, khác nội dung; 401 = token sai; 422 = sai mapping/unit/format. Lỗi mapping/unit lưu FAILED và mã lỗi, không ghi nguyên payload vào log. Mỗi nguồn chỉ tác động branch đã cấu hình.

`php artisan route:list --except-vendor` liệt kê đầy đủ; OpenAPI machine API ở `openapi.yaml`.
