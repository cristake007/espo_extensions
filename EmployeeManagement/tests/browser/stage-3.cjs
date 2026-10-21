const {chromium} = require('/usr/src/app/node_modules/playwright-core');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
    assert.equal(process.env.EMPLOYEE_TEST_LAB,'employee-feasibility-20261020');
    const state=JSON.parse(fs.readFileSync('/feasibility/employee-stage3-state.json','utf8'));
    const base='http://employee-feasibility-20261020-app-1/';
    const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
    const results=[];let failures=0;
    function check(test,ok,observed){const r={test,status:ok?'PASS':'FAIL',observed};results.push(r);if(!ok)failures++;console.log(JSON.stringify(r));}
    async function nativeInput(locator,value){await locator.click();await locator.press('ControlOrMeta+A');await locator.pressSequentially(value);await locator.press('Tab');}
    for(const kind of ['ui','manager','nobook','noentry','admin']){
        const context=await browser.newContext({viewport:{width:1440,height:1000}}),page=await context.newPage(),errors=[];
        page.on('pageerror',e=>{errors.push(e.message);console.log('PAGE ERROR: '+e.message);});
        await page.goto(base+'#EmployeeManagement/holiday');
        await page.locator('input[name="username"]').fill(kind==='admin'?'admin':state.users[kind].name);
        await page.locator('input[name="password"]').fill(kind==='admin'?process.env.EMPLOYEE_TEST_ADMIN_PASSWORD:process.env.EMPLOYEE_TEST_PASSWORD);
        await page.locator('#btn-login').click();await page.waitForTimeout(1800);
        if(['admin','noentry'].includes(kind)){
            check('direct-private-workspace-denied-'+kind,(await page.locator('body').innerText()).includes('403'));
        }else{
            await page.locator('#content [data-action="own"]').waitFor({timeout:30000});
            check('no-premature-approve-'+kind,await page.locator('#content [data-action="approve"]').count()===0);
            check('booking-button-native-scope-'+kind,(await page.locator('#content [data-action="create"]').count()>0)===(kind!=='nobook'));
            check('manager-booking-native-scope-'+kind,(await page.locator('#content [data-action="managed"]').count()>0)===(kind==='manager'));
            if(kind==='ui'){
                await page.locator('#content [data-action="create"]').click();
                await page.locator('.modal input[data-name="dateStartDate"]').waitFor();
                // Native date format is deliberately obtained from UI widgets, not assumed.
                const initial=await page.locator('.modal input[data-name="dateStartDate"]').inputValue();
                const uiDate=day=>initial.includes('.')?`${day}.11.2026`:(initial.includes('-')?`2026-11-${day}`:(Number(initial.split('/')[0])>12?`${day}/11/2026`:`11/${day}/2026`));
                await nativeInput(page.locator('.modal input[data-name="dateStartDate"]'),uiDate('23'));
                await nativeInput(page.locator('.modal input[data-name="dateEndDate"]'),uiDate('24'));
                await page.locator('.modal textarea[data-name="description"]').fill('PRIVATE browser Holiday request');
                const created=page.waitForResponse(r=>r.url().endsWith('/EmployeeManagement/holiday/create')&&r.request().method()==='POST');
                await page.locator('.modal [data-name="submit"]').click();const response=await created;check('reused-booking-modal-create',response.status()===200,await response.text());
                if(response.status()===200){
                    const record=await response.json();check('native-date-input-requested-interval',record.dateStartDate==='2026-11-23'&&record.dateEndDate==='2026-11-24',record);
                    const row=page.locator(`article[data-id="${record.id}"]`);await row.waitFor();
                    check('private-request-rendered',await row.innerText().then(t=>t.includes('PRIVATE browser Holiday request')));
                    await row.locator('[data-action="edit"]').click();
                    await page.locator('.modal textarea[data-name="description"]').fill('PRIVATE browser Holiday modified');
                    const updated=page.waitForResponse(r=>r.url().endsWith(`/${record.id}/update`));await page.locator('.modal [data-name="submit"]').click();check('reused-booking-modal-update',(await updated).status()===200);
                    await row.locator('[data-action="withdraw"]').waitFor();await row.locator('[data-action="withdraw"]').click();
                    await page.locator('.modal [data-name="confirm"]').waitFor();const withdrawn=page.waitForResponse(r=>r.url().endsWith(`/${record.id}/withdraw`));await page.locator('.modal [data-name="confirm"]').click();check('withdraw-through-native-confirm',(await withdrawn).status()===200);
                    await page.waitForTimeout(800);check('withdraw-history-retained',await row.count()===1&&await row.locator('[data-action="edit"]').count()===0);
                }
                await page.screenshot({path:'/feasibility/employee-stage3-ui.png',fullPage:true});
            }
            if(kind==='manager'){
                check('authorized-queue-visible',await page.locator('#content [data-action="queue"]').count()===1);
                await page.locator('#content [data-action="queue"]').click();await page.waitForTimeout(700);
                check('authorized-queue-includes-employees',await page.locator('#content').innerText().then(t=>t.includes('Stage3 race')));
                const reject=page.locator('article [data-action="reject"]').first();
                await reject.click();await page.locator('.modal [data-name="confirm"]').waitFor();
                const rejected=page.waitForResponse(r=>r.url().endsWith('/reject'));await page.locator('.modal [data-name="confirm"]').click();check('reject-ui-atomic',(await rejected).status()===200);
                await page.goto(base+'#EmployeeAdministration/profiles');await page.locator('[data-action="save"]:not([disabled])').waitFor({timeout:30000});
                const row=page.locator(`tr[data-user-id="${state.users.ui.id}"]`);await row.locator('[data-action="correctBalance"]').click();
                await nativeInput(page.locator('.modal input[data-name="balance"]'),'35');
                await page.locator('.modal textarea[data-name="reason"]').fill('Synthetic browser correction');
                const corrected=page.waitForResponse(r=>r.url().endsWith('/profiles/correct'));await page.locator('.modal [data-name="submit"]').click();check('original-correction-modal-ui',(await corrected).status()===200);
                await page.waitForTimeout(1000);await row.locator('[data-action="viewCorrectionHistory"]').click();await page.waitForTimeout(600);
                check('original-history-modal-ui',await page.locator('.modal').innerText().then(t=>t.includes('Synthetic browser correction')));
                await page.screenshot({path:'/feasibility/employee-stage3-manager.png',fullPage:true});
            }
        }
        check('no-JS-errors-'+kind,errors.length===0,errors);await context.close();
    }
    fs.writeFileSync('/feasibility/employee-stage3-browser-results.json',JSON.stringify({results,assertions:results.length,failures},null,2));
    await browser.close();process.exit(failures?1:0);
})().catch(e=>{console.error(e);process.exit(1);});
