import {test, expect} from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const directory = path.dirname(fileURLToPath(import.meta.url));
const csvPath = path.join(directory, 'fixtures/schedule.csv');
const username = process.env.ESPO_ADMIN_USERNAME;
const password = process.env.ESPO_ADMIN_PASSWORD;

test.skip(!username || !password, 'ESPO_ADMIN_USERNAME and ESPO_ADMIN_PASSWORD are required.');
test.describe.configure({mode: 'serial'});

let recordId;
let runTag;
const createdAttachmentIds = new Set();

function assertUsefulMessage(message) {
    expect(typeof message).toBe('string');
    expect(message.trim().length).toBeGreaterThanOrEqual(12);
    expect(message.trim()).not.toMatch(/^(?:not valid|invalid|error|failed|undefined|null|\[object Object\]|\d{3})$/i);
    expect(message).not.toMatch(/(?:authorization|bearer\s|password|token|stack\s*trace|\.php:\d+|\/var\/www|sqlstate|exception)/i);
    expect(message).not.toMatch(/^wpUpdater[A-Z]/);
    expect(message).not.toMatch(/^\s*[\[{]/);
}

async function installNetworkGuard(page) {
    const prohibited = [];

    await page.context().route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        const wordpressRest = url.pathname.includes('/wp-json/');
        const remoteUpdaterAction = /GeneratorPerioadeCursuriWordPressUpdater\/[^/]+\/(?:connect|fetchDates|updateRow)$/.test(url.pathname);

        if ((wordpressRest && request.method() !== 'GET') || remoteUpdaterAction) {
            prohibited.push(`${request.method()} ${url.origin}${url.pathname}`);
            await route.abort('blockedbyclient');
            return;
        }

        await route.continue();
    });

    return prohibited;
}

async function login(page) {
    await page.goto('/');

    const usernameInput = page.getByRole('textbox', {name: /Username|Utilizator/i}).first();
    const navbar = page.getByRole('searchbox', {name: /Search|Cautare/i}).first();
    await Promise.race([
        usernameInput.waitFor({state: 'visible', timeout: 30_000}),
        navbar.waitFor({state: 'visible', timeout: 30_000}),
    ]);

    if (await usernameInput.isVisible().catch(() => false)) {
        await usernameInput.fill(username);
        await page.getByRole('textbox', {name: /Password|Parola/i}).fill(password);
        const authResponsePromise = page.waitForResponse(response =>
            /\/api\/v1\/App\/user$/.test(new URL(response.url()).pathname)
        );
        await page.getByRole('button', {name: /Log in|Autentificare/i}).click();
        const authResponse = await authResponsePromise;
        expect(authResponse.status(), 'EspoCRM browser authentication must succeed.').toBe(200);
        await expect(page.getByRole('button', {name: /Log in|Autentificare/i})).toBeHidden({timeout: 20_000});
        await expect.poll(async () => {
            const cookies = await page.context().cookies();
            return cookies.some(cookie => cookie.name === 'auth-token' && cookie.value.length > 0);
        }, {timeout: 30_000, message: 'EspoCRM must finish persisting the browser auth token.'}).toBeTruthy();
        await expect(navbar, 'EspoCRM authenticated navigation must finish loading.').toBeVisible({timeout: 30_000});
    }

    await expect(page.locator('body')).not.toContainText(/Authentication failed|Autentificare esuata/i);
}

async function fileInput(page) {
    const input = page.locator('[data-name="wpScheduleFile"] input[type="file"], input[type="file"]').first();
    await expect(input).toBeAttached();
    return input;
}

async function attemptSave(page) {
    await page.getByRole('button', {name: /^(?:Save|Salveaza)$/i}).click();
    await page.waitForTimeout(500);
}

async function navigateHash(page, hash) {
    await page.evaluate(value => {
        window.location.hash = value;
    }, hash);

    const confirmLeave = page.getByRole('button', {name: /^(?:Yes|Da)$/i}).last();
    if (await confirmLeave.isVisible({timeout: 1_500}).catch(() => false)) {
        await confirmLeave.click();
    }
}

function previewButton(page) {
    return page.locator(
        '[data-name="buildWordPressPreview"]:visible, [data-action="buildWordPressPreview"]:visible'
    ).first();
}

async function openCreate(page) {
    await navigateHash(page, '#GeneratorPerioadeCursuriWordPressUpdater/create');
    await expect(page.locator('input[data-name="name"], [data-name="name"] input').first()).toBeVisible();
}

test.beforeEach(async ({page}) => {
    const prohibited = await installNetworkGuard(page);
    page.__prohibitedRequests = prohibited;
    page.on('response', async response => {
        const url = new URL(response.url());

        if (response.request().method() === 'POST' && /\/api\/v1\/Attachment$/.test(url.pathname) && response.ok()) {
            const data = await response.json().catch(() => ({}));
            if (data.id) createdAttachmentIds.add(data.id);
        }
    });
    await login(page);
});

test.afterEach(async ({page}) => {
    expect(page.__prohibitedRequests, 'No WordPress REST or remote updater action may be attempted.').toEqual([]);
});

test.afterAll(async ({browser}) => {
    if (!recordId && createdAttachmentIds.size === 0) return;
    const page = await browser.newPage();
    const prohibited = await installNetworkGuard(page);
    await login(page);

    if (recordId) {
        const response = await page.request.delete(`/api/v1/GeneratorPerioadeCursuriWordPressUpdater/${recordId}`);
        expect([200, 204, 404]).toContain(response.status());
    }

    for (const attachmentId of createdAttachmentIds) {
        const response = await page.request.delete(`/api/v1/Attachment/${attachmentId}`);
        expect([200, 204, 404]).toContain(response.status());
    }

    expect(prohibited).toEqual([]);
    await page.close();
});

test('visible create, native upload, local preview and responsive workflow', async ({page}) => {
    runTag = `AUTOTEST-GPC-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
    await openCreate(page);
    await page.locator('input[data-name="name"], [data-name="name"] input').first().fill(runTag);

    await attemptSave(page);
    const requiredMessage = page.locator('.field[data-name="wpScheduleFile"] .error:visible, .popover:visible, .toast:visible, .alert:visible').first();
    await expect(requiredMessage).toBeVisible();
    assertUsefulMessage((await requiredMessage.textContent()) || '');

    const csvName = `${runTag}.csv`;
    const uploadPromise = page.waitForResponse(response =>
        response.request().method() === 'POST' && /\/api\/v1\/Attachment$/.test(new URL(response.url()).pathname)
    );
    await (await fileInput(page)).setInputFiles({
        name: csvName,
        mimeType: 'text/csv',
        buffer: fs.readFileSync(csvPath),
    });
    const uploadResponse = await uploadPromise;
    expect(uploadResponse.ok()).toBeTruthy();
    const uploadData = await uploadResponse.json();
    createdAttachmentIds.add(uploadData.id);
    await expect(page.locator('body')).toContainText(csvName);

    const createResponse = await page.request.post('/api/v1/GeneratorPerioadeCursuriWordPressUpdater', {
        data: {name: runTag, wpScheduleFileId: uploadData.id},
    });
    expect(createResponse.ok()).toBeTruthy();
    const created = await createResponse.json();
    recordId = created.id;
    expect(recordId).toBeTruthy();
    await navigateHash(page, `#GeneratorPerioadeCursuriWordPressUpdater/view/${recordId}`);
    await expect(page).toHaveURL(new RegExp(recordId));
    await expect(previewButton(page)).toBeVisible();

    await expect(previewButton(page)).toBeEnabled();
    await previewButton(page).focus();
    await expect(previewButton(page)).toBeFocused();
    const previewResponsePromise = page.waitForResponse(response =>
        response.request().method() === 'POST' &&
        new URL(response.url()).pathname.endsWith(`/GeneratorPerioadeCursuriWordPressUpdater/${recordId}/preview`)
    );
    await previewButton(page).click();
    const previewResponse = await previewResponsePromise;
    expect(previewResponse.ok(), await previewResponse.text()).toBeTruthy();
    await expect(page.locator('.wordpress-updater-table-scroll table')).toBeVisible();
    await expect(page.locator('.wordpress-updater-table-scroll tbody tr')).toHaveCount(3);
    await expect(page.locator('.wordpress-updater-row-error')).toHaveCount(2);
    await expect(page.locator('.wordpress-updater-global-status[role="status"]')).toBeVisible();

    await page.reload();
    await expect(previewButton(page)).toBeVisible();

    await page.setViewportSize({width: 520, height: 800});
    await previewButton(page).click();
    const tableScroller = page.locator('.wordpress-updater-table-scroll').first();
    await expect(tableScroller).toBeVisible();
    expect(await tableScroller.evaluate(element => element.scrollWidth > element.clientWidth)).toBeTruthy();
    await page.setViewportSize({width: 1440, height: 900});
});

test('generic and malformed preview failures render useful localized messages', async ({page}) => {
    test.skip(!recordId, 'The serial workflow did not create a record.');
    await navigateHash(page, `#GeneratorPerioadeCursuriWordPressUpdater/view/${recordId}`);
    await expect(previewButton(page)).toBeVisible();
    const endpoint = `**/api/v1/GeneratorPerioadeCursuriWordPressUpdater/${recordId}/preview`;
    const scenarios = [
        {status: 400, body: JSON.stringify({error: 'not valid'})},
        {status: 403, body: JSON.stringify({error: 'invalid'})},
        {status: 404, body: JSON.stringify({error: 'error'})},
        {status: 409, body: JSON.stringify({error: 'failed'})},
        {status: 422, body: JSON.stringify({error: '422'})},
        {status: 500, body: JSON.stringify({error: 'RuntimeException at /var/www/html/Foo.php:42'})},
        {status: 500, body: '{malformed'},
        {status: 500, body: ''},
    ];

    for (const scenario of scenarios) {
        await page.route(endpoint, route => route.fulfill({status: scenario.status, contentType: 'application/json', body: scenario.body}), {times: 1});
        await previewButton(page).click();
        const alert = page.locator('.wordpress-updater-row-error[role="alert"]').last();
        await expect(alert).toBeVisible();
        assertUsefulMessage((await alert.textContent()) || '');
    }
});
