const {chromium}=require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
    assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
    const s=JSON.parse(fs.readFileSync('/feasibility/employee-stage7-state.json','utf8'));
    const variant=process.argv[2]||'legacy',base='http://employee-feasibility-20261020-app-1/';
    const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
    const results=[];let failures=0;
    function check(test,ok,observed){const r={test,status:ok?'PASS':'FAIL',observed};results.push(r);if(!ok)failures++;console.log(JSON.stringify(r));}
    for(const kind of ['manager','employee','colleague','denied','fls','admin']){
        const context=await browser.newContext({viewport:{width:1550,height:1080},acceptDownloads:true}),page=await context.newPage(),errors=[];
        page.on('pageerror',e=>errors.push({message:e.message,stack:e.stack}));page.setDefaultTimeout(45000);
        if(kind==='admin'){page.on('response',r=>{if(r.status()>=400){console.log(JSON.stringify({diagnostic:'admin-http',status:r.status(),url:r.url()}));}});}
        const login=kind==='manager'&&variant==='legacy'?'#EmployeeManagement/holiday':'#Calendar/show/mode=month&date=2026-11-15';
        await page.goto(base+login);await page.locator('input[name="username"]').fill(kind==='admin'?'admin':s.users[kind].name);await page.locator('input[name="password"]').fill(kind==='admin'?process.env.EMPLOYEE_TEST_ADMIN_PASSWORD:process.env.EMPLOYEE_TEST_PASSWORD);await page.locator('#btn-login').click();
        if(kind==='manager'&&variant==='legacy'){
            const row=page.locator(`article[data-id="${s.browserSelf}"]`);await row.locator('[data-action="approve"]').waitFor();await row.locator('[data-action="approve"]').click();await page.locator('.modal [data-name="confirm"]').waitFor();const approved=page.waitForResponse(r=>r.url().endsWith('/'+s.browserSelf+'/approve'));await page.locator('.modal [data-name="confirm"]').click();const r=await approved;check('manager-self-approval-real-native-Job',r.status()===200&&(await r.json()).status==='ApprovalProcessing');
            await row.locator('[data-name="approval-document"]').first().waitFor();const download=page.waitForEvent('download');await row.locator('[data-name="approval-document"]').first().click();const d=await download;check('browser-private-DOCX-after-native-Job',await d.failure()===null&&fs.readFileSync(await d.path()).subarray(0,2).toString()==='PK');
            await page.goto(base+'#EmployeeAdministration/holiday');const checkbox=page.locator('[data-name="employeeHolidayAutomaticAnnualGrantEnabled"] input[type="checkbox"]');await checkbox.waitFor();check('annual-opt-in-field-native-editable',await checkbox.isEnabled()&&!await checkbox.isChecked());
            for(const enabled of [true,false]){await checkbox.setChecked(enabled);const saved=page.waitForResponse(r=>r.url().endsWith('/EmployeeManagement/administration/holiday')&&r.request().method()==='PUT');await page.locator('#content [data-action="save"]').click();const response=await saved;check('manager-explicit-annual-setting-'+enabled,response.status()===200&&(await response.json()).employeeHolidayAutomaticAnnualGrantEnabled===enabled);}
            check('delegated-page-no-WTC-or-global-Role-editor',await page.locator('#content [data-name="workingTimeCalendar"],#content [data-name="roles"],#content a[href="#Admin"]').count()===0);
            await page.goto(base+'#Calendar/show/mode=month&date=2026-11-15');
        }
        await page.locator('.fc-daygrid-body').waitFor();await page.waitForTimeout(700);
        const employeeName=s.users.employee.displayName||'Calendar Employee employee',managerName=s.users.manager.displayName||'Calendar Employee manager';
        const event=page.locator('.fc-event').filter({hasText:employeeName});
        if(['manager','employee','colleague'].includes(kind)){
            await event.first().waitFor();check('common-CancellationPending-visible-'+kind,await event.count()>0);check('Approved-common-visible-'+kind,await page.locator('.fc-event').filter({hasText:managerName}).count()>0);
            await event.first().click();const modal=page.locator('.modal').last();await modal.locator('.field[data-name="dateStart"]').waitFor();check('native-click-opens-minimal-detail-'+kind,await modal.locator('.field[data-name="name"]').count()===1&&await modal.locator('.field[data-name="dateEnd"]').count()===1);
            const body=await modal.innerText();check('minimal-detail-no-private-data-'+kind,!body.includes('PRIVATE')&&await modal.locator('[data-name="description"],[data-name="balance"],[data-name="approvalDocuments"],[data-name="sourceId"],a[href*="EmployeeHolidayRequest/view"]').count()===0);check('minimal-detail-no-mutation-button-'+kind,await modal.locator('[data-name="edit"],[data-action="edit"],[data-name="remove"],[data-action="remove"]').count()===0);
            await modal.locator('[data-dismiss="modal"],button.close').first().click();
            if(kind==='colleague'){
                const r=await page.request.get(base+'api/v1/EmployeeHolidayRequest/'+s.approved);check('public-detail-no-private-source-access-browser',r.status()===403,r.status());const d=await page.request.get(base+'?entryPoint=download&id='+s.documentId);check('public-detail-no-document-access-browser',d.status()===403,d.status());
                for(const [mode,date] of [['listWeek','2026-11-02'],['agendaWeek','2026-11-02'],['agendaDay','2026-11-03']]){await page.goto(base+'#Calendar/show/mode='+mode+'&date='+date);await page.locator('.fc-event').filter({hasText:employeeName}).first().waitFor();check('native-calendar-mode-'+mode,await page.locator('.fc-event').filter({hasText:employeeName}).count()>0);}
                if(s.singleDay){await page.goto(base+'#Calendar/show/mode=agendaDay&date=2026-11-04');await page.locator('.fc-event').filter({hasText:managerName}).first().waitFor();check('single-day-event-visible-in-native-Day-browser',await page.locator('.fc-event').filter({hasText:managerName}).count()>0);}
                await page.goto(base+'#Calendar/show/mode=month&date=2026-11-15');await page.locator('.fc-daygrid-body').waitFor();
            }
        }else{check('no-public-event-without-business-access-'+kind,await event.count()===0);}
        if(variant==='zile'&&['manager','employee','colleague'].includes(kind)){
            await page.locator('[data-date="2026-11-30"].zile-sarbatoare-non-working-day').first().waitFor();check('ZileSarbatoare-native-decoration-preserved-'+kind,await page.locator('[data-date="2026-11-30"].zile-sarbatoare-non-working-day').count()>0);
        }
        check('no-JS-errors-'+kind,errors.length===0,errors);await page.screenshot({path:'/feasibility/employee-stage7-'+variant+'-'+kind+'.png',fullPage:true});await context.close();
    }
    fs.writeFileSync('/feasibility/employee-stage7-browser-'+variant+'-results.json',JSON.stringify({version:'0.7.0',variant,results,assertions:results.length,failures},null,2));await browser.close();process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1);});
