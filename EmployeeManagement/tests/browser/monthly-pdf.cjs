const {chromium}=require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict'),crypto=require('crypto');
(async()=>{
 assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
 const state=JSON.parse(fs.readFileSync('/feasibility/monthly-pdf-state.json')),before=process.argv.includes('--before'),base='http://employee-feasibility-20261020-app-1/',results=[];let failures=0;
 const output='/work/tests/evidence/monthly-pdf-'+(before?'before':'browser')+'.json';
 function check(test,ok,observed){results.push({test,status:ok?'PASS':'FAIL',observed});if(!ok)failures++;fs.writeFileSync(output,JSON.stringify({results,assertions:results.length,failures,complete:false},null,2));console.log(JSON.stringify(results.at(-1)));assert.ok(ok,test);}
 const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
 for(const kind of ['managerUnset','managerDenied']){
  const context=await browser.newContext({acceptDownloads:true,viewport:{width:1600,height:1000}}),page=await context.newPage(),errors=[];page.setDefaultTimeout(30000);page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'#EmployeeAttendanceOverview');await page.locator('[name="username"]').fill(state.users[kind].name);await page.locator('[name="password"]').fill(process.env.EMPLOYEE_TEST_PASSWORD);await page.locator('#btn-login').click();await page.locator('[data-overview-month-select]').waitFor();
  const user=await(await page.request.get(base+'api/v1/App/user')).json(),overview=await(await page.request.get(base+'api/v1/EmployeeManagement/attendance/overview?month=2026-10')).json();
  check(kind+'-NON-ADMIN-no-global-Export',user.user.type==='regular'&&user.user.isActive&&user.acl.exportPermission!=='yes',{type:user.user.type,exportPermission:user.acl.exportPermission});
  check(kind+'-management-and-incomplete-policy',overview.isManager&&overview.incompleteExportsAllowed&&overview.downloadReady&&!overview.registerComplete,{month:overview.month,registerComplete:overview.registerComplete});
  const button=page.locator('[data-action="download-pdf"]');
  if(before){check(kind+'-reproduce-disabled-PDF',await button.isDisabled());const response=await page.request.post(base+'api/v1/EmployeeManagement/attendance/pdf',{data:{month:'2026-10'}});check(kind+'-reproduce-backend-403',response.status()===403,response.status());await page.screenshot({path:'/work/tests/evidence/monthly-pdf-before-'+kind+'.png'});}
  else{
   check(kind+'-PDF-enabled-without-global-Export',await button.isEnabled());check(kind+'-XLSX-native-permission-retained',await page.locator('[data-action="download-xlsx"]').isDisabled());
   const downloadEvent=page.waitForEvent('download'),responseEvent=page.waitForResponse(r=>r.url().endsWith('/attendance/pdf'));await button.click();const response=await responseEvent,download=await downloadEvent;check(kind+'-PDF-download-http',response.status()===200,response.status());const bytes=fs.readFileSync(await download.path());check(kind+'-actual-monthly-PDF-file',await download.failure()===null&&bytes.subarray(0,5).toString()==='%PDF-'&&bytes.toString('latin1').includes('%%EOF'),{filename:download.suggestedFilename(),bytes:bytes.length,sha256:crypto.createHash('sha256').update(bytes).digest('hex')});await download.saveAs('/work/tests/evidence/monthly-pdf-'+kind+'.pdf');check(kind+'-button-enabled-after-download',await button.isEnabled());await page.screenshot({path:'/work/tests/evidence/monthly-pdf-after-'+kind+'.png'});
   if(kind==='managerDenied'){
    async function savePolicy(enabled){await page.goto(base+'#EmployeeAdministration/attendance');await page.locator('#content [data-action="save"]:enabled').waitFor();const input=page.locator('.field[data-name="allowIncompleteExports"] input[type="checkbox"]');await input.setChecked(enabled);const saved=page.waitForResponse(r=>r.url().endsWith('/administration/attendance')&&r.request().method()==='PUT');await page.locator('#content [data-action="save"]').click();check('save-incomplete-policy-'+enabled,(await saved).status()===200);await page.reload();await page.locator('#content [data-action="save"]:enabled').waitFor();check('incomplete-policy-persists-'+enabled,await input.isChecked()===enabled);await page.goto(base+'#EmployeeAttendanceOverview');await page.locator('[data-overview-month-select]').waitFor();check('PDF-button-follows-incomplete-policy-'+enabled,await button.isEnabled()===enabled);}
    await savePolicy(false);const rejected=await page.request.post(base+'api/v1/EmployeeManagement/attendance/pdf',{data:{month:'2026-10'}});check('incomplete-disabled-command-denied',rejected.status()===409,rejected.status());await savePolicy(true);
   }
   const after=await(await page.request.get(base+'api/v1/App/user')).json();check(kind+'-still-no-global-Export-after-download',after.acl.exportPermission!=='yes');
  }
  check(kind+'-no-JS-errors',errors.length===0,errors);await context.close();
 }
 fs.writeFileSync(output,JSON.stringify({results,assertions:results.length,failures,complete:true},null,2));
 }finally{await browser.close();}
 process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1)});
