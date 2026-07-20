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

assert.match(canvas, /if \(!preview && node\.selected\)[\s\S]*selectionToolbar/);
assert.match(canvas, /toolbar\.dataset\.selectionToolbar = ''/);
assert.match(canvas, /button\.dataset\.nodeId = nodeId/);
assert.match(canvas, /value\.className = 'document-builder-editor__variable-value'/);
assert.match(canvas, /element\.append\(value\)/);
assert.doesNotMatch(canvas, /element\.textContent = node\.variableText/);
assert.doesNotMatch(canvas, /hoverToolbar|data\.hoverToolbar/);
assert.doesNotMatch(shell, /mouseover \[data-document-canvas\]|handleCanvasHover|hoverHideTimer/);
assert.match(css, /selection-toolbar[\s\S]*position:\s*absolute[\s\S]*right:\s*0[\s\S]*transform:\s*translateY/);
assert.doesNotMatch(css, /hover-toolbar|hover-action/);

console.log('Editor correction 07 selection-only action tests passed.');
