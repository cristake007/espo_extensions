const {chromium}=require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
 assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
 const s=JSON.parse(fs.readFileSync('/feasibility/navigation-hotfix-state.json'));
 const base='http://employee-feasibility-20261020-app-1/';
 const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const page=await browser.newPage({viewport:{width:1550,height:1000}}),results=[],errors=[];let failures=0;
 page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(30000);
 function check(test,ok,observed){results.push({test,status:ok?'PASS':'FAIL',observed});if(!ok)failures++;console.log(JSON.stringify(results.at(-1)));}
 await page.goto(base+'#EmployeeManagement');await page.locator('[name="username"]').fill(s.users.manager.name);await page.locator('[name="password"]').fill(process.env.EMPLOYEE_TEST_PASSWORD);await page.locator('#btn-login').click();
 for(const scope of ['EmployeeAttendanceRecord','EmployeeHolidayRequest','EmployeeHolidayProfile','EmployeeHolidayLedger']){
  await page.goto(base+'#EmployeeManagement');await page.locator(`#content a[href="#${scope}"]`).last().click();
  await page.locator('#content table').first().waitFor();
  check('workspace-link-loads-native-list-'+scope,page.url().endsWith('#'+scope));
  check('native-list-no-create-'+scope,await page.locator('#content [data-action="create"]').count()===0);
  const r=await page.request.get(base+'api/v1/'+scope+'?maxSize=1');const data=await r.json();check('native-list-API-'+scope,r.status()===200,r.status());
  if(data.list?.[0]){
   const id=data.list[0].id;
   const loaded=page.waitForResponse(r=>r.url().includes('/api/v1/'+scope+'/'+id)&&r.request().method()==='GET');
   await page.goto(base+'#'+scope+'/view/'+id);check('native-detail-'+scope,(await loaded).status()===200);
   await page.locator('#content .record').waitFor();
   check('native-detail-no-edit-'+scope,await page.locator('#content [data-action="edit"]').count()===0);
  }
 }
 await page.goto(base+'#EmployeeAdministration');await page.locator('#content a[href="#EmployeeAdministration/attendanceMetadata"]').waitFor();
 await page.screenshot({path:'/work/tests/evidence/navigation-sidebar.png',fullPage:true});
 check('no-JS-errors',errors.length===0,errors);
 fs.writeFileSync('/work/tests/evidence/navigation-records-browser.json',JSON.stringify({results,assertions:results.length,failures},null,2));
 await browser.close();process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1)});
