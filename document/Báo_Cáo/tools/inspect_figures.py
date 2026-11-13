"""Create figure contact sheets and flag intersecting text for visual review."""
import json
from pathlib import Path
import pymupdf as fitz
from PIL import Image, ImageOps, ImageDraw

BASE=Path(__file__).resolve().parents[1]
OUT=BASE/'tools/revision-qa'
OUT.mkdir(exist_ok=True)
items=json.loads((BASE/'tools/diagrams.json').read_text(encoding='utf8'))
warnings=[]
for item in items:
    with fitz.open(BASE/'figures/diagrams'/(item['key']+'.pdf')) as doc:
        lines=[]
        for block in doc[0].get_text('dict')['blocks']:
            if block['type']!=0:continue
            for line in block['lines']:
                text=''.join(s['text'] for s in line['spans']).strip()
                if text:lines.append((fitz.Rect(line['bbox']),text))
        for i,(a,ta) in enumerate(lines):
            for b,tb in lines[i+1:]:
                r=a&b
                if not r.is_empty and r.width>8 and r.height>3 and r.get_area()>.18*min(a.get_area(),b.get_area()):
                    warnings.append({'diagram':item['key'],'text':[ta,tb],'intersection':list(r)})
for start in range(0,len(items),6):
    canvas=Image.new('RGB',(1440,1680),'#e8eeee')
    draw=ImageDraw.Draw(canvas)
    for j,item in enumerate(items[start:start+6]):
        im=Image.open(BASE/'figures/diagrams'/(item['key']+'.png')).convert('RGB')
        thumb=ImageOps.contain(im,(690,510))
        x=(j%2)*720+15;y=(j//2)*560+30
        canvas.paste(thumb,(x,y))
        draw.text((x,y-22),item['key'],fill='black')
    canvas.save(OUT/f'figures-{start//6+1:02d}.png')
(OUT/'text-overlaps.json').write_text(json.dumps(warnings,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'figures':len(items),'text_overlap_candidates':warnings},ensure_ascii=False))
