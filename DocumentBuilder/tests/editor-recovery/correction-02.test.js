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

assert.match(canvas, /element\.draggable = !preview && !node\.isHeading && !node\.isParagraph/);
assert.match(canvas, /editor\.contentEditable = preview \? 'false' : 'true'/);
assert.match(shell, /drop \[data-rich-editor\]/);
assert.match(shell, /handleRichTextFlowDrop[\s\S]*applyFlowDrop\(target\)/);
assert.match(shell, /handleRichTextFlowDragOver[\s\S]*is-rich-drop-before/);
assert.match(shell, /application\/x-document-builder-flow/);
assert.doesNotMatch(shell, /setData\('text\/plain', JSON\.stringify\(this\.flowDrag\)\)/);
assert.match(shell, /handleRichTextKeydown[\s\S]*Keyboard\.isManualSave/);
assert.match(css, /is-rich-drop-before[\s\S]*box-shadow/);
assert.match(css, /is-rich-drop-after[\s\S]*box-shadow/);

console.log('Editor correction 02 WYSIWYG and structural-drop isolation tests passed.');
