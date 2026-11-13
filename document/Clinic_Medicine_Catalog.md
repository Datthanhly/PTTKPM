# Danh mục thuốc và tồn đầu các cơ sở

Đối chiếu nguồn ngày **12/11/2026**. Đây là dữ liệu thiết lập cho đồ án: tên, hoạt chất, hàm lượng và dạng thuốc theo nhà sản xuất; giá tham khảo theo nhà thuốc tại thời điểm tra cứu. Số lượng, số lô có tiền tố `KHOITAO-` và hạn dùng là dữ liệu khởi tạo, không phải số liệu kiểm kê hoặc lô thực từ nhà cung cấp.

| Mã | Tên thuốc | Hoạt chất / hàm lượng / dạng | Đơn vị | Giá ban đầu (đ/viên) | Tồn khởi tạo mỗi cơ sở |
|---|---|---|---|---|---|
| PANADOL-500 | Panadol 500 mg | Paracetamol 500 mg, viên nén | viên | 1.000 | 300 |
| CLANOZ-10 | Clanoz 10 mg | Loratadin 10 mg, viên nén | viên | 600 | 200 |
| BOCALEX-C1000 | Bocalex C 1000 | Vitamin C 1000 mg, viên nén sủi bọt | viên | 2.100 | 100 |

Các cơ sở **CN01, CN02 và 003** có lô/tồn riêng cho cả 3 thuốc. Hạn dùng khởi tạo bằng ngày thêm dữ liệu cộng một năm; khi dùng dữ liệu kho thực cần nhập số lô, hạn dùng và số lượng theo phiếu nhập. Giá dùng chung trong danh mục thuốc, có thể sửa tại **Kho / cấp thuốc → Danh mục thuốc**. Giá tham khảo có thể thay đổi; đây không phải báo giá cố định của nhà thuốc hoặc giá được nhà cung cấp chào cho phòng khám.

## Nguồn sản phẩm và giá

- **Panadol:** [nhà sản xuất, thành phần và dạng thuốc](https://www.panadol.com/content/cf-consumer-healthcare/wellness-panadol/vi_vn/home/san-pham/san-pham-nguoi-lon/giam-dau-va-ha-sot/panadol-.html); [Long Châu: 1.000 đ/viên](https://nhathuoclongchau.com.vn/thuoc/panadol-giam-dau-ha-sot-2716.html).
- **Clanoz:** [DHG Pharma, Loratadin 10 mg và dạng viên nén](https://dhgpharma.com.vn/vi/san-pham/ho-hap/clanoz); [Long Châu: 600 đ/viên](https://nhathuoclongchau.com.vn/thuoc/clanoz-10-mg-dhg-pharma-10-x10.html).
- **Bocalex C 1000:** [DHG Pharma, Vitamin C 1000 mg và dạng viên nén sủi bọt](https://dhgpharma.com.vn/vi/san-pham/dinh-duong/bocalex-c-1000); [Pharmacity: 21.000 đ/tuýp 10 viên, quy đổi 2.100 đ/viên](https://www.pharmacity.vn/bocalex-c-1000-hop-1-tuyp-10-vien.html).

Thông tin này dùng để điền danh mục; liều dùng và chỉ định cho bệnh nhân do bác sĩ quyết định khi khám, không được tự tạo đơn từ dữ liệu kho.

## Bổ sung và bảo toàn dữ liệu

```powershell
php artisan clinic:backup
php artisan clinic:medicines
```

Lệnh tạo phiếu nhập và biến động kho khớp từng lô khởi tạo. Chạy lại không tự nạp thêm hàng, không đổi giá/thuốc đã sửa và không mở lại thuốc bị vô hiệu hóa. Cơ sở đã có lô của một thuốc được giữ tồn hiện có, không tạo tồn đầu lần nữa. Cơ sở ngừng hoạt động được bỏ qua.

Hai danh mục cũ `DEMO-A` và `DEMO-B` chưa đổi tên/hàm lượng được chuyển sang ngừng sử dụng. Giữ nguyên tên, mã, số lượng, lô, đơn thuốc, hóa đơn và lịch sử cũ; không đổi chúng thành thuốc thật. Mặc định màn hình kho chỉ hiện thuốc hoạt động. Quản trị viên bật **Hiện thuốc ngừng sử dụng** để xem lại lô và danh mục đã lưu.

Lệnh bổ sung này chạy riêng, không tự chạy trong seed thông thường. Nguồn và mức tồn đầu được cấu hình ở `config/clinic_medicines.php`.
