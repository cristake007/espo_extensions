'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const cache = new Map(); let active;
function define(dependencies, factory) { active = {dependencies, factory}; }
function load(name) {
    if (cache.has(name)) return cache.get(name);
    active = null;
    const file = path.join(sourceRoot, `${name.replace(/^document-builder:/, '')}.js`);
    new Function('define', fs.readFileSync(file, 'utf8'))(define);
    const value = active.factory(...active.dependencies.map(load)); cache.set(name, value); return value;
}
const EntityCatalogueApi = load('document-builder:services/entity-catalogue-api');
const UpdateDataSourceCommand = load('document-builder:editor/commands/update-data-source');
const EditorState = load('document-builder:editor/state/editor-state');
const DraftSaveCoordinator = load('document-builder:editor/save/draft-save-coordinator');
const layout = JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/layout/phase-08-default.json')));
(async () => {
    const api = new EntityCatalogueApi({getRequest: async () => ({list: [
        {entityType: 'Account', label: 'Cont', custom: false},
        {entityType: 'CustomRecord', label: 'Înregistrare proprie', custom: true},
    ]})});
    assert.deepEqual((await api.get()).map(item => item.entityType), ['Account', 'CustomRecord']);
    await assert.rejects(
        () => new EntityCatalogueApi({getRequest: async () => ({list: [
            {entityType: '../Contact', label: 'Bad', custom: false},
        ]})}).get(),
        /invalid item/,
    );

    const state = new EditorState(layout);
    assert.equal(state.execute(new UpdateDataSourceCommand({
        type: 'entity', entityType: 'Account', relationshipDepth: 2,
    })), true);
    assert.equal(state.getLayout().dataSource.entityType, 'Account');
    assert.equal(state.undo(), true);
    assert.deepEqual(state.getLayout().dataSource, {type: 'none'});
    assert.throws(() => new UpdateDataSourceCommand({
        type: 'entity', entityType: '../Contact', relationshipDepth: 2,
    }), /invalid/);

    let payload;
    const coordinator = new DraftSaveCoordinator({
        editorState: state,
        draftApi: {save: async (...args) => {
            payload = args;
            return {revision: 1, layout: state.getLayout()};
        }, getRevisionConflict: () => null, getErrorMessage: () => 'failed'},
        precheck: {check: () => ({valid: true, errors: []})},
        templateId: 'template', revision: 0,
    });
    state.execute(new UpdateDataSourceCommand({type: 'entity', entityType: 'Contact', relationshipDepth: 2}));
    coordinator.confirmSourceChange();
    assert.equal((await coordinator.save()).status, 'saved');
    assert.equal(payload[3], true);
    assert.equal(coordinator.sourceChangeConfirmed, false);
    console.log('Phase 24 client catalogue validation, source history, and confirmed save tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
