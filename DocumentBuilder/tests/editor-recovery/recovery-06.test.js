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

class Element {
    constructor(tag, ownerDocument) {
        this.tag = tag; this.tagName = tag.toUpperCase(); this.ownerDocument = ownerDocument;
        this.children = []; this.childNodes = this.children; this.dataset = {};
        this.style = {cssText: ''}; this.attributes = {}; this.className = '';
        this.classList = {add: value => { this.className += ` ${value}`; }};
    }
    append(child) { this.children.push(child); }
    replaceChildren(...children) { this.children = children; this.childNodes = this.children; }
    insertBefore(child) { this.children.unshift(child); }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    get firstChild() { return this.children[0] || null; }
}
const documentRef = {
    createElement(tag) { return new Element(tag, documentRef); },
    createTextNode(value) { return {nodeType: 3, text: value}; },
};
const host = new Element('div', documentRef);
const DocumentCanvas = load('document-builder:editor/canvas/document-canvas');
new DocumentCanvas().render(host, [{
    id: 'paragraph', type: 'paragraph', label: 'Paragraph', region: 'sections',
    pageNumber: 1, flowStyle: '', selected: false, isParagraph: true,
    content: [{type: 'text', text: 'Clean', marks: []}], children: [],
}], {preview: true});
assert.equal(host.children.length, 1, 'Preview rendered editing drop targets or toolbars.');
const paragraph = host.children[0];
assert.equal(paragraph.draggable, false);
assert.equal(paragraph.dataset.action, undefined);
assert.equal(paragraph.children[0].contentEditable, 'false');
assert.equal(paragraph.children[0].dataset.richEditor, undefined);

const shell = fs.readFileSync(path.join(sourceRoot, 'views/editor/shell.js'), 'utf8');
const template = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/templates/editor/shell.tpl'), 'utf8');
const css = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/css/editor.css'), 'utf8');
assert.match(shell, /loadPreview\(mode, recordId\)[\s\S]*this\.previewApi\.load\(/);
assert.match(shell, /actionPdfProof[\s\S]*this\.previewApi\.loadPdf\(/);
assert.match(shell, /preview:\s*this\.canvasPreviewOpen/);
assert.match(shell, /selectedId:\s*this\.canvasPreviewOpen \? null/);
assert.match(shell, /rememberCanvasScroll/);
assert.match(shell, /actionBackToEdit/);
assert.match(template, /data-action="backToEdit"/);
assert.match(template, /data-action="pdfProof"/);
assert.match(template, /PDF Proof/);
assert.match(template, /unless cleanPreviewActive/);
assert.match(css, /workspace\.is-preview[\s\S]*grid-template-columns:\s*minmax\(320px, 1fr\)/);

console.log('Editor recovery 06 clean canvas preview and PDF Proof tests passed.');
