# 🏥 HỆ THỐNG QUẢN LÝ PHÒNG KHÁM TƯ NHÂN VÀ ĐẶT LỊCH KHÁM BỆNH

**Bài tập lớn môn Phân tích và Thiết kế Phần mềm (PTTKPM)**  
**Lớp:** N01 — **Nhóm:** 03
**Trường:** Đại học Phenikaa — Khoa Công nghệ Thông tin

> **Trạng thái:** Đề tài mới đang trong giai đoạn phân tích, thiết kế và phát triển. Các chức năng được mô tả bên dưới là **phạm vi dự kiến**, không phải tuyên bố tất cả đã được triển khai.

## 📖 1. Giới thiệu đề tài

**Hệ thống quản lý phòng khám tư nhân và đặt lịch khám bệnh** là ứng dụng web hỗ trợ số hóa các hoạt động cơ bản của một phòng khám tư nhân, từ tiếp nhận bệnh nhân, đặt lịch khám, sắp xếp lịch làm việc của bác sĩ đến ghi nhận kết quả khám, lập hóa đơn và thống kê hoạt động.

Hệ thống hướng tới hai nhóm người sử dụng chính:

- **Bệnh nhân:** tra cứu thông tin bác sĩ, chuyên khoa và lịch khám; đăng ký, theo dõi hoặc hủy lịch hẹn.
- **Nhân sự phòng khám:** tiếp nhận bệnh nhân, quản lý lịch hẹn, khám bệnh, lập hóa đơn và giám sát hoạt động của phòng khám.

Mục tiêu là giảm thao tác thủ công, hạn chế trùng lịch, nâng cao khả năng tra cứu thông tin và tạo một quy trình khám bệnh rõ ràng, dễ quản lý.

## 🎯 2. Mục tiêu dự án

1. Phân tích yêu cầu và mô hình hóa quy trình nghiệp vụ của phòng khám tư nhân.
2. Thiết kế hệ thống phân quyền theo các vai trò: bệnh nhân, lễ tân, bác sĩ, thu ngân và quản trị viên.
3. Xây dựng cơ chế đăng ký và quản lý lịch khám theo bác sĩ, chuyên khoa, ngày và khung giờ.
4. Hỗ trợ quy trình tiếp nhận, khám bệnh, ghi nhận thông tin khám và thanh toán mô phỏng.
5. Thiết kế cơ sở dữ liệu có ràng buộc toàn vẹn, tránh trùng lịch và bảo vệ thông tin bệnh nhân.
6. Xây dựng bộ tài liệu UML và nguyên mẫu/ứng dụng minh họa cho môn PTTKPM.

## 👥 3. Các tác nhân (Actors)

| STT | Tác nhân | Vai trò |
|:--:|---|---|
| 1 | **Bệnh nhân (Patient)** | Quản lý thông tin cá nhân; tìm bác sĩ, đặt lịch, xem và hủy lịch khám của mình. |
| 2 | **Nhân viên lễ tân (Receptionist)** | Tiếp nhận bệnh nhân; hỗ trợ tạo, xác nhận, điều chỉnh và check-in lịch hẹn. |
| 3 | **Bác sĩ (Doctor)** | Xem lịch làm việc, danh sách bệnh nhân, ghi nhận kết quả khám và chỉ định dịch vụ khi cần. |
| 4 | **Thu ngân (Cashier)** | Lập hóa đơn, ghi nhận thanh toán, tra cứu giao dịch. |
| 5 | **Quản trị viên (Admin)** | Quản lý tài khoản, phân quyền, bác sĩ, chuyên khoa, dịch vụ, lịch làm việc và báo cáo. |

> Một tài khoản chỉ được truy cập các dữ liệu và chức năng phù hợp với quyền được cấp. Bệnh nhân không được xem hồ sơ khám của người khác.

## 🧩 4. Chức năng hệ thống (Functional Requirements)

### 4.1. Bệnh nhân

- Đăng ký, đăng nhập, đăng xuất và cập nhật hồ sơ cá nhân.
- Xem danh sách chuyên khoa, bác sĩ và thông tin dịch vụ khám.
- Tra cứu khung giờ khám còn trống theo bác sĩ/ngày.
- Tạo lịch hẹn khám và nhận mã lịch hẹn.
- Xem lịch hẹn sắp tới, trạng thái lịch và lịch sử đặt khám.
- Hủy lịch hẹn theo quy định của phòng khám.
- Xem kết quả khám của chính mình khi phòng khám cho phép công bố.

### 4.2. Nhân viên lễ tân

- Tìm kiếm, tạo mới và cập nhật thông tin bệnh nhân.
- Đặt lịch trực tiếp tại quầy hoặc hỗ trợ bệnh nhân đặt lịch.
- Xem lịch khám theo ngày, bác sĩ, chuyên khoa và trạng thái.
- Xác nhận, sắp xếp lại hoặc hủy lịch hẹn theo phân quyền.
- Ghi nhận bệnh nhân đã đến (check-in) và đưa vào danh sách chờ khám.

### 4.3. Bác sĩ

- Xem lịch làm việc và danh sách bệnh nhân theo ca/ngày.
- Xem thông tin cần thiết của bệnh nhân được phân công khám.
- Ghi nhận triệu chứng, kết luận khám, chẩn đoán và ghi chú chuyên môn.
- Chỉ định dịch vụ khám/cận lâm sàng ở mức mô phỏng nếu thuộc phạm vi đồ án.
- Cập nhật trạng thái ca khám sau khi hoàn tất.

### 4.4. Thu ngân

- Xem danh sách dịch vụ phát sinh từ ca khám.
- Lập hóa đơn từ bảng giá dịch vụ đã cấu hình.
- Ghi nhận thanh toán tiền mặt hoặc thanh toán giả lập.
- Xem chi tiết, tra cứu và in/xuất hóa đơn (nếu triển khai).

### 4.5. Quản trị viên

- Quản lý tài khoản, vai trò và quyền truy cập.
- Quản lý bác sĩ, chuyên khoa, dịch vụ và bảng giá.
- Thiết lập ca làm việc và khung giờ khám của bác sĩ.
- Cấu hình các quy tắc đặt/hủy lịch cơ bản.
- Theo dõi số lượng lịch hẹn, ca khám và doanh thu theo thời gian.

## 🗂️ 5. Danh sách Use Case dự kiến

| Mã | Use Case | Tác nhân chính |
|---|---|---|
| UC01 | Đăng ký tài khoản | Bệnh nhân |
| UC02 | Đăng nhập/đăng xuất | Tất cả tác nhân |
| UC03 | Cập nhật thông tin cá nhân | Bệnh nhân |
| UC04 | Tra cứu chuyên khoa | Bệnh nhân |
| UC05 | Tìm kiếm và xem thông tin bác sĩ | Bệnh nhân |
| UC06 | Xem khung giờ khám còn trống | Bệnh nhân, Lễ tân |
| UC07 | Đặt lịch khám | Bệnh nhân, Lễ tân |
| UC08 | Xem lịch sử và trạng thái lịch hẹn | Bệnh nhân |
| UC09 | Hủy lịch khám | Bệnh nhân, Lễ tân |
| UC10 | Quản lý thông tin bệnh nhân | Lễ tân |
| UC11 | Xác nhận/điều chỉnh lịch hẹn | Lễ tân |
| UC12 | Check-in bệnh nhân | Lễ tân |
| UC13 | Xem danh sách chờ khám | Bác sĩ, Lễ tân |
| UC14 | Xem lịch làm việc | Bác sĩ |
| UC15 | Xem thông tin bệnh nhân được phân công | Bác sĩ |
| UC16 | Lập phiếu/kết quả khám bệnh | Bác sĩ |
| UC17 | Chỉ định dịch vụ | Bác sĩ |
| UC18 | Hoàn tất ca khám | Bác sĩ |
| UC19 | Lập hóa đơn | Thu ngân |
| UC20 | Ghi nhận thanh toán | Thu ngân |
| UC21 | Tra cứu hóa đơn | Thu ngân |
| UC22 | Quản lý tài khoản và phân quyền | Quản trị viên |
| UC23 | Quản lý bác sĩ | Quản trị viên |
| UC24 | Quản lý chuyên khoa | Quản trị viên |
| UC25 | Quản lý lịch làm việc và khung giờ | Quản trị viên |
| UC26 | Quản lý dịch vụ và bảng giá | Quản trị viên |
| UC27 | Xem thống kê, báo cáo | Quản trị viên |

Các Use Case có thể được điều chỉnh sau khi nhóm hoàn tất đặc tả yêu cầu và sơ đồ Use Case tổng quát.

## 🔄 6. Quy trình nghiệp vụ chính

### 6.1. Quy trình đặt lịch khám trực tuyến

```text
Bệnh nhân truy cập website
    ↓
Đăng nhập / đăng ký tài khoản
    ↓
Chọn chuyên khoa và bác sĩ
    ↓
Chọn ngày, xem khung giờ trống
    ↓
Gửi yêu cầu đặt lịch
    ↓
Hệ thống kiểm tra điều kiện và chống trùng lịch
    ↓
Tạo lịch hẹn → Chờ xác nhận / Đã xác nhận
    ↓
Bệnh nhân theo dõi trạng thái lịch hẹn
```

### 6.2. Quy trình tiếp nhận và khám bệnh

```text
Bệnh nhân đến phòng khám
    ↓
Lễ tân kiểm tra thông tin và check-in
    ↓
Bệnh nhân vào danh sách chờ
    ↓
Bác sĩ tiếp nhận và thực hiện khám
    ↓
Bác sĩ ghi nhận kết quả / dịch vụ chỉ định
    ↓
Thu ngân lập hóa đơn, ghi nhận thanh toán
    ↓
Hoàn tất quy trình khám
```

### 6.3. Trạng thái lịch hẹn

| Trạng thái | Ý nghĩa |
|---|---|
| `PENDING` | Đã đặt, chờ xác nhận. |
| `CONFIRMED` | Lịch hẹn đã được xác nhận. |
| `CHECKED_IN` | Bệnh nhân đã đến và được tiếp nhận. |
| `IN_PROGRESS` | Đang khám. |
| `COMPLETED` | Đã hoàn tất khám. |
| `CANCELLED` | Lịch hẹn đã bị hủy. |
| `NO_SHOW` | Bệnh nhân không đến đúng lịch. |

**Luồng bình thường:** `PENDING → CONFIRMED → CHECKED_IN → IN_PROGRESS → COMPLETED`.

Các nhánh `CANCELLED`, `NO_SHOW` chỉ hợp lệ từ một số trạng thái trước đó theo quy tắc nghiệp vụ; không cho phép chuyển trạng thái tùy ý.

## 📌 7. Quy tắc nghiệp vụ

- **BR01 — Chống trùng lịch:** Một bác sĩ không được có hai lịch hẹn còn hiệu lực chiếm cùng một khoảng thời gian.
- **BR02 — Giới hạn ca làm việc:** Chỉ cho phép đặt lịch trong ca làm việc đang mở của bác sĩ.
- **BR03 — Tính hợp lệ:** Không thể đặt lịch vào thời gian đã qua hoặc khung giờ bị khóa.
- **BR04 — Hủy lịch:** Chỉ cho phép hủy trước thời hạn do phòng khám cấu hình; lễ tân có thể xử lý ngoại lệ theo quyền.
- **BR05 — Check-in:** Chỉ cho phép check-in với lịch đủ điều kiện tiếp nhận.
- **BR06 — Hồ sơ khám:** Chỉ nhân sự y tế được phân quyền mới có thể ghi nhận/sửa thông tin khám.
- **BR07 — Thanh toán:** Hóa đơn chỉ được đánh dấu đã thanh toán khi đã ghi nhận đủ số tiền theo cách thanh toán được hỗ trợ.
- **BR08 — Bảo mật:** Bệnh nhân chỉ truy cập thông tin cá nhân và hồ sơ khám thuộc quyền của mình.
- **BR09 — Toàn vẹn dữ liệu:** Khi hủy lịch, không xóa vật lý hồ sơ liên quan; sử dụng trạng thái và lịch sử thao tác.
- **BR10 — Xử lý đồng thời:** Thao tác tạo lịch phải kiểm tra xung đột trong giao dịch ở máy chủ, không chỉ kiểm tra trên giao diện.

## 🗃️ 8. Thiết kế cơ sở dữ liệu dự kiến

| Bảng | Mục đích |
|---|---|
| `roles` | Danh sách vai trò hệ thống. |
| `users` | Tài khoản đăng nhập, thông tin xác thực, vai trò. |
| `patients` | Thông tin hành chính của bệnh nhân. |
| `specialties` | Danh mục chuyên khoa. |
| `doctors` | Hồ sơ bác sĩ và chuyên khoa liên kết. |
| `doctor_schedules` | Ca làm việc của bác sĩ theo ngày/giờ. |
| `appointments` | Thông tin lịch hẹn, khung giờ, người đặt, trạng thái. |
| `medical_records` | Nội dung từng lần khám và kết luận chuyên môn. |
| `services` | Danh mục dịch vụ và đơn giá. |
| `visit_services` | Các dịch vụ thực hiện/chỉ định trong lần khám. |
| `invoices` | Hóa đơn gắn với ca khám. |
| `invoice_items` | Các dòng dịch vụ/chi phí trên hóa đơn. |
| `payments` | Các lần ghi nhận thanh toán. |
| `audit_logs` | Lưu vết thao tác quan trọng (nếu triển khai). |

**Quan hệ tiêu biểu:**

- `specialties (1) — (N) doctors`.
- `doctors (1) — (N) doctor_schedules`.
- `patients (1) — (N) appointments`.
- `doctors (1) — (N) appointments`.
- `appointments (1) — (0..1) medical_records`.
- `medical_records (1) — (N) visit_services`.
- `invoices (1) — (N) invoice_items`.
- `invoices (1) — (N) payments`.

> Các bảng và quan hệ là bản phác thảo ban đầu. Nhóm cần hoàn thiện khóa chính, khóa ngoại, ràng buộc, chỉ mục và chuẩn hóa trong ERD trước khi lập trình.

## 🏗️ 9. Kiến trúc hệ thống

Dự kiến áp dụng **kiến trúc 3 lớp (Three-Tier Architecture)**:

| Lớp | Trách nhiệm |
|---|---|
| **Presentation Layer** | Hiển thị giao diện bệnh nhân, lễ tân, bác sĩ, thu ngân và quản trị viên; tiếp nhận thao tác người dùng. |
| **Business Layer** | Xử lý quy tắc đặt lịch, trạng thái khám, phân quyền, hóa đơn và thống kê. |
| **Data Access Layer** | Truy vấn, lưu trữ và kiểm soát tính nhất quán của dữ liệu. |

Thiết kế được tách thành các module: `Authentication`, `Patient`, `Doctor`, `Schedule`, `Appointment`, `MedicalRecord`, `Billing`, `Administration` và `Reporting`.

## ⚙️ 10. Công nghệ sử dụng

Dựa trên cấu hình mã nguồn hiện có của repository (`pom.xml`), nhóm có thể tiếp tục với bộ công nghệ sau:

| Thành phần | Công nghệ | Trạng thái |
|---|---|---|
| Ngôn ngữ backend | **Java 17** | Có trong `pom.xml` hiện tại |
| Web backend | **Jakarta Servlet / JSP / JSTL** | Có trong `pom.xml` hiện tại |
| Quản lý phụ thuộc/build | **Apache Maven** | Có trong repository |
| Giao diện | HTML5, CSS3, JavaScript, Bootstrap | Đề xuất / xác nhận khi triển khai |
| Cơ sở dữ liệu | MySQL | Đề xuất, cần bổ sung cấu hình kết nối |
| Máy chủ ứng dụng | Apache Tomcat 10.x tương thích Jakarta Servlet 5.0 | Đề xuất |
| Quản lý mã nguồn | Git, GitHub | Đang sử dụng |
| Mô hình phát triển | Agile/Scrum | Định hướng quản lý công việc |

> **Lưu ý kỹ thuật:** README cũ có nhắc Laravel, nhưng `pom.xml` của repository hiện tại cấu hình dự án Java WAR. Nhóm cần thống nhất **một** stack trước khi triển khai đề tài mới. Nếu nhóm quyết định chuyển sang Laravel, hãy sửa phần công nghệ và hướng dẫn chạy tương ứng; không sử dụng các lệnh Maven bên dưới cho dự án Laravel.

## 🚀 11. Hướng dẫn thiết lập môi trường (dự kiến cho Java/Maven)

### 11.1. Yêu cầu

- JDK 17.
- Apache Maven.
- Apache Tomcat tương thích phiên bản Jakarta Servlet của dự án.
- MySQL nếu triển khai tầng lưu trữ dữ liệu.
- IDE: IntelliJ IDEA / Eclipse / Visual Studio Code.

### 11.2. Biên dịch dự án hiện có

```bash
mvn clean package
```

File `.war` được tạo tại thư mục `target/` nếu quá trình build thành công. Tên file phụ thuộc `artifactId` và `version` trong `pom.xml`.

### 11.3. Triển khai

1. Đặt file `.war` vào thư mục `webapps/` của Tomcat phù hợp.
2. Khởi động Tomcat và truy cập đường dẫn theo context path của ứng dụng.
3. Sau khi nhóm phát triển cơ sở dữ liệu, cập nhật cấu hình kết nối bằng biến môi trường hoặc file cấu hình **không commit thông tin bí mật**.
4. Thực hiện khởi tạo schema và dữ liệu mẫu theo script của dự án **khi các script này đã được bổ sung**.

> Các bước trên chỉ hướng dẫn build/triển khai skeleton Java đang có trong repo. Chúng **chưa bảo đảm** chức năng phòng khám chạy được vì nhóm đang chuyển đổi đề tài.

## 🔐 12. Yêu cầu phi chức năng

- **Bảo mật:** xác thực, phân quyền tại máy chủ, mật khẩu được băm an toàn, không để lộ dữ liệu cá nhân trong log và repository.
- **Riêng tư:** sử dụng dữ liệu giả lập khi demo; giới hạn truy cập hồ sơ khám, chỉ lưu thông tin thật khi có cơ sở pháp lý và biện pháp bảo vệ phù hợp.
- **Toàn vẹn:** kiểm tra ràng buộc khóa ngoại và sử dụng giao dịch khi đặt lịch/ghi nhận thanh toán.
- **Dễ sử dụng:** giao diện rõ ràng, hỗ trợ máy tính và thiết bị di động.
- **Khả năng bảo trì:** tách module, đặt tên nhất quán, quản lý phiên bản và viết tài liệu cho chức năng chính.
- **Kiểm thử:** bao gồm ca đặt lịch thành công, trùng lịch, hủy lịch, check-in, phân quyền, tạo hóa đơn và xử lý lỗi nhập liệu.

**Giới hạn phạm vi:** Đây là dự án học tập; không phải phần mềm hồ sơ bệnh án điện tử được chứng nhận để vận hành y tế thực tế. Không tích hợp dữ liệu y tế thật, cổng thanh toán thật hay hệ thống bảo hiểm y tế trong phiên bản môn học trừ khi có yêu cầu riêng và giải pháp phù hợp.

## 📐 13. Tài liệu phân tích và thiết kế

Các sản phẩm nhóm dự kiến hoàn thiện:

1. Khảo sát hiện trạng, xác định phạm vi và yêu cầu hệ thống.
2. Danh sách yêu cầu chức năng và phi chức năng.
3. Use Case Diagram tổng quát và đặc tả Use Case chính.
4. Activity Diagram cho đặt lịch, tiếp nhận, khám bệnh và thanh toán.
5. Sequence Diagram cho các kịch bản trọng tâm.
6. Class Diagram: các lớp thực thể, lớp điều khiển, lớp nghiệp vụ.
7. State Diagram cho vòng đời lịch hẹn.
8. ERD và thiết kế bảng dữ liệu.
9. Thiết kế giao diện (wireframe/mockup) và bản demo.
10. Báo cáo kiểm thử, phân công công việc và tổng kết.

Tài liệu và bản thiết kế có thể được tổ chức tại hai thư mục `Documents/` và `Design/` đã có trong repository.

## 🗓️ 14. Kế hoạch triển khai đề xuất

| Giai đoạn | Công việc | Sản phẩm |
|---|---|---|
| **1. Phân tích yêu cầu** | Xác định tác nhân, yêu cầu, quy tắc nghiệp vụ và phạm vi | SRS, Use Case List |
| **2. Thiết kế hệ thống** | Thiết kế UML, giao diện và cơ sở dữ liệu | UML, ERD, mockup |
| **3. Xây dựng chức năng lõi** | Xác thực, quản lý bác sĩ, ca làm việc, bệnh nhân, đặt lịch | Demo nghiệp vụ đặt lịch |
| **4. Xây dựng quy trình khám** | Tiếp nhận, khám bệnh, dịch vụ, thanh toán mô phỏng | Demo quy trình toàn phần |
| **5. Kiểm thử & báo cáo** | Kiểm thử tích hợp, hoàn thiện tài liệu và thuyết trình | Báo cáo, slide, bản bàn giao |


## 🌱 15. Hướng phát triển

- Tự động nhắc lịch khám qua email/SMS.
- Hỗ trợ nhiều chi nhánh và quản lý phòng khám theo cơ sở.
- Tìm kiếm lịch khám linh hoạt theo chuyên khoa, thời gian và bác sĩ.
- Báo cáo số lượt khám và hiệu suất sử dụng khung giờ.
- Thanh toán trực tuyến khi có hạ tầng bảo mật và yêu cầu triển khai phù hợp.
- Mở rộng quản lý xét nghiệm, đơn thuốc và hồ sơ khám theo yêu cầu nghiệp vụ thực tế.

## 📚 16. Liên kết dự án

- **Tài liệu phân tích và thiết kế:** [`Documents/`](./Documents/)
- **Giao diện và sơ đồ:** [`Design/`](./Design/)
- **Nhật ký thay đổi:** [`Changelog.md`](./Changelog.md)

---
*Dự án phục vụ mục đích học tập và nghiên cứu trong môn Phân tích và Thiết kế Phần mềm.*
