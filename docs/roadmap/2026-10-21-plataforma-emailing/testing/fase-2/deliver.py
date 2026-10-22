from pathlib import Path
import subprocess,os,json,re
import fitz
from PIL import Image,ImageOps,ImageDraw
out=Path(__file__).resolve().parent;root=out.parents[4]
cache=Path.home()/'.claude/tool-cache/to-pdf'
theme=(cache/'theme.css').read_text(encoding='utf-8')+'\npre,code {white-space:pre-wrap;overflow-wrap:anywhere;} table {width:100%;table-layout:auto;break-inside:auto;} tr {break-inside:avoid;} td,th {overflow-wrap:anywhere;font-size:9pt;line-height:1.35;}\n'
(out/'report-theme.css').write_text(theme,encoding='utf-8')
env=os.environ.copy();env['PUPPETEER_EXECUTABLE_PATH']=str(Path(os.environ['LOCALAPPDATA'])/'ms-playwright/chromium-1243/chrome-win64/chrome.exe')
with (out/'raw/artifacts/pdf-export.stdout.txt').open('wb') as log:
 p=subprocess.run(['node',str(cache/'to-pdf.mjs'),str(out/'report.md'),'--out',str(out/'report.pdf'),'--title','QA T09 fase 2 intento 1','--theme',str(out/'report-theme.css')],cwd=root,env=env,stdout=log,stderr=subprocess.STDOUT,creationflags=subprocess.CREATE_NO_WINDOW)
if p.returncode:raise SystemExit('PDF conversion failed')
doc=fitz.open(out/'report.pdf');checks=[];images=[]
for i,page in enumerate(doc):
 text=page.get_text();assert text.strip(),i
 outside=[]
 for block in page.get_text('dict')['blocks']:
  for line in block.get('lines',[]):
   for span in line.get('spans',[]):
    x0,y0,x1,y1=span['bbox']
    if x0 < -1 or x1>page.rect.width+1 or y0 < -1 or y1>page.rect.height+1:outside.append(span['text'][:50])
 checks.append({'page':i+1,'characters':len(text),'text_outside_page':outside})
 pix=page.get_pixmap(matrix=fitz.Matrix(1,1));path=out/f'raw/artifacts/report-page-{i+1}.png';pix.save(path)
 im=Image.open(path).convert('RGB');im.thumbnail((300,430));canvas=Image.new('RGB',(320,460),'#d9e2ec');canvas.paste(im,((320-im.width)//2,18));ImageDraw.Draw(canvas).text((10,440),f'Page {i+1}',fill='black');images.append(canvas)
montage=Image.new('RGB',(320*3,460*((len(images)+2)//3)),'white')
for i,im in enumerate(images):montage.paste(im,((i%3)*320,(i//3)*460))
montage.save(out/'raw/artifacts/pdf-montage.png')
text='\n'.join(p.get_text() for p in doc)
assert 'NO-VERDE' in text and '12' in text and 'QA-01' in text
receipt={'pages':len(doc),'nonempty_pages':True,'page_checks':checks,'gate_visible':True}
(out/'raw/pdf-check.json').write_text(json.dumps(receipt,indent=2))
with (out/'raw/public-data-check.txt').open('wb') as log:
 check=subprocess.run(['python','scripts/check-public-data.py'],cwd=root,stdout=log,stderr=subprocess.STDOUT)
receipt['public_data_exit']=check.returncode
(out/'raw/pdf-check.json').write_text(json.dumps(receipt,indent=2))
print(json.dumps({'pages':len(doc),'outside_page_spans':sum(len(c['text_outside_page']) for c in checks),'public_data_exit':check.returncode}))
