import {expect, test} from '../../../AttendanceManagement/tests/browser/node_modules/@playwright/test/index.mjs';
import {readFile} from 'node:fs/promises';
import path from 'node:path';

const repositoryRoot = path.resolve(import.meta.dirname, '..', '..', '..');
const themeCss = path.join(
    repositoryRoot,
    'TuvtkTheme', 'files', 'client', 'custom', 'css', 'tuvtk.css',
);
const holidayCss = path.join(
    repositoryRoot,
    'HolidayManagement', 'files', 'client', 'custom', 'modules',
    'holiday-management', 'css', 'calendar.css',
);
const overviewSource = path.join(
    repositoryRoot,
    'HolidayManagement', 'files', 'client', 'custom', 'modules',
    'holiday-management', 'src', 'views', 'holiday-overview', 'index.js',
);
const adminMenuSource = path.join(
    repositoryRoot,
    'HolidayManagement', 'files', 'client', 'custom', 'modules',
    'holiday-management', 'js', 'admin-menu.js',
);

function tableHeader() {
    return Array.from({length: 31}, (_, index) => {
        const day = index + 1;
        const className = [5, 6, 12, 13, 19, 20, 26, 27].includes(day)
            ? 'holiday-day-heading holiday-non-working-column'
            : `holiday-day-heading${day === 19 ? ' holiday-today-column' : ''}`;

        return `<th class="${className}"><span>${String(day).padStart(2, '0')}</span></th>`;
    }).join('');
}

function employeeRow(name, balance, activeDays = []) {
    const cells = Array.from({length: 31}, (_, index) => {
        const day = index + 1;
        const nonWorking = [5, 6, 12, 13, 19, 20, 26, 27].includes(day);
        const status = activeDays.find(item => item.day === day);
        const classes = [
            'holiday-overview-cell',
            nonWorking ? 'holiday-non-working-column' : '',
            day === 19 ? 'holiday-today-column' : '',
        ].filter(Boolean).join(' ');
        const content = status
            ? `<span class="label label-${status.style}" title="${status.label}">✓</span>`
            : '';

        return `<td class="${classes}">${content}</td>`;
    }).join('');

    return `<tr><th class="holiday-sticky-employee"><div class="holiday-employee-name">${name}</div><div class="text-muted small">Sold: ${balance} · în așteptare: 0</div></th>${cells}</tr>`;
}

function fixture(contentWidth) {
    return `
        <main class="test-stage" style="width:${contentWidth}px;padding:16px">
            <div class="header page-header holiday-overview-header">
                <div><h3>Situație concedii</h3><p class="text-muted">Situație lunară angajat × zi.</p></div>
                <a class="btn btn-default holiday-settings-link">Setări concedii</a>
            </div>
            <div class="panel panel-default holiday-overview-panel">
                <div class="panel-heading holiday-overview-toolbar">
                    <div class="holiday-overview-month-control"><span>Luna</span>
                        <select class="form-control holiday-overview-month-select"><option>octombrie 2026</option></select>
                    </div>
                    <div class="holiday-overview-legend"><span class="label label-warning">În așteptare</span><span class="label label-success">Aprobat</span><span class="label label-info">Anulare solicitată</span></div>
                </div>
                <div class="panel-body holiday-overview-summary">
                    <section class="holiday-summary-card holiday-summary-primary"><span class="fas fa-users"></span><strong>18</strong><div>Angajați</div></section>
                    <section class="holiday-summary-card holiday-summary-success"><span class="fas fa-suitcase"></span><strong>3</strong><div>Absenți astăzi</div></section>
                    <section class="holiday-summary-card holiday-summary-warning"><span class="fas fa-clock"></span><strong>4</strong><div>Cereri în așteptare</div></section>
                    <section class="holiday-summary-card holiday-summary-danger"><span class="fas fa-user-slash"></span><strong>1</strong><div>Profiluri lipsă</div></section>
                </div>
                <div class="table-responsive holiday-overview-table-wrap">
                    <table class="table table-bordered table-condensed holiday-overview-table">
                        <thead><tr><th class="holiday-sticky-employee">Angajat</th>${tableHeader()}</tr></thead>
                        <tbody>
                            ${employeeRow('Ana Popescu', 18, [{day: 7, style: 'success', label: 'Aprobat'}])}
                            ${employeeRow('Bogdan Ionescu', 12, [{day: 14, style: 'warning', label: 'În așteptare'}])}
                            ${employeeRow('Cristina Dumitru', 21, [{day: 21, style: 'info', label: 'Anulare solicitată'}])}
                        </tbody>
                    </table>
                </div>
            </div>
        </main>`;
}

async function openFixture(page, width, contentWidth) {
    await page.setViewportSize({width, height: 900});
    await page.setContent(fixture(contentWidth));
    await page.addStyleTag({path: themeCss});
    await page.addStyleTag({path: holidayCss});
}

test('month control is strictly month-based and uses Espo form styling', async () => {
    const source = await readFile(overviewSource, 'utf8');

    expect(source).toContain('data-overview-month-select');
    expect(source).toContain('Intl.DateTimeFormat');
    expect(source).not.toContain('type="month"');
    expect(source).not.toContain("'views/fields/date'");
});

test('overview cards and transposed table are stable on desktop', async ({page}) => {
    await openFixture(page, 1440, 1180);

    const cards = page.locator('.holiday-summary-card');
    await expect(cards).toHaveCount(4);
    const boxes = await cards.evaluateAll(elements => elements.map(element => {
        const rect = element.getBoundingClientRect();
        return {left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom};
    }));

    expect(new Set(boxes.map(box => box.top)).size).toBe(1);
    expect(boxes.every((box, index) => index === 0 || box.left > boxes[index - 1].right)).toBe(true);
    await expect(page.locator('.holiday-overview-table tbody tr')).toHaveCount(3);
    await expect(page.locator('.holiday-overview-table thead .holiday-day-heading')).toHaveCount(31);
    expect(await page.locator('.holiday-overview-table-wrap').evaluate(element =>
        element.scrollWidth > element.clientWidth
    )).toBe(true);

    await page.screenshot({path: '/tmp/holiday-overview-desktop.png', fullPage: true});
});

test('cards stack cleanly and controls stay inside the mobile viewport', async ({page}) => {
    await openFixture(page, 390, 390);

    const stage = page.locator('.test-stage');
    expect(await stage.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true);
    const boxes = await page.locator('.holiday-summary-card').evaluateAll(elements =>
        elements.map(element => element.getBoundingClientRect().top)
    );
    expect(new Set(boxes).size).toBe(4);
    await expect(page.locator('.holiday-overview-month-select')).toBeVisible();

    await page.screenshot({path: '/tmp/holiday-overview-mobile.png', fullPage: true});
});

test('Holiday role keeps the Administration entry when no native entry is available', async ({page}) => {
    await page.setContent(`
        <nav id="navbar"><ul>
            <li><a data-name="holidayAdministration" href="#Admin">Administration</a></li>
        </ul></nav>
    `);
    await page.addScriptTag({path: adminMenuSource});

    const fallback = page.locator('a[data-name="holidayAdministration"]');

    await expect(fallback).toBeVisible();
    await expect(fallback).toHaveAttribute('href', '#Admin');
    await expect(fallback).toHaveAttribute('aria-hidden', 'false');
});

test('native Administration entry wins when Attendance or administrator access already exposes it', async ({page}) => {
    await page.setContent(`
        <nav id="navbar"><ul>
            <li><a data-name="admin" href="#Admin">Administration</a></li>
            <li><a data-name="holidayAdministration" href="#Admin">Administration</a></li>
        </ul></nav>
    `);
    await page.addScriptTag({path: adminMenuSource});

    await expect(page.locator('a[data-name="admin"]')).toBeVisible();
    await expect(page.locator('a[data-name="holidayAdministration"]')).toBeHidden();
    await expect(page.locator('a[data-name="holidayAdministration"]'))
        .toHaveAttribute('aria-hidden', 'true');
});
