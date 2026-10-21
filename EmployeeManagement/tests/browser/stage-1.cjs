/* Runs in the disposable local Chromium container attached to the existing lab. */
const {chromium} = require('/usr/src/app/node_modules/playwright-core');
const fs = require('fs');
const assert = require('node:assert/strict');

(async () => {
    assert.equal(process.env.EMPLOYEE_TEST_LAB, 'employee-feasibility-20261020');
    const state = JSON.parse(fs.readFileSync('/feasibility/employee-stage1-state.json', 'utf8'));
    const browser = await chromium.launch({headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
    const base = 'http://employee-feasibility-20261020-app-1/';
    const results = [];
    let failures = 0;
    function check(test, ok, observed) {
        const row = {test, status: ok ? 'PASS' : 'FAIL', observed};
        results.push(row);
        console.log(JSON.stringify(row));
        if (!ok) { failures++; }
    }

    for (const kind of ['manager', 'owner', 'colleague', 'unconfigured', 'admin']) {
        const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push({message: error.message, stack: error.stack}));
        await page.goto(base + '#EmployeeManagement');
        await page.locator('input[name="username"]').fill(kind === 'admin' ? 'admin' : 'em_stage1_' + kind);
        await page.locator('input[name="password"]').fill(kind === 'admin'
            ? process.env.EMPLOYEE_TEST_ADMIN_PASSWORD : process.env.EMPLOYEE_TEST_PASSWORD);
        await page.locator('#btn-login').click();
        await page.waitForFunction(() => !document.querySelector('#login') || document.body.innerText.includes('403'), {timeout: 30000});
        await page.waitForTimeout(1200);

        if (['manager', 'owner', 'colleague'].includes(kind)) {
            check('browser-workspace-' + kind, await page.locator('#content').innerText().then(t => t.includes('0.1.0')));
            check('browser-four-native-record-links-' + kind, await page.locator('#content a[href^="#Employee"]').count() === 4);
            check('browser-administration-menu-' + kind,
                await page.locator('a[href="#EmployeeAdministration"]').count() === (kind === 'manager' ? 1 : 0));

            await page.goto(base + '#EmployeeHolidayRequest');
            await page.locator('#content table').first().waitFor({timeout: 20000});
            await page.waitForTimeout(500);
            check('browser-native-list-' + kind, await page.locator('#content').innerText().then(t => t.includes('EM fixture holiday')));
            check('browser-no-create-button-' + kind, await page.locator('#content [data-action="create"]').count() === 0);

            await page.goto(base + '#EmployeeHolidayRequest/view/' + state.records.owner.EmployeeHolidayRequest);
            await page.waitForTimeout(1500);
            const text = await page.locator('body').innerText();
            check('browser-private-detail-' + kind,
                kind === 'colleague' ? (text.includes('403') && !text.includes('EM PRIVATE NOTE owner')) : text.includes('EM PRIVATE NOTE owner'));
            if (kind !== 'colleague') {
                check('browser-no-edit-button-' + kind, await page.locator('#content [data-action="edit"]').count() === 0);
            }
            await page.goto(base + '#EmployeeAdministration');
            await page.waitForTimeout(1000);
            const adminText = await page.locator('body').innerText();
            check('browser-delegated-route-' + kind, kind === 'manager'
                ? adminText.includes('0.1.0') && (adminText.includes('next stage') || adminText.includes('etapa următoare'))
                : adminText.includes('403'));
        } else {
            check('browser-workspace-denied-' + kind, await page.locator('body').innerText().then(t => t.includes('403')));
        }
        // Core Record view may surface its native Forbidden exception after rendering 403.
        const unexpected = errors.filter(e => !(kind === 'colleague' && e.message === 'i'));
        check('browser-no-unexpected-js-errors-' + kind, unexpected.length === 0, errors);
        await page.screenshot({path: '/feasibility/employee-stage1-browser-' + kind + '.png', fullPage: true});
        await context.close();
    }
    await browser.close();
    fs.writeFileSync('/feasibility/employee-stage1-browser-results.json', JSON.stringify(results, null, 2));
    console.log(JSON.stringify({assertions: results.length, failures}));
    process.exitCode = failures ? 1 : 0;
})().catch(error => {console.error(error); process.exit(1);});
