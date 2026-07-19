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

const AddFlowNodeCommand = load('document-builder:editor/commands/add-flow-node');
const EditorState = load('document-builder:editor/state/editor-state');
const FlowStructure = load('document-builder:editor/flow/flow-structure');
const LayoutPrecheck = load('document-builder:editor/validation/layout-precheck');
const RichText = load('document-builder:editor/content/rich-text');
const StableIdFactory = load('document-builder:editor/state/stable-id-factory');
const VariablePresentation = load('document-builder:editor/variables/variable-presentation');
const layout = JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/layout/phase-08-default.json')));
const ids = ['section', 'variable'];
const state = new EditorState(layout, new StableIdFactory(() => ids.shift()));
const flow = new FlowStructure();
const section = new AddFlowNodeCommand(flow, 'flow-section', {
    region: 'sections', parentId: null, index: null,
});
assert.equal(state.execute(section), true);

const identity = {source: 'system', type: 'system', path: ['currentDate']};
const presentation = VariablePresentation.defaults();
const variable = new AddFlowNodeCommand(flow, 'variable', {parentId: section.addedId}, {
    label: 'Current Date', identity, presentation,
});
assert.equal(state.execute(variable), true);
assert.equal(state.getLayout().sections[0].children[0].type, 'variable');
assert.equal(new LayoutPrecheck().check(state.getLayout()).valid, true);
assert.throws(() => flow.createNode('variable', {
    label: 'Courses',
    identity: {source: 'entity', type: 'collection', entityType: 'Contact', path: ['courses']},
    presentation,
}), /scalar/);

const list = RichText.createList('numbered', [
    [{type: 'text', text: 'First', marks: []}],
    [{type: 'variable', tokenId: 'token_date', label: 'Current Date', identity, presentation}],
]);
assert.equal(RichText.toPlainText([list]), 'First\n{{Current Date}}');
assert.deepEqual(RichText.toggleMark([list], 'bold')[0].items[0][0].marks, ['bold']);

const documentRef = {
    createTextNode(text) { return {text}; },
    createElement(tag) {
        return {
            tag, children: [], style: {}, classList: {add() {}}, dataset: {},
            append(child) { this.children.push(child); },
        };
    },
};
const host = {children: [], replaceChildren() { this.children = []; }, append(child) { this.children.push(child); }};
RichText.render(host, [list], documentRef);
assert.equal(host.children[0].tag, 'ol');
assert.equal(host.children[0].children.length, 2);

console.log('Editor recovery 01 client schema and structured-content tests passed.');
