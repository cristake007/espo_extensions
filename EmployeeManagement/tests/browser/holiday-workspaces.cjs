const {chromium} = require('/usr/src/app/node_modules/playwright-core');
const assert = require('node:assert/strict');
const fs = require('fs');

(async () => {
    assert.equal(process.env.EMPLOYEE_TEST_LAB, 'employee-feasibility-20261020');
    const state = JSON.parse(fs.readFileSync('/feasibility/employee-holiday-workspaces-0.8.15-state.json'));
    const base = 'http://employee-feasibility-20261020-app-1/';
    const password = process.env.EMPLOYEE_TEST_PASSWORD;
    assert(password);
    const browser = await chromium.launch({headless:true, args:['--no-sandbox', '--disable-dev-shm-usage']});
    const results = [];
    const screenshots = [];

    function check(test, ok, observed) {
        results.push({test, status:ok ? 'PASS' : 'FAIL', observed});
        console.log(JSON.stringify(results.at(-1)));
        assert.equal(ok, true, `${test}: ${JSON.stringify(observed)}`);
    }

    async function login(userName, route, viewport = {width:1500, height:1050}) {
        const context = await browser.newContext({viewport});
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.setDefaultTimeout(30000);
        await page.goto(base + '#' + route);
        await page.locator('[name="username"]').fill(userName);
        await page.locator('[name="password"]').fill(password);
        await page.locator('#btn-login').click();
        await page.locator('#btn-login').waitFor({state:'hidden'});
        return {context, page, errors};
    }

    async function shot(page, name) {
        const path = `/work/tests/evidence/${name}`;
        await page.screenshot({path, fullPage:false});
        screenshots.push(path);
    }

    const employee = await login(state.employee, 'EmployeeHolidayRequest');
    await employee.page.locator('[data-name="holiday-balance"]').waitFor();
    check('employee-personal-page-has-no-manager-workspace',
        await employee.page.locator('[data-name="manager-actions"], .employee-holiday-management-options, [data-action="managed"], [data-action="approve"], [data-action="reject"], [data-action="cancel"], [data-action="acceptCancellation"], [data-action="denyCancellation"]').count() === 0);
    check('employee-personal-balance-and-requests-remain-visible',
        await employee.page.locator('[data-name="balance"]').count() === 1 &&
        await employee.page.locator('.employee-holiday-request-list').count() === 1);
    await employee.page.locator('[data-action="create"]').click();
    await employee.page.locator('.modal [data-name="daysCalculation"]').waitFor();
    check('personal-request-action-reuses-existing-booking-form',
        await employee.page.locator('.modal [data-name="employeeField"]').count() === 1);
    await employee.page.locator('.modal [data-name="cancel"]').click();
    const editablePersonalRow = employee.page.locator('article:has([data-action="edit"])').first();
    if (await editablePersonalRow.count()) {
        await editablePersonalRow.locator('[data-action="edit"]').click();
        await employee.page.locator('.modal [data-name="daysCalculation"]').waitFor();
        check('personal-edit-remains-available-for-pending-request', true);
        await employee.page.locator('.modal [data-name="cancel"]').click();
    }
    await shot(employee.page, 'holiday-workspaces-employee-personal-desktop.png');
    const queueDenied = await employee.page.request.get(base + 'api/v1/EmployeeManagement/holiday/queue');
    check('employee-manager-api-remains-denied', queueDenied.status() === 403, queueDenied.status());
    await employee.page.goto(base + '#EmployeeHolidayManagement');
    await employee.page.waitForFunction(() => document.body.innerText.includes('403'));
    check('employee-direct-manager-route-is-denied', true);
    check('employee-no-javascript-errors', employee.errors.length === 0, employee.errors);
    await employee.context.close();

    const manager = await login(state.manager, 'EmployeeHolidayRequest');
    await manager.page.locator('[data-name="holiday-balance"]').waitFor();
    check('manager-personal-page-is-still-exclusively-personal',
        await manager.page.locator('[data-name="manager-actions"], .employee-holiday-management-options, [data-action="managed"], [data-action="approve"], [data-action="reject"], [data-action="cancel"], [data-action="acceptCancellation"], [data-action="denyCancellation"]').count() === 0);
    await shot(manager.page, 'holiday-workspaces-manager-personal-desktop.png');

    await manager.page.goto(base + '#EmployeeHolidayManagement');
    await manager.page.locator('.employee-holiday-management-options').waitFor();
    check('manager-landing-has-five-persistent-options',
        await manager.page.locator('.employee-holiday-management-option').count() === 5);
    check('manager-landing-selects-approvals',
        await manager.page.locator('[data-section="approvals"][aria-current="page"]').count() === 1);
    await shot(manager.page, 'holiday-workspaces-manager-landing-desktop.png');

    const appUser = await (await manager.page.request.get(base + 'api/v1/App/user')).json();
    const managerId = appUser.user.id;
    const preferences = await (await manager.page.request.get(base + `api/v1/Preferences/${managerId}`)).json();
    const originalLanguage = preferences.language || 'en_US';
    const romanianSaved = await manager.page.request.put(base + `api/v1/Preferences/${managerId}`, {data:{language:'ro_RO'}});
    check('romanian-language-preference-saves', romanianSaved.status() === 200, romanianSaved.status());
    await manager.page.reload();
    await manager.page.locator('.employee-holiday-management-options').waitFor();
    check('romanian-manager-translation-renders',
        (await manager.page.locator('.employee-holiday-management-hero h3').innerText()).trim() === 'Gestionare concedii');
    await shot(manager.page, 'holiday-workspaces-manager-landing-ro-desktop.png');
    const englishSaved = await manager.page.request.put(base + `api/v1/Preferences/${managerId}`, {data:{language:'en_US'}});
    check('english-language-preference-saves', englishSaved.status() === 200, englishSaved.status());
    await manager.page.reload();
    await manager.page.locator('.employee-holiday-management-options').waitFor();
    check('english-manager-translation-renders',
        (await manager.page.locator('.employee-holiday-management-hero h3').innerText()).trim() === 'Holiday Management');

    const sidebar = manager.page.locator('#navbar a[href="#EmployeeHolidayManagement"]');
    check('native-sidebar-entry-is-visible', await sidebar.count() === 1);
    check('native-sidebar-entry-uses-icon-class',
        await sidebar.locator('.fa-user-shield').count() === 1 || (await sidebar.getAttribute('class') || '').includes('fa-user-shield'));
    await shot(manager.page, 'holiday-workspaces-sidebar-icon.png');

    const queue = await (await manager.page.request.get(base + 'api/v1/EmployeeManagement/holiday/queue')).json();
    const expectedApprovals = queue.list.filter(row => ['Pending', 'ApprovalProcessing'].includes(row.status)).length;
    const expectedCancellations = queue.list.filter(row => row.status === 'CancellationPending').length;
    check('approvals-filter-matches-existing-queue',
        await manager.page.locator('article[data-id]').count() === expectedApprovals,
        {expectedApprovals, rendered:await manager.page.locator('article[data-id]').count()});
    check('approval-processing-is-not-relabeled-as-pending',
        await manager.page.locator('article.employee-holiday-request-processing').count() ===
        queue.list.filter(row => row.status === 'ApprovalProcessing').length);
    check('pending-approval-actions-remain-available',
        await manager.page.locator('article [data-action="approve"]').count() ===
            queue.list.filter(row => row.status === 'Pending').length &&
        await manager.page.locator('article [data-action="reject"]').count() ===
            queue.list.filter(row => row.status === 'Pending').length);
    await shot(manager.page, 'holiday-workspaces-manager-approvals-desktop.png');

    await manager.page.locator('[data-section="cancellations"]').click();
    await manager.page.waitForURL(/#EmployeeHolidayManagement\/cancellations$/);
    await manager.page.locator('[data-section="cancellations"][aria-current="page"]').waitFor();
    check('cancellations-are-separated-from-new-approvals',
        await manager.page.locator('article[data-id]').count() === expectedCancellations,
        {expectedCancellations, rendered:await manager.page.locator('article[data-id]').count()});
    check('cancellation-decision-actions-remain-available',
        await manager.page.locator('article [data-action="acceptCancellation"]').count() === expectedCancellations &&
        await manager.page.locator('article [data-action="denyCancellation"]').count() === expectedCancellations);
    check('all-manager-options-remain-visible-on-cancellations',
        await manager.page.locator('.employee-holiday-management-option').count() === 5);
    await shot(manager.page, 'holiday-workspaces-manager-cancellations-desktop.png');

    await manager.page.goBack();
    await manager.page.locator('[data-section="approvals"][aria-current="page"]').waitFor();
    check('browser-back-restores-selected-manager-section', true);
    await manager.page.goForward();
    await manager.page.locator('[data-section="cancellations"][aria-current="page"]').waitFor();
    check('browser-forward-restores-selected-manager-section', true);

    await manager.page.locator('[data-section="approved"]').click();
    await manager.page.locator('[data-section="approved"][aria-current="page"]').waitFor();
    const approved = await (await manager.page.request.get(base + 'api/v1/EmployeeManagement/holiday/approved')).json();
    check('approved-section-reuses-approved-endpoint',
        await manager.page.locator('article[data-id]').count() === approved.list.length,
        {expected:approved.list.length, rendered:await manager.page.locator('article[data-id]').count()});
    check('approved-manager-cancellation-action-remains-available',
        await manager.page.locator('article [data-action="cancel"]').count() === approved.list.length);
    if (approved.list.length) {
        await manager.page.locator('article [data-action="cancel"]').first().click();
        await manager.page.locator('.modal textarea').waitFor();
        check('approved-cancellation-still-requires-reason-modal', true);
        await manager.page.locator('.modal [data-name="cancel"]').click();
    }
    await shot(manager.page, 'holiday-workspaces-manager-approved-desktop.png');

    await manager.page.locator('[data-section="verify"]').click();
    await manager.page.locator('[data-name="verification-form"]').waitFor();
    check('verification-option-opens-existing-verification-page',
        manager.page.url().endsWith('#EmployeeHolidayDocumentVerification'));
    await manager.page.goBack();
    await manager.page.locator('[data-section="approved"][aria-current="page"]').waitFor();

    await manager.page.setViewportSize({width:390, height:844});
    await manager.page.goto(base + '#EmployeeHolidayManagement/cancellations');
    await manager.page.locator('[data-section="cancellations"][aria-current="page"]').waitFor();
    const overflow = await manager.page.locator('.employee-holiday-management-page').evaluate(element => ({
        scrollWidth:element.scrollWidth,
        clientWidth:element.clientWidth,
    }));
    check('mobile-manager-page-has-no-horizontal-overflow', overflow.scrollWidth <= overflow.clientWidth + 1, overflow);
    check('mobile-manager-options-remain-visible',
        await manager.page.locator('.employee-holiday-management-option').count() === 5);
    await shot(manager.page, 'holiday-workspaces-manager-mobile.png');
    check('manager-no-javascript-errors', manager.errors.length === 0, manager.errors);
    await manager.page.request.put(base + `api/v1/Preferences/${managerId}`, {data:{language:originalLanguage}});
    await manager.context.close();

    const approverOnly = await login(state.temporary.approverOnly.userName, 'EmployeeHolidayManagement');
    await approverOnly.page.locator('.employee-holiday-management-options').waitFor();
    check('approver-only-sees-approval-functions',
        await approverOnly.page.locator('[data-section="approvals"], [data-section="cancellations"], [data-section="approved"], [data-section="verify"]').count() === 4);
    check('approver-only-does-not-gain-managed-booking',
        await approverOnly.page.locator('[data-section="book"]').count() === 0);
    check('approver-only-no-javascript-errors', approverOnly.errors.length === 0, approverOnly.errors);
    await approverOnly.context.close();

    const bookingOnly = await login(state.temporary.bookingOnly.userName, 'EmployeeHolidayManagement');
    await bookingOnly.page.locator('.employee-holiday-management-options').waitFor();
    check('booking-only-opens-native-managed-booking-section',
        await bookingOnly.page.locator('[data-section="book"][aria-current="page"]').count() === 1);
    check('booking-only-does-not-gain-approval-or-verification',
        await bookingOnly.page.locator('[data-section="approvals"], [data-section="cancellations"], [data-section="approved"], [data-section="verify"]').count() === 0);
    const bookingQueue = await bookingOnly.page.request.get(base + 'api/v1/EmployeeManagement/holiday/queue');
    check('booking-only-cannot-read-approval-queue', bookingQueue.status() === 403, bookingQueue.status());
    const employeesLoaded = bookingOnly.page.waitForResponse(response =>
        response.url().endsWith('/EmployeeManagement/holiday/employees') && response.request().method() === 'GET');
    await bookingOnly.page.locator('[data-action="managed"]').click();
    const employeeResponse = await employeesLoaded;
    await bookingOnly.page.waitForTimeout(1000);
    const modalState = {
        count:await bookingOnly.page.locator('.modal').count(),
        employeeField:await bookingOnly.page.locator('.modal [data-name="employeeField"]').count(),
        calculation:await bookingOnly.page.locator('.modal [data-name="daysCalculation"]').count(),
        errors:bookingOnly.errors,
    };
    check('book-option-reuses-original-managed-booking-modal',
        employeeResponse.status() === 200 &&
        modalState.employeeField === 1 && modalState.calculation === 1,
        {status:employeeResponse.status(), ...modalState});
    await bookingOnly.page.locator('.modal [data-name="cancel"]').click();
    check('booking-only-no-javascript-errors', bookingOnly.errors.length === 0, bookingOnly.errors);
    await bookingOnly.context.close();

    fs.writeFileSync('/work/tests/evidence/holiday-workspaces-0.8.15-browser.json', JSON.stringify({
        results,
        assertions:results.length,
        failures:0,
        screenshots,
        queue:{total:queue.list.length, approvals:expectedApprovals, cancellations:expectedCancellations},
    }, null, 2));
    await browser.close();
})().catch(error => {
    console.error(error);
    process.exit(1);
});
