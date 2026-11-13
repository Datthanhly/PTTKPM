"""Vector UML/ER diagrams stored as editable TikZ sources, not screenshots."""
import re, json
from pathlib import Path
from content import USECASES
BASE = Path(__file__).resolve().parents[1]
OUT = BASE/'figures'/'diagrams'
OUT.mkdir(parents=True,exist_ok=True)
DIAGRAMS=[]
def esc(s):
    s=re.sub(r'(?<=[a-z])(?=[A-Z])','§',str(s))
    return ''.join({'&':r'\&','_':r'\_\allowbreak{}','%':r'\%','#':r'\#','$':r'\$','§':r'\allowbreak{}'}.get(c,c) for c in s)
def node(n,x,y,text,style='box',w=3.2):
    formatted=esc(text).replace(chr(10),r'\\')
    if style=='ent' and '\n' in text:
        title,rest=text.split('\n',1)
        formatted=r'\textbf{'+esc(title)+r'}\par\smallskip\hrule\smallskip '+esc(rest).replace(chr(10),r'\\')
    return rf'\node[{style},text width={w}cm] ({n}) at ({x},{y}) {{{formatted}}};'+'\n'
def arrow(a,b,label='',path='--',dashed=False):
    sty='arr,dashed' if dashed else 'arr'
    middle=rf' node[lab] {{{esc(label)}}}' if label else ''
    return rf'\draw[{sty}] ({a}) {path}{middle} ({b});'+'\n'
def save(key,title,ch,body,explain,source):
    tex=r'''\begingroup\hyphenpenalty=10000
\begin{tikzpicture}[x=1cm,y=1cm,>=Stealth,
box/.style={draw=teal!65!black,fill=teal!4,rounded corners=2pt,align=center,minimum height=.9cm,inner sep=5pt,font=\fontsize{10}{12}\selectfont},
uc/.style={draw=teal!65!black,fill=yellow!7,ellipse,align=center,minimum height=.8cm,inner sep=5pt,font=\fontsize{10}{12}\selectfont},
state/.style={box,rounded corners=8pt},
ent/.style={box,rounded corners=0pt,fill=white,align=left},
decision/.style={diamond,draw=teal!65!black,fill=yellow!8,aspect=2,align=center,font=\fontsize{10}{12}\selectfont,inner sep=2pt},
arr/.style={->,line width=.65pt,draw=black!75},
assoc/.style={line width=.6pt,draw=black!65},
lab/.style={font=\fontsize{8}{10}\selectfont,fill=white,inner sep=2pt,align=center}]
'''+body+'\\end{tikzpicture}\n\\endgroup\n'
    (OUT/(key+'.tex')).write_text(tex,encoding='utf8')
    DIAGRAMS.append(dict(key=key,title=title,ch=ch,explain=explain,source=source))
def actor(n,x,y,label):
    return rf'''\draw ({x},{y+.3}) circle (.15);
\draw ({x},{y+.15}) -- ({x},{y-.35});
\draw ({x-.3},{y}) -- ({x+.3},{y});
\draw ({x},{y-.35}) -- ({x-.25},{y-.7});
\draw ({x},{y-.35}) -- ({x+.25},{y-.7});
\node[align=center,font=\fontsize{{10}}{{12}}\selectfont,text width=2.7cm] ({n}) at ({x},{y-1.05}) {{{esc(label)}}};
\coordinate ({n}-east) at ({x+.3},{y});
\coordinate ({n}-west) at ({x-.3},{y});
\coordinate ({n}-out-east) at ({x+1.55},{y});
\coordinate ({n}-out-west) at ({x-1.55},{y});
'''

def association(a,u,side):
    target='west' if side=='east' else 'east'
    return rf'\draw[assoc] ({a}-{side}) -- ({a}-out-{side}) -- ({u}.{target});'+'\n'
groups=[
('Tài khoản và cổng bệnh nhân',[1,2,3,4,5,25,26],['Khách','Bệnh nhân']),
('Lịch hẹn và tiếp nhận',[6,7,8,9,10,11,12],['Bệnh nhân','Lễ tân']),
('Khám và cận lâm sàng',[13,14,15,16,17,18],['Bác sĩ','Nhân sự kết quả']),
('Tài chính và dược',[19,20,21,22,23,24],['Thu ngân','Nhân sự kho/dược']),
('Ca khám và điều phối',[27,28,32],['Bác sĩ','Người vận hành']),
('Lịch nhân viên và tiền lương',[29,30,31,33,34,35],['Nhân viên','Admin']),
('Quản trị và xử lý nền',[36,37,38,39,40],['Admin/Thu ngân','Worker/Nguồn máy'])]
# Check index in source if use-case count changes.
for gi,(title,ids,actors) in enumerate(groups,1):
    ids=[i for i in ids if i<=len(USECASES)]
    bottom=-1.8*(len(ids)-1)-1.1
    b=rf'\draw[rounded corners,draw=black!65] (3,1.5) rectangle (13,{bottom});'+'\n'
    b+=rf'\node[font=\bfseries] at (8,1) {{{esc(title)}}};'+'\n'
    b+=actor('actor0',.5,-1.5,'Admin' if gi==7 else actors[0])+actor('actor1',15.5,-4.2 if gi==1 else (-2.8 if gi==7 else min(-3.5,bottom+2)),'Worker' if gi==7 else actors[1])
    if gi==1:b+=actor('actor2',15.5,bottom+1,'Bác sĩ')
    if gi==7:b+=actor('actor2',.5,-5.8,'Thu ngân')+actor('actor3',15.5,-6.5,'LIS/Device')
    for j,i in enumerate(ids):
        b+=node('u'+str(i),8,-.25-1.8*j,f'UC-{i:02d}. '+USECASES[i-1][0],'uc',5.5)
        # Use associations based on the actor semantics of the individual use case.
        a=0 if j%2==0 else 1
        if gi==2: a=0 if i in [6,7,8,9] else 1
        if gi==3: a=1 if i==18 else 0
        if gi==4: a=0 if i<=21 else 1
        if gi==1: a=0 if i in [1,2,3,5] else 1
        if gi==5: a=1 if i==32 else 0
        if gi==6: a=0 if i in [29,35] else 1
        if gi==7: a=0 if i in [36,37,40] else (1 if i==38 else 3)
        side='east' if a==0 else 'west'; target='west' if a==0 else 'east'
        b+=association('actor'+str(a),'u'+str(i),side)
        if gi==2 and i in [6,7,8,9]:b+=association('actor1','u'+str(i),'west')
        if gi==1 and i==5:b+=association('actor1','u'+str(i),'west')
        if gi==1 and i==26:b+=association('actor2','u'+str(i),'west')
        if gi==6 and i==35:b+=association('actor1','u'+str(i),'west')
        if gi==3 and i==18:b+=association('actor0','u'+str(i),'east')
        if gi==7 and i==37:b+=association('actor2','u'+str(i),'east')
    save(f'uc_g{gi}',f'Use case nhóm {gi}: {title.lower()}',1,b,
         'Đường nối là quan hệ tham gia giữa tác nhân và mục tiêu sử dụng. Các quyền chi tiết vẫn cần kiểm theo cơ sở; một vai trò trong sơ đồ không được mặc định truy cập mọi tài nguyên. Các mã UC dẫn tới đặc tả chi tiết ở phần sau.',
         'routes/web.php; routes/api.php; config/clinic.php; các controller liên quan')
b=''
for i,(title,_,_) in enumerate(groups): b+=node('g'+str(i),8,-i*1.8,title,'uc',5)
b+=actor('p',.4,-.4,'Bệnh nhân/Khách')+actor('d',.4,-4.1,'Bác sĩ')+actor('r',.4,-8,'Lễ tân')
b+=actor('c',15.6,-.4,'Thu ngân')+actor('a',15.6,-4.1,'Admin')+actor('m',15.6,-8,'LIS/Device; worker')
for a,inds in [('p',[0,1]),('d',[2,4,5]),('r',[1,5]),('c',[3,5,6]),('a',[4,5,6]),('m',[6])]:
    for j in inds:
        side='east' if a in ['p','d','r'] else 'west'; target='west' if side=='east' else 'east'
        b+=association(a,'g'+str(j),side)
b=rf'\draw[draw=black!65] (3.5,1) rectangle (12.5,-11.8);'+'\n'+b
save('uc_overview','Use case tổng quan hệ thống An Tâm',1,b,
'Sơ đồ tổng quan gom mục tiêu theo phân hệ để giữ khả năng đọc. Bảy nhóm được phân rã thành các sơ đồ tiếp theo. Nhân sự có quyền nhập/duyệt kết quả hoặc cấp phát không phải role mới; họ xuất hiện ở sơ đồ chi tiết theo permission bổ sung.',
'routes/web.php; config/clinic.php; app/Services/ClinicAccess.php')

def relation_diagram(key,title,ch,entities,links,explain,source):
    b=''
    for name,x,y,fields in entities:
        b+=node(name,x,y,name+'\n'+fields,'ent',3.4)
    for a,z,label in links:
        if (key,a,z)==('domain_ops','BangLuong','NhanSu'):
            b+=rf'\draw[arr] ({a}.south) -- (10,-8.3) -- node[lab] {{{esc(label)}}} (0,-8.3) -- ({z}.south);'+'\n'
        elif (key,a,z)==('domain_ops','DotCap','LoThuoc'):
            b+=r'\draw[arr] (DotCap.east) -- (LoThuoc.west);\node[lab,anchor=south] at (7.5,-2.15) {phân bổ lô};'+'\n'
        elif (key,a,z)==('architecture','Controllers','PrivateFiles'):
            b+=rf'\draw[arr] ({a}.south) -- (12,-4.5) -- node[lab,above] {{{esc(label)}}} (0,-4.5) -- ({z}.north);'+'\n'
        elif key in ['architecture','deployment']:
            b+=rf'\draw[arr] ({a}) -- node[lab,above,sloped] {{{esc(label)}}} ({z});'+'\n'
        else:b+=arrow(a,z,label)
    save(key,title,ch,b,explain,source)

relation_diagram('domain_care','Mô hình lĩnh vực: người bệnh và chăm sóc',2,
[('BenhNhan',0,0,'Thông tin hành chính'),('LichHen',5,0,'Ngày, giờ, trạng thái'),('CaKham',10,0,'Giờ, bước, sức chứa'),('LuotKham',5,-3,'Hàng chờ, tiến trình'),('BacSi',10,-3,'Chuyên môn, phân công'),('HoSoKham',0,-6,'Nội dung, chữ ký'),('DonThuoc',5,-6,'Thuốc, hướng dẫn'),('ChiDinh',10,-6,'Dịch vụ, kết quả')],
[('LichHen','BenhNhan','0..* / 1'),('LichHen','CaKham','0..* / 0..1'),('LuotKham','LichHen','0..1 / 0..1'),('CaKham','BacSi','0..* / 1'),('LuotKham','BacSi','0..* / 1'),('HoSoKham','LuotKham','0..1 / 1'),('DonThuoc','LuotKham','0..* / 1'),('ChiDinh','LuotKham','0..* / 1')],
'Mô hình chọn các quan hệ trọng tâm; không phải ERD đầy đủ. Ký hiệu bội số ghi ở nhãn theo thứ tự nguồn/đích. Lượt vãng lai không bắt buộc có lịch hẹn, còn lịch chưa đến khám chưa có lượt. Hồ sơ và các chứng từ chuyên môn gắn lượt để tránh đồng nhất việc đặt với việc đã được phục vụ.',
'app/Models/{Patient,Appointment,Visit,Doctor,MedicalRecord,Prescription,ServiceOrder}.php')
relation_diagram('domain_ops','Mô hình lĩnh vực: tài chính, dược và nhân sự',2,
[('HoaDon',0,0,'Giá chụp khi phát hành'),('KhoanThu',5,0,'Tiền mặt/chuyển khoản'),('HoanTien',10,0,'Số tiền, lý do'),('DonThuoc',0,-3,'Số lượng đã ký'),('DotCap',5,-3,'Lượng giao, người cấp'),('LoThuoc',10,-3,'Cơ sở, hạn, tồn'),('NhanSu',0,-6,'Vai trò, phân công'),('CaNhanVien',5,-6,'Dự kiến/đã chốt'),('BangLuong',10,-6,'Đơn giá, nguồn, trạng thái')],
[('KhoanThu','HoaDon','0..* / 1'),('HoanTien','KhoanThu','0..* / 1'),('DotCap','DonThuoc','0..* / 1'),('DotCap','LoThuoc','qua allocation'),('CaNhanVien','NhanSu','0..* / 1'),('BangLuong','NhanSu','0..* / 1')],
'Khoản thu và hoàn tiền là các chứng từ bổ sung, không xóa hóa đơn. Quan hệ cấp phát-lô cần thực thể allocation để ghi nhiều lô cho một dòng đơn. Bảng lương lưu snapshot ca, không giữ một quan hệ trực tiếp bắt buộc tới từng ca nguồn trong khóa ngoại.',
'BillingService; InventoryService; StaffRosterService; PayrollService')

SEQS=[
('seq_book','Tuần tự thiết kế: đặt lịch',3,['Người đặt','Appointment\nController','Booking\nService','Database','Outbox'],[(0,1,'POST dữ liệu + key'),(1,2,'book(patient, doctor, ...)'),(2,3,'BEGIN; khóa bác sĩ, bệnh nhân'),(2,3,'đọc khả dụng và lịch giao'),(3,2,'slot hợp lệ / lỗi'),(2,3,'INSERT appointment'),(2,4,'ghi notification job'),(2,3,'ghi audit; COMMIT'),(2,1,'Appointment / validation'),(1,0,'mã lịch hoặc thông báo')],
'Giao dịch bao trùm kiểm tra cuối cùng và ghi dữ liệu. Nhánh cùng khóa trả lịch cũ khi thông tin khớp; khác nội dung là 409. Outbox trong sơ đồ là bảng cùng database, không phải dịch vụ email đang được gọi đồng bộ.', 'AppointmentBookingService::book'),
('seq_cancel','Tuần tự phân tích: hủy lịch có ngưỡng thời gian',2,['Bệnh nhân','Trang lịch','Điều khiển hủy','Lịch hẹn','Thông báo'],[(0,1,'nhập lý do hủy'),(1,2,'yêu cầu hủy lịch'),(2,3,'kiểm quyền, khóa và đọc trạng thái'),(2,3,'kiểm now <= giờ khám - 24h'),(3,2,'đủ hạn / không đủ hạn'),(2,3,'[đủ hạn] cập nhật CANCELLED'),(2,4,'[đủ hạn] hủy nhắc, ghi sự kiện'),(2,1,'thành công hoặc lỗi nghiệp vụ'),(1,0,'hiển thị kết quả, giữ dữ liệu nếu lỗi')],
'Sơ đồ ở mức phân tích thể hiện quyết định nghiệp vụ, không phải hai nhánh cùng được chạy. Khi không đủ hạn, không cập nhật lịch, không tạo lịch sử hay job mới. Đúng 24 giờ thuộc nhánh cho phép.', 'AppointmentBookingService::cancel; Appointment::patientCanChange'),
('seq_checkin','Tuần tự thiết kế: tiếp nhận một lần',3,['Lễ tân','StaffAppointment\nController','VisitWorkflow','Appointment','Visit'],[(0,1,'PATCH CHECKED_IN'),(1,2,'checkIn(appointment)'),(2,3,'khóa bác sĩ, khóa lịch'),(2,3,'CONFIRMED? hôm nay? chưa Visit?'),(2,4,'create WAITING'),(2,3,'update CHECKED_IN'),(2,3,'hủy nhắc; COMMIT'),(2,1,'Visit mới'),(1,0,'thông báo đã tiếp nhận')],
'Visit được tạo cùng giao dịch đổi trạng thái lịch. Kiểm tra quyền được thực hiện theo cơ sở của bản lịch đã khóa. Việc bấm lặp không tạo lượt thứ hai; lỗi trạng thái web trở lại form còn JSON giữ mã lỗi phù hợp.', 'VisitWorkflow::checkIn; ReceptionAppointmentActionsTest'),
('seq_record','Tuần tự phân tích: điều chỉnh hồ sơ đã ký',2,['Bác sĩ','Biên hồ sơ','Điều khiển khám','Hồ sơ','Lịch sử sửa'],[(0,1,'nội dung + lý do + version'),(1,2,'yêu cầu ký điều chỉnh'),(2,3,'khóa lượt; đọc hồ sơ'),(2,3,'kiểm version và quyền'),(2,4,'snapshot bản đã ký'),(2,3,'lưu bản mới, tăng version'),(2,3,'ghi ký/công bố và audit'),(2,1,'bản hiện hành mới'),(1,0,'hiển thị kết quả')],
'Trường hợp version không khớp dừng trước snapshot và cập nhật. Chỉ bác sĩ phụ trách đủ quyền mới thực hiện được; lý do điều chỉnh là dữ liệu nghiệp vụ bắt buộc, không chỉ là ghi chú tùy chọn trên giao diện.', 'VisitWorkflow::record'),
('seq_payment','Tuần tự thiết kế: thu tiền chống lặp',3,['Thu ngân','Billing\nController','BillingService','Invoice','Payment'],[(0,1,'POST amount, method, key'),(1,2,'pay(invoice, data)'),(2,3,'BEGIN; khóa Invoice'),(2,4,'tìm idempotency_key'),(2,3,'tính lại outstanding'),(2,4,'[hợp lệ] INSERT SUCCEEDED'),(2,3,'ghi paid_at nếu đủ; COMMIT'),(2,1,'Payment hiện hành'),(1,0,'số dư và kết quả thu')],
'Nếu khóa đã tồn tại, máy chủ so invoice/amount/method/reference trước khi trả lại. Nếu chưa có, số tiền phải dương và không vượt số dư sau khóa. TRANSFER cần xác nhận; đây chưa là xác minh tự động từ ngân hàng.', 'BillingService::pay'),
('seq_fefo','Tuần tự thiết kế: cấp thuốc theo lô',3,['Người cấp','Inventory\nController','InventoryService','Prescription','Batch/Allocation'],[(0,1,'POST quantities + key'),(1,2,'dispense(...)'),(2,3,'BEGIN; khóa đơn'),(2,3,'kiểm SIGNED, phần chưa cấp'),(2,4,'SELECT lô còn hạn FOR UPDATE'),(2,4,'giảm tồn; lưu allocation'),(2,4,'ghi stock_movements'),(2,3,'đủ mọi dòng -> COMMIT'),(2,1,'đợt cấp / lỗi rollback'),(1,0,'lượng thực cấp')],
'Vòng lặp các lô thực hiện theo hạn dùng rồi ID. Nếu một dòng không đủ tồn, transaction rollback cả allocation và các lần giảm trước đó. Đơn đã ký là điều kiện cần, nhưng không đủ để bỏ kiểm cơ sở hay lượng đã cấp.', 'InventoryService::dispense'),
('seq_roster','Tuần tự phân tích: xếp và chốt lịch nhân viên',2,['Nhân viên','Biên lịch','Điều phối','Phương án','Admin'],[(0,1,'gửi nguyện vọng'),(1,2,'đăng ký ca'),(2,3,'lưu WAITING'),(4,2,'xếp tự động hoặc bằng tay'),(2,3,'tạo DRAFT; ARRANGED'),(2,4,'bảng cơ sở + fingerprint'),(4,2,'chốt tập ca với fingerprint'),(2,3,'kiểm toàn bộ; CONFIRMED'),(2,1,'công bố lịch chính thức'),(1,0,'xem ca đã chốt')],
'Đăng ký và phương án có trạng thái riêng. Admin rà soát trước chốt. Nếu fingerprint cũ hoặc một nhân viên không còn đủ điều kiện, không công bố một phần; lịch lâm sàng bác sĩ được giữ tách khỏi staff roster.', 'StaffRosterService::submit; arrange; publish'),
('seq_payroll','Tuần tự thiết kế: lập bảng lương tháng',3,['Admin','Payroll\nController','PayrollService','Nguồn ca','PayrollEntry'],[(0,1,'chọn người, tháng, profile_version'),(1,2,'generate(user, data)'),(2,3,'khóa user/profile; lấy ca đã kết thúc'),(3,2,'phút/ca, nguồn, loại ca nghỉ'),(2,4,'kiểm duy nhất user/tháng'),(2,2,'tính base và net nguyên VND'),(2,4,'INSERT snapshot UNPAID'),(2,4,'ghi audit; COMMIT'),(1,0,'bảng lương để đối chiếu')],
'Nguồn ca là lịch đã kết thúc, chưa chứng minh chấm công. Bảng tháng ghi lại nguồn để admin đối chiếu. Chỉ admin được lập/chỉnh; nhân viên dùng đường đọc riêng được giới hạn sở hữu.', 'PayrollService::source; generate'),
('seq_integration','Tuần tự phân tích: nhận thông điệp tích hợp',2,['Nguồn máy','Biên API','Điều khiển nhận','Thông điệp','Kết quả'],[(0,1,'POST code, Bearer, message ID'),(1,2,'nhận payload theo type'),(2,3,'kiểm nguồn, hash và khóa lặp'),(2,4,'đối chiếu order/visit/patient/branch'),(2,3,'lưu payload_hash + status'),(2,4,'lưu RECEIVED'),(2,1,'resource id / lỗi'),(1,0,'kết quả HTTP'),(2,4,'chờ xác minh của người có quyền')],
'Thông điệp trùng phải có cùng nội dung mới được trả tài nguyên cũ. Nguồn máy không tự đặt kết quả VERIFIED. Sơ đồ mô tả mô hình mock nhận dữ liệu; chưa có adapter gửi chỉ định ra LIS ngoài.', 'IntegrationController::receive'),
('seq_transfer','Tuần tự thiết kế: bác sĩ nhận chuyển ca',3,['Bác sĩ nhận','ShiftTransfer\nController','TransferService','Ca và yêu cầu','Appointment'],[(0,1,'accept + version/ngữ cảnh'),(1,2,'respond(request, accept)'),(2,3,'khóa, kiểm PENDING và sở hữu'),(2,3,'kiểm lại nguồn và người nhận rảnh'),(2,4,'kiểm chưa check-in'),(2,3,'chuyển doctor_id của ca'),(2,4,'chuyển lịch PENDING/CONFIRMED'),(2,3,'ACCEPTED; outbox/audit; COMMIT'),(1,0,'kết quả chuyển ca')],
'Người nhận có thể rảnh ở lúc gửi nhưng bận ở lúc chấp nhận; dịch vụ kiểm lại bên trong transaction. Ca và lịch còn hiệu lực phải đổi cùng nhau. Lịch hủy/vắng và dữ liệu khám lịch sử không được thay người phụ trách theo thao tác này.', 'ShiftTransferService::respond')]
for key,title,ch,parts,msgs,explain,source in SEQS:
    b=''
    for i,p in enumerate(parts):
        b+=node('l'+str(i),i*3.1,0,p,w=2.6)
        b+=rf'\draw[dashed,black!50] ({i*3.1},-.5) -- ({i*3.1},{-len(msgs)*.95-1});'+'\n'
    for k,(a,z,label) in enumerate(msgs):
        y=-1.15-k*.95
        if a==z:
            b+=rf'\draw[arr] ({a*3.1},{y}) -- ++(.8,0) -- ++(0,-.4) -- ({z*3.1},{y-.4});'+'\n'
            b+=rf'\node[lab,anchor=west,text width=3.6cm,align=left] at ({a*3.1+1},{y-.18}) {{{esc(label)}}};'+'\n'
        else:b+=rf'\draw[arr] ({a*3.1},{y}) -- node[lab,above] {{{esc(label)}}} ({z*3.1},{y});'+'\n'
    save(key,title,ch,b,explain,source)

def activity(key,title,steps,dec,explain,source):
    b=r'\node[circle,fill=black,inner sep=0pt,minimum size=5pt] (start) {};'+'\n'
    for i,t in enumerate(steps):
        style='decision' if i==dec else 'box'
        w=3.2 if i==dec else 4.6
        previous='start' if i==0 else 's'+str(i-1)
        b+=rf'\node[{style},text width={w}cm,below=8mm of {previous}] (s{i}) {{{esc(t)}}};'+'\n'
        label=r' node[lab,right] {Có}' if i-1==dec else ''
        b+=rf'\draw[arr] ({previous}.south) --{label} (s{i}.north);'+'\n'
    errors={
      'act_care':('Thông báo điều kiện còn thiếu\nGiữ lượt đang xử lý\nBổ sung hồ sơ hoặc kết quả',3),
      'act_roster':('Giữ ca dự kiến\nTải lại phương án\nRà soát và điều chỉnh',3),
      'act_dispense':('Hoàn tác toàn bộ đợt cấp\nThông báo thiếu thuốc\nĐiều chỉnh lượng yêu cầu',0),
      'act_payroll':('Không lưu thay đổi\nTải lại bảng lương\nXử lý trạng thái và phiên bản',3)}
    text,back=errors[key]
    formatted=esc(text).replace(chr(10),r'\\')
    b+=rf'\node[box,text width=3.6cm,right=18mm of s{dec}] (err) {{{formatted}}};'+'\n'
    b+=rf'\draw[arr] (s{dec}.east) -- node[lab,above] {{Không}} (err.west);'+'\n'
    b+=rf'\draw[arr] (err.north) |- (s{back}.east);'+'\n'
    b+=rf'\node[circle,draw=black,minimum size=9pt,inner sep=0pt,below=8mm of s{len(steps)-1}] (end) {{\tikz\fill (0,0) circle (2.5pt);}};'+'\n'
    b+=rf'\draw[arr] (s{len(steps)-1}.south) -- (end.north);'+'\n'
    save(key,title,2,b,explain,source)
activity('act_care','Hoạt động: từ đặt hẹn đến hoàn tất khám',[
'Đặt hẹn hoặc tiếp nhận vãng lai','Xác nhận, tiếp nhận nếu có hẹn','Bắt đầu lượt khám','Ghi hồ sơ, đơn và chỉ định','Hồ sơ đã ký, không còn đơn nháp hoặc chỉ định mở?','Hoàn tất lượt khám\nvà công bố theo quyền'],4,
'Người bệnh vãng lai được tiếp nhận trực tiếp mà không cần đặt hẹn. Khi chưa đủ điều kiện hoàn tất, lượt khám tiếp tục ở trạng thái đang xử lý để bổ sung hồ sơ, đơn thuốc hoặc kết quả. Sau bước hoàn tất, thu ngân và nhân sự cấp phát thực hiện các nghiệp vụ tiếp theo trong phạm vi quyền được cấp.', 'VisitWorkflow; AppointmentBookingService')
activity('act_roster','Hoạt động: đăng ký, xếp và chốt lịch nhân viên',[
'Nhân viên gửi nguyện vọng WAITING','Admin chọn tuần và cơ sở','Xếp tự động hoặc bằng tay thành DRAFT','Rà soát ca và tải phút','Fingerprint, người và giờ còn hợp lệ?','Chốt CONFIRMED và công bố'],4,
'Lưu nguyện vọng không có nghĩa ca chính thức. Nhánh lỗi khi chốt giữ nguyên các ca dự kiến; người quản trị phải tải lại hoặc sửa điều kiện trước khi chốt. Bảng theo cơ sở giúp nhìn thiếu nhân sự nhưng phạm vi thao tác vẫn kiểm tại server.', 'StaffRosterService')
activity('act_dispense','Hoạt động: phân bổ FEFO và rollback',[
'Nhận lượng thực cấp và khóa yêu cầu','Kiểm đơn ký, quyền và lượng chưa cấp','Đọc lô còn hạn theo expiry_date, id','Phân bổ lần lượt, ghi biến động','Tất cả dòng đủ tồn và hợp lệ?','Commit đợt cấp và trạng thái'],4,
'Các lần giảm ở trước điểm quyết định vẫn nằm trong transaction. Khi thiếu, nhánh lỗi rollback toàn bộ thay vì cấp một phần âm thầm. Nếu muốn cấp một phần, người dùng gửi lượng thực cấp nhỏ hơn phù hợp và hệ thống lưu rõ đợt đó.', 'InventoryService::dispense')
activity('act_payroll','Hoạt động: đối chiếu và ghi nhận trả lương',[
'Thiết lập đơn giá riêng theo người','Lấy ca đã kết thúc và chụp nguồn','Tính số công và số tiền gợi ý','Admin đối chiếu công, khoản cộng/trừ','Version đúng và bảng UNPAID?','Lưu điều chỉnh hoặc ghi nhận PAID'],4,
'Chứng từ PAID khóa sửa tiền. Nếu cần sửa sau trả, admin mở về UNPAID có lý do trước. Nguồn giờ là lịch, nên đối chiếu thực tế là bước nghiệp vụ bắt buộc trước khi coi số tiền là khoản cần chi.', 'PayrollService')

def state(key,title,states,links,explain,source):
    b=''
    for i,s in enumerate(states):
        if key=='st_transfer':x,y=((0,0) if i==0 else (8,3-(i-1)*3))
        elif key=='st_payroll':x,y=0,-i*4
        else:x,y=(i%3)*7,-(i//3)*4.4
        b+=node('q'+str(i),x,y,s,'state',3.5)
    if key=='st_payroll':b+=r'\fill (3,1.3) circle (.08); \draw[arr] (3,1.3) -- (q0.north east);'+'\n'
    elif key=='st_staff_request':b+=r'\fill (-2.8,1.4) circle (.08); \draw[arr] (-2.8,1.4) -- (q0.north west);'+'\n'
    else:b+=r'\fill (-2.8,0) circle (.08); \draw[arr] (-2.8,0) -- (q0);'+'\n'
    for a,z,label in links:
        if a==z:
            b+=rf'\draw[arr] (q{a}.north west) .. controls ($(q{a}.north west)+(-.6,1.7)$) and ($(q{a}.north east)+(.6,1.7)$) .. (q{a}.north east);'+'\n'
            b+=rf'\node[lab,anchor=south,text width=3.5cm] at ($(q{a}.north)+(0,1.25)$) {{{esc(label)}}};'+'\n'
        elif key=='st_payroll':
            side='east' if a==0 else 'west'; angle='30' if a==0 else '210'
            b+=rf'\draw[arr] (q{a}.{side}) to[out={angle},in={-int(angle)},looseness=1.5] node[lab,{"right" if a==0 else "left"},text width=3.1cm] {{{esc(label)}}} (q{z}.{side});'+'\n'
        elif (a,z)==(2,1) and key in ['st_visit','st_staff_request']:
            b+=rf'\draw[arr] (q{a}.south) to[out=220,in=320] node[lab,below,text width=3cm] {{{esc(label)}}} (q{z}.south);'+'\n'
        elif key=='st_visit' and (a,z)==(2,4):
            b+=rf'\draw[arr] (q2.south east) -- (16,-1.5) -- (16,-6.4) -- node[lab,below] {{{esc(label)}}} (7,-6.4) -- (q4.south);'+'\n'
        elif key=='st_staff_request' and (a,z)==(0,4):
            b+=rf'\draw[arr] (q0.west) -- (-3,0) -- (-3,-6.5) -- node[lab,below] {{{esc(label)}}} (7,-6.5) -- (q4.south);'+'\n'
        else:
            b+=rf'\draw[arr] (q{a}) -- node[lab,above,sloped,text width=3cm] {{{esc(label)}}} (q{z});'+'\n'
    save(key,title,2,b,explain,source)
state('st_appointment','Trạng thái lịch hẹn hiện hành',
['PENDING','CONFIRMED','CHECKED_IN','CANCELLED','NO_SHOW','COMPLETED'],
[(0,1,'lễ tân xác nhận'),(0,3,'hủy hợp lệ'),(1,3,'hủy hợp lệ'),(1,2,'đúng ngày; chưa Visit'),(1,4,'đã hết giờ hẹn'),(2,5,'Visit hoàn tất'),(0,0,'đổi ngày/giờ hợp lệ'),(1,1,'đổi ngày/giờ hợp lệ')],
'PENDING là chờ xác nhận; CONFIRMED là đã xác nhận. CHECKED_IN đánh dấu đã tiếp nhận và tạo lượt khám. Quá trình khám được theo dõi riêng trên lượt khám. Đổi lịch hợp lệ giữ nguyên trạng thái PENDING hoặc CONFIRMED; hủy và vắng hẹn giải phóng chỗ đặt.', 'Appointment; StaffAppointmentController; VisitWorkflow')
state('st_visit','Trạng thái lượt khám',
['WAITING','IN_PROGRESS','WAITING_RESULT','CANCELLED','COMPLETED','CLOSED'],
[(0,1,'bắt đầu khám'),(0,3,'hủy'),(1,2,'chờ kết quả'),(2,1,'tiếp tục khám'),(1,4,'đủ điều kiện'),(2,4,'đủ điều kiện'),(1,3,'hủy'),(4,5,'đóng lượt')],
'Điều kiện COMPLETED được kiểm ở service: hồ sơ đã ký, không đơn nháp, không chỉ định chưa hoàn tất/hủy. Mốc started_at chỉ ghi lần bắt đầu đầu tiên; completed_at ghi lúc hoàn tất. Đóng lượt không cho tự sửa tự do, chỉ điều chỉnh hồ sơ ký theo quy trình.', 'VisitWorkflow::transition')
state('st_staff_request','Trạng thái nguyện vọng nhân viên',
['WAITING','ARRANGED','CONFIRMED','REJECTED','CANCELLED'],
[(0,1,'xếp DRAFT'),(1,1,'sửa phương án'),(1,2,'chốt toàn tập'),(2,1,'mở chốt có lý do'),(0,3,'admin từ chối'),(1,3,'admin từ chối'),(0,4,'nhân viên rút'),(1,4,'nhân viên rút')],
'Đây là trạng thái request, không phải của StaffWorkShift. Ca tương ứng dùng DRAFT/CONFIRMED/CANCELLED. Rút hoặc từ chối ca chưa chốt hủy phương án liên quan; ca đã chốt phải được admin mở chốt trước khi điều chỉnh.', 'StaffRosterService; StaffScheduleRequest; StaffWorkShift')
state('st_transfer','Trạng thái yêu cầu chuyển ca bác sĩ',
['PENDING','ACCEPTED','REJECTED','CANCELLED'],
[(0,1,'đồng ý; hợp lệ'),(0,2,'người nhận từ chối'),(0,3,'người gửi rút')],
'Yêu cầu chỉ được phản hồi khi đang PENDING và ca chưa bắt đầu. Khi chấp nhận, hệ thống kiểm tra lại chuyên môn, cơ sở và thời gian rảnh của người nhận, rồi chuyển ca cùng các lịch hẹn còn hiệu lực trong một giao dịch. Từ chối hoặc rút yêu cầu giữ nguyên người phụ trách.', 'ShiftTransferRequest::canRespond; ShiftTransferService')
state('st_payroll','Trạng thái ghi nhận thanh toán lương',
['UNPAID','PAID'],[(0,0,'đối chiếu; sửa/tính lại với version'),(0,1,'admin ghi đã thanh toán'),(1,0,'admin mở lại; có lý do')],
'Quản trị viên ghi nhận PAID sau khi đối soát chi trả ngoài hệ thống. Bảng đã thanh toán khóa các khoản tiền. Khi cần sửa, quản trị viên chuyển về UNPAID kèm lý do; hệ thống lưu dấu vết thay đổi trước khi cho phép điều chỉnh và ghi nhận chi trả lại.', 'PayrollService; PayrollEntry')

relation_diagram('architecture','Kiến trúc ứng dụng và phụ thuộc',3,
[('Browser',0,0,'Blade HTML; CSS; JavaScript'),('HTTP',6,0,'Apache → public/index.php'),('Controllers',12,-3,'Kiểm dữ liệu; phản hồi'),('Middleware',6,-3,'auth; role; CSRF; throttle'),('Services',12,-6,'Nghiệp vụ; giao dịch'),('Models_DB',6,-9,'Eloquent; MySQL/SQLite'),('PrivateFiles',0,-6,'Kết quả; PDF; sao lưu'),('Worker',0,-9,'clinic:work; scheduler')],
[('Browser','HTTP','HTTP/form/JSON'),('HTTP','Middleware','pipeline'),('Middleware','Controllers','đủ điều kiện'),('Controllers','Services','gọi nghiệp vụ'),('Services','Models_DB','đọc/ghi/khóa'),('Controllers','PrivateFiles','qua kiểm quyền'),('Worker','Models_DB','outbox và tác vụ')],
'Ứng dụng được tổ chức thành lớp giao diện, tiếp nhận yêu cầu, kiểm soát truy cập, xử lý nghiệp vụ và lưu trữ. Cơ sở dữ liệu lưu thông tin nghiệp vụ cùng hàng đợi thông báo. Tệp riêng được truy cập qua điểm tải có kiểm soát quyền. Các mũi tên thể hiện hướng xử lý và phụ thuộc chính giữa các thành phần.', 'routes; app/Http; app/Services; app/Models')
relation_diagram('deployment','Triển khai bản Laragon trên Windows',3,
[('Client',0,0,'Trình duyệt; phiên host-only'),('Laragon_Apache',6,0,'127.0.0.1:8000; public'),('PHP',12,0,'PHP 8.1.10; Laravel 10'),('MySQL',12,-3.5,'127.0.0.1:3306; InnoDB'),('Storage',6,-3.5,'storage/app; logs; backup'),('CLI',6,3.5,'PowerShell; artisan'),('Mail',0,-3.5,'Log / SMTP khi cấu hình'),('MockSource',12,3.5,'API LIS/Device thử nghiệm')],
[('Client','Laragon_Apache','HTTP local'),('Laragon_Apache','PHP','thực thi request'),('PHP','MySQL','PDO'),('PHP','Storage','file riêng'),('CLI','PHP','worker; backup'),('PHP','Mail','thư outbox'),('MockSource','PHP','POST API + token')],
'Sơ đồ mô tả môi trường học tập hiện tại. Khi triển khai ngoài máy local cần HTTPS và cấu hình hạ tầng riêng; không dùng sơ đồ này để khẳng định có phân cụm, dự phòng hoặc mail production. SQLite test tách khỏi MySQL vận hành.', 'README.md; scripts/php-runtime.ps1; document/Laragon_Troubleshooting.md')

schema=json.loads((BASE/'tools/schema.json').read_text(encoding='utf8'))
erd_groups=[
('identity','Danh tính và phân công',['roles','users','branches','branch_user','user_permissions','doctors','specialties','doctor_specialty']),
('booking','Ca khám và lịch hẹn',['patients','doctors','doctor_schedules','doctor_time_off','appointments','appointment_history','visits','shift_transfer_requests']),
('clinical','Hồ sơ và đơn thuốc',['visits','medical_records','medical_record_revisions','prescriptions','prescription_items','medicines','visit_services','services']),
('diagnostic','Chỉ định, kết quả và tái khám',['visits','service_orders','service_order_items','services','diagnostic_results','device_measurements','follow_up_plans']),
('finance','Hóa đơn và dòng tiền',['visits','invoices','invoice_items','services','payments','refunds']),
('inventory','Kho và cấp phát theo lô',['medicines','medicine_batches','goods_receipts','stock_movements','prescriptions','prescription_items','dispensations','dispensation_items']),
('workforce','Nhân viên và bảng lương',['users','branches','staff_schedule_requests','staff_work_shifts','salary_profiles','payroll_entries']),
('integration','Tích hợp và thông báo',['branches','integration_systems','integration_messages','appointments','notification_jobs','audit_logs'])]
for key,title,names in erd_groups:
    b=''
    for i,n in enumerate(names):
        cols=schema[n]['columns']; relevant=[c['name'] for c in cols if c['pk'] or c['name'].endswith('_id') or c['name'] in ['status','version','work_date','pay_month','event_key','external_message_id']]
        fields='\n'.join(relevant[:6])
        b+=node(n,(i%2)*7.3,-(i//2)*3.1,n+'\n'+fields,'ent',5.5)
    for n in names:
        for fk in schema[n]['foreign_keys']:
            target=fk['table']
            if target not in names:continue
            # Foreign-key arrow only; exact uniqueness/nullability in data dictionary.
            col=fk['from']
            if col in ['created_by','updated_by','paid_by','published_by','granted_by','processed_by','signed_by','verified_by']:continue
            b+=rf'\draw[arr] ({n}) to[bend left=10] node[lab] {{{esc(col)}}} ({target});'+'\n'
    save('erd_'+key,'Lược đồ quan hệ: '+title.lower(),3,b,
    'Đường nối xuất phát ở dòng FK và đi tới khóa được tham chiếu. Hình chỉ chọn các cột và liên kết trong nhóm; khóa actor và liên kết ra nhóm khác được lược bớt. Bội số chính xác được suy từ nullable và chỉ mục UNIQUE trong từ điển dữ liệu. Mũi tên biểu thị tham chiếu dữ liệu, không phải kế thừa.',
    'database/migrations; tools/schema.json (sáu migration; FK/chỉ mục đối chiếu MySQL)')
    # Graphviz-rendered counterpart uses ports and routes around table rectangles.
    if (OUT/('erd_'+key+'.dot')).exists():
        (OUT/('erd_'+key+'.tex')).write_text(r'\includegraphics{figures/diagrams/erd_'+key+'.pdf}\n',encoding='utf8')

# Class design diagrams use real class responsibilities and methods.
classes=[
('class_booking','Lớp thiết kế phân hệ lịch',[
('AppointmentController',0,0,'+store()\n+availability()\n+reschedule(); cancel()'),('AppointmentBookingService',7.3,0,'+book(); availability()\n+reschedule(); cancel()\n+lockDoctor()'),('ScheduleService',0,-4,'Quản lý ca/nghỉ\nKiểm trùng và lặp'),('Appointment',7.3,-4,'+patientCanChange()\n+patientChangeDeadline()\n+visit(); schedule()')], [('AppointmentController','AppointmentBookingService','gọi'),('AppointmentBookingService','Appointment','tạo/cập nhật'),('ScheduleService','AppointmentBookingService','khóa cùng bác sĩ')]),
('class_care','Lớp thiết kế khám và kết quả',[
('VisitController',0,0,'+transition(); record()\n+prescription(); sign()\n+order()'),('VisitWorkflow',7.3,0,'+checkIn(); walkIn()\n+record(); prescription()\n+signPrescription(); order()'),('ClinicAccess',0,-4,'+require()\n+treat(); scope()'),('DiagnosticController',7.3,-4,'+enter(); verify()\n+download(); measurement()')], [('VisitController','VisitWorkflow','điều phối'),('VisitWorkflow','ClinicAccess','kiểm quyền'),('DiagnosticController','ClinicAccess','kiểm quyền')]),
('class_operations','Lớp thiết kế tiền, kho và báo cáo',[
('BillingService',0,0,'+issue(); pay()\n+refund()'),('Money',7.3,0,'+cents()\n+decimal()'),('InventoryService',0,-4,'+receive(); dispense()\n+adjust()'),('ReportService',7.3,-4,'Tổng hợp theo phạm vi\nThu ròng và dư nợ')], [('BillingService','Money','tính chính xác'),('ReportService','Money','chuẩn hóa tiền')]),
('class_hr','Lớp thiết kế lịch nhân viên và lương',[
('StaffRosterService',0,0,'+submit(); arrange()\n+autoArrange(); publish()\n+withdraw(); reopen()'),('StaffWorkShift',7.3,0,'+minutes(); version()\n+user(); branch()'),('PayrollService',0,-4,'+saveProfile(); generate()\n+update(); recalculate()\n+payment()'),('PayrollEntry',7.3,-4,'source_shifts; version\nUNPAID / PAID\n+user()')], [('StaffRosterService','StaffWorkShift','tạo/chốt'),('PayrollService','StaffWorkShift','đọc ca đã kết thúc'),('PayrollService','PayrollEntry','snapshot')])]
for key,title,ents,links in classes:
    relation_diagram(key,title,3,ents,links,
    'Sơ đồ mô tả các lớp trọng tâm, thao tác và trách nhiệm của phân hệ. Dấu + biểu thị thao tác công khai. Các mũi tên thể hiện hướng sử dụng hoặc phụ thuộc giữa các lớp; dữ liệu lưu trữ được trình bày riêng trong lược đồ quan hệ và từ điển dữ liệu.',
    'app/Services; app/Http/Controllers; app/Models')

(BASE/'tools/diagrams.json').write_text(json.dumps(DIAGRAMS,ensure_ascii=False,indent=2),encoding='utf8')
(BASE/'tools/erd-groups.json').write_text(json.dumps([{'key':'erd_'+k,'title':t,'tables':n} for k,t,n in erd_groups],ensure_ascii=False,indent=2),encoding='utf8')
if __name__=='__main__':print(f'{len(DIAGRAMS)} source-backed vector diagrams generated (TikZ/DOT).')
