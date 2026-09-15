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
                        <div class="attendance-month-control"><div class="attendance-month-field"><input class="form-control" value="15.09.2026"></div><button class="btn btn-primary btn-sm">Deschide luna</button></div>
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
