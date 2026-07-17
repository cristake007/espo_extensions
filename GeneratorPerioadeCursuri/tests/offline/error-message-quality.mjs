import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';
import path from 'node:path';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionRoot = path.resolve(testDirectory, '../..');
const detailPath = path.join(
    extensionRoot,
    'files/client/custom/modules/generator-perioade-cursuri/src/views/' +
    'generator-perioade-cursuri-wordpress-updater/record/detail.js'
);
const recordUiPath = path.join(
    extensionRoot,
    'files/client/custom/modules/generator-perioade-cursuri/src/views/shared/record-ui.js'
);

let ViewClass;
let RecordUi;

class DetailRecordView {}

const context = {
    URL,
    define(name, dependencies, factory) {
        if (name === 'generator-perioade-cursuri:views/shared/record-ui') {
            RecordUi = factory();

            return;
        }

        assert.ok(
            dependencies.includes('views/record/detail'),
            'the updater detail view must retain its native base dependency'
        );
        assert.ok(
            dependencies.includes('generator-perioade-cursuri:views/shared/record-ui'),
            'the updater detail view must load the shared record UI module'
        );
        ViewClass = factory(DetailRecordView, RecordUi);
    },
};

vm.runInNewContext(fs.readFileSync(recordUiPath, 'utf8'), context, {filename: recordUiPath});
vm.runInNewContext(fs.readFileSync(detailPath, 'utf8'), context, {filename: detailPath});
assert.equal(typeof ViewClass, 'function', 'the production detail view must load through AMD');

const localePaths = {
    en_US: path.join(extensionRoot, 'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/GeneratorPerioadeCursuriWordPressUpdater.json'),
    ro_RO: path.join(extensionRoot, 'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/GeneratorPerioadeCursuriWordPressUpdater.json'),
};

const forbiddenExactMessages = new Set([
    'not valid', 'invalid', 'error', 'failed', '400', '403', '404', '409', '422', '500',
    '[object object]', 'undefined', 'null',
]);
const forbiddenContent = /(?:authorization|bearer\s|password|token|stack trace|\.php:\d+|\/var\/www|sqlstate|select\s+.+\s+from|exception)/i;

let checks = 0;

for (const [locale, localePath] of Object.entries(localePaths)) {
    const translations = JSON.parse(fs.readFileSync(localePath, 'utf8'));
    const view = new ViewClass();
    view.translate = (key, category) => translations[category]?.[key] ?? key;

    const cases = [
        {operation: 'preview', error: null},
        {operation: 'preview', error: {status: 400, responseJSON: {error: 'not valid'}}},
        {operation: 'connect', error: {status: 403, responseJSON: {error: 'invalid'}}},
        {operation: 'preview', error: {status: 404, responseJSON: {error: 'error'}}},
        {operation: 'fetchDates', error: {status: 409, responseJSON: {error: 'failed'}}},
        {operation: 'updateRow', error: {status: 422, responseText: '{"error":"422"}'}},
        {operation: 'updateRow', error: {status: 500, responseJSON: {error: 'RuntimeException at /var/www/html/application/Foo.php:42'}}},
        {operation: 'preview', error: {status: 0, responseText: ''}},
        {operation: 'preview', error: {status: 200, responseText: '{malformed'}},
        {operation: 'preview', error: {status: 500, responseJSON: {error: '[object Object]'}}},
        {operation: 'preview', error: {status: 500, responseJSON: {error: 'wpUpdaterSourceInvalid'}}},
        {operation: 'preview', error: {status: 500, getResponseHeader: () => 'SQLSTATE[42000] SELECT secret FROM user'}},
    ];

    for (const testCase of cases) {
        const message = view.getWordPressUpdaterError(testCase.error, testCase.operation);
        checks++;
        assert.equal(typeof message, 'string', `${locale}: error text must be a string`);
        checks++;
        assert.ok(message.trim().length >= 12, `${locale}: error text must be useful: ${message}`);
        checks++;
        assert.ok(!forbiddenExactMessages.has(message.trim().toLowerCase()), `${locale}: generic error leaked: ${message}`);
        checks++;
        assert.ok(!forbiddenContent.test(message), `${locale}: sensitive/internal error leaked: ${message}`);
        checks++;
        assert.ok(!/^wpUpdater[A-Z]/.test(message), `${locale}: untranslated key leaked: ${message}`);
    }

    const specific = view.getWordPressUpdaterError(
        {status: 409, responseJSON: {error: translations.messages.wpUpdaterPreviewStale}},
        'fetchDates'
    );
    checks++;
    assert.equal(specific, translations.messages.wpUpdaterPreviewStale, `${locale}: useful localized server errors must be preserved`);
}

console.log(`Offline error-message quality: ${checks} checks passed; production JavaScript executed.`);
