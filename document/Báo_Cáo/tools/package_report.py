from pathlib import Path
import json,zipfile,hashlib,re
import pymupdf as fitz
BASE=Path(__file__).resolve().parents[1]
pdf=BASE/'BaoCao_PhongKham_AnTam.pdf'; doc=fitz.open(pdf)
items=json.loads((BASE/'tools/diagrams.json').read_text(encoding='utf8'))
audit=json.loads((BASE/'tools/qa/layout-audit.json').read_text(encoding='utf8'))
readme=f'''# Báo cáo hệ thống phòng khám đa cơ sở An Tâm

Đề tài: **Phân tích và thiết kế hệ thống quản lý phòng khám đa cơ sở An Tâm**.

Nhóm 3, lớp N01. Lý Thành Đạt (23010879), Nguyễn Văn Nam (22011663).
Giảng viên: TS. Mai Thúy Nga. Hà Nội, tháng 11/2026.

## Bản đọc và nội dung

- `BaoCao_PhongKham_AnTam.pdf`: **{len(doc)} trang A4**, có mục lục, danh mục hình/bảng và bookmark.
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
'''
readme+='\n'.join(f"| `{i['key']}` | {i['title']} |" for i in items)
readme+='''

## Nguồn và bằng chứng

`tools/schema.json` chứa metadata cột/khóa/chỉ mục, không chứa nội dung bệnh nhân. Lược đồ được kiểm qua migration SQLite rỗng và đối chiếu metadata MySQL; khác biệt FK khi ALTER ở SQLite được nêu trong báo cáo.

`tools/source-manifest.json` lưu SHA-256 các tệp code đã đối chiếu. `tools/tests.xml`, `tools/test-run.txt` và `tools/test-inventory.json` là bằng chứng kiểm thử. Không suy ra code coverage hoặc hiệu năng từ số test.

Ảnh giao diện dùng SQLite minh họa riêng. Không đổi mật khẩu hay dữ liệu MySQL đang chạy. API LIS/Device, số lô/tồn, chuyển khoản, chấm công và trạng thái chi lương được mô tả đúng giới hạn hiện thực; báo cáo không khẳng định các tích hợp production đã được nghiệm thu.

Các tệp QA/bản dựng trung gian nằm trong `tools/qa` trên workspace và không được đóng gói vào bản nguồn bàn giao.
'''
(BASE/'README.md').write_text(readme,encoding='utf8')
quality=f'''# Kết quả kiểm tra bản bàn giao

- PDF cuối: {len(doc)} trang A4, {len(doc.get_toc())} bookmark.
- Nội dung: 40 UC, 42 BR, 43 sơ đồ, 52 bảng dữ liệu, 16 ảnh giao diện.
- PHPUnit: 109 tests / 2.807 assertions đạt trên SQLite cô lập.
- Render toàn bộ {len(doc)} trang ở 120 dpi; rà soát bố cục qua các trang tổng hợp và xem chi tiết bìa, mục lục, UC, sơ đồ, bảng dài, trang kiểm thử.
- Kiểm tra tự động vùng chữ: {len(audit['issues'])} cảnh báo còn lại. Không có lỗi XeLaTeX, tràn khung hoặc ký tự thiếu trong log cuối.
- ERD dùng định tuyến tránh hộp và nối trực tiếp dòng FK tới khóa đích.
- Đã chỉnh khoảng cách, nhánh điều kiện và vòng lặp của sơ đồ hoạt động; bố trí lại use case, trạng thái, kiến trúc và triển khai.
- Đã viết lại phần thuyết minh theo nghiệp vụ và thiết kế, bỏ chú thích đối chiếu mã; 93 tình huống kiểm thử có tên tiếng Việt.
- SHA-256 PDF: `{hashlib.sha256(pdf.read_bytes()).hexdigest()}`.

Phạm vi: kiểm tài liệu và chức năng trên dữ liệu riêng; không phải chứng nhận y tế, kiểm thử tải hoặc kiểm thử xâm nhập.
'''
(BASE/'KIEM_TRA_BAN_BAO_CAO.md').write_text(quality,encoding='utf8')
files=[pdf,BASE/'main.tex',BASE/'setup.tex',BASE/'build.ps1',BASE/'README.md',BASE/'KIEM_TRA_BAN_BAO_CAO.md']
files+=list((BASE/'chapters').glob('*.tex'))
files+=[p for p in (BASE/'figures').rglob('*') if p.is_file() and p.suffix in ['.png','.jpg','.jpeg','.pdf','.svg','.tex','.dot']]
for name in ['content.py','diagrams.py','build_report.py','quality_check.py','inspect_figures.py','package_report.py','export_diagrams.py','render_erd.cjs','collect.php','source-manifest.json','test-inventory.json','test-labels.json','diagrams.json','erd-groups.json','screens.json','schema.json','tests.xml','test-run.txt','SoDo_AnTam.pdf']:
    files.append(BASE/'tools'/name)
archive=BASE/'BaoCao_AnTam_NguonVaSoDo.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=7) as z:
    for file in sorted(set(files)):
        z.write(file,Path('BaoCao_AnTam')/file.relative_to(BASE))
with zipfile.ZipFile(archive) as z:
    if z.testzip():raise RuntimeError('Archive CRC check failed')
print(json.dumps({'pages':len(doc),'pdf_bytes':pdf.stat().st_size,'zip_bytes':archive.stat().st_size,'files_in_zip':len(set(files))},ensure_ascii=False))
