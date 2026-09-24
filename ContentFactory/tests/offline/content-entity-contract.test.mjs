import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const readJson = path => JSON.parse(readFileSync(new URL(path, root), 'utf8'));

test('content entity contains the future persistence seam without analytics fields', () => {
    const entity = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/metadata/entityDefs/ContentFactoryContent.json'
    );
    const requiredFields = [
        'name',
        'brief',
        'platform',
        'format',
        'style',
        'customInstructions',
        'contentType',
        'courseId',
        'courseName',
        'courseSlug',
        'courseUrl',
        'generatedContent',
        'status',
        'assignedUser',
        'teams',
        'generatedAt',
        'publishedAt',
        'externalUrl',
        'externalId',
    ];

    requiredFields.forEach(field => assert.ok(entity.fields[field], `Missing field: ${field}`));
    assert.equal(entity.fields.generatedContent.type, 'text');
    assert.equal(entity.fields.contentType.required, true);
    assert.deepEqual(entity.fields.contentType.options, [
        'general', 'course', 'webinar', 'top10', 'service', 'postEvent',
    ]);
    assert.equal(entity.fields.subjectId, undefined);
    assert.equal(entity.fields.subjectLabel, undefined);
    assert.deepEqual(entity.fields.status.options, ['Draft', 'Review', 'Published']);
    assert.equal(entity.fields.reach, undefined);
    assert.equal(entity.fields.engagement, undefined);
});

test('native layouts expose only relevant content fields', () => {
    const list = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/layouts/ContentFactoryContent/list.json'
    );
    const search = readJson(
        'files/custom/Espo/Modules/ContentFactory/Resources/layouts/ContentFactoryContent/search.json'
    );

    assert.deepEqual(
        list.map(item => item.name),
        ['name', 'platform', 'format', 'status', 'assignedUser', 'generatedAt']
    );
    assert.ok(search.some(item => item.name === 'teams'));
    assert.ok(search.some(item => item.name === 'status'));
});
