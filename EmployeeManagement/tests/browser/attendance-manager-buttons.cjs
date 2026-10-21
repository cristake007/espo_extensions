const {chromium} = require('/usr/src/app/node_modules/playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');

(async () => {
    assert.equal(process.env.EMPLOYEE_TEST_LAB, 'employee-feasibility-20261020');
    const state = JSON.parse(fs.readFileSync('/feasibility/employee-stage4-state.json', 'utf8'));
    const base = 'http://employee-feasibility-20261020-app-1/';
    const browser = await chromium.launch({headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
    const context = await browser.newContext({viewport: {width: 1650, height: 1080}});
    const page = await context.newPage();
    const results = [];
    const pageErrors = [];
    let failures = 0;

    page.setDefaultTimeout(120000);
    page.on('pageerror', error => pageErrors.push(error.message));
    function check(test, condition, observed = null) {
        const result = {test, status: condition ? 'PASS' : 'FAIL', observed};
        results.push(result);
        if (!condition) failures++;
        console.log(JSON.stringify(result));
    }

    await page.goto(base + '#EmployeeAttendanceOverview');
    await page.locator('input[name="username"]').fill(state.users.manager.name);
    await page.locator('input[name="password"]').fill(process.env.EMPLOYEE_TEST_PASSWORD);
    await page.locator('#btn-login').click();
    await page.locator('[data-overview-month-select]').waitFor();
    await page.locator('[data-overview-month-select]').selectOption('2026-10');

    const employeeId = state.users.four.id;
    const selector = status => `[data-action="manager-mark-attendance"][data-user-id="${employeeId}"][data-date="2026-10-01"][data-status="${status}"]`;
    const group = page.locator(selector('AtWork')).locator('..');
    await page.locator(selector('AtWork')).waitFor();
    const rendered = group.locator('[data-action="manager-mark-attendance"]');
    check('editable-cell-renders-three-buttons', await rendered.count() === 3, await rendered.allTextContents());
    check('buttons-use-compact-P-D-CM-labels', JSON.stringify(await rendered.allTextContents()) === JSON.stringify(['P', 'D', 'CM']), await rendered.allTextContents());
    const accessibleLabels = await rendered.evaluateAll(items => items.map(item => ({
        label: item.getAttribute('aria-label'),
        title: item.title,
        shortLabel: item.textContent,
    })));
    check('buttons-have-full-accessible-labels', accessibleLabels.every(item =>
        item.label === item.title && item.label !== item.shortLabel && item.label.length > 2
    ), accessibleLabels);
    const groupBox = await group.boundingBox();
    check('button-group-remains-compact', groupBox !== null && groupBox.width <= 100, groupBox);
    if (await page.locator(selector('BusinessTrip')).getAttribute('aria-pressed') !== 'true') {
        const revision = Number(await page.locator(selector('BusinessTrip')).getAttribute('data-revision'));
        const normalization = await page.request.post(base + 'api/v1/EmployeeManagement/attendance/managerMarkRoutine', {
            data: {userId: employeeId, date: '2026-10-01', status: 'BusinessTrip', revision},
        });
        assert.equal(normalization.status(), 200);
        await page.reload();
        await page.locator(selector('BusinessTrip') + '[aria-pressed="true"]').waitFor();
    }
    check('current-business-trip-is-highlighted',
        await page.locator(selector('BusinessTrip')).getAttribute('aria-pressed') === 'true' &&
        await page.locator(selector('BusinessTrip')).evaluate(element => element.classList.contains('active')));

    let routineRequests = 0;
    const countRoutine = request => {
        if (request.url().endsWith('/EmployeeManagement/attendance/managerMarkRoutine')) routineRequests++;
    };
    page.on('request', countRoutine);
    await page.locator(selector('BusinessTrip')).click();
    await page.waitForTimeout(600);
    check('clicking-active-status-does-not-write', routineRequests === 0, routineRequests);

    const toWork = page.waitForResponse(response => response.url().endsWith('/attendance/managerMarkRoutine'));
    await page.locator(selector('AtWork')).click();
    const workResponse = await toWork;
    const workPayload = workResponse.request().postDataJSON();
    await page.locator(selector('AtWork') + '[aria-pressed="true"]').waitFor();
    check('single-click-saves-without-reason', workResponse.status() === 200 && !Object.hasOwn(workPayload, 'reason'), {status: workResponse.status(), payload: workPayload});
    check('normal-marking-opens-no-modal', await page.locator('.modal').count() === 0);

    const toTrip = page.waitForResponse(response => response.url().endsWith('/attendance/managerMarkRoutine'));
    await page.locator(selector('BusinessTrip')).click();
    check('manager-can-correct-a-mistake-with-another-button', (await toTrip).status() === 200);
    await page.locator(selector('BusinessTrip') + '[aria-pressed="true"]').waitFor();

    const requestsBeforeRapidClick = routineRequests;
    const rapidResponse = page.waitForResponse(response => response.url().endsWith('/attendance/managerMarkRoutine'));
    await page.locator(selector('AtWork')).click();
    await page.locator(selector('MedicalLeave')).evaluate(element => element.click());
    check('rapid-click-request-succeeds', (await rapidResponse).status() === 200);
    await page.locator(selector('AtWork') + '[aria-pressed="true"]').waitFor();
    check('rapid-clicks-produce-one-write', routineRequests - requestsBeforeRapidClick === 1, routineRequests - requestsBeforeRapidClick);

    await page.route('**/EmployeeManagement/attendance/managerMarkRoutine', route => route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({message: 'Synthetic temporary failure'}),
    }));
    const failedRequest = page.waitForResponse(response => response.url().endsWith('/attendance/managerMarkRoutine'));
    await page.locator(selector('MedicalLeave')).click();
    await failedRequest;
    await page.waitForTimeout(400);
    check('failed-request-restores-previous-button',
        await page.locator(selector('AtWork')).getAttribute('aria-pressed') === 'true' &&
        await page.locator(selector('MedicalLeave')).getAttribute('aria-pressed') === 'false');
    await page.unroute('**/EmployeeManagement/attendance/managerMarkRoutine');

    const cicId = state.users.cic.id;
    const cicSelector = `[data-action="manager-mark-attendance"][data-user-id="${cicId}"][data-date="2026-10-02"]`;
    check('cic-cell-exposes-only-permitted-medical-button',
        await page.locator(cicSelector).count() === 1 &&
        await page.locator(cicSelector).getAttribute('data-status') === 'MedicalLeave');

    const monthResponse = page.waitForResponse(response => response.url().includes('/attendance/overview?month=2026-09'));
    await page.locator('[data-overview-month-select]').selectOption('2026-09');
    await monthResponse;
    await page.waitForTimeout(300);
    check('frozen-month-has-no-routine-buttons', await page.locator('[data-action="manager-mark-attendance"]').count() === 0);
    check('no-browser-javascript-errors', pageErrors.length === 0, pageErrors);
    await page.screenshot({path: '/work/tests/evidence/attendance-manager-0.8.11.png', fullPage: false});

    const result = {version: '0.8.11', results, assertions: results.length, failures};
    fs.writeFileSync('/work/tests/evidence/attendance-manager-0.8.11-browser.json', JSON.stringify(result, null, 2));
    await context.close();
    await browser.close();
    process.exit(failures ? 1 : 0);
})().catch(error => {
    console.error(error);
    process.exit(1);
});
