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
        this.tag = tag;
        this.ownerDocument = ownerDocument;
        this.children = [];
        this.dataset = {};
        this.style = {cssText: ''};
        this.attributes = {};
        this.className = '';
        this.classList = {add: value => {
            const values = new Set(this.className.split(/\s+/).filter(Boolean));
            values.add(value);
            this.className = [...values].join(' ');
        }};
    }
    append(child) { this.children.push(child); }
    replaceChildren(...children) { this.children = children; }
    insertBefore(child, reference) {
        const index = this.children.indexOf(reference);
        this.children.splice(index < 0 ? this.children.length : index, 0, child);
    }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    get firstChild() { return this.children[0] || null; }
}

const documentRef = {
    createElement(tag) { return new Element(tag, documentRef); },
    createTextNode(text) { return {tag: '#text', text}; },
};
const DocumentCanvas = load('document-builder:editor/canvas/document-canvas');
const host = new Element('div', documentRef);
const base = {
    region: 'sections', pageNumber: 1, flowStyle: '', selected: false, depth: 0,
    label: 'Container', children: [],
};
const tree = [{
    ...base, id: 'section', type: 'flow-section', isSection: true, isContainer: false,
    children: [{
        ...base, id: 'container', type: 'flow-container', isSection: false, isContainer: true, depth: 1,
        children: [{
            ...base, id: 'heading', type: 'heading', label: 'Heading', isHeading: true, depth: 2,
            content: [{type: 'text', text: 'Nested title', marks: []}],
        }],
    }],
}];
new DocumentCanvas().render(host, tree);
const section = host.children.find(item => item.dataset?.nodeId === 'section');
const container = section.children.find(item => item.dataset?.nodeId === 'container');
const heading = container.children.find(item => item.dataset?.nodeId === 'heading');
assert.equal(section.tag, 'section');
assert.equal(container.tag, 'div');
assert.equal(heading.tag, 'h2');
assert.equal(section.dataset.flowDepth, '0');
assert.equal(container.dataset.flowDepth, '1');
assert.equal(heading.dataset.flowDepth, '2');
assert.equal(heading.children[0].className, 'document-builder-editor__rich-editor');
assert.equal(heading.children[0].children[0].text, 'Nested title');
assert.equal(section.attributes['aria-keyshortcuts'], 'ArrowUp ArrowDown Home End');
assert.equal(host.children.filter(item => item.dataset?.flowDrop).length, 2);
assert.equal(container.children.filter(item => item.dataset?.flowDrop).length, 2);

const template = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/templates/editor/shell.tpl'), 'utf8');
const css = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/css/editor.css'), 'utf8');
assert.match(template, /data-document-canvas/);
assert.doesNotMatch(template, /level-badge|child-count|node-badge/);
assert.match(css, /\.document-builder-editor__flow-node\s*\{[^}]*outline:\s*1px solid/s);
assert.match(css, /\.document-builder-editor__flow-node\.is-flow-container\s*\{[^}]*outline-style:\s*dashed/s);
assert.match(css, /\.document-builder-editor__flow-node\.is-selected\s*\{[^}]*outline:/s);
assert.match(css, /\.document-builder-editor__drop\s*\{[^}]*display:\s*none/s);
assert.doesNotMatch(css, /document-builder-depth/);

console.log('Editor recovery 02 nested document canvas tests passed.');
