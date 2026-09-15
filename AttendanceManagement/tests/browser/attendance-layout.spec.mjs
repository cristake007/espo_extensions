import {expect, test} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import path from 'node:path';

const repositoryRoot = path.resolve(import.meta.dirname, '..', '..', '..');
const themeCss = path.join(repositoryRoot, 'TuvtkTheme', 'files', 'client', 'custom', 'css', 'tuvtk.css');
const attendanceCss = path.join(
    repositoryRoot,
    'AttendanceManagement', 'files', 'client', 'custom', 'modules',
    'attendance-management', 'css', 'attendance.css'
);
const managerMenuScript = path.join(
    repositoryRoot,
    'AttendanceManagement', 'files', 'client', 'custom', 'modules',
    'attendance-management', 'js', 'manager-menu.js'
);

const viewportCases = [
    {name: 'desktop', width: 1440, contentWidth: 1180},
    {name: 'compact-desktop', width: 1024, contentWidth: 784},
    {name: 'side-nav-tablet', width: 768, contentWidth: 528},
    {name: 'mobile', width: 390, contentWidth: 390},
];

test('every attendance CSS variable is supplied by the active TUVTK theme', async () => {
    const [attendanceSource, themeSource] = await Promise.all([
        readFile(attendanceCss, 'utf8'),
        readFile(themeCss, 'utf8'),
    ]);
    const usedTokens = [...attendanceSource.matchAll(/var\((--[\w-]+)/g)].map(match => match[1]);
    const themeTokens = new Set([...themeSource.matchAll(/(--[\w-]+)\s*:/g)].map(match => match[1]));
    const missingTokens = [...new Set(usedTokens)].filter(token => !themeTokens.has(token));

    expect(missingTokens).toEqual([]);
});

test('manager menu access is rechecked when the authenticated user changes', async ({page}) => {
    await page.setContent('<nav id="navbar"><li data-name="AttendanceOverview"></li></nav>');
    await page.evaluate(() => {
        window.attendanceManagerAccess = true;
        window.Espo = {
            Ajax: {
                getRequest: async () => ({isManager: window.attendanceManagerAccess}),
            },
        };
    });
    await page.addScriptTag({path: managerMenuScript});

    await expect(page.locator('body')).toHaveClass(/attendance-management-manager/);
    await page.evaluate(() => {
        window.attendanceManagerAccess = false;
        window.dispatchEvent(new HashChangeEvent('hashchange'));
    });
    await expect(page.locator('body')).not.toHaveClass(/attendance-management-manager/);

    await page.evaluate(() => {
        window.attendanceManagerAccess = true;
        window.dispatchEvent(new HashChangeEvent('hashchange'));
    });
    await expect(page.locator('body')).toHaveClass(/attendance-management-manager/);
    await page.locator('[data-name="AttendanceOverview"]').evaluate(element => element.remove());
    await expect(page.locator('body')).not.toHaveClass(/attendance-management-manager/);
});

function fixture(contentWidth) {
    return `
        <main class="test-stage" style="width:${contentWidth}px; padding:16px;">
            <div class="panel panel-default theme-reference-panel" style="position:absolute;left:-9999px;"></div>
            <div class="header page-header attendance-page-header">
                <div>
                    <h3><span class="fas fa-clipboard-check"></span> Condica de prezenta</h3>
                    <p class="text-muted attendance-page-intro">
                        Marcheaza unde lucrezi astazi sau verifica si completeaza o zi lucratoare anterioara.
                    </p>
                </div>
            </div>
            <div class="attendance-page">
                <div class="panel panel-default attendance-today-panel">
                    <div class="panel-heading attendance-today-heading">
                        <div class="attendance-section-icon attendance-section-icon-today">✓</div>
                        <div><strong>Prezenta de astazi</strong><div class="attendance-today-date text-muted">15.09.2026</div></div>
                    </div>
                    <div class="panel-body attendance-today-body">
                        <div class="attendance-today-summary">
                            <span class="text-muted">Starea curenta</span>
                            <div class="attendance-current-status"><span class="label label-default attendance-status-large">Nemarcata</span></div>
                            <p class="attendance-today-guidance text-muted">Alege o optiune. O poti schimba ulterior daca ai selectat gresit.</p>
                        </div>
                        <div class="attendance-state-actions">
                            <button class="btn btn-success btn-lg">
                                <span class="attendance-action-icon fas fa-building"></span>
                                <span class="attendance-action-copy"><strong>La serviciu</strong><small>Lucrez la locul normal de munca</small></span>
                            </button>
                            <button class="btn btn-warning btn-lg">
                                <span class="attendance-action-icon fas fa-car"></span>
                                <span class="attendance-action-copy"><strong>In deplasare</strong><small>Lucrez in afara sediului, in deplasare</small></span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="panel panel-default attendance-month-panel">
                    <div class="panel-heading attendance-month-heading">
                        <div class="attendance-month-title"><div class="attendance-section-icon">□</div><div><strong>Condica lunara</strong><div class="text-muted small">Verifica zilele lucratoare si completeaza orice zi marcata ca nemarcata.</div></div></div>
                    </div>
                    <div class="panel-body attendance-month-browser">
                        <label class="attendance-month-selector"><span>Luna</span><select class="form-control" data-month-select><option>septembrie 2026</option></select></label>
                        <div class="attendance-month-lock-message text-muted hidden"><span class="fas fa-lock"></span><span>Aceasta luna poate fi doar consultata.</span></div>
                    </div>
                    <div class="panel-body attendance-month-summary">
                        <div class="attendance-progress-item attendance-progress-success"><span class="fas fa-check-circle"></span><strong>8</strong><span>Completate</span></div>
                        <div class="attendance-progress-item attendance-progress-danger"><span class="fas fa-exclamation-circle"></span><strong>2</strong><span>Nemarcate</span></div>
                        <div class="attendance-progress-item attendance-progress-default"><span class="fas fa-clock"></span><strong>12</strong><span>Urmeaza</span></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover attendance-my-table">
                            <thead><tr><th>Data</th><th>Stare</th><th>Alege prezenta</th></tr></thead>
                            <tbody><tr><td class="attendance-day-date"><strong>15.09.2026</strong><span class="text-muted">marti</span></td><td class="attendance-day-status"><span class="label label-default">Nemarcata</span></td><td><div class="attendance-day-actions"><button class="btn btn-sm btn-success">La serviciu</button><button class="btn btn-sm btn-warning">In deplasare</button></div></td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>`;
}

function adminFixture(contentWidth) {
    const scheduleRow = name => `
        <div class="attendance-schedule-row" data-user-id="1">
            <div class="attendance-schedule-employee"><span class="fas fa-user-clock"></span><strong>${name}</strong></div>
            <label class="attendance-schedule-control"><span>Intrare</span><select class="form-control input-sm"><option>09:00</option></select></label>
            <label class="attendance-schedule-control"><span>Iesire</span><select class="form-control input-sm"><option>17:00</option></select></label>
            <div class="attendance-schedule-actions"><button class="btn btn-default btn-sm">Salveaza programul</button></div>
        </div>`;

    return `
        <main class="test-stage attendance-management-settings-page" style="width:${contentWidth}px;padding:16px;">
            <div class="row">
                <div class="cell col-sm-6" data-name="attendanceManagementScheduleEditor">
                    <div class="attendance-schedule-editor">
                        <div class="attendance-schedule-editor-intro">
                            <span class="fas fa-business-time attendance-schedule-editor-icon"></span>
                            <div><strong>Programe angajati</strong><div class="text-muted small">Alege ora normala de intrare si iesire pentru fiecare angajat.</div></div>
                        </div>
                        <div class="attendance-schedule-rows">
                            ${scheduleRow('Burete D')}
                            ${scheduleRow('Un nume de angajat suficient de lung pentru verificarea incadrarii')}
                        </div>
                    </div>
                </div>
            </div>
        </main>`;
}

function overviewFixture(contentWidth) {
    const detail = (name, value = '') => `<div class="attendance-summary-detail"><span>${name}</span>${value ? `<strong>${value}</strong>` : ''}</div>`;
    const card = (style, title, value, description, details) => `
        <section class="attendance-summary-card attendance-summary-card-${style}">
            <div class="attendance-summary-card-heading"><span class="fas fa-chart-pie"></span><strong>${title}</strong></div>
            <div class="attendance-summary-card-value">${value}</div>
            <div class="attendance-summary-card-description text-muted">${description}</div>
            <div class="attendance-summary-detail-list">${details}</div>
        </section>`;

    return `
        <main class="test-stage" style="width:${contentWidth}px;padding:16px;">
            <div class="header page-header attendance-page-header"><h3>Centralizator prezenta</h3><p class="text-muted attendance-page-intro">Selecteaza luna pentru a verifica situatia.</p></div>
            <div class="attendance-overview-page"><div class="panel panel-default">
                <div class="panel-heading attendance-overview-toolbar">
                    <label class="attendance-overview-month-selector"><span>Luna</span><select class="form-control" data-overview-month-select><option>septembrie 2026</option><option>august 2026</option><option>iulie 2026</option></select></label>
                    <div class="attendance-overview-actions"><button class="btn btn-warning">Trimite notificari</button><button class="btn btn-primary">Descarca XLSX</button><button class="btn btn-default">Descarca PDF</button></div>
                </div>
                <div class="panel-body attendance-overview-dashboard"><div class="attendance-overview-summary">
                    ${card('success', 'Grad de completare', '88%', '22 din 25 inregistrari sunt completate.', detail('Burete D', '10/11') + detail('Un nume foarte lung de angajat', '12/14'))}
                    ${card('danger', 'Prezente nemarcate', '3', '3 inregistrari lipsa pentru 2 angajati.', detail('Burete D', '1') + detail('Un nume foarte lung de angajat', '2'))}
                    ${card('warning', 'Acoperire programe', '5/6', 'Sunt configurate 5 din 6 programe.', detail('Angajat fara program configurat'))}
                    ${card('warning', 'Starea registrului', 'Necesita atentie', 'Registrul XLSX nu poate fi descarcat inca.', detail('Lipsesc 3 inregistrari de prezenta.') + detail('Lipseste programul pentru 1 angajat.'))}
                </div></div>
                <div class="panel-body attendance-overview-matrix-heading"><strong>Situatie zilnica</strong><span class="text-muted small">Fiecare rand este o zi lucratoare.</span></div>
                <div class="table-responsive attendance-overview-table-wrap"><table class="table table-bordered attendance-overview-table"><thead><tr><th class="attendance-sticky-date">Data</th><th>Burete D</th><th>Popa Dorin</th></tr></thead><tbody><tr><th class="attendance-sticky-date">15.09.2026</th><td class="attendance-cell-AtWork">La serviciu</td><td class="attendance-cell-missing">Nemarcata</td></tr></tbody></table></div>
            </div></div>
        </main>`;
}

for (const viewport of viewportCases) {
    test(`attendance page has no outer or button overflow at ${viewport.name}`, async ({page}) => {
        await page.setViewportSize({width: viewport.width, height: 900});
        await page.setContent(fixture(viewport.contentWidth));
        await page.addStyleTag({path: themeCss});
        await page.addStyleTag({path: attendanceCss});

        const overflow = await page.evaluate(() => {
            const stage = document.querySelector('.test-stage');
            const buttons = [...document.querySelectorAll('.attendance-state-actions .btn')];

            return {
                stage: stage.scrollWidth - stage.clientWidth,
                buttons: buttons.map(button => button.scrollWidth - button.clientWidth),
            };
        });

        expect(overflow.stage).toBeLessThanOrEqual(1);
        expect(Math.max(...overflow.buttons)).toBeLessThanOrEqual(1);
        await page.screenshot({
            path: `/tmp/attendance-${viewport.name}.png`,
            fullPage: true,
        });
    });
}

for (const viewport of viewportCases) {
    test(`manager overview cards are actionable and contained at ${viewport.name}`, async ({page}) => {
        await page.setViewportSize({width: viewport.width, height: 1100});
        await page.setContent(overviewFixture(viewport.contentWidth));
        await page.addStyleTag({path: themeCss});
        await page.addStyleTag({path: attendanceCss});

        const measurements = await page.evaluate(() => {
            const stage = document.querySelector('.test-stage');

            return {
                overflow: stage.scrollWidth - stage.clientWidth,
                monthOptions: document.querySelectorAll('[data-overview-month-select] option').length,
                cards: document.querySelectorAll('.attendance-summary-card').length,
                detailRows: document.querySelectorAll('.attendance-summary-detail').length,
                actionButtons: document.querySelectorAll('.attendance-overview-actions .btn').length,
            };
        });

        expect(measurements.overflow).toBeLessThanOrEqual(1);
        expect(measurements.monthOptions).toBe(3);
        expect(measurements.cards).toBe(4);
        expect(measurements.detailRows).toBeGreaterThan(3);
        expect(measurements.actionButtons).toBe(3);
        await page.screenshot({path: `/tmp/attendance-overview-${viewport.name}.png`, fullPage: true});
    });
}

for (const viewport of viewportCases.filter(item => item.width > 991)) {
    test(`current status and attendance buttons share one aligned row at ${viewport.name}`, async ({page}) => {
        await page.setViewportSize({width: viewport.width, height: 900});
        await page.setContent(fixture(viewport.contentWidth));
        await page.addStyleTag({path: themeCss});
        await page.addStyleTag({path: attendanceCss});

        const positions = await page.evaluate(() => {
            const summary = document.querySelector('.attendance-today-summary').getBoundingClientRect();
            const actions = document.querySelector('.attendance-state-actions').getBoundingClientRect();
            const buttons = [...document.querySelectorAll('.attendance-state-actions .btn')]
                .map(button => button.getBoundingClientRect());

            return {
                summaryTop: summary.top,
                actionsTop: actions.top,
                buttonTops: buttons.map(button => button.top),
                buttonHeights: buttons.map(button => button.height),
            };
        });

        expect(Math.abs(positions.summaryTop - positions.actionsTop)).toBeLessThanOrEqual(1);
        expect(Math.max(...positions.buttonTops) - Math.min(...positions.buttonTops)).toBeLessThanOrEqual(1);
        expect(Math.max(...positions.buttonHeights) - Math.min(...positions.buttonHeights)).toBeLessThanOrEqual(1);
    });
}

test('attendance components use the active theme radius and shadow tokens', async ({page}) => {
    await page.setContent(fixture(1180));
    await page.addStyleTag({path: themeCss});
    await page.addStyleTag({path: attendanceCss});

    const values = await page.evaluate(() => {
        const panel = getComputedStyle(document.querySelector('.attendance-today-panel'));
        const reference = getComputedStyle(document.querySelector('.theme-reference-panel'));

        return {
            expectedRadius: reference.borderRadius,
            actualRadius: panel.borderRadius,
            expectedShadow: reference.boxShadow,
            actualShadow: panel.boxShadow,
        };
    });

    expect(values.actualRadius).toBe(values.expectedRadius);
    expect(values.actualShadow).toBe(values.expectedShadow);
});

test('locked-month notice fits beside the selector and stacks on narrow screens', async ({page}) => {
    for (const viewport of [
        {width: 1440, contentWidth: 1180},
        {width: 390, contentWidth: 390},
    ]) {
        await page.setViewportSize({width: viewport.width, height: 900});
        await page.setContent(fixture(viewport.contentWidth));
        await page.addStyleTag({path: themeCss});
        await page.addStyleTag({path: attendanceCss});
        await page.locator('.attendance-month-lock-message').evaluate(element => {
            element.classList.remove('hidden');
        });

        const overflow = await page.locator('.attendance-month-browser').evaluate(element =>
            element.scrollWidth - element.clientWidth
        );

        expect(overflow).toBeLessThanOrEqual(1);
    }
});

for (const viewport of viewportCases) {
    test(`schedule administration is full-width and contained at ${viewport.name}`, async ({page}) => {
        await page.setViewportSize({width: viewport.width, height: 900});
        await page.setContent(adminFixture(viewport.contentWidth));
        await page.addStyleTag({path: themeCss});
        await page.addStyleTag({path: attendanceCss});

        const measurements = await page.evaluate(() => {
            const stage = document.querySelector('.test-stage');
            const cell = document.querySelector('[data-name="attendanceManagementScheduleEditor"]');
            const row = cell.parentElement;

            return {
                overflow: stage.scrollWidth - stage.clientWidth,
                cellWidth: cell.getBoundingClientRect().width,
                rowWidth: row.getBoundingClientRect().width,
                nativeTimeInputs: document.querySelectorAll('input[type="time"]').length,
                selects: document.querySelectorAll('.attendance-schedule-control select').length,
            };
        });

        expect(measurements.overflow).toBeLessThanOrEqual(1);
        expect(Math.abs(measurements.cellWidth - measurements.rowWidth)).toBeLessThanOrEqual(1);
        expect(measurements.nativeTimeInputs).toBe(0);
        expect(measurements.selects).toBe(4);
        await page.screenshot({path: `/tmp/attendance-admin-${viewport.name}.png`, fullPage: true});
    });
}
