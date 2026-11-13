# Cấu hình Laragon trên máy hiện tại

Đã xác minh ngày 12/11/2026 với Apache 2.4.54, PHP 8.1.10 và MySQL 8.0.30.

## Mở ứng dụng

1. Bật Apache và MySQL trong Laragon (Start All).
2. Vào **http://127.0.0.1:8000**; Apache đã phục vụ trực tiếp thư mục public tại cổng này.
3. Không cần chạy thêm artisan serve. Nếu chạy dev.ps1, script nhận ra ứng dụng đang chạy và báo địa chỉ.
4. Email/tái khám cần mở worker.ps1 riêng.

VirtualHost thực tế: `D:/laragon/etc/apache2/sites-enabled/clinic-nhom3.conf`. Mẫu để thiết lập máy khác: `scripts/laragon/clinic-nhom3.conf.example`. Không để artisan serve và Apache cùng chiếm cổng 8000.

## Lỗi đã xử lý

### ERR_CONNECTION_REFUSED

Ban đầu cổng 8000 chưa có server; Apache Laragon chỉ lắng nghe 80/443. Đã bổ sung VirtualHost local tại cổng 8000, DocumentRoot trỏ public. Cấu hình chặn truy cập trực tiếp thư mục gốc/storage/vendor qua website mặc định.

### openssl_cipher_iv_length / could not find driver

Biến môi trường PHP_INI_SCAN_DIR trỏ tới file cấu hình CLI của Scoop. File này được nạp sau php.ini Laragon và ghi đè extension_dir bằng đường dẫn tương đối ext; Apache không tải được OpenSSL, PDO, mbstring, intl và GD.

Đã bỏ dòng ghi đè extension_dir trong `C:/Users/ADMIN/scoop/apps/php/current/cli/php.ini`. Đường dẫn extension của Scoop được đặt riêng trong `C:/Users/ADMIN/scoop/apps/php/current/php.ini`; Laragon tiếp tục dùng đường dẫn tuyệt đối trong php.ini của chính nó. Cả hai PHP đã được kiểm tra lại, Apache đã khởi động lại và các extension cần thiết hoạt động. [Cách PHP nạp thêm file INI](https://www.php.net/manual/en/configuration.file.php).

### Database MySQL chưa có bảng

Database phong_kham_nhom3 trước đó rỗng; ứng dụng còn dùng SQLite. Đã sao lưu SQLite và cấu hình cũ, tạo schema MySQL và chuyển 46 bảng/121 dòng dữ liệu. Số dòng và tất cả khóa ngoại đã được kiểm chứng; dữ liệu nguồn SQLite vẫn còn.

`.env` hiện dùng mysql, 127.0.0.1:3306, database phong_kham_nhom3. Tài khoản/mật khẩu demo được giữ. Có backup MySQL sau chuyển trong storage/app/backups; các file chứa cấu hình không được đưa vào Git.

Script `scripts/import-sqlite-to-mysql.php` chỉ chấp nhận database đích hoàn toàn chưa có bảng; không chạy lại lên database đang dùng. Không chạy migrate:fresh để khắc phục lỗi trên dữ liệu thật.

## Kiểm tra lại

### 419 — Trang đã hết hạn khi đăng nhập

Form đang mở có thể chứa mã CSRF của phiên cũ sau khi hết hạn session, đăng xuất ở tab khác hoặc thay đổi cấu hình phiên. Trên máy hiện tại đã kiểm tra cookie HTTP ở 127.0.0.1, đường dẫn `/`, session ghi/đọc ổn định và mã CSRF không thay đổi giữa các lần tải trang trong cùng phiên.

POST đăng nhập có mã CSRF cũ được từ chối và chuyển bằng HTTP 303 về form mới, giữ email và yêu cầu nhập lại mật khẩu. Không lưu mật khẩu, không tự gửi lại thông tin đăng nhập. Trang đăng nhập dùng `Cache-Control: no-store` và tải lại khi trình duyệt khôi phục trang từ bộ nhớ lịch sử. Các form khác vẫn trả 419 với hướng dẫn mở lại trang; yêu cầu JSON vẫn nhận HTTP 419.

Khi đang đứng ở trang lỗi cũ, mở trực tiếp `http://127.0.0.1:8000/dang-nhap` rồi nhập lại mật khẩu. Dùng nhất quán địa chỉ này, không đổi qua localhost giữa lúc điền và gửi form. Khi chỉnh `.env`, giữ APP_KEY hiện có và chạy `php artisan config:clear` để cấu hình mới có hiệu lực.

CSRF vẫn được bật; không thêm đường dẫn đăng nhập vào danh sách miễn kiểm tra CSRF để khắc phục lỗi.

### Nhiều tài khoản trong cùng cửa sổ trình duyệt

Các tab cùng địa chỉ website dùng chung cookie. Đăng nhập hoặc đăng xuất ở một tab sẽ thay đổi phiên của các tab cùng địa chỉ; form đang mở có thể còn mã CSRF cũ hoặc thuộc tài khoản trước đó.

Với admin/bác sĩ/lễ tân/thu ngân, bấm **tên tài khoản ở góc dưới menu trái → Đăng nhập tài khoản khác** để mở tab với phiên riêng. Đổi mật khẩu và Đăng xuất cũng nằm trong menu tên tài khoản; bấm ra ngoài hoặc Escape để đóng. Bệnh nhân vẫn dùng liên kết ở menu ngang. Trang đăng nhập cũng có liên kết **Mở phiên riêng ở tab mới**. Mỗi lần mở tạo một địa chỉ local dạng `phien-<mã ngẫu nhiên>.localhost:8000`. Chrome/Edge đã được kiểm tra với Laragon hiện tại; không cần sửa hosts hoặc khởi động lại Apache. Cookie session và ghi nhớ đăng nhập gắn riêng với từng địa chỉ. Mã trong địa chỉ không phải token đăng nhập; chỉ sao chép địa chỉ sang trình duyệt khác sẽ không đăng nhập được.

Có thể mở admin, bệnh nhân, bác sĩ trong cùng một cửa sổ; đăng xuất một phiên không ảnh hưởng tài khoản ở phiên khác. Để dùng chung một tài khoản ở nhiều tab, mở hoặc nhân bản tab cùng địa chỉ. Để đăng nhập tài khoản khác, luôn dùng nút mở phiên riêng. Tính năng chỉ hiển thị ở môi trường local, truy cập loopback và `SESSION_DOMAIN` để trống/null, tránh chia sẻ cookie giữa các địa chỉ.

Đã kiểm tra 3 tài khoản đồng thời trong **cùng một browser context/cookie jar**: phân quyền vẫn áp dụng, form CSRF của bệnh nhân còn dùng được sau khi admin/bác sĩ đăng nhập và đăng xuất từng tài khoản không làm mất phiên của tài khoản còn lại.

### Lệnh kiểm tra tự động

```powershell
python scripts/check-http.py
.\test.ps1
```

Kiểm tra HTTP dùng cookie/CSRF thật: đăng nhập, các trang chính và đăng xuất của 5 vai trò; trang chủ, tra cứu bác sĩ và CSS. Dùng tài khoản demo, có thể đặt CLINIC_SMOKE_PASSWORD nếu đã đổi mật khẩu.
