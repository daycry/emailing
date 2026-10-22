import http from 'node:http';
import {test,expect,fixture,login,shot,lw,cleanup,resources,uiCall,clickCall} from './helpers.mjs';
const field=(page,name)=>page.locator(`[wire\\:model=${name}]`);
async function create(page,{name,subject='Saved subject',html='<p>Saved HTML</p>',text='Saved TEXT'},f){
 await clickCall(page,'create',()=>page.getByRole('button',{name:'Nueva plantilla',exact:true}).click());
 await expect(field(page,'name')).toHaveValue('');
 for(const [key,value] of Object.entries({name,subject,html,text}))await field(page,key).fill(value);
 expect(await page.locator('form[wire\\:submit=save]').evaluate(form=>form.checkValidity())).toBe(true);
 await clickCall(page,'save',()=>page.getByRole('button',{name:'Guardar plantilla',exact:true}).click());
 await expect.poll(()=>resources(f).email_templates.some(t=>t.name===name)).toBe(true);
 await expect(page.getByRole('status')).toContainText('guardada');
}
test('E2E-06 CA-04/12/13 browser HTML-only image TEXT-only empty rejected variables retry saved version developer scopes',async({page,browser,baseURL},info)=>{
 const f=fixture(),contexts=[];try{
  await login(page,f);await page.goto('/templates');
  await create(page,{name:'HTML only image',html:'<img src="https://example.invalid/image.png" alt="Image only">',text:''},f);
  let s=resources(f);const image=s.email_templates.find(t=>t.name==='HTML only image');expect(image.html).toContain('<img');expect(image.text).toBe('');
  await create(page,{name:'TEXT only',html:'',text:'TEXT ONLY BODY'},f);
  s=resources(f);const text=s.email_templates.find(t=>t.name==='TEXT only');expect(text.html).toBe('');expect(text.text).toBe('TEXT ONLY BODY');
  await page.getByRole('button',{name:'Nueva plantilla',exact:true}).click();
  await field(page,'name').fill('Empty rejected');await field(page,'subject').fill('Empty');await field(page,'html').fill('');await field(page,'text').fill('');
  expect(await page.locator('form[wire\\:submit=save]').evaluate(form=>form.checkValidity())).toBe(true);
  await page.getByRole('button',{name:'Guardar plantilla',exact:true}).click();await expect(page.getByRole('alert')).toBeVisible();expect(resources(f).email_templates.some(t=>t.name==='Empty rejected')).toBe(false);
  await page.reload();await uiCall(page,'open',[f.templates.A]);
  await field(page,'html').fill('<p>{{ contact.name }} / {{ contact.email }} / {{ account.name }}</p>');await field(page,'text').fill('{{ contact.name }}');
  await field(page,'sampleName').fill('<b>{{ contact.email }}</b>');await field(page,'sampleEmail').fill(`sample-${f.run}@example.test`);
  await page.getByRole('button',{name:'Actualizar preview',exact:true}).click();await expect(page.locator('iframe[title]')).toBeVisible();
  let frame=page.frames().find(fr=>fr.parentFrame());expect(await frame.locator('b').count()).toBe(0);await expect(frame.locator('body')).toContainText('<b>{{ contact.email }}</b>');
  for(const invalid of ['<p>{{ unknown.variable }}</p>','<p>{{ contact.name }</p>','<p title="{{ contact.name }}">&#123;&#123; contact.name &#125;&#125;</p>','<p title="{{ contact.name }}">&#x7b;&#x7b; contact.name &#x7d;&#x7d;</p>']){
   await field(page,'html').fill(invalid);await page.getByRole('button',{name:'Actualizar preview',exact:true}).click();await expect(page.locator('iframe')).toHaveCount(0);await expect(page.getByRole('alert')).toBeVisible();
   await field(page,'html').fill('<p>Retry {{ contact.name }}</p>');await page.getByRole('button',{name:'Actualizar preview',exact:true}).click();await expect(page.locator('iframe')).toBeVisible();await expect(page.getByRole('alert')).toHaveCount(0);
  }
  await shot(page,'06-preview-clean-retry',info.project.name);
  const developer=await browser.newContext({baseURL});contexts.push(developer);const dp=await developer.newPage();await login(dp,f,'developer');await dp.goto('/templates');
  await dp.getByRole('button',{name:'Ver y previsualizar',exact:true}).first().click();
  await expect(dp.getByRole('button',{name:'Crear prueba',exact:true})).toBeVisible();
  expect((await dp.request.get('/settings/mail')).status()).toBe(403);expect((await dp.request.get('/contacts')).status()).toBe(403);
  await uiCall(dp,'open',[text.id]);await field(dp,'senderId').selectOption(String(f.senders.A));await field(dp,'recipient').fill(`test-${f.run}@example.test`);
  await dp.getByRole('button',{name:'Crear prueba',exact:true}).click();await expect(dp.getByRole('status')).toContainText('log privado');
  s=resources(f);let message=s.mail_messages.at(-1);let dl=await dp.request.get(`/mail-artifacts/${message.id}/download`);expect(dl.status()).toBe(200);expect(await dl.text()).toContain('TEXT ONLY BODY');
  await uiCall(page,'open',[image.id]);await field(page,'senderId').selectOption(String(f.senders.A));await field(page,'recipient').fill(`image-${f.run}@example.test`);await field(page,'html').fill('<p>UNSAVED ATTACK CONTENT</p>');
  await page.getByRole('button',{name:'Crear prueba',exact:true}).click();await expect(page.getByRole('status')).toContainText('log privado');
  message=resources(f).mail_messages.at(-1);dl=await page.request.get(`/mail-artifacts/${message.id}/download`);const body=await dl.text();expect(body).toContain('Image only');expect(body).not.toContain('UNSAVED ATTACK CONTENT');
  await shot(dp,'06-developer-test-without-settings',info.project.name);
  await page.reload();expect((await lw(page,'open',[f.templates.B])).status).toBe(404);await page.reload();
  expect((await lw(page,'delete',[f.templates.B])).status).toBe(404);await page.reload();
  expect((await lw(page,'test',[],{editing:f.templates.B,senderId:f.senders.A,recipient:`cross-${f.run}@example.test`})).status).toBe(404);
 }finally{await cleanup(f,contexts);}
});

test('E2E-06 CA-04/12 hostile preview has zero execution navigation observer requests',async({page},info)=>{
 const f=fixture(),requests=[];const server=http.createServer((req,res)=>{requests.push(req.url);res.writeHead(200,{'Content-Type':'text/html'});res.end('<p>Observer reached</p>');});
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const observer=`http://127.0.0.1:${server.address().port}/`;
 try{
  await login(page,f);await page.goto('/templates');await uiCall(page,'open',[f.templates.A]);
  const attack=`<base href="${observer}"><meta http-equiv="refresh" content="0;url=${observer}meta"><script>window.__qaExecuted=1;top.location='${observer}top';fetch('${observer}script')</script><style>@import '${observer}import';p{background:url('${observer}css')}</style><p style="background-image:url('${observer}inline')">Hostile preview</p><img src="${observer}image" srcset="${observer}srcset 2x" onerror="window.__qaExecuted=2"><svg onload="window.__qaExecuted=3"><image href="${observer}svg"/></svg><iframe src="${observer}iframe"></iframe><object data="${observer}object"></object><embed src="${observer}embed"><form action="${observer}form" target="_top"><input name="q" value="test"><button>Submit attack</button></form><a href="${observer}link" target="_top" onclick="window.__qaExecuted=4">Attack link</a><img src="data:image/svg+xml,%3Csvg%20onload='alert(1)'%3E">`;
  await field(page,'html').fill(attack);await field(page,'text').fill('Hostile source preserved');await page.getByRole('button',{name:'Actualizar preview',exact:true}).click();await expect(page.locator('iframe')).toBeVisible();
  const preview=page.locator('iframe');expect(await preview.getAttribute('sandbox')).toBe('');expect(await preview.getAttribute('srcdoc')).toContain('Content-Security-Policy');
  const frame=page.frames().find(fr=>fr.parentFrame());await expect(frame.locator('body')).toContainText('Hostile preview');
  const before={page:page.url(),frame:frame.url()};
  for(const locator of [frame.locator('a'),frame.locator('button')])if(await locator.count())await locator.first().click({timeout:5000});
  await page.waitForTimeout(1200);
  expect(await frame.evaluate(()=>window.__qaExecuted)).toBeUndefined();expect(await page.evaluate(()=>window.__qaExecuted)).toBeUndefined();expect(page.url()).toBe(before.page);expect(frame.url()).toBe(before.frame);expect(requests).toEqual([]);
  await shot(page,'06-hostile-preview-blocked',info.project.name);
 }finally{await new Promise(resolve=>server.close(resolve));await cleanup(f);}
});
