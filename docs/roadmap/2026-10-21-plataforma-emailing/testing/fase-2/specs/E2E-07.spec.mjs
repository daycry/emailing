import {test,expect,fixture,login,shot,lw,cleanup,resources,clickCall} from './helpers.mjs';
async function messages(page,email){const response=await page.request.get('http://127.0.0.1:18025/api/v1/search?query='+encodeURIComponent('to:'+email));expect(response.status()).toBe(200);return (await response.json()).messages||[];}
test('E2E-07 CA-07/08/12/14 account log Mailpit accepted private eml encryption credentials cleared external blocked',async({page,browser,baseURL},info)=>{
 const f=fixture(),contexts=[],ownedMail=[];try{
  await login(page,f);await page.goto('/settings/mail');expect(resources(f).credentialsEncrypted).toBe(true);
  expect(await page.content()).not.toContain('qa-secret-'+f.run);expect(await page.content()).not.toContain('qa-user-'+f.run);
  await page.locator('details').first().evaluate(el=>el.open=true);
  for(const name of ['smtp_username','smtp_password'])await expect(page.locator(`[name=${name}]`)).toHaveValue('');
  await page.locator('[name=smtp_username]').fill('replacement-user-'+f.run);await page.locator('[name=smtp_password]').fill('replacement-secret-'+f.run);
  await clickCall(page,'saveSettings',()=>page.getByRole('button',{name:'Guardar configuración',exact:true}).click());await expect(page.getByRole('status')).toContainText('guardada');
  for(const name of ['smtp_username','smtp_password'])await expect(page.locator(`[name=${name}]`)).toHaveValue('');
  expect(await page.content()).not.toContain('replacement-secret-'+f.run);expect(resources(f).credentialsEncrypted).toBe(true);
  await page.locator('details').first().evaluate(el=>el.open=true);await page.locator('[name=smtp_username]').fill('error-user-'+f.run);await page.locator('[name=smtp_password]').fill('error-secret-'+f.run);await page.locator('[name=smtp_host]').fill('invalid host');
  await clickCall(page,'saveSettings',()=>page.getByRole('button',{name:'Guardar configuración',exact:true}).click());await expect(page.getByRole('alert')).toBeVisible();
  for(const name of ['smtp_username','smtp_password'])await expect(page.locator(`[name=${name}]`)).toHaveValue('');expect(await page.content()).not.toContain('error-secret-'+f.run);
  await page.locator('details').first().evaluate(el=>el.open=true);await page.locator('[name=smtp_host]').fill('relay.invalid');await clickCall(page,'saveSettings',()=>page.getByRole('button',{name:'Guardar configuración',exact:true}).click());await expect(page.getByRole('alert')).toHaveCount(0);
  const logRecipient=`log-${f.run}@example.test`;const before=await messages(page,logRecipient);
  await page.locator('[wire\\:model=testSender]').selectOption(String(f.senders.A));await page.locator('[wire\\:model=testRecipient]').fill(logRecipient);await page.locator('[wire\\:model=testSubject]').fill('QA log '+f.run);
  await page.getByRole('button',{name:'Crear prueba',exact:true}).click();await expect(page.getByRole('status')).toContainText('No se conect');
  let s=resources(f),message=s.mail_messages.at(-1);expect(message.account_id).toBe(f.accounts.A.id);expect(message.mode).toBe('log');expect(message.status).toBe('logged');expect(await messages(page,logRecipient)).toHaveLength(before.length);
  const dl=await page.request.get(`/mail-artifacts/${message.id}/download`);expect(dl.status()).toBe(200);expect(dl.headers()['cache-control']).toContain('private');const body=await dl.text();expect(body).toContain('QA log '+f.run);expect(body).not.toContain('replacement-secret-'+f.run);
  const publicArtifact=await page.request.get('/storage/mail-artifacts/'+message.id+'.eml');expect(publicArtifact.status()).toBe(403);
  const publicBody=await publicArtifact.text();expect(publicBody).not.toContain('QA log '+f.run);expect(publicBody).not.toContain(logRecipient);expect(publicBody).not.toBe(body);
  await shot(page,'07-log-private-settings',info.project.name);
  const foreign=await browser.newContext({baseURL});contexts.push(foreign);const fp=await foreign.newPage();await login(fp,f,'other');await fp.goto('/settings/mail');expect((await fp.request.get(`/mail-artifacts/${message.id}/download`)).status()).toBe(404);
  expect((await lw(fp,'editSender',[f.senders.A])).status).toBe(404);await fp.reload();
  expect((await lw(fp,'test',[],{testSender:f.senders.A,testRecipient:`cross-${f.run}@example.test`,testSubject:'Forbidden'})).status).toBe(404);await fp.reload();
  const recipient=`mailpit-${f.run}@example.test`;await fp.locator('[wire\\:model=testSender]').selectOption(String(f.senders.B));await fp.locator('[wire\\:model=testRecipient]').fill(recipient);await fp.locator('[wire\\:model=testSubject]').fill('QA Mailpit '+f.run);
  await fp.getByRole('button',{name:'Crear prueba',exact:true}).click();await expect(fp.getByRole('status')).toContainText('SMTP aceptado');await expect(fp.getByRole('status')).toContainText('No acredita entrega');
  s=resources(f);message=s.mail_messages.at(-1);expect(message.account_id).toBe(f.accounts.B.id);expect(message.mode).toBe('mailpit');expect(message.status).toBe('smtp_accepted');
  await expect.poll(async()=>{const found=await messages(fp,recipient);if(found.length){ownedMail.push(...found.map(m=>m.ID));return found.length;}return 0;}).toBe(1);
  await shot(fp,'07-mailpit-accepted-not-delivered',info.project.name);
  const denied=await browser.newContext({baseURL});contexts.push(denied);const dp=await denied.newPage();await login(dp,f,'marketer');
  // Marketer has activity.read; settings.read is independently denied.
  expect((await dp.request.get('/settings/mail')).status()).toBe(403);
  const accountant=await browser.newContext({baseURL});contexts.push(accountant);const ap=await accountant.newPage();await login(ap,f,'accountant');await ap.goto('/settings/mail');expect((await lw(ap,'test',[],{testSender:f.senders.A,testRecipient:`nosend-${f.run}@example.test`})).status).toBe(403);
  await page.reload();const beforeExternal=resources(f);
  const response=await lw(page,'saveSettings',[{mode:'smtp',smtp_host:'relay.invalid',smtp_port:587,smtp_security:'starttls',domain_verified:true,dns_verified:true,feedback_verified:true}]);
  expect(response.status).toBe(200);const rejectedHtml=JSON.parse(response.body).components[0].effects.html;
  expect(rejectedHtml).toContain('role="alert"');expect(rejectedHtml).toContain('El valor seleccionado para mode no est\u00e1 permitido.');
  expect(resources(f)).toEqual(beforeExternal);expect(resources(f).mode).toBe('log');
  expect((await page.request.get('/dashboard')).status()).toBe(200);
 }finally{
  for(const id of [...new Set(ownedMail)]){expect(id).toBeTruthy();await page.request.delete('http://127.0.0.1:18025/api/v1/messages',{data:{IDs:[id]}});expect((await page.request.get(`http://127.0.0.1:18025/api/v1/message/${id}`)).status()).toBe(404);}
  await cleanup(f,contexts);
 }
});
