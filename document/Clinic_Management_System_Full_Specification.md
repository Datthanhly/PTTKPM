# ĐẶC TẢ TOÀN DIỆN & HƯỚNG DẪN XÂY DỰNG WEBSITE QUẢN LÝ PHÒNG KHÁM

> **Tên dự án:** Clinic Management System (CMS) — Hệ thống quản lý phòng khám đa chi nhánh  
> **Loại tài liệu:** SRS + Software Design + Implementation Blueprint + Test Plan  
> **Phiên bản tài liệu:** 1.0 — 12/11/2026  
> **Mục đích:** Tài liệu đầu vào thống nhất để phân tích, thiết kế, lập trình, kiểm thử, viết báo cáo và demo đồ án môn *Phân tích và Thiết kế phần mềm*.  
> **Trạng thái:** Bản thiết kế đề xuất; **chưa phải mã nguồn đã triển khai**. Các ví dụ tài khoản và dữ liệu khám đều phải dùng dữ liệu giả lập.

> **Bản triển khai Laragon 6.0 (12/11/2026):** giữ Laravel 10 + Blade + PHP 8.1, hỗ trợ MySQL/SQLite theo môi trường thực tế. Các đề xuất stack/schema ở dưới cần đọc cùng [quyết định, ma trận triển khai và giới hạn](Implementation_Laragon.md). Hợp đồng HTTP đã triển khai nằm trong [API_Laragon.md](API_Laragon.md).

---

## MỤC LỤC

1. [Cách dùng tài liệu](#1-cách-dùng-tài-liệu)
2. [Bài toán, phạm vi, giả định](#2-bài-toán-phạm-vi-giả-định)
3. [Vai trò, quyền và chính sách truy cập](#3-vai-trò-quyền-và-chính-sách-truy-cập)
4. [Đặc tả chức năng FR-01 đến FR-17](#4-đặc-tả-chức-năng-fr-01-đến-fr-17)
5. [Yêu cầu phi chức năng và bảo mật](#5-yêu-cầu-phi-chức-năng-và-bảo-mật)
6. [Quy tắc nghiệp vụ và trạng thái](#6-quy-tắc-nghiệp-vụ-và-trạng-thái)
7. [Use Case và sơ đồ UML](#7-use-case-và-sơ-đồ-uml)
8. [Mô hình dữ liệu và ERD](#8-mô-hình-dữ-liệu-và-erd)
9. [Thiết kế backend, frontend, API](#9-thiết-kế-backend-frontend-api)
10. [Danh mục màn hình và thành phần UI](#10-danh-mục-màn-hình-và-thành-phần-ui)
11. [Hướng dẫn khởi tạo dự án](#11-hướng-dẫn-khởi-tạo-dự-án)
12. [Lập trình các quy trình khó](#12-lập-trình-các-quy-trình-khó)
13. [PDF, Excel, email, tái khám, kho và tích hợp](#13-pdf-excel-email-tái-khám-kho-và-tích-hợp)
14. [Kiểm thử và tiêu chí nghiệm thu](#14-kiểm-thử-và-tiêu-chí-nghiệm-thu)
15. [Kế hoạch 16 tuần và chia việc](#15-kế-hoạch-16-tuần-và-chia-việc)
16. [Tài liệu bàn giao và demo](#16-tài-liệu-bàn-giao-và-demo)
17. [Checklist bắt đầu lập trình](#17-checklist-bắt-đầu-lập-trình)
18. [Nhật ký quyết định thiết kế](#18-nhật-ký-quyết-định-thiết-kế)

---

# 1. Cách dùng tài liệu

- Đọc **§2–§7** để chốt phạm vi và nghiệp vụ trước khi viết code.
- Dùng **§8** để tạo mô hình cơ sở dữ liệu và migration.
- Dùng **§9–§13** để xây dựng frontend/backend từng module.
- Sau mỗi chức năng, đối chiếu **FR → UC → API → Test Case** ở **§14**.
- Lập trình **P0 trước**, sau đó P1, cuối cùng P2 (mô phỏng LIS/thiết bị). Tài liệu vẫn thiết kế đủ mọi nhóm chức năng.
- Những cấu hình phụ thuộc phòng khám thực tế phải được đưa thành tham số cấu hình, không hard-code tùy tiện.

**Định nghĩa mức ưu tiên**

| Mức | Mục tiêu | Điều kiện hoàn thành |
|---|---|---|
| P0 | Luồng cốt lõi vận hành xuyên suốt | Bệnh nhân đặt lịch → lễ tân tiếp nhận → bác sĩ khám/kê đơn → thu ngân thanh toán → admin xem thống kê |
| P1 | Mở rộng quản trị và nghiệp vụ | Đổi lịch, nghỉ bác sĩ, xét nghiệm/dịch vụ, kho, nhiều chi nhánh, PDF, Excel, email |
| P2 | Tự động hóa/tích hợp | Tái khám tự động theo đồng ý; kết nối LIS và thiết bị qua API giả lập |

**Ngoài phạm vi ban đầu:** lưu trú nội trú; bảo hiểm y tế tích hợp thật; cổng thanh toán sản xuất; kết nối thiết bị y tế thật; ký số/hồ sơ bệnh án điện tử triển khai pháp lý thực tế. Đây là ứng dụng **demo học thuật**, không đưa vào vận hành lâm sàng với dữ liệu thật khi chưa thẩm định kỹ thuật, quyền riêng tư và tuân thủ pháp luật.

# 2. Bài toán, phạm vi, giả định

## 2.1. Bài toán

Quy trình vận hành phòng khám thường gồm đặt lịch, tiếp nhận, khám và chỉ định, nhận kết quả, kê đơn, cấp thuốc, thanh toán và báo cáo. Việc xử lý rời rạc dẫn đến trùng lịch, ghi dữ liệu nhiều lần, sai sót báo cáo và khó truy vết. CMS số hóa toàn bộ vòng đời một lượt khám trong hệ thống đa chi nhánh.

## 2.2. Mục tiêu đo lường

1. Quản lý tập trung hồ sơ bệnh nhân, lịch hẹn, lượt khám, đơn thuốc và hóa đơn.
2. Có **5 vai trò chính** và quyền nghiệp vụ bổ sung.
3. Bác sĩ có thể làm tại nhiều chi nhánh mà không trùng ca.
4. Có chống đặt lịch quá sức chứa kể cả khi hai người đặt đồng thời.
5. Phân biệt **kê đơn** với **cấp phát**, đảm bảo không xuất thuốc quá tồn.
6. Tính toán khoản phải thu, thanh toán và số dư chính xác.
7. Dashboard theo chi nhánh và toàn hệ thống dựa trên dữ liệu phát sinh thực tế.
8. Gửi thông báo tách khỏi giao dịch chính; hỗ trợ gửi lại khi lỗi.
9. Hỗ trợ PDF, Excel, lịch tái khám và kết nối LIS/thiết bị giả lập.
10. Có UML, ERD, bảng kiểm thử, hướng dẫn cài đặt và kịch bản demo.

## 2.3. Thuật ngữ

| Từ | Ý nghĩa |
|---|---|
| Appointment | Lịch hẹn; bệnh nhân có thể không đến |
| Visit | Lượt khám thực tế; có thể tạo trực tiếp không qua lịch |
| Medical Record | Nội dung khám của một lượt khám |
| Prescription | Đơn thuốc được kê |
| Dispensation | Phiếu cấp thuốc thực tế, ảnh hưởng tồn kho |
| Service Order | Phiếu chỉ định xét nghiệm/dịch vụ |
| Diagnostic Result | Kết quả dịch vụ; có quy trình kiểm tra/duyệt |
| Invoice | Các khoản phải thu đã được lập thành chứng từ |
| Payment | Giao dịch thu tiền; có thể thu nhiều lần |
| Branch | Chi nhánh phòng khám |
| Slot | Khung giờ đặt lịch |
| LIS | Hệ thống thông tin xét nghiệm |
| FHIR | Chuẩn mô hình trao đổi dữ liệu y tế |
| RBAC | Phân quyền theo vai trò |
| Idempotency | Cùng một yêu cầu gửi lặp không tạo tác động lặp |

## 2.4. Giả định cần thống nhất

- Một tổ chức phòng khám vận hành từ **1 đến N chi nhánh**.
- Hồ sơ bệnh nhân có **mã toàn hệ thống**; mỗi chi nhánh có lịch, kho và doanh thu riêng.
- Các bác sĩ có thể thuộc nhiều chuyên khoa và công tác tại nhiều chi nhánh.
- Mỗi lịch hẹn chọn **một bác sĩ, một chi nhánh, một khoảng thời gian**.
- Thời lượng khám mặc định có thể cấu hình theo dịch vụ/bác sĩ, ví dụ 30 phút; không coi 30 phút là quy định cố định.
- Số chỗ/slot mặc định là 1; nếu khám nhóm thì cấu hình capacity tương ứng.
- Tất cả thời điểm lưu trong database sử dụng kiểu timezone-aware, hiển thị theo múi giờ chi nhánh; mặc định môi trường demo Asia/Ho_Chi_Minh.
- Một lượt khám có tối đa một hóa đơn chính (mô hình đồ án); thanh toán nhiều phần được hỗ trợ.
- Một hóa đơn lưu snapshot giá và tên hạng mục tại thời điểm phát hành.
- Chỉ nhân sự có quyền chuyên môn mới xác nhận kết quả, chốt đơn và cấp phát thuốc.
- Tài khoản Admin không được mặc nhiên đọc tất cả bệnh án: **quyền quản trị không đồng nghĩa quyền khám chữa bệnh**.
- Các mốc nhắc lịch cấu hình được; mặc định demo 24 giờ và 2 giờ trước lịch.

# 3. Vai trò, quyền và chính sách truy cập

## 3.1. Vai trò chính

| Code | Vai trò | Công việc |
|---|---|---|
| PATIENT | Bệnh nhân | Hồ sơ của mình, đặt/hủy/đổi lịch, xem thông tin khám đã công bố, xác nhận tái khám |
| RECEPTIONIST | Lễ tân | Hồ sơ hành chính, đặt lịch hộ, check-in, danh sách chờ |
| DOCTOR | Bác sĩ | Lượt khám được phân công, bệnh sử cần thiết, chẩn đoán, chỉ định, kê đơn |
| CASHIER | Thu ngân | Hóa đơn, thanh toán, các trường hành chính tối thiểu |
| ADMIN | Quản trị viên | Cấu hình chi nhánh, nhân viên, danh mục, quyền, báo cáo tổng hợp |

## 3.2. Quyền nghiệp vụ (permission) bổ sung

`PATIENT_READ_OWN`, `PATIENT_ADMIN_WRITE`, `APPOINTMENT_CREATE`, `APPOINTMENT_RESCHEDULE`, `VISIT_CHECK_IN`, `VISIT_TREAT`, `RECORD_SIGN`, `PRESCRIPTION_SIGN`, `SERVICE_ORDER_CREATE`, `LAB_RESULT_ENTER`, `LAB_RESULT_VERIFY`, `INVOICE_ISSUE`, `PAYMENT_COLLECT`, `INVENTORY_MANAGE`, `MEDICINE_DISPENSE`, `BRANCH_MANAGE`, `REPORT_VIEW`, `REPORT_EXPORT`, `INTEGRATION_MANAGE`.

**Mô hình dữ liệu quyền:** `users` → `role_assignments(user_id, role_code, branch_id nullable)` → `role_permissions(role_code, permission_code)`, cộng ngoại lệ có kiểm soát trong `user_permissions(user_id, permission_code, branch_id, granted_by)`. Nếu không triển khai bảng permission chi tiết ở P0, cấu hình mapping trong source code nhưng mọi API vẫn phải kiểm tra quyền.

## 3.3. Quy tắc phạm vi

- **OWN:** bệnh nhân được xem hồ sơ gắn với tài khoản mình; trường hợp người giám hộ cần cơ chế ủy quyền riêng (ngoài phạm vi P0).
- **ASSIGNED:** bác sĩ chỉ được thao tác lượt khám mình được phân công; truy cập lịch sử cần thiết phải được ghi log và theo chính sách phòng khám.
- **BRANCH:** nhân viên chỉ được nhìn danh sách nghiệp vụ thuộc chi nhánh được phân công.
- **GLOBAL:** quản trị có thể xem số liệu tổng hợp toàn hệ thống; quyền đối với thông tin y tế chi tiết phải cấp riêng.
- Không lấy `role`, `branchId`, `userId` gửi từ frontend làm bằng chứng quyền; backend lấy danh tính từ phiên xác thực.

## 3.4. Ma trận quyền cốt lõi

| Nghiệp vụ | PATIENT | RECEPTIONIST | DOCTOR | CASHIER | ADMIN |
|---|---|---|---|---|---|
| Xem/sửa thông tin cá nhân | Theo chính mình | Hành chính có phân công | Theo nghiệp vụ khám | Tối thiểu để thu tiền | Hành chính theo quyền |
| Đặt lịch | Có | Có | Không mặc định | Không | Theo quyền |
| Đổi/hủy lịch | Theo quy tắc | Có | Không mặc định | Không | Theo quyền |
| Check-in | Không | Có | Không | Không | Theo quyền |
| Ghi hồ sơ khám | Không | Không | Lượt được phân công | Không | Không mặc định |
| Kê đơn | Không | Không | Có | Không | Không |
| Nhập/duyệt kết quả | Không | Không | Nếu có quyền nghiệp vụ | Không | Không mặc định |
| Hóa đơn/thanh toán | Xem của mình | Không | Không | Có | Theo quyền |
| Kho/cấp phát | Không | Không | Chỉ nếu được phân quyền chuyên môn | Không | Quản lý cấu hình; cấp phát phải có quyền chuyên môn |
| Chi nhánh/danh mục | Không | Không | Không | Không | Có |
| Báo cáo tổng hợp | Không | Không | Theo phân công | Phần tài chính theo quyền | Có |

# 4. Đặc tả chức năng FR-01 đến FR-17

> **Quy tắc đặt mã:** `FR-xx.yy`; quy tắc có thể truy vết đến `UC-xx`, API, màn hình, Test Case. Ưu tiên có thể thay đổi theo thời gian còn lại nhưng không xóa mã đã dùng.

## FR-01 — Xác thực & phân quyền (P0)

- **FR-01.01** Bệnh nhân đăng ký tài khoản, kiểm tra thông tin bắt buộc và trùng email.
- **FR-01.02** Đăng nhập email/tên đăng nhập + mật khẩu; thông báo lỗi không tiết lộ tài khoản tồn tại hay không.
- **FR-01.03** Kiểm tra trạng thái `ACTIVE/LOCKED/DISABLED`.
- **FR-01.04** Tạo phiên và điều hướng giao diện theo vai trò.
- **FR-01.05** Đăng xuất; chấm dứt/thu hồi phiên.
- **FR-01.06** Đổi mật khẩu, khôi phục qua token một lần hết hạn.
- **FR-01.07** Admin tạo, gán chi nhánh, khóa/mở tài khoản nhân viên.
- **FR-01.08** Backend kiểm tra `permission + scope` ở mỗi API.
- **FR-01.09** Ghi log login, thay đổi quyền, reset mật khẩu và các thao tác nhạy cảm.

**Đầu vào:** thông tin đăng nhập/đăng ký. **Đầu ra:** phiên xác thực và hồ sơ quyền. **Lỗi:** sai mật khẩu, bị khóa, hết phiên, không có quyền.

## FR-02 — Bệnh nhân (P0)

- **FR-02.01** Tạo hồ sơ bệnh nhân.
- **FR-02.02** Sinh `patient_code` duy nhất toàn hệ thống.
- **FR-02.03** Sửa thông tin hành chính, có lịch sử cập nhật quan trọng.
- **FR-02.04** Tìm theo mã, tên, số điện thoại; có phân trang.
- **FR-02.05** Xem lịch sử lượt khám theo quyền.
- **FR-02.06** Lưu dị ứng, tiền sử, liên hệ khẩn cấp.
- **FR-02.07** Cảnh báo hồ sơ có dấu hiệu trùng (không tự gộp).
- **FR-02.08** Vô hiệu hóa/đánh dấu trùng thay vì xóa cứng.
- **FR-02.09** Che/giới hạn trường nhạy cảm theo vai trò.

## FR-03 — Bác sĩ & chuyên khoa (P0)

- **FR-03.01** CRUD mềm hồ sơ bác sĩ.
- **FR-03.02** CRUD chuyên khoa.
- **FR-03.03** Một bác sĩ thuộc nhiều chuyên khoa.
- **FR-03.04** Phân công bác sĩ nhiều chi nhánh.
- **FR-03.05** Thông tin chuyên môn, giới thiệu, trạng thái.
- **FR-03.06** Lọc bác sĩ theo chuyên khoa/chi nhánh/lịch khả dụng.
- **FR-03.07** Không xóa bác sĩ đã có lịch sử khám.

## FR-04 — Lịch làm việc & đặt lịch (P0)

- **FR-04.01** Admin tạo ca làm định kỳ theo thứ trong tuần.
- **FR-04.02** Cấu hình thời lượng và sức chứa slot.
- **FR-04.03** Ca làm gắn bác sĩ + chi nhánh.
- **FR-04.04** Trả các slot còn khả dụng từ ca làm, lịch nghỉ và số booking.
- **FR-04.05** Bệnh nhân tự đặt lịch.
- **FR-04.06** Lễ tân đặt lịch hộ.
- **FR-04.07** Chặn đặt trùng/overbook ngay tại transaction.
- **FR-04.08** Xác nhận/hủy/tìm lịch.
- **FR-04.09** Lưu lý do và người tạo.
- **FR-04.10** Quản lý trạng thái lịch hợp lệ.

## FR-05 — Tiếp nhận & lượt khám (P0)

- **FR-05.01** Lễ tân tra cứu lịch theo ngày/chi nhánh/bệnh nhân.
- **FR-05.02** Check-in lịch hợp lệ.
- **FR-05.03** Tạo lượt khám khi check-in.
- **FR-05.04** Tạo lượt khám cho bệnh nhân walk-in.
- **FR-05.05** Danh sách chờ theo bác sĩ và chi nhánh.
- **FR-05.06** Chuyển trạng thái lượt khám theo quy định.
- **FR-05.07** Lưu các thời điểm check-in/bắt đầu/kết thúc.
- **FR-05.08** Chặn check-in trùng.

## FR-06 — Hồ sơ khám & kê đơn (P0)

- **FR-06.01** Bác sĩ mở lượt khám được phân công.
- **FR-06.02** Hiển thị bệnh sử, dị ứng được phép truy cập.
- **FR-06.03** Ghi triệu chứng, lý do khám.
- **FR-06.04** Ghi chỉ số sinh tồn (tùy có dữ liệu).
- **FR-06.05** Ghi khám lâm sàng, chẩn đoán.
- **FR-06.06** Ghi hướng xử trí và lời dặn.
- **FR-06.07** Tìm thuốc từ danh mục.
- **FR-06.08** Kê đơn: thuốc, đơn vị, liều, đường dùng, số lần/ngày, số ngày, tổng lượng, hướng dẫn.
- **FR-06.09** Lưu nháp/chốt hồ sơ, đơn thuốc.
- **FR-06.10** Sau chốt dùng bản sửa đổi có lưu người, thời điểm, lý do.

## FR-07 — Hóa đơn & thanh toán (P0)

- **FR-07.01** Lập hóa đơn gắn lượt khám.
- **FR-07.02** Tổng hợp phí khám, dịch vụ, thuốc được bán/cấp có thu tiền.
- **FR-07.03** Chụp snapshot tên, giá, số lượng tại lúc phát hành.
- **FR-07.04** Tính tạm tính, giảm giá (nếu có), phải thu.
- **FR-07.05** Ghi nhận tiền mặt hoặc chuyển khoản được xác nhận thủ công.
- **FR-07.06** Hỗ trợ thanh toán nhiều lần/một phần.
- **FR-07.07** Tính tổng đã thu và còn phải thu.
- **FR-07.08** Lưu lịch sử giao dịch bất biến theo quy tắc.
- **FR-07.09** Phân biệt trạng thái phát hành và thanh toán.
- **FR-07.10** Hủy/điều chỉnh/hoàn tiền có quyền và audit.

## FR-08 — Dashboard (P0)

- **FR-08.01** Tổng lịch hẹn trong ngày.
- **FR-08.02** Tổng lượt khám hoàn tất.
- **FR-08.03** Bệnh nhân mới.
- **FR-08.04** Doanh thu **đã thu** trong khoảng thời gian.
- **FR-08.05** Công nợ/hóa đơn còn phải thu.
- **FR-08.06** Biểu đồ lượt khám theo ngày/tuần/tháng.
- **FR-08.07** Lượt khám theo chuyên khoa.
- **FR-08.08** Bộ lọc ngày, bác sĩ (khi phù hợp), chi nhánh.
- **FR-08.09** Chỉ tính và hiển thị dữ liệu theo quyền.

## FR-09 — Chỉ định & kết quả dịch vụ (P1)

- **FR-09.01** Quản lý danh mục dịch vụ và giá.
- **FR-09.02** Bác sĩ chọn một/nhiều dịch vụ.
- **FR-09.03** Tạo phiếu chỉ định duy nhất.
- **FR-09.04** Theo dõi `ORDERED/IN_PROGRESS/RESULT_PENDING/COMPLETED/CANCELLED`.
- **FR-09.05** Người có quyền nhập kết quả thủ công.
- **FR-09.06** Đính kèm tệp an toàn.
- **FR-09.07** Kiểm tra/duyệt kết quả, lưu người duyệt.
- **FR-09.08** Thông báo đến bác sĩ khi sẵn sàng.
- **FR-09.09** Liên kết kết quả với lượt khám và từng dòng chỉ định.
- **FR-09.10** Sinh hạng mục hóa đơn theo quy tắc, không tính phí trùng.

## FR-10 — Đổi lịch & nghỉ bác sĩ (P1)

- **FR-10.01** Bệnh nhân yêu cầu đổi lịch theo chính sách.
- **FR-10.02** Lễ tân đổi lịch được phân quyền.
- **FR-10.03** Kiểm tra lại slot mới tại transaction.
- **FR-10.04** Lưu lịch sử ngày/giờ cũ và mới, người sửa và lý do.
- **FR-10.05** Tạo khoảng bác sĩ nghỉ tại tất cả chi nhánh liên quan.
- **FR-10.06** Tìm lịch bị ảnh hưởng.
- **FR-10.07** Đề xuất đổi giờ/bác sĩ hoặc hủy hợp lệ.
- **FR-10.08** Gửi thông báo.
- **FR-10.09** Không tự mất lịch xác nhận khi thêm lịch nghỉ.

## FR-11 — PDF (P1)

- **FR-11.01** PDF đơn thuốc đã chốt.
- **FR-11.02** PDF hóa đơn.
- **FR-11.03** Đúng thông tin chi nhánh.
- **FR-11.04** Mã chứng từ và ngày lập.
- **FR-11.05** Hiển thị tiếng Việt, font nhúng phù hợp (không phân phối font độc quyền).
- **FR-11.06** In/tải.
- **FR-11.07** Yêu cầu quyền xem đúng chứng từ.
- **FR-11.08** Tài liệu demo có nhãn phù hợp, không tự nhận là chứng từ lâm sàng/pháp lý hoàn chỉnh.

## FR-12 — Tìm/lọc/Excel (P1)

- **FR-12.01** Tìm theo từ khóa, mã, ngày.
- **FR-12.02** Lọc lịch theo bác sĩ, chi nhánh, trạng thái.
- **FR-12.03** Lọc hóa đơn theo thời gian, trạng thái thanh toán.
- **FR-12.04** Lọc tồn kho theo hạn dùng/tồn thấp.
- **FR-12.05** Phân trang/sắp xếp server-side.
- **FR-12.06** Xuất Excel theo bộ lọc.
- **FR-12.07** Xuất dữ liệu theo quyền và phạm vi chi nhánh.
- **FR-12.08** Audit thao tác xuất dữ liệu nhạy cảm.

## FR-13 — Email & nhắc lịch (P1)

- **FR-13.01** Email sau đặt lịch.
- **FR-13.02** Email sau xác nhận.
- **FR-13.03** Email đổi/hủy.
- **FR-13.04** Nhắc lịch theo mốc cấu hình.
- **FR-13.05** Tôn trọng tùy chọn thông báo của bệnh nhân.
- **FR-13.06** Lưu job, trạng thái và thời điểm gửi.
- **FR-13.07** Retry có backoff khi lỗi tạm thời.
- **FR-13.08** Không gửi trùng cùng job.
- **FR-13.09** Email nhắc không chứa chẩn đoán hay nội dung y tế nhạy cảm.

## FR-14 — Kho & cấp phát thuốc (P1)

- **FR-14.01** Danh mục thuốc và đơn vị.
- **FR-14.02** Kho thuộc chi nhánh.
- **FR-14.03** Nhập lô: số lô, số lượng, hạn dùng.
- **FR-14.04** Theo dõi ngày nhập/hạn dùng.
- **FR-14.05** Số dư tồn theo lô, kho, thuốc.
- **FR-14.06** Cảnh báo tồn thấp.
- **FR-14.07** Cảnh báo hết/sắp hết hạn.
- **FR-14.08** Tạo phiếu cấp phát từ đơn đã chốt.
- **FR-14.09** Ghi lượng thực cấp.
- **FR-14.10** Trừ tồn khi xác nhận **cấp phát**, không trừ khi kê đơn.
- **FR-14.11** Không xuất vượt lượng khả dụng.
- **FR-14.12** Trả thuốc/điều chỉnh kho cần chứng từ, lý do và quyền.
- **FR-14.13** Lưu người, thời điểm, lô thuốc.
- **FR-14.14** Báo cáo nhập–xuất–tồn.

## FR-15 — Nhiều chi nhánh (P1, kiến trúc có ngay từ P0)

- **FR-15.01** CRUD mềm chi nhánh.
- **FR-15.02** Thông tin liên lạc, giờ mở cửa.
- **FR-15.03** Gán nhân viên vào chi nhánh.
- **FR-15.04** Gán bác sĩ vào nhiều chi nhánh.
- **FR-15.05** Lịch theo chi nhánh.
- **FR-15.06** Kho theo chi nhánh.
- **FR-15.07** Hóa đơn và doanh thu theo chi nhánh.
- **FR-15.08** Dashboard tổng hợp toàn hệ thống.
- **FR-15.09** API giới hạn dữ liệu theo chi nhánh và permission.
- **FR-15.10** Kiểm soát trùng thời gian bác sĩ giữa chi nhánh.

## FR-16 — Tái khám tự động (P2)

- **FR-16.01** Bác sĩ đề xuất ngày/khoảng tái khám.
- **FR-16.02** Ghi lý do và khoảng ngày phù hợp.
- **FR-16.03** Liên kết với lượt khám trước.
- **FR-16.04** Gợi ý slot.
- **FR-16.05** Gửi đề xuất cho bệnh nhân.
- **FR-16.06** Bệnh nhân chấp nhận/từ chối.
- **FR-16.07** Chỉ tự đặt khi có đăng ký đồng ý còn hiệu lực và đáp ứng điều kiện.
- **FR-16.08** Chống trùng slot.
- **FR-16.09** Nhắc lịch tái khám.
- **FR-16.10** Rút lại đồng ý tự đặt lịch.

## FR-17 — LIS/thiết bị y tế (P2, dùng mock trước)

- **FR-17.01** Đăng ký cấu hình hệ thống ngoài.
- **FR-17.02** Gửi yêu cầu xét nghiệm có mã liên kết.
- **FR-17.03** Nhận kết quả từ LIS mock.
- **FR-17.04** Đối chiếu với phiếu chỉ định, bệnh nhân, lượt khám.
- **FR-17.05** Nhận dữ liệu sinh tồn từ thiết bị giả lập.
- **FR-17.06** Kiểm tra format, đơn vị, giá trị, nguồn.
- **FR-17.07** Lưu thời gian đo và nguồn gốc.
- **FR-17.08** Trạng thái `RECEIVED → VALIDATING → VERIFIED/REJECTED`, không tự công bố trước duyệt.
- **FR-17.09** Ghi log lỗi và trạng thái đồng bộ.
- **FR-17.10** Nhập tay có lưu vết khi hệ thống ngoài lỗi.
- **FR-17.11** Mock LIS và Mock Device.
- **FR-17.12** Chuẩn bị adapter ánh xạ dữ liệu kiểu FHIR; DICOM chỉ cần khi phát triển tích hợp hình ảnh y tế.

# 5. Yêu cầu phi chức năng và bảo mật

| ID | Yêu cầu | Tiêu chí kiểm thử đề xuất |
|---|---|---|
| NFR-01 | Hiệu năng | ≥95% yêu cầu tra cứu P0 <2 giây với 50 người dùng đồng thời trong môi trường demo được ghi lại cấu hình |
| NFR-02 | Xác thực | Mật khẩu băm Argon2id; phiên đăng nhập hết hạn/thu hồi được |
| NFR-03 | Quyền | Test trực tiếp API xác minh mọi route cần quyền và branch scope |
| NFR-04 | Bảo vệ dữ liệu | Không rò dữ liệu bệnh án qua lỗi, log, URL, email, export |
| NFR-05 | Nhất quán | Không slot quá chỗ, không check-in trùng, không kho âm, không thu trùng |
| NFR-06 | Dễ dùng | Giao diện tiếng Việt, trường bắt buộc, trạng thái lỗi rõ ràng |
| NFR-07 | Responsive | Giao diện bệnh nhân chạy tốt mobile; giao diện staff tối ưu desktop/tablet |
| NFR-08 | Audit | Có actor, action, resource, time, branch, request_id đối với hành động quan trọng |
| NFR-09 | Sao lưu | Có backup tự động môi trường chạy thử và chạy thử restore trước demo |
| NFR-10 | Khả năng mở rộng | Thêm branch, doctor, stock mà không cần sửa dữ liệu cũ |
| NFR-11 | Chịu lỗi tích hợp | Email/LIS lỗi không rollback booking/medical-record đã commit |
| NFR-12 | Bảo trì | Module tách lớp, validation dùng chung, OpenAPI, CI kiểm thử |
| NFR-13 | Truy cập file | PDF/tệp kết quả yêu cầu xác thực và kiểm quyền; link tải tạm thời |
| NFR-14 | Tái lập | Docker Compose + migration + seed chạy được trên máy khác |

**Checklist bảo mật tối thiểu:**

- TLS/HTTPS ở môi trường triển khai; HTTPS-only cookies, `HttpOnly`, `SameSite`, `Secure` phù hợp.
- Hash mật khẩu, không log mật khẩu/token/PHI; hạn chế rate login; quản lý secret bằng biến môi trường.
- Với auth dùng cookie: bảo vệ CSRF cho các thao tác thay đổi trạng thái; với token: không lưu access token nhạy cảm trong localStorage nếu tránh được.
- Kiểm tra quyền **trên từng bản ghi** (object-level authorization/IDOR), nhất là patientId, visitId, invoiceId, branchId.
- Truy vấn tham số hóa qua ORM; giới hạn kích thước request và upload; validate MIME/content, không chỉ extension.
- Chặn formula injection khi export Excel (chuỗi bắt đầu `=`, `+`, `-`, `@` theo chính sách escape).
- Đặt policy retention và backup; seed bằng dữ liệu giả; môi trường demo không dùng email/điện thoại của người thật.
- Quản trị viên hệ thống không được mặc nhiên có quyền duyệt hồ sơ bệnh án hoặc cấp phát thuốc.
- Rà soát quy định hiện hành về dữ liệu cá nhân, khám chữa bệnh, hóa đơn và bệnh án điện tử trước mọi ứng dụng thực tế; tài liệu này không phải chứng nhận tuân thủ.

# 6. Quy tắc nghiệp vụ và trạng thái

## 6.1. Business Rules

| ID | Nội dung |
|---|---|
| BR-01 | Mỗi bệnh nhân có một `patient_code` duy nhất toàn hệ thống |
| BR-02 | Lịch bác sĩ không được trùng thời gian giữa các chi nhánh |
| BR-03 | Slot phải nằm trong ca làm và ngoài lịch nghỉ |
| BR-04 | Tổng lịch hiệu lực của slot ≤ capacity; dùng transaction để chống race condition |
| BR-05 | Lịch bị hủy không thể check-in |
| BR-06 | Mỗi lịch hẹn tạo tối đa một lượt khám; walk-in không cần appointment |
| BR-07 | Lượt khám gắn bệnh nhân, bác sĩ, chi nhánh |
| BR-08 | Hồ sơ đã ký/chốt chỉ sửa bằng cơ chế điều chỉnh có lưu vết |
| BR-09 | Chỉ người có quyền chuyên môn được chốt đơn thuốc và xác minh kết quả |
| BR-10 | Kê đơn không làm giảm tồn; chỉ cấp phát mới giảm tồn |
| BR-11 | Thuốc cấp phát phải có lô, hạn dùng hợp lệ và đủ tồn |
| BR-12 | Cấp phát và cập nhật tồn trong cùng DB transaction |
| BR-13 | Đơn giá hóa đơn được chụp snapshot tại thời điểm phát hành |
| BR-14 | Không tính phí trùng; khoản đã hủy không được cộng thu |
| BR-15 | Số đã thu = tổng payment SUCCEEDED - refund hợp lệ; không tính payment FAILED/PENDING |
| BR-16 | Không xóa cứng bệnh án/hóa đơn đã phát hành bằng CRUD thường |
| BR-17 | Lịch nghỉ phải dẫn đến danh sách lịch bị ảnh hưởng và phương án xử lý |
| BR-18 | Mỗi lần đổi lịch có audit old/new, người và lý do |
| BR-19 | Không tự tạo lịch tái khám nếu không có điều kiện đồng ý hợp lệ |
| BR-20 | Nhân viên chỉ xem dữ liệu trong phạm vi chi nhánh/quyền |
| BR-21 | Nhận lại cùng messageId bên LIS không tạo kết quả trùng |
| BR-22 | Email gửi lỗi không làm mất lịch hẹn đã tạo |
| BR-23 | Không công bố kết quả thiết bị/LIS chưa xác minh như kết quả chính thức |
| BR-24 | File PDF/Excel chỉ chứa dữ liệu người dùng được phép xem |
| BR-25 | Chức năng cấp phát dành cho người có thẩm quyền phù hợp, không mặc định thuộc CASHIER |
| BR-26 | Khi chuyển chi nhánh của lượt khám phải bảo toàn ràng buộc lịch, hồ sơ và nguồn thu |

## 6.2. Trạng thái

| Đối tượng | Trạng thái | Ghi chú |
|---|---|---|
| Appointment | `PENDING, CONFIRMED, CHECKED_IN, COMPLETED, CANCELLED, NO_SHOW` | CANCELLED/NO_SHOW là nhánh kết thúc |
| Visit | `WAITING, IN_PROGRESS, WAITING_RESULT, COMPLETED, CLOSED, CANCELLED` | Có thể chờ xét nghiệm |
| Prescription | `DRAFT, SIGNED, CANCELLED` | SIGNED mới đủ điều kiện cấp thuốc |
| ServiceOrder | `ORDERED, IN_PROGRESS, RESULT_PENDING, COMPLETED, CANCELLED` | Trạng thái phiếu |
| DiagnosticResult | `RECEIVED, VALIDATING, VERIFIED, REJECTED` | VERIFIED mới công bố nếu được phép |
| Dispensation | `PENDING, PARTIALLY_DISPENSED, DISPENSED, CANCELLED` | Hoàn tất trừ tồn khi xác nhận từng lần |
| Invoice | `DRAFT, ISSUED, VOID` | Payment status **tính riêng** |
| Payment | `PENDING, SUCCEEDED, FAILED, REFUNDED` | Có idempotency key |
| FollowUp | `PROPOSED, ACCEPTED, SCHEDULED, DECLINED, EXPIRED` | Kiểm tra consent |
| IntegrationMessage | `RECEIVED, PROCESSED, RETRYING, FAILED` | Dùng de-duplicate |

### Sơ đồ trạng thái lịch hẹn

```mermaid
stateDiagram-v2
    [*] --> PENDING
    PENDING --> CONFIRMED: Lễ tân xác nhận
    PENDING --> CANCELLED: Hủy
    CONFIRMED --> CHECKED_IN: Bệnh nhân đến
    CONFIRMED --> NO_SHOW: Quá giờ, không đến
    CONFIRMED --> CANCELLED: Hủy
    CHECKED_IN --> COMPLETED: Kết thúc lượt khám
    COMPLETED --> [*]
    CANCELLED --> [*]
    NO_SHOW --> [*]
```

### Sơ đồ luồng nghiệp vụ tổng thể

```mermaid
flowchart TD
    A[Bệnh nhân đặt lịch] --> B[Kiểm tra giờ bác sĩ và slot]
    B -->|Hợp lệ| C[Xác nhận lịch]
    B -->|Hết chỗ| X[Chọn lại giờ]
    C --> D{Bệnh nhân đến?}
    D -->|Không| E[Hủy hoặc đánh dấu vắng]
    D -->|Có| F[Lễ tân check-in / tạo Visit]
    F --> G[Bác sĩ thăm khám]
    G --> H{Cần dịch vụ?}
    H -->|Có| I[Chỉ định và nhận kết quả]
    I --> J[Kiểm tra / duyệt kết quả]
    J --> K[Chẩn đoán và kê đơn]
    H -->|Không| K
    K --> L[Hoàn tất lượt khám]
    L --> M[Lập hóa đơn và ghi thanh toán]
    M --> N{Có thuốc cấp tại phòng khám?}
    N -->|Có| O[Cấp phát theo lô, trừ tồn]
    N -->|Không| P[Lưu hồ sơ và đề xuất tái khám]
    O --> P
```

# 7. Use Case và sơ đồ UML

## 7.1. Danh mục Use Case

| ID | Use Case | Tác nhân chính | Liên kết FR | Ưu tiên |
|---|---|---|---|---|
| UC-01 | Đăng nhập/đăng xuất | Mọi vai trò | FR-01 | P0 |
| UC-02 | Quản lý tài khoản, quyền | ADMIN | FR-01 | P0 |
| UC-03 | Quản lý bệnh nhân | RECEPTIONIST | FR-02 | P0 |
| UC-04 | Quản lý bác sĩ/chuyên khoa | ADMIN | FR-03 | P0 |
| UC-05 | Tạo lịch làm việc | ADMIN | FR-04 | P0 |
| UC-06 | Đặt lịch khám | PATIENT, RECEPTIONIST | FR-04 | P0 |
| UC-07 | Xác nhận lịch hẹn | RECEPTIONIST | FR-04 | P0 |
| UC-08 | Đổi/hủy lịch hẹn | PATIENT, RECEPTIONIST | FR-10 | P1 |
| UC-09 | Quản lý bác sĩ nghỉ | ADMIN | FR-10 | P1 |
| UC-10 | Check-in bệnh nhân | RECEPTIONIST | FR-05 | P0 |
| UC-11 | Tiếp nhận walk-in | RECEPTIONIST | FR-05 | P0 |
| UC-12 | Xem bệnh nhân chờ | DOCTOR | FR-05 | P0 |
| UC-13 | Khám, ghi hồ sơ | DOCTOR | FR-06 | P0 |
| UC-14 | Kê/chốt đơn thuốc | DOCTOR | FR-06 | P0 |
| UC-15 | Hoàn tất lượt khám | DOCTOR | FR-05,06 | P0 |
| UC-16 | Chỉ định dịch vụ | DOCTOR | FR-09 | P1 |
| UC-17 | Nhập/duyệt kết quả | Người có quyền | FR-09 | P1 |
| UC-18 | Lập hóa đơn | CASHIER | FR-07 | P0 |
| UC-19 | Ghi thanh toán | CASHIER | FR-07 | P0 |
| UC-20 | In PDF đơn/hóa đơn | Người có quyền | FR-11 | P1 |
| UC-21 | Tìm kiếm/lọc/xuất Excel | Người có quyền | FR-12 | P1 |
| UC-22 | Quản lý thuốc | ADMIN | FR-14 | P1 |
| UC-23 | Nhập kho | Người có quyền | FR-14 | P1 |
| UC-24 | Cấp phát thuốc | Người có quyền chuyên môn | FR-14 | P1 |
| UC-25 | Quản lý chi nhánh | ADMIN | FR-15 | P1 |
| UC-26 | Xem dashboard | ADMIN | FR-08 | P0 |
| UC-27 | Gửi email/nhắc lịch | Hệ thống (job worker) | FR-13 | P1 |
| UC-28 | Đề xuất/đặt tái khám | DOCTOR, PATIENT, worker | FR-16 | P2 |
| UC-29 | Nhận kết quả LIS | LIS Mock / integration | FR-17 | P2 |
| UC-30 | Nhận số đo thiết bị | Device Mock / integration | FR-17 | P2 |

## 7.2. Sơ đồ tương tác actor–nhóm chức năng (minh họa)

> Mermaid bên dưới là **sơ đồ tương tác ở dạng flowchart**, không phải biểu đồ Use Case UML chuẩn. Khi nộp môn học, hãy vẽ Actor (hình người), khung hệ thống và oval Use Case bằng Draw.io/StarUML, với đúng các liên kết `association`, `<<include>>`, `<<extend>>` có căn cứ.

```mermaid
flowchart LR
    P([Bệnh nhân]) --> A([Đặt/đổi lịch])
    P --> F([Xác nhận tái khám])
    R([Lễ tân]) --> A
    R --> B([Tiếp nhận, check-in])
    D([Bác sĩ]) --> C([Khám, kê đơn])
    D --> G([Chỉ định dịch vụ])
    K([Thu ngân]) --> E([Hóa đơn, thanh toán])
    M([Admin]) --> H([Quản lý danh mục/chi nhánh])
    M --> I([Dashboard])
    L([LIS / thiết bị]) --> J([Nhận dữ liệu kết quả])
```

## 7.3. Mẫu đặc tả Use Case tiêu chuẩn

Mỗi UC cần viết: **ID, tên, mục tiêu, actor chính/phụ, kích hoạt, tiền điều kiện, hậu điều kiện thành công/thất bại, luồng chính, luồng thay thế, ngoại lệ, dữ liệu, quy tắc nghiệp vụ và tiêu chí nghiệm thu**.

### UC-06 — Đặt lịch khám

- **Actor:** PATIENT hoặc RECEPTIONIST đặt hộ.
- **Tiền điều kiện:** Có bệnh nhân hợp lệ; bác sĩ đang hoạt động tại chi nhánh; slot thuộc ca làm hợp lệ.
- **Kích hoạt:** Nhấn “Đặt lịch”.
- **Đầu vào:** `patientId, doctorId, branchId, startAt, endAt, reason`.
- **Luồng chính:**
  1. Chọn chi nhánh → chuyên khoa → bác sĩ → ngày.
  2. API tính slot trống từ lịch làm, nghỉ và booking hiệu lực.
  3. Người dùng chọn slot, xác nhận.
  4. Backend lấy danh tính từ phiên, kiểm quyền đặt hộ.
  5. Transaction khóa/kiểm tra sức chứa, kiểm tra bác sĩ không có conflict toàn hệ thống, tạo Appointment trạng thái PENDING hoặc CONFIRMED theo cấu hình.
  6. Tạo sự kiện email trong outbox; commit.
  7. Trả mã lịch, trạng thái; worker gửi email độc lập.
- **Ngoại lệ:** slot hết chỗ → HTTP 409; bác sĩ nghỉ → 422/409; patient không thuộc quyền → 403; thời gian quá khứ → 422.
- **Hậu điều kiện:** tối đa 1 lịch hợp lệ cho mỗi booking request; không overbook.
- **Nghiệm thu:** 2 request đồng thời vào slot capacity=1 → chỉ một request đặt được.

### UC-08 — Đổi lịch

- **Actor:** PATIENT/RECEPTIONIST.
- **Tiền điều kiện:** appointment PENDING hoặc CONFIRMED, còn trong thời hạn đổi lịch.
- **Luồng:** mở lịch → chọn lịch mới → kiểm availability trong transaction → cập nhật/ghi history old/new → ghi outbox notification → trả trạng thái.
- **Ngoại lệ:** completed/checked-in không cho đổi; slot mới hết → 409; không có quyền → 403.
- **Nghiệm thu:** slot cũ được giải phóng khi giao dịch đổi lịch commit, lịch mới hợp lệ, history lưu đầy đủ.

### UC-09 — Xử lý nghỉ bác sĩ

- **Actor:** ADMIN được cấp `SCHEDULE_MANAGE`.
- **Luồng:** nhập khoảng nghỉ → kiểm overlap với lịch bác sĩ tại mọi chi nhánh → hiển thị danh sách lịch bị ảnh hưởng → chọn cách xử lý (đề xuất giờ/bác sĩ khác hoặc hủy theo chính sách) → lưu lịch nghỉ và quyết định xử lý → phát thông báo.
- **Ngoại lệ:** có lịch đã CHECKED_IN/đang khám → chặn thao tác gây xung đột, cần xử lý riêng.
- **Nghiệm thu:** không có lịch CONFIRMED chưa xử lý thuộc thời gian nghỉ đã xác nhận.

### UC-10 — Check-in

- **Actor:** RECEPTIONIST.
- **Tiền điều kiện:** appointment thuộc chi nhánh của lễ tân, trạng thái CONFIRMED (hoặc PENDING nếu chính sách cho phép xác nhận tại quầy).
- **Luồng:** tìm bệnh nhân/lịch → xác minh thông tin hành chính → bấm check-in → DB transaction cập nhật Appointment và tạo Visit WAITING, unique theo appointmentId → hiển thị số thứ tự/danh sách chờ.
- **Ngoại lệ:** lịch đã CHECKED_IN/CANCELLED/COMPLETED → 409.
- **Nghiệm thu:** click/check-in hai lần không tạo hai Visit.

### UC-13 — Khám, ghi bệnh án

- **Actor:** DOCTOR được phân công.
- **Tiền điều kiện:** Visit trạng thái WAITING/IN_PROGRESS/WAITING_RESULT; bác sĩ có quyền.
- **Luồng:** chọn lượt khám → truy xuất lịch sử/dị ứng cần thiết → bắt đầu khám → nhập triệu chứng, dấu hiệu, chẩn đoán → lưu nháp → nếu cần chỉ định/kê đơn → chốt hồ sơ.
- **Ngoại lệ:** quyền không hợp lệ → 403; visit bị hủy → 409; hồ sơ đã ký → chỉ sửa theo amendment.
- **Nghiệm thu:** hồ sơ liên kết `visitId`; có `updatedBy` và lịch sử khi chỉnh sửa quan trọng.

### UC-14 — Kê đơn

- **Actor:** DOCTOR có quyền kê đơn.
- **Luồng:** trong Visit → thêm thuốc, đường dùng, liều, tần suất, số ngày, số lượng → validate → lưu DRAFT → ký/chốt SIGNED → có thể tạo yêu cầu cấp phát.
- **Ngoại lệ:** thiếu thông tin, thuốc vô hiệu, đơn đã ký không cho sửa trực tiếp.
- **Nghiệm thu:** sau khi SIGNED, tồn kho vẫn không giảm khi chưa cấp phát.

### UC-16/17 — Chỉ định và trả kết quả

- **Actor:** DOCTOR, nhân viên nhập/duyệt kết quả, hệ thống LIS (phụ).
- **Luồng:** tạo ServiceOrder với nhiều ServiceOrderItem → chuyển trạng thái → nhập kết quả hoặc nhận mock LIS → xác minh kết quả và đơn vị → phê duyệt → thông báo bác sĩ → liên kết Visit.
- **Ngoại lệ:** sai patient/order mapping, file độc hại, nguồn không xác thực, payload bị gửi trùng.
- **Nghiệm thu:** kết quả chưa VERIFIED không được hiển thị như kết quả đã duyệt.

### UC-18/19 — Hóa đơn và thanh toán

- **Actor:** CASHIER.
- **Luồng:** lấy các khoản phí thuộc Visit → tạo invoice draft → xác thực dòng phí → phát hành snapshot → thu tiền (một phần/toàn bộ) → Payment SUCCEEDED → cập nhật `paidAmount` tính toán → xuất PDF.
- **Ngoại lệ:** đã có invoice chính → 409; số tiền <=0 → 422; request payment duplicate → trả kết quả cũ, không tạo thu trùng; không có quyền chi nhánh → 403.
- **Nghiệm thu:** `outstanding = invoiceTotal - SUM(successfulPayments) + SUM(validRefunds)` theo mô hình hoàn tiền đã lựa chọn; luôn đối soát đúng.

### UC-23/24 — Nhập kho và cấp phát

- **Actor:** nhân viên có quyền kho/cấp phát.
- **Nhập:** chọn kho/chi nhánh → tạo phiếu nhập + dòng lô, hạn dùng, lượng → phê duyệt → ghi StockMovement tăng, cập nhật tồn.
- **Cấp:** mở đơn SIGNED → chọn tồn lô còn hạn (gợi ý FEFO: hết hạn trước xuất trước) → kiểm lượng, khóa cập nhật tồn → tạo Dispensation/Allocations + StockMovement giảm → commit.
- **Ngoại lệ:** lô hết hạn, thiếu tồn, đã cấp phát, hai request đồng thời.
- **Nghiệm thu:** tồn không âm, mỗi lần cấp phát đúng lô, có audit.

### UC-28 — Tái khám

- **Actor:** DOCTOR, PATIENT, hệ thống.
- **Luồng:** bác sĩ đề xuất khoảng tái khám → FollowUpPlan PROPOSED → thông báo → bệnh nhân đồng ý hoặc có consent tự đặt còn hiệu lực → xác định slot phù hợp → tạo Appointment thông qua **chung service đặt lịch UC-06** → FollowUpPlan SCHEDULED.
- **Ngoại lệ:** hết slot, bác sĩ nghỉ, consent bị thu hồi, tạo trùng lịch.
- **Nghiệm thu:** không tự tạo lịch nếu không có căn cứ đồng ý; plan không tạo nhiều lịch hiệu lực ngoài chủ ý.

### UC-29/30 — LIS và thiết bị y tế

- **Actor:** Integration system được xác thực.
- **Luồng:** nhận thông điệp + externalMessageId → kiểm chữ ký/API credential và schema → upsert IntegrationMessage idempotent → tìm bệnh nhân/order/visit → chuyển đổi đơn vị, đối chiếu → ghi trạng thái RECEIVED/VALIDATING → nhân viên được quyền xác minh → phản hồi ACK.
- **Ngoại lệ:** sai mã, unit, dữ liệu chưa tin cậy, message trùng, kết nối đứt.
- **Nghiệm thu:** duplicate message không sinh record mới; dữ liệu lỗi được đánh dấu có thể xử lý lại.

## 7.4. Sequence Diagram: đặt lịch

```mermaid
sequenceDiagram
    actor P as Bệnh nhân
    participant FE as React
    participant API as Appointments API
    participant DB as PostgreSQL
    participant W as Notification worker
    P->>FE: Chọn bác sĩ/ngày/giờ
    FE->>API: GET /appointments/availability
    API->>DB: Lịch làm, lịch nghỉ, booking
    DB-->>API: Slot khả dụng
    API-->>FE: Danh sách slot
    P->>FE: Xác nhận
    FE->>API: POST /appointments
    API->>DB: Transaction kiểm tra capacity và lưu
    alt Slot còn chỗ
        DB-->>API: Appointment + outbox
        API-->>FE: 201 + appointment
        FE-->>P: Đặt lịch thành công
        W->>DB: Lấy outbox/job
        W-->>P: Gửi email
    else Conflict
        DB-->>API: Slot unavailable
        API-->>FE: 409 SLOT_UNAVAILABLE
    end
```

## 7.5. Sequence Diagram: khám, hóa đơn và cấp phát

```mermaid
sequenceDiagram
    actor R as Lễ tân
    actor D as Bác sĩ
    actor C as Thu ngân
    actor PH as Người cấp phát
    participant API as Backend
    participant DB as Database
    R->>API: POST /appointments/:id/check-in
    API->>DB: Transaction tạo Visit
    API-->>R: Visit WAITING
    D->>API: PATCH /visits/:id/record
    API->>DB: Lưu hồ sơ
    D->>API: POST /visits/:id/prescriptions
    D->>API: POST /prescriptions/:id/sign
    API->>DB: Đơn SIGNED
    C->>API: POST /invoices
    API->>DB: Tạo snapshot phí
    C->>API: POST /invoices/:id/payments
    API->>DB: Lưu payment có idempotency
    PH->>API: POST /dispensations/:id/confirm
    API->>DB: Lock tồn, tạo movement, trừ stock
    API-->>PH: Đã cấp phát
```

## 7.6. Danh sách sơ đồ UML nên nộp

- Use Case tổng quan + 5 sơ đồ theo vai trò (6).
- Activity: đặt lịch, tiếp nhận, khám/chỉ định, thanh toán, cấp phát (5).
- Sequence: đăng nhập, đặt lịch, check-in, khám–kê đơn, thanh toán, cấp thuốc, LIS (7).
- Class Diagram nghiệp vụ (1).
- State Machine cho Appointment và Visit (2).
- ERD toàn hệ thống hoặc 2 ERD theo domain (1–2).
- Component Diagram và Deployment Diagram (2).

**Mức tối thiểu nếu thiếu thời gian:** Use Case tổng quan + 5 actor, 4 Activity, 4 Sequence, 1 Class, 1 ERD, 2 State và 1 Deployment. UML phải đúng ký hiệu và khớp với code.

# 8. Mô hình dữ liệu và ERD

## 8.1. Nguyên tắc tổng quát

- Cơ sở dữ liệu quan hệ **PostgreSQL**; PK UUID; cột thời gian `timestamptz` hoặc Prisma `DateTime` ánh xạ thích hợp; tiền `NUMERIC(14,2)` (VNĐ có thể quy ước số nguyên), quantity `NUMERIC` nếu thuốc có đơn vị lẻ.
- `createdAt`, `updatedAt`, `createdBy` cho bảng cần audit; `deletedAt` hoặc `status=INACTIVE` cho các danh mục có lịch sử.
- FK rõ ràng; unique và index theo tần suất tra cứu; không cascade xóa lịch sử y tế/tài chính.
- Quan hệ đa chi nhánh: **Branch** là thực thể thực; không dùng một string branch name lặp khắp database.
- Dữ liệu nhạy cảm cần encryption at rest, backup được bảo vệ và kiểm soát truy cập theo thiết kế triển khai.

## 8.2. Data Dictionary: các bảng cần có

> Ký hiệu `PK`: khóa chính; `FK`: khóa ngoại; `UQ`: duy nhất. Mặc định các bảng có `id PK UUID`, `createdAt`, `updatedAt` khi hợp lý.

### Nhóm A — Người dùng & tổ chức

| Bảng | Các trường cốt lõi | Ràng buộc |
|---|---|---|
| `users` | `username UQ`, `email UQ`, `passwordHash`, `status`, `lastLoginAt` | passwordHash không trả qua API |
| `role_assignments` | `userId FK`, `roleCode`, `branchId FK nullable` | UQ phù hợp trên user/role/branch; global scope phân biệt null |
| `role_permissions` | `roleCode`, `permissionCode` | UQ(roleCode, permissionCode) |
| `user_permissions` | `userId`, `permissionCode`, `branchId`, `grantedBy` | Audit mọi thay đổi |
| `patients` | `patientCode UQ`, `userId nullable`, `fullName`, `dob`, `gender`, `phone`, `address`, `allergies`, `medicalHistory` | userId UQ nếu 1:1 ở P0; hồ sơ walk-in có thể không có tài khoản |
| `doctors` | `doctorCode UQ`, `userId UQ`, `fullName`, `qualification`, `status` | không xóa cứng đã có visit |
| `specialties` | `code UQ`, `name`, `status` | lưu danh mục |
| `doctor_specialties` | `doctorId FK`, `specialtyId FK` | UQ(doctorId,specialtyId) |
| `branches` | `code UQ`, `name`, `address`, `phone`, `timezone`, `status` | không xóa khi có dữ liệu liên kết |
| `doctor_branch_assignments` | `doctorId`, `branchId`, `startAt`, `endAt`, `status` | kiểm tra phân công khi đặt lịch |

### Nhóm B — Lịch hẹn & khám

| Bảng | Các trường cốt lõi | Ràng buộc |
|---|---|---|
| `doctor_schedules` | `doctorId`, `branchId`, `dayOfWeek`, `startTime`, `endTime`, `slotMinutes`, `capacity`, `validFrom`, `validTo` | start < end; không trùng ca bất hợp lệ |
| `doctor_time_off` | `doctorId`, `startAt`, `endAt`, `reason`, `status` | áp dụng toàn bộ lịch bác sĩ |
| `appointment_slots` *(khuyến nghị)* | `doctorId`, `branchId`, `startsAt`, `endsAt`, `capacity`, `bookedCount` | UQ(doctorId,startsAt,endsAt); bookedCount ≤ capacity; kiểm tra không overlap |
| `appointments` | `patientId`, `doctorId`, `branchId`, `slotId`, `startsAt`, `endsAt`, `status`, `reason`, `createdBy` | index theo branch/time; chống trùng theo transaction |
| `appointment_history` | `appointmentId`, `oldStart`, `newStart`, `oldDoctorId`, `newDoctorId`, `reason`, `changedBy` | append-only |
| `visits` | `appointmentId nullable`, `patientId`, `doctorId`, `branchId`, `status`, `checkInAt`, `startedAt`, `completedAt` | `appointmentId` UQ khi khác null |
| `medical_records` | `visitId UQ`, `symptoms`, `findings`, `diagnosis`, `treatmentPlan`, `status`, `signedAt`, `signedBy` | chốt → version/amendment |
| `medical_record_revisions` | `recordId`, `revisionNumber`, `snapshot`, `reason`, `updatedBy` | UQ(recordId,revisionNumber) |
| `vital_signs` | `visitId`, `pulse`, `systolic`, `diastolic`, `temperature`, `weight`, `measuredAt`, `source` | units được xác định rõ |
| `follow_up_plans` | `sourceVisitId`, `suggestedFrom`, `suggestedTo`, `doctorId`, `consentStatus`, `appointmentId nullable`, `status` | idempotency khi tạo lịch |

### Nhóm C — Thuốc & dịch vụ

| Bảng | Các trường cốt lõi | Ràng buộc |
|---|---|---|
| `medicines` | `code UQ`, `name`, `strength`, `form`, `unit`, `status` | không xóa thuốc đã được kê |
| `prescriptions` | `visitId`, `doctorId`, `status`, `signedAt`, `signedBy` | ký bởi người có quyền |
| `prescription_items` | `prescriptionId`, `medicineId`, `dosage`, `route`, `frequency`, `durationDays`, `quantity`, `instructions` | quantity > 0 |
| `services` | `code UQ`, `name`, `unitPrice`, `status`, `category` | giá tại hóa đơn lưu snapshot |
| `service_orders` | `visitId`, `doctorId`, `status`, `orderedAt` | một order có nhiều item |
| `service_order_items` | `orderId`, `serviceId`, `status`, `externalOrderCode` | định danh gửi LIS nếu có |
| `diagnostic_results` | `serviceOrderItemId`, `valueText`, `valueNumeric`, `unit`, `referenceRange`, `status`, `source`, `verifiedBy`, `verifiedAt` | có thể nhiều giá trị trong kết quả phức hợp; mở rộng thành result_observations khi cần |
| `file_attachments` | `ownerType`, `ownerId`, `storageKey`, `mimeType`, `size`, `uploadedBy` | không public trực tiếp; quét tệp |

### Nhóm D — Kho & cấp phát

| Bảng | Các trường cốt lõi | Ràng buộc |
|---|---|---|
| `warehouses` | `branchId`, `code`, `name`, `status` | UQ(branchId,code) |
| `medicine_batches` | `warehouseId`, `medicineId`, `lotNumber`, `expiryDate`, `availableQty`, `status` | UQ(warehouseId,medicineId,lotNumber,expiryDate); availableQty ≥ 0 |
| `goods_receipts` | `warehouseId`, `receiptCode`, `status`, `receivedAt`, `createdBy` | confirmation là bất biến |
| `goods_receipt_items` | `receiptId`, `batchId`, `quantity`, `unitCost` | quantity > 0 |
| `stock_movements` | `warehouseId`, `batchId`, `type`, `quantityDelta`, `referenceType`, `referenceId`, `actorId` | append-only; signed +/- |
| `dispensations` | `prescriptionId`, `branchId`, `status`, `dispensedBy`, `dispensedAt` | một prescription có thể cấp nhiều lần |
| `dispensation_items` | `dispensationId`, `prescriptionItemId`, `quantity` | tổng cấp ≤ lượng kê nếu chính sách yêu cầu |
| `dispensation_allocations` | `dispensationItemId`, `batchId`, `quantity` | dùng để truy ngược số lô |

### Nhóm E — Tài chính

| Bảng | Các trường cốt lõi | Ràng buộc |
|---|---|---|
| `invoices` | `invoiceCode UQ`, `visitId UQ`, `branchId`, `subtotal`, `discount`, `totalAmount`, `status`, `issuedAt` | snapshot tiền khi issued |
| `invoice_items` | `invoiceId`, `sourceType`, `sourceId`, `descriptionSnapshot`, `unitPriceSnapshot`, `quantity`, `lineTotal` | chỉ 1 item/source trong invoice để chống phí trùng nếu nghiệp vụ đòi hỏi |
| `payments` | `invoiceId`, `amount`, `method`, `status`, `idempotencyKey UQ`, `receivedBy`, `receivedAt`, `reference` | không tự suy ra chuyển khoản đã thành công từ form |
| `refunds` | `paymentId`, `amount`, `status`, `reason`, `approvedBy` | hoàn <= đã thu hợp lệ |

### Nhóm F — Thông báo, tích hợp, nhật ký

| Bảng | Các trường cốt lõi | Ràng buộc |
|---|---|---|
| `notification_preferences` | `patientId`, `emailEnabled`, `reminderHours` | bệnh nhân chỉnh theo quyền |
| `notification_jobs` | `eventId`, `recipient`, `template`, `scheduledAt`, `status`, `retryCount`, `sentAt` | UQ(eventId,template,recipient) |
| `outbox_events` | `eventType`, `aggregateType`, `aggregateId`, `payload`, `processedAt` | commit cùng transaction nghiệp vụ |
| `integration_systems` | `code`, `systemType`, `status`, `credentialRef` | secret không lưu plaintext |
| `integration_messages` | `integrationSystemId`, `externalMessageId`, `receivedAt`, `status`, `errorCode`, `payloadRef` | UQ(integrationSystemId,externalMessageId) |
| `device_measurements` | `visitId`, `deviceCode`, `measurementType`, `value`, `unit`, `measuredAt`, `status` | cần nguồn và xác minh |
| `audit_logs` | `actorId`, `action`, `entityType`, `entityId`, `branchId`, `requestId`, `occurredAt`, `detailsRedacted` | append-only; không lưu bí mật và PHI thừa |

## 8.3. ERD lõi nghiệp vụ

```mermaid
erDiagram
    USERS ||--o| PATIENTS : account
    USERS ||--o| DOCTORS : account
    DOCTORS ||--o{ DOCTOR_SPECIALTIES : specializes
    SPECIALTIES ||--o{ DOCTOR_SPECIALTIES : includes
    BRANCHES ||--o{ DOCTOR_BRANCH_ASSIGNMENTS : employs
    DOCTORS ||--o{ DOCTOR_BRANCH_ASSIGNMENTS : works
    DOCTORS ||--o{ DOCTOR_SCHEDULES : schedule
    DOCTORS ||--o{ DOCTOR_TIME_OFF : unavailable
    PATIENTS ||--o{ APPOINTMENTS : books
    DOCTORS ||--o{ APPOINTMENTS : assigned
    BRANCHES ||--o{ APPOINTMENTS : hosts
    APPOINTMENTS o|--o| VISITS : creates
    PATIENTS ||--o{ VISITS : attends
    DOCTORS ||--o{ VISITS : examines
    BRANCHES ||--o{ VISITS : owns
    VISITS ||--o| MEDICAL_RECORDS : contains
    VISITS ||--o{ PRESCRIPTIONS : prescribes
    PRESCRIPTIONS ||--o{ PRESCRIPTION_ITEMS : items
    MEDICINES ||--o{ PRESCRIPTION_ITEMS : medicine
    VISITS ||--o{ SERVICE_ORDERS : orders
    SERVICE_ORDERS ||--o{ SERVICE_ORDER_ITEMS : items
    SERVICES ||--o{ SERVICE_ORDER_ITEMS : service
    SERVICE_ORDER_ITEMS ||--o{ DIAGNOSTIC_RESULTS : results
    VISITS ||--o| INVOICES : billed
    INVOICES ||--o{ INVOICE_ITEMS : lines
    INVOICES ||--o{ PAYMENTS : receipts
    VISITS ||--o{ FOLLOW_UP_PLANS : plans
```

## 8.4. ERD kho

```mermaid
erDiagram
    BRANCHES ||--o{ WAREHOUSES : owns
    WAREHOUSES ||--o{ MEDICINE_BATCHES : contains
    MEDICINES ||--o{ MEDICINE_BATCHES : stocks
    MEDICINE_BATCHES ||--o{ STOCK_MOVEMENTS : movements
    WAREHOUSES ||--o{ GOODS_RECEIPTS : receipts
    GOODS_RECEIPTS ||--o{ GOODS_RECEIPT_ITEMS : lines
    MEDICINE_BATCHES ||--o{ GOODS_RECEIPT_ITEMS : received
    PRESCRIPTIONS ||--o{ DISPENSATIONS : fulfills
    DISPENSATIONS ||--o{ DISPENSATION_ITEMS : lines
    PRESCRIPTION_ITEMS ||--o{ DISPENSATION_ITEMS : prescribed
    DISPENSATION_ITEMS ||--o{ DISPENSATION_ALLOCATIONS : allocations
    MEDICINE_BATCHES ||--o{ DISPENSATION_ALLOCATIONS : source
```

## 8.5. Các index và constraint đáng ưu tiên

```sql
-- Ví dụ minh họa PostgreSQL; bổ sung đúng schema do Prisma sinh ra.
CREATE UNIQUE INDEX IF NOT EXISTS ux_patient_code ON patients(patient_code);
CREATE INDEX IF NOT EXISTS ix_appointment_branch_start ON appointments(branch_id, starts_at);
CREATE INDEX IF NOT EXISTS ix_appointment_doctor_start ON appointments(doctor_id, starts_at);
CREATE INDEX IF NOT EXISTS ix_visit_patient_completed ON visits(patient_id, completed_at);
CREATE INDEX IF NOT EXISTS ix_stock_batch_expiry ON medicine_batches(warehouse_id, medicine_id, expiry_date);
CREATE UNIQUE INDEX IF NOT EXISTS ux_integration_message
  ON integration_messages(integration_system_id, external_message_id);
```

> **Lưu ý:** đây là SQL minh họa tên cột `snake_case`. Nếu dùng Prisma mặc định với `camelCase`, phải dùng `@map`/`@@map` hoặc điều chỉnh SQL cho đúng schema thực tế. Không chạy đoạn trên trước khi có bảng.

**Chống trùng lịch:** Không nên chỉ dùng UNIQUE(doctorId,startAt) vì có thể có khoảng giờ khác nhau bị chồng lấn. Thiết kế nên dùng `appointment_slots` cố định có sức chứa/`bookedCount` và transaction khóa slot, đồng thời kiểm tra ca làm/bác sĩ không có giao nhau giữa chi nhánh. Với lịch thời gian tùy ý, cân nhắc PostgreSQL exclusion constraint trên `tstzrange` và trạng thái hiệu lực, hoặc advisory locking theo doctor/time window.

**Chống kho âm:** `availableQty >= 0` (CHECK constraint tại DB) và thực hiện cập nhật nguyên tử `UPDATE ... SET available_qty = available_qty - requested WHERE id = ... AND available_qty >= requested`, kiểm tra số dòng ảnh hưởng. Cùng transaction phải tạo movement và phân bổ lô.

# 9. Thiết kế backend, frontend, API

## 9.1. Kiến trúc kỹ thuật

**Kiểu kiến trúc:** Modular Monolith + REST API + một PostgreSQL instance + worker xử lý job. Hệ thống gồm:

1. **Frontend:** React + Vite + TypeScript + React Router + Tailwind CSS; TanStack Query để đồng bộ dữ liệu server; React Hook Form + Zod cho form.
2. **Backend:** Node.js + Express + TypeScript; Prisma ORM; lớp route/controller/service/repository.
3. **Database:** PostgreSQL; migration, seed, transaction và audit.
4. **Worker:** tiến trình nhắc lịch/email và xử lý outbox; khởi đầu có thể dùng database-backed queue, khi cần dùng Redis/BullMQ.
5. **Storage:** lưu file đính kèm/PDF trên ổ cứng riêng trong demo hoặc object storage khi triển khai; luôn kiểm quyền khi download.
6. **Integrations:** adapter LIS/Device với Mock Server, xác thực machine-to-machine.

```mermaid
flowchart TB
    BROWSER[React Web App] -->|HTTPS REST JSON| API[Express API]
    API --> AUTH[Auth + RBAC + Branch Scope]
    AUTH --> MODULES[Business Modules]
    MODULES --> DB[(PostgreSQL)]
    MODULES --> FILES[(Private File Storage)]
    MODULES --> OUTBOX[(Outbox Events)]
    WORKER[Job Worker] --> OUTBOX
    WORKER --> MAIL[Email Provider]
    LIS[Mock LIS / Device] -->|Authenticated API| API
    API -->|Optional Service Order| LIS
```

## 9.2. Cấu trúc repository

```text
clinic-management-system/
├── README.md
├── docker-compose.yml
├── .gitignore
├── .env.example
├── docs/
│   ├── CMS_Full_Specification.md
│   ├── uml/
│   ├── erd/
│   ├── api/openapi.yaml
│   ├── wireframes/
│   ├── testing/
│   └── report/
├── frontend/
│   ├── src/
│   │   ├── app/                # Router, providers, permission gates
│   │   ├── layouts/            # PatientLayout, StaffLayout, AdminLayout
│   │   ├── components/         # Button, Modal, DataTable, StatusBadge
│   │   ├── features/
│   │   │   ├── auth/
│   │   │   ├── patients/
│   │   │   ├── doctors/
│   │   │   ├── branches/
│   │   │   ├── appointments/
│   │   │   ├── visits/
│   │   │   ├── medical-records/
│   │   │   ├── prescriptions/
│   │   │   ├── diagnostics/
│   │   │   ├── inventory/
│   │   │   ├── billing/
│   │   │   ├── reports/
│   │   │   └── follow-ups/
│   │   ├── lib/api.ts
│   │   ├── lib/auth.ts
│   │   ├── types/
│   │   └── main.tsx
│   └── package.json
├── backend/
│   ├── src/
│   │   ├── app.ts
│   │   ├── server.ts
│   │   ├── config/
│   │   ├── common/
│   │   │   ├── middleware/
│   │   │   ├── errors/
│   │   │   ├── validation/
│   │   │   ├── permissions/
│   │   │   └── audit/
│   │   ├── modules/
│   │   │   ├── auth/
│   │   │   ├── users/
│   │   │   ├── patients/
│   │   │   ├── doctors/
│   │   │   ├── branches/
│   │   │   ├── schedules/
│   │   │   ├── appointments/
│   │   │   ├── visits/
│   │   │   ├── medical-records/
│   │   │   ├── prescriptions/
│   │   │   ├── service-orders/
│   │   │   ├── results/
│   │   │   ├── inventory/
│   │   │   ├── dispensations/
│   │   │   ├── invoices/
│   │   │   ├── payments/
│   │   │   ├── reports/
│   │   │   ├── notifications/
│   │   │   ├── follow-ups/
│   │   │   └── integrations/
│   │   ├── jobs/
│   │   └── tests/
│   ├── prisma/
│   │   ├── schema.prisma
│   │   ├── migrations/
│   │   └── seed.ts
│   ├── .env.example
│   ├── package.json
│   └── tsconfig.json
└── mock-integrations/
    ├── lis-server/
    └── device-simulator/
```

**Cấu trúc mỗi backend module:**

```text
appointments/
├── appointment.routes.ts
├── appointment.controller.ts
├── appointment.service.ts
├── appointment.repository.ts
├── appointment.schema.ts
├── appointment.types.ts
└── appointment.service.test.ts
```

- **Routes:** định tuyến, middleware.
- **Controller:** request/response, không chứa logic nghiệp vụ phức tạp.
- **Service:** kiểm quyền nghiệp vụ, state transitions, transaction.
- **Repository:** truy vấn dữ liệu, không quyết định chính sách.
- **Schema:** validate request/DTO.
- **Tests:** unit và integration test.

## 9.3. Quy ước API

- Base URL: `/api/v1` (bảng bên dưới dùng dạng này để dễ versioning).
- JSON dạng `camelCase`, ID là UUID string, thời gian dùng ISO-8601 có timezone.
- List: `?page=1&pageSize=20&sortBy=createdAt&sortOrder=desc`.
- Filter: `branchId`, `from`, `to`, `doctorId`, `status`, `search` theo endpoint.
- `201` tạo mới; `200` đọc/sửa thành công; `204` thao tác không trả body.
- `400` JSON không hợp lệ, `401` thiếu xác thực, `403` không đủ quyền, `404` không thấy bản ghi hoặc cố tình không để lộ tài nguyên, `409` xung đột trạng thái/slot, `422` validate nghiệp vụ, `429` rate limit.
- Backend dùng error code ổn định như `SLOT_UNAVAILABLE`, `VISIT_ALREADY_EXISTS`, `INSUFFICIENT_STOCK`, `ACCESS_DENIED`.

**Response thành công:**

```json
{
  "success": true,
  "data": { "id": "8dbe0000-0000-4000-8000-000000000001", "status": "PENDING" },
  "message": "Tạo lịch hẹn thành công",
  "requestId": "req-demo-001"
}
```

**Response lỗi:**

```json
{
  "success": false,
  "error": {
    "code": "SLOT_UNAVAILABLE",
    "message": "Khung giờ này đã hết chỗ",
    "details": []
  },
  "requestId": "req-demo-002"
}
```

### Danh mục endpoint tối thiểu theo module

| Method | Endpoint | Quyền / Mục đích |
|---|---|---|
| POST | `/api/v1/auth/register` | PATIENT đăng ký |
| POST | `/api/v1/auth/login` | Đăng nhập |
| POST | `/api/v1/auth/logout` | Đăng xuất |
| POST | `/api/v1/auth/forgot-password` | Tạo reset request |
| POST | `/api/v1/auth/reset-password` | Reset qua token |
| GET | `/api/v1/auth/me` | Trả profile/role/scope |
| GET | `/api/v1/users` | ADMIN xem nhân viên |
| POST | `/api/v1/users` | ADMIN tạo tài khoản |
| PATCH | `/api/v1/users/:id/status` | ADMIN khóa/mở |
| PUT | `/api/v1/users/:id/roles` | ADMIN gán role, branch |
| GET | `/api/v1/branches` | Danh sách theo quyền; public projection riêng nếu cần |
| POST | `/api/v1/branches` | BRANCH_MANAGE |
| PATCH | `/api/v1/branches/:id` | BRANCH_MANAGE |
| GET | `/api/v1/specialties` | Danh mục |
| POST | `/api/v1/specialties` | ADMIN |
| GET | `/api/v1/doctors` | Tìm theo specialty, branch |
| GET | `/api/v1/doctors/:id` | Chi tiết public/staff theo quyền |
| POST | `/api/v1/doctors` | ADMIN |
| PATCH | `/api/v1/doctors/:id` | ADMIN |
| GET | `/api/v1/patients` | RECEPTIONIST theo branch/scope |
| POST | `/api/v1/patients` | RECEPTIONIST |
| GET | `/api/v1/patients/:id` | Scope bệnh nhân/nhân viên |
| PATCH | `/api/v1/patients/:id` | Scope + field-level |
| GET | `/api/v1/patients/:id/visits` | Patient-own hoặc bác sĩ được quyền |
| GET | `/api/v1/schedules` | Tra lịch |
| POST | `/api/v1/schedules` | SCHEDULE_MANAGE |
| PATCH | `/api/v1/schedules/:id` | SCHEDULE_MANAGE |
| POST | `/api/v1/time-off` | SCHEDULE_MANAGE |
| GET | `/api/v1/time-off/:id/impacted-appointments` | ADMIN xem ảnh hưởng |
| GET | `/api/v1/appointments/availability` | Tra slot hợp lệ |
| GET | `/api/v1/appointments` | Danh sách theo quyền |
| POST | `/api/v1/appointments` | PATIENT/RECEPTIONIST |
| GET | `/api/v1/appointments/:id` | Scope |
| POST | `/api/v1/appointments/:id/confirm` | RECEPTIONIST |
| POST | `/api/v1/appointments/:id/reschedule` | Quyền đổi lịch |
| POST | `/api/v1/appointments/:id/cancel` | Quyền hủy |
| POST | `/api/v1/appointments/:id/check-in` | RECEPTIONIST |
| GET | `/api/v1/visits` | Danh sách chờ và đã khám |
| POST | `/api/v1/visits/walk-in` | RECEPTIONIST |
| GET | `/api/v1/visits/:id` | DOCTOR/được quyền |
| POST | `/api/v1/visits/:id/start` | DOCTOR phân công |
| PATCH | `/api/v1/visits/:id/medical-record` | DOCTOR |
| POST | `/api/v1/visits/:id/medical-record/sign` | RECORD_SIGN |
| POST | `/api/v1/visits/:id/complete` | DOCTOR |
| GET | `/api/v1/medicines` | Danh mục thuốc |
| POST | `/api/v1/medicines` | ADMIN |
| POST | `/api/v1/visits/:id/prescriptions` | DOCTOR |
| PATCH | `/api/v1/prescriptions/:id` | DOCTOR sửa nháp |
| POST | `/api/v1/prescriptions/:id/sign` | PRESCRIPTION_SIGN |
| GET | `/api/v1/prescriptions/:id/pdf` | Xem/in có quyền |
| GET | `/api/v1/services` | Danh mục dịch vụ |
| POST | `/api/v1/services` | ADMIN |
| POST | `/api/v1/visits/:id/service-orders` | SERVICE_ORDER_CREATE |
| GET | `/api/v1/service-orders/:id` | Có quyền |
| POST | `/api/v1/service-order-items/:id/results` | LAB_RESULT_ENTER |
| POST | `/api/v1/results/:id/verify` | LAB_RESULT_VERIFY |
| GET | `/api/v1/warehouses` | Scope branch |
| POST | `/api/v1/warehouses` | INVENTORY_MANAGE |
| POST | `/api/v1/stock/receipts` | INVENTORY_MANAGE |
| POST | `/api/v1/stock/receipts/:id/confirm` | INVENTORY_MANAGE |
| GET | `/api/v1/stock/balances` | Tồn kho theo quyền |
| GET | `/api/v1/stock/movements` | Lịch sử kho |
| POST | `/api/v1/dispensations` | MEDICINE_DISPENSE |
| POST | `/api/v1/dispensations/:id/confirm` | MEDICINE_DISPENSE |
| POST | `/api/v1/invoices` | INVOICE_ISSUE |
| GET | `/api/v1/invoices` | Hóa đơn theo branch/scope |
| GET | `/api/v1/invoices/:id` | Chi tiết hóa đơn có quyền |
| POST | `/api/v1/invoices/:id/issue` | INVOICE_ISSUE |
| POST | `/api/v1/invoices/:id/payments` | PAYMENT_COLLECT; Idempotency-Key |
| POST | `/api/v1/payments/:id/refund` | Quyền hoàn tiền riêng |
| GET | `/api/v1/invoices/:id/pdf` | Có quyền |
| GET | `/api/v1/reports/overview` | REPORT_VIEW |
| GET | `/api/v1/reports/revenue` | REPORT_VIEW |
| GET | `/api/v1/reports/export` | REPORT_EXPORT |
| GET | `/api/v1/notification-preferences` | Chính mình |
| PATCH | `/api/v1/notification-preferences` | Chính mình |
| POST | `/api/v1/follow-ups` | DOCTOR |
| POST | `/api/v1/follow-ups/:id/accept` | Patient-own |
| POST | `/api/v1/follow-ups/:id/decline` | Patient-own |
| POST | `/api/v1/integrations/lis/results` | Chỉ LIS đã xác thực |
| POST | `/api/v1/integrations/devices/measurements` | Chỉ device gateway |
| GET | `/api/v1/integrations/messages` | INTEGRATION_MANAGE |

> **Phân quyền khác nhau theo endpoint**: GET `/doctors` cho bệnh nhân chỉ trả thông tin công khai; GET `/patients` không bao giờ là danh sách công khai.

## 9.4. Mẫu request trọng yếu

**Đặt lịch:**

```http
POST /api/v1/appointments
Content-Type: application/json
Idempotency-Key: demo-appointment-001
```

```json
{
  "patientId": "8dbe0000-0000-4000-8000-000000000001",
  "branchId": "8dbe0000-0000-4000-8000-000000000002",
  "doctorId": "8dbe0000-0000-4000-8000-000000000003",
  "slotId": "8dbe0000-0000-4000-8000-000000000004",
  "reason": "Khám sức khỏe tổng quát"
}
```

Backend lấy `startsAt`/`endsAt` chuẩn từ slot đã khóa thay vì tin hoàn toàn thời gian client tự gửi. Nếu không có slotId, nhận `startsAt`/`endsAt` và validate chặt.

**Ghi hồ sơ:**

```json
{
  "symptoms": "Đau đầu nhẹ theo mô tả giả lập",
  "clinicalFindings": "Dữ liệu mô phỏng",
  "diagnosis": "Chẩn đoán mô phỏng phục vụ kiểm thử",
  "treatmentPlan": "Theo dõi theo kịch bản demo",
  "version": 3
}
```

`version` dùng optimistic locking khi nhiều trình duyệt cùng sửa bản nháp.

**Kê đơn:**

```json
{
  "items": [
    {
      "medicineId": "8dbe0000-0000-4000-8000-000000000021",
      "dosage": "Theo dữ liệu demo",
      "route": "Theo dữ liệu demo",
      "frequency": "Theo dữ liệu demo",
      "durationDays": 3,
      "quantity": "3",
      "instructions": "Dữ liệu minh họa, không dùng điều trị"
    }
  ]
}
```

**Ghi thanh toán:**

```http
POST /api/v1/invoices/8dbe0000-0000-4000-8000-000000000040/payments
Idempotency-Key: demo-payment-001
Content-Type: application/json
```

```json
{
  "amount": "150000.00",
  "method": "CASH",
  "reference": "DEMO-CASH-001"
}
```

**Nhận kết quả LIS mock:**

```json
{
  "externalMessageId": "LIS-RESULT-00001",
  "externalOrderCode": "ORDER-DEMO-001",
  "resultCode": "DEMO_TEST",
  "value": "7.2",
  "unit": "demo_unit",
  "observedAt": "2026-12-01T09:40:00+07:00",
  "source": "MOCK_LIS"
}
```

LIS mock chỉ mô phỏng luồng dữ liệu. Không gán ý nghĩa y khoa hoặc ngưỡng bình thường cho các kết quả giả lập này.

## 9.5. Kiến trúc frontend và quản lý dữ liệu

- `AuthProvider`: lấy `/auth/me`, trạng thái login, quyền và chi nhánh.
- `ProtectedRoute`: ẩn trang không phù hợp **chỉ để UX**; backend vẫn bảo vệ thật.
- `apiClient`: base URL, xử lý lỗi, cookie/CSRF nếu dùng session.
- TanStack Query: cache danh sách, invalidate sau tạo/sửa; **không** coi cache là nguồn quyền.
- Form Zod: validate tại client và **lặp lại** tại server.
- DataTable chung: search, filters, paging, sorting, trạng thái loading/empty/error.
- Các actions rủi ro cao mở dialog xác nhận và thể hiện hậu quả.
- Không in PHI vào browser console, analytics, localStorage.

# 10. Danh mục màn hình và thành phần UI

## 10.1. Sitemap theo vai trò

| Mã | Route | Màn hình | Vai trò |
|---|---|---|---|
| UI-01 | `/login` | Đăng nhập | Tất cả |
| UI-02 | `/register` | Đăng ký | PATIENT |
| UI-03 | `/doctors` | Danh sách bác sĩ | Công khai (thông tin an toàn) |
| UI-04 | `/doctors/:id` | Chi tiết bác sĩ | Công khai |
| UI-05 | `/patient/book` | Đặt lịch | PATIENT |
| UI-06 | `/patient/appointments` | Lịch của tôi | PATIENT |
| UI-07 | `/patient/profile` | Hồ sơ cá nhân | PATIENT |
| UI-08 | `/patient/medical-history` | Lịch sử khám được công bố | PATIENT |
| UI-09 | `/reception/dashboard` | Hôm nay | RECEPTIONIST |
| UI-10 | `/reception/patients` | Tìm/tạo bệnh nhân | RECEPTIONIST |
| UI-11 | `/reception/appointments` | Quản lý lịch hẹn | RECEPTIONIST |
| UI-12 | `/reception/check-in` | Tiếp nhận | RECEPTIONIST |
| UI-13 | `/doctor/dashboard` | Lịch khám bác sĩ | DOCTOR |
| UI-14 | `/doctor/queue` | Danh sách chờ | DOCTOR |
| UI-15 | `/doctor/visits/:id` | Phiếu khám | DOCTOR |
| UI-16 | `/doctor/visits/:id/prescription` | Kê đơn | DOCTOR |
| UI-17 | `/doctor/visits/:id/services` | Chỉ định dịch vụ | DOCTOR |
| UI-18 | `/diagnostics/results` | Kết quả chờ nhập/duyệt | Có quyền nghiệp vụ |
| UI-19 | `/cashier/invoices` | Hóa đơn | CASHIER |
| UI-20 | `/cashier/invoices/:id` | Chi tiết/thu tiền | CASHIER |
| UI-21 | `/admin/dashboard` | KPI, biểu đồ | ADMIN |
| UI-22 | `/admin/users` | Tài khoản/phân quyền | ADMIN |
| UI-23 | `/admin/doctors` | Bác sĩ/chuyên khoa | ADMIN |
| UI-24 | `/admin/branches` | Chi nhánh | ADMIN |
| UI-25 | `/admin/schedules` | Lịch làm/nghỉ | ADMIN |
| UI-26 | `/inventory/medicines` | Danh mục thuốc | Có quyền |
| UI-27 | `/inventory/receipts` | Nhập kho | Có quyền |
| UI-28 | `/inventory/stock` | Tồn theo lô | Có quyền |
| UI-29 | `/pharmacy/dispensations` | Cấp thuốc | Có quyền chuyên môn |
| UI-30 | `/reports` | Báo cáo và Excel | Có quyền |
| UI-31 | `/patient/follow-ups` | Tái khám | PATIENT |
| UI-32 | `/admin/notifications` | Cấu hình nhắc lịch | ADMIN |
| UI-33 | `/admin/integrations` | Quản lý LIS/thiết bị | ADMIN có quyền |

**Quy ước điều hướng:** menu staff có `branch selector` chỉ hiện những chi nhánh người dùng được phép; bộ lọc branch không được mở rộng quyền backend. Giao diện PATIENT tối ưu mobile; staff tối ưu desktop/tablet.

## 10.2. Thiết kế khung giao diện (wireframe bằng văn bản)

**Trang bác sĩ đang khám:**

```text
+--------------------------------------------------------------------------+
| Logo CMS     Chi nhánh: Hà Nội      Bác sĩ: Demo                Avatar     |
+-------------+------------------------------------------------------------+
| LỊCH KHÁM   | BỆNH NHÂN: BN00001                 LƯỢT: VISIT-0001         |
| 08:00 BN01  | --------------------------------------------------------   |
| 08:30 BN02  | [Thông tin] [Tiền sử] [Khám bệnh] [Dịch vụ] [Đơn thuốc]     |
| 09:00 BN03  | Lý do khám: [..........................................]   |
|             | Triệu chứng: [........................................]    |
|             | Khám lâm sàng: [......................................]    |
|             | Chẩn đoán: [..........................................]    |
|             | Hướng điều trị: [.....................................]    |
|             |                                                            |
|             | [Lưu nháp] [Chỉ định dịch vụ] [Kê đơn] [Hoàn tất]           |
+-------------+------------------------------------------------------------+
```

**Trang lễ tân:**

```text
[Ngày] [Chi nhánh] [Bác sĩ] [Trạng thái] [Tìm bệnh nhân] [+ Đặt lịch]
-------------------------------------------------------------------------
| Giờ  | Mã lịch | Bệnh nhân | Bác sĩ | Trạng thái | Hành động          |
| 09:00| AP0001  | BN Demo   | BS01   | CONFIRMED  | [Check-in] [Đổi]   |
| 09:30| AP0002  | BN Demo 2 | BS01   | PENDING    | [Xác nhận]         |
-------------------------------------------------------------------------
```

**Dashboard Admin:** KPI lịch hẹn, lượt khám, số bệnh nhân mới, tiền thực thu, công nợ; biểu đồ ngày/tháng; bộ lọc chi nhánh và khoảng thời gian; danh sách lịch hẹn gần nhất.

## 10.3. Các component dùng chung

- `AppShell`, `Sidebar`, `Topbar`, `BranchSelector`, `Breadcrumb`.
- `RoleGuard`, `PermissionGuard`, `SessionExpiredDialog`.
- `DataTable`, `SearchInput`, `DateRangePicker`, `StatusBadge`, `Pagination`.
- `ConfirmDialog`, `Toast`, `FormField`, `LoadingSkeleton`, `EmptyState`.
- `PatientSearch`, `DoctorSelector`, `ScheduleCalendar`, `AvailabilitySlots`.
- `MoneyDisplay`, `MedicationPicker`, `InvoicePreview`, `PrintableDocument`.
- `AuditHistoryPanel`, `AttachmentUploader`, `ErrorBoundary`.

**Accessibility:** nhãn form rõ, tương phản đủ, tab/keyboard hoạt động, lỗi đọc được, không dùng màu làm chỉ dấu trạng thái duy nhất.

# 11. Hướng dẫn khởi tạo dự án

> Các lệnh bên dưới là gợi ý cho môi trường phát triển mới. Phiên bản Node/Prisma có thể thay đổi; kiểm tra tài liệu chính thức tương ứng với phiên bản đã cài và commit file lock. **Không dán mật khẩu production vào Git.**

## 11.1. Chuẩn bị phần mềm

- Git; Node.js phiên bản LTS tương thích với các package đã chọn; npm.
- Docker Desktop hoặc Docker Engine + Compose (chạy PostgreSQL local).
- VS Code; PostgreSQL client (pgAdmin hoặc DBeaver); Postman/Bruno.
- Draw.io để vẽ UML và Figma để thiết kế giao diện.

Kiểm tra:

```bash
node --version
npm --version
git --version
docker --version
docker compose version
```

## 11.2. Tạo project và frontend

```bash
mkdir clinic-management-system
cd clinic-management-system
git init
npm create vite@latest frontend -- --template react-ts
cd frontend
npm install
npm install react-router-dom @tanstack/react-query axios react-hook-form zod @hookform/resolvers
npm install lucide-react recharts
npm install tailwindcss @tailwindcss/vite
cd ..
```

Cấu hình Tailwind theo hướng dẫn của phiên bản đã cài. Dùng `VITE_API_URL=http://localhost:4000/api/v1` ở `.env.development` (Vite chỉ public những biến có tiền tố VITE_; **không đặt secret ở đó**).

## 11.3. Khởi tạo backend

```bash
mkdir backend
cd backend
npm init -y
npm install express cors helmet cookie-parser dotenv zod argon2 @prisma/client
npm install -D typescript tsx @types/node @types/express @types/cors @types/cookie-parser prisma vitest supertest @types/supertest
npx tsc --init
npx prisma init --datasource-provider postgresql
```

Tạo `package.json` scripts (chỉnh sửa phần `scripts` đang có):

```json
{
  "scripts": {
    "dev": "tsx watch src/server.ts",
    "build": "tsc -p tsconfig.json",
    "start": "node dist/server.js",
    "test": "vitest run",
    "db:migrate": "prisma migrate dev",
    "db:seed": "prisma db seed",
    "db:studio": "prisma studio"
  }
}
```

**Ghi chú phiên bản:** Prisma có thay đổi cách cấu hình datasource/CLI giữa các major version. Sau khi cài, chỉnh `prisma.config.ts`, `schema.prisma` và driver adapter theo yêu cầu chính thức của phiên bản đang sử dụng. Ví dụ lệnh ở đây là khung để bắt đầu, không phải bản source tự chạy độc lập cho mọi phiên bản.

## 11.4. Docker Compose cho PostgreSQL local

Tại thư mục root tạo `docker-compose.yml`:

```yaml
services:
  postgres:
    image: postgres:17
    container_name: cms-postgres
    restart: unless-stopped
    environment:
      POSTGRES_USER: cms_dev
      POSTGRES_PASSWORD: cms_dev_password_change_me
      POSTGRES_DB: cms
    ports:
      - "127.0.0.1:5432:5432"
    volumes:
      - cms_pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U cms_dev -d cms"]
      interval: 5s
      timeout: 5s
      retries: 10
volumes:
  cms_pgdata:
```

Chạy:

```bash
docker compose up -d
docker compose ps
```

Tại `backend/.env`:

```dotenv
NODE_ENV=development
PORT=4000
DATABASE_URL="postgresql://cms_dev:cms_dev_password_change_me@localhost:5432/cms?schema=public"
FRONTEND_URL="http://localhost:5173"
SESSION_SECRET="REPLACE_WITH_RANDOM_32_PLUS_BYTES"
EMAIL_MODE="mock"
FILE_STORAGE_DIR="./private_uploads"
```

`.env` phải được `.gitignore`; `.env.example` chỉ chứa biến và giá trị giả. Chưa cần SMTP thật ở P0.

## 11.5. Khởi tạo schema database từng lớp

**Thứ tự migration nên làm:**

1. `users`, `branches`, `role_assignments`, `patients`, `doctors`, `specialties`.
2. `doctor_schedules`, `doctor_time_off`, `appointment_slots`, `appointments`, `visits`.
3. `medical_records`, `prescriptions`, `prescription_items`.
4. `services`, `service_orders`, `results`.
5. `invoices`, `invoice_items`, `payments`.
6. `warehouses`, `medicine_batches`, `stock_movements`, `dispensations`.
7. `notification_jobs`, `follow_up_plans`, `integration_messages`, `audit_logs`.

Ví dụ **một phần** Prisma schema để minh họa cách thiết kế PK/FK, không phải schema đủ cho toàn hệ thống:

```prisma
model Branch {
  id           String        @id @default(uuid())
  code         String        @unique
  name         String
  timezone     String        @default("Asia/Ho_Chi_Minh")
  appointments Appointment[]
  createdAt    DateTime      @default(now())
}

model Patient {
  id           String        @id @default(uuid())
  patientCode  String        @unique
  fullName     String
  dateOfBirth  DateTime?
  phone        String?
  appointments Appointment[]
  createdAt    DateTime      @default(now())
}

model Doctor {
  id           String        @id @default(uuid())
  doctorCode   String        @unique
  fullName     String
  appointments Appointment[]
  createdAt    DateTime      @default(now())
}

model Appointment {
  id         String    @id @default(uuid())
  patientId  String
  doctorId   String
  branchId   String
  startsAt   DateTime
  endsAt     DateTime
  status     String    @default("PENDING")
  patient    Patient   @relation(fields: [patientId], references: [id])
  doctor     Doctor    @relation(fields: [doctorId], references: [id])
  branch     Branch    @relation(fields: [branchId], references: [id])
  createdAt  DateTime  @default(now())
  updatedAt  DateTime  @updatedAt

  @@index([doctorId, startsAt])
  @@index([branchId, startsAt])
}
```

Sau khi hoàn chỉnh schema:

```bash
cd backend
npx prisma format
npx prisma migrate dev --name initial_domain
npx prisma generate
```

**Không khởi tạo tất cả các bảng ngay bằng cách copy schema chưa kiểm tra.** Từng migration cần được chạy và kiểm tra quan hệ trước khi sang module mới.

## 11.6. Khởi tạo API nền tảng

Ví dụ `backend/src/app.ts` (chỉ là skeleton, cần bổ sung middleware auth, validation, error handling và route của dự án):

```ts
import express from "express";
import helmet from "helmet";
import cors from "cors";

export const app = express();
app.use(helmet());
app.use(cors({ origin: process.env.FRONTEND_URL, credentials: true }));
app.use(express.json({ limit: "1mb" }));

app.get("/api/v1/health", (_req, res) => {
  res.json({ success: true, data: { status: "ok" } });
});
```

Ví dụ `backend/src/server.ts`:

```ts
import "dotenv/config";
import { app } from "./app";

const port = Number(process.env.PORT || 4000);
app.listen(port, () => console.log(`API listening on :${port}`));
```

Cấu hình TypeScript module phù hợp với Node phiên bản đang sử dụng; với ESM cần xử lý import path khi compile. Khi dev có thể dùng `tsx`, khi production nên kiểm thử riêng `npm run build && npm start`.

Chạy dev hai terminal:

```bash
# Terminal 1
cd backend && npm run dev

# Terminal 2
cd frontend && npm run dev
```

Truy cập frontend ở địa chỉ Vite in ra, API health tại `http://localhost:4000/api/v1/health`.

## 11.7. Quy ước Git

- `main`: nhánh ổn định; `develop`: tích hợp (tùy nhóm).
- Feature branch: `feature/auth`, `feature/appointments`, `feature/inventory`.
- Mỗi pull request yêu cầu: thay đổi FR/UC/API nếu có, migration nếu đổi schema, test case và ảnh màn hình nếu đổi UI.
- Không commit `.env`, dữ liệu bệnh nhân thật, private key, tệp PDF bệnh án thật.
- Nên thiết lập CI: `npm ci`, typecheck, lint, test và build từng app.

# 12. Lập trình các quy trình khó

## 12.1. Đặt lịch không trùng khi nhiều người đặt cùng lúc

**Sai thường gặp:** frontend hiển thị slot trống rồi POST thẳng vào database; hai người cùng thấy slot trống và cùng tạo thành công.

**Giải pháp đề xuất:** dùng bảng slot có `capacity`, `bookedCount`; khi đặt trong một transaction:

```text
BEGIN TRANSACTION
  1. Xác thực + kiểm quyền bệnh nhân, bác sĩ, branch.
  2. Khóa slot hoặc cập nhật nguyên tử theo điều kiện:
     UPDATE appointment_slots
     SET booked_count = booked_count + 1
     WHERE id = :slotId AND booked_count < capacity;
  3. Nếu không cập nhật được dòng nào → SLOT_UNAVAILABLE.
  4. Kiểm bác sĩ không có lịch công tác/xung đột ở các branch khác.
  5. INSERT appointment.
  6. INSERT outbox_event APPOINTMENT_CREATED.
COMMIT
```

Cần kiểm tra **toàn bộ điều kiện phụ thuộc thời gian trong cùng chiến lược khóa/transaction**. Riêng trường hợp bác sĩ đặt ca làm tại hai chi nhánh cũng cần transaction/constraint tương ứng, không để lịch công tác thay đổi đồng thời phá bất biến. Khi hủy/đổi lịch cần điều chỉnh `bookedCount` đúng trong transaction; job bảo trì có thể so sánh lại `bookedCount` với số appointment hiệu lực.

**Bổ sung:** gửi `Idempotency-Key` để người dùng click hai lần không tạo hai lịch, độc lập với vấn đề hai bệnh nhân khác nhau đặt cùng slot.

## 12.2. Chống check-in hai lần

- Unique nullable `visits.appointmentId`; nếu có appointment thì luôn check trên DB.
- Transaction kiểm tra trạng thái Appointment hợp lệ, `UPDATE appointment` sang CHECKED_IN, `INSERT Visit WAITING`.
- Nếu conflict unique, trả lỗi 409 hoặc trả Visit đã tồn tại nếu chính là request lặp với cùng idempotency key.

## 12.3. Tính hóa đơn và thanh toán

**Công thức:**

```text
lineTotal = quantity × unitPriceSnapshot
subtotal  = SUM(lineTotal)
total     = MAX(0, subtotal - discountAmount + taxAmountIfApplicable)
received  = SUM(payments status=SUCCEEDED) - SUM(refunds đã hoàn hợp lệ)
outstanding = MAX(0, total - received)
```

Trong demo không nên tự giả định thuế suất/phí bảo hiểm; hãy đặt `taxAmount` = 0 nếu không triển khai chính sách tương ứng. Số tiền được tính toán backend và đối chiếu với cơ sở dữ liệu, không tin `totalAmount` do frontend gửi.

**Đảm bảo không thu trùng:** unique `idempotencyKey` cho payment; backend kiểm transaction/đối soát khoản còn phải thu. Với chuyển khoản thủ công, chỉ ghi SUCCEEDED sau khi thu ngân xác nhận đã nhận tiền; không suy ra thành công từ hành động bấm nút.

## 12.4. Nhập kho và xuất thuốc

**Nhập kho:** phiếu `DRAFT` → kiểm dữ liệu và quyền → xác nhận → tăng tồn từng lô, ghi `stock_movements` số dương.

**Xuất kho:**

```text
BEGIN TRANSACTION
  1. Xác nhận prescription SIGNED và người cấp phát có quyền.
  2. Kiểm số lượng chưa cấp còn đủ theo đơn.
  3. Chọn lô phù hợp FEFO; lô chưa hết hạn tại thời điểm cấp.
  4. Với từng batch: giảm tồn bằng update nguyên tử có điều kiện.
  5. Nếu không đủ tồn ở bất kỳ batch nào → ROLLBACK.
  6. Tạo dispensation, dispensation_items, allocations.
  7. Tạo stock_movements âm, audit.
COMMIT
```

Giá và đơn vị tính của thuốc cần quy đổi nhất quán giữa đơn thuốc, lô kho và dòng cấp phát. Với hoàn trả, tạo giao dịch mới thay vì xóa/đảo âm giao dịch cũ không có lý do.

## 12.5. Kiểm quyền dữ liệu đa chi nhánh

**Sai:** chỉ kiểm tra `req.user.role === 'RECEPTIONIST'` rồi cho đọc `/patients/:id` của mọi chi nhánh.

**Đúng:** `authorize('PATIENT_ADMIN_READ', {branchId, patientId})` và kiểm tra quan hệ giữa người dùng, chi nhánh, nghiệp vụ, field cần trả; không lấy dữ liệu y tế quá mức cần thiết. Với hồ sơ bệnh nhân toàn hệ thống, cần quy định rõ trường nào cho lễ tân xem ở chi nhánh có lượt khám và khi nào có quyền truy cập.

## 12.6. Tránh lỗi giá dịch vụ thay đổi làm sai hóa đơn cũ

Khi lập invoice, copy `service.name`, `service.price`, `prescription_item`, `quantity` sang `invoice_items.descriptionSnapshot`, `unitPriceSnapshot`, `quantity`, `lineTotal`. Không JOIN danh mục giá hiện tại để tính lại hóa đơn đã ISSUED. Có thể hỗ trợ thay đổi giá dịch vụ theo thời gian bằng bảng price history nếu cần.

## 12.7. Ngăn sửa bệnh án đã chốt

- Hồ sơ DRAFT được bác sĩ được phân công sửa, kiểm optimistic version (`version`).
- Khi SIGNED, lưu `signedBy`, `signedAt`, `version` và bản snapshot.
- Yêu cầu sửa sau SIGNED tạo `medical_record_revisions`/amendment có lý do, người duyệt phù hợp, không ghi đè mất lịch sử.

# 13. PDF, Excel, email, tái khám, kho và tích hợp

## 13.1. PDF đơn thuốc

Các phần: header chi nhánh, logo (nếu có), mã đơn, họ tên/mã bệnh nhân theo quyền, ngày khám, bác sĩ, bảng thuốc (tên, dạng, liều, tần suất, lượng, hướng dẫn), lưu ý, thời điểm chốt, vùng xác nhận theo thiết kế. Mọi tài liệu phục vụ đồ án gắn dấu **"BẢN MÔ PHỎNG - KHÔNG SỬ DỤNG ĐỂ ĐIỀU TRỊ"** khi cần để tránh nhầm với đơn thật. Chỉ in đơn SIGNED trong nghiệp vụ thông thường, bản DRAFT phải có nhãn riêng.

## 13.2. PDF hóa đơn

Header chi nhánh, mã hóa đơn, ngày phát hành, mã bệnh nhân/mã lượt khám, danh sách dòng phí, tiền tạm tính, giảm giá, tổng, đã thu, còn phải trả, phương thức thanh toán. Cần phân biệt **phiếu thu/hóa đơn demo** và hóa đơn điện tử hợp lệ theo pháp luật nếu chưa tích hợp thật.

## 13.3. Excel

- Bảng xuất: `Patients`, `Appointments`, `Visits`, `Revenue`, `Inventory` theo quyền.
- Header tiếng Việt; metadata khoảng thời gian, chi nhánh, người xuất, thời điểm xuất.
- File `.xlsx`; cột ngày thống nhất định dạng; tiền là số có format, không dùng text khiến không tính được.
- Chống formula injection; giới hạn dòng, timeout, ghi audit; export lớn dùng background job nếu cần.

## 13.4. Email và worker

**Sự kiện:** `APPOINTMENT_CREATED`, `APPOINTMENT_CONFIRMED`, `APPOINTMENT_RESCHEDULED`, `APPOINTMENT_CANCELLED`, `APPOINTMENT_REMINDER_DUE`, `FOLLOW_UP_PROPOSED`.

```mermaid
flowchart LR
    A[Business transaction] --> B[Outbox event in DB]
    B --> C[Background worker]
    C --> D{Gửi email}
    D -->|Thành công| E[Job SENT]
    D -->|Lỗi tạm thời| F[RETRY với backoff]
    F --> C
    D -->|Lỗi vĩnh viễn| G[FAILED + hiển thị quản trị]
```

**Mẫu email tối thiểu:** tên phòng khám/chi nhánh, mã lịch, ngày/giờ, tên bác sĩ (nếu phù hợp), hướng dẫn xác nhận hoặc đổi lịch, thông tin liên hệ. Không đưa chẩn đoán/kết quả xét nghiệm trong email thường.

## 13.5. Tái khám tự động

- Bác sĩ tạo `follow_up_plan` với khoảng ngày, bác sĩ/chuyên khoa mong muốn.
- Bệnh nhân nhận đề xuất, chọn slot và đồng ý; **hoặc** đã bật tùy chọn tự đặt trước đó.
- Worker chạy định kỳ tìm plan đủ điều kiện, gọi **cùng service đặt lịch** với request từ người dùng; dùng idempotency key `follow-up:<planId>`.
- Nếu không có slot: đưa trạng thái chờ và thông báo, không tự chọn giờ bất tiện không được bệnh nhân chấp nhận.
- Consent cần ghi bản ghi đồng ý, thời gian và trạng thái thu hồi; thao tác thu hồi ảnh hưởng các booking tương lai theo chính sách.

## 13.6. LIS Mock — hợp đồng tích hợp

**Outbound:** Khi bác sĩ chỉ định xét nghiệm, API tạo `service_order_item` có mã tham chiếu. Adapter gửi yêu cầu đến mock LIS (nếu bật). Nếu gửi thất bại, đánh dấu RETRYING và giữ đơn trong hệ thống.

**Inbound:** Mock LIS gọi endpoint nhận kết quả có `externalMessageId`, `externalOrderCode`, code xét nghiệm, giá trị, đơn vị, thời điểm; backend xác thực nguồn, validate, map dữ liệu sang `diagnostic_results`, yêu cầu duyệt.

**Tình huống cần mô phỏng:** hợp lệ, gửi lặp 2 lần, sai mã chỉ định, sai đơn vị, kết quả đến muộn, lỗi HTTP 500 rồi retry.

**Khuyến nghị hướng phát triển:** adapter ánh xạ các tài nguyên FHIR như `ServiceRequest`, `Observation`, `DiagnosticReport`; không tuyên bố tương thích FHIR chỉ vì dùng tên trường tương tự. DICOM/DICOMweb là phần riêng cho hình ảnh y tế nếu triển khai về sau.

## 13.7. Thiết bị đo sinh tồn Mock

Mock Device gửi `{deviceCode, visitId, measurementType, value, unit, measuredAt}`. API xác thực machine-to-machine, kiểm mapping visit, whitelist loại đo/đơn vị, chống gửi trùng, lưu `source=MOCK_DEVICE`, đặt trạng thái chờ xác minh trước khi nhập chính thức vào phiếu khám nếu chính sách yêu cầu.

## 13.8. Khả năng hoạt động khi hệ thống ngoài lỗi

- Email lỗi → booking vẫn hợp lệ; job retry/hiển thị lỗi.
- LIS lỗi → phiếu chỉ định vẫn tồn tại; cho nhập thủ công có kiểm soát.
- PDF thất bại → giữ nguyên dữ liệu đơn/hóa đơn, cho tải lại.
- Worker ngừng → sự kiện chờ trong outbox, chạy tiếp sau khi worker khởi động.
- Không retry mù các POST có tác động bên ngoài nếu chưa có idempotency/correlation ID.

# 14. Kiểm thử và tiêu chí nghiệm thu

## 14.1. Phân loại kiểm thử

- **Unit:** các hàm validate, trạng thái, tính tiền, tính tồn khả dụng.
- **Integration:** API + PostgreSQL thật trong môi trường test, transaction, quyền theo branch.
- **E2E:** Playwright theo 5 vai trò; quy trình từ đặt lịch đến thống kê.
- **Concurrency:** hai request cùng slot/lot/payment.
- **Security:** 401/403, IDOR, field-level access, upload, SQL injection, XSS, CSRF, export.
- **Resilience:** email lỗi, LIS timeout, worker retry.
- **Performance:** ghi rõ cấu hình phần cứng, số user, thời gian đo; không tuyên bố vượt chỉ tiêu nếu chưa test.

## 14.2. Test Case toàn hệ thống

| ID | Kịch bản | Kết quả mong đợi | Mức |
|---|---|---|---|
| TC-01 | Đăng nhập đúng | 200, có phiên | P0 |
| TC-02 | Mật khẩu sai | 401, không tiết lộ tài khoản | P0 |
| TC-03 | RECEPTIONIST gọi API ký đơn | 403 | P0 |
| TC-04 | PATIENT sửa patientId để xem người khác | 403/404, không lộ dữ liệu | P0 |
| TC-05 | Tạo bệnh nhân | Sinh patientCode UQ | P0 |
| TC-06 | Tìm bệnh nhân theo mã | Đúng hồ sơ | P0 |
| TC-07 | Thêm bác sĩ + chuyên khoa | Lưu quan hệ | P0 |
| TC-08 | Tạo lịch làm bác sĩ trùng hai branch | Từ chối/điều phối hợp lệ | P0 |
| TC-09 | Đặt slot trống | 201 appointment | P0 |
| TC-10 | Hai request cùng slot capacity=1 | Tối đa một booking thành công | P0 |
| TC-11 | Đặt lịch trong thời gian nghỉ | 409/422 | P0 |
| TC-12 | Đặt giờ quá khứ | 422 | P0 |
| TC-13 | Đổi lịch hợp lệ | Slot cũ/mới và history đúng | P1 |
| TC-14 | Check-in hợp lệ | Tạo Visit WAITING | P0 |
| TC-15 | Check-in 2 lần | Không tạo 2 Visit | P0 |
| TC-16 | Tạo Visit walk-in | Không cần appointment | P0 |
| TC-17 | Bác sĩ khác truy cập Visit không được phân công | Bị từ chối theo policy | P0 |
| TC-18 | Lưu bệnh án nháp | Đúng Visit và doctor | P0 |
| TC-19 | Sửa record đã ký trái quy tắc | Bị chặn | P0 |
| TC-20 | Kê và ký đơn thuốc | SIGNED, chưa trừ tồn | P0 |
| TC-21 | Chỉ định dịch vụ | Tạo order/item đúng | P1 |
| TC-22 | Nhập kết quả dịch vụ | Chưa VERIFIED | P1 |
| TC-23 | Duyệt kết quả đúng quyền | VERIFIED | P1 |
| TC-24 | Gửi kết quả LIS 2 lần cùng ID | Chỉ một kết quả có hiệu lực | P2 |
| TC-25 | LIS trả mã order sai | Bị từ chối/quarantine | P2 |
| TC-26 | Nhập kho đúng lô | Tồn tăng và có movement | P1 |
| TC-27 | Cấp phát lô còn hạn | Tồn giảm, truy vết lô | P1 |
| TC-28 | Cấp phát vượt tồn | Rollback, không kho âm | P1 |
| TC-29 | Cấp phát lô hết hạn | Từ chối | P1 |
| TC-30 | Hai yêu cầu xuất cùng lô đồng thời | Không vượt tồn | P1 |
| TC-31 | Lập hóa đơn từ dịch vụ | Tổng snapshot đúng | P0 |
| TC-32 | Lập lại invoice chính của Visit | Chặn trùng | P0 |
| TC-33 | Thanh toán một phần | outstanding đúng | P0 |
| TC-34 | Gửi lại payment cùng Idempotency-Key | Không thu trùng | P0 |
| TC-35 | Thay giá danh mục sau phát hành | Invoice cũ không đổi | P0 |
| TC-36 | PDF đơn tiếng Việt | Không lỗi dấu, đúng nội dung | P1 |
| TC-37 | PDF hóa đơn theo chi nhánh | Đúng tên và tổng tiền | P1 |
| TC-38 | Excel xuất theo lọc | Không lộ branch ngoài scope | P1 |
| TC-39 | Email thành công | Job SENT | P1 |
| TC-40 | Email lỗi tạm thời | Retry, appointment còn | P1 |
| TC-41 | Tạo tái khám chưa có consent tự động | Không tự đặt | P2 |
| TC-42 | Tái khám có consent, có slot | Đặt đúng slot, không trùng | P2 |
| TC-43 | Device gửi sai unit | Validate/reject | P2 |
| TC-44 | Nhân viên branch A xem dữ liệu nhạy cảm branch B | 403/404 | P0 |
| TC-45 | Dashboard revenue theo ngày | Bằng tổng tiền thực thu trừ hoàn | P0 |
| TC-46 | Restore database backup demo | Khôi phục và kiểm checksum mẫu | P1 |

## 14.3. Tiêu chí nghiệm thu theo module

- **Auth:** đăng nhập/đăng xuất, role/permission, password hash, session expiry, unauthorized tests đạt.
- **Patients/Doctors/Branches:** CRUD mềm, validation, phân trang, branch scope.
- **Appointments:** availability đúng, transaction an toàn, đổi/hủy và lưu lịch sử.
- **Visits/Records:** check-in một lần, record gắn visit, chỉ người có quyền chỉnh.
- **Prescriptions:** có thuốc và trường bắt buộc, ký đơn, không giảm tồn.
- **Services/LIS:** order → result → verify, xử lý kết quả trùng.
- **Inventory:** nhập–xuất–tồn đúng và audit theo lô.
- **Billing:** item snapshot, thanh toán nhiều lần, báo cáo đối soát.
- **Reports:** lọc branch/time, định nghĩa doanh thu nhất quán.
- **Notifications:** worker gửi, retry không ảnh hưởng booking.
- **Export:** PDF/Excel đúng dữ liệu, bảo vệ quyền và tệp.
- **Demo:** một luồng E2E thành công và tối thiểu hai tình huống lỗi nghiệp vụ.

## 14.4. Ma trận truy vết mẫu

| Yêu cầu | Use Case | API chính | Test |
|---|---|---|---|
| FR-01.08 | UC-01/02 | Auth + middleware | TC-03,04,44 |
| FR-04.05/07 | UC-06 | POST /appointments | TC-09,10,11 |
| FR-05.02/08 | UC-10 | POST /appointments/:id/check-in | TC-14,15 |
| FR-06.08 | UC-14 | POST /visits/:id/prescriptions | TC-20 |
| FR-09.05/07 | UC-17 | POST /results/:id/verify | TC-22,23 |
| FR-14.10/11 | UC-24 | POST /dispensations/:id/confirm | TC-27,28,30 |
| FR-07.05 | UC-19 | POST /invoices/:id/payments | TC-33,34 |
| FR-13.07 | UC-27 | Background worker | TC-39,40 |
| FR-16.07 | UC-28 | POST /follow-ups/:id/accept | TC-41,42 |
| FR-17.03 | UC-29 | POST /integrations/lis/results | TC-24,25 |

# 15. Kế hoạch 16 tuần và chia việc

> Giả định nhóm 4 thành viên. Nếu một người thực hiện, giữ P0 làm đích bắt buộc và mô phỏng một phần P1/P2 theo thời hạn.

| Tuần | Công việc | Deliverable/Definition of Done |
|---|---|---|
| 1 | Khảo sát nghiệp vụ, scope, actors | Mô tả bài toán, RACI vai trò |
| 2 | FR, NFR, BR, Use Case inventory | SRS v1; traceability ban đầu |
| 3 | Use Case/Activity/Sequence/State | Bộ UML được review |
| 4 | ERD, kiến trúc, API, wireframe | SDD và schema phác thảo |
| 5 | Git, Docker, auth, RBAC | Login, phân quyền, seed |
| 6 | Branch, patients, doctors, specialties | CRUD với test và phân trang |
| 7 | Schedules, slot, appointments | Booking không overbook |
| 8 | Reception, check-in, visit queue | Lễ tân tạo lượt khám |
| 9 | Doctor, medical record, prescription | Lưu khám và ký đơn |
| 10 | Services, orders, result workflow | Chỉ định và duyệt kết quả |
| 11 | Billing, payments, dashboard | Demo E2E P0 |
| 12 | Inventory, batches, dispensing | Nhập/xuất/tồn có transaction |
| 13 | Time-off, reschedule, email, PDF, Excel | Tính năng P1 dùng được |
| 14 | Follow-up, Mock LIS, Mock Device | P2 demo, lỗi và retry |
| 15 | Full integration, E2E, security, fixes | Test report + bug list |
| 16 | Deployment demo, report, slides | Bản bàn giao chạy trên máy khác |

## 15.1. Phân công đề xuất

| Thành viên | Trách nhiệm chính | Cần phối hợp |
|---|---|---|
| TV1 — BA/Lead | SRS, UML, acceptance, báo cáo | Chốt quy tắc trước khi code; review PR |
| TV2 — Frontend | React, layout, form, tables, charts | Ký hợp đồng API cùng TV3/4 |
| TV3 — Backend 1 | Auth, doctor/patient, schedules, appointments, visits, medical records | Transaction với TV4 |
| TV4 — Backend 2 | Database/migration, billing, stock, reports, notifications, LIS mock | Backup và tích hợp E2E |

Mỗi tuần nên có: 1 lần chia việc, 1 lần review API/schema, 1 lần chạy demo tích hợp. Không cho hai thành viên tự ý sửa schema cùng lúc mà không có migration được review.

## 15.2. Những phụ thuộc quan trọng

```mermaid
flowchart TD
    A[Auth + Role + Branch] --> B[Patients / Doctors / Specialties]
    B --> C[Schedules + Appointments]
    C --> D[Check-in + Visits]
    D --> E[Medical Records]
    E --> F[Prescriptions]
    E --> G[Service Orders / Results]
    F --> H[Dispensations / Inventory]
    F --> I[Invoices]
    G --> I
    H --> I
    I --> J[Payments + Dashboard]
    C --> K[Email + Reschedule]
    E --> L[Follow-up]
    G --> M[Mock LIS]
```

Đồ thị thể hiện phụ thuộc thiết kế. Trong thực tế thu phí khám có thể phát sinh trước khi kết thúc khám, vì vậy invoice/payment cần hỗ trợ nghiệp vụ phù hợp thay vì cố coi sơ đồ là luồng duy nhất.

# 16. Tài liệu bàn giao và demo

## 16.1. Các artifact bàn giao

- [ ] SRS với FR/NFR/BR, phạm vi và giả định đã chốt.
- [ ] Use Case Diagram tổng quan và theo vai trò.
- [ ] Mỗi UC chính có đặc tả text (main/alternative/exception flow).
- [ ] Activity, Sequence, Class, State, Component, Deployment Diagram.
- [ ] ERD và Data Dictionary đầy đủ.
- [ ] API Specification (OpenAPI/Swagger), mã lỗi và quyền.
- [ ] Wireframes hoặc Figma prototype cho màn hình chính.
- [ ] Frontend/Backend source, Docker Compose, seed và `.env.example`.
- [ ] Database migrations; script backup/restore demo.
- [ ] Unit/Integration/E2E test và báo cáo Pass/Fail.
- [ ] README hướng dẫn chạy và tài khoản demo.
- [ ] Báo cáo Word/PDF (nếu giảng viên yêu cầu) và slides.
- [ ] Video/ảnh demo ngắn (nếu môn học yêu cầu).

## 16.2. Kịch bản demo xuyên suốt

1. Admin đăng nhập, tạo **chi nhánh A** và **chi nhánh B**, chuyên khoa và hai bác sĩ.
2. Admin thiết lập ca làm bác sĩ ở chi nhánh A; thử tạo ca trùng ở B để chứng minh hệ thống chặn.
3. Bệnh nhân giả **BN0001** đăng ký, tìm bác sĩ, chọn slot và đặt lịch.
4. Lễ tân thấy lịch tại chi nhánh A, xác nhận và check-in; tạo Visit WAITING.
5. Bác sĩ mở queue, bắt đầu khám, ghi nội dung mô phỏng, chỉ định dịch vụ.
6. Mock LIS gửi kết quả; nhân viên có quyền xác minh, bác sĩ nhận được.
7. Bác sĩ kê và ký đơn, hoàn tất lượt khám, đề xuất tái khám.
8. Thu ngân tạo hóa đơn từ phí khám, dịch vụ, thuốc theo chính sách, ghi thanh toán và tải PDF.
9. Nhân viên cấp phát chọn lô thuốc còn hạn, ghi nhận cấp phát; tồn kho giảm đúng.
10. Bệnh nhân xem lịch sử khám và phản hồi đề xuất tái khám.
11. Admin lọc doanh thu theo branch, xem lượt khám và xuất Excel.
12. Chạy hai case lỗi: (a) đặt trùng slot, (b) xuất thuốc vượt tồn; chứng minh rollback đúng.

**Dữ liệu demo:** chỉ dùng tên và thông tin giả, giá/lượng giả lập, chẩn đoán và thuốc là nhãn minh họa không áp dụng điều trị.

## 16.3. Cấu trúc báo cáo môn học

1. **Chương 1 — Tổng quan:** lý do, mục tiêu, phạm vi, phương pháp.
2. **Chương 2 — Khảo sát & đặc tả:** actors, nghiệp vụ, FR, NFR, BR.
3. **Chương 3 — Phân tích UML:** Use Case, Activity, Sequence, State, Class.
4. **Chương 4 — Thiết kế hệ thống:** kiến trúc, ERD, data dictionary, API, wireframe.
5. **Chương 5 — Triển khai:** stack, cấu trúc, module, ảnh giao diện.
6. **Chương 6 — Kiểm thử:** chiến lược, test case, kết quả, hạn chế.
7. **Chương 7 — Kết luận:** những gì đạt được và hướng phát triển.
8. **Phụ lục:** schema, endpoints, test, hướng dẫn chạy, tài khoản demo.

# 17. Checklist bắt đầu lập trình

## Giai đoạn A — Chốt phân tích

- [ ] Chốt phạm vi P0/P1/P2 và quy định phòng khám giả lập.
- [ ] Chốt 5 actor, permission, branch scope.
- [ ] Rà soát danh sách FR-01 đến FR-17.
- [ ] Rà soát UC-01 đến UC-30 và Business Rules.
- [ ] Vẽ ít nhất Use Case tổng quan, Activity đặt lịch, Sequence đặt lịch.

## Giai đoạn B — Chuẩn bị công nghệ

- [ ] Cài Node LTS, Git, Docker/Compose, VS Code.
- [ ] Khởi tạo `frontend` React TS.
- [ ] Khởi tạo `backend` Express TS.
- [ ] Dựng PostgreSQL local bằng Docker Compose.
- [ ] Thiết lập `.env.example`, `.gitignore`, README.
- [ ] Đặt convention API, error codes, log/request IDs.

## Giai đoạn C — P0 (bắt buộc)

- [ ] `users`, session, permissions.
- [ ] `branches`, `patients`, `doctors`, `specialties`.
- [ ] Ca làm, slot, lịch nghỉ cơ bản.
- [ ] Đặt lịch và transaction chống trùng.
- [ ] Check-in, Visit và queue.
- [ ] MedicalRecord và Prescription.
- [ ] Invoice và Payment.
- [ ] Dashboard dữ liệu thật từ database.
- [ ] E2E xuyên suốt 5 vai trò.

## Giai đoạn D — P1

- [ ] Đổi lịch, xử lý bác sĩ nghỉ và thông báo.
- [ ] Chỉ định, kết quả và duyệt.
- [ ] PDF và Excel có kiểm quyền.
- [ ] Worker email có retry.
- [ ] Nhập kho, cấp phát, chứng từ và tồn theo lô/chi nhánh.
- [ ] Phân quyền branch kiểm thử chéo.

## Giai đoạn E — P2

- [ ] Kế hoạch tái khám và consent.
- [ ] Mock LIS và adapter nhận kết quả.
- [ ] Mock Device và validate measurement.
- [ ] Kiểm thử idempotency, timeout, retry.

## Giai đoạn F — Hoàn thiện

- [ ] Automated tests + concurrency tests.
- [ ] Kiểm thử backup/restore và migrate từ database trống.
- [ ] Chuẩn bị demo seed, tài khoản giả.
- [ ] Chụp giao diện, cập nhật sơ đồ theo code thực tế.
- [ ] Viết báo cáo, slide và hướng dẫn chạy trên máy khác.

# 18. Nhật ký quyết định thiết kế

> Ghi lại thay đổi tại đây để không bị mâu thuẫn giữa SRS, UML, database và code.

| Mã | Quyết định hiện tại | Lý do | Trạng thái |
|---|---|---|---|
| ADR-01 | Modular Monolith thay vì Microservices | Đơn giản hơn cho đồ án, vẫn tách module | Đề xuất |
| ADR-02 | React + TypeScript, Express + TypeScript, PostgreSQL + Prisma | Dễ tổ chức và kiểm thử | Đề xuất |
| ADR-03 | 5 role chính + permission/branch scope | Hỗ trợ kho và LIS không cần thêm role cố định | Đề xuất |
| ADR-04 | Bệnh nhân dùng chung toàn hệ thống; dữ liệu nghiệp vụ mang branchId | Multi-branch ngay từ đầu | Đề xuất |
| ADR-05 | Appointment khác Visit | Walk-in và no-show đúng nghiệp vụ | Đề xuất |
| ADR-06 | Kê đơn khác cấp phát | Tránh tự động trừ tồn sai | Đề xuất |
| ADR-07 | Invoice ISSUED khác Payment SUCCEEDED | Hỗ trợ thu tiền nhiều lần | Đề xuất |
| ADR-08 | Email qua outbox/worker | Không ảnh hưởng booking và có retry | Đề xuất |
| ADR-09 | LIS/thiết bị chỉ Mock API ở đồ án | Khả thi, không giả danh kết nối y tế thật | Đề xuất |
| ADR-10 | Ưu tiên hoàn thành P0 trước | Kiểm soát phạm vi và chất lượng | Đề xuất |

### Các thông số cần chốt trước khi code production-like

1. Khung giờ khám có độ dài cố định từng bác sĩ hay thay đổi theo dịch vụ?
2. Lịch đặt xong PENDING hay CONFIRMED ngay?
3. Lễ tân có được tạo walk-in cho bệnh nhân chưa có tài khoản không? **Đề xuất có.**
4. Khi chỉ định dịch vụ, thu tiền trước hay sau? **Đề xuất có cấu hình; demo thu cuối.**
5. Khi khám xong mà thuốc chưa cấp, cho phép đóng Visit không? **Đề xuất có; Dispensation xử lý riêng.**
6. Chính sách hoàn tiền/giảm giá và người có quyền duyệt?
7. Chi nhánh nào nhìn thấy hồ sơ nào, phân biệt thông tin hành chính và bệnh án?
8. Bệnh nhân có quyền tải đơn PDF/hóa đơn PDF của mình ở trạng thái nào?
9. Tái khám tự động chọn giờ nào khi có nhiều slot, consent lưu ra sao?
10. Có cần xem hồ sơ của người được giám hộ không (ngoài P0)?

**Quy trình đổi quyết định:** ghi ADR mới → cập nhật SRS/Business Rules → cập nhật UML, DB/API → cập nhật Test Case → mới sửa code.

---

## Ghi chú kết thúc

Tài liệu này đủ làm **bản thiết kế và checklist triển khai end-to-end** của đề tài. Tuy nhiên, nó **không phải** một ứng dụng đầy đủ có thể chạy ngay: bạn vẫn cần triển khai các model/migration, controllers/services, frontend, test, worker và mock integrations theo thứ tự đã nêu. Với các tính năng y tế thật, phải rà soát thêm quy định và tiêu chuẩn liên quan, an toàn thông tin, định dạng liên thông và xác minh chuyên môn trước khi đưa vào thực tế.

**Bước đầu tiên nên thực hiện:** dựng repository + Docker PostgreSQL + Express/React, hoàn thành Auth/RBAC và 4 danh mục `branches`, `patients`, `doctors`, `specialties`; sau đó triển khai Appointments và kiểm thử chống trùng trước khi chuyển qua luồng khám bệnh.
