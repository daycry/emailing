import {test,expect,fixture,command,state,login,shot,mailbox,localLink,invite,post,cleanup,closeContext} from './helpers.mjs';
test('E2E-03 CA-17 real invitation email intended identity one-use expired revoked resent',async({page,browser,baseURL},info)=>{
 const f=fixture(),contexts=[];try{
  await login(page,f);await page.goto('/team');
  await invite(page,f,'invitee');const acceptedMail=await mailbox(page,f.users.invitee.email,'invitation');
  const otherContext=await browser.newContext({baseURL});contexts.push(otherContext);const other=await otherContext.newPage();await login(other,f,'other');
  let r=await other.goto(localLink(acceptedMail.url,baseURL));expect(r.status()).toBe(403);await other.goto('/accounts');let denied=await post(other,new URL(acceptedMail.url).pathname);expect(denied.status).toBe(403);
  await closeContext(otherContext);contexts.splice(contexts.indexOf(otherContext),1);
  const memberContext=await browser.newContext({baseURL});contexts.push(memberContext);const member=await memberContext.newPage();await login(member,f,'invitee');await member.goto(localLink(acceptedMail.url,baseURL));
  await Promise.all([member.waitForURL('**/team'),member.getByRole('button',{name:'Aceptar invitación'}).click()]);await expect(member.locator('body')).toContainText('Ya formas parte del equipo');
  await shot(member,'03-accepted',info.project.name);
  r=await member.request.get(localLink(acceptedMail.url,baseURL));expect(r.status()).toBe(410);denied=await post(member,new URL(acceptedMail.url).pathname);expect(denied.status).toBe(410);
  expect(state(f).members.filter(m=>m.account_id===f.accounts.A.id&&m.user_id===f.users.invitee.id)).toHaveLength(1);
  await closeContext(memberContext);contexts.splice(contexts.indexOf(memberContext),1);
  for(const key of ['expired','revoked','resent']){
   console.info('E2E03 stage '+key+' '+new Date().toISOString());
   await invite(page,f,key);const mail=await mailbox(page,f.users[key].email,'invitation');
   const invitation=state(f).invitations.find(i=>i.email===f.users[key].email);expect(invitation.token_is_hash).toBe(true);
   const entry=page.locator('section').filter({has:page.getByRole('heading',{name:'Invitaciones',exact:true})}).locator('.border-b').filter({hasText:f.users[key].email});
   if(key==='expired')command('expire',f.run);
   if(key==='revoked'){await entry.getByRole('button',{name:'Revocar',exact:true}).click();await expect(page.getByRole('status')).toContainText('revocada');}
   let newest;
   if(key==='resent'){await entry.getByRole('button',{name:'Reenviar',exact:true}).click();await expect(page.getByRole('status')).toContainText('nuevo enlace');newest=await mailbox(page,f.users[key].email,'invitation',[mail.id]);expect(newest.url).not.toBe(mail.url);}
   const c=await browser.newContext({baseURL});contexts.push(c);const p=await c.newPage();await login(p,f,key);await p.goto('/accounts');
   r=await p.request.get(localLink(mail.url,baseURL));expect(r.status()).toBe(key==='resent'?404:410);
   denied=await post(p,new URL(mail.url).pathname);expect(denied.status).toBe(key==='resent'?404:410);
   if(key==='resent'){await p.goto(localLink(newest.url,baseURL));await Promise.all([p.waitForURL('**/team'),p.getByRole('button',{name:'Aceptar invitación'}).click()]);}
   await closeContext(c);contexts.splice(contexts.indexOf(c),1);
  }
  const s=state(f);for(const key of ['expired','revoked'])expect(s.members.filter(m=>m.account_id===f.accounts.A.id&&m.user_id===f.users[key].id)).toHaveLength(0);
  expect(s.members.filter(m=>m.account_id===f.accounts.A.id&&m.user_id===f.users.resent.id)).toHaveLength(1);
  await page.reload();await shot(page,'03-invitations',info.project.name);
 }finally{await cleanup(f,contexts);}
});
