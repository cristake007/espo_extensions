'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
let PageGeometry;

new Function('define', fs.readFileSync(
    path.join(sourceRoot, 'editor/geometry/page-geometry.js'),
    'utf8',
))((dependencies, factory) => { PageGeometry = factory(); });

const geometry = new PageGeometry();
assert.deepEqual(geometry.getSizeList().map(item => item.id), ['A4', 'A3']);
assert.deepEqual(
    {widthMm: geometry.getPage('A3').widthMm, heightMm: geometry.getPage('A3').heightMm},
    {widthMm: 297, heightMm: 420},
);
assert.deepEqual(
    {widthMm: geometry.getPage('A3', 'landscape').widthMm,
        heightMm: geometry.getPage('A3', 'landscape').heightMm},
    {widthMm: 420, heightMm: 297},
);

const shell = fs.readFileSync(path.join(sourceRoot, 'views/editor/shell.js'), 'utf8');
const precheck = fs.readFileSync(path.join(sourceRoot, 'editor/validation/layout-precheck.js'), 'utf8');
const canvas = fs.readFileSync(path.join(sourceRoot, 'editor/canvas/document-canvas.js'), 'utf8');
assert.match(shell, /this\.customPageSizes = \[\]/);
assert.match(precheck, /A3: Object\.freeze\(\{width: 297, height: 420\}\)/);
assert.doesNotMatch(precheck, /Letter:|Legal:/);
assert.match(canvas, /value\.className = 'document-builder-editor__variable-value'/);
assert.match(canvas, /element\.append\(value\)/);
assert.doesNotMatch(canvas, /element\.textContent = node\.variableText/);

const metadataRoot = path.join(root,
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata');
const entity = JSON.parse(fs.readFileSync(
    path.join(metadataRoot, 'entityDefs/DocumentBuilderTemplate.json'),
    'utf8',
));
const app = JSON.parse(fs.readFileSync(
    path.join(metadataRoot, 'app/documentBuilder.json'),
    'utf8',
));
assert.deepEqual(entity.fields.pageSize.options, ['A4', 'A3']);
assert.deepEqual(app.allowedValues.defaultPageSize, ['A4', 'A3']);

console.log('Editor correction 08 variable controls and A4/A3 page-setting tests passed.');
