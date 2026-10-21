const {chromium}=require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
 assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
 const s=JSON.parse(fs.readFileSync('/feasibility/navigation-hotfix-state.json'));
 const base='http://employee-feasibility-20261020-app-1/';
 const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const results=[];let failures=0;
 function check(test,ok,observed){results.push({test,status:ok?'PASS':'FAIL',observed});if(!ok)failures++;console.log(JSON.stringify(results.at(-1)));}
 for(const kind of ['manager','employee','entry','noentry','viewer','admin']){
  const context=await browser.newContext({viewport:{width:1550,height:1000}}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(30000);
  await page.goto(base+'#EmployeeAdministration');
  await page.locator('[name="username"]').fill(kind==='admin'?'admin':s.users[kind].name);
  await page.locator('[name="password"]').fill(kind==='admin'?process.env.EMPLOYEE_TEST_ADMIN_PASSWORD:process.env.EMPLOYEE_TEST_PASSWORD);
  await page.locator('#btn-login').click();
  if(['employee','noentry','admin'].includes(kind)){
   await page.waitForFunction(()=>document.body.innerText.includes('403'));check('delegated-entry-denied-'+kind,true);
  }else{
   await page.locator('#content .admin-content').waitFor({state:'attached'});
   check('section-count-'+kind,await page.locator('#content a[href^="#EmployeeAdministration/"]').count()===(kind==='manager'?4:0));
  }
  const user=await page.request.get(base+'api/v1/App/user'),data=await user.json();
  if(kind==='manager'){
   check('manager-no-global-admin',data.user.type==='regular'&&data.user.isAdmin!==true&&data.acl.table.User.edit==='no'&&data.acl.table.Role!==true);
   for(const [resource,method] of [['Settings','PUT'],['Role','GET']]){const r=await page.request.fetch(base+'api/v1/'+resource,{method,...(method==='PUT'?{data:{}}:{})});check('manager-cannot-manage-global-'+resource,r.status()===403,r.status());}
   const r=await page.request.post(base+'api/v1/WorkingTimeCalendar',{data:{name:'Must not be created'}});check('manager-cannot-create-WTC',r.status()===403,r.status());
  }
  if(['employee','entry','noentry','viewer'].includes(kind)){
   for(const section of ['attendance','holiday','profiles','attendanceMetadata']){
    await page.goto(base+'#EmployeeAdministration/'+section);await page.waitForFunction(()=>document.body.innerText.includes('403'));
    check('direct-section-denied-'+kind+'-'+section,true);
   }
   for(const [path,method,body] of [
    ['EmployeeManagement/administration/attendance','GET'],
    ['EmployeeManagement/administration/attendance','PUT',{editablePastMonths:5,allowIncompleteExports:true}],
    ['EmployeeManagement/attendance/schedules','GET'],
    ['EmployeeManagement/attendance/schedule','POST',{userId:s.users.manager.id,remote:true,cic:false,cicStartDate:null,cicEndDate:null}]
   ]){
    const r=await page.request.fetch(base+'api/v1/'+path,{method,...(body?{data:body}:{})});check('API-denied-'+kind+'-'+method+'-'+path,r.status()===403,r.status());
   }
   if(['viewer','noentry'].includes(kind)){
    await page.goto(base+'#EmployeeAttendanceOverview');await page.locator('[data-overview-month-select]').waitFor();
    check('Schedules-link-hidden-'+kind,await page.locator('#content a[href="#EmployeeAdministration/attendanceMetadata"]').count()===0);
   }
  }
  if(kind==='admin'){
   for(const path of ['EmployeeManagement/workspace/status','EmployeeManagement/administration/status','EmployeeManagement/administration/attendance','EmployeeManagement/attendance/schedules']){
    const r=await page.request.get(base+'api/v1/'+path);check('technical-admin-business-denied-'+path,r.status()===403,r.status());
   }
  }
  check('no-JS-errors-'+kind,errors.length===0,errors);await context.close();
 }
 fs.writeFileSync('/work/tests/evidence/navigation-acl-browser.json',JSON.stringify({results,assertions:results.length,failures},null,2));
 await browser.close();process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1)});
