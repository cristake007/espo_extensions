const {chromium}=require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
    assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
    const state=JSON.parse(fs.readFileSync('/feasibility/employee-stage5-state.json','utf8'));
    const base='http://employee-feasibility-20261020-app-1/';
    const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
    const results=[];let failures=0;
    function check(test,ok,observed){const r={test,status:ok?'PASS':'FAIL',observed};results.push(r);if(!ok)failures++;console.log(JSON.stringify(r));}
    for(const kind of ['ui','manager','cic','viewer','admin']){
        const context=await browser.newContext({viewport:{width:1600,height:1050}}),page=await context.newPage(),errors=[];
        page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(25000);
        await page.goto(base+(kind==='cic'?'#EmployeeAttendance':'#EmployeeManagement/holiday'));
        await page.locator('input[name="username"]').fill(kind==='admin'?'admin':state.users[kind].name);
        await page.locator('input[name="password"]').fill(kind==='admin'?process.env.EMPLOYEE_TEST_ADMIN_PASSWORD:process.env.EMPLOYEE_TEST_PASSWORD);
        await page.locator('#btn-login').click();await page.waitForTimeout(900);
        if(kind==='admin'){check('technical-admin-excluded',(await page.locator('body').innerText()).includes('403'));}
        else if(kind==='cic'){
            await page.locator('.attendance-state-actions [data-status="MedicalLeave"]').waitFor();
            check('CIC-existing-UI-allows-medical-not-work',await page.locator('.attendance-state-actions [data-status="AtWork"]').isDisabled()&&await page.locator('.attendance-state-actions [data-status="BusinessTrip"]').isDisabled()&&await page.locator('.attendance-state-actions [data-status="MedicalLeave"]').isEnabled());
            const response=page.waitForResponse(r=>r.url().endsWith('/attendance/mark'));
            await page.locator('.attendance-state-actions [data-status="MedicalLeave"]').click();check('CIC-medical-after-integration',(await response).status()===200);
        }else{
            await page.locator('#content [data-action="own"]').waitFor();
            check('no-premature-approve-button-'+kind,await page.locator('#content [data-action="approve"]').count()===0);
            check('native-role-booking-button-'+kind,(await page.locator('#content [data-action="create"]').count()>0)===(kind!=='viewer'));
            if(kind==='ui'){
                const row=page.locator(`article[data-id="${state.browserConflict}"]`);await row.waitFor();
                await row.locator('[data-action="edit"]').click();await page.locator('.modal textarea[data-name="description"]').fill('Description update must revalidate worked day');
                const update=page.waitForResponse(r=>r.url().endsWith(`/${state.browserConflict}/update`));await page.locator('.modal [data-name="submit"]').click();const denied=await update;
                check('original-edit-modal-conflict-is-backend-409',denied.status()===409,(await denied.headerValue('x-status-reason')));
                check('conflict-reason-native-header',String(await denied.headerValue('x-status-reason')).includes('attendance'));
                await page.waitForTimeout(300);check('failed-edit-preserves-reusable-open-form',await page.locator('.modal [data-name="submit"]').isEnabled());
                // Native API errors may display a persistent closable notification over modal buttons.
                const closeError=page.locator('#notification button.close');if(await closeError.isVisible()){await closeError.click();}
                await page.locator('.modal [data-name="cancel"]').click();
                await row.locator('[data-action="withdraw"]').click();await page.locator('.modal [data-name="confirm"]').waitFor();
                const withdraw=page.waitForResponse(r=>r.url().endsWith(`/${state.browserConflict}/withdraw`));await page.locator('.modal [data-name="confirm"]').click();check('withdraw-conflicted-Pending-through-original-UI',(await withdraw).status()===200);
                await page.waitForTimeout(500);check('Cancelled-history-kept-not-deleted',await row.count()===1&&await row.locator('[data-action="edit"]').count()===0);
                await page.goto(base+'#EmployeeAttendance');await page.locator('.attendance-day-list tr').first().waitFor();
                check('same-user-personal-attendance-still-operational',await page.locator('.attendance-day-list').innerText().then(t=>t.length>0));
                await page.screenshot({path:'/feasibility/employee-stage5-employee.png',fullPage:true});
            }
            if(kind==='manager'){
                await page.locator('#content [data-action="queue"]').click();const row=page.locator(`article[data-id="${state.browserCycle}"]`);await row.waitFor();
                check('CancellationPending-private-queue-active',await row.locator('[data-action="acceptCancellation"]').count()===1);
                await row.locator('[data-action="acceptCancellation"]').click();await page.locator('.modal [data-name="confirm"]').waitFor();
                const cancel=page.waitForResponse(r=>r.url().endsWith(`/${state.browserCycle}/decideCancellation`));await page.locator('.modal [data-name="confirm"]').click();const result=await cancel;check('final-cancellation-through-reused-native-confirm',result.status()===200,result.status());
                if(result.status()===200){check('final-API-state-Cancelled',(await result.json()).status==='Cancelled');}
                await page.waitForTimeout(500);check('Cancelled-no-longer-in-active-queue',await row.count()===0);
                await page.goto(base+'#EmployeeAttendanceOverview');await page.locator('[data-overview-month-select]').waitFor();
                await page.locator('[data-overview-month-select]').selectOption('2026-09');await page.waitForTimeout(600);
                const select=page.locator(`[data-action="manager-mark-attendance"][data-user-id="${state.users.four.id}"][data-date="2026-09-14"]`);
                await select.waitFor();check('cancelled-absence-restores-frozen-manager-editor',await select.isEnabled());
                const corrected=page.waitForResponse(r=>r.url().endsWith('/attendance/managerMark'));await select.selectOption('MedicalLeave');await page.locator('.modal textarea[data-name="reason"]').fill('Stage5 browser correction after final cancellation');await page.locator('.modal [data-name="submit"]').click();check('frozen-manager-correction-still-audited',(await corrected).status()===200);
                await page.waitForTimeout(500);check('updated-matrix-medical-visible',await select.inputValue()==='MedicalLeave');
                const exported=page.waitForResponse(r=>r.url().endsWith('/attendance/xlsx'));await page.locator('[data-action="download-xlsx"]').click();check('export-after-integrated-correction',(await exported).status()===200);
                await page.screenshot({path:'/feasibility/employee-stage5-manager.png',fullPage:true});
            }
        }
        check('no-JS-errors-'+kind,errors.length===0,errors);await context.close();
    }
    fs.writeFileSync('/feasibility/employee-stage5-browser-results.json',JSON.stringify({version:'0.5.0',results,assertions:results.length,failures},null,2));
    await browser.close();process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1);});
