import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const read = path => readFileSync(resolve(root, path), 'utf8');

const binding = read('files/custom/Espo/Modules/ZileSarbatoare/Binding.php');
assert.match(binding, /bindImplementation\(ZileLibereCalendar::class, ZileLibereCalendarService::class\)/);
assert.match(binding, /bindImplementation\(ZileLibereRepository::class, EspoZileLibereRepository::class\)/);

const calendar = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereCalendar.php');
assert.match(calendar, /interface ZileLibereCalendar/);
assert.match(calendar, /getZileLiberePentruLuni/);
assert.match(calendar, /getDateLiberePentruLuni/);
assert.match(calendar, /esteZiLibera/);

const data = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereData.php');
assert.match(data, /final readonly class ZileLibereData/);

const repository = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/EspoZileLibereRepository.php');
assert.match(repository, /'countryCode' => \$countryCode/);
assert.match(repository, /'dateStart>=' => \$dateFrom/);
assert.match(repository, /'dateStart<' => \$dateUntil/);
assert.equal((repository.match(/->find\(\)/g) ?? []).length, 1);

const service = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereCalendarService.php');
assert.doesNotMatch(service, /NagerDate|Http|Integration|Settings/);
assert.match(service, /isset\(\$selectedMonths/);
assert.match(service, /usort\(/);

const entityDefs = JSON.parse(read(
    'files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/entityDefs/ZileLibere.json',
));
assert.deepEqual(
    entityDefs.indexes.countryDate.columns,
    ['countryCode', 'dateStart', 'deleted'],
    'The month query must remain covered by the country/date/deleted index.',
);

console.log('PHASE-005 public month service contracts passed.');
