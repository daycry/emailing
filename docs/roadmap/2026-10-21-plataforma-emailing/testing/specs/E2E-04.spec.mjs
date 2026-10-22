import {test,expect,fixture,command,state,login,shot,lw,cleanup,closeContext} from './helpers.mjs';
test('E2E-04 CA-13/15/20 roles presets forged privilege owner transfer password and audit',async({page,browser,baseURL},info)=>{
 const f=fixture(),contexts=[],rolePages={};try{
  page.on('dialog',dialog=>dialog.accept());
  await login(page,f);await page.goto('/team');
  // Presets are applied through actual Livewire UI, then compared with the catalog.
  await page.locator('[wire\\:model\\.live=role]').selectOption('restricted');
  const expected={developer:['templates.read','mail.send','activity.read','api-keys.read','api-keys.write'],marketer:['contacts.read','contacts.write','templates.read','templates.write','campaigns.read','campaigns.write','mail.send','activity.read','suppressions.read','suppressions.write'],accountant:['activity.read','settings.read']};
  for(const [preset,scopes] of Object.entries(expected)){
   await page.locator('[wire\\:model\\.live=preset]').selectOption(preset);
   await expect.poll(async()=>page.locator('[wire\\:model=scopes]:checked').evaluateAll(es=>es.map(e=>e.value).sort())).toEqual([...scopes].sort());
  }
  await shot(page,'04-permissions-desktop',info.project.name);
  const current=state(f),owner=current.members.find(m=>m.id===f.members.owner);
  let r=await lw(page,'remove',[owner.id]);expect(r.status).toBe(200);expect(r.body).toContain('propietario');expect(state(f).members.some(m=>m.id===owner.id)).toBe(true);
  r=await lw(page,'saveMember',[],{editing:owner.id,memberRole:'read-only',memberScopes:[]});expect(r.status).toBe(200);expect(r.body).toMatch(/propietario|administrador/);expect(state(f).members.find(m=>m.id===owner.id).role).toBe('admin');
  r=await lw(page,'invite',[],{email:f.users.expired.email,role:'restricted',scopes:['owner.root']});expect(r.status).toBe(200);expect(r.body).toContain('scopes');expect(state(f).invitations).toHaveLength(0);
  for(const key of ['readonly','developer','marketer','accountant']){
   console.info('E2E04 role '+key+' '+new Date().toISOString());
   const c=await browser.newContext({baseURL});contexts.push(c);const p=await c.newPage();rolePages[key]=p;await login(p,f,key);await p.goto('/team');await expect(p.getByRole('button',{name:'Enviar invitación',exact:true})).toHaveCount(0);
   r=await lw(p,'invite',[],{email:f.users.expired.email,role:'admin',scopes:[]});expect(r.status).toBe(403);
   r=await lw(p,'saveMember',[],{editing:f.members[key],memberRole:'admin',memberScopes:['settings.write']});expect(r.status).toBe(403);
   r=await lw(p,'remove',[owner.id]);expect(r.status).toBe(403);
   r=await lw(p,'transfer',[],{transferMember:f.members[key],transferPassword:f.password});expect(r.status).toBe(403);
   if(key!=='readonly'){await closeContext(c);contexts.splice(contexts.indexOf(c),1);}
  }
  await page.locator('tr').filter({hasText:f.users.readonly.email}).getByRole('button',{name:'Retirar acceso',exact:true}).click();await expect(page.getByRole('status')).toContainText('Acceso retirado');
  r=await lw(rolePages.readonly,'invite',[],{email:f.users.expired.email,role:'admin',scopes:[]});expect(r.status).toBe(403);expect(state(f).members.some(m=>m.id===f.members.readonly)).toBe(false);expect(state(f).accounts.some(a=>a.id===f.accounts.A.id)).toBe(true);
  await closeContext(rolePages.readonly.context());contexts.splice(contexts.indexOf(rolePages.readonly.context()),1);
  // Open administrator session loses privileges on the very next action.
  const ac=await browser.newContext({baseURL});contexts.push(ac);const actor=await ac.newPage();await login(actor,f,'actor');await actor.goto('/team');
  const row=page.locator('tr').filter({hasText:f.users.actor.email});await row.getByRole('button',{name:'Editar',exact:true}).click();await page.locator('[wire\\:model\\.live=memberRole]').selectOption('read-only');await page.getByRole('button',{name:'Guardar permisos',exact:true}).click();await expect(page.getByRole('status')).toContainText('Permisos actualizados');
  r=await lw(actor,'invite',[],{email:f.users.expired.email,role:'admin',scopes:[]});expect(r.status).toBe(403);
  // Transfer through UI first rejects a wrong password, then succeeds for the owner.
  await page.locator('summary').filter({hasText:'Transferir la propiedad'}).click();await page.locator('[wire\\:model=transferMember]').selectOption(String(f.members.developer));await page.locator('[wire\\:model=transferPassword]').fill('Wrong-example-password');
  await page.getByRole('button',{name:'Transferir propiedad',exact:true}).click();await expect(page.locator('body')).toContainText('La contraseña no es correcta');expect(state(f).accounts.find(a=>a.id===f.accounts.A.id).owner_id).toBe(f.users.owner.id);
  const confirmationPassword=page.locator('[wire\\:model=transferPassword]');
  if(!await confirmationPassword.isVisible()) await page.locator('summary').filter({hasText:'Transferir la propiedad'}).click();
  await expect(confirmationPassword).toBeVisible();
  await page.locator('[wire\\:model=transferPassword]').fill(f.password);await page.getByRole('button',{name:'Transferir propiedad',exact:true}).click();await expect(page.getByRole('status')).toContainText('Propiedad transferida');
  await expect(page.getByText('La contraseña no es correcta',{exact:false})).not.toBeVisible();
  let s=state(f);expect(s.accounts.find(a=>a.id===f.accounts.A.id).owner_id).toBe(f.users.developer.id);expect(s.members.find(m=>m.id===f.members.developer).role).toBe('admin');expect(s.members.find(m=>m.id===f.members.owner).role).toBe('admin');
  for(const action of ['teammate.removed','teammate.permissions_changed','account.ownership_transferred'])expect(s.events.some(e=>e.account_id===f.accounts.A.id&&e.actor_id===f.users.owner.id&&e.action===action)).toBe(true);
  await expect(page.locator('body')).toContainText('Transfirió la propiedad');await shot(page,'04-audit',info.project.name);
  await page.setViewportSize({width:390,height:844});await shot(page,'04-mobile',info.project.name);expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
  await page.setViewportSize({width:768,height:1024});await shot(page,'04-tablet',info.project.name);expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
 }finally{await cleanup(f,contexts);}
});
