import {test,expect,fixture,command,state,login,shot,lw,post} from './helpers.mjs';
test('E2E-02 CA-13/15/18 account selection foreign HTTP/Livewire payload and live revocation',async({page},info)=>{
 const f=fixture();try{
  await login(page,f,'actor');await page.goto('/team');await expect(page.getByRole('button',{name:'Enviar invitación',exact:true})).toBeVisible();
  const select=page.locator('#account-switch');await expect(select.locator('option')).toHaveCount(2);await expect(select.locator('option')).not.toContainText([f.accounts.C.name]);await shot(page,'02-selector-admin',info.project.name);
  await select.selectOption(String(f.accounts.B.id));await page.waitForURL('**/dashboard');await page.goto('/team');await expect(page.getByText('Solo los administradores gestionan sus accesos.',{exact:false})).toBeVisible();await expect(page.getByRole('button',{name:'Enviar invitación',exact:true})).toHaveCount(0);
  let result=await post(page,'/accounts/switch',{account_id:f.accounts.C.id});expect(result.status).toBe(403);
  result=await lw(page,'invite',[],{email:f.users.invitee.email,role:'admin',scopes:[]});expect(result.status).toBe(403);
  await page.reload();await expect(page.locator('#account-switch')).toHaveValue(String(f.accounts.B.id));await shot(page,'02-readonly',info.project.name);
  await page.locator('#account-switch').selectOption(String(f.accounts.A.id));await page.waitForURL('**/dashboard');await page.goto('/team');
  const before=state(f),foreign=before.members.find(m=>m.account_id===f.accounts.C.id);
  result=await lw(page,'saveMember',[],{editing:foreign.id,memberRole:'read-only',memberScopes:[]});expect(result.status).toBe(404);
  expect(state(f).members.find(m=>m.id===foreign.id)).toEqual(foreign);
  command('revoke-actor',f.run);
  result=await lw(page,'invite',[],{email:f.users.invitee.email,role:'admin',scopes:[]});expect(result.status).toBe(403);
  const response=await page.goto('/dashboard');expect(response.status()).toBe(403);await expect(page.getByRole('heading',{name:'No tienes acceso a esta acción',exact:true})).toBeVisible();await shot(page,'02-revoked',info.project.name);
  expect(state(f).invitations).toHaveLength(0);
 }finally{command('cleanup',f.run);}
});
