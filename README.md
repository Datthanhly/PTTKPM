# Hệ thống quản lý phòng khám - Laragon 6.0

Laravel **10**, PHP **8.1+**, Blade, MySQL **8** hoặc SQLite. CSS có sẵn trong `public/app.css`, không cần Node/Vite để chạy. Nghiệp vụ đối chiếu với [đặc tả](document/Clinic_Management_System_Full_Specification.md); các quyết định và giới hạn nằm trong [bảng đối chiếu triển khai](document/Implementation_Laragon.md).

## Báo cáo phân tích và thiết kế

- [Báo cáo hoàn chỉnh, 204 trang](document/Báo_Cáo/BaoCao_PhongKham_AnTam.pdf).
- [Nguồn LaTeX và toàn bộ sơ đồ](document/Báo_Cáo/BaoCao_AnTam_NguonVaSoDo.zip).
- [Hướng dẫn chỉnh sửa, biên dịch báo cáo](document/Báo_Cáo/README.md).

Báo cáo gồm 40 use case, 42 quy tắc nghiệp vụ, 43 sơ đồ, 16 ảnh giao diện và từ điển 52 bảng. Mã nguồn và tài liệu được lưu cùng repository; cấu hình riêng, database vận hành, log và thư viện cài qua Composer không được đưa lên Git.

## Chạy trên máy hiện tại

**Máy này đã cấu hình Apache Laragon phục vụ trực tiếp http://127.0.0.1:8000 và dùng MySQL phong_kham_nhom3. Bật Start All trong Laragon rồi mở địa chỉ trên.** Không cần chạy thêm artisan serve. [Cấu hình và lỗi đã xử lý](document/Laragon_Troubleshooting.md).

```powershell
cd D:\laragon\www\DanhGiaKiemDinh\PTTKPM25-26_ClassN05_Nhom3
.\setup.ps1
.\dev.ps1
```

Mở **http://127.0.0.1:8000**. Script tự tìm PHP của Laragon; có thể đặt `$env:CLINIC_PHP` thành đường dẫn php.exe muốn dùng. Đã kiểm tra với **PHP 8.1.10 của Laragon 6**.

Nếu Apache đã phục vụ project, dev.ps1 chỉ thông báo ứng dụng đang chạy để tránh lỗi trùng cổng. Các lệnh setup/dev bên trên cũng dùng được khi cài project trên máy chưa có VirtualHost.

Setup cài dependency theo lock, giữ `.env`/APP_KEY đã có, backup, migrate nâng cấp và seed bổ sung. **Không dùng migrate:fresh trên database đang chạy.** Mặc định `.env.example` dùng SQLite để không phụ thuộc dịch vụ MySQL.

Nếu PowerShell chặn script, dùng `powershell -ExecutionPolicy Bypass -File .\setup.ps1` / `dev.ps1`, hoặc Terminal Laragon:

```powershell
composer install
php artisan config:clear
# Chỉ key:generate khi APP_KEY chưa có; sao chép .env.example nếu chưa có .env.
php artisan clinic:database
php artisan clinic:backup
php artisan migrate --force
php artisan db:seed --force
php artisan serve
```

## MySQL / Apache của Laragon

Bật MySQL trong Laragon, sửa nhóm DB trong `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=phong_kham_nhom3
DB_USERNAME=root
DB_PASSWORD=
```

Chạy lại setup. Script tạo database nếu chưa có. Đổi connection tạo bộ dữ liệu riêng trong MySQL, không tự chuyển dữ liệu SQLite.

Với Apache/Virtual Host, đặt **DocumentRoot vào thư mục `public`**, chỉnh APP_URL theo domain rồi `php artisan config:clear`. Không trỏ DocumentRoot vào gốc project chứa `.env`.

## Chức năng

- 5 vai trò, khóa tài khoản, quyền theo chi nhánh, Argon2id, đổi/reset mật khẩu và audit.
- Bệnh nhân/walk-in, bác sĩ nhiều chuyên khoa/chi nhánh, ca ngày/tuần, capacity/lịch nghỉ.
- **Quản lý thời gian / Lịch của tôi:** lịch tuần theo giờ, các ô ca sáng/chiều, chuyển tuần và lọc bác sĩ/chi nhánh. Bấm ca mới tải danh sách lịch hẹn; bác sĩ chỉ xem ca của mình. Menu đang mở có gạch chân và giữ dấu khi vào trang con.
- Bác sĩ đăng ký ca ngay trong **Lịch của tôi → Đăng ký ca làm**, không có mục menu riêng: sáng, chiều, cả hai hoặc giờ tùy chỉnh; có thể lặp cùng thứ hằng tuần trong 90 ngày. Chỉ đăng ký cho chính mình tại chi nhánh được phân công; chặn ca đã bắt đầu, trùng ca (kể cả khác chi nhánh/ca đóng), trùng lịch nghỉ và ca quá ngắn. Đợt nhiều ca lưu cùng lúc hoặc không lưu ca nào khi có lỗi. Ca mới sẵn sàng nhận lịch hẹn ngay; lưu thành công mở đúng tuần đã đăng ký.
- Chọn giờ trống, đặt hộ/tự đặt, đổi/hủy có lịch sử; check-in một lần và Visit/queue riêng.
- Bệnh nhân được đổi/hủy khi còn ít nhất **24 giờ** trước giờ khám đã đăng ký (đúng 24 giờ được phép). Dưới mốc này, hệ thống giữ nguyên lịch và hiện thông báo; nhân viên xử lý theo quyền nghiệp vụ riêng.
- Nháp/ký hồ sơ, kiểm version, điều chỉnh có lịch sử; đơn thuốc có từng dòng và ký.
- Chỉ định, nhập/duyệt kết quả và tệp riêng tư.
- Invoice snapshot, thuốc bán tại phòng khám, giảm giá, thanh toán từng phần/chống trùng/hoàn tiền.
- Kho theo branch/lô, FEFO, cấp từng phần, chặn hết hạn/thiếu tồn, biến động điều chỉnh.
- PDF tiếng Việt, XLSX, thu thực tế trừ hoàn, email outbox/retry, tái khám có consent, API mock LIS/Device.

## Tài khoản demo

Mật khẩu chung khi seed mới: **`Password@123`**. Seed nâng cấp không đổi mật khẩu đã có.

Muốn dùng nhiều tài khoản trong cùng cửa sổ Chrome/Edge, bấm **tên tài khoản ở cuối menu trái → Đăng nhập tài khoản khác** để mở tab với phiên độc lập (bệnh nhân dùng liên kết trên menu ngang). Menu tài khoản chỉ hiện tên; bấm mới mở Đổi mật khẩu, Đăng nhập tài khoản khác và Đăng xuất. Có thể đóng bằng cách bấm ra ngoài hoặc nhấn Escape. Mỗi tài khoản dùng một địa chỉ local riêng; không đăng xuất tài khoản hiện tại để đăng nhập tài khoản khác trong tab cùng địa chỉ. [Hướng dẫn nhiều tài khoản](document/Laragon_Troubleshooting.md).

| Vai trò | Email | Phạm vi |
|---|---|---|
| Admin | `admin@clinic.test` | Quản trị/báo cáo; không mặc định đọc/ký bệnh án |
| Lễ tân | `letan@clinic.test` | CN01 |
| Thu ngân | `thungan@clinic.test` | CN01 |
| Bác sĩ | `bacsi.an@clinic.test` | CN01; seed cấp rõ quyền nhập/duyệt kết quả và cấp phát |
| Bác sĩ | `bacsi.ha@clinic.test` | CN01 |
| Bác sĩ | `bacsi.nam@clinic.test` | CN02 khi seed mới |
| Bệnh nhân | `benhnhan@clinic.test` | Own |
| Nhân viên CN02 | `letan.cn2@clinic.test`, `thungan.cn2@clinic.test` | CN02 |

Hồ sơ bác sĩ/dữ liệu cũ giữ phân công khi nâng cấp, sửa tại Cấu hình. Ca demo mới có 14 ngày sắp tới; bổ sung ca nếu dữ liệu cũ đã quá ngày. Check-in chỉ đúng ngày hẹn; dùng walk-in để demo ngay hôm nay.

### Bổ sung nhân sự demo

```powershell
php artisan clinic:backup
php artisan clinic:coverage --days=14 --include-sunday --apply --report
```

Lệnh bảo đảm ít nhất **2 bác sĩ/chuyên khoa tại từng cơ sở đang hoạt động**, và mỗi buổi sáng/chiều có ít nhất 1 bác sĩ của khoa đó trực tại cơ sở. Với 11 khoa và 3 cơ sở hiện tại, có **66 bác sĩ, mỗi cơ sở 22 người**. Lễ tân/thu ngân hiện có giữ nguyên. Lệnh luân phiên các ca tương lai còn trống theo tổng giờ trong tuần; giữ ca có lịch hẹn, lịch sử hoặc đề nghị đổi ca, ca tùy chỉnh/đã đóng và lịch quá khứ. Ca trống dư được đóng, không xóa. Không đổi lịch hẹn bệnh nhân, tài khoản, mật khẩu, phân công hoặc lương cũ; không mở lại tài khoản bị khóa.

Mặc định bắt đầu ngày mai trong 14 ngày; `--include-sunday` xếp cả Chủ nhật. Có thể chỉ định `--from=YYYY-MM-DD` và `--days=1..90`, trong phạm vi 90 ngày tới. **Bỏ `--apply` để xem trước**; chạy lại khi đã đủ không thêm bác sĩ. Bác sĩ mới dùng mật khẩu `Password@123`, mức lương 500.000 VNĐ/giờ; hồ sơ và số giấy phép là dữ liệu giả lập cho đồ án. Không tự cấp thêm quyền ngoài vai trò. [Danh sách tài khoản](document/Clinic_Demo_Accounts.md), [báo cáo khoa/cơ sở và đợt phân ca](document/Doctor_Coverage_Report.md). Cấu hình ở `config/clinic_workforce.php`; cần chạy lại để mở rộng lịch sau ngày cuối đợt, không tự chạy mỗi lần setup/seed. Lệnh `clinic:staff` vẫn dùng để bổ sung dữ liệu ban đầu theo quy tắc cũ; dùng `clinic:coverage` cho yêu cầu phủ khoa/cơ sở hiện tại.

### Quên mật khẩu

Mở **Đăng nhập → Quên mật khẩu**, nhập email đã đăng ký. Khi `MAIL_MAILER=log` trên máy Laragon, liên kết nằm trong `storage/logs/laravel.log`, không gửi ra hộp thư thật. Liên kết chỉ dùng một lần và hết hạn sau 60 phút. Muốn gửi đến hộp thư thật cần cấu hình SMTP trong `.env` rồi chạy `php artisan config:clear`.

Đăng nhập, đăng ký, quên mật khẩu và đặt lại mật khẩu dùng bộ đếm giới hạn riêng. Quên mật khẩu cho phép tối đa 3 yêu cầu/phút/IP; form giữ email và đếm ngược khi phải chờ, tự bật lại nút gửi khi hết hạn. Gửi liên tiếp cùng email không tạo liên kết mới trong 60 giây. Thông báo giống nhau cho email tồn tại và không tồn tại.

### Xác nhận và tiếp nhận lịch hẹn

Lễ tân có thể xác nhận lịch ngay tại **Thông báo → Lịch khám sắp tới**, hoặc mở ca trong **Quản lý thời gian → Lịch khám bác sĩ**. Chờ xác nhận → Xác nhận → Tiếp nhận; chỉ tiếp nhận đúng ngày hẹn và chỉ ghi nhận vắng mặt sau khi hết giờ hẹn. Nút chưa đủ điều kiện được vô hiệu hóa kèm giải thích. Trong cửa sổ chi tiết ca, đổi trạng thái cập nhật danh sách tại chỗ. Thao tác chỉ áp dụng tại cơ sở được phân công; form cũ hoặc thao tác không hợp lệ trả thông báo thay vì trang lỗi kỹ thuật.

### Thiết lập giờ khám sáng và chiều

Form đặt lịch lấy giờ thực từ ca làm, chia thành buổi sáng/chiều và hiển thị số chỗ còn lại. Giờ đã qua, hết chỗ hoặc trùng lịch nghỉ không được chọn.

Để bổ sung nhanh ca **08:00–11:30 và 13:30–17:00**, mỗi lượt **30 phút**, sức chứa 1, từ thứ Hai đến thứ Bảy:

```powershell
php artisan clinic:schedules --days=14
# Hoặc chỉ một bác sĩ tại chi nhánh được phân công:
php artisan clinic:schedules --days=14 --doctor=BS0001 --branch=CN01
```

Lệnh tạo ca kể từ hôm nay, tối đa 90 ngày, giữ ca đã có (kể cả ca đóng), không tạo chồng giờ giữa các chi nhánh và không thay đổi lịch hẹn. Khi bỏ trống chi nhánh, dùng chi nhánh hoạt động đầu tiên được phân công cho từng bác sĩ. Đổi các khung mặc định tại `config/clinic.php` (`working_hours`, `slot_minutes`), rồi chạy `php artisan config:clear`; thay đổi chỉ áp dụng cho ca tạo mới. Để cấu hình ca riêng, đăng nhập admin → **Danh mục → Thêm ca làm việc**.

### Đổi bác sĩ phụ trách ca

Mục **Bảng điều khiển** được đổi tên thành **Thông báo**, giữ địa chỉ `/bang-dieu-khien` để các liên kết cũ tiếp tục hoạt động.

Bác sĩ vào **Lịch của tôi → chọn ca → Đổi ca với bác sĩ đang trống**, chọn người nhận và nhập lý do. Danh sách chỉ gồm bác sĩ đang hoạt động, cùng chuyên khoa, được phân công tại cùng chi nhánh và trống toàn bộ khoảng giờ của ca. Ca phải đang mở, chưa bắt đầu và chưa có bệnh nhân được tiếp nhận/hồ sơ điều trị.

Đề nghị xuất hiện trong **Thông báo** của hai bác sĩ. Người nhận có thể đồng ý hoặc từ chối; người gửi có thể hủy đề nghị đang chờ. Khi đồng ý, hệ thống kiểm tra lại lịch trống và chuyển ca cùng các lịch hẹn PENDING/CONFIRMED trong một giao dịch. Ngày, giờ và chi nhánh giữ nguyên. Lịch đã hủy/vắng mặt giữ bác sĩ cũ trong lịch sử. Chỉ bác sĩ đã nhận ca mới được xem bệnh nhân trong ca; thay đổi được ghi lịch sử và tạo thông báo email cho bệnh nhân đã bật nhận email.

### Danh mục và kho thuốc tại các cơ sở

`php artisan clinic:medicines` bổ sung Panadol 500 mg, Clanoz 10 mg và Bocalex C 1000, với tồn đầu riêng từng cơ sở hoạt động. Mỗi lô có phiếu nhập và biến động kho; chạy lại không nạp trùng hoặc sửa tồn đã thay đổi. Hai thuốc DEMO chưa chỉnh sửa được chuyển sang ngừng sử dụng, giữ nguyên liên kết đơn thuốc/lịch sử và tồn cũ. Bật **Hiện thuốc ngừng sử dụng** trong Kho để xem lại.

[Nguồn nhà sản xuất, giá tham khảo và dữ liệu tồn đầu](document/Clinic_Medicine_Catalog.md). Tên/hàm lượng là thông tin sản phẩm thật; số lô, hạn dùng và số lượng `KHOITAO-` là dữ liệu khởi tạo cho đồ án, cần thay theo phiếu nhập thực tế. Sao lưu trước khi bổ sung vào database đang dùng.

### Đăng ký lịch làm lễ tân và thu ngân

Trong **Quản lý thời gian**, lễ tân/thu ngân mở **Lịch làm của tôi → Đăng ký lịch làm** để gửi nguyện vọng ở trạng thái **WAITING**. Admin mở **Xếp lịch nhân viên**, xếp tự động theo nhu cầu từng ca hoặc chỉnh bằng tay, rồi bấm **Chốt lịch** để công bố. Hỗ trợ rút/từ chối đăng ký chưa chốt, mở chốt ca tương lai để điều chỉnh, chặn trùng giờ giữa cơ sở và kiểm tra phiên bản khi nhiều tab cùng sửa. [Hướng dẫn và trạng thái](document/Staff_Roster_Workflow.md).

### Tiền lương và menu bên trái

Admin, bác sĩ, lễ tân và thu ngân dùng menu bên trái; điện thoại có nút Menu. Admin vào **Bảng lương** để nhập đơn giá riêng theo giờ hoặc ca, lập bảng tháng từ ca đã kết thúc, đối chiếu số công thực tế rồi ghi nhận **Đã thanh toán / Chưa thanh toán**. Nhân viên chỉ xem **Lương của tôi**. Bảng đã thanh toán khóa chỉnh số tiền, thay đơn giá không ảnh hưởng bảng cũ. [Hướng dẫn tiền lương](document/Payroll_Workflow.md).

## Worker, email, backup

Mở terminal thứ hai:

```powershell
.\worker.ps1
# Hoặc xử lý một lượt:
php artisan clinic:work
```

Mặc định MAIL_MAILER=log: email demo trong `storage/logs/laravel.log`. Cấu hình SMTP trong `.env` nếu muốn gửi thật. Scheduler xử lý email/tái khám mỗi phút, backup lúc 23:00; cần giữ worker chạy.

```powershell
php artisan clinic:backup
php artisan clinic:restore "đường-dẫn-backup.sqlite"
# MySQL cần tên DB bản sao chưa tồn tại:
php artisan clinic:restore "đường-dẫn-backup.sql" --database=clinic_restore_demo
```

Backup có manifest JSON/SHA-256/số dòng. Restore kiểm file và số dòng vào bản sao mới. MySQL dùng mysql.exe/mysqldump.exe Laragon; khai báo MYSQL_PATH/MYSQLDUMP_PATH trong `.env` nếu không tự tìm thấy.

## Kiểm thử

```powershell
.\test.ps1
```

**25 tests / 192 assertions** đã đạt trên SQLite và MySQL 8.0.30: HTTP theo vai trò, P0 xuyên 5 vai trò, scope, chống trùng, snapshot tiền, rollback kho, PDF/XLSX, email/mock. [Chi tiết và giới hạn](document/Implementation_Laragon.md).

Concurrency dùng **DB riêng có tiền tố clinic_spec_**; script tạo lại schema DB kiểm thử:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='mysql'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_DATABASE='clinic_spec_race'
$env:DB_USERNAME='root'
$env:DB_PASSWORD=''
# Tạo clinic_spec_race bằng MySQL client trước.
php tests/concurrency.php
```

Đã đạt 4 ca process đồng thời: slot capacity=1, tranh lô, tranh số dư hóa đơn, duplicate payment. Mở terminal mới sau kiểm thử để tránh dùng lại biến môi trường test.

## API / mô phỏng

[Hợp đồng HTTP](document/API_Laragon.md), [OpenAPI machine API](document/openapi.yaml). Đăng ký nguồn LIS/Device và token riêng tại Cấu hình. Script `scripts/mock-lis.ps1` / `scripts/mock-device.ps1` gửi dữ liệu giả lập vào local, hỗ trợ gửi lặp để kiểm idempotency.

Đây là demo học thuật với dữ liệu giả. Phần chưa đủ như thiết kế mở rộng (LIS outbound/FHIR, nhiều múi giờ/kho, tải lớn/browser E2E) được ghi rõ trong bảng đối chiếu.
