import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..', '..');

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(extensionRoot, ...segments), 'utf8'));
}

test('package scaffold follows the mandatory naming and runtime contract', async () => {
    const manifest = await readJson('manifest.json');
    const module = await readJson(
        'files', 'custom', 'Espo', 'Modules', 'ZileSarbatoare', 'Resources', 'module.json'
    );

    assert.equal(manifest.name, 'Zile Sărbătoare');
    assert.deepEqual(manifest.acceptableVersions, ['>=10.0.0']);
    assert.deepEqual(manifest.php, ['>=8.4']);
    assert.equal(module.order, 100);
    assert.equal(module.jsTranspiled, false);
});

test('captured Nager.Date fixture covers nullable arrays and duplicate dates', async () => {
    const rows = await readJson('tests', 'fixtures', 'nager-date', 'ro-2026.json');

    assert.equal(rows.length, 17);
    assert.ok(rows.every(row => row.countryCode === 'RO'));
    assert.ok(rows.every(row => row.global === true));
    assert.ok(rows.every(row => row.counties === null));
    assert.ok(rows.every(row => row.types.includes('Public')));
    assert.ok(rows.every(row => row.localName !== row.name));
    assert.equal(rows.filter(row => row.date === '2026-06-01').length, 2);
});

test('Phase 0 records the global calendar visibility seam and pending runtime proof', async () => {
    const contracts = await readFile(
        path.join(extensionRoot, 'docs', 'phase-0-runtime-contracts.md'),
        'utf8'
    );

    assert.match(contracts, /getCalenderQuery/);
    assert.match(contracts, /withStrictAccessControl\(\)/);
    assert.match(contracts, /zile_libere/);
    assert.match(contracts, /runtime proof pending/i);
});
