import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..', '..');
const moduleRoot = path.join(
    extensionRoot,
    'files', 'custom', 'Espo', 'Modules', 'ZileSarbatoare'
);

async function readModuleFile(...segments) {
    return readFile(path.join(moduleRoot, ...segments), 'utf8');
}

test('module registers the administrator holiday lookup route', async () => {
    const routes = JSON.parse(await readModuleFile('Resources', 'routes.json'));

    assert.deepEqual(routes, [
        {
            route: '/ZileLibere/availableDates',
            method: 'post',
            actionClassName:
                'Espo\\Modules\\ZileSarbatoare\\Tools\\ZileLibere\\Api\\PostAvailableDates',
        },
    ]);
});

test('holiday lookup action uses only the stored calendar service boundary', async () => {
    const source = await readModuleFile(
        'Tools', 'ZileLibere', 'Api', 'PostAvailableDates.php'
    );

    assert.match(source, /implements Action/);
    assert.match(source, /Request/);
    assert.match(source, /ResponseComposer::json/);
    assert.match(source, /User/);
    assert.match(source, /->isAdmin\(\)/);
    assert.match(source, /throw new Forbidden\(/);
    assert.match(source, /ZileLibereCalendar/);
    assert.match(source, /->getZileLiberePentruLuni\(/);
    assert.match(source, /['"]RO['"]/);
    assert.match(source, /['"]dates['"]/);
    assert.match(source, /['"]holidays['"]/);

    assert.doesNotMatch(source, /countryCode/);
    assert.doesNotMatch(source, /NagerDate|Synchroniz|Settings/i);
    assert.doesNotMatch(source, /Exception->getMessage|->getTrace|traceAsString/i);
});
