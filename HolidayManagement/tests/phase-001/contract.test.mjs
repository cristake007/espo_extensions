import assert from 'node:assert/strict';
import {readFile, readdir} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..', '..');
const moduleRoot = path.join(
    extensionRoot,
    'files',
    'custom',
    'Espo',
    'Modules',
    'HolidayManagement'
);

const settingDefaults = {
    holidayManagementAnnualEntitlementDays: null,
    holidayManagementResetDate: null,
    holidayManagementCarryOverLimitDays: 90,
    holidayManagementNegativeBalanceLimitDays: -21,
    holidayManagementApprovalBlock1Title: '',
    holidayManagementApprovalBlock1Name: '',
    holidayManagementApprovalBlock2Title: '',
    holidayManagementApprovalBlock2Name: '',
};

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(extensionRoot, ...segments), 'utf8'));
}

test('manifest targets EspoCRM 10 and packages the Holiday Management module', async () => {
    const manifest = await readJson('manifest.json');

    assert.equal(manifest.name, 'Holiday Management');
    assert.match(manifest.version, /^1\.\d+\.\d+$/);
    assert.deepEqual(manifest.acceptableVersions, ['>=10.0.0']);
    assert.deepEqual(manifest.php, ['>=8.4']);
});

test('settings metadata exposes every phase-001 setting with stable defaults', async () => {
    const metadata = await readJson(
        'files', 'custom', 'Espo', 'Modules', 'HolidayManagement',
        'Resources', 'metadata', 'entityDefs', 'Settings.json'
    );

    for (const [name, value] of Object.entries(settingDefaults)) {
        assert.ok(metadata.fields[name], `missing Settings field ${name}`);
        assert.equal(metadata.fields[name].default, value, `wrong default for ${name}`);
    }

    assert.deepEqual(metadata.fields.holidayManagementApprovers, {
        type: 'linkMultiple',
        entity: 'User',
        required: true,
        tooltip: true,
        validatorClassNameList: [
            'Espo\\Modules\\HolidayManagement\\FieldValidators\\Settings\\Approvers\\Valid',
        ],
    });
    assert.equal(metadata.fields.holidayManagementApproverRole, undefined);
    assert.equal(metadata.fields.holidayManagementResetCeilingDays, undefined);
    assert.equal(metadata.fields.holidayManagementResetWarningDays, undefined);
    assert.equal(metadata.fields.holidayManagementResetWarningRepeatDays, undefined);
});

test('all settings are admin-only config parameters', async () => {
    const config = await readJson(
        'files', 'custom', 'Espo', 'Modules', 'HolidayManagement',
        'Resources', 'metadata', 'app', 'config.json'
    );

    const expected = [...Object.keys(settingDefaults), 'holidayManagementApprovers'];
    assert.deepEqual(Object.keys(config.params).sort(), expected.sort());

    for (const name of expected) {
        assert.deepEqual(config.params[name], {level: 'admin'});
    }
});

test('print settings contain exactly two title/name blocks and no signature data', async () => {
    const metadata = await readJson(
        'files', 'custom', 'Espo', 'Modules', 'HolidayManagement',
        'Resources', 'metadata', 'entityDefs', 'Settings.json'
    );
    const names = Object.keys(metadata.fields);
    const blockFields = names.filter(name => /holidayManagementApprovalBlock\d+(Title|Name)$/.test(name));

    assert.deepEqual(blockFields.sort(), [
        'holidayManagementApprovalBlock1Name',
        'holidayManagementApprovalBlock1Title',
        'holidayManagementApprovalBlock2Name',
        'holidayManagementApprovalBlock2Title',
    ]);
    assert.equal(names.some(name => /signature/i.test(name)), false);
});

test('server-side validator accepts only one or two active internal approvers', async () => {
    const source = await readFile(path.join(
        moduleRoot,
        'FieldValidators', 'Settings', 'Approvers', 'Valid.php'
    ), 'utf8');

    assert.match(source, /\$field \. 'Ids'/);
    assert.match(source, /count\(\$ids\) < 1 \|\| count\(\$ids\) > 2/);
    assert.match(source, /User::TYPE_REGULAR/);
    assert.match(source, /User::TYPE_ADMIN/);
    assert.match(source, /'isActive'\s*=>\s*true/);
    assert.match(source, /\$activeInternalUserCount !== count\(\$ids\)/);
    assert.doesNotMatch(source, /TYPE_PORTAL|TYPE_API|TYPE_SYSTEM/);
});

test('install script persists missing defaults without overwriting existing values', async () => {
    const source = await readFile(path.join(extensionRoot, 'scripts', 'AfterInstall.php'), 'utf8');

    for (const [name, value] of Object.entries(settingDefaults)) {
        assert.match(source, new RegExp(`['\"]${name}['\"]\\s*=>\\s*${JSON.stringify(value).replace('-', '\\-')}`));
    }

    assert.match(source, /->has\(\$name\)/);
    assert.match(source, /setMultiple\(\$missingDefaults\)/);
    assert.match(source, /'holidayManagementApproversIds'\s*=>\s*\[\]/);
    assert.match(source, /\$missingDefaults\['holidayManagementApproversNames'\]\s*=\s*\(object\) \[\]/);
    assert.doesNotMatch(source, /holidayManagementApproverRole/);
});

test('English and Romanian settings/admin translations cover every field', async () => {
    for (const locale of ['en_US', 'ro_RO']) {
        const settings = await readJson(
            'files', 'custom', 'Espo', 'Modules', 'HolidayManagement',
            'Resources', 'i18n', locale, 'Settings.json'
        );
        const admin = await readJson(
            'files', 'custom', 'Espo', 'Modules', 'HolidayManagement',
            'Resources', 'i18n', locale, 'Admin.json'
        );

        for (const name of [...Object.keys(settingDefaults), 'holidayManagementApprovers']) {
            assert.equal(typeof settings.fields[name], 'string', `${locale} missing ${name}`);
            assert.notEqual(settings.fields[name].trim(), '');
        }

        assert.equal(typeof admin.labels['Holiday Management'], 'string');
        assert.equal(typeof admin.descriptions.holidayManagementSettings, 'string');
    }
});

test('approval remains field-based without extra response or document entities', async () => {
    const forbidden = [
        'CompanyHoliday',
        'HolidayApprovalResponse',
        'HolidayDocument',
    ];
    const entityDefsDir = path.join(moduleRoot, 'Resources', 'metadata', 'entityDefs');
    const entityFiles = await readdir(entityDefsDir);

    for (const entity of forbidden) {
        assert.equal(entityFiles.includes(`${entity}.json`), false, `${entity} belongs to a later phase`);
    }
    assert.equal(entityFiles.includes('Settings.json'), true);
    assert.equal(entityFiles.includes('HolidayProfile.json'), true);
    assert.equal(entityFiles.includes('HolidayLedger.json'), true);
});

test('phase test harness covers package layout and EspoCRM 10 Docker installation', async () => {
    const packageScript = await readFile(path.join(import.meta.dirname, 'package.ps1'), 'utf8');
    const compose = await readFile(path.join(import.meta.dirname, 'compose.yaml'), 'utf8');
    const dockerScript = await readFile(path.join(import.meta.dirname, 'docker.ps1'), 'utf8');

    assert.match(packageScript, /System\.IO\.Compression\.ZipFile/);
    assert.match(packageScript, /manifest\.json/);
    assert.match(compose, /espocrm\/espocrm:10\.0\.2/);
    assert.match(dockerScript, /bin\/command extension[^\n]*--file=/);
    assert.match(dockerScript, /bin\/command rebuild/);
    assert.match(dockerScript, /api\/v1/);
    assert.match(dockerScript, /Path 'Metadata'/);
    assert.match(dockerScript, /Path 'Settings'/);
    assert.match(dockerScript, /ExpectedStatus 400/);
});

test('Windows packaging preserves portable forward-slash ZIP entry names', async () => {
    const packageScript = await readFile(path.join(import.meta.dirname, 'package.ps1'), 'utf8');

    assert.match(packageScript, /CreateEntry(?:FromFile)?\(/);
    assert.equal(packageScript.includes("$entryName = $relativePath.Replace('\\', '/')"), true);
    assert.equal(packageScript.includes("$_.FullName -match '\\\\'"), true);
    assert.doesNotMatch(packageScript, /Compress-Archive/);
});

test('settings view declares three explicit EspoCRM tabs', async () => {
    const source = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'admin', 'settings.js'
    ), 'utf8');

    assert.equal((source.match(/tabBreak:\s*true/g) ?? []).length, 3);
    assert.equal((source.match(/tabLabel:/g) ?? []).length, 3);
});

test('reset-date setting uses the native EspoCRM date field', async () => {
    const metadata = await readJson(
        'files', 'custom', 'Espo', 'Modules', 'HolidayManagement',
        'Resources', 'metadata', 'entityDefs', 'Settings.json'
    );
    const field = metadata.fields.holidayManagementResetDate;

    assert.equal(field.type, 'date');
    assert.equal(field.required, true);
    assert.equal(field.pattern, undefined);
    assert.equal(field.view, 'holiday-management:views/fields/annual-reset-date');

    const source = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'fields', 'annual-reset-date.js'
    ), 'utf8');

    assert.match(source, /define\(\['views\/fields\/date'\]/);
    assert.match(source, /return `2000-\$\{value\}`/);
    assert.doesNotMatch(source, /type=["']date/);
});
