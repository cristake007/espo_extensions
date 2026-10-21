const {chromium} = require('/usr/src/app/node_modules/playwright-core');
const fs = require('fs');

const state = JSON.parse(fs.readFileSync('/feasibility/employee-holiday-ledger-0.8.14-browser-state.json'));
const baseUrl = 'http://employee-feasibility-20261020-app-1';
const results = [];

function check(name, passed, observed = null) {
    results.push({test: name, status: passed ? 'PASS' : 'FAIL', observed});
    console.log(JSON.stringify(results.at(-1)));
}

async function login(page, userName) {
    await page.goto(`${baseUrl}/#EmployeeHolidayLedger`);
    await page.locator('input[name="username"]').fill(userName);
    await page.locator('input[name="password"]').fill(state.password);
    await page.locator('#btn-login').click();
    await page.locator('#login').waitFor({state: 'hidden', timeout: 30000});
    await page.goto(`${baseUrl}/#EmployeeHolidayLedger`);
    await page.locator('.employee-holiday-ledger-page').waitFor({timeout: 30000});
    await page.locator('.employee-holiday-ledger-list table').waitFor({timeout: 30000});
}

async function hasViewportOverflow(page) {
    return page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
}

(async () => {
    const browser = await chromium.launch({headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});

    const managerContext = await browser.newContext({viewport: {width: 1440, height: 1000}});
    const managerPage = await managerContext.newPage();
    const managerErrors = [];
    managerPage.on('pageerror', error => managerErrors.push(error.message));
    await login(managerPage, state.manager);

    const heroText = await managerPage.locator('.employee-holiday-ledger-hero').innerText();
    check('manager sees the designed Holiday History workspace',
        /Holiday History/i.test(heroText) && /Balance Ledger/i.test(heroText), heroText);

    const selector = managerPage.locator('[data-role="employee-selector"] input[data-name="assignedUserName"]');
    check('manager has the visible native User selector in the hero',
        await selector.count() === 1 && await selector.isVisible());
    check('manager has obvious All Employees and My History controls',
        await managerPage.locator('[data-action="ledger-show-all"]:visible').count() === 1 &&
        await managerPage.locator('[data-action="ledger-show-own"]:visible').count() === 1);

    const headings = (await managerPage.locator('.employee-holiday-ledger-list thead').innerText())
        .replace(/\s+/g, ' ').trim();
    for (const label of [
        /Operation Date|Data operațiunii/, /Operation|Operațiune/, /Employee|Angajat/,
        /Balance Before|Sold anterior/, /Change|Modificare/, /Balance After|Sold ulterior/,
        /Performed By|Efectuat de/,
    ]) check(`native list heading ${label}`, new RegExp(label.source, 'i').test(headings), headings);

    const bodyText = await managerPage.locator('.employee-holiday-ledger-list tbody').innerText();
    check('translated operation labels render in the designed history',
        /Holiday Booked|Annual Grant/.test(bodyText),
        bodyText.slice(0, 1200));
    check('signed positive negative and zero changes remain visible',
        bodyText.includes('+21') && bodyText.includes('−3') && /(^|\s)0(\s|$)/m.test(bodyText),
        bodyText.slice(0, 1200));
    check('history rows visually distinguish additions deductions and neutral operations',
        await managerPage.locator('.employee-holiday-ledger-row-positive').count() > 0 &&
        await managerPage.locator('.employee-holiday-ledger-row-negative').count() > 0 &&
        await managerPage.locator('.employee-holiday-ledger-row-neutral').count() > 0);
    check('manager desktop has no viewport overflow', !(await hasViewportOverflow(managerPage)));
    await managerPage.screenshot({
        path: '/feasibility/employee-holiday-ledger-0.8.14-manager-desktop-after.png', fullPage: true,
    });

    await selector.fill('Ledger Owner');
    const suggestion = managerPage.locator('.autocomplete-suggestions .autocomplete-suggestion').first();
    await suggestion.waitFor({state: 'visible', timeout: 10000});
    await suggestion.click();
    await managerPage.waitForFunction(() => {
        const text = document.querySelector('.employee-holiday-ledger-list tbody')?.innerText || '';
        return text.includes('Ledger Owner') && !text.includes('Ledger Colleague');
    }, null, {timeout: 15000});
    const filteredText = await managerPage.locator('.employee-holiday-ledger-list tbody').innerText();
    check('visible selector applies the native assignedUser filter',
        filteredText.includes('Ledger Owner') && !filteredText.includes('Ledger Colleague'),
        filteredText.slice(0, 1000));
    await managerPage.screenshot({
        path: '/feasibility/employee-holiday-ledger-0.8.14-manager-filtered-after.png', fullPage: true,
    });

    await managerPage.locator('[data-action="ledger-show-all"]').click();
    await managerPage.waitForFunction(() => {
        const text = document.querySelector('.employee-holiday-ledger-list tbody')?.innerText || '';
        return text.includes('Ledger Owner') && text.includes('Ledger Colleague');
    }, null, {timeout: 15000});
    check('All Employees clears the native employee filter',
        (await managerPage.locator('.employee-holiday-ledger-list tbody').innerText()).includes('Ledger Colleague'));

    const navLink = managerPage.locator('a[href="#EmployeeHolidayLedger"]:visible').first();
    check('configured native navigation renders the Holiday Ledger tab', await navLink.count() === 1);
    check('native iconClass renders fa-history in navigation',
        await navLink.locator('.fa-history').count() === 1,
        await navLink.evaluate(element => element.outerHTML));
    await managerPage.screenshot({
        path: '/feasibility/employee-holiday-ledger-0.8.14-sidebar-icon-after.png', fullPage: true,
    });

    await managerPage.goto(`${baseUrl}/#EmployeeHolidayLedger/view/${state.ownerLedgerId}`);
    await managerPage.locator('.record .detail').waitFor({timeout: 30000});
    const detailText = await managerPage.locator('.record .detail').innerText();
    check('native detail navigation remains available and read-only',
        /Balance Before|Sold anterior/.test(detailText) && /Performed By|Efectuat de/.test(detailText) &&
        await managerPage.locator('[data-action="edit"], [data-action="delete"]').count() === 0,
        detailText.slice(0, 1200));
    check('manager page has no client errors', managerErrors.length === 0, managerErrors);
    await managerContext.close();

    const employeeDesktopContext = await browser.newContext({viewport: {width: 1440, height: 1000}});
    const employeeDesktopPage = await employeeDesktopContext.newPage();
    const employeeDesktopErrors = [];
    employeeDesktopPage.on('pageerror', error => employeeDesktopErrors.push(error.message));
    await login(employeeDesktopPage, state.owner);
    const employeeText = await employeeDesktopPage.locator('.employee-holiday-ledger-page').innerText();
    const employeeRows = await employeeDesktopPage.locator('.employee-holiday-ledger-list tbody tr').count();
    check('employee desktop is ownership restricted and needs no technical filter',
        employeeRows === 3 && !employeeText.includes('Ledger Colleague') &&
        await employeeDesktopPage.locator('[data-role="employee-filter"]').count() === 0,
        {employeeRows, employeeText: employeeText.slice(0, 1000)});
    check('Romanian Holiday History copy renders for employee preference',
        /Istoric concedii/i.test(employeeText) && /Registrul soldului/i.test(employeeText));
    check('employee desktop has no viewport overflow or client errors',
        !(await hasViewportOverflow(employeeDesktopPage)) && employeeDesktopErrors.length === 0,
        employeeDesktopErrors);
    await employeeDesktopPage.screenshot({
        path: '/feasibility/employee-holiday-ledger-0.8.14-employee-desktop-after.png', fullPage: true,
    });
    await employeeDesktopContext.close();

    const mobileContext = await browser.newContext({viewport: {width: 390, height: 844}});
    const mobilePage = await mobileContext.newPage();
    const mobileErrors = [];
    mobilePage.on('pageerror', error => mobileErrors.push(error.message));
    await login(mobilePage, state.owner);
    const ownRows = await mobilePage.locator('.employee-holiday-ledger-list tbody tr').count();
    const ownText = await mobilePage.locator('.employee-holiday-ledger-page').innerText();
    check('employee mobile remains ownership restricted',
        ownRows === 3 && !ownText.includes('Ledger Colleague') && ownText.includes('−3'),
        {ownRows, ownText: ownText.slice(0, 900)});
    check('employee mobile uses the responsive designed workspace',
        await mobilePage.locator('.employee-holiday-ledger-hero:visible').count() === 1 &&
        await mobilePage.locator('.employee-holiday-ledger-panel:visible').count() === 1 &&
        !(await hasViewportOverflow(mobilePage)));
    check('mobile page has no client errors', mobileErrors.length === 0, mobileErrors);
    await mobilePage.screenshot({
        path: '/feasibility/employee-holiday-ledger-0.8.14-employee-mobile-after.png', fullPage: true,
    });
    await mobileContext.close();

    await browser.close();
    const failures = results.filter(result => result.status === 'FAIL').length;
    fs.writeFileSync('/feasibility/employee-holiday-ledger-0.8.14-browser-results.json', JSON.stringify({
        status: failures ? 'FAIL' : 'PASS', assertions: results.length, failures, results,
    }, null, 2));
    process.exit(failures ? 1 : 0);
})().catch(error => {
    console.error(error);
    process.exit(1);
});
