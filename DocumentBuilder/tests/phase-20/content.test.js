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
    cache.set(name, value); return value;
}

const RichText = load('document-builder:editor/content/rich-text');
const FlowStructure = load('document-builder:editor/flow/flow-structure');
const EditorState = load('document-builder:editor/state/editor-state');
const StableIdFactory = load('document-builder:editor/state/stable-id-factory');
const AddFlowNodeCommand = load('document-builder:editor/commands/add-flow-node');
const UpdateNodeCommand = load('document-builder:editor/commands/update-node');
const LayoutPrecheck = load('document-builder:editor/validation/layout-precheck');
const VariablePresentation = load('document-builder:editor/variables/variable-presentation');
const layout = JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/layout/phase-08-default.json')));
const ids = ['section', 'container', 'heading', 'static', 'paragraph'];
const state = new EditorState(layout, new StableIdFactory(() => ids.shift()));
const flow = new FlowStructure();
const section = new AddFlowNodeCommand(flow, 'flow-section', {region: 'sections', parentId: null});
state.execute(section);
const container = new AddFlowNodeCommand(flow, 'flow-container', {parentId: section.addedId}); state.execute(container);
for (const type of ['heading', 'static-text', 'paragraph']) state.execute(new AddFlowNodeCommand(flow, type, {parentId: container.addedId}));
assert.equal(new LayoutPrecheck([], {}).check(state.getLayout()).valid, true);
const heading = state.getLayout().sections[0].children[0].children[0];
assert.equal(state.execute(new UpdateNodeCommand(heading.id, {content: RichText.toggleMark(heading.content, 'bold')})), true);
assert.deepEqual(heading.content[0].marks, []); // command works on a cloned layout
assert.deepEqual(state.getLayout().sections[0].children[0].children[0].content[0].marks, ['bold']);
assert.equal(state.undo(), true); assert.equal(state.redo(), true);

assert.deepEqual(RichText.fromPlainText('<img onerror=alert(1)>safe\r\nline', ['italic']), [
    {type: 'text', text: 'safe', marks: ['italic']}, {type: 'break'},
    {type: 'text', text: 'line', marks: ['italic']},
]);
const identity = {source: 'system', type: 'system', path: ['currentDate']};
const presentation = VariablePresentation.defaults();
assert.throws(() => RichText.appendVariable([], 'bad token', 'x', identity, presentation), /Invalid/);
const content = RichText.appendVariable(
    [{type: 'text', text: '<script>', marks: []}],
    'token_safe',
    'Name',
    identity,
    presentation,
);
const created = [];
const documentRef = {
    createTextNode(text) { const node = {kind: 'text', text}; created.push(node); return node; },
    createElement(tag) { return {tag, children: [], style: {}, append(node) { this.children.push(node); }}; },
};
const host = {children: [], replaceChildren() { this.children = []; }, append(node) { this.children.push(node); }};
RichText.render(host, content, documentRef);
assert.equal(created[0].text, '<script>');
assert.equal(host.children[1].textContent, '{{Name}}');
assert.equal(JSON.stringify(state.getLayout()).includes('"type":"heading"'), true);

console.log('Phase 20 structured content, safe rendering, sanitation, history, and precheck tests passed.');
