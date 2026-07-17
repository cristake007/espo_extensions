import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..', '..');
const moduleRoot = path.join(
    extensionRoot,
    'files', 'custom', 'Espo', 'Modules', 'ZileSarbatoare'
);

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(moduleRoot, ...segments), 'utf8'));
}

test('holiday date and source year use full ungrouped standard formatting', async () => {
    const defs = await readJson('Resources', 'metadata', 'entityDefs', 'ZileLibere.json');

    assert.equal(defs.fields.dateStart.type, 'date');
    assert.equal(defs.fields.dateStart.useNumericFormat, true);
    assert.equal(defs.fields.sourceYear.type, 'int');
    assert.equal(defs.fields.sourceYear.disableFormatting, true);
    assert.equal(defs.fields.sourceYear.readOnly, true);
});

test('presentation flags preserve synchronization and query indexes', async () => {
    const defs = await readJson('Resources', 'metadata', 'entityDefs', 'ZileLibere.json');

    assert.deepEqual(
        defs.indexes.dateStart.columns,
        ['dateStart', 'deleted']
    );
    assert.deepEqual(
        defs.indexes.countryDate.columns,
        ['countryCode', 'dateStart', 'deleted']
    );
    assert.deepEqual(
        defs.indexes.managedSyncScope.columns,
        ['source', 'managed', 'countryCode', 'sourceYear', 'deleted']
    );
});

test('custom detail modal delegates value rendering to the native detail modal', async () => {
    const source = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'zile-sarbatoare',
        'src', 'views', 'modals', 'zile-libere-detail.js'
    ), 'utf8');

    assert.match(source, /^define\(\['views\/modals\/detail'\]/);
    assert.match(source, /class extends DetailModalView/);
    assert.match(source, /super\.setup\(\)/);
    assert.doesNotMatch(source, /model\.(?:set|unset|clear)\s*\(/);
    assert.doesNotMatch(source, /setAttribute\s*\(/);
});
