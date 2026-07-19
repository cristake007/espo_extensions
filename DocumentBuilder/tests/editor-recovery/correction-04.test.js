'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder');
const shell = fs.readFileSync(path.join(sourceRoot, 'src/views/editor/shell.js'), 'utf8');
const template = fs.readFileSync(path.join(sourceRoot, 'res/templates/editor/shell.tpl'), 'utf8');

assert.match(shell, /supportsTypography = \['heading', 'static-text', 'paragraph', 'variable'\]/);
assert.match(shell, /supportsContainerAlignment = \['flow-section', 'flow-container'\]/);
assert.match(shell, /isStructural: \['flow-section', 'flow-container'\]/);
assert.match(template, /\{\{#if selectedFlowNode\.supportsTypography\}\}[\s\S]*data-style-setting="fontSize"/);
assert.match(template, /\{\{#if selectedFlowNode\.supportsAppearance\}\}[\s\S]*data-style-setting="backgroundColor"/);
assert.match(template, /\{\{#if selectedFlowNode\.supportsContainerAlignment\}\}[\s\S]*data-style-setting="horizontalAlignment"/);
assert.doesNotMatch(template, /data-style-setting="verticalAlignment"/);
assert.match(template, /\{\{#if selectedFlowNode\.isSpacer\}\}[\s\S]*data-basic-flow-setting="height"/);
assert.match(template, /\{\{#if selectedFlowNode\.isStructural\}\}[\s\S]*data-flow-setting="marginTop"/);

console.log('Editor correction 04 type-specific Properties tests passed.');
