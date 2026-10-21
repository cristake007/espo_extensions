const {chromium}=require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
    assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
    const state=JSON.parse(fs.readFileSync('/feasibility/employee-stage4-state.json','utf8')),base='http://employee-feasibility-20261020-app-1/';
    const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
    const results=[];let failures=0;
    function check(test,ok,observed){const r={test,status:ok?'PASS':'FAIL',observed};results.push(r);if(!ok)failures++;console.log(JSON.stringify(r));}
    for(const kind of ['ui','cic','manager','viewer','noexport','admin']){
        const context=await browser.newContext({viewport:{width:1600,height:1050}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>{errors.push(e.message);console.log('PAGE ERROR: '+e.message);});
        page.setDefaultTimeout(20000);
        await page.goto(base+(kind==='manager'||kind==='viewer'||kind==='noexport'?'#EmployeeAttendanceOverview':'#EmployeeAttendance'));
        await page.locator('input[name="username"]').fill(kind==='admin'?'admin':state.users[kind].name);
        await page.locator('input[name="password"]').fill(kind==='admin'?process.env.EMPLOYEE_TEST_ADMIN_PASSWORD:process.env.EMPLOYEE_TEST_PASSWORD);
        await page.locator('#btn-login').click();await page.waitForTimeout(1200);
        if(kind==='admin'){check('admin-personal-route-denied',(await page.locator('body').innerText()).includes('403'));}
        if(kind==='ui'){
            const today=page.locator(`.attendance-state-actions [data-date="${state.today}"][data-status="AtWork"]`);await today.waitFor();
            check('original-personal-view-loaded',await page.locator('.attendance-day-list tr').count()>0);
            const marked=page.waitForResponse(r=>r.url().endsWith('/EmployeeManagement/attendance/mark')&&r.request().method()==='POST');await today.click();const markedResponse=await marked;check('native-personal-mark-ui',markedResponse.status()===200,markedResponse.status());
            await page.goto(base+'#EmployeeAttendance/month/2026-09');await page.reload();await page.locator('.attendance-day-list tr').first().waitFor();await page.waitForTimeout(500);
            check('frozen-month-no-personal-day-buttons',await page.locator('.attendance-day-list [data-action="mark-attendance"]').count()===0);
            await page.screenshot({path:'/feasibility/employee-stage4-personal.png',fullPage:true});
        }
        if(kind==='cic'){
            await page.locator('.attendance-state-actions [data-status="MedicalLeave"]').waitFor();
            check('CIC-UI-medical-only',await page.locator('.attendance-state-actions [data-status="AtWork"]').isDisabled()&&await page.locator('.attendance-state-actions [data-status="BusinessTrip"]').isDisabled()&&await page.locator('.attendance-state-actions [data-status="MedicalLeave"]').isEnabled());
            const medical=page.waitForResponse(r=>r.url().endsWith('/attendance/mark'));
            await page.locator('.attendance-state-actions [data-status="MedicalLeave"]').click();check('CIC-medical-original-UI',(await medical).status()===200);
        }
        if(['manager','viewer','noexport'].includes(kind)){
            await page.locator('[data-overview-month-select]').waitFor();await page.locator('[data-overview-month-select]').selectOption('2026-09');await page.waitForTimeout(900);
            const selector=page.locator(`[data-action="manager-mark-attendance"][data-user-id="${state.users.four.id}"][data-date="2026-09-14"]`);
            check('matrix-management-vs-readonly-'+kind,(await selector.count()>0)===(kind!=='viewer'));
            check('reminders-visible-only-manager-'+kind,(await page.locator('[data-action="send-reminders"]').isVisible())===(kind!=='viewer'));
            check('native-export-button-permission-'+kind,(await page.locator('[data-action="download-xlsx"]').isEnabled())===(kind!=='noexport'));
            if(kind==='manager'){
                const corrected=page.waitForResponse(r=>r.url().endsWith('/attendance/managerMark'));
                await selector.selectOption('BusinessTrip');await page.locator('.modal textarea[data-name="reason"]').fill('Synthetic frozen correction from original matrix');await page.locator('.modal [data-name="submit"]').click();const correctedResponse=await corrected;check('frozen-manager-correction-native-modal-ui',correctedResponse.status()===200,correctedResponse.status());
                await page.waitForTimeout(800);check('corrected-cell-visible',await selector.inputValue()==='BusinessTrip');
                const exported=page.waitForResponse(r=>r.url().endsWith('/attendance/xlsx'));await page.locator('[data-action="download-xlsx"]').click();check('original-XLSX-download-ui',(await exported).status()===200);
                await page.goto(base+'#EmployeeAdministration/attendanceMetadata');await page.locator(`.attendance-schedule-row[data-user-id="${state.users.remote.id}"]`).waitFor();
                check('no-WTC-time-editor-ui',await page.locator('[data-schedule-field="startTime"],[data-schedule-field="endTime"]').count()===0);
                const row=page.locator(`.attendance-schedule-row[data-user-id="${state.users.remote.id}"]`);await row.locator('[data-schedule-field="remote"]').uncheck();
                const saved=page.waitForResponse(r=>r.url().endsWith('/attendance/schedule'));await row.locator('[data-action="save-work-schedule"]:not([disabled])').click();check('original-operational-settings-ui',(await saved).status()===200);
                await page.screenshot({path:'/feasibility/employee-stage4-metadata.png',fullPage:true});
            }
        }
        check('no-JS-errors-'+kind,errors.length===0,errors);await context.close();
    }
    fs.writeFileSync('/feasibility/employee-stage4-browser-results.json',JSON.stringify({version:'0.4.0',results,assertions:results.length,failures},null,2));await browser.close();process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1);});
