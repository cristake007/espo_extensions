'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const cache = new Map([['dompurify', {sanitize: value => String(value).replace(/<[^>]*>/g, '')}]]);
let activeDefinition;

function define(dependencies, factory) { activeDefinition = {dependencies, factory}; }
function load(name) {
    if (cache.has(name)) return cache.get(name);
    activeDefinition = null;
    const file = path.join(sourceRoot, `${name.replace(/^document-builder:/, '')}.js`);
    new Function('define', fs.readFileSync(file, 'utf8'))(define);
    const value = activeDefinition.factory(...activeDefinition.dependencies.map(load));
    cache.set(name, value);

    return value;
}

const text = value => ({nodeType: 3, nodeValue: value});
const element = (tagName, children = [], options = {}) => ({
    nodeType: 1,
    tagName,
    childNodes: children,
    children,
    dataset: options.dataset || {},
    style: options.style || {},
    matches(selector) { return selector === '[data-rich-variable]' && 'richVariable' in this.dataset; },
});
const originalVariable = {
    type: 'variable', tokenId: 'variable_token', label: 'Current Date',
    identity: {source: 'system', type: 'system', path: ['currentDate']},
    presentation: {format: {type: 'auto', decimals: 2, currency: null, dateStyle: 'medium', timeStyle: 'short', trim: false, case: 'none', separator: ', ', trueLabel: null, falseLabel: null, prefix: '', suffix: '', fallback: null}, missing: 'empty'},
};
const host = element('DIV', [
    element('STRONG', [text('Selected ')]),
    element('SPAN', [text('color')], {style: {color: 'rgb(1, 2, 3)'}}),
    element('SPAN', [], {dataset: {richVariable: '', tokenId: 'variable_token'}}),
    element('UL', [element('LI', [text('One')]), element('LI', [element('EM', [text('Two')])])]),
]);
const Wysiwyg = load('document-builder:editor/content/wysiwyg');
const content = Wysiwyg.read(host, [originalVariable]);

assert.deepEqual(content[0], {type: 'text', text: 'Selected ', marks: ['bold']});
assert.deepEqual(content[1], {type: 'text', text: 'color', marks: [], color: '#010203'});
assert.deepEqual(content[2], originalVariable);
assert.equal(content[3].type, 'list');
assert.equal(content[3].style, 'bulleted');
assert.deepEqual(content[3].items[1][0].marks, ['italic']);

const shell = fs.readFileSync(path.join(sourceRoot, 'views/editor/shell.js'), 'utf8');
const canvas = fs.readFileSync(path.join(sourceRoot, 'editor/canvas/document-canvas.js'), 'utf8');
const template = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/templates/editor/shell.tpl'), 'utf8');
assert.match(canvas, /contentEditable = preview \? 'false' : 'true'/);
assert.match(canvas, /dataset\.richEditor/);
assert.match(shell, /selectionchange/);
assert.match(shell, /Wysiwyg\.captureRange/);
assert.match(shell, /Wysiwyg\.insertVariable/);
assert.match(shell, /pasteRichText[\s\S]*getData\('text\/plain'\)/);
assert.match(shell, /new UpdateNodeCommand\(location\.node\.id, \{content\}\)[\s\S]*render: false/);
assert.match(template, /data-rich-command="insertUnorderedList"/);
assert.match(template, /data-rich-command="insertOrderedList"/);
assert.doesNotMatch(shell, /innerHTML\s*=/);

console.log('Editor recovery 05 structured WYSIWYG tests passed.');
