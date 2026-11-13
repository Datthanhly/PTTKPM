"""Create a cropped vector diagram atlas and individual PNG/SVG/PDF files."""
from pathlib import Path
import json,subprocess
import pymupdf as fitz
BASE=Path(__file__).resolve().parents[1]
items=json.loads((BASE/'tools/diagrams.json').read_text(encoding='utf8'))
source=r'''\documentclass[tikz,border=8pt,multi=tikzpicture]{standalone}
\usepackage{fontspec}\setmainfont{Times New Roman}
\usepackage{xcolor,tikz,graphicx}\usetikzlibrary{arrows.meta,shapes.geometric,positioning,calc}
\begin{document}
'''+''.join((r'\begin{tikzpicture}\node[inner sep=0] {\input{figures/diagrams/'+i['key']+r'.tex}};\end{tikzpicture}'+'\n') if i['key'].startswith('erd_') else r'\input{figures/diagrams/'+i['key']+'.tex}\n' for i in items)+r'\end{document}'
(BASE/'tools/diagram_atlas.tex').write_text(source,encoding='utf8')
cmd=['xelatex','-interaction=nonstopmode','-halt-on-error','-output-directory=tools','-jobname=SoDo_AnTam','tools/diagram_atlas.tex']
with (BASE/'tools/diagram-build.txt').open('w',encoding='utf8') as log:
    result=subprocess.run(cmd,cwd=BASE,stdout=log,stderr=subprocess.STDOUT)
if result.returncode:raise RuntimeError('Diagram atlas compilation failed; see tools/diagram-build.txt')
doc=fitz.open(BASE/'tools/SoDo_AnTam.pdf')
if len(doc)!=len(items):raise RuntimeError(f'Expected {len(items)} cropped diagrams, got {len(doc)}')
for i,item in enumerate(items):
    page=doc[i]; prefix=BASE/'figures/diagrams'/item['key']
    if item['key'].startswith('erd_'):
        native=fitz.open(str(prefix)+'.pdf')
        native[0].get_pixmap(matrix=fitz.Matrix(2,2),alpha=False).save(str(prefix)+'.png')
        native.close()
        continue
    page.get_pixmap(matrix=fitz.Matrix(2,2),alpha=False).save(str(prefix)+'.png')
    Path(str(prefix)+'.svg').write_text(page.get_svg_image(text_as_path=True),encoding='utf8')
    out=fitz.open();out.insert_pdf(doc,from_page=i,to_page=i);out.save(str(prefix)+'.pdf');out.close()
print(f'Exported {len(items)} diagrams in editable TikZ + vector PDF/SVG + PNG.')
