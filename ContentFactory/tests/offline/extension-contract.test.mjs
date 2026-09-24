import assert from 'node:assert/strict';
import {existsSync, readFileSync} from 'node:fs';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const readJson = path => JSON.parse(readFileSync(new URL(path, root), 'utf8'));

test('extension exposes two custom pages and one native history entity', () => {
    const generator = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/metadata/scopes/ContentFactoryGenerator.json'
    );
    const analytics = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/metadata/scopes/ContentFactoryAnalytics.json'
    );
    const history = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/metadata/scopes/ContentFactoryContent.json'
    );

    assert.equal(generator.entity, false);
    assert.equal(generator.acl, 'boolean');
    assert.equal(analytics.entity, false);
    assert.equal(analytics.acl, 'boolean');
    assert.equal(history.entity, true);
    assert.equal(history.acl, true);
    assert.equal(history.type, 'BasePlus');
});

test('history is list-only and opens records in the read-only generator', () => {
    const clientDefs = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/metadata/clientDefs/ContentFactoryContent.json'
    );

    const controller = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Controllers/ContentFactoryContent.php', root
    ), 'utf8');
    const clientController = readFileSync(new URL(
        'files/client/custom/modules/content-factory/src/controllers/content-factory-content.js', root
    ), 'utf8');

    assert.equal(
        clientDefs.controller,
        'content-factory:controllers/content-factory-content'
    );
    assert.equal(clientDefs.createDisabled, true);
    assert.equal(clientDefs.inlineEditDisabled, true);
    assert.match(clientController, /actionView\(options\)/);
    assert.match(clientController, /content-factory:views\/generator\/index/);
    assert.match(clientController, /readOnly: true/);
    assert.equal(existsSync(new URL(
        'files/client/custom/modules/content-factory/src/controllers/content-factory-history.js', root
    )), false);
    assert.equal(existsSync(new URL(
        'files/client/custom/modules/content-factory/src/views/history/index.js', root
    )), false);
    assert.match(controller, /class ContentFactoryContent extends Record/);
    assert.ok(existsSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Resources/routes.json', root
    )));
});

test('generator uses the protected backend endpoint and can reload a history record', () => {
    const generator = readFileSync(new URL(
        'files/client/custom/modules/content-factory/src/views/generator/index.js', root
    ), 'utf8');
    const routes = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/routes.json'
    );

    assert.match(generator, /postRequest\(\s*'ContentFactory\/generate'/);
    assert.doesNotMatch(generator, /contentFactoryN8nWebhookUrl/);
    assert.match(generator, /getRequest\(/);
    assert.match(generator, /applyHistoryRecord\(record\)/);
    assert.match(generator, /select, textarea, input/);
    assert.deepEqual(routes, [
        {
            route: '/ContentFactory/generate',
            method: 'post',
            actionClassName: 'Espo\\Modules\\ContentFactory\\Api\\PostGenerate',
        },
        {
            route: '/ContentFactory/subjects',
            method: 'get',
            actionClassName: 'Espo\\Modules\\ContentFactory\\Api\\GetSubjects',
        },
    ]);
});

test('course selection uses WordPress and adds versioned editorial context to the n8n payload', () => {
    const generator = readFileSync(new URL(
        'files/client/custom/modules/content-factory/src/views/generator/index.js', root
    ), 'utf8');
    const request = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/Generation/GenerationRequest.php',
        root
    ), 'utf8');
    const client = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/Subject/WordPressCourseClient.php',
        root
    ), 'utf8');
    const integration = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/metadata/integrations/ContentFactoryWordPress.json'
    );

    assert.match(generator, /ContentFactory\/subjects/);
    assert.match(generator, /this\.contentType = 'general'/);
    assert.match(generator, /course:\s*this\.course \? \{\.\.\.this\.course\} : null/);
    assert.match(generator, /const isCourse = this\.contentType === 'course'/);
    assert.match(generator, /if \(this\.contentType !== 'course'\) \{\s*return;/);
    assert.match(generator, /this\.contentType !== 'course' \|\| Boolean\(this\.course && this\.course\.id\)/);
    assert.match(client, /\/wp-json\/wp\/v2\/cursuri/);
    assert.match(client, /CURLOPT_HTTPAUTH/);
    assert.equal(integration.fields.applicationPassword.type, 'password');
    const n8nPayload = request.match(/toN8nPayload[\s\S]*?\n    }/)[0];
    assert.match(n8nPayload, /'contentType' => \$this->contentType/);
    assert.match(n8nPayload, /'course' => \$this->course/);
});

test('server-side generation validates before creating a history record', () => {
    const service = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/Generation/GenerationService.php',
        root
    ), 'utf8');
    const writer = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/Persistence/ContentHistoryWriter.php',
        root
    ), 'utf8');

    const responseValidation = service.indexOf('$this->responseValidator->validate');
    const persistence = service.indexOf('$this->historyWriter->write');

    assert.ok(responseValidation > -1 && persistence > responseValidation);
    assert.match(service, /Table::ACTION_CREATE/);
    assert.match(writer, /'status' => 'Draft'/);
    assert.match(writer, /'assignedUserId' => \$this->user->getId\(\)/);
    assert.match(writer, /'generatedContent' => \$json/);
});

test('validator rejection logging contains bounded structural diagnostics', () => {
    const service = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/Generation/GenerationService.php',
        root
    ), 'utf8');

    assert.match(service, /ContentFactory n8n response rejected\. diagnostic=/);
    assert.match(service, /firstImageDataLength/);
    assert.doesNotMatch(service, /firstImageData['"]\s*=>/);
});

test('n8n connection uses the native EspoCRM Integration mechanism', () => {
    const integration = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/metadata/integrations/ContentFactoryN8n.json'
    );
    const settingsProvider = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/Config/SettingsProvider.php',
        root
    ), 'utf8');
    const generationService = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/Generation/GenerationService.php',
        root
    ), 'utf8');
    const n8nClient = readFileSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/N8n/N8nClient.php',
        root
    ), 'utf8');

    assert.equal(integration.allowUserAccounts, false);
    assert.equal(integration.view, 'views/admin/integrations/edit');
    assert.equal(integration.fields.webhookUrl.type, 'url');
    assert.equal(integration.fields.authenticationToken.type, 'password');
    assert.deepEqual(integration.fields.connectTimeoutSeconds, {
        type: 'int', required: true, default: 5, min: 1, max: 30,
    });
    assert.deepEqual(integration.fields.responseTimeoutSeconds, {
        type: 'int', required: true, default: 90, min: 10, max: 180,
    });
    assert.match(settingsProvider, /Integration::class/);
    assert.match(settingsProvider, /ContentFactoryN8n/);
    assert.doesNotMatch(settingsProvider, /contentFactoryN8nWebhookUrl/);
    assert.match(generationService, /!\$settings->enabled \|\| \$settings->webhookUrl === ''/);
    assert.ok(
        generationService.indexOf('!$settings->enabled') <
        generationService.indexOf('$this->n8nClient->generate')
    );
    assert.doesNotMatch(n8nClient, /SettingsProvider/);
    assert.match(n8nClient, /Settings \$settings/);
    assert.equal(existsSync(new URL(
        'files/custom/Espo/Modules/ContentFactory/Resources/metadata/app/config.json', root
    )), false);
});

test('both supported languages define Content Factory navigation labels', () => {
    for (const locale of ['ro_RO', 'en_US']) {
        const language = readJson(
            `files/custom/Espo/Modules/ContentFactory/Resources/i18n/${locale}/Global.json`
        );

        assert.equal(language.labels.ContentFactory, 'Content Factory');
        assert.ok(language.scopeNames.ContentFactoryGenerator);
        assert.ok(language.scopeNames.ContentFactoryAnalytics);
        assert.ok(language.scopeNames.ContentFactoryContent);
    }
});

test('both supported languages label the native integration', () => {
    for (const locale of ['ro_RO', 'en_US']) {
        const language = readJson(
            `files/custom/Espo/Modules/ContentFactory/Resources/i18n/${locale}/Integration.json`
        );

        assert.equal(language.titles.ContentFactoryN8n, 'Content Factory / n8n');
        assert.ok(language.fields.webhookUrl);
        assert.ok(language.fields.authenticationToken);
        assert.ok(language.fields.connectTimeoutSeconds);
        assert.ok(language.fields.responseTimeoutSeconds);
    }
});

test('post preview renders optional hashtags and final image assets safely', () => {
    const content = readFileSync(new URL(
        'files/client/custom/modules/content-factory/src/views/previews/content.js', root
    ), 'utf8');
    const post = readFileSync(new URL(
        'files/client/custom/modules/content-factory/src/views/previews/social-post.js', root
    ), 'utf8');
    const shared = readFileSync(new URL(
        'files/client/custom/modules/content-factory/src/views/previews/shared.js', root
    ), 'utf8');

    assert.ok(content.indexOf('image.imageDataUrl') < content.indexOf('image.imageUrl'));
    assert.match(content, /Array\.isArray\(asset\.hashtags\)/);
    assert.match(post, /if \(content\.hashtags\)/);
    assert.match(post, /content-factory-post-hashtags/);
    assert.match(shared, /content-factory-preview-media-image/);
    assert.match(shared, /image\.on\('error'/);
});

test('post preview offers desktop and mobile viewport modes', () => {
    const preview = readFileSync(new URL(
        'files/client/custom/modules/content-factory/src/views/previews/social-post.js', root
    ), 'utf8');
    const css = readFileSync(new URL(
        'files/client/custom/modules/content-factory/css/content-factory.css', root
    ), 'utf8');

    assert.match(preview, /data-post-viewport/);
    assert.match(preview, /is-desktop/);
    assert.match(preview, /is-mobile/);
    assert.match(css, /content-factory-post-viewport-stage\.is-desktop/);
    assert.match(css, /content-factory-post-viewport-stage\.is-mobile/);
});
