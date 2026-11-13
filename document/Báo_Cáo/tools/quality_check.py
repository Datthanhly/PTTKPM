"""Render every final report page and create visual review contact sheets."""
from pathlib import Path
import json,math
import pymupdf as fitz
from PIL import Image,ImageDraw,ImageFont
BASE=Path(__file__).resolve().parents[1]
OUT=BASE/'tools/qa'; OUT.mkdir(exist_ok=True)
pdf=fitz.open(BASE/'BaoCao_PhongKham_AnTam.pdf')
pages=[]; issues=[]
font=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',17)
for i,page in enumerate(pdf):
    pix=page.get_pixmap(matrix=fitz.Matrix(120/72,120/72),alpha=False)
    pix.save(OUT/f'page-{i+1:03d}.png')
    words=len(page.get_text().split())
    if words<60:issues.append({'page':i+1,'kind':'sparse','words':words})
    for block in page.get_text('dict')['blocks']:
        if block['type']!=0:continue
        for line in block['lines']:
            for span in line['spans']:
                x0,y0,x1,y1=span['bbox']
                if y0>77 and y1<767 and (x0<96 or x1>544):
                    issues.append({'page':i+1,'kind':'outside-text-width','text':span['text'],'bbox':span['bbox']})
    thumb=Image.frombytes('RGB',[pix.width,pix.height],pix.samples)
    thumb.thumbnail((235,334))
    pages.append((i+1,thumb))
for start in range(0,len(pages),20):
    sheet=Image.new('RGB',(5*255,4*370),'#e0e5e5'); dr=ImageDraw.Draw(sheet)
    for j,(number,thumb) in enumerate(pages[start:start+20]):
        x=(j%5)*255+10;y=(j//5)*370+27
        sheet.paste(thumb,(x,y));dr.text((x,y-23),f'{number:03d}',fill='black',font=font)
    sheet.save(OUT/f'contact-{start//20+1:02d}.png')
stats={'pages':len(pdf),'words':sum(len(p.get_text().split()) for p in pdf),'page_size':'A4','issues':issues,'bookmarks':pdf.get_toc()}
(OUT/'layout-audit.json').write_text(json.dumps(stats,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'pages':len(pdf),'issues':issues},ensure_ascii=False))
