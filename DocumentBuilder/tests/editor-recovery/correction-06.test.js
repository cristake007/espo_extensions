'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const shell = fs.readFileSync(path.join(sourceRoot, 'views/editor/shell.js'), 'utf8');
const canvas = fs.readFileSync(path.join(sourceRoot, 'editor/canvas/document-canvas.js'), 'utf8');
const flow = fs.readFileSync(path.join(sourceRoot, 'editor/flow/flow-structure.js'), 'utf8');
const pageSettings = fs.readFileSync(path.join(sourceRoot, 'editor/page-settings.js'), 'utf8');

assert.match(shell, /createView\('canvasWysiwyg', 'views\/fields\/wysiwyg'/);
assert.match(shell, /\['heading', 'static-text', 'paragraph'\]\.includes\(location\.node\.type\)/);
assert.match(shell, /params: \{height: 0, minHeight: 32, maxLength: 10000, toolbar\}/);
assert.match(shell, /surface\.dataset\.richEditor = ''/);
assert.match(shell, /syncRichTextSurface[\s\S]*\['heading', 'static-text', 'paragraph'\]/);
assert.match(canvas, /node\.isContent \|\| node\.isHeading \|\| node\.isStaticText \|\| node\.isParagraph[\s\S]*node\.selected[\s\S]*dataset\.wysiwygMount/);
assert.match(flow, /type === 'static-text'[\s\S]*content:/);
assert.match(pageSettings, /normalizeStaticText[\s\S]*delete node\.text/);

console.log('Editor correction 06 EspoCRM native WYSIWYG integration tests passed.');
