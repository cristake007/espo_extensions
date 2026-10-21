const {chromium}=require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
 assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
 const s=JSON.parse(fs.readFileSync('/feasibility/navigation-hotfix-state.json'));
 const base='http://employee-feasibility-20261020-app-1/';
 const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const context=await browser.newContext({viewport:{width:1600,height:1080}}),page=await context.newPage();
 const errors=[],httpErrors=[],results=[]; let failures=0;
 page.on('pageerror',e=>errors.push(e.message));
 page.on('response',r=>{if(r.status()>=400)httpErrors.push({url:r.url(),status:r.status()});});
 page.setDefaultTimeout(20000);
 function check(test,ok,observed){results.push({test,status:ok?'PASS':'FAIL',observed});if(!ok)failures++;console.log(JSON.stringify(results.at(-1)));}
 await page.goto(base+'#EmployeeAttendanceOverview');
 await page.locator('input[name="username"]').fill(s.users.manager.name);
 await page.locator('input[name="password"]').fill(process.env.EMPLOYEE_TEST_PASSWORD);
 await page.locator('#btn-login').click();
 try { await page.locator('[data-overview-month-select]').waitFor(); } catch(e) { console.log(JSON.stringify({body:await page.locator('body').innerText(),errors,httpErrors,url:page.url()})); await page.screenshot({path:'/work/tests/evidence/navigation-diagnostic.png'});throw e; }
 if(process.argv.includes('--before')){
  check('reproduce-legacy-admin-link',await page.locator('#content a[href="#Admin/employeeAttendanceSettings"]').count()===1);
  check('reproduce-no-sidebar-tabs',await page.locator('#navbar ul.tabs a[href="#EmployeeManagement"],#navbar ul.tabs a[href="#EmployeeAdministration"]').count()===0);
  await page.locator('#content a[href="#Admin/employeeAttendanceSettings"]').click();
  await page.waitForFunction(()=>document.body.innerText.includes('403'));
  check('reproduce-manager-403-from-Schedules',true);
 } else {
  // Continue with full validation after installing the updated ZIP.
  const user=await page.request.get(base+'api/v1/App/user');
  const data=await user.json();
  check('actor-is-active-regular-non-admin',data.user?.type==='regular'&&data.user?.isActive===true,data.user&&{type:data.user.type,isActive:data.user.isActive,isAdmin:data.user.isAdmin});
  for(const scope of ['EmployeeManagement','EmployeeAttendance','EmployeeAttendanceOverview','EmployeeAdministration']){
   check('no-automatic-sidebar-'+scope,await page.locator(`#navbar ul.tabs a[href="#${scope}"]`).count()===0);
  }
  check('no-operational-Admin-link',await page.locator('#content a[href^="#Admin"]').count()===0);
  await page.locator('#content a[href="#EmployeeAdministration/attendanceMetadata"]').click();
  await page.locator('.attendance-schedule-row').first().waitFor();
  check('Schedules-from-overview-opens-delegated-page',page.url().endsWith('#EmployeeAdministration/attendanceMetadata'));
  const row=page.locator(`.attendance-schedule-row[data-user-id="${s.users.manager.id}"]`);
  const remote=row.locator('[data-schedule-field="remote"]'),originalRemote=await remote.isChecked();
  for(const v of [!originalRemote,originalRemote]){
   await remote.setChecked(v);
   const saved=page.waitForResponse(r=>r.url().endsWith('/attendance/schedule')&&r.request().method()==='POST');
   await row.locator('[data-action="save-work-schedule"]').click();check('Schedules-save-'+v,(await saved).status()===200);
   await page.reload();await page.locator('.attendance-schedule-row').first().waitFor();
   check('Schedules-persisted-'+v,await remote.isChecked()===v);
  }
  await page.screenshot({path:'/work/tests/evidence/navigation-schedules.png',fullPage:true});
  for(const section of ['attendance','holiday']){
   await page.goto(base+'#EmployeeAdministration/'+section);
   await page.locator('#content [data-action="save"]:enabled').waitFor();
   const name=section==='attendance'?'editablePastMonths':'employeeHolidayApprovalBlock1Title';
   const input=page.locator(`input[data-name="${name}"]`),old=await input.inputValue(),next=section==='attendance'?(old==='3'?'4':'3'):'Delegated browser validation';
   for(const v of [next,old]){
    await input.fill(v);
    const saved=page.waitForResponse(r=>r.url().endsWith('/administration/'+section)&&r.request().method()==='PUT');
    await page.locator('#content [data-action="save"]').click();check(section+'-save-'+v,(await saved).status()===200);
    await page.reload();await page.locator('#content [data-action="save"]:enabled').waitFor();
    check(section+'-persisted-'+v,await input.inputValue()===v);
   }
   await page.screenshot({path:'/work/tests/evidence/navigation-'+section+'.png',fullPage:true});
  }
  await page.goto(base+'#EmployeeAdministration/profiles');
  await page.locator('#content [data-action="save"]:enabled').waitFor();
  const profileRow=page.locator(`tr[data-user-id="${s.users.manager.id}"]`);
  const entitlement=profileRow.locator('input[data-name="annualEntitlement"]');
  if(await profileRow.locator('[data-action="initializeProfile"]').count()){
   await entitlement.click();await entitlement.press('ControlOrMeta+A');await entitlement.pressSequentially('21');
   await profileRow.locator('input[data-name="balance"]').click();
   await profileRow.locator('input[data-name="balance"]').pressSequentially('0');
   const saved=page.waitForResponse(r=>r.url().endsWith('/profiles/bulkInitialize')&&r.request().method()==='POST');
   await profileRow.locator('[data-action="initializeProfile"]').click();check('profile-initialize-native-form',(await saved).status()===200);
   await page.locator('#content [data-action="save"]:enabled').waitFor();
   await profileRow.locator('input[data-name="balance"]').waitFor({state:'detached'});
  }
  check('initialized-profile-editable',await profileRow.locator('input[data-name="balance"]').count()===0);
  const previousEntitlement=await entitlement.inputValue();
  for(const v of [previousEntitlement==='24'?'25':'24',previousEntitlement]){
   await entitlement.click();await entitlement.press('ControlOrMeta+A');await entitlement.pressSequentially(v);
   const saved=page.waitForResponse(r=>r.url().endsWith('/profiles/bulkInitialize')&&r.request().method()==='POST');
   await page.locator('#content [data-action="save"]').click();check('profile-parameters-save-'+v,(await saved).status()===200);
   await page.reload();await page.locator('#content [data-action="save"]:enabled').waitFor();
   check('profile-parameters-persisted-'+v,await entitlement.inputValue()===v);
  }
  await page.screenshot({path:'/work/tests/evidence/navigation-profiles.png',fullPage:true});
  for(const [route,selector] of [
   ['EmployeeManagement','#content a[href="#EmployeeAttendance"]'],
   ['EmployeeAttendance','.attendance-day-list'],
   ['EmployeeAttendanceOverview','[data-overview-month-select]'],
   ['EmployeeManagement/holiday','#content [data-action="create"]'],
   ['EmployeeAdministration','#content a[href="#EmployeeAdministration/attendance"]'],
   ['EmployeeAdministration/profiles','#content [data-action="save"]:enabled'],
  ]){
   await page.goto(base+'#'+route);
   await page.locator(selector).first().waitFor();check('page-'+route,true);
   if(route==='EmployeeAdministration'){
    const links=await page.locator('#content a[href^="#EmployeeAdministration/"]').evaluateAll(a=>a.map(x=>x.hash).sort());
    check('all-four-delegated-sections',links.length===4,links);
   }
  }
  check('no-manager-page-http-errors',httpErrors.length===0,httpErrors);
  await page.goto(base+'#Admin/employeeAttendanceSettings');
  await page.waitForFunction(()=>document.body.innerText.includes('403'));
  check('technical-Admin-remains-forbidden',true);
 }
 check('no-JS-errors',errors.length===0,errors);
 await page.screenshot({path:'/work/tests/evidence/navigation-'+(process.argv.includes('--before')?'before':'admin-denied')+'.png',fullPage:true});
 fs.writeFileSync('/work/tests/evidence/navigation-'+(process.argv.includes('--before')?'before':'browser')+'.json',JSON.stringify({results,assertions:results.length,failures},null,2));
 await browser.close();process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1)});
