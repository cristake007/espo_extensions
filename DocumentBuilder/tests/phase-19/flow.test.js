'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const extensionRoot = path.resolve(__dirname, '../..');
const sourceRoot = path.join(extensionRoot, 'files/client/custom/modules/document-builder/src');
const cache = new Map();
let activeDefinition = null;

function define(dependencies, factory) { activeDefinition = {dependencies, factory}; }
function load(moduleName) {
    if (cache.has(moduleName)) return cache.get(moduleName);
    const filename = path.join(sourceRoot, `${moduleName.replace(/^document-builder:/, '')}.js`);
    activeDefinition = null;
    new Function('define', `${fs.readFileSync(filename, 'utf8')}\n//# sourceURL=${filename}`)(define);
    const definition = activeDefinition;
    const exported = definition.factory(...definition.dependencies.map(load));
    cache.set(moduleName, exported);
    return exported;
}

const EditorState = load('document-builder:editor/state/editor-state');
const StableIdFactory = load('document-builder:editor/state/stable-id-factory');
const FlowStructure = load('document-builder:editor/flow/flow-structure');
const AddFlowNodeCommand = load('document-builder:editor/commands/add-flow-node');
const MoveFlowNodeCommand = load('document-builder:editor/commands/move-flow-node');
const RemoveFlowNodeCommand = load('document-builder:editor/commands/remove-flow-node');
const UpdateNodeCommand = load('document-builder:editor/commands/update-node');
const LayoutPrecheck = load('document-builder:editor/validation/layout-precheck');
const defaultLayout = JSON.parse(fs.readFileSync(
    path.join(extensionRoot, 'tests/fixtures/layout/phase-08-default.json'),
    'utf8',
));
const tokens = values => {
    const queue = [...values];
    return () => queue.shift();
};

const flow = new FlowStructure({maxNestingDepth: 4, maxElements: 5, maxSections: 3});
const state = new EditorState(
    defaultLayout,
    new StableIdFactory(tokens(['section', 'first', 'second', 'nested'])),
);
const addSection = new AddFlowNodeCommand(
    flow,
    'flow-section',
    {region: 'sections', parentId: null, index: null},
);
assert.equal(state.execute(addSection), true);
const sectionId = addSection.addedId;
assert.deepEqual(state.getLayout().capabilities, ['layout.flow']);
assert.equal(state.getLayout().sections[0].type, 'flow-section');

const addFirst = new AddFlowNodeCommand(flow, 'flow-container', {parentId: sectionId, index: null});
const addSecond = new AddFlowNodeCommand(flow, 'flow-container', {parentId: sectionId, index: null});
assert.equal(state.execute(addFirst), true);
assert.equal(state.execute(addSecond), true);
assert.deepEqual(
    state.getLayout().sections[0].children.map(node => node.id),
    [addFirst.addedId, addSecond.addedId],
);

const addNested = new AddFlowNodeCommand(
    flow,
    'flow-container',
    {parentId: addFirst.addedId, index: null},
);
assert.equal(state.execute(addNested), true);
assert.equal(state.getLayout().sections[0].children[0].children[0].id, addNested.addedId);
assert.throws(
    () => state.execute(new AddFlowNodeCommand(
        flow,
        'flow-section',
        {parentId: addFirst.addedId, index: null},
    )),
    /sections region/,
);
assert.throws(
    () => state.execute(new MoveFlowNodeCommand(
        flow,
        addFirst.addedId,
        {parentId: addNested.addedId, index: null},
    )),
    /own subtree/,
);

assert.equal(state.execute(new MoveFlowNodeCommand(
    flow,
    addNested.addedId,
    {parentId: addSecond.addedId, index: 0},
)), true);
assert.equal(state.getLayout().sections[0].children[1].children[0].id, addNested.addedId);
assert.equal(state.undo(), true);
assert.equal(state.getLayout().sections[0].children[0].children[0].id, addNested.addedId);
assert.equal(state.redo(), true);

assert.equal(state.execute(new UpdateNodeCommand(addSecond.addedId, {
    minHeight: {value: 25, unit: 'mm'},
    keepTogether: true,
})), true);
assert.equal(state.getLayout().sections[0].children[1].minHeight.value, 25);
assert.equal(state.getLayout().sections[0].children[1].keepTogether, true);

const serialized = JSON.stringify(state.getLayout());
const reloaded = new EditorState(JSON.parse(serialized));
assert.equal(reloaded.getLayout().sections[0].children.length, 2);
assert.equal(new LayoutPrecheck([], {
    maxNestingDepth: 4,
    maxElements: 5,
    maxSections: 3,
}).check(reloaded.getLayout()).valid, true);

const breadcrumbs = flow.breadcrumbs(state.getLayout(), addNested.addedId);
assert.deepEqual(breadcrumbs.map(item => item.id), [sectionId, addSecond.addedId, addNested.addedId]);
assert.equal(flow.flatten(state.getLayout(), addNested.addedId).find(row => row.selected).depth, 2);

const shallowFlow = new FlowStructure({maxNestingDepth: 2});
assert.throws(
    () => shallowFlow.assertTarget(
        state.getLayout(),
        shallowFlow.createNode('flow-container'),
        {parentId: addSecond.addedId, index: null},
    ),
    /nesting limit/,
);

const removeState = new EditorState(
    state.getLayout(),
    new StableIdFactory(tokens([])),
);
assert.equal(removeState.execute(new RemoveFlowNodeCommand(flow, sectionId)), true);
assert.deepEqual(removeState.getLayout().sections, []);
assert.deepEqual(removeState.getLayout().capabilities, []);
assert.equal(removeState.undo(), true);
assert.equal(removeState.getLayout().sections[0].id, sectionId);

console.log('Phase 19 flow add, move, nesting, styling, reload, breadcrumbs, and undo/redo tests passed.');
