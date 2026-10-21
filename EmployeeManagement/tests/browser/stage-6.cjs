const {chromium}=require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
    assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
    const state=JSON.parse(fs.readFileSync('/feasibility/employee-stage6-state.json','utf8')),base='http://employee-feasibility-20261020-app-1/';
    const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
    const results=[];let failures=0;
    function check(test,ok,observed){const r={test,status:ok?'PASS':'FAIL',observed};results.push(r);if(!ok)failures++;console.log(JSON.stringify(r));}
    for(const kind of ['manager','employee','colleague','noapproval','admin']){
        const context=await browser.newContext({viewport:{width:1500,height:1050},acceptDownloads:true}),page=await context.newPage(),errors=[];
        page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(45000);
        await page.goto(base+'#EmployeeManagement/holiday');await page.locator('input[name="username"]').fill(kind==='admin'?'admin':state.users[kind].name);await page.locator('input[name="password"]').fill(kind==='admin'?process.env.EMPLOYEE_TEST_ADMIN_PASSWORD:process.env.EMPLOYEE_TEST_PASSWORD);await page.locator('#btn-login').click();
        if(kind==='admin'){await page.waitForTimeout(900);check('technical-admin-business-route-denied',(await page.locator('body').innerText()).includes('403'));}
        else{
            await page.locator('#content [data-action="own"]').waitFor();
            if(kind==='manager'){
                const row=page.locator(`article[data-id="${state.browserSelf}"]`);await row.locator('[data-action="approve"]').waitFor();
                check('manager-can-initiate-own-approval',await row.locator('[data-action="approve"]').isEnabled());
                await row.locator('[data-action="approve"]').click();await page.locator('.modal [data-name="confirm"]').waitFor();const approval=page.waitForResponse(r=>r.url().endsWith(`/${state.browserSelf}/approve`));await page.locator('.modal [data-name="confirm"]').click();const response=await approval;const body=await response.json();check('real-approval-enters-Processing-in-UI',response.status()===200&&body.status==='ApprovalProcessing',body.status);
                await row.locator('[data-name="approval-document"]').first().waitFor();check('native-worker-polling-reveals-saved-document',await row.locator('[data-name="approval-document"]').count()===1);check('Approved-only-after-document-visible',(await row.innerText()).includes('Approved')&&await row.locator('[data-action="approve"]').count()===0);
                const retryRow=page.locator(`article[data-id="${state.browserRetry}"]`);await retryRow.locator('[data-action="retryApproval"]').waitFor();check('real-worker-error-visible-in-UI',await retryRow.locator('[data-name="approval-error"]').isVisible());check('partial-document-not-exposed-in-UI',await retryRow.locator('[data-name="approval-document"]').count()===0);
                await retryRow.locator('[data-action="retryApproval"]').click();await page.locator('.modal [data-name="confirm"]').waitFor();const retried=page.waitForResponse(r=>r.url().endsWith(`/${state.browserRetry}/retryApproval`));await page.locator('.modal [data-name="confirm"]').click();check('authorized-controlled-retry-through-native-confirm',(await retried).status()===200);
                await retryRow.locator('[data-name="approval-document"]').first().waitFor();check('retry-UI-completes-all-month-documents',await retryRow.locator('[data-name="approval-document"]').count()===2);check('error-cleared-only-on-recovery',await retryRow.locator('[data-name="approval-error"]').count()===0);
                const download=page.waitForEvent('download');await retryRow.locator('[data-name="approval-document"]').first().click();const saved=await download;const path=await saved.path();check('native-private-DOCX-download-browser',await saved.failure()===null&&fs.readFileSync(path).subarray(0,2).toString()==='PK',saved.suggestedFilename());
                await page.screenshot({path:'/feasibility/employee-stage6-manager.png',fullPage:true});
                // Leave the polling view, exercising its native remove cleanup.
                await page.goto(base+'#EmployeeAttendance');await page.locator('.attendance-day-list').waitFor();
            }
            if(kind==='employee'){
                const row=page.locator(`article[data-id="${state.normal}"]`);await row.locator('[data-name="approval-document"]').first().waitFor();check('employee-sees-own-generated-documents',await row.locator('[data-name="approval-document"]').count()===state.normalIds.length);
                check('employee-no-approval-or-retry-buttons',await page.locator('#content [data-action="approve"],#content [data-action="retryApproval"]').count()===0);
                const download=page.waitForEvent('download');await row.locator('[data-name="approval-document"]').first().click();const saved=await download;check('employee-native-download-authorized',await saved.failure()===null&&fs.readFileSync(await saved.path()).subarray(0,2).toString()==='PK');
                await page.screenshot({path:'/feasibility/employee-stage6-employee.png',fullPage:true});
            }
            if(['colleague','noapproval'].includes(kind)){
                check('no-colleague-private-request-'+kind,await page.locator(`article[data-id="${state.normal}"]`).count()===0);
                check('no-unauthorized-approval-actions-'+kind,await page.locator('#content [data-action="approve"],#content [data-action="retryApproval"]').count()===0);
                const r=await page.request.get(base+'?entryPoint=download&id='+state.normalIds[0]);check('direct-document-download-denied-'+kind,r.status()===403,r.status());
            }
        }
        check('no-JS-errors-'+kind,errors.length===0,errors);await context.close();
    }
    fs.writeFileSync('/feasibility/employee-stage6-browser-results.json',JSON.stringify({version:'0.6.0',results,assertions:results.length,failures},null,2));await browser.close();process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1);});
