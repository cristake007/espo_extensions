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
    'System Variables',
    'Entity Fields',
    'toggleMetadataRelationship',
]) assert.match(left, new RegExp(contract));
assert.doesNotMatch(left, /data-variable-search/);
assert.match(left, /is-field is-insertable[^>]*draggable="true"/);
assert.match(left, /title="\{\{variablePathKey\}\}"/);
assert.match(left, /document-builder-editor__variable-copy/);

assert.equal((right.match(/role="tab"/g) || []).length, 2);
assert.match(right, /data-action="showElementsTab"/);
assert.match(right, /data-action="showPropertiesTab"/);
for (const type of [
    'flow-section', 'flow-container', 'heading', 'paragraph', 'static-text', 'variable',
    'divider', 'spacer', 'page-break',
]) assert.match(right, new RegExp(`data-library-type="${type}"`));
assert.match(right, /data-action="addVariable"[^>]*draggable="true"/);
assert.match(right, /selectedFlowNode\.variablePresentation/);

assert.match(shell, /rightTabElements:\s*this\.rightSidebarTab === 'elements'/);
assert.match(shell, /rightTabProperties:\s*this\.rightSidebarTab === 'properties'/);
assert.match(shell, /'click \[data-action="selectCanvas"\]':\s*'actionSelectCanvas'/);
assert.match(shell, /actionSelectCanvas[\s\S]*pageSelected[\s\S]*nextTab = pageSelected \? 'properties' : 'elements'/);
assert.match(shell, /canvasSelectionActive:\s*false|this\.canvasSelectionActive = false/);
assert.match(shell, /actionEditFlowNode[\s\S]*rightSidebarTab = 'properties'/);
assert.match(shell, /kind:\s*'variable'[\s\S]*presentation:\s*this\.variablePresentationDraft/);
assert.match(shell, /new AddFlowNodeCommand\(this\.flowStructure, type, target, options\)/);
assert.match(css, /variable-copy[\s\S]*text-overflow:\s*ellipsis/);
assert.match(css, /sidebar-tabs[\s\S]*grid-template-columns:\s*1fr 1fr/);
assert.match(css, /flow-node\.is-container-node\[data-flow-depth="2"\]/);
assert.match(css, /inspector-section-body[\s\S]*grid-template-rows:\s*0fr/);
assert.match(template, /canvas-scroll" data-action="selectCanvas"/);
assert.match(template, /document-builder-editor__page \{\{#if canvasSelected\}\}is-selected/);
assert.match(template, /data-action="toggleInspectorSection"/);
assert.match(template, /document-builder-editor__condition-summary-list/);
assert.match(template, /data-action="removeConditionRule"[^>]*aria-label="Remove rule/);
assert.match(template, /document-builder-editor__no-selection/);

let shellDefinition;
new Function('define', shell)((dependencies, factory) => {
    shellDefinition = {dependencies, factory};
});
const dependencyStubs = shellDefinition.dependencies.map((name, index) =>
    index === 0 ? class View {} : {}
);
const ShellView = shellDefinition.factory(...dependencyStubs);
const view = new ShellView();
const sectionClasses = new Set();
const bodyAttributes = new Map();
const body = {
    toggleAttribute(name, force) {
        if (force) bodyAttributes.set(name, '');
        else bodyAttributes.delete(name);
    },
    setAttribute(name, value) { bodyAttributes.set(name, value); },
};
const section = {
    classList: {
        contains(name) { return sectionClasses.has(name); },
        toggle(name, force) {
            if (force) sectionClasses.add(name);
            else sectionClasses.delete(name);
        },
    },
    querySelector(selector) {
        assert.equal(selector, '.document-builder-editor__inspector-section-body');

        return body;
    },
};
const toggleAttributes = new Map();
const toggle = {
    dataset: {inspectorSection: 'visibility'},
    closest(selector) {
        assert.equal(selector, '.document-builder-editor__inspector-section');

        return section;
    },
    setAttribute(name, value) { toggleAttributes.set(name, value); },
};
view.openInspectorSections = new Set();
view.actionToggleInspectorSection({currentTarget: toggle});
assert.equal(sectionClasses.has('is-open'), true);
assert.equal(toggleAttributes.get('aria-expanded'), 'true');
assert.equal(bodyAttributes.has('inert'), false);
assert.equal(bodyAttributes.get('aria-hidden'), 'false');
assert.equal(view.openInspectorSections.has('visibility'), true);
view.actionToggleInspectorSection({currentTarget: toggle});
assert.equal(sectionClasses.has('is-open'), false);
assert.equal(toggleAttributes.get('aria-expanded'), 'false');
assert.equal(bodyAttributes.has('inert'), true);
assert.equal(bodyAttributes.get('aria-hidden'), 'true');
assert.equal(view.openInspectorSections.has('visibility'), false);

console.log('Editor recovery 04 sidebar contract tests passed.');
