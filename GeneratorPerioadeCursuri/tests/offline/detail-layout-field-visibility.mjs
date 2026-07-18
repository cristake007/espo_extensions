import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionRoot = path.resolve(testDirectory, '../..');
const moduleResources = path.join(
    extensionRoot,
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources'
);
const mainPageCss = fs.readFileSync(path.join(
    extensionRoot,
    'files/client/custom/modules/generator-perioade-cursuri/css/generator-perioade-cursuri.css'
), 'utf8');

const entityContracts = {
    GeneratorPerioadeCursuri: {
        hiddenDetailFields: [
            'assignedUsers',
            'teams',
            'generatedAt',
            'createdAt',
            'createdBy',
            'modifiedAt',
            'modifiedBy',
        ],
        visibleDetailFields: ['name', 'sourceFile', 'exportFile'],
    },
    GeneratorPerioadeCursuriWordMatcher: {
        hiddenDetailFields: [
            'assignedUsers',
            'teams',
            'createdAt',
            'createdBy',
            'modifiedAt',
            'modifiedBy',
        ],
        visibleDetailFields: [
            'name',
            'wordTemplateFile',
            'wordScheduleFile',
            'wordConvertedFile',
            'wordConvertedAt',
        ],
    },
    GeneratorPerioadeCursuriXmlConverter: {
        hiddenDetailFields: [
            'assignedUsers',
            'teams',
            'createdAt',
            'createdBy',
            'modifiedAt',
            'modifiedBy',
        ],
        visibleDetailFields: [
            'name',
            'xmlScheduleFile',
            'startPostId',
            'xmlConvertedFile',
            'xmlConvertedAt',
        ],
    },
    GeneratorPerioadeCursuriWordPressUpdater: {
        hiddenDetailFields: [
            'assignedUsers',
            'teams',
            'createdAt',
            'createdBy',
            'modifiedAt',
            'modifiedBy',
        ],
        visibleDetailFields: ['name', 'wpScheduleFile', 'wpBaseUrl', 'wpUsername'],
    },
};

function readJson(file) {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function collectLayoutFieldNames(layout, entityType) {
    assert.ok(Array.isArray(layout), `${entityType}: detail layout must contain a panel list`);

    const fieldNames = [];

    for (const panel of layout) {
        assert.ok(Array.isArray(panel.rows), `${entityType}: each detail panel must contain rows`);

        for (const row of panel.rows) {
            assert.ok(Array.isArray(row), `${entityType}: each detail row must be a list`);

            for (const cell of row) {
                if (cell && typeof cell.name === 'string') {
                    fieldNames.push(cell.name);
                }
            }
        }
    }

    return fieldNames;
}

function collectSidePanelFieldNames(layout, entityType) {
    assert.ok(Array.isArray(layout), `${entityType}: default side panel must contain a field list`);

    return layout.map((item) => {
        assert.ok(
            item && typeof item.name === 'string',
            `${entityType}: each default side-panel item must name a field`
        );

        return item.name;
    });
}

const visibilityViolations = [];
let checks = 0;

for (const [entityType, contract] of Object.entries(entityContracts)) {
    const layoutPath = path.join(moduleResources, 'layouts', entityType, 'detail.json');
    const sidePanelPath = path.join(
        moduleResources,
        'layouts',
        entityType,
        'defaultSidePanel.json'
    );
    const entityDefsPath = path.join(moduleResources, 'metadata/entityDefs', `${entityType}.json`);
    const detailFieldNames = collectLayoutFieldNames(readJson(layoutPath), entityType);
    const detailFieldSet = new Set(detailFieldNames);
    const sidePanelFieldSet = new Set(
        collectSidePanelFieldNames(readJson(sidePanelPath), entityType)
    );
    const entityFields = readJson(entityDefsPath).fields;

    assert.ok(entityFields && typeof entityFields === 'object', `${entityType}: entityDefs must define fields`);
    checks++;

    for (const field of contract.hiddenDetailFields) {
        assert.ok(
            Object.hasOwn(entityFields, field),
            `${entityType}.${field}: hidden detail field must remain available in Entity Manager metadata`
        );
        checks++;

        if (detailFieldSet.has(field)) {
            visibilityViolations.push(`${entityType}.${field}`);
        }
    }

    for (const field of contract.visibleDetailFields) {
        assert.ok(
            detailFieldSet.has(field),
            `${entityType}.${field}: representative workflow field must remain on the detail layout`
        );
        checks++;
    }

    for (const field of [':assignedUser', 'assignedUser', 'assignedUsers', 'teams']) {
        assert.ok(
            !sidePanelFieldSet.has(field),
            `${entityType}.${field}: assignment field must not appear in the default side panel`
        );
        checks++;
    }
}

assert.ok(
    Object.hasOwn(
        readJson(path.join(
            moduleResources,
            'metadata/entityDefs/GeneratorPerioadeCursuri.json'
        )).fields,
        'generatedAt'
    ),
    'GeneratorPerioadeCursuri.generatedAt must remain defined for duplicate-generation control'
);
checks++;

assert.deepEqual(
    visibilityViolations,
    [],
    `Detail layouts still expose fields that must be hidden: ${visibilityViolations.join(', ')}`
);
checks++;

for (const layoutName of ['edit', 'detail']) {
    const layout = readJson(path.join(
        moduleResources,
        'layouts/GeneratorPerioadeCursuri',
        `${layoutName}.json`
    ));
    const sourceRows = layout.flatMap(panel => panel.rows)
        .filter(row => row.some(cell => cell && cell.name === 'sourceFile'));

    assert.equal(
        sourceRows.length,
        1,
        `GeneratorPerioadeCursuri ${layoutName}: Source File must appear in exactly one row`
    );
    checks++;
    assert.equal(
        sourceRows[0].length,
        1,
        `GeneratorPerioadeCursuri ${layoutName}: Source File must not retain a false partner cell`
    );
    checks++;
    assert.deepEqual(
        sourceRows[0][0],
        {name: 'sourceFile', fullWidth: true},
        `GeneratorPerioadeCursuri ${layoutName}: Source File must be explicitly full-width`
    );
    checks++;
}

assert.deepEqual(
    readJson(path.join(
        moduleResources,
        'layouts/GeneratorPerioadeCursuri/defaultSidePanel.json'
    )),
    [],
    'GeneratorPerioadeCursuri must retain its intentionally empty packaged side panel'
);
checks++;

for (const selector of [
    '.generator-perioade-cursuri-page .record-grid.record-grid-wide',
    '.generator-perioade-cursuri-page .record-grid > .side',
    '.generator-perioade-cursuri-page .generator-source-upload-label',
    '.generator-perioade-cursuri-page .generator-source-upload-action',
]) {
    assert.ok(mainPageCss.includes(selector), `Main-page CSS must include scoped selector: ${selector}`);
    checks++;
}

assert.match(
    mainPageCss,
    /\.generator-perioade-cursuri-page \.generator-source-upload-formats,[\s\S]*?flex-wrap:\s*wrap;/,
    'accepted-format badges must wrap within the main-page upload field'
);
checks++;
assert.match(
    mainPageCss,
    /@media \(max-width: 767px\)[\s\S]*?\.generator-perioade-cursuri-page \.generator-source-upload-action/,
    'the main-page uploader must retain a narrow-screen layout rule'
);
checks++;

console.log(`Detail-screen field visibility: ${checks} checks passed across four entities.`);
