const {chromium} = require('/usr/src/app/node_modules/playwright-core');
const assert = require('node:assert/strict');

const base = process.env.EMPLOYEE_TEST_BASE_URL;
const username = process.env.EMPLOYEE_TEST_MANAGER_USERNAME;
const password = process.env.EMPLOYEE_TEST_PASSWORD;
const normalObservationMs = Number(process.env.EMPLOYEE_TEST_NORMAL_POLL_OBSERVATION_MS || 7000);
assert(base && username && password, 'Holiday polling browser-test environment is required.');
assert(Number.isFinite(normalObservationMs) && normalObservationMs >= 0);

(async () => {
    const browser = await chromium.launch({headless:true, args:['--no-sandbox', '--disable-dev-shm-usage']});
    const context = await browser.newContext({viewport:{width:1500, height:1000}});
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    page.setDefaultTimeout(20000);

    const queueUrl = '/api/v1/EmployeeManagement/holiday/queue';
    const approvedUrl = '/api/v1/EmployeeManagement/holiday/approved';
    const queueResponses = [];
    const approvedResponses = [];
    page.on('response', response => {
        if (response.request().method() !== 'GET') { return; }
        if (response.url().endsWith(queueUrl)) { queueResponses.push(Date.now()); }
        if (response.url().endsWith(approvedUrl)) { approvedResponses.push(Date.now()); }
    });

    const setHidden = hidden => page.evaluate(value => {
        window.__employeePollingHidden = value;
        if (!Object.prototype.hasOwnProperty.call(document, 'hidden')) {
            Object.defineProperty(document, 'hidden', {
                configurable:true,
                get:() => window.__employeePollingHidden,
            });
        }
        document.dispatchEvent(new Event('visibilitychange'));
    }, hidden);

    await page.goto(base + '#EmployeeManagement/holiday');
    await page.locator('[name="username"]').fill(username);
    await page.locator('[name="password"]').fill(password);
    await page.locator('#btn-login').click();
    await page.locator('[data-name="holiday-balance"]').waitFor();

    let emptyQueue = true;
    await page.route(`**${queueUrl}`, async route => {
        if (emptyQueue) {
            emptyQueue = false;
            await route.fulfill({
                status:200,
                contentType:'application/json',
                body:JSON.stringify({list:[], total:0, approvalEnabled:true, canDecide:true}),
            });
            return;
        }
        await route.continue();
    });

    await page.locator('[data-action="queue"]').click();
    await page.locator('[data-name="empty-list"]').waitFor();
    const visibleQueue = page.waitForResponse(response => response.url().endsWith(queueUrl));
    await setHidden(true);
    await page.waitForTimeout(500);
    await setHidden(false);
    await visibleQueue;
    await page.locator('article[data-id]').first().waitFor();
    assert.ok(await page.locator('[data-name="approval-state"]').count() > 0,
        'The lab fixture must include ApprovalProcessing rows for the 5-second interval check.');

    queueResponses.length = 0;
    await page.waitForTimeout(6200);
    assert.equal(queueResponses.length, 1, 'Processing mode must make one request after about five seconds.');

    let failNextQueue = true;
    await page.unroute(`**${queueUrl}`);
    await page.route(`**${queueUrl}`, async route => {
        if (failNextQueue) {
            failNextQueue = false;
            await route.abort('failed');
            return;
        }
        await route.continue();
    });
    queueResponses.length = 0;
    await setHidden(true);
    await setHidden(false);
    await page.waitForTimeout(6000);
    assert.equal(queueResponses.length, 0, 'A failed request must back off instead of retrying every five seconds.');
    await page.waitForResponse(response => response.url().endsWith(queueUrl), {timeout:7000});

    const approvedLoad = page.waitForResponse(response => response.url().endsWith(approvedUrl));
    await page.locator('[data-action="approved"]').click();
    await approvedLoad;
    assert.ok(await page.locator('[data-name="approval-document"]').count() > 0,
        'Published approval documents must remain visible.');
    approvedResponses.length = 0;
    await page.waitForTimeout(normalObservationMs);
    assert.equal(
        approvedResponses.length,
        Math.floor(normalObservationMs / 30000),
        'Normal mode must poll once per 30-second interval.',
    );

    const queueLoad = page.waitForResponse(response => response.url().endsWith(queueUrl));
    await page.locator('[data-action="queue"]').click();
    await queueLoad;
    queueResponses.length = 0;
    await page.goto(base + '#EmployeeManagement');
    await page.waitForTimeout(6200);
    assert.equal(queueResponses.length, 0, 'Polling must stop after navigating away from the Holiday view.');
    assert.deepEqual(pageErrors, []);

    console.log(JSON.stringify({
        emptyQueueRecovered:true,
        processingIntervalMs:5000,
        networkBackoffRecovered:true,
        approvedDocumentsVisible:true,
        normalObservationMs,
        normalPollRequests:approvedResponses.length,
        cleanupVerified:true,
    }));
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
