'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const clientRoot = path.join(root, 'files/client/custom/modules/document-builder');
const template = fs.readFileSync(path.join(clientRoot, 'res/templates/editor/shell.tpl'), 'utf8');
const shell = fs.readFileSync(path.join(clientRoot, 'src/views/editor/shell.js'), 'utf8');
const css = fs.readFileSync(path.join(clientRoot, 'res/css/editor.css'), 'utf8');
const leftStart = template.indexOf('<aside class="document-builder-editor__left"');
const leftEnd = template.indexOf('</aside>', leftStart);
const left = template.slice(leftStart, leftEnd);
const rightStart = template.indexOf('<aside class="document-builder-editor__inspector"');
const rightEnd = template.indexOf('</aside>', rightStart);
const right = template.slice(rightStart, rightEnd);

assert.ok(leftStart >= 0 && leftEnd > leftStart);
assert.doesNotMatch(left, /data-library-type=/, 'The left sidebar must be variables-only.');
for (const contract of [
    'data-source-setting="entityType"',
    'data-variable-search',
    'System Variables',
    'Entity Fields',
    'toggleMetadataRelationship',
]) assert.match(left, new RegExp(contract));
assert.match(left, /is-field is-insertable[^>]*draggable="true"/);
assert.match(left, /title="\{\{variablePathKey\}\}"/);
assert.match(left, /document-builder-editor__variable-copy/);

assert.equal((right.match(/role="tab"/g) || []).length, 2);
assert.match(right, /data-action="showElementsTab"/);
assert.match(right, /data-action="showPropertiesTab"/);
for (const type of [
    'flow-section', 'flow-container', 'heading', 'paragraph', 'static-text',
    'divider', 'spacer', 'page-break',
]) assert.match(right, new RegExp(`data-library-type="${type}"`));
assert.match(right, /data-action="focusVariables"/);
assert.match(right, /selectedFlowNode\.variablePresentation/);

assert.match(shell, /rightTabElements:\s*this\.rightSidebarTab === 'elements'/);
assert.match(shell, /rightTabProperties:\s*this\.rightSidebarTab === 'properties'/);
assert.match(shell, /actionEditFlowNode[\s\S]*rightSidebarTab = 'properties'/);
assert.match(shell, /kind:\s*'variable'[\s\S]*presentation:\s*this\.variablePresentationDraft/);
assert.match(shell, /new AddFlowNodeCommand\(this\.flowStructure, type, target, options\)/);
assert.match(css, /variable-copy[\s\S]*text-overflow:\s*ellipsis/);
assert.match(css, /sidebar-tabs[\s\S]*grid-template-columns:\s*1fr 1fr/);

console.log('Editor recovery 04 sidebar contract tests passed.');
