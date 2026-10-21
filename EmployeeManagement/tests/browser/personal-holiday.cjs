const {chromium} = require('/usr/src/app/node_modules/playwright-core');
const assert = require('node:assert/strict');
const fs = require('fs');

(async () => {
    assert.equal(process.env.EMPLOYEE_TEST_LAB, 'employee-feasibility-20261020');
    const state = JSON.parse(fs.readFileSync('/feasibility/functional-fixes-state.json'));
    const base = 'http://employee-feasibility-20261020-app-1/';
    const password = process.env.EMPLOYEE_TEST_PASSWORD;
    assert(password);

    const browser = await chromium.launch({headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
    const context = await browser.newContext({viewport: {width: 1500, height: 1050}});
    const page = await context.newPage();
    const results = [];
    const errors = [];
    page.setDefaultTimeout(90000);
    page.on('pageerror', error => errors.push(error.message));

    function check(test, ok, observed) {
        results.push({test, status: ok ? 'PASS' : 'FAIL', observed});
        console.log(JSON.stringify(results.at(-1)));
        assert.equal(ok, true, `${test}: ${JSON.stringify(observed)}`);
    }

    async function setDate(input, isoDate) {
        const current = await input.inputValue();
        const [year, month, day] = isoDate.split('-');
        const localized = current.includes('.') ? `${day}.${month}.${year}` :
            current.includes('-') ? isoDate : `${month}/${day}/${year}`;
        await input.click();
        await input.press('ControlOrMeta+A');
        await input.pressSequentially(localized);
        await input.press('Tab');
    }

    await page.goto(base + '#EmployeeHolidayRequest');
    await page.locator('[name="username"]').fill(state.users.employee.name);
    await page.locator('[name="password"]').fill(password);
    await page.locator('#btn-login').click();
    await page.locator('[data-name="holiday-balance"]').waitFor();

    check('compatibility-route-opens-personal-page',
        await page.locator('[data-name="available-days"]').innerText() === '39');
    check('original-balance-information-is-visible',
        await page.locator('[data-name="balance"]').innerText() === '39' &&
        await page.locator('[data-name="pending-days"]').innerText() === '0' &&
        await page.locator('[data-name="annual-entitlement"]').innerText() === '21' &&
        (await page.locator('[data-name="next-reset-date"]').innerText()).length > 0);
    check('non-admin-does-not-see-manager-actions',
        await page.locator('[data-name="manager-actions"]').count() === 0);

    await page.goto(base + '#EmployeeHolidayRequest/create');
    const modal = page.locator('.modal');
    try {
        await modal.locator('input[data-name="dateStartDate"]').waitFor({timeout: 10000});
    } catch (error) {
        await page.screenshot({path: '/work/tests/evidence/personal-holiday-create-route-error.png', fullPage: true});
        console.error(JSON.stringify({url: page.url(), hash: await page.evaluate(() => location.hash), errors,
            content: (await page.locator('#content').innerText()).slice(0, 2000)}));
        throw error;
    }
    check('native-create-route-opens-accounting-form', true);
    await setDate(modal.locator('input[data-name="dateStartDate"]'), '2026-12-07');
    await setDate(modal.locator('input[data-name="dateEndDate"]'), '2026-12-11');
    await modal.locator('textarea[data-name="description"]').fill('Personal Holiday 0.7.3 browser verification');

    const sent = page.waitForResponse(response =>
        response.url().endsWith('/EmployeeManagement/holiday/create') && response.request().method() === 'POST');
    await modal.locator('[data-name="submit"]').click();
    const response = await sent;
    const request = await response.json();
    check('non-admin-five-day-command-succeeds',
        response.status() === 200 && request.days === 5 && request.status === 'Pending' &&
        request.assignedUserId === state.users.employee.id,
        {status: response.status(), id: request.id, days: request.days, requestStatus: request.status});

    const row = page.locator(`article[data-id="${request.id}"]`);
    await row.waitFor();
    check('own-request-and-status-are-visible',
        (await row.innerText()).includes('Personal Holiday 0.7.3 browser verification') &&
        (await row.innerText()).includes('5'));
    check('pending-reservation-and-balance-refresh',
        await page.locator('[data-name="balance"]').innerText() === '34' &&
        await page.locator('[data-name="available-days"]').innerText() === '39' &&
        await page.locator('[data-name="pending-days"]').innerText() === '5');
    check('pending-owner-operations-are-available',
        await row.locator('[data-action="edit"]').count() === 1 &&
        await row.locator('[data-action="withdraw"]').count() === 1);

    await page.goto(base + '#EmployeeManagement/holiday');
    await page.locator(`article[data-id="${request.id}"]`).waitFor();
    check('canonical-and-compatibility-routes-share-the-request', true, request.id);

    const generic = await page.request.post(base + 'api/v1/EmployeeHolidayRequest', {
        data: {dateStartDate: '2027-03-01', dateEndDate: '2027-03-05', description: 'Native create compatibility browser'},
    });
    const genericBody = await generic.json();
    check('native-create-fallback-uses-accounting-service',
        generic.status() === 200 && genericBody.days === 5 && genericBody.status === 'Pending',
        {status: generic.status(), id: genericBody.id, days: genericBody.days});
    check('no-browser-javascript-errors', errors.length === 0, errors);

    await page.screenshot({path: '/work/tests/evidence/personal-holiday-non-admin.png', fullPage: true});
    fs.writeFileSync('/work/tests/evidence/personal-holiday-browser.json', JSON.stringify({
        user: {id: state.users.employee.id, type: 'regular', isAdmin: false},
        request: {id: request.id, dateStart: '2026-12-07', dateEnd: '2026-12-11', days: request.days, status: request.status},
        routes: ['#EmployeeManagement/holiday', '#EmployeeHolidayRequest', '#EmployeeHolidayRequest/create'],
        results,
        assertions: results.length,
        failures: 0,
    }, null, 2));

    await context.close();
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
