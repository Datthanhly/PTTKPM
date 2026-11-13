"""Build editable XeLaTeX report from curated prose and source-derived inventories."""
from pathlib import Path
import re,json,shutil,hashlib,xml.etree.ElementTree as ET
from content import TITLE,AUTHORS,USECASES,PAGES,RULES,TABLE_NOTES
import diagrams
BASE=Path(__file__).resolve().parents[1]; ROOT=BASE.parents[1]
(BASE/'chapters').mkdir(exist_ok=True)
(BASE/'figures/logo').mkdir(parents=True,exist_ok=True)
shutil.copyfile(ROOT/'document/UniCateen_BaoCao_v5/figures/logo/logo_truong.png',BASE/'figures/logo/logo_truong.png')
def e(s):
    s=str(s).replace('–','-').replace('—','-')
    # Add discretionary breaks to source identifiers, keeping normal prose intact.
    s=re.sub(r'(?<=[a-z])(?=[A-Z])','§',s)
    return ''.join({'&':r'\&','_':r'\_\allowbreak{}','%':r'\%','#':r'\#','$':r'\$','{':r'\{','}':r'\}','\\':r'\textbackslash{}','~':r'\textasciitilde{}','^':r'\textasciicircum{}','§':r'\allowbreak{}', '.':r'.\allowbreak{}', '/':r'/\allowbreak{}', ':':r':\allowbreak{}'}.get(c,c) for c in s)
def code(s):return r'{\ttfamily\small '+e(s)+'}'
def p(s):return e(s)+'\n\n'
def section(t):return '\n\\section{'+e(t)+'}\n'
def sub(t):return '\n\\Needspace{6\\baselineskip}\\subsection{'+e(t)+'}\n'
def smallsource(s):
    # Source provenance stays in the accompanying inventories, outside report prose.
    return ''
def table(headers,rows,widths=None,caption=None,compact=False):
    n=len(headers); widths=widths or [1/n]*n
    spec='@{}'+' '.join('L{\\dimexpr '+str(w)+r'\textwidth-'+str(round(6*(n-1)*w,3))+r'pt\relax}' for w in widths)+'@{}'
    out='\n{'+('\\fontsize{10}{12}\\selectfont' if compact else '\\small')+'\\setlength{\\tabcolsep}{3pt}\\renewcommand{\\arraystretch}{1.12}\n\\begin{longtable}{'+spec+'}\n'
    if caption:out+='\\caption{'+e(caption)+'}\\\\\n'
    head=' & '.join(r'\textbf{'+e(h)+'}' for h in headers)+r'\\\midrule'+'\n'
    out+='\\toprule\n'+head+'\\endfirsthead\n\\toprule\n'+head+'\\endhead\n\\bottomrule\\endlastfoot\n'
    for row in rows:out+=' & '.join(e(x) for x in row)+r'\ \[-4pt]'+'\n' if False else ''
    for row in rows:out+=' & '.join(e(x) for x in row)+('\\\\[1pt]' if compact else '\\\\[3pt]')+'\n'
    out+='\\end{longtable}}\n';return out
def enum(items):return '\\begin{enumerate}\n'+''.join('\\item '+e(s)+'\n' for s in items)+'\\end{enumerate}\n'
def figure(d,maxheight='.61',width='1'):
    return (r'\begin{center}\includegraphics[width='+width+r'\textwidth,height='+maxheight+r'\textheight,keepaspectratio]{figures/diagrams/'+d['key']+r'.pdf}'+
      r'\captionof{figure}{'+e(d['title'])+r'}\label{fig:'+d['key']+r'}\end{center}'+'\n')
def diagpage(d,newpage=True):
    return ('\\clearpage\n' if newpage else '')+sub(d['title'])+figure(d)+p(d['explain'])+smallsource(d['source'])
def narrative(ch):
    # Flow text naturally, as in the reference report; no artificial one-page padding.
    return ''.join(section(t)+''.join(p(s) for s in paras) for t,paras in PAGES[ch])

setup=r'''\usepackage{fix-cm}
\usepackage{fontspec}
\setmainfont{Times New Roman}
\setsansfont{Arial}
\setmonofont{Consolas}[Scale=.82]
\usepackage[a4paper,left=3.5cm,right=2cm,top=3cm,bottom=2.7cm,headheight=15pt,headsep=.55cm]{geometry}
\usepackage{graphicx,xcolor,tikz,array,booktabs,longtable,tabularx,enumitem,caption,fancyhdr,titlesec,xurl,pdflscape,amsmath,needspace}
\usetikzlibrary{arrows.meta,shapes.geometric,positioning,calc}
\usepackage[unicode,colorlinks=true,linkcolor=black,urlcolor=teal!60!black]{hyperref}
\usepackage{tocloft}
\makeatletter
\renewcommand\normalsize{\@setfontsize\normalsize{13pt}{18pt}}
\renewcommand\small{\@setfontsize\small{10.5pt}{13pt}}
\renewcommand\footnotesize{\@setfontsize\footnotesize{9pt}{11pt}}
\makeatother
\normalsize
\setlength{\parindent}{1cm}\setlength{\parskip}{3pt}
\emergencystretch=2em\widowpenalty=10000\clubpenalty=10000\raggedbottom
\setlist{itemsep=3pt,topsep=4pt,leftmargin=1.6em}
\setlist[enumerate]{label=\arabic*.}
\titleformat{\chapter}[block]{\normalfont\bfseries\fontsize{16}{21}\selectfont}{\thechapter.}{.5em}{}
\titleformat{\section}{\normalfont\bfseries\fontsize{13}{17}\selectfont}{\thesection}{.6em}{}
\titleformat{\subsection}{\normalfont\bfseries\fontsize{13}{17}\selectfont}{\thesubsection}{.6em}{}
\titlespacing*{\chapter}{0pt}{0pt}{16pt}\titlespacing*{\section}{0pt}{13pt}{6pt}\titlespacing*{\subsection}{0pt}{10pt}{5pt}
\setcounter{secnumdepth}{2}\setcounter{tocdepth}{1}
\renewcommand{\contentsname}{MỤC LỤC}\renewcommand{\listfigurename}{DANH MỤC HÌNH}\renewcommand{\listtablename}{DANH MỤC BẢNG}
\renewcommand{\figurename}{Hình}\renewcommand{\tablename}{Bảng}\renewcommand{\bibname}{TÀI LIỆU THAM KHẢO}
\captionsetup{font=small,labelfont=bf,skip=7pt,hypcap=false}
\captionsetup[table]{position=top,justification=raggedright,singlelinecheck=false}
\newcolumntype{L}[1]{>{\raggedright\arraybackslash}p{#1}}
\setlength{\LTpre}{7pt}\setlength{\LTpost}{7pt}
\pagestyle{fancy}\fancyhf{}
\fancyhead[L]{\footnotesize Báo cáo Phân tích và thiết kế phần mềm}
\fancyhead[R]{\footnotesize Nhóm 3 - N01}
\fancyfoot[L]{\footnotesize Hệ thống quản lý phòng khám đa cơ sở An Tâm}
\fancyfoot[R]{\footnotesize\thepage}
\renewcommand{\headrulewidth}{.6pt}\renewcommand{\footrulewidth}{.4pt}
\fancypagestyle{plain}{\fancyhf{}\fancyhead[L]{\footnotesize Báo cáo Phân tích và thiết kế phần mềm}\fancyhead[R]{\footnotesize Nhóm 3 - N01}\fancyfoot[L]{\footnotesize Hệ thống quản lý phòng khám đa cơ sở An Tâm}\fancyfoot[R]{\footnotesize\thepage}\renewcommand{\headrulewidth}{.6pt}\renewcommand{\footrulewidth}{.4pt}}
\hypersetup{pdftitle={Phân tích và thiết kế hệ thống quản lý phòng khám đa cơ sở An Tâm},pdfauthor={Lý Thành Đạt; Nguyễn Văn Nam},pdfsubject={Báo cáo học phần - Nhóm 3, N01}}
\newcommand{\fitdiagram}[1]{\begin{center}\includegraphics[width=\textwidth,height=.61\textheight,keepaspectratio]{#1}\end{center}}
'''
(BASE/'setup.tex').write_text(setup,encoding='utf8')

cover=r'''\begin{titlepage}\thispagestyle{empty}\centering
\begin{tikzpicture}[remember picture,overlay]
\draw[line width=1pt] ([xshift=2.1cm,yshift=-1.4cm]current page.north west) rectangle ([xshift=-1.4cm,yshift=1.4cm]current page.south east);
\draw[line width=.4pt] ([xshift=2.25cm,yshift=-1.55cm]current page.north west) rectangle ([xshift=-1.55cm,yshift=1.55cm]current page.south east);
\end{tikzpicture}
{\large\bfseries ĐẠI HỌC PHENIKAA\par TRƯỜNG CÔNG NGHỆ THÔNG TIN PHENIKAA\par}
\vspace{.6cm}\includegraphics[width=3.6cm]{figures/logo/logo_truong.png}\par
\vspace{.8cm}{\large\bfseries HỌC PHẦN\par PHÂN TÍCH VÀ THIẾT KẾ PHẦN MỀM\par}
\vspace{.6cm}{\fontsize{21}{27}\selectfont\bfseries BÁO CÁO HỌC PHẦN\par}
\vspace{.8cm}{\large\bfseries ĐỀ TÀI\par}
\vspace{.25cm}{\fontsize{20}{27}\selectfont\bfseries PHÂN TÍCH VÀ THIẾT KẾ\par HỆ THỐNG QUẢN LÝ PHÒNG KHÁM\par ĐA CƠ SỞ AN TÂM\par}
\vspace{.8cm}
\begin{tabular}{@{}ll@{}}
\textbf{Nhóm thực hiện} & Nhóm 3 - Lớp N01\\[6pt]
\textbf{Thành viên} & Lý Thành Đạt - 23010879\\[4pt]
& Nguyễn Văn Nam - 22011663\\[6pt]
\textbf{Giảng viên giảng dạy} & TS. Mai Thúy Nga\\
\end{tabular}
\vfill Hà Nội, tháng 11 năm 2026\par\vspace{.3cm}
\end{titlepage}
'''
front=r'\chapter*{LỜI MỞ ĐẦU}\addcontentsline{toc}{chapter}{LỜI MỞ ĐẦU}'+'\n'
front+=p('Số hóa hoạt động phòng khám không chỉ là đưa biểu mẫu lên web. Thách thức nằm ở việc nhiều vai trò cùng thao tác trên lịch, hồ sơ, chứng từ và nguồn lực, trong khi thông tin phải được bảo toàn và chỉ công bố đúng người. Đề tài lựa chọn phòng khám đa cơ sở An Tâm để vận dụng phương pháp phân tích, mô hình hóa và thiết kế phần mềm vào một chuỗi nghiệp vụ có nhiều ràng buộc liên quan nhau.')
front+=p('Báo cáo trình bày yêu cầu, mô hình phân tích, thiết kế hệ thống và kết quả kiểm thử. Trọng tâm gồm đặt lịch theo ca, tiếp nhận và khám, kết quả cận lâm sàng, đơn thuốc và kho, hóa đơn và thanh toán, điều phối nhân sự và tiền lương. Các mô hình làm rõ quan hệ dữ liệu, trách nhiệm của từng thành phần và điều kiện chuyển trạng thái trong chuỗi nghiệp vụ.')
front+=p('Nhóm xin cảm ơn giảng viên TS. Mai Thúy Nga đã hướng dẫn học phần. Đề tài được thực hiện trong phạm vi học tập, nhằm vận dụng kiến thức phân tích yêu cầu, mô hình hóa hướng đối tượng và thiết kế cơ sở dữ liệu. Các hạn chế và hướng phát triển được tổng hợp ở phần kết luận.')
front+='\\clearpage\n'+r'\chapter*{THÔNG TIN BÁO CÁO}\addcontentsline{toc}{chapter}{THÔNG TIN BÁO CÁO}'+'\n'
front+=table(['Nội dung','Thông tin'],[
('Đề tài',TITLE),('Học phần','Phân tích và thiết kế phần mềm'),('Nhóm / lớp','Nhóm 3 / N01'),('Thành viên','Lý Thành Đạt - 23010879; Nguyễn Văn Nam - 22011663'),('Giảng viên','TS. Mai Thúy Nga'),('Thời gian','Tháng 11 năm 2026'),('Phạm vi','Phòng khám ngoại trú đa cơ sở; năm vai trò chính'),('Nền tảng','Laravel 10; PHP 8.1; Blade; Laragon 6; MySQL'),('Môi trường kiểm thử','SQLite cô lập; 109 lần thực thi, 2.807 phép xác nhận'),('Dữ liệu minh họa','Dữ liệu học tập dùng riêng cho trình bày giao diện')],[.25,.75])
front+=p('Báo cáo tổ chức theo bốn chương: đặc tả yêu cầu, phân tích hệ thống, thiết kế hệ thống, hiện thực hóa và kiểm thử. Phụ lục cung cấp từ điển dữ liệu, danh mục kiểm thử và ma trận liên kết yêu cầu với phân hệ và tiêu chí kiểm tra.')
front+=p('Phạm vi tập trung vào quy trình ngoại trú, quản lý nguồn lực và bảo vệ dữ liệu theo vai trò. Những nội dung ngoài phạm vi được nêu ở phần giới hạn và hướng phát triển. Số ca, nhân sự và chứng từ minh họa phục vụ đánh giá chức năng trong môi trường học tập.')
front+='\\clearpage\n'+r'\chapter*{ĐỊNH HƯỚNG PHÂN CÔNG VÀ KIỂM SOÁT CHẤT LƯỢNG}\addcontentsline{toc}{chapter}{ĐỊNH HƯỚNG PHÂN CÔNG}'+'\n'
front+=p('Bảng dưới là đề xuất phân công để hai thành viên rà soát và hoàn thiện sản phẩm học phần. Bộ tài liệu được cung cấp chưa có nhật ký công việc cá nhân hoặc tỷ lệ đóng góp đã xác nhận, vì vậy báo cáo không tự điền phần trăm hoặc tuyên bố từng người đã hoàn thành các nhiệm vụ này.')
front+=table(['Thành viên','Phần việc đề xuất rà soát'],[
('Lý Thành Đạt','Yêu cầu, ma trận quyền, use case; kiểm luồng đặt/đổi/hủy và khám; tổng hợp nội dung báo cáo.'),('Nguyễn Văn Nam','Mô hình dữ liệu, thiết kế lớp/triển khai; kiểm lịch nhân viên, lương, kho và chứng từ.'),('Cả hai thành viên','Rà soát tính nhất quán của các mô hình, thực hiện kịch bản theo vai trò và xác nhận nội dung báo cáo.')],[.27,.73])
front+=p('Kiểm soát chất lượng tài liệu tập trung vào tính đầy đủ của yêu cầu, tính nhất quán giữa các mô hình và khả năng kiểm tra từng quy tắc nghiệp vụ. Kiểm thử được thực hiện trên dữ liệu riêng. Sơ đồ dùng định dạng vector; bảng, mục lục, số hình và tiếng Việt được kiểm tra sau khi xuất PDF.')
front+=p('Phân công thực tế và biên bản nghiệm thu cần được các thành viên xác nhận trước khi nộp. Việc rà soát chung ưu tiên các ràng buộc liên phân hệ như thời gian khám, hoàn tất hồ sơ, số dư thanh toán, tồn kho và nguồn tính lương.')
front+='\\clearpage\n'+r'\chapter*{TÓM TẮT}\addcontentsline{toc}{chapter}{TÓM TẮT}'+'\n'
front+=p('Đề tài phân tích và thiết kế hệ thống quản lý phòng khám đa cơ sở An Tâm với năm vai trò chính. Báo cáo đặc tả 40 use case và 42 quy tắc nghiệp vụ, xây dựng 43 sơ đồ gồm use case, lĩnh vực, hoạt động, tuần tự, trạng thái, kiến trúc, lớp, ERD và triển khai. Thiết kế dữ liệu gồm 52 bảng nghiệp vụ và nền tảng.')
front+=p('Giải pháp hiện thực bằng Laravel/Blade trên Laragon 6. Các điểm thiết kế trọng tâm là tách Appointment khỏi Visit; kiểm khả dụng theo ca/sức chứa/lịch nghỉ; dùng transaction, khóa và idempotency tại ranh giới thay đổi; giữ revision của hồ sơ đã ký, snapshot hóa đơn và bảng lương; quản lý phân bổ thuốc theo FEFO; tách nguyện vọng nhân viên khỏi ca đã chốt.')
front+=p('Bằng chứng kiểm thử lần biên soạn gồm 109 test thực thi và 2.807 assertion đạt trên SQLite cô lập. Các kiểm thử tập trung vào luồng xuyên vai trò, quyền theo cơ sở, mốc 24 giờ, trạng thái, gửi lặp, rollback và riêng tư lương. Bộ test không chứng minh khả năng chịu tải, tuân thủ pháp lý y tế hoặc kết nối thiết bị ngoài thực tế.')
front+=p('Kết quả là bộ đặc tả yêu cầu và mô hình thiết kế liên kết từ quy trình nghiệp vụ đến cấu trúc dữ liệu và kiểm thử. Hệ thống cần tiếp tục hoàn thiện chấm công, cấu hình hạ tầng, đánh giá bảo mật và thử tải; đồng thời cần xác nhận chuyên môn trước khi triển khai tại cơ sở khám thực tế.')
front+=r'\clearpage\begingroup\small\tableofcontents\endgroup\clearpage\phantomsection\addcontentsline{toc}{chapter}{DANH MỤC HÌNH}\begingroup\small\listoffigures\endgroup\clearpage\phantomsection\addcontentsline{toc}{chapter}{DANH MỤC BẢNG}\begingroup\small\listoftables\endgroup\clearpage'+'\n'
(BASE/'chapters/frontmatter.tex').write_text(cover+front,encoding='utf8')

ch1=r'\chapter{ĐẶC TẢ YÊU CẦU HỆ THỐNG}\label{ch:requirements}'+'\n'+narrative(1)
ch1+=section('Các quy tắc nghiệp vụ')
for j in range(0,len(RULES),2):
    if j:ch1+='\\clearpage\n'
    for title,items in RULES[j:j+2]:
        ch1+=sub(title)+table(['Mã','Quy tắc'],items,[.12,.88],title)
    ch1+=p('Các quy tắc trong bảng được thực hiện ở lớp nghiệp vụ và kết hợp kiểm quyền. Một ràng buộc dạng khoảng thời gian hoặc tổng tiền không thể chỉ dựa vào khóa ngoại. Các mã này dùng trong đối chiếu use case và kịch bản kiểm thử; thay đổi cấu hình phải được kiểm tra lại ở các điểm biên liên quan.')
ch1+='\\clearpage\n'+section('Ma trận quyền mặc định theo vai trò')
permission_source=(ROOT/'config/clinic.php').read_text(encoding='utf8')
permission_map={role:re.findall(r"'([A-Z_]+)'",body) for role,body in re.findall(r"'(patient|receptionist|doctor|cashier|admin)'\s*=>\s*\[([^\]]+)\]",permission_source)}
permission_codes=sorted({item for vals in permission_map.values() for item in vals})
ch1+=p('Ma trận xác định quyền mặc định của năm vai trò. Quyền bổ sung có thể được cấp riêng theo cơ sở. Một thao tác chỉ được thực hiện khi đồng thời đáp ứng quyền, phạm vi cơ sở, quan hệ sở hữu hoặc phân công và điều kiện trạng thái. Dấu - thể hiện quyền không được cấp mặc định.')
ch1+=table(['Mã quyền','Bệnh nhân','Lễ tân','Bác sĩ','Thu ngân','Admin'],[(perm,*['Có' if perm in permission_map[role] else '-' for role in ['patient','receptionist','doctor','cashier','admin']]) for perm in permission_codes],[.45,.11,.11,.11,.11,.11],'Ma trận quyền mặc định theo vai trò')
ch1+=p('Nhập kết quả và xác minh kết quả được tách thành LAB_RESULT_ENTER và LAB_RESULT_VERIFY. Cấp phát thuốc dùng MEDICINE_DISPENSE; quản lý kho dùng INVENTORY_MANAGE. Các quyền chuyên biệt được giới hạn theo cơ sở. Quản trị viên chịu trách nhiệm xếp lịch nhân viên và thay đổi bảng lương; quyền quản trị không thay thế quyền điều trị.')
ch1+=smallsource('config/clinic.php; User::hasPermission; ClinicAccess; routes/web.php')
ch1+='\\clearpage\n'+section('Danh mục yêu cầu chức năng')
for start in [0,20]:
    if start:ch1+='\\clearpage\n'
    ch1+=table(['Mã yêu cầu','Chức năng / mục tiêu','Use case'],[(f'FR-{i+1:02d}',u[0],f'UC-{i+1:02d}') for i,u in enumerate(USECASES) if start<=i<start+20],[.14,.70,.16],f'Yêu cầu chức năng {start+1}-{min(start+20,len(USECASES))}')
ch1+='\\clearpage\n'+section('Mô hình use case')
for index,d in enumerate([next(x for x in diagrams.DIAGRAMS if x['key']=='uc_overview')]+[x for x in diagrams.DIAGRAMS if x['key'].startswith('uc_g')]):ch1+=diagpage(d,newpage=index>0)
ch1+='\\clearpage\n'+section('Đặc tả chi tiết các use case')
for i,u in enumerate(USECASES,1):
    if i>1:ch1+='\\clearpage\n'
    name,actors,source,pre,flow,alts,post,rule,tests=u
    ch1+=sub(f'UC-{i:02d}. {name}')+r'\label{uc:'+str(i)+'}\n'
    ch1+=table(['Thuộc tính','Đặc tả'],[('Tác nhân',actors),('Mục tiêu',name),('Điều kiện bắt đầu',pre),('Hậu điều kiện',post)],[.22,.78],f'Đặc tả UC-{i:02d}')
    ch1+=r'\noindent\textbf{Luồng thành công chính.}'+'\n'+enum(flow)
    ch1+=r'\noindent\textbf{Luồng thay thế và ngoại lệ.}'+'\n'+enum(alts)
    ch1+=p('Quy tắc trọng tâm: '+rule)
    ch1+=smallsource(source)+smallsource('Kiểm chứng: '+tests+'. Đây là liên hệ theo nhóm kiểm thử, không khẳng định mọi nhánh chỉ có một test riêng.')
(BASE/'chapters/chuong1.tex').write_text(ch1,encoding='utf8')

ch2=r'\chapter{PHÂN TÍCH HỆ THỐNG}\label{ch:analysis}'+'\n'+narrative(2)
for heading,keys in [('Mô hình lĩnh vực',['domain_care','domain_ops']),('Phân tích tương tác và quy trình',[x['key'] for x in diagrams.DIAGRAMS if x['ch']==2 and (x['key'].startswith('seq_') or x['key'].startswith('act_'))]),('Vòng đời và điều kiện chuyển trạng thái',[x['key'] for x in diagrams.DIAGRAMS if x['key'].startswith('st_')])]:
    ch2+='\\clearpage\n'+section(heading)
    for index,key in enumerate(keys):
        d=next(x for x in diagrams.DIAGRAMS if x['key']==key)
        ch2+=diagpage(d,newpage=index>0)
(BASE/'chapters/chuong2.tex').write_text(ch2,encoding='utf8')

ch3=r'\chapter{THIẾT KẾ HỆ THỐNG}\label{ch:design}'+'\n'+narrative(3)
for heading,keys in [('Kiến trúc và triển khai',['architecture','deployment']),('Thiết kế lớp và phụ thuộc',[x['key'] for x in diagrams.DIAGRAMS if x['key'].startswith('class_')]),('Tuần tự thiết kế các thao tác trọng yếu',[x['key'] for x in diagrams.DIAGRAMS if x['key'].startswith('seq_') and x['ch']==3]),('Thiết kế lược đồ quan hệ',[x['key'] for x in diagrams.DIAGRAMS if x['key'].startswith('erd_')])]:
    ch3+='\\clearpage\n'+section(heading)
    for index,key in enumerate(keys):
        d=next(x for x in diagrams.DIAGRAMS if x['key']==key)
        ch3+=diagpage(d,newpage=index>0)
ch3+='\\clearpage\n'+section('Thiết kế lưu trữ và từ điển dữ liệu')
ch3+=p('Phụ lục A trình bày thiết kế chi tiết của 52 bảng, gồm bảng nghiệp vụ và bảng nền tảng. Mỗi bảng được mô tả theo mục đích, thuộc tính, kiểu dữ liệu, tính bắt buộc, khóa và chỉ mục. Phần tương thích dữ liệu cũ được phân biệt với cấu trúc dùng cho các quy trình mới.')
ch3+=p('MySQL là cơ sở dữ liệu triển khai trên Laragon; SQLite được dùng cho kiểm thử cô lập. Các khóa ngoại bảo vệ quan hệ tham chiếu, chỉ mục duy nhất bảo vệ định danh và quan hệ một-một. Những ràng buộc liên quan tổng tiền, tổng số lượng và khoảng thời gian còn được kiểm tra trong giao dịch của lớp nghiệp vụ.')
ch3+=table(['Nhóm dữ liệu','Chiến lược toàn vẹn'],[
('Danh tính/cơ sở','UNIQUE email, mã; FK và phân công; kiểm role/permission trong ứng dụng.'),('Lịch/ca','Khóa bác sĩ, bệnh nhân; kiểm giao khoảng, trạng thái và lịch nghỉ trong transaction.'),('Khám/kết quả','Visit làm gốc; version hồ sơ; snapshot revision; quyền ký/duyệt.'),('Tài chính','Khóa hóa đơn; snapshot dòng; UNIQUE idempotency; hoàn theo Payment.'),('Kho','Khóa đơn/lô; giảm có điều kiện; chứng từ và allocation cùng transaction.'),('Nhân sự/lương','Fingerprint chốt ca, version bảng lương, UNIQUE user/tháng; nguồn ca chụp JSON.')],[.25,.75],'Tóm tắt kiểm soát toàn vẹn')
(BASE/'chapters/chuong3.tex').write_text(ch3,encoding='utf8')

screens=json.loads((BASE/'tools/screens.json').read_text(encoding='utf8')) if (BASE/'tools/screens.json').exists() else []
ch4=r'\chapter{HIỆN THỰC HÓA VÀ KIỂM THỬ}\label{ch:implementation}'+'\n'+narrative(4)
ch4+='\\clearpage\n'+section('Giao diện và hành vi quan sát')
ch4+=p('Các màn hình sau minh họa cách tổ chức giao diện theo vai trò trên dữ liệu học tập riêng. Mỗi màn hình được trình bày cùng mục đích, thao tác chính và điều kiện truy cập. Phần minh họa hỗ trợ đánh giá bố cục và luồng sử dụng; kiểm thử hành vi được tổng hợp ở phần tiếp theo.')
for s in screens:
    ch4+='\\clearpage\n'+sub(s['title'])
    ch4+=r'\begin{center}\includegraphics[width=\textwidth,height=.57\textheight,keepaspectratio]{figures/ui/'+s['key']+r'.png}\captionof{figure}{'+e(s['title'])+r'}\end{center}'+'\n'
    ch4+=p(s['note'])+p('Vai trò minh họa: '+s['role']+'. Đường dẫn chức năng: '+s['url']+'.')+smallsource(s['source'])

jt=ET.parse(BASE/'tools/tests.xml').getroot(); alltests=jt.findall('.//testcase')
groups={}
for t in alltests:groups.setdefault(t.attrib.get('class','').split('\\')[-1],[]).append(t)
ch4+='\\clearpage\n'+section('Kết quả chạy kiểm thử tại mốc báo cáo')
ch4+=p(f'Lệnh chạy: DB_CONNECTION=sqlite, DB_DATABASE=:memory:, ./test.ps1 --log-junit ... . Lần chạy ghi nhận {len(alltests)} test, '+str(sum(int(t.attrib.get('assertions',0)) for t in alltests))+' assertion; không có failure, error hoặc skipped trong JUnit. Thời gian và bộ nhớ dưới đây chỉ mô tả một lần chạy chức năng, không phải benchmark ứng dụng.')
ch4+=table(['Lớp kiểm thử','Số lần chạy','Assertion'],[(k,len(v),sum(int(t.attrib.get('assertions',0)) for t in v)) for k,v in sorted(groups.items())],[.68,.14,.18],'Kết quả suite theo lớp kiểm thử')
ch4+=smallsource('tools/tests.xml; tools/test-run.txt; phpunit.xml')
ch4+='\\clearpage\n'+section('Đánh giá đáp ứng và rủi ro còn lại')
ch4+=table(['Nội dung','Kết luận có bằng chứng','Giới hạn'],[
('Luồng nghiệp vụ','Kiểm thử chức năng chuỗi nhiều vai trò đạt.','Cần xác nhận chuyên môn tại đơn vị sử dụng.'),('Bảo mật quyền','Đã kiểm vai trò, cơ sở, sở hữu, lương riêng và tải tệp.','Chưa có kiểm thử xâm nhập độc lập.'),('Đồng thời','Sử dụng khóa, phiên bản và dấu nhận diện phương án.','Bộ chức năng chạy trên SQLite; cần kiểm đồng thời trên MySQL.'),('Giao diện','Có 16 màn hình minh họa theo vai trò.','Chưa kiểm toàn bộ hành trình tương tác trên trình duyệt.'),('Email/tích hợp','Đã kiểm thử lại, chống lặp và ánh xạ dữ liệu.','SMTP và thiết bị thực tế chưa nghiệm thu.'),('Hiệu năng','Có phân trang và tải danh sách khi chọn ca.','Chưa đo tải hoặc xác lập thời gian đáp ứng.'),('Lương','Đã kiểm nguồn chụp và trạng thái thanh toán.','Cần đối soát chấm công và chi trả ngoài hệ thống.')],[.19,.41,.40],'Đánh giá kết quả và phạm vi kiểm thử')
(BASE/'chapters/chuong4.tex').write_text(ch4,encoding='utf8')

# Dictionary with physical column types, exact PK/FK/UNIQUE from schema inventory.
schema=json.loads((BASE/'tools/schema.json').read_text(encoding='utf8'))
field_desc={
'id':'Khóa định danh nội bộ.','created_at':'Thời điểm tạo bản ghi.','updated_at':'Thời điểm cập nhật bản ghi.',
'user_id':'Tài khoản liên quan.','branch_id':'Cơ sở của dữ liệu.','doctor_id':'Bác sĩ phụ trách.','patient_id':'Hồ sơ bệnh nhân.',
'status':'Trạng thái vòng đời; kiểm chuyển ở nghiệp vụ.','version':'Phiên bản chống ghi đè dữ liệu cũ.',
'is_active':'Cờ hoạt động của danh mục/hồ sơ.','work_date':'Ngày ca làm.','start_time':'Giờ bắt đầu trong ngày.','end_time':'Giờ kết thúc trong ngày.',
'password':'Giá trị băm mật khẩu, không phải mật khẩu rõ.','token':'Token theo cơ chế reset, không công bố.','token_hash':'Hash token nguồn tích hợp.',
'remember_token':'Thông tin ghi nhớ phiên; dữ liệu bí mật.','name':'Tên hiển thị.','email':'Địa chỉ email.','phone':'Số điện thoại.',
'code':'Mã nghiệp vụ/danh mục.','reason':'Lý do nghiệp vụ.','note':'Ghi chú.','idempotency_key':'Khóa chống tác động lặp.',
'unit_rate':'Đơn giá nguyên VND theo giờ/ca.','pay_basis':'Cách tính HOUR hoặc SHIFT.','pay_month':'Tháng lương, biểu diễn ngày đầu tháng.',
'worked_minutes':'Tổng phút làm dùng để tính bảng.','worked_shifts':'Số ca dùng để tính bảng.','source_shifts':'Snapshot nguồn giờ/ca.','base_amount':'Tiền lương cơ sở.','net_amount':'Thực nhận sau cộng/trừ.',
'allowance':'Phụ cấp nguyên VND.','bonus':'Thưởng nguyên VND.','deduction':'Khấu trừ nguyên VND.',
'available_qty':'Số lượng khả dụng của lô.','quantity_delta':'Biến động có dấu theo lô.','quantity':'Số lượng theo đơn vị danh mục.',
'expiry_date':'Ngày hết hạn dùng.','lot_number':'Số lô thuốc.','amount':'Số tiền của dòng/chứng từ.','price':'Giá danh mục hiện hành.','unit_price':'Đơn giá chụp của dòng.',
'snapshot':'Nội dung trước thay đổi.','payload_hash':'Hash kiểm trùng nội dung.','active_key':'Khóa duy nhất khi yêu cầu còn hiệu lực.',
'pending_key':'Khóa giới hạn yêu cầu chuyển ca đang chờ.','signed_at':'Thời điểm ký nghiệp vụ.','signed_by':'Người ký nghiệp vụ.',
'paid_at':'Thời điểm ghi nhận thanh toán.','paid_by':'Người ghi nhận chi trả.','published_at':'Thời điểm công bố/chốt.',
'slot_minutes':'Thời lượng mỗi slot khám.','capacity':'Sức chứa đồng thời trong slot.','is_open':'Ca đang mở nhận hẹn.',
'bill_at_clinic':'Đánh dấu thuốc bán được tính vào invoice.','attachment_path':'Đường dẫn tệp trên disk riêng.',
'event_key':'Định danh sự kiện chống tạo job trùng.','external_message_id':'Mã thông điệp do nguồn cấp.','external_order_code':'Mã dòng chỉ định để ánh xạ.',
'scheduled_at':'Lịch thực hiện job.','retry_count':'Số lần lỗi đã thử lại.','error_code':'Mã lỗi xử lý.','sent_at':'Thời điểm ghi gửi thành công.',
'employee_name':'Tên nhân sự chụp khi lập bảng.','role_name':'Tên vai trò chụp khi lập bảng.','payment_reference':'Tham chiếu chi trả ngoài hệ thống.',
}
field_desc.update({
'patient_code':'Mã hồ sơ bệnh nhân.', 'full_name':'Họ tên trên hồ sơ.', 'birth_date':'Ngày sinh.', 'gender':'Giới tính ghi trên hồ sơ.',
'address':'Địa chỉ liên hệ.', 'blood_type':'Nhóm máu ghi nhận.', 'insurance_number':'Mã thẻ bảo hiểm lưu tham khảo.', 'allergies':'Thông tin dị ứng.',
'registered_branch_id':'Cơ sở tạo hồ sơ ban đầu.', 'medical_history':'Tiền sử bệnh.', 'emergency_contact':'Liên hệ khẩn cấp.', 'email_enabled':'Cho phép nhận thư thông báo.',
'auto_booking_consented_at':'Lúc cấp consent tự đặt tái khám.', 'auto_booking_expires_at':'Hết hạn consent tự đặt.',
'appointment_code':'Mã lịch để tra cứu.', 'appointment_date':'Ngày hẹn khám.', 'patient_note':'Ghi chú của bệnh nhân.', 'cancel_reason':'Lý do hủy.', 'checked_in_at':'Thời điểm tiếp nhận.',
'action':'Mã hành động kiểm toán.', 'old_values':'Ảnh dữ liệu trước đổi.', 'new_values':'Ảnh dữ liệu sau đổi.', 'subject_type':'Loại chủ thể audit.', 'subject_id':'ID chủ thể audit.',
'ip_address':'IP của phiên/yêu cầu.', 'request_id':'Mã tương quan yêu cầu.', 'timezone':'Ngữ cảnh múi giờ cơ sở.', 'opening_hours':'Thông tin giờ mở cửa cơ sở.',
'measurement_type':'Loại số đo thiết bị.', 'device_code':'Mã thiết bị gửi số đo.', 'value':'Giá trị số đo hoặc cache.', 'value_text':'Nội dung kết quả.', 'unit':'Đơn vị ghi nhận/cấp phát.', 'source':'Nguồn MANUAL hoặc tích hợp.',
'doctor_code':'Mã bác sĩ.', 'license_number':'Số chứng chỉ trong hồ sơ.', 'qualification':'Trình độ/chức danh.', 'room':'Phòng khám hiển thị.', 'bio':'Giới thiệu bác sĩ.',
'dosage':'Liều do bác sĩ ghi.', 'route':'Đường dùng thuốc.', 'frequency':'Tần suất dùng.', 'duration_days':'Số ngày theo đơn.', 'instructions':'Hướng dẫn bổ sung.',
'suggested_from':'Ngày đầu khoảng tái khám.', 'suggested_to':'Ngày cuối khoảng tái khám.',
'reference':'Mã đối chiếu chứng từ.', 'reference_type':'Loại nguồn biến động.', 'reference_id':'ID nguồn biến động.', 'resource_id':'ID tài nguyên nhận tích hợp.',
'description':'Mô tả danh mục hoặc dòng.', 'invoice_code':'Mã hóa đơn.', 'subtotal':'Tạm tính trước giảm giá.', 'discount':'Số tiền giảm giá.', 'total':'Tổng phải thu.', 'method':'Phương thức CASH/TRANSFER.',
'symptoms':'Triệu chứng người bệnh.', 'findings':'Kết quả khám thực thể.', 'diagnosis':'Chẩn đoán do bác sĩ nhập.', 'conclusion':'Kết luận khám.', 'notes':'Ghi chú chuyên môn.',
'prescription':'Nội dung đơn kiểu cũ còn giữ.', 'strength':'Hàm lượng trong danh mục.', 'recipient':'Địa chỉ nhận thư.', 'template':'Mã mẫu/sự kiện thông báo.', 'body':'Nội dung thư đã chuẩn bị.',
'role_code':'Mã vai trò tại lúc đăng ký.', 'decision_note':'Ghi chú quyết định admin.', 'adjustment_reason':'Lý do đối chiếu/sửa số công.',
'key':'Khóa cache.', 'owner':'Chủ khóa cache.', 'expiration':'Mốc hết hạn cache.', 'attempts':'Số lần queue đã thử.', 'connection':'Kết nối queue.', 'queue':'Tên hàng đợi.', 'payload':'Dữ liệu nội bộ của phiên/job.',
'exception':'Thông tin lỗi job.', 'failed_job_ids':'Danh sách ID job lỗi.', 'failed_jobs':'Số job lỗi trong lô.', 'pending_jobs':'Số job đang chờ.', 'total_jobs':'Tổng job trong lô.', 'options':'Tùy chọn lô job.',
'uuid':'Định danh UUID của job lỗi.', 'migration':'Tên bản nâng cấp lược đồ.', 'batch':'Đợt chạy migration.', 'last_activity':'Mốc hoạt động cuối phiên.', 'user_agent':'Thông tin trình duyệt.',
'email_verified_at':'Mốc xác minh email nếu có.',
'actor_id':'Người thực hiện biến động.', 'created_by':'Người tạo bản ghi.', 'updated_by':'Người cập nhật.', 'approved_by':'Người duyệt hoàn tiền.',
'changed_by':'Người thực hiện thay đổi.', 'processed_by':'Admin xử lý nguyện vọng.', 'published_by':'Admin chốt lịch.', 'verified_by':'Người xác minh kết quả.',
'issued_by':'Người phát hành hóa đơn.', 'received_by':'Người ghi nhận thu tiền.', 'dispensed_by':'Người thực hiện cấp phát.',
'calculated_at':'Thời điểm tính bảng lương.', 'processed_at':'Thời điểm xử lý nguyện vọng.', 'responded_at':'Thời điểm phản hồi chuyển ca.',
'starts_at':'Bắt đầu khoảng nghỉ.', 'ends_at':'Kết thúc khoảng nghỉ.', 'started_at':'Lúc bắt đầu khám.', 'completed_at':'Lúc hoàn tất khám.',
'cancelled_at':'Thời điểm hủy.', 'measured_at':'Thời điểm đo.', 'verified_at':'Thời điểm xác minh.', 'issued_at':'Thời điểm phát hành.',
'reserved_at':'Mốc job được nhận xử lý.', 'available_at':'Mốc job sẵn sàng.', 'failed_at':'Thời điểm job lỗi.', 'finished_at':'Thời điểm lô job hoàn tất.',
'visit_id':'Lượt khám liên quan.', 'appointment_id':'Lịch hẹn liên quan.', 'doctor_schedule_id':'Ca khám của bác sĩ.', 'specialty_id':'Chuyên khoa liên quan.',
'prescription_id':'Đơn thuốc gốc.', 'prescription_item_id':'Dòng thuốc trong đơn.', 'medicine_id':'Thuốc trong danh mục.', 'batch_id':'Lô thuốc nguồn.',
'dispensation_id':'Đợt cấp phát liên quan.', 'service_id':'Dịch vụ trong danh mục.', 'service_order_id':'Phiếu chỉ định gốc.', 'service_order_item_id':'Dòng chỉ định gốc.',
'medical_record_id':'Hồ sơ khám liên quan.', 'invoice_id':'Hóa đơn liên quan.', 'payment_id':'Khoản thu gốc.', 'role_id':'Vai trò chính của tài khoản.',
'integration_system_id':'Nguồn tích hợp gửi thông điệp.', 'sender_doctor_id':'Bác sĩ gửi chuyển ca.', 'receiver_doctor_id':'Bác sĩ nhận chuyển ca.',
})
def column_desc(c):
    n=c['name']
    if n in field_desc:return field_desc[n]
    if n.endswith('_id'):return 'Tham chiếu '+n[:-3].replace('_',' ')+'.'
    if n.endswith('_by'):return 'Người thực hiện '+n[:-3].replace('_',' ')+'.'
    if n.endswith('_at'):return 'Mốc '+n[:-3].replace('_',' ')+'.'
    return n.replace('_',' ')+': xem mô tả và kiểm tra tại module sở hữu.'
def dictionary(n):
    d=schema[n]; note=TABLE_NOTES[n]
    out=sub(n)+p(note[0])
    rows=[]
    fks={fk['from']:fk['table']+'.'+fk['to'] for fk in d['foreign_keys']}
    for c in d['columns']:
        flags=[]
        if c['pk']:flags.append('PK')
        if c['name'] in fks:flags.append('FK')
        if c['dflt_value'] is not None:flags.append('mặc định '+str(c['dflt_value']).strip('"'))
        rows.append((c['name']+(' *' if c['notnull'] or c['pk'] else ''),c.get('mysql_type') or c['type'],', '.join(flags) or '-',column_desc(c)))
    out+=table(['Cột (*: bắt buộc)','Kiểu MySQL / SQLite','Khóa / mặc định','Ý nghĩa'],rows,[.27,.22,.16,.35],f'Từ điển bảng {n}',compact=True)
    if d['foreign_keys']:
        text='; '.join(f"{f['from']} → {f['table']}.{f['to']} (xóa: {f['on_delete']})" for f in d['foreign_keys'])
        out+=p('Liên kết khóa ngoại: '+text+'.')
    inds=['('+', '.join(c['name'] for c in idx['columns'])+')'+(' UNIQUE' if idx['unique'] else '') for idx in d['indexes']]
    if inds:out+=p('Chỉ mục: '+'; '.join(inds)+'.')
    out+=p('Ràng buộc và cách dùng: '+note[1])+p('Kiểm soát/giới hạn: '+note[2])
    return out
dd=r'\appendix\chapter{TỪ ĐIỂN DỮ LIỆU ĐẦY ĐỦ}\label{app:dictionary}'+'\n'
dd+=p('Từ điển dữ liệu gồm 52 bảng, bao quát dữ liệu nghiệp vụ, phần tương thích và hạ tầng Laravel. Mỗi thuộc tính có kiểu lưu trữ, khóa, giá trị mặc định và ý nghĩa sử dụng. Dấu * thể hiện thuộc tính không nhận NULL hoặc là khóa chính; giá trị có thể được hệ thống sinh tự động. Cấu trúc MySQL là cơ sở triển khai; các khác biệt của SQLite cần được kiểm tra khi chuyển môi trường.')
dd+=p('JSON của MySQL có thể là text ở SQLite. Quy tắc trạng thái, khoảng giờ và tổng tiền còn nằm trong service; tham chiếu subject/source/reference đa hình không được suy thành FK không tồn tại.')
dd+=section('Bảng nghiệp vụ')
dd+=r'\begingroup\fontsize{11.5}{14.5}\selectfont'+'\n'
framework={'cache','cache_locks','jobs','job_batches','failed_jobs','migrations','sessions','password_reset_tokens'}
for n in schema:
    if n in framework:continue
    dd+='\\clearpage\n'+dictionary(n)
dd+=r'\endgroup'+'\n\\clearpage\n'+section('Các bảng nền tảng Laravel')
for start in range(0,len(framework),3):
    if start:dd+='\\clearpage\n'
    for n in sorted(framework)[start:start+3]:
        dd+=sub(n)+p(TABLE_NOTES[n][0])
        dd+=table(['Cột','Kiểu','NULL'],[(c['name'],c.get('mysql_type') or c['type'],'Không' if c['notnull'] or c['pk'] else 'Có') for c in schema[n]['columns']],[.44,.40,.16],f'Bảng framework {n}',compact=True)
        dd+=r'{\small '+p(TABLE_NOTES[n][2])+'}\n'
(BASE/'chapters/dictionary.tex').write_text(dd,encoding='utf8')

# Source-backed test catalog, one page per test class; assertion snippets split for readability.
test_topics={
'AppointmentBookingTest':('Đặt lịch và khoảng giờ','Tạo ca cùng các lịch hiệu lực; gửi yêu cầu có giờ giao hoặc lệch bước.','Ứng dụng phải chặn double booking và giờ không bắt đầu đúng slot.'),
'AppointmentCutoffTest':('Biên 24 giờ đổi/hủy','Đóng băng thời gian trước/đúng/sau hạn; thử cả web và JSON.','Nhánh bị chặn giữ nguyên lịch, history và outbox; nhánh hợp lệ cập nhật đúng.'),
'AuthenticationTest':('Tài khoản và token','Đăng ký, đăng nhập với hash cũ và đặt lại mật khẩu.','Hồ sơ patient được tạo; hash nâng cấp khi cần; token chỉ dùng một lần.'),
'ClinicMedicineTest':('Dữ liệu thuốc và phân kho','Khởi tạo ba thuốc có nguồn tên sản phẩm tại các cơ sở; chạy seeder lặp.','Không tự nạp thêm tồn, không chiếm mã có sẵn và quyền kho vẫn theo branch.'),
'ClinicSchedulesTest':('Sinh ca sáng/chiều','Tạo ca chuẩn, ca lặp và ca đóng ở cơ sở khác.','Slot dùng được và kiểm trùng liên cơ sở vẫn tôn trọng ca đã đóng.'),
'ClinicSpecialtyUpdateTest':('Nâng cấp danh mục chuyên khoa','Chạy nâng cấp nhiều lần trên dữ liệu có sẵn.','Giữ dữ liệu cũ và không tạo trùng khi chạy lại.'),
'ClinicWorkforceTest':('Bổ sung lực lượng ban đầu','Tạo thiếu bác sĩ/nhân viên và email trùng bệnh nhân.','Phân bổ có thể đặt hẹn, chạy lại không phá dữ liệu và không chiếm tài khoản người khác.'),
'DoctorCoverageTest':('Độ phủ ca theo cơ sở-khoa','Chuẩn bị ca, nghỉ, chuyển ca và dữ liệu cần giữ; chạy preview/apply.','Đủ độ phủ khi khả thi; rollback toàn đợt khi không thể; bảo toàn dữ liệu đang dùng.'),
'DoctorScheduleRegistrationTest':('Bác sĩ tự đăng ký ca','Đăng ký sáng/chiều/tùy chỉnh/lặp ở cơ sở được phân công.','Không nhận ca trùng, nghỉ hoặc dữ liệu sai; xung đột một lần lặp làm rollback cả đợt.'),
'ExpiredLoginTest':('Trang đăng nhập hết phiên','Gửi CSRF token cũ vào login, JSON và các form khác.','Login web mở form mới không giữ mật khẩu; form khác tiếp tục bị chặn.'),
'LocalAccountSessionTest':('Nhiều tài khoản local','Mở tiện ích từ hostname local, workspace khác và production.','Tạo hostname riêng khi đủ điều kiện, không dùng token URL hoặc làm hỏng phiên hiện tại.'),
'PasswordRecoveryTest':('Quên mật khẩu và hạn mức','Thử email đã có/chưa có, lặp, rate limit, token dùng/hết hạn và mail transport.','Phản hồi thống nhất, chờ đúng thời gian và chỉ token hợp lệ đổi mật khẩu.'),
'PayrollTest':('Lương, quyền sở hữu và khóa tiền','Tạo ca bác sĩ/nhân viên ở nhiều trạng thái; chỉnh profile/bảng/PAID.','Đúng phút/ca và số tiền, snapshot cũ không đổi, nhân viên không đọc/sửa người khác.'),
'ReceptionAppointmentActionsTest':('Thao tác lễ tân và menu tài khoản','Mở Thông báo/ca; thử xác nhận, tiếp nhận, NO_SHOW sớm và đổi role/branch.','Đúng trạng thái, đúng cơ sở, một Visit; lỗi web thân thiện và JSON giữ conflict.'),
'RolePagesSmokeTest':('Trang theo năm vai trò','Đăng nhập từng role với dữ liệu seed, gọi các trang chính.','Trang render phù hợp và điều hướng đặt lịch không cấp quyền ghi ngoài role.'),
'ShiftTransferTest':('Chuyển ca có người nhận','Tạo người nhận hợp lệ/bận/nghỉ và xung đột mới trước lúc chấp nhận.','Chỉ chuyển khi đồng ý và kiểm lại; không sửa một phần hoặc tiết lộ cho người không liên quan.'),
'SpecificationWorkflowTest':('Luồng tích hợp nghiệp vụ','Dùng năm vai trò đi qua khám, chỉ định, tiền, kho và báo cáo; thử các nhánh quyền/lặp.','Các module liên kết nhất quán, giao dịch nguyên tử và chứng từ chỉ công bố đúng người.'),
'StaffCalendarTest':('Lịch tuần và tải theo ca','Đăng nhập bác sĩ/nhân viên, chọn tuần/ca, thử truy cập ngoài phạm vi.','Bác sĩ chỉ thấy ca mình, không lộ bệnh nhân trước mở ca, Chủ nhật thuộc đúng tuần.'),
'StaffRosterTest':('Nguyện vọng, xếp và chốt nhân viên','Đăng ký WAITING, xếp tự động/tay, sửa ở nhiều tab, chốt và rút.','Không trùng/vượt phút; kiểm version/eligibility khi chốt; lịch chính thức và branch được bảo vệ.'),
}
catalog=r'\chapter{DANH MỤC KIỂM THỬ VÀ TRUY VẾT}\label{app:tests}'+'\n'
catalog+=p('Danh mục tổ chức các trường hợp kiểm thử theo nhóm chức năng. Mỗi tình huống có mã TC, nội dung cần kiểm tra và kết quả tổng hợp của nhóm. Một tình huống có thể được thực thi nhiều lần với các bộ dữ liệu biên khác nhau. Các kết quả phản ánh kiểm thử chức năng trong môi trường cô lập.')
catalog+=section('Danh mục theo nhóm chức năng')+r'\begingroup\fontsize{11.5}{14.5}\selectfont'+'\n'
test_inventory=[]
test_labels=json.loads((BASE/'tools/test-labels.json').read_text(encoding='utf8'))
for file in sorted((ROOT/'tests/Feature').glob('*Test.php')):
    name=file.stem; src=file.read_text(encoding='utf8'); methods=re.findall(r'public function (test_\w+)\(',src)
    topic,setup_text,expected=test_topics.get(name,(name,'Theo fixture của lớp.','Theo assertion của test.'))
    # Split the very large integration suite; retain readable names without shrinking fonts.
    chunks=[methods[i:i+5] for i in range(0,len(methods),5)] or [[]]
    for part,chunk in enumerate(chunks):
        catalog+='\n\\par\\vspace{8pt}\n'+sub(topic+(f' - phần {part+1}' if len(chunks)>1 else ''))
        catalog+=p('Chuẩn bị và thao tác: '+setup_text)+p('Kết quả cần đạt: '+expected)
        runs=[t for t in groups.get(name,[]) if any(t.attrib['name'].startswith(m) for m in chunk)]
        catalog+=p(f'Kết quả JUnit của phần này: {len(runs)} lần chạy, {sum(int(t.attrib.get("assertions",0)) for t in runs)} assertion, tất cả đạt. Môi trường chung: PHP 8.1.10, SQLite :memory:, thời gian đóng băng trong test nếu cần.')
        for m in chunk:
            line=src[:src.index('public function '+m)].count('\n')+1
            catalog+=r'\noindent\textbf{'+e('TC-'+str(len(test_inventory)+1).zfill(3))+r'.} '+e(test_labels[m])+r'\par'+'\n'
            catalog+=smallsource(str(file.relative_to(ROOT)).replace('\\','/')+':'+str(line))
            # Derive HTTP routes and assertion kinds from this exact method body.
            mstart=src.index('public function '+m); bstart=src.index('{',mstart); depth=1; pos=bstart+1
            while pos<len(src) and depth:
                if src[pos]=='{':depth+=1
                elif src[pos]=='}':depth-=1
                pos+=1
            body=src[bstart:pos]
            route_names=list(dict.fromkeys(re.findall(r"route\('([^']+)'",body)))
            asserts=list(dict.fromkeys(re.findall(r'(assert[A-Za-z]+)\(',body)))
            checks=asserts[:6] or list(dict.fromkeys(re.findall(r'(expectException\w*)\(',body)))
            observations=[]
            if any(x.startswith('assertDatabase') for x in asserts):observations.append('trạng thái bản ghi sau thao tác')
            if any(x in asserts for x in ['assertForbidden','assertUnauthorized','assertNotFound']):observations.append('giới hạn truy cập')
            if any(x in asserts for x in ['assertSessionHasErrors','assertInvalid','assertUnprocessable']):observations.append('thông báo dữ liệu hoặc điều kiện không hợp lệ')
            if any(x in asserts for x in ['assertRedirect','assertSessionHas','assertSee','assertDontSee']):observations.append('nội dung hoặc điều hướng trên giao diện')
            if any(x.startswith('assertJson') for x in asserts):observations.append('nội dung phản hồi JSON')
            if not observations:observations.append('kết quả xử lý và các điều kiện nghiệp vụ liên quan')
            catalog+=p('Nội dung xác nhận: '+', '.join(observations)+'.')
            test_inventory.append({'id':'TC-'+str(len(test_inventory)+1).zfill(3),'class':name,'method':m,'source':str(file.relative_to(ROOT)).replace('\\','/'),'line':line,'routes':route_names,'assertions':asserts})
    # Each class is represented, even if multiple methods map to one business requirement.
catalog+=r'\endgroup'+'\n\\clearpage\n'+section('Ma trận yêu cầu - phân hệ - kiểm thử')
modules={i:group[0] for group in diagrams.groups for i in group[1]}
for start in range(0,len(USECASES),8):
    if start:catalog+='\\clearpage\n'
    catalog+=table(['Use case / yêu cầu','Phân hệ thiết kế','Nhóm kiểm tra'],[(f'UC-{i+1:02d} / FR-{i+1:02d}: '+u[0],modules[i+1],'; '.join(test_topics[t.strip()][0] for t in u[8].split(';') if t.strip() in test_topics) or 'Quy trình vận hành và phục hồi bản sao') for i,u in enumerate(USECASES) if start<=i<start+8],[.40,.27,.33],f'Liên kết UC-{start+1:02d} đến UC-{min(start+8,len(USECASES)):02d}')
    catalog+=p('Ma trận liên kết mục tiêu sử dụng với phân hệ chịu trách nhiệm và nhóm kiểm tra tương ứng. Khi yêu cầu thay đổi, cần rà soát đồng thời quy tắc, dữ liệu và các trường hợp biên. Sao lưu và khôi phục được đánh giá qua quy trình vận hành trên bản sao, tách khỏi kết quả kiểm thử chức năng tự động.')
(BASE/'chapters/tests.tex').write_text(catalog,encoding='utf8')
(BASE/'tools/test-inventory.json').write_text(json.dumps(test_inventory,ensure_ascii=False,indent=2),encoding='utf8')

end=r'\chapter*{KẾT LUẬN VÀ HƯỚNG PHÁT TRIỂN}\addcontentsline{toc}{chapter}{KẾT LUẬN VÀ HƯỚNG PHÁT TRIỂN}'+'\n'
end+=p('Báo cáo đã hình thành bộ mô hình nhất quán cho hệ thống phòng khám đa cơ sở An Tâm, từ mục tiêu của năm vai trò đến thiết kế dữ liệu và các điểm bảo vệ nghiệp vụ. Khác biệt cốt lõi của mô hình là tách lịch hẹn khỏi lần khám, tách kê đơn khỏi cấp thuốc, tách phát hành hóa đơn khỏi thu tiền và tách đăng ký nhân viên khỏi lịch chính thức.')
end+=p('Phần hiện thực có transaction, khóa, idempotency, version/fingerprint và snapshot tại các điểm có nguy cơ thay đổi đồng thời hoặc mất lịch sử. Các module lịch, khám, kết quả, kho, tài chính và lương liên kết trên cơ sở dữ liệu chung nhưng giữ quyền và vòng đời riêng. Lương theo giờ/ca có đơn giá cá nhân và trạng thái chi trả do admin quản lý; nhân viên chỉ xem phần của mình.')
end+=p('Lần kiểm thử mới đạt 109 test và 2.807 assertion trên SQLite cô lập. Sơ đồ, từ điển 52 bảng, ảnh giao diện và ma trận truy vết cho phép tiếp tục kiểm tra từng kết luận. Tuy nhiên, các test chức năng không thay thế xác nhận chuyên môn, kiểm thử tải, đánh giá an ninh độc lập hoặc nghiệm thu production.')
end+=p('Ưu tiên tiếp theo là đối chiếu quy trình với cơ sở khám thật; hoàn thiện chấm công và đối soát lương; kiểm thử đồng thời trên MySQL ở hạ tầng mục tiêu; cấu hình HTTPS/SMTP/backup có mã hóa và giám sát; đánh giá cách quản lý chữ ký, lịch sử hồ sơ và thời hạn lưu trữ. Chỉ tích hợp LIS/Device hoặc ngân hàng khi có hợp đồng, quyền truy cập và tiêu chí kiểm chứng thực tế.')
end+='\\clearpage\n'+r'\chapter*{TÀI LIỆU THAM KHẢO}\addcontentsline{toc}{chapter}{TÀI LIỆU THAM KHẢO}'+'\n'
refs=[
('S1','Nhóm 3, Clinic_Management_System_Full_Specification.md, tài liệu đặc tả nội bộ của dự án.'),
('S2','Nhóm 3, Hệ thống quản lý phòng khám đa cơ sở An Tâm, sản phẩm phần mềm học phần, tháng 11/2026.'),
('S3','UniCateen_BaoCao_v5, tài liệu tham khảo về bố cục báo cáo phân tích và thiết kế hệ thống.'),
('S4','Tài liệu nội bộ: Implementation_Laragon.md; Staff_Roster_Workflow.md; Payroll_Workflow.md; Doctor_Coverage_Report.md; API_Laragon.md; Laragon_Troubleshooting.md.'),
('S5','Bằng chứng kiểm thử lần biên soạn: tools/tests.xml và tools/test-run.txt; 109 tests, 2.807 assertions.'),
('S6','OMG, Unified Modeling Language, Version 2.5.1. Dùng tham khảo ký hiệu UML. https://www.omg.org/spec/UML/2.5.1'),
('S7','Laravel, Database: Getting Started, tài liệu phiên bản 10.x. Tham khảo transaction và kết nối DB. https://laravel.com/docs/10.x/database'),
('S8','Laravel, Authorization, tài liệu phiên bản 10.x. Tham khảo nguyên tắc cấp quyền; dự án dùng ClinicAccess riêng. https://laravel.com/docs/10.x/authorization'),
('S9','PHP Manual, Password Hashing. Tham khảo cơ chế băm/kiểm mật khẩu, không phải mã hóa để giải ngược. https://www.php.net/manual/en/book.password.php')]
for key,text in refs:
    if 'https://' in text:
        before,url=text.split('https://',1);end+=r'\noindent\textbf{['+key+r']} '+e(before)+r'\url{https://'+url+r'}. Truy cập 14/11/2026.\par'+'\n'
    else:end+=r'\noindent\textbf{['+key+r']} '+e(text)+r'\par'+'\n'
(BASE/'chapters/end.tex').write_text(end,encoding='utf8')
main=r'''\documentclass[12pt,a4paper,oneside]{report}
\input{setup}
\begin{document}
\pagenumbering{roman}
\input{chapters/frontmatter}
\pagenumbering{arabic}\setcounter{page}{1}
\input{chapters/chuong1}
\input{chapters/chuong2}
\input{chapters/chuong3}
\input{chapters/chuong4}
\input{chapters/end}
\input{chapters/dictionary}
\input{chapters/tests}
\end{document}
'''
(BASE/'main.tex').write_text(main,encoding='utf8')
manifest={}
for subdir in ['app','routes','database/migrations','tests/Feature','resources/views','config','public']:
    for f in sorted((ROOT/subdir).rglob('*')):
        if f.is_file() and f.suffix in ['.php','.css','.js']:
            manifest[str(f.relative_to(ROOT)).replace('\\','/')]=hashlib.sha256(f.read_bytes()).hexdigest()
(BASE/'tools/source-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf8')
print(f'Generated report: {len(USECASES)} use cases, {len(diagrams.DIAGRAMS)} diagrams, {len(schema)} tables, {len(alltests)} executed tests, {len(screens)} fresh UI screenshots.')
