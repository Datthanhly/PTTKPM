# Báo cáo hệ thống phòng khám đa cơ sở An Tâm

Đề tài: **Phân tích và thiết kế hệ thống quản lý phòng khám đa cơ sở An Tâm**.

Nhóm 3, lớp N01. Lý Thành Đạt (23010879), Nguyễn Văn Nam (22011663).
Giảng viên: TS. Mai Thúy Nga. Hà Nội, tháng 11/2026.

## Bản đọc và nội dung

- `BaoCao_PhongKham_AnTam.pdf`: **204 trang A4**, có mục lục, danh mục hình/bảng và bookmark.
- Bốn chương tương tự bố cục UniCateen: yêu cầu, phân tích, thiết kế, hiện thực/kiểm thử.
- 40 đặc tả use case, 42 quy tắc nghiệp vụ, 43 sơ đồ, 16 ảnh giao diện chụp mới từ dữ liệu riêng.
- Từ điển 52 bảng (gồm bảng framework), danh mục kiểm thử và ma trận truy vết.
- Bằng chứng lần chạy kèm theo: **109 tests, 2.807 assertions**, SQLite `:memory:`.

Bìa đã điền theo thông tin nhóm cung cấp. Bảng phân công là **đề xuất rà soát**, không tự đặt tỷ lệ đóng góp hoặc tuyên bố công việc cá nhân chưa được xác nhận. Nhóm nên xác nhận phần này trước khi nộp.

## Sửa nội dung và xuất PDF

Các tệp sau chỉnh trực tiếp được bằng VS Code/TeXstudio:

- `main.tex`: thứ tự các phần.
- `setup.tex`: font, cỡ chữ, lề, tiêu đề, đầu/chân trang.
- `chapters/frontmatter.tex`: bìa, thành viên, lời mở đầu, phân công, mục lục.
- `chapters/chuong1.tex` đến `chuong4.tex`: nội dung chính.
- `chapters/end.tex`: kết luận và tài liệu tham khảo.
- `chapters/dictionary.tex`, `chapters/tests.tex`: phụ lục.

Máy Windows có MiKTeX và Times New Roman: chạy `./build.ps1` trong PowerShell. Script gọi XeLaTeX ba lần để cập nhật số trang/mục lục; không chạy Laravel, migration hoặc tác động dữ liệu. Có thể gọi trực tiếp:

```powershell
xelatex -interaction=nonstopmode -halt-on-error -jobname=BaoCao_PhongKham_AnTam main.tex
```

Lặp lệnh ba lần sau thay đổi mục lục. Các bản PDF hình đã kèm sẵn nên **không cần Node/Python/Graphviz khi chỉ sửa chữ và biên dịch báo cáo**. Đây là nguồn LaTeX, cùng loại với báo cáo tham khảo.

Sau khi sửa sơ đồ TikZ trong `figures/diagrams/*.tex`, chạy `./build.ps1 -RebuildDiagrams` để xuất lại PDF/PNG/SVG của hình rồi dựng báo cáo. Bước này cần Python có PyMuPDF. Báo cáo nhúng các PDF hình đã xuất, có giới hạn cả chiều rộng và chiều cao để tránh tràn trang. Không chạy `tools/diagrams.py` sau khi sửa TikZ trực tiếp vì bộ sinh sẽ ghi đè các chỉnh sửa đó.

`tools/build_report.py` là bộ sinh nguồn ban đầu từ `tools/content.py` và các inventory. Chạy lại sẽ ghi đè chỉnh sửa trực tiếp trong `chapters/`; chỉ dùng khi chủ động sửa bộ sinh. Nó cần nằm trong project gốc để đối chiếu code.

## Sơ đồ dùng riêng

Mỗi sơ đồ ở `figures/diagrams/` có PNG để chèn Word, SVG/PDF vector để phóng to. Nguồn chỉnh sửa gồm 35 TikZ `.tex` và 8 Graphviz `.dot` cho ERD; `.tex` của ERD là tệp nhúng PDF tương ứng.

- Sửa TikZ: mở `.tex`, chỉnh nhãn hoặc tọa độ.
- Sửa ERD: mở `.dot`; khi có Graphviz, dùng `dot -Tsvg ...` rồi xuất PDF, hoặc bộ sinh `tools/render_erd.cjs`. Các bản xuất hiện tại đã có sẵn.
- `tools/SoDo_AnTam.pdf` gom 43 sơ đồ vector riêng để xem và sử dụng độc lập.

| Mã tệp | Sơ đồ |
|---|---|
| `uc_g1` | Use case nhóm 1: tài khoản và cổng bệnh nhân |
| `uc_g2` | Use case nhóm 2: lịch hẹn và tiếp nhận |
| `uc_g3` | Use case nhóm 3: khám và cận lâm sàng |
| `uc_g4` | Use case nhóm 4: tài chính và dược |
| `uc_g5` | Use case nhóm 5: ca khám và điều phối |
| `uc_g6` | Use case nhóm 6: lịch nhân viên và tiền lương |
| `uc_g7` | Use case nhóm 7: quản trị và xử lý nền |
| `uc_overview` | Use case tổng quan hệ thống An Tâm |
| `domain_care` | Mô hình lĩnh vực: người bệnh và chăm sóc |
| `domain_ops` | Mô hình lĩnh vực: tài chính, dược và nhân sự |
| `seq_book` | Tuần tự thiết kế: đặt lịch |
| `seq_cancel` | Tuần tự phân tích: hủy lịch có ngưỡng thời gian |
| `seq_checkin` | Tuần tự thiết kế: tiếp nhận một lần |
| `seq_record` | Tuần tự phân tích: điều chỉnh hồ sơ đã ký |
| `seq_payment` | Tuần tự thiết kế: thu tiền chống lặp |
| `seq_fefo` | Tuần tự thiết kế: cấp thuốc theo lô |
| `seq_roster` | Tuần tự phân tích: xếp và chốt lịch nhân viên |
| `seq_payroll` | Tuần tự thiết kế: lập bảng lương tháng |
| `seq_integration` | Tuần tự phân tích: nhận thông điệp tích hợp |
| `seq_transfer` | Tuần tự thiết kế: bác sĩ nhận chuyển ca |
| `act_care` | Hoạt động: từ đặt hẹn đến hoàn tất khám |
| `act_roster` | Hoạt động: đăng ký, xếp và chốt lịch nhân viên |
| `act_dispense` | Hoạt động: phân bổ FEFO và rollback |
| `act_payroll` | Hoạt động: đối chiếu và ghi nhận trả lương |
| `st_appointment` | Trạng thái lịch hẹn hiện hành |
| `st_visit` | Trạng thái lượt khám |
| `st_staff_request` | Trạng thái nguyện vọng nhân viên |
| `st_transfer` | Trạng thái yêu cầu chuyển ca bác sĩ |
| `st_payroll` | Trạng thái ghi nhận thanh toán lương |
| `architecture` | Kiến trúc ứng dụng và phụ thuộc |
| `deployment` | Triển khai bản Laragon trên Windows |
| `erd_identity` | Lược đồ quan hệ: danh tính và phân công |
| `erd_booking` | Lược đồ quan hệ: ca khám và lịch hẹn |
| `erd_clinical` | Lược đồ quan hệ: hồ sơ và đơn thuốc |
| `erd_diagnostic` | Lược đồ quan hệ: chỉ định, kết quả và tái khám |
| `erd_finance` | Lược đồ quan hệ: hóa đơn và dòng tiền |
| `erd_inventory` | Lược đồ quan hệ: kho và cấp phát theo lô |
| `erd_workforce` | Lược đồ quan hệ: nhân viên và bảng lương |
| `erd_integration` | Lược đồ quan hệ: tích hợp và thông báo |
| `class_booking` | Lớp thiết kế phân hệ lịch |
| `class_care` | Lớp thiết kế khám và kết quả |
| `class_operations` | Lớp thiết kế tiền, kho và báo cáo |
| `class_hr` | Lớp thiết kế lịch nhân viên và lương |

## Nguồn và bằng chứng

`tools/schema.json` chứa metadata cột/khóa/chỉ mục, không chứa nội dung bệnh nhân. Lược đồ được kiểm qua migration SQLite rỗng và đối chiếu metadata MySQL; khác biệt FK khi ALTER ở SQLite được nêu trong báo cáo.

`tools/source-manifest.json` lưu SHA-256 các tệp code đã đối chiếu. `tools/tests.xml`, `tools/test-run.txt` và `tools/test-inventory.json` là bằng chứng kiểm thử. Không suy ra code coverage hoặc hiệu năng từ số test.

Ảnh giao diện dùng SQLite minh họa riêng. Không đổi mật khẩu hay dữ liệu MySQL đang chạy. API LIS/Device, số lô/tồn, chuyển khoản, chấm công và trạng thái chi lương được mô tả đúng giới hạn hiện thực; báo cáo không khẳng định các tích hợp production đã được nghiệm thu.

Các tệp QA/bản dựng trung gian nằm trong `tools/qa` trên workspace và không được đóng gói vào bản nguồn bàn giao.
