import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const read = path => readFileSync(resolve(root, path), 'utf8');

const manifest = JSON.parse(read('manifest.json'));
assert.equal(manifest.version, '0.7.3');

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
assert.match(readme, /build\.sh --extension ZileSarbatoare --zip 0\.7\.3 files scripts/);
assert.match(readme, /bin\/command rebuild/);
assert.match(readme, /populate-scheduled-jobs/);
assert.match(readme, /cron or daemon/i);
assert.match(readme, /Synchronize now/);
assert.match(readme, /does not delete `ZileLibere` records/);

for (const path of [
    'files/client/custom/modules/zile-sarbatoare/src/acl/zile-libere.js',
    'files/client/custom/modules/zile-sarbatoare/src/views/admin/integrations/nager-date.js',
]) {
    const clientModule = read(path);

    assert.match(clientModule, /^define\(\[/);
    assert.doesNotMatch(clientModule, /^\s*import\s/m);
    assert.doesNotMatch(clientModule, /^\s*export\s/m);
}

const integrationView = read(
    'files/client/custom/modules/zile-sarbatoare/src/views/admin/integrations/nager-date.js',
);
assert.match(integrationView, /Espo\.Ui\.notify\(result\.message, 'info', 8000/);
assert.ok(
    integrationView.indexOf('Espo.Ui.notify(false)') <
        integrationView.indexOf("Espo.Ui.notify(result.message, 'info', 8000"),
    'The progress notification must close before the persistent result is shown.',
);

console.log('PHASE-006 release and operational contracts passed.');
