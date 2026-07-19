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

assert.match(canvas, /element\.dataset\.flowContainerDrop = ''/);
assert.match(shell, /dragover \[data-flow-drop\], \[data-flow-container-drop\]/);
assert.match(shell, /flowDropTarget\(element\)[\s\S]*dataset\.flowContainerDrop[\s\S]*parentId: element\.dataset\.nodeId/);
assert.match(shell, /querySelectorAll\('\[data-flow-drop\], \[data-flow-container-drop\]'\)/);
assert.match(shell, /handleFlowDrop\(event\)[\s\S]*event\.stopPropagation\(\)/);
assert.match(css, /data-flow-container-drop[^}]*is-drag-over[\s\S]*box-shadow/);

console.log('Editor correction 05 full container drop-surface tests passed.');
