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
    holidayManagementResetCeilingDays: 90,
    holidayManagementResetWarningDays: 80,
    holidayManagementResetWarningRepeatDays: 30,
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
    assert.match(manifest.version, /^1\.0\.\d+$/);
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

    assert.deepEqual(metadata.fields.holidayManagementApproverRole, {
        type: 'link',
        entity: 'Role',
        required: true,
        tooltip: true,
        validatorClassNameList: [
            'Espo\\Modules\\HolidayManagement\\FieldValidators\\Settings\\ApproverRole\\AtMostTwoActiveInternalUsers',
        ],
    });
});

test('all settings are admin-only config parameters', async () => {
    const config = await readJson(
        'files', 'custom', 'Espo', 'Modules', 'HolidayManagement',
        'Resources', 'metadata', 'app', 'config.json'
    );

    const expected = [...Object.keys(settingDefaults), 'holidayManagementApproverRole'];
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

test('server-side validator rejects a role with more than two active internal users', async () => {
    const source = await readFile(path.join(
        moduleRoot,
        'FieldValidators', 'Settings', 'ApproverRole', 'AtMostTwoActiveInternalUsers.php'
    ), 'utf8');

    assert.match(source, /User::TYPE_REGULAR/);
    assert.match(source, /User::TYPE_ADMIN/);
    assert.match(source, /'isActive'\s*=>\s*true/);
    assert.match(source, /\$activeInternalUserCount\s*>\s*2/);
    assert.doesNotMatch(source, /TYPE_PORTAL|TYPE_API|TYPE_SYSTEM/);
});

test('install script persists missing defaults without overwriting existing values', async () => {
    const source = await readFile(path.join(extensionRoot, 'scripts', 'AfterInstall.php'), 'utf8');

    for (const [name, value] of Object.entries(settingDefaults)) {
        assert.match(source, new RegExp(`['\"]${name}['\"]\\s*=>\\s*${JSON.stringify(value).replace('-', '\\-')}`));
    }

    assert.match(source, /->has\(\$name\)/);
    assert.match(source, /setMultiple\(\$missingDefaults\)/);
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

        for (const name of [...Object.keys(settingDefaults), 'holidayManagementApproverRole']) {
            assert.equal(typeof settings.fields[name], 'string', `${locale} missing ${name}`);
            assert.notEqual(settings.fields[name].trim(), '');
        }

        assert.equal(typeof admin.labels['Holiday Management'], 'string');
        assert.equal(typeof admin.descriptions.holidayManagementSettings, 'string');
    }
});

test('phase 1 does not add later-phase domain entities or document templates', async () => {
    const forbidden = [
        'HolidayProfile',
        'HolidayBalance',
        'HolidayLedger',
        'CompanyHoliday',
        'HolidayRequest',
        'HolidayApprovalResponse',
        'HolidayDocument',
    ];
    const entityDefsDir = path.join(moduleRoot, 'Resources', 'metadata', 'entityDefs');
    const entityFiles = await readdir(entityDefsDir);

    for (const entity of forbidden) {
        assert.equal(entityFiles.includes(`${entity}.json`), false, `${entity} belongs to a later phase`);
    }
    assert.equal(entityFiles.includes('Settings.json'), true);
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

test('reset-date pattern uses EspoCRM 10 regular-expression metadata shape', async () => {
    const patterns = await readJson(
        'files', 'custom', 'Espo', 'Modules', 'HolidayManagement',
        'Resources', 'metadata', 'app', 'regExpPatterns.json'
    );

    assert.deepEqual(patterns.holidayManagementMonthDay, {
        pattern: '^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$',
        isSystem: false,
    });
});
