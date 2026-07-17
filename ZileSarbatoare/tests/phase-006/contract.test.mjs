import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const read = path => readFileSync(resolve(root, path), 'utf8');

const manifest = JSON.parse(read('manifest.json'));
assert.equal(manifest.version, '0.7.0');

const uninstall = read('scripts/BeforeUninstall.php');
assert.match(uninstall, /getRDBRepositoryByClass\(ScheduledJob::class\)/);
assert.match(uninstall, /where\(\['job' => self::SCHEDULED_JOB\]\)/);
assert.match(uninstall, /removeEntity\(\$scheduledJob\)/);
assert.doesNotMatch(uninstall, /getRDBRepository(?:ByClass)?\([^\n]*ZileLibere/);
assert.doesNotMatch(uninstall, /DELETE\s+FROM|DROP\s+TABLE/i);

const jobs = JSON.parse(read(
    'files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/app/scheduledJobs.json',
));
assert.deepEqual(Object.keys(jobs), ['SyncZileSarbatoare']);
assert.equal(jobs.SyncZileSarbatoare.isDefault, true);

const readme = read('README.md');
assert.match(readme, /build\.sh --extension ZileSarbatoare --zip 0\.7\.0 files scripts/);
assert.match(readme, /bin\/command rebuild/);
assert.match(readme, /populate-scheduled-jobs/);
assert.match(readme, /cron or daemon/i);
assert.match(readme, /Synchronize now/);
assert.match(readme, /does not delete `ZileLibere` records/);

console.log('PHASE-006 release and operational contracts passed.');
