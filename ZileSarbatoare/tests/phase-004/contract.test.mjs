import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const read = path => readFileSync(resolve(root, path), 'utf8');

const store = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/EspoHolidayStore.php');
assert.match(store, /getTransactionManager\(\)->run\(\$operation\)/);
assert.match(store, /->forUpdate\(\)/);
assert.match(store, /'source' => ZileLibere::SOURCE_NAGER_DATE/);
assert.match(store, /'managed' => true/);
assert.match(store, /'countryCode' => \$countryCode/);
assert.match(store, /'sourceYear' => \$years/);
assert.match(store, /'dateStart' => \$data->date/);
assert.match(store, /'source' => ZileLibere::SOURCE_NAGER_DATE/);
assert.match(store, /'syncedAt' => \$data->syncedAt/);

const runner = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/SynchronizationRunner.php');
assert.ok(runner.indexOf('holidayProvider->fetch') < runner.indexOf('reconciler->reconcile'));

const manager = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/SyncManager.php');
assert.ok(manager.indexOf('syncLock->acquire') < manager.indexOf('settingsProvider->get'));
assert.match(manager, /recordSuccess/);
assert.match(manager, /recordFailureBestEffort/);
assert.match(manager, /ZileSarbatoare synchronization completed/);
assert.doesNotMatch(manager, /response->body|payload/);

const lock = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/IntegrationSyncLock.php');
assert.match(lock, /lockExclusive\(Integration::ENTITY_TYPE\)/);
assert.match(lock, /random_bytes\(16\)/);
assert.match(lock, /syncLockTokenHash/);
assert.match(lock, /hash\('sha256', \$token\)/);
assert.match(lock, /tokenOwnsLock/);

const scope = JSON.parse(read('files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/scopes/ZileLibere.json'));
assert.equal(scope.calendar, true);
assert.equal(scope.calendarOneDay, true);

console.log('PHASE-004 EspoCRM reconciliation contracts passed.');
