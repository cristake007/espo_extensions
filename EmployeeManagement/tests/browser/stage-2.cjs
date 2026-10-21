const {chromium} = require('/usr/src/app/node_modules/playwright-core');
const fs = require('fs');
const assert = require('node:assert/strict');
(async () => {
    assert.equal(process.env.EMPLOYEE_TEST_LAB, 'employee-feasibility-20261020');
    const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
    const state=JSON.parse(fs.readFileSync('/feasibility/employee-stage2-state.json','utf8'));
    const base='http://employee-feasibility-20261020-app-1/';const results=[];let failures=0;
    function check(test,ok,observed){const r={test,status:ok?'PASS':'FAIL',observed};results.push(r);if(!ok)failures++;console.log(JSON.stringify(r));}
    const fixture=await browser.newContext();
    const created=await fixture.request.post(base+'api/v1/User', {headers:{'Espo-Authorization':Buffer.from('admin:'+process.env.EMPLOYEE_TEST_ADMIN_PASSWORD).toString('base64')},data:{userName:'em_stage2_ui_'+Date.now(),firstName:'Synthetic',lastName:'Stage2 UI target',type:'regular',isActive:true}});
    assert.equal(created.status(),200,'Native technical User creation must succeed in the isolated lab.');
    state.users.target2=(await created.json()).id;
    await fixture.close();
    for(const kind of (process.env.EMPLOYEE_BROWSER_KINDS || 'manager,attendance,holiday,profiles,entry,weak,fls,employee,admin').split(',')){
        const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
        await page.goto(base+'#EmployeeAdministration');
        await page.locator('input[name="username"]').fill(kind==='admin'?'admin':'em_stage2_'+kind);
        await page.locator('input[name="password"]').fill(kind==='admin'?process.env.EMPLOYEE_TEST_ADMIN_PASSWORD:process.env.EMPLOYEE_TEST_PASSWORD);
        await page.locator('#btn-login').click();
        await page.waitForFunction(()=>!document.querySelector('#login')||document.body.innerText.includes('403'));
        await page.waitForTimeout(600);
        const expected={manager:['attendance','holiday','profiles'],attendance:['attendance'],holiday:['holiday'],profiles:['profiles'],entry:[],weak:[],fls:[],employee:null,admin:null}[kind];
        if(expected===null){check('admin-page-denied-'+kind,(await page.locator('body').innerText()).includes('403'));}
        else{
            check('native-administration-menu-'+kind,await page.locator('a[href="#EmployeeAdministration"]').count()===1);
            const links=await page.locator('#content a[href^="#EmployeeAdministration/"]').evaluateAll(nodes=>nodes.map(n=>n.hash.split('/').pop()).sort());
            check('section-visibility-'+kind,JSON.stringify(links)===JSON.stringify([...expected].sort()),links);
            if(['manager','attendance'].includes(kind)){
                await page.goto(base+'#EmployeeAdministration/attendance');
                await page.locator('[data-action="save"]:not([disabled])').waitFor();
                await page.locator('input[data-name="editablePastMonths"]').fill(kind==='manager'?'3':'4');
                const saved=page.waitForResponse(r=>r.url().endsWith('/EmployeeManagement/administration/attendance')&&r.request().method()==='PUT');
                await page.locator('[data-action="save"]').click();check('attendance-save-ui-'+kind,(await saved).status()===200);
                await page.reload();await page.locator('[data-action="save"]:not([disabled])').waitFor();
                check('attendance-persisted-ui-'+kind,await page.locator('input[data-name="editablePastMonths"]').inputValue()===(kind==='manager'?'3':'4'));
                check('no-archive-button-'+kind,await page.locator('[data-action="initializeArchive"]').count()===0);
            }
            if(kind==='holiday'){
                await page.goto(base+'#EmployeeAdministration/holiday');await page.locator('[data-action="save"]:not([disabled])').waitFor();
                await page.locator('input[data-name="employeeHolidayApprovalBlock1Title"]').fill('UI Stage2 Director');
                const saved=page.waitForResponse(r=>r.url().endsWith('/EmployeeManagement/administration/holiday')&&r.request().method()==='PUT');
                await page.locator('[data-action="save"]').click();check('holiday-save-ui',(await saved).status()===200);
                await page.reload();await page.locator('[data-action="save"]:not([disabled])').waitFor();
                check('holiday-persisted-ui',await page.locator('input[data-name="employeeHolidayApprovalBlock1Title"]').inputValue()==='UI Stage2 Director');
                check('automatic-grant-not-editable',await page.locator('input[data-name="employeeHolidayAutomaticAnnualGrantEnabled"]').count()===0);
            }
            if(kind==='profiles'){
                await page.goto(base+'#EmployeeAdministration/profiles');await page.locator('[data-action="save"]:not([disabled])').waitFor({timeout:60000});
                check('profile-population-ui',await page.locator(`tr[data-user-id="${state.users.target2}"]`).count()===1);
                check('no-unsafe-profile-buttons',await page.locator('[data-action="correctBalance"],[data-action="viewCorrectionHistory"]').count()===0);
                const row=page.locator(`tr[data-user-id="${state.users.target2}"]`);
                await row.locator('input[data-name="balance"]').click();
                await row.locator('input[data-name="balance"]').pressSequentially('0');
                const saved=page.waitForResponse(r=>r.url().endsWith('/profiles/bulkInitialize')&&r.request().method()==='POST');
                await row.locator('[data-action="initializeProfile"]').click();
                check('profile-initialize-ui',(await saved).status()===200);
                await page.waitForTimeout(1200);await page.locator('[data-action="save"]:not([disabled])').waitFor();
                const initializedRow=page.locator(`tr[data-user-id="${state.users.target2}"]`);
                check('profile-no-opening-edit-after-init',await initializedRow.locator('input[data-name="balance"]').count()===0);
                await initializedRow.locator('[data-action="completeInitialSetup"]').click();
                await page.locator('.modal input[data-name="balance"]').click();
                await page.locator('.modal input[data-name="balance"]').pressSequentially('15');
                const completed=page.waitForResponse(r=>r.url().endsWith('/profiles/completeInitialSetup')&&r.request().method()==='POST');
                await page.locator('.modal [data-name="submit"]').click();
                check('preserved-initial-setup-modal-ui',(await completed).status()===200);
                await page.waitForTimeout(800);await page.locator('[data-action="save"]:not([disabled])').waitFor();
                await initializedRow.locator('input[data-name="annualEntitlement"]').click();
                await initializedRow.locator('input[data-name="annualEntitlement"]').press('ControlOrMeta+A');
                await initializedRow.locator('input[data-name="annualEntitlement"]').pressSequentially('24');
                const updated=page.waitForResponse(r=>r.url().endsWith('/profiles/bulkInitialize')&&r.request().method()==='POST');
                await page.locator('[data-action="save"]').click();check('profile-parameter-save-ui',(await updated).status()===200);
            }
            if(!expected.includes('holiday')){
                await page.goto(base+'#EmployeeAdministration/holiday');await page.waitForTimeout(500);
                check('direct-holiday-route-denied-'+kind,(await page.locator('body').innerText()).includes('403'));
            }
        }
        if(['weak','fls','entry','holiday','employee','admin'].includes(kind)){
            await page.goto(base+'#EmployeeAdministration/profiles');await page.waitForTimeout(500);
            check('direct-profiles-route-denied-'+kind,(await page.locator('body').innerText()).includes('403'));
        }
        check('no-js-errors-'+kind,errors.length===0,errors);
        await page.screenshot({path:'/feasibility/employee-stage2-'+kind+'.png',fullPage:true});await context.close();
    }
    await browser.close();fs.writeFileSync('/feasibility/employee-stage2-browser-results.json',JSON.stringify(results,null,2));
    console.log(JSON.stringify({assertions:results.length,failures}));process.exitCode=failures?1:0;
})().catch(e=>{console.error(e);process.exit(1)});
