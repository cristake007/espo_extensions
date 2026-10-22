import {expect, test} from '../../../AttendanceManagement/tests/browser/node_modules/@playwright/test/index.mjs';
import path from 'node:path';

const repositoryRoot = path.resolve(import.meta.dirname, '..', '..', '..');
const clientRoot = path.join(repositoryRoot, 'TuvtkTheme', 'files', 'client');
const baseUrl = (process.env.ESPO_BASE_URL || '').replace(/\/$/, '');
const username = process.env.ESPO_ADMIN_USERNAME || '';
const password = process.env.ESPO_ADMIN_PASSWORD || '';
const screenshotDir = process.env.TUVTK_SCREENSHOT_DIR || '/tmp';

async function routeCandidateAssets(page) {
    await page.route('**/client/custom/css/tuvtk.css*', route =>
        route.fulfill({
            contentType: 'text/css',
            path: path.join(clientRoot, 'custom', 'css', 'tuvtk.css'),
        })
    );
    await page.route('**/client/custom/modules/tuvtk-theme/src/views/site/navbar.js*', route =>
        route.fulfill({
            contentType: 'text/javascript',
            path: path.join(
                clientRoot,
                'custom',
                'modules',
                'tuvtk-theme',
                'src',
                'views',
                'site',
                'navbar.js'
            ),
        })
    );
}

async function logIn(page) {
    await page.goto(baseUrl, {waitUntil: 'networkidle'});

    if (await page.locator('#login').count()) {
        await page.locator('#field-userName').fill(username);
        await page.locator('#field-password').fill(password);
        await page.locator('#btn-login').click();
    }

    await expect(page.locator('#navbar > .navbar')).toBeVisible({timeout: 20_000});
}

async function geometry(page) {
    return page.evaluate(() => {
        const navbar = document.querySelector('#navbar > .navbar');
        const minimizer = navbar.querySelector('a.minimizer');
        const active = navbar.querySelector('ul.tabs > li.active > a') ||
            navbar.querySelector('ul.tabs > li:not(.tab-divider) > a');
        const navbarRect = navbar.getBoundingClientRect();
        const minimizerRect = minimizer.getBoundingClientRect();
        const navbarStyle = getComputedStyle(navbar);
        const railStyle = getComputedStyle(navbar, '::after');
        const visibleIcon = [...minimizer.children]
            .find(element => getComputedStyle(element).display !== 'none');
        const visibleIconRect = visibleIcon.getBoundingClientRect();
        const visibleIconStyle = getComputedStyle(visibleIcon);
        const railWidth = Number.parseFloat(railStyle.width);
        const railRight = Number.parseFloat(railStyle.right);
        const railTransform = new DOMMatrixReadOnly(railStyle.transform);
        const containingRight = navbarRect.right - Number.parseFloat(navbarStyle.borderRightWidth);
        const activeStyle = getComputedStyle(active);

        return {
            classes: [...document.body.classList],
            navbarWidth: navbarRect.width,
            containerEdge: containingRight,
            minimizerOffsetParentIsNavbar: minimizer.offsetParent === navbar,
            railCenter: containingRight - railRight - railWidth / 2 + railTransform.m41,
            railHeight: Number.parseFloat(railStyle.height),
            railZIndex: Number.parseInt(railStyle.zIndex, 10),
            activeZIndex: activeStyle.zIndex === 'auto' ? 0 : Number.parseInt(activeStyle.zIndex, 10),
            minimizerCenter: minimizerRect.left + minimizerRect.width / 2,
            minimizerCenterY: minimizerRect.top + minimizerRect.height / 2,
            iconCenter: visibleIconRect.left + visibleIconRect.width / 2,
            iconCenterY: visibleIconRect.top + visibleIconRect.height / 2,
            iconDisplay: visibleIconStyle.display,
        };
    });
}

test.describe('authenticated TUVTK navigation', () => {
    test.skip(
        !baseUrl || !username || !password,
        'ESPO_BASE_URL, ESPO_ADMIN_USERNAME and ESPO_ADMIN_PASSWORD are required.'
    );

    test.beforeEach(async ({page}) => {
        await page.setViewportSize({width: 1440, height: 900});
        await routeCandidateAssets(page);
        await logIn(page);
    });

    test('real navbar closes the hamburger overlay with one chevron click', async ({page}) => {
        const body = page.locator('body');
        const minimizer = page.locator('#navbar a.minimizer');
        const hamburger = page.locator('#navbar a.side-menu-button');
        const isMinimized = () => body.evaluate(element => element.classList.contains('minimized'));
        const isSideMenuOpened = () => body.evaluate(
            element => element.classList.contains('side-menu-opened')
        );

        if (await isSideMenuOpened()) {
            await hamburger.click();
        }

        const initialMinimized = await isMinimized();

        if (initialMinimized) {
            await minimizer.click();
        }

        try {
            const states = [];
            const capture = async name => states.push({name, ...await geometry(page)});

            await capture('expanded');
            await minimizer.click();
            await capture('expanded-to-collapsed-by-chevron');

            await hamburger.click();
            await capture('collapsed-to-overlay-by-hamburger');
            expect(await isMinimized()).toBe(true);
            expect(await isSideMenuOpened()).toBe(true);

            await minimizer.click();
            await capture('overlay-closed-by-one-chevron-click');
            expect(await isMinimized()).toBe(true);
            expect(await isSideMenuOpened()).toBe(false);

            await minimizer.click();
            await capture('collapsed-to-expanded-by-chevron');
            expect(await isMinimized()).toBe(false);

            await minimizer.click();
            await hamburger.click();
            await capture('overlay-opened-by-hamburger');
            await hamburger.click();
            await capture('overlay-closed-by-hamburger');
            expect(await isMinimized()).toBe(true);
            expect(await isSideMenuOpened()).toBe(false);

            await page.reload({waitUntil: 'networkidle'});
            await expect(page.locator('#navbar > .navbar')).toBeVisible({timeout: 20_000});
            await capture('collapsed-restored-after-reload');
            expect(await isMinimized()).toBe(true);
            expect(await isSideMenuOpened()).toBe(false);

            for (const state of states) {
                expect(state.minimizerOffsetParentIsNavbar, state.name).toBe(true);
                expect(Math.abs(state.minimizerCenter - state.containerEdge), state.name)
                    .toBeLessThanOrEqual(0.5);
                expect(Math.abs(state.minimizerCenter - state.railCenter), state.name)
                    .toBeLessThanOrEqual(0.5);
                expect(Math.abs(state.iconCenter - state.minimizerCenter), state.name)
                    .toBeLessThanOrEqual(0.5);
                expect(Math.abs(state.iconCenterY - state.minimizerCenterY), state.name)
                    .toBeLessThanOrEqual(0.5);
                expect(state.iconDisplay, state.name).not.toBe('none');
                expect(state.railHeight, state.name).toBeGreaterThanOrEqual(899);
                expect(state.railZIndex, state.name).toBeGreaterThan(state.activeZIndex);
            }

            await page.locator('#navbar ul.tabs > li:not(.tab-divider) > a').first().hover();
            const hoverState = await geometry(page);
            expect(hoverState.railZIndex).toBeGreaterThan(hoverState.activeZIndex);
            await page.screenshot({
                path: path.join(screenshotDir, 'tuvtk-authenticated-sidenav-1.0.19.png'),
                fullPage: true,
            });
        } finally {
            if (await isSideMenuOpened()) {
                await hamburger.click();
            }

            if (await isMinimized() !== initialMinimized) {
                await minimizer.click();
            }
        }
    });

    test('real Administration headings are blue and dashboard is not targeted', async ({page}) => {
        await page.evaluate(() => { window.location.hash = '#Admin'; });
        await expect(page.locator('#main > .admin-content')).toBeVisible();

        for (const viewport of [
            {name: 'desktop', width: 1440, height: 900},
            {name: 'mobile', width: 390, height: 844},
        ]) {
            await page.setViewportSize({width: viewport.width, height: viewport.height});

            const colors = await page.locator(
                '#main > .page-header > h3, ' +
                '#main > .admin-content .admin-tables-container > .admin-content-section > h4'
            ).evaluateAll(elements => elements.map(element => getComputedStyle(element).color));

            expect(colors.length).toBeGreaterThan(1);
            expect(colors.every(color => color === 'rgb(22, 65, 148)')).toBe(true);

            const nonHeadingColors = await page.locator(
                '#main .admin-content-row a, #main .admin-content-row .icon, ' +
                '#main .admin-content-row > td:nth-child(2)'
            ).evaluateAll(elements => elements.map(element => getComputedStyle(element).color));
            expect(nonHeadingColors).not.toContain('rgb(22, 65, 148)');

            await page.screenshot({
                path: path.join(
                    screenshotDir,
                    `tuvtk-authenticated-administration-1.0.19-${viewport.name}.png`
                ),
                fullPage: true,
            });
        }

        await page.evaluate(() => { window.location.hash = '#Home'; });
        await expect(page.locator('#main')).toBeVisible();
        await expect(page.locator('#main > .admin-content')).toHaveCount(0);

        const dashboardHeadingColors = await page.locator('#main .page-header h3')
            .evaluateAll(elements => elements.map(element => getComputedStyle(element).color));
        expect(dashboardHeadingColors).not.toContain('rgb(22, 65, 148)');
    });
});
