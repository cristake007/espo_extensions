'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const extensionRoot = path.resolve(__dirname, '../..');
const clientSourceRoot = path.join(
    extensionRoot,
    'files/client/custom/modules/document-builder/src',
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
    const exported = definition.factory(...definition.dependencies.map(load));

    cache.set(moduleName, exported);

    return exported;
}

const StableIdFactory = load('document-builder:editor/state/stable-id-factory');
const EditorState = load('document-builder:editor/state/editor-state');
const UpdateNodeCommand = load('document-builder:editor/commands/update-node');
const LayoutPrecheck = load('document-builder:editor/validation/layout-precheck');
const DraftApi = load('document-builder:services/draft-api');
const DraftSaveCoordinator = load('document-builder:editor/save/draft-save-coordinator');
const Keyboard = load('document-builder:editor/save/keyboard');
const DirtyGuard = load('document-builder:editor/save/dirty-guard');

const defaultLayout = JSON.parse(fs.readFileSync(
    path.join(extensionRoot, 'tests/fixtures/layout/phase-08-default.json'),
    'utf8',
));
const tokens = values => {
    const queue = [...values];

    return () => queue.shift();
};
const editableLayout = value => ({
    ...defaultLayout,
    sections: [{id: 'fixture_editable', type: 'fixture', value}],
});
const createDirtyState = () => {
    const state = new EditorState(
        editableLayout(0),
        new StableIdFactory(tokens([])),
    );

    state.execute(new UpdateNodeCommand('fixture_editable', {value: 1}));

    return state;
};
const validPrecheck = {check: () => ({valid: true, errors: []})};
const cloneLayout = layout => JSON.parse(JSON.stringify(layout));

(async () => {
    {
        const precheck = new LayoutPrecheck();

        assert.equal(precheck.check(defaultLayout).valid, true);
        assert.equal(precheck.check({...defaultLayout, schemaVersion: 2}).valid, false);
        assert.equal(precheck.check(editableLayout(1)).valid, false, 'Unsupported nodes passed client precheck.');
        assert.equal(precheck.check({...defaultLayout, unexpected: true}).valid, false);

        const invalidSize = cloneLayout(defaultLayout);
        invalidSize.document.page.size = 'Tabloid';
        assert.deepEqual(precheck.check(invalidSize).errors, ['document.page.size']);

        const invalidOrientation = cloneLayout(defaultLayout);
        invalidOrientation.document.page.orientation = 'upside-down';
        assert.deepEqual(precheck.check(invalidOrientation).errors, ['document.page.orientation']);

        const invalidMargin = cloneLayout(defaultLayout);
        invalidMargin.document.page.margins.left = {value: -1, unit: 'mm'};
        assert.deepEqual(precheck.check(invalidMargin).errors, ['document.page.margins.left']);

        const noPrintableWidth = cloneLayout(defaultLayout);
        noPrintableWidth.document.page.margins.left.value = 105;
        noPrintableWidth.document.page.margins.right.value = 105;
        assert.deepEqual(precheck.check(noPrintableWidth).errors, ['document.page.printableWidth']);

        const noLandscapePrintableHeight = cloneLayout(defaultLayout);
        noLandscapePrintableHeight.document.page.orientation = 'landscape';
        noLandscapePrintableHeight.document.page.margins.top.value = 105;
        noLandscapePrintableHeight.document.page.margins.bottom.value = 105;
        assert.deepEqual(
            precheck.check(noLandscapePrintableHeight).errors,
            ['document.page.printableHeight'],
        );

        const invalidDefaults = cloneLayout(defaultLayout);
        invalidDefaults.document.defaults = {
            fontFamily: '',
            fontSize: {value: 513, unit: 'pt'},
            color: 'red',
            lineHeight: 0.25,
            locale: 'english',
        };
        assert.deepEqual(precheck.check(invalidDefaults).errors, [
            'document.defaults.fontFamily',
            'document.defaults.fontSize',
            'document.defaults.color',
            'document.defaults.lineHeight',
            'document.defaults.locale',
        ]);

        const entitySource = cloneLayout(defaultLayout);
        entitySource.dataSource = {
            type: 'entity',
            entityType: 'Contact',
            relationshipDepth: 2,
        };
        assert.equal(precheck.check(entitySource).valid, true);

        const incompleteEntitySource = cloneLayout(defaultLayout);
        incompleteEntitySource.dataSource = {type: 'entity', entityType: 'Contact'};
        assert.deepEqual(
            precheck.check(incompleteEntitySource).errors,
            ['dataSource.entity.structure'],
        );

        const spreadsheetSource = cloneLayout(defaultLayout);
        spreadsheetSource.dataSource = {
            type: 'spreadsheet',
            format: 'xlsx',
            worksheet: 'Participants',
        };
        assert.equal(precheck.check(spreadsheetSource).valid, true);

        const incompleteSpreadsheetSource = cloneLayout(defaultLayout);
        incompleteSpreadsheetSource.dataSource = {type: 'spreadsheet'};
        assert.deepEqual(
            precheck.check(incompleteSpreadsheetSource).errors,
            ['dataSource.spreadsheet.structure'],
        );

        const invalidCsvSource = cloneLayout(defaultLayout);
        invalidCsvSource.dataSource = {
            type: 'spreadsheet',
            format: 'csv',
            worksheet: 'Sheet 1',
        };
        assert.deepEqual(
            precheck.check(invalidCsvSource).errors,
            ['dataSource.spreadsheet.worksheet'],
        );
    }

    {
        const calls = [];
        const api = new DraftApi({
            putRequest(url, payload) {
                calls.push({url, payload});

                return Promise.resolve({revision: 4, layout: defaultLayout});
            },
        });

        await api.save('template-1', defaultLayout, 3);
        assert.equal(calls.length, 1);
        assert.equal(calls[0].url, 'DocumentBuilder/template/template-1/draft');
        assert.equal(calls[0].payload.expectedRevision, 3);
        assert.equal(calls[0].payload.layout, JSON.stringify(defaultLayout));
        assert.equal(calls[0].payload.confirmSourceChange, false);
        assert.equal(calls[0].payload.changeNote, null);
        assert.deepEqual(
            api.getRevisionConflict({
                status: 409,
                responseJSON: {expectedRevision: 3, actualRevision: 4},
            }),
            {expectedRevision: 3, actualRevision: 4},
        );
        assert.equal(api.getRevisionConflict({status: 409, responseJSON: {}}), null);
        assert.equal(api.getErrorMessage({status: 403}), 'editorSaveAccessDenied');
        assert.equal(api.getErrorMessage({status: 400}), 'editorServerValidationFailed');
        assert.equal(api.getErrorMessage({status: 500}), 'editorSaveFailed');
    }

    {
        const state = createDirtyState();
        const requests = [];
        const api = new DraftApi({
            putRequest(url, payload) {
                requests.push(payload);

                return Promise.resolve({
                    revision: 2,
                    layout: editableLayout(1),
                    changeNote: null,
                });
            },
        });
        const coordinator = new DraftSaveCoordinator({
            editorState: state,
            draftApi: api,
            precheck: validPrecheck,
            templateId: 'template-success',
            revision: 1,
        });
        const outcome = await coordinator.save();

        assert.equal(outcome.status, 'saved');
        assert.equal(requests[0].expectedRevision, 1);
        assert.equal(coordinator.revision, 2);
        assert.equal(coordinator.status, 'saved');
        assert.equal(state.isDirty(), false, 'Successful save did not reset the baseline.');
        assert.equal(state.canUndo(), true, 'Successful save discarded the current session history.');
    }

    {
        const state = createDirtyState();
        let requestCount = 0;
        const api = new DraftApi({
            putRequest() {
                requestCount++;

                return Promise.reject({status: 500});
            },
        });
        const coordinator = new DraftSaveCoordinator({
            editorState: state,
            draftApi: api,
            precheck: validPrecheck,
            templateId: 'template-failure',
            revision: 1,
        });
        const outcome = await coordinator.save();

        assert.equal(outcome.status, 'error');
        assert.equal(requestCount, 1);
        assert.equal(coordinator.errorMessage, 'editorSaveFailed');
        assert.equal(state.isDirty(), true, 'A failed save cleared local dirty state.');
    }

    {
        const state = createDirtyState();
        const coordinator = new DraftSaveCoordinator({
            editorState: state,
            draftApi: new DraftApi({
                putRequest: () => Promise.resolve({revision: 2, layout: {}}),
            }),
            precheck: validPrecheck,
            templateId: 'template-invalid-response',
            revision: 1,
        });
        const outcome = await coordinator.save();

        assert.equal(outcome.status, 'error');
        assert.equal(coordinator.errorMessage, 'editorInvalidSaveResponse');
        assert.equal(coordinator.revision, 1);
        assert.equal(state.isDirty(), true, 'An invalid server response cleared local changes.');
    }

    {
        const state = createDirtyState();
        const revisions = [];
        let attempt = 0;
        const api = new DraftApi({
            putRequest(url, payload) {
                revisions.push(payload.expectedRevision);
                attempt++;

                if (attempt === 1) {
                    return Promise.reject({
                        status: 409,
                        responseJSON: {expectedRevision: 1, actualRevision: 5},
                    });
                }

                return Promise.resolve({
                    revision: 6,
                    layout: editableLayout(1),
                    changeNote: null,
                });
            },
        });
        const coordinator = new DraftSaveCoordinator({
            editorState: state,
            draftApi: api,
            precheck: validPrecheck,
            templateId: 'template-conflict',
            revision: 1,
        });
        const conflictOutcome = await coordinator.save();

        assert.equal(conflictOutcome.status, 'conflict');
        assert.deepEqual(revisions, [1], 'A revision conflict retried without user action.');
        assert.equal(state.isDirty(), true, 'A revision conflict discarded local changes.');
        assert.equal(coordinator.revision, 1, 'A conflict silently advanced the client revision.');

        const retryOutcome = await coordinator.retryConflict(conflictOutcome.conflict);
        assert.equal(retryOutcome.status, 'saved');
        assert.deepEqual(revisions, [1, 5], 'Explicit retry did not use the server revision.');
        assert.equal(coordinator.revision, 6);
    }

    {
        const state = createDirtyState();
        let requestCount = 0;
        const coordinator = new DraftSaveCoordinator({
            editorState: state,
            draftApi: new DraftApi({
                putRequest() {
                    requestCount++;

                    return Promise.resolve({revision: 2, layout: editableLayout(1)});
                },
            }),
            precheck: {check: () => ({valid: false, errors: ['fixture.invalid']})},
            templateId: 'template-invalid',
            revision: 1,
        });
        const outcome = await coordinator.save();

        assert.equal(outcome.status, 'error');
        assert.equal(requestCount, 0, 'Invalid layout reached the server.');
        assert.equal(coordinator.errorMessage, 'editorClientValidationFailed');
    }

    {
        const state = createDirtyState();
        const coordinator = new DraftSaveCoordinator({
            editorState: state,
            draftApi: new DraftApi({putRequest: () => Promise.reject({status: 409})}),
            precheck: validPrecheck,
            templateId: 'template-reload',
            revision: 1,
        });

        coordinator.acceptReload(defaultLayout, 8);
        assert.equal(coordinator.revision, 8);
        assert.equal(state.isDirty(), false);
        assert.equal(state.canUndo(), false, 'Explicit reload retained history from the discarded local draft.');
    }

    {
        let resolveRequest;
        const pending = new Promise(resolve => {
            resolveRequest = resolve;
        });
        const state = createDirtyState();
        const coordinator = new DraftSaveCoordinator({
            editorState: state,
            draftApi: new DraftApi({putRequest: () => pending}),
            precheck: validPrecheck,
            templateId: 'template-busy',
            revision: 1,
        });
        const first = coordinator.save();
        const second = await coordinator.save();

        assert.equal(second.status, 'busy', 'Concurrent manual saves were not suppressed.');
        resolveRequest({revision: 2, layout: editableLayout(1)});
        await first;
    }

    {
        assert.equal(Keyboard.isManualSave({key: 's', ctrlKey: true}), true);
        assert.equal(Keyboard.isManualSave({key: 'S', metaKey: true}), true);
        assert.equal(Keyboard.isManualSave({key: 's', ctrlKey: true, altKey: true}), false);
        assert.equal(Keyboard.isManualSave({key: 'x', ctrlKey: true}), false);
    }

    {
        const calls = [];
        const owner = {};
        const guard = new DirtyGuard({
            addLeaveOutObject(value) { calls.push(['add', value]); },
            removeLeaveOutObject(value) { calls.push(['remove', value]); },
        }, owner);

        guard.sync(true);
        guard.sync(true);
        guard.sync(false);
        guard.sync(false);
        guard.sync(true);
        guard.dispose();
        assert.deepEqual(calls, [
            ['add', owner],
            ['remove', owner],
            ['add', owner],
            ['remove', owner],
        ]);
    }

    console.log('Phase 17 manual save, conflict, shortcut, and dirty-guard unit tests passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
