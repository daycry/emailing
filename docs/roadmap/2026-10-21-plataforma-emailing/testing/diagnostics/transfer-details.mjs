import path from 'node:path';
import {homedir} from 'node:os';
import {pathToFileURL} from 'node:url';
import {fixture,cleanup,login,state} from '../specs/helpers.mjs';
const {chromium,expect}=await import(pathToFileURL(path.join(homedir(),'.claude/tool-cache/qa/node_modules/@playwright/test/index.mjs')).href);
const browser=await chromium.launch({headless:true,executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')});
const context=await browser.newContext({baseURL:'http://127.0.0.1:8083'});
const f=fixture();
try {
 const page=await context.newPage();
 page.setDefaultTimeout(30000);
 page.on('dialog',dialog=>dialog.accept());
 await login(page,f);
 await page.goto('/team');
 const summary=page.locator('summary').filter({hasText:'Transferir la propiedad'});
 const password=page.locator('[wire\\:model=transferPassword]');
 await summary.click();
 await page.locator('[wire\\:model=transferMember]').selectOption(String(f.members.developer));
 await password.fill('Wrong-example-password');
 await page.getByRole('button',{name:'Transferir propiedad',exact:true}).click();
 await expect(page.locator('body')).toContainText('La contrase\u00f1a no es correcta');
 const ownerUnchanged=state(f).accounts.find(a=>a.id===f.accounts.A.id).owner_id===f.users.owner.id;
 const beforeReopen={detailsOpen:await summary.locator('..').evaluate(el=>el.open),passwordVisible:await password.isVisible(),ownerUnchanged};
 if(!beforeReopen.detailsOpen) await summary.click();
 const afterReopen={detailsOpen:await summary.locator('..').evaluate(el=>el.open),passwordVisible:await password.isVisible()};
 console.log(JSON.stringify({wrongPasswordRejected:true,beforeReopen,afterReopen,validTransferAttempted:false}));
} finally {
 await cleanup(f,[context]);
 await browser.close();
}
