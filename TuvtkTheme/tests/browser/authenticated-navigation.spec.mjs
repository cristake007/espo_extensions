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
        const navbarHeader = navbar.querySelector('.navbar-header');
        const sideMenuButton = navbar.querySelector('a.side-menu-button');
        const navbarRect = navbar.getBoundingClientRect();
        const minimizerRect = minimizer.getBoundingClientRect();
        const navbarStyle = getComputedStyle(navbar);
        const nativeAfterStyle = getComputedStyle(navbar, '::after');
        const navbarHeaderStyle = getComputedStyle(navbarHeader);
        const sideMenuButtonStyle = getComputedStyle(sideMenuButton);
        const visibleIcon = [...minimizer.children]
            .find(element => getComputedStyle(element).display !== 'none');
        const visibleIconRect = visibleIcon.getBoundingClientRect();
        const visibleIconStyle = getComputedStyle(visibleIcon);
        const railWidth = Number.parseFloat(navbarStyle.borderRightWidth);
        const containingRight = navbarRect.right - railWidth;

        return {
            classes: [...document.body.classList],
            devicePixelRatio: window.devicePixelRatio,
            navbarWidth: navbarRect.width,
            containerEdge: containingRight,
            minimizerOffsetParentIsNavbar: minimizer.offsetParent === navbar,
            navbarBorderRightColor: navbarStyle.borderRightColor,
            railWidth,
            railCenter: navbarRect.right - railWidth / 2,
            railHeight: navbarRect.height,
            nativeAfterBackground: nativeAfterStyle.backgroundColor,
            nativeAfterPosition: nativeAfterStyle.position,
            nativeAfterTransform: nativeAfterStyle.transform,
            navbarHeaderBorders: [
                navbarHeaderStyle.borderTopWidth,
                navbarHeaderStyle.borderRightWidth,
                navbarHeaderStyle.borderBottomWidth,
                navbarHeaderStyle.borderLeftWidth,
            ],
            sideMenuButtonBorders: [
                sideMenuButtonStyle.borderTopWidth,
                sideMenuButtonStyle.borderRightWidth,
                sideMenuButtonStyle.borderBottomWidth,
                sideMenuButtonStyle.borderLeftWidth,
            ],
            minimizerCenter: minimizerRect.left + minimizerRect.width / 2,
            minimizerCenterY: minimizerRect.top + minimizerRect.height / 2,
            iconCenter: visibleIconRect.left + visibleIconRect.width / 2,
            iconCenterY: visibleIconRect.top + visibleIconRect.height / 2,
            iconDisplay: visibleIconStyle.display,
        };
    });
}

test.describe('authenticated TUVTK navigation', () => {
    test.use({deviceScaleFactor: 2});

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
            const clearMinimizerInteraction = async () => {
                await page.mouse.move(1000, 500);
                await page.evaluate(() => document.activeElement?.blur());
                await page.waitForTimeout(200);
            };

            await capture('expanded');
            const expanded = states.at(-1);
            expect(expanded.navbarBorderRightColor).toBe('rgb(200, 211, 225)');

            await minimizer.hover();
            const expandedHoverStart = await geometry(page);
            expect(expandedHoverStart.railWidth).toBe(1);
            expect(expandedHoverStart.nativeAfterBackground).toBe('rgba(0, 0, 0, 0)');
            await page.waitForTimeout(200);
            const expandedHoverEnd = await geometry(page);
            expect(expandedHoverEnd.railWidth).toBe(1);
            expect(expandedHoverEnd.navbarBorderRightColor).toBe('rgb(22, 65, 148)');

            await clearMinimizerInteraction();
            await page.keyboard.press('Tab');
            await minimizer.focus();
            expect(await minimizer.evaluate(element => element.matches(':focus-visible'))).toBe(true);
            await page.waitForTimeout(200);
            const expandedFocus = await geometry(page);
            expect(expandedFocus.railWidth).toBe(1);
            expect(expandedFocus.navbarBorderRightColor).toBe('rgb(22, 65, 148)');
            await clearMinimizerInteraction();

            await minimizer.click();
            await capture('collapsed-transition-start');
            expect(states.at(-1).railWidth).toBe(1);
            expect(states.at(-1).nativeAfterBackground).toBe('rgba(0, 0, 0, 0)');
            await clearMinimizerInteraction();
            await capture('collapsed-transition-complete');
            expect(states.at(-1).navbarBorderRightColor).toBe('rgb(200, 211, 225)');

            await minimizer.hover();
            const collapsedHoverStart = await geometry(page);
            expect(collapsedHoverStart.railWidth).toBe(1);
            await page.waitForTimeout(200);
            const collapsedHoverEnd = await geometry(page);
            expect(collapsedHoverEnd.railWidth).toBe(1);
            expect(collapsedHoverEnd.navbarBorderRightColor).toBe('rgb(22, 65, 148)');
            await clearMinimizerInteraction();

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
                expect(state.devicePixelRatio, state.name).toBe(2);
                expect(state.railWidth, state.name).toBe(1);
                expect(state.railHeight, state.name).toBeGreaterThanOrEqual(899);
                expect(state.nativeAfterBackground, state.name).toBe('rgba(0, 0, 0, 0)');
                expect(state.nativeAfterPosition, state.name).not.toBe('absolute');
                expect(state.nativeAfterTransform, state.name).toBe('none');
                expect(state.navbarHeaderBorders, state.name).toEqual(Array(4).fill('0px'));
                expect(state.sideMenuButtonBorders, state.name).toEqual(Array(4).fill('0px'));
            }

            await page.locator('#navbar ul.tabs > li:not(.tab-divider) > a').first().hover();
            const hoverState = await geometry(page);
            expect(hoverState.railWidth).toBe(1);
            expect(hoverState.navbarBorderRightColor).toBe('rgb(200, 211, 225)');
            await page.screenshot({
                path: path.join(screenshotDir, 'tuvtk-authenticated-sidenav-1.0.20.png'),
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
                    `tuvtk-authenticated-administration-1.0.20-${viewport.name}.png`
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
