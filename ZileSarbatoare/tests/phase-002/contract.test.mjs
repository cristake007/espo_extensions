import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const read = path => readFileSync(resolve(root, path), 'utf8');
const json = path => JSON.parse(read(path));

const integration = json('files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/integrations/NagerDate.json');
assert.equal(integration.allowUserAccounts, false);
assert.equal(integration.view, 'zile-sarbatoare:views/admin/integrations/nager-date');
assert.deepEqual(integration.fields.frequency.options, ['Daily', 'Weekly', 'Monthly', 'ManualOnly']);
assert.equal(integration.fields.lastAttemptedAt.readOnly, true);
assert.equal(integration.fields.nextRunAt.readOnly, true);

const jobs = json('files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/app/scheduledJobs.json');
assert.equal(jobs.SyncZileSarbatoare.isDefault, true);
assert.equal(jobs.SyncZileSarbatoare.scheduling, '*/5 * * * *');
assert.match(jobs.SyncZileSarbatoare.jobClassName, /SyncZileSarbatoare$/);

const controller = read('files/custom/Espo/Modules/ZileSarbatoare/Controllers/NagerDate.php');
assert.match(controller, /isAdmin\(\)/);
assert.match(controller, /postActionSynchronize/);
assert.match(controller, /postActionSaveSettings/);

const manager = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/SyncManager.php');
assert.match(manager, /frequency === 'ManualOnly'/);
assert.match(manager, /automaticSync/);
assert.doesNotMatch(manager, /https?:\/\//);

const view = read('files/client/custom/modules/zile-sarbatoare/src/views/admin/integrations/nager-date.js');
assert.match(view, /NagerDate\/action\/synchronize/);
assert.match(view, /NagerDate\/action\/saveSettings/);
assert.match(view, /frequency.*ManualOnly/s);

console.log('PHASE-002 contract tests passed.');
