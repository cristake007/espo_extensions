'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const extensionRoot = path.resolve(__dirname, '../..');
const moduleRoot = path.join(
    extensionRoot,
    'files/client/custom/modules/document-builder',
);
const metadataRoot = path.join(
    extensionRoot,
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata',
);
const readJson = relativePath => JSON.parse(fs.readFileSync(
    path.join(metadataRoot, relativePath),
    'utf8',
));
const modulePath = reference => {
    assert.match(reference, /^document-builder:[a-z0-9/-]+$/);

    return path.join(moduleRoot, 'src', `${reference.split(':')[1]}.js`);
};

const clientDefs = readJson('clientDefs/DocumentBuilderTemplate.json');
const clientRoutes = readJson('app/clientRoutes.json');
const client = readJson('app/client.json');
const route = clientRoutes['DocumentBuilderTemplate/editor/:id'];

assert.ok(route, 'The editor route is missing.');
assert.equal(route.params.controller, 'DocumentBuilderTemplate');
assert.equal(route.params.action, 'editor');
assert.equal(
    fs.existsSync(modulePath(clientDefs.controller)),
    true,
    `Tracked controller metadata points to missing module ${clientDefs.controller}.`,
);

const controllerSource = fs.readFileSync(modulePath(clientDefs.controller), 'utf8');
assert.match(
    controllerSource,
    /actionEditor\s*\(/,
    'The tracked editor route points to a controller without actionEditor.',
);

for (const action of clientDefs.detailActionList || []) {
    if (!action.handler || !action.handler.startsWith('document-builder:')) {
        continue;
    }

    assert.equal(
        fs.existsSync(modulePath(action.handler)),
        true,
        `Tracked action metadata points to missing module ${action.handler}.`,
    );
}

for (const cssPath of (client.cssList || []).filter(value => value !== '__APPEND__')) {
    assert.equal(
        fs.existsSync(path.join(extensionRoot, 'files', cssPath)),
        true,
        `Tracked client metadata points to missing stylesheet ${cssPath}.`,
    );
}

console.log('Phase 15 tracked client metadata reference integrity tests passed.');
