'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const canvas = fs.readFileSync(path.join(sourceRoot, 'editor/canvas/document-canvas.js'), 'utf8');
const shell = fs.readFileSync(path.join(sourceRoot, 'views/editor/shell.js'), 'utf8');
const css = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/css/editor.css'), 'utf8');

for (const icon of ['fa-grip-vertical', 'fa-pen', 'fa-copy', 'fa-trash-alt']) {
    assert.match(canvas, new RegExp(icon));
}
assert.match(canvas, /button\.setAttribute\('aria-label', translate\(label, 'actions'\)\)/);
assert.match(canvas, /button\.draggable = draggable/);
assert.doesNotMatch(canvas, /button\.textContent = translate\(label, 'actions'\)/);
assert.doesNotMatch(shell, /handleCanvasHover|scheduleCanvasHoverHide|data-hover-toolbar/);
assert.match(canvas, /node\.selected[\s\S]*selectionToolbar/);
assert.match(css, /selection-action[\s\S]*width:\s*24px[\s\S]*height:\s*24px/);

console.log('Editor correction 03 compact selected-control tests passed.');
