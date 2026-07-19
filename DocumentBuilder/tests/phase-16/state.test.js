'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientSourceRoot = path.resolve(
    __dirname,
    '../../files/client/custom/modules/document-builder/src',
);
const cache = new Map();
let activeDefinition = null;

function define(dependencies, factory) {
    activeDefinition = {dependencies, factory};
}

function load(moduleName) {
    if (cache.has(moduleName)) {
        return cache.get(moduleName);
    }

    const relativeName = moduleName.replace(/^document-builder:/, '');
    const filename = path.join(clientSourceRoot, `${relativeName}.js`);
    const source = fs.readFileSync(filename, 'utf8');

    activeDefinition = null;
    new Function('define', `${source}\n//# sourceURL=${filename}`)(define);

    if (!activeDefinition) {
        throw new Error(`AMD module ${moduleName} did not call define.`);
    }

    const definition = activeDefinition;
    const dependencies = definition.dependencies.map(load);
    const exported = definition.factory(...dependencies);

    cache.set(moduleName, exported);

    return exported;
}

const StableIdFactory = load('document-builder:editor/state/stable-id-factory');
const EditorState = load('document-builder:editor/state/editor-state');
const AddNodeCommand = load('document-builder:editor/commands/add-node');
const RemoveNodeCommand = load('document-builder:editor/commands/remove-node');
const MoveNodeCommand = load('document-builder:editor/commands/move-node');
const UpdateNodeCommand = load('document-builder:editor/commands/update-node');
const DuplicateNodeCommand = load('document-builder:editor/commands/duplicate-node');

const toHost = value => JSON.parse(JSON.stringify(value));
const node = (id, value, children = undefined) => {
    const result = {id, type: 'fixture', value};

    if (children) {
        result.children = children;
    }

    return result;
};
const layout = sections => ({
    schemaVersion: 1,
    capabilities: [],
    document: {},
    dataSource: {type: 'none'},
    header: [],
    sections,
    footer: [],
});
const tokens = values => {
    const queue = [...values];

    return () => {
        if (!queue.length) {
            throw new Error('The test token queue is empty.');
        }

        return queue.shift();
    };
};

{
    const original = layout([node('fixture_original', 1)]);
    const state = new EditorState(original, new StableIdFactory(tokens([])));

    original.sections[0].value = 99;
    assert.equal(state.getLayout().sections[0].value, 1, 'Input mutation leaked into editor state.');

    const exposed = state.getLayout();
    exposed.sections[0].value = 88;
    assert.equal(state.getLayout().sections[0].value, 1, 'External mutation bypassed editor commands.');
    assert.equal(state.isDirty(), false, 'A newly loaded layout must match its saved baseline.');
}

{
    const idFactory = new StableIdFactory(tokens(['parenttoken', 'childtoken']));
    const state = new EditorState(layout([]), idFactory);
    const command = new AddNodeCommand(
        {type: 'fixture', children: [{type: 'fixture'}]},
        {region: 'sections', parentId: null, index: 0},
    );

    assert.equal(state.execute(command), true);
    const added = state.getLayout().sections[0];
    assert.equal(added.id, 'fixture_parenttoken');
    assert.equal(added.children[0].id, 'fixture_childtoken');
    assert.equal(command.addedId, added.id);
    assert.equal(state.isDirty(), true);
    assert.equal(state.undo(), true);
    assert.deepEqual(toHost(state.getLayout().sections), []);
    assert.equal(state.isDirty(), false, 'Undoing to the baseline must clear dirty state.');
    assert.equal(state.redo(), true);
    assert.equal(state.getLayout().sections[0].id, added.id, 'Redo regenerated a stable ID.');
    assert.throws(() => state.execute(command), /only be executed once/);
}

{
    const child = node('fixture_child', 2);
    const state = new EditorState(
        layout([node('fixture_parent', 1, [child]), node('fixture_sibling', 3)]),
        new StableIdFactory(tokens([])),
    );

    assert.equal(state.select('fixture_child'), true);
    assert.equal(state.getSelectedId(), 'fixture_child');
    assert.equal(state.select('fixture_sibling'), true, 'Single selection did not replace the previous node.');
    assert.equal(state.getSelectedId(), 'fixture_sibling');
    state.select('fixture_child');
    assert.equal(state.execute(new RemoveNodeCommand('fixture_parent')), true);
    assert.equal(state.getSelectedId(), null, 'Removing a selected subtree did not clean selection.');
    assert.equal(state.undo(), true);
    assert.equal(state.getLayout().sections[0].children[0].id, 'fixture_child');
    assert.equal(state.getSelectedId(), null, 'Undo must not invent a previous UI selection.');
    assert.equal(state.select('missing'), false);
}

{
    const state = new EditorState(
        layout([node('fixture_a', 1), node('fixture_b', 2), node('fixture_c', 3)]),
        new StableIdFactory(tokens([])),
    );

    assert.equal(state.execute(new MoveNodeCommand('fixture_a', {
        region: 'sections',
        parentId: null,
        index: 2,
    })), true);
    assert.deepEqual(
        toHost(state.getLayout().sections.map(item => item.id)),
        ['fixture_b', 'fixture_c', 'fixture_a'],
    );
    state.undo();
    assert.deepEqual(
        toHost(state.getLayout().sections.map(item => item.id)),
        ['fixture_a', 'fixture_b', 'fixture_c'],
    );
}

{
    const state = new EditorState(
        layout([node('fixture_parent', 1, [node('fixture_child', 2)])]),
        new StableIdFactory(tokens([])),
    );

    assert.throws(
        () => state.execute(new MoveNodeCommand('fixture_parent', {
            parentId: 'fixture_child',
            index: 0,
        })),
        /own subtree/,
    );
    assert.equal(state.getLayout().sections[0].id, 'fixture_parent');
}

{
    const state = new EditorState(
        layout([node('fixture_source', 1, [node('fixture_source_child', 2)])]),
        new StableIdFactory(tokens(['copytoken', 'copychildtoken'])),
    );
    const command = new DuplicateNodeCommand('fixture_source');

    assert.equal(state.execute(command), true);
    const duplicate = state.getLayout().sections[1];
    assert.equal(duplicate.id, 'fixture_copytoken');
    assert.equal(duplicate.children[0].id, 'fixture_copychildtoken');
    assert.notEqual(duplicate.id, 'fixture_source');
    assert.equal(command.duplicateId, duplicate.id);
    assert.equal(state.undo(), true);
    assert.equal(state.getLayout().sections.length, 1);
    assert.equal(state.redo(), true);
    assert.equal(
        state.getLayout().sections[1].id,
        duplicate.id,
        'Redo regenerated a duplicated subtree ID.',
    );
}

{
    const state = new EditorState(
        layout([node('fixture_update', 0)]),
        new StableIdFactory(tokens([])),
    );

    assert.equal(state.execute(new UpdateNodeCommand('fixture_update', {value: 1})), true);
    state.markSaved();
    assert.equal(state.isDirty(), false);
    assert.equal(state.execute(new UpdateNodeCommand('fixture_update', {value: 2})), true);
    assert.equal(state.isDirty(), true);
    assert.equal(state.undo(), true);
    assert.equal(state.isDirty(), false);
    assert.equal(state.execute(new UpdateNodeCommand('fixture_update', {value: 1})), false);
    assert.equal(state.canRedo(), true, 'A no-op command invalidated redo history.');
    assert.equal(state.execute(new UpdateNodeCommand('fixture_update', {value: 3})), true);
    assert.equal(state.canRedo(), false, 'A new edit did not invalidate redo history.');
    assert.throws(
        () => new UpdateNodeCommand('fixture_update', {id: 'fixture_changed'}),
        /structural commands/,
    );
    assert.throws(
        () => new UpdateNodeCommand('fixture_update', null),
        /property object/,
    );
}

{
    const state = new EditorState(
        layout([node('fixture_taken', 0)]),
        new StableIdFactory(tokens(['taken', 'fresh'])),
    );
    const command = new AddNodeCommand(
        {type: 'fixture'},
        {region: 'sections', parentId: null, index: null},
    );

    state.execute(command);
    assert.equal(command.addedId, 'fixture_fresh', 'Stable-ID allocation did not retry a collision.');
}

{
    const state = new EditorState(
        layout([node('fixture_history', 0)]),
        new StableIdFactory(tokens([])),
    );

    for (let value = 1; value <= 105; value++) {
        state.execute(new UpdateNodeCommand('fixture_history', {value}));
    }

    let undoCount = 0;

    while (state.undo()) {
        undoCount++;
    }

    assert.equal(undoCount, 100, 'Command history did not enforce its 100-state limit.');
    assert.equal(state.getLayout().sections[0].value, 5);
}

{
    const state = new EditorState(layout([]), new StableIdFactory(tokens([])));

    assert.equal(state.execute(new RemoveNodeCommand('missing')), false);
    assert.equal(state.canUndo(), false, 'A no-op command created a history entry.');
    assert.throws(() => state.execute({apply() {}}), /only executes command objects/);
    assert.throws(
        () => new EditorState(
            layout([node('fixture_duplicate', 1), node('fixture_duplicate', 2)]),
            new StableIdFactory(tokens([])),
        ),
        /Duplicate layout node ID/,
    );
}

console.log('Phase 16 editor state and command unit tests passed.');
