import {test,expect,fixture,command,state,login,shot,mailbox,localLink,cleanup} from './helpers.mjs';
test('E2E-01 CA-01/12/16 registration login logout reset SMTP one-use and disabled registration',async({page,browser,baseURL},info)=>{
 const f=fixture(),contexts=[];const email=`qa-${f.run}-registered@example.test`;
 try{
  await page.goto('/dashboard');await expect(page).toHaveURL(/\/login$/);
  const csrf=await page.request.post(baseURL+'/login',{form:{email,password:'invalid'},maxRedirects:0});expect(csrf.status()).toBe(419);
  await page.goto('/register');await shot(page,'01-registration',info.project.name);
  await page.locator('[name=name]').fill('QA Registered');await page.locator('[name=account_name]').fill('QA Registered '+f.run.slice(0,8));await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill(f.password);await page.locator('[name=password_confirmation]').fill(f.password);
  await Promise.all([page.waitForURL('**/dashboard'),page.getByRole('button',{name:/Crear cuenta/}).click()]);
  let s=state(f);const u=s.users.find(u=>u.email===email);const accounts=s.accounts.filter(a=>a.owner_id===u.id);expect(accounts).toHaveLength(1);expect(s.members.filter(m=>m.user_id===u.id&&m.account_id===accounts[0].id&&m.role==='admin')).toHaveLength(1);
  await shot(page,'01-dashboard',info.project.name);
  // Registration session stays untouched until the password reset is complete.
  const loginContext=await browser.newContext({baseURL});contexts.push(loginContext);const logged=await loginContext.newPage();
  const rf={...f,users:{registered:{email}},password:f.password};await login(logged,rf,'registered');
  await logged.getByRole('button',{name:'Salir',exact:true}).click();await expect(logged).toHaveURL(/\/login$/);await login(logged,rf,'registered');
  // Second pre-reset cookie jar is obtained through login, also untouched after reset.
  const resetContext=await browser.newContext({baseURL});contexts.push(resetContext);const reset=await resetContext.newPage();
  await reset.goto('/login');await reset.locator('[name=email]').fill(email);await reset.locator('[name=password]').fill('Incorrect-example-password');await reset.getByRole('button',{name:'Acceder',exact:true}).click();await expect(reset).toHaveURL(/\/login$/);await expect(reset.locator('body')).toContainText('El email o la contraseña no son correctos');
  await reset.goto('/forgot-password');await reset.locator('[name=email]').fill(email);await reset.getByRole('button',{name:'Enviar enlace'}).click();await expect(reset.locator('body')).toContainText(/enviad|enlace/i);
  const mail=await mailbox(reset,email,'reset');const url=localLink(mail.url,baseURL);await reset.goto(url);
  const newPassword=f.password+'-new';await reset.locator('[name=password]').fill(newPassword);await reset.locator('[name=password_confirmation]').fill(newPassword);await reset.getByRole('button',{name:'Guardar contraseña'}).click();await expect(reset).toHaveURL(/\/login$/);await shot(reset,'01-reset-confirmation',info.project.name);
  for(const context of [page.context(),loginContext]){const r=await context.request.get(baseURL+'/dashboard',{maxRedirects:0});expect(r.status()).toBe(302);expect(r.headers().location).toMatch(/\/login$/);}
  await reset.goto(url);await reset.locator('[name=password]').fill(newPassword+'-twice');await reset.locator('[name=password_confirmation]').fill(newPassword+'-twice');await reset.getByRole('button',{name:'Guardar contraseña'}).click();await expect(reset.locator('body')).toContainText('El enlace ha caducado o ya se ha utilizado');
  await login(reset,{...rf,password:newPassword},'registered');await expect(reset.locator('h1')).toBeVisible();
  const offContext=await browser.newContext({baseURL:'http://127.0.0.1:8084'});contexts.push(offContext);const off=await offContext.newPage();await off.goto('/login');await expect(off.getByRole('link',{name:'Crea tu cuenta'})).toHaveCount(0);
  const token=await off.locator('[name=_token]').inputValue();const disabledEmail=`qa-${f.run}-disabled@example.test`;
  const denied=await off.request.post('http://127.0.0.1:8084/register',{form:{_token:token,name:'QA Disabled',account_name:'QA Disabled',email:disabledEmail,password:f.password,password_confirmation:f.password},maxRedirects:0});expect(denied.status()).toBe(404);expect(state(f).users.some(u=>u.email===disabledEmail)).toBe(false);
 }finally{await cleanup(f,contexts);}
});
