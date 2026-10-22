import {expect, test} from '../../../AttendanceManagement/tests/browser/node_modules/@playwright/test/index.mjs';
import {readFile} from 'node:fs/promises';
import path from 'node:path';

const repositoryRoot = path.resolve(import.meta.dirname, '..', '..', '..');
const clientRoot = path.join(repositoryRoot, 'TuvtkTheme', 'files', 'client');
const baseUrl = (process.env.ESPO_BASE_URL || '').replace(/\/$/, '');
const screenshotDir = process.env.TUVTK_SCREENSHOT_DIR || '/tmp';

async function routeCandidateAssets(page) {
    await page.route('**/client/custom/css/tuvtk.css*', route =>
        route.fulfill({
            contentType: 'text/css',
            path: path.join(clientRoot, 'custom', 'css', 'tuvtk.css'),
        })
    );
    await page.route('**/client/custom/css/tuvtk-iframe.css*', route =>
        route.fulfill({
            contentType: 'text/css',
            path: path.join(clientRoot, 'custom', 'css', 'tuvtk-iframe.css'),
        })
    );
    await page.route('**/client/custom/img/tuvtk-login-office.png*', route =>
        route.fulfill({
            contentType: 'image/png',
            path: path.join(clientRoot, 'custom', 'img', 'tuvtk-login-office.png'),
        })
    );
}

test.beforeEach(async ({page}) => {
    test.skip(!baseUrl, 'ESPO_BASE_URL is required.');
    await routeCandidateAssets(page);
});

test('desktop login renders the candidate 25/75 split over native Espo CSS', async ({page}) => {
    await page.setViewportSize({width: 1440, height: 900});
    await page.goto(baseUrl, {waitUntil: 'networkidle'});
    await expect(page.locator('#login')).toBeVisible();

    const visual = await page.evaluate(() => {
        const content = document.querySelector('body > .container.content');
        const centering = content.querySelector('.container-centering');
        const login = document.querySelector('#login');
        const heading = login.querySelector('.panel-heading');
        const panelBody = login.querySelector('.panel-body');
        const username = login.querySelector('#field-userName');
        const button = login.querySelector('#btn-login');
        const bodyBefore = getComputedStyle(document.body, '::before');
        const bodyStyle = getComputedStyle(document.body);
        const loginStyle = getComputedStyle(login);
        const usernameRect = username.getBoundingClientRect();
        const buttonRect = button.getBoundingClientRect();

        return {
            themeName: bodyStyle.getPropertyValue('--theme-name').trim(),
            contentWidth: content.getBoundingClientRect().width,
            image: bodyBefore.backgroundImage,
            backgrounds: [document.body, content, centering, login, heading, panelBody]
                .map(element => getComputedStyle(element).backgroundColor),
            loginBorderWidth: loginStyle.borderTopWidth,
            loginShadow: loginStyle.boxShadow,
            usernameLeft: usernameRect.left,
            usernameWidth: usernameRect.width,
            buttonLeft: buttonRect.left,
            buttonWidth: buttonRect.width,
            horizontalOverflow: document.documentElement.scrollWidth - window.innerWidth,
        };
    });

    expect(visual.themeName).toBe('Tuvtk');
    expect(visual.contentWidth).toBeGreaterThan(350);
    expect(visual.contentWidth).toBeLessThan(370);
    expect(visual.image).toContain('tuvtk-login-office.png');
    expect(visual.backgrounds).toEqual(Array(6).fill('rgb(255, 255, 255)'));
    expect(visual.loginBorderWidth).toBe('0px');
    expect(visual.loginShadow).toBe('none');
    expect(Math.abs(visual.buttonLeft - visual.usernameLeft)).toBeLessThanOrEqual(0.5);
    expect(Math.abs(visual.buttonWidth - visual.usernameWidth)).toBeLessThanOrEqual(0.5);
    expect(visual.horizontalOverflow).toBeLessThanOrEqual(0);
    await page.screenshot({path: path.join(screenshotDir, 'tuvtk-login-1.0.21-desktop.png'), fullPage: true});
});

test('mobile login stays single-column and contained', async ({page}) => {
    await page.setViewportSize({width: 390, height: 844});
    await page.goto(baseUrl, {waitUntil: 'networkidle'});
    await expect(page.locator('#login')).toBeVisible();

    const visual = await page.evaluate(() => {
        const content = document.querySelector('body > .container.content');
        const centering = content.querySelector('.container-centering');
        const login = document.querySelector('#login');
        const usernameRect = login.querySelector('#field-userName').getBoundingClientRect();
        const buttonRect = login.querySelector('#btn-login').getBoundingClientRect();

        return {
            image: getComputedStyle(document.body, '::before').backgroundImage,
            backgrounds: [document.body, content, centering, login]
                .map(element => getComputedStyle(element).backgroundColor),
            horizontalOverflow: document.documentElement.scrollWidth - window.innerWidth,
            loginWidth: login.getBoundingClientRect().width,
            usernameLeft: usernameRect.left,
            usernameWidth: usernameRect.width,
            buttonLeft: buttonRect.left,
            buttonWidth: buttonRect.width,
        };
    });

    expect(visual.image).toBe('none');
    expect(visual.backgrounds).toEqual(Array(4).fill('rgb(255, 255, 255)'));
    expect(visual.horizontalOverflow).toBeLessThanOrEqual(0);
    expect(visual.loginWidth).toBeLessThanOrEqual(390);
    expect(Math.abs(visual.buttonLeft - visual.usernameLeft)).toBeLessThanOrEqual(0.5);
    expect(Math.abs(visual.buttonWidth - visual.usernameWidth)).toBeLessThanOrEqual(0.5);
    await page.screenshot({path: path.join(screenshotDir, 'tuvtk-login-1.0.21-mobile.png'), fullPage: true});
});

const navigationFixture = `<!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" href="${baseUrl}/client/custom/css/tuvtk.css">
    </head>
    <body data-navbar="side">
        <nav id="navbar">
            <div class="navbar navbar-inverse">
                <div class="navbar-header">
                    <div class="navbar-logo-container"><a class="navbar-brand">TUVTK</a></div>
                    <a role="button" class="side-menu-button"><span class="fas fa-bars"></span></a>
                </div>
                <div class="navbar-collapse navbar-body">
                    <div class="navbar-left-container">
                        <ul class="nav navbar-nav tabs">
                            <li class="tab active"><a href="#"><span class="short-label">D</span><span class="full-label">Dashboard</span></a></li>
                            <li class="tab"><a href="#"><span class="short-label">C</span><span class="full-label">Calendar</span></a></li>
                            <li class="tab-divider"><div><span class="label-text">Operations</span></div></li>
                        </ul>
                        <a class="minimizer" role="button">
                            <span class="fas fa-chevron-right right"></span>
                            <span class="fas fa-chevron-left left"></span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>
        <main id="main">
            <button class="btn btn-primary">Primary</button>
            <button class="btn btn-danger">Danger</button>
            <div class="calendar-container">
                <div class="fc">
                    <div class="fc-day-sat fc-daygrid-day"><a class="fc-daygrid-day-number">24</a></div>
                </div>
            </div>
        </main>
        <script>
            (() => {
                const storageKey = 'tuvtk-test-site-layout-state';
                const body = document.body;
                let isSideMenuOpened = false;

                if (localStorage.getItem(storageKey) === 'collapsed') {
                    body.classList.add('minimized');
                }

                const closeSideMenu = () => {
                    isSideMenuOpened = false;
                    body.classList.remove('side-menu-opened');
                };
                const switchMinimizer = () => {
                    if (isSideMenuOpened) {
                        closeSideMenu();

                        return;
                    }

                    if (body.classList.contains('minimized')) {
                        body.classList.remove('minimized');
                        localStorage.setItem(storageKey, 'expanded');
                    } else {
                        body.classList.add('minimized');
                        localStorage.setItem(storageKey, 'collapsed');
                    }
                };
                const switchSideMenu = () => {
                    if (!body.classList.contains('minimized')) {
                        return;
                    }

                    if (isSideMenuOpened) {
                        closeSideMenu();
                    } else {
                        isSideMenuOpened = true;
                        body.classList.add('side-menu-opened');
                    }
                };

                document.querySelector('a.minimizer').addEventListener('click', switchMinimizer);
                document.querySelector('a.side-menu-button').addEventListener('click', switchSideMenu);
                window.tuvtkNavbarFixture = {
                    get isSideMenuOpened() {
                        return isSideMenuOpened;
                    },
                };
            })();
        </script>
    </body>
    </html>
`;

async function loadNavigationFixture(page) {
    await page.route('**/__tuvtk-navbar-fixture', route => {
        return route.fulfill({
            contentType: 'text/html',
            body: navigationFixture,
        });
    });
    await page.goto(`${baseUrl}/__tuvtk-navbar-fixture`, {waitUntil: 'networkidle'});
    await page.evaluate(() => localStorage.removeItem('tuvtk-test-site-layout-state'));
    await page.reload({waitUntil: 'networkidle'});
}

async function navbarGeometry(page) {
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
            isSideMenuOpened: window.tuvtkNavbarFixture.isSideMenuOpened,
            storedLayoutState: localStorage.getItem('tuvtk-test-site-layout-state'),
            navbarRight: navbarRect.right,
            navbarWidth: navbarRect.width,
            containerEdge: containingRight,
            minimizerOffsetParentIsNavbar: minimizer.offsetParent === navbar,
            navbarBorderRightColor: navbarStyle.borderRightColor,
            railCenter: navbarRect.right - railWidth / 2,
            minimizerCenter: minimizerRect.left + minimizerRect.width / 2,
            minimizerCenterY: minimizerRect.top + minimizerRect.height / 2,
            iconCenter: visibleIconRect.left + visibleIconRect.width / 2,
            iconCenterY: visibleIconRect.top + visibleIconRect.height / 2,
            iconDisplay: visibleIconStyle.display,
            minimizerVisible: minimizerRect.width > 0 && minimizerRect.height > 0,
            railWidth,
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
        };
    });
}

test('navigation, semantic buttons and calendar retain the TUVTK identity', async ({page}) => {
    await page.setViewportSize({width: 1440, height: 900});
    await loadNavigationFixture(page);

    const activeLink = page.locator('#navbar .tab.active > a');
    const normalLink = page.locator('#navbar .tab').nth(1).locator('a');
    await normalLink.hover();

    const visual = await page.evaluate(() => {
        const active = document.querySelector('#navbar .tab.active > a');
        const hovered = document.querySelectorAll('#navbar .tab > a')[1];
        const weekend = document.querySelector('.fc-day-sat');
        const primary = document.querySelector('.btn-primary');
        const danger = document.querySelector('.btn-danger');

        return {
            activeHeight: getComputedStyle(active).height,
            activeMarkerHeight: getComputedStyle(active, '::before').height,
            hoverColor: getComputedStyle(hovered).color,
            weekendPattern: getComputedStyle(weekend).backgroundImage,
            primaryBackground: getComputedStyle(primary).backgroundColor,
            dangerBackground: getComputedStyle(danger).backgroundColor,
        };
    });

    await expect(activeLink).toBeVisible();
    expect(visual.activeHeight).toBe('36px');
    expect(Number.parseFloat(visual.activeMarkerHeight)).toBeGreaterThanOrEqual(32);
    expect(visual.hoverColor).toBe('rgb(212, 17, 49)');
    expect(visual.weekendPattern).toContain('repeating-linear-gradient');
    expect(visual.primaryBackground).toBe('rgb(22, 65, 148)');
    expect(visual.dangerBackground).toBe('rgb(212, 17, 49)');

    await page.locator('a.minimizer').hover();
    await page.waitForTimeout(200);
    const rail = await page.locator('#navbar > .navbar').evaluate(element => {
        const navbarStyle = getComputedStyle(element);
        const nativeAfterStyle = getComputedStyle(element, '::after');

        return {
            borderRightColor: navbarStyle.borderRightColor,
            borderRightWidth: navbarStyle.borderRightWidth,
            nativeAfterBackground: nativeAfterStyle.backgroundColor,
            nativeAfterPosition: nativeAfterStyle.position,
        };
    });
    expect(rail.borderRightColor).toBe('rgb(22, 65, 148)');
    expect(rail.borderRightWidth).toBe('1px');
    expect(rail.nativeAfterBackground).toBe('rgba(0, 0, 0, 0)');
    expect(rail.nativeAfterPosition).not.toBe('absolute');
    await page.screenshot({path: path.join(screenshotDir, 'tuvtk-components-1.0.21.png'), fullPage: true});
});

test('form and modal styling remains scoped and token-driven', async ({page}) => {
    await page.setViewportSize({width: 1440, height: 900});
    await page.setContent(`
        <main id="main">
            <div class="record">
                <div class="panel panel-default">
                    <label class="control-label" for="record-name">Name</label>
                    <input id="record-name" class="form-control">
                </div>
            </div>
            <div class="modal-content">
                <div class="modal-body">
                    <div class="panel panel-default">
                        <label class="control-label" for="modal-name">Modal name</label>
                        <input id="modal-name" class="form-control">
                    </div>
                </div>
            </div>
        </main>
    `);
    await page.addStyleTag({url: `${baseUrl}/client/custom/css/tuvtk.css`});
    const styles = await page.evaluate(() => ({
        controlRadius: getComputedStyle(document.querySelector('#record-name')).borderRadius,
        modalRadius: getComputedStyle(document.querySelector('.modal-content')).borderRadius,
        recordPanelShadow: getComputedStyle(document.querySelector('.record .panel')).boxShadow,
        modalPanelShadow: getComputedStyle(document.querySelector('.modal-body .panel')).boxShadow,
        recordLabelColor: getComputedStyle(document.querySelector('.record label')).color,
        modalLabelColor: getComputedStyle(document.querySelector('.modal-body label')).color,
    }));

    expect(styles.controlRadius).toBe('3px');
    expect(styles.modalRadius).toBe('3px');
    expect(styles.recordPanelShadow).toBe('none');
    expect(styles.modalPanelShadow).toBe('none');
    expect(styles.recordLabelColor).toBe('rgb(124, 135, 151)');
    expect(styles.modalLabelColor).toBe('rgb(124, 135, 151)');
});

test('chevron closes the hamburger overlay once and preserves the permanent state', async ({page}) => {
    await page.setViewportSize({width: 1440, height: 900});
    await loadNavigationFixture(page);

    const minimizer = page.locator('a.minimizer');
    const hamburger = page.locator('a.side-menu-button');
    const states = [];
    const capture = async name => states.push({name, ...await navbarGeometry(page)});

    await capture('expanded');
    await page.screenshot({
        path: path.join(screenshotDir, 'tuvtk-sidenav-expanded-1.0.21.png'),
        fullPage: true,
    });
    await minimizer.click();
    await capture('expanded-to-collapsed-by-chevron');
    await page.screenshot({
        path: path.join(screenshotDir, 'tuvtk-sidenav-collapsed-1.0.21.png'),
        fullPage: true,
    });
    await hamburger.click();
    await capture('collapsed-to-overlay-by-hamburger');
    await page.screenshot({
        path: path.join(screenshotDir, 'tuvtk-sidenav-hamburger-expanded-1.0.21.png'),
        fullPage: true,
    });
    await minimizer.click();
    await capture('overlay-closed-by-one-chevron-click');

    await minimizer.click();
    await capture('collapsed-to-expanded-by-chevron');

    await minimizer.click();
    await hamburger.click();
    await capture('overlay-opened-by-hamburger');
    await hamburger.click();
    await capture('overlay-closed-by-hamburger');

    await page.reload({waitUntil: 'networkidle'});
    await capture('collapsed-restored-after-reload');

    for (const state of states) {
        expect(state.minimizerVisible, state.name).toBe(true);
        expect(state.minimizerOffsetParentIsNavbar, state.name).toBe(true);
        expect(Math.abs(state.minimizerCenter - state.containerEdge), state.name).toBeLessThanOrEqual(0.5);
        expect(Math.abs(state.minimizerCenter - state.railCenter), state.name).toBeLessThanOrEqual(0.5);
        expect(Math.abs(state.iconCenter - state.minimizerCenter), state.name).toBeLessThanOrEqual(0.5);
        expect(Math.abs(state.iconCenterY - state.minimizerCenterY), state.name).toBeLessThanOrEqual(0.5);
        expect(state.iconDisplay, state.name).not.toBe('none');
        expect(state.railWidth, state.name).toBe(1);
        expect(state.railHeight, state.name).toBeGreaterThanOrEqual(899);
        expect(state.nativeAfterBackground, state.name).toBe('rgba(0, 0, 0, 0)');
        expect(state.nativeAfterPosition, state.name).not.toBe('absolute');
        expect(state.nativeAfterTransform, state.name).toBe('none');
        expect(state.navbarHeaderBorders, state.name).toEqual(Array(4).fill('0px'));
        expect(state.sideMenuButtonBorders, state.name).toEqual(Array(4).fill('0px'));
    }

    expect(states[1].classes).toContain('minimized');
    expect(states[1].navbarWidth).toBeLessThan(states[0].navbarWidth);
    expect(states[1].storedLayoutState).toBe('collapsed');
    expect(states[2].classes).toEqual(expect.arrayContaining(['minimized', 'side-menu-opened']));
    expect(Math.abs(states[2].navbarWidth - states[0].navbarWidth)).toBeLessThanOrEqual(0.5);
    expect(states[2].isSideMenuOpened).toBe(true);
    expect(states[3].classes).toContain('minimized');
    expect(states[3].classes).not.toContain('side-menu-opened');
    expect(states[3].isSideMenuOpened).toBe(false);
    expect(states[3].storedLayoutState).toBe('collapsed');
    expect(states[4].classes).not.toContain('minimized');
    expect(states[4].storedLayoutState).toBe('expanded');
    expect(states[5].classes).toEqual(expect.arrayContaining(['minimized', 'side-menu-opened']));
    expect(states[6].classes).toContain('minimized');
    expect(states[6].classes).not.toContain('side-menu-opened');
    expect(states[7].classes).toContain('minimized');
    expect(states[7].classes).not.toContain('side-menu-opened');
    expect(states[7].storedLayoutState).toBe('collapsed');
});

test('Administration headings are blue without leaking to dashboard headings', async ({page}) => {
    await page.goto('about:blank');
    const administrationMarkup = `
        <main id="main">
            <div class="page-header"><h3>Administration</h3></div>
            <div class="admin-content">
                <div class="row">
                    <div class="col-md-7">
                        <div class="admin-tables-container">
                            <div class="admin-content-section">
                                <h4>System</h4>
                                <table class="table table-admin-panel">
                                    <tr class="admin-content-row">
                                        <td><div><span class="icon fas fa-cog"></span><a href="#">Settings</a></div></td>
                                        <td class="description">Configure system settings.</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="admin-content-section"><h4>Users</h4></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    `;

    for (const viewport of [
        {name: 'desktop', width: 1440, height: 900},
        {name: 'mobile', width: 390, height: 844},
    ]) {
        await page.setViewportSize({width: viewport.width, height: viewport.height});
        await page.setContent(administrationMarkup);
        await page.addStyleTag({url: `${baseUrl}/client/custom/css/tuvtk.css`});

        const adminColors = await page.locator(
            '#main > .page-header > h3, ' +
            '#main > .admin-content .admin-tables-container > .admin-content-section > h4'
        ).evaluateAll(elements => elements.map(element => getComputedStyle(element).color));
        expect(adminColors).toEqual(Array(3).fill('rgb(22, 65, 148)'));

        const nonHeadingColors = await page.locator(
            '#main .admin-content-row a, #main .admin-content-row .icon, ' +
            '#main .admin-content-row .description'
        ).evaluateAll(elements => elements.map(element => getComputedStyle(element).color));
        expect(nonHeadingColors).not.toContain('rgb(22, 65, 148)');

        await page.screenshot({
            path: path.join(
                screenshotDir,
                `tuvtk-administration-headings-1.0.21-${viewport.name}.png`
            ),
            fullPage: true,
        });
    }

    await page.setContent(`
        <main id="main">
            <div class="page-header"><h3>Dashboard</h3></div>
            <div class="dashboard-container"></div>
        </main>
    `);
    await page.addStyleTag({url: `${baseUrl}/client/custom/css/tuvtk.css`});

    await expect(page.locator('#main .page-header h3')).toHaveCSS('color', 'rgb(17, 24, 39)');
    await page.screenshot({
        path: path.join(screenshotDir, 'tuvtk-dashboard-heading-1.0.21.png'),
        fullPage: true,
    });
});

test('native sidenav controls remain hidden at the mobile breakpoint', async ({page}) => {
    await page.setViewportSize({width: 390, height: 844});
    await loadNavigationFixture(page);

    await expect(page.locator('a.minimizer')).toBeHidden();
    await expect(page.locator('a.side-menu-button')).toBeHidden();
});
