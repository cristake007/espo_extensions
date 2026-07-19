'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const cache = new Map(); let active;
function define(dependencies, factory) { active = {dependencies, factory}; }
function load(name) {
    if (cache.has(name)) return cache.get(name);
    active = null;
    const file = path.join(sourceRoot, `${name.replace(/^document-builder:/, '')}.js`);
    new Function('define', fs.readFileSync(file, 'utf8'))(define);
    const value = active.factory(...active.dependencies.map(load)); cache.set(name, value); return value;
}
const EntityMetadataApi = load('document-builder:services/entity-metadata-api');
const MetadataBrowser = load('document-builder:editor/variables/metadata-browser');
const response = {
    rootEntityType: 'Contact', entityType: 'Contact', path: [],
    fields: [
        {name: 'firstName', label: 'Prenume', type: 'varchar', direct: true, calculated: false, required: false, readOnly: false, custom: false},
        {name: 'displayCode', label: 'Cod afișat', type: 'varchar', direct: true, calculated: true, required: false, readOnly: true, custom: true},
    ],
    relationships: [
        {name: 'account', label: 'Cont', type: 'belongsTo', targetEntityType: 'Account', single: true, collection: false, custom: false, expandable: true, circular: false, depthLimited: false},
        {name: 'courses', label: 'Cursuri', type: 'hasMany', targetEntityType: 'CourseEnrollment', single: false, collection: true, custom: true, expandable: true, circular: false, depthLimited: false},
    ],
};
(async () => {
    const api = new EntityMetadataApi({getRequest: async () => response});
    const node = await api.get('Contact');
    assert.equal(node.fields[0].name, 'firstName');
    assert.equal(node.fields[0].label, 'Prenume');
    assert.equal(node.relationships[0].single, true);
    assert.equal(node.relationships[1].collection, true);
    await assert.rejects(
        () => new EntityMetadataApi({getRequest: async () => ({
            ...response, fields: [{...response.fields[0], name: '../password'}],
        })}).get('Contact'),
        /invalid field/,
    );
    await assert.rejects(
        () => new EntityMetadataApi({getRequest: async () => ({
            ...response, relationships: [{...response.relationships[0], single: true, collection: true}],
        })}).get('Contact'),
        /invalid relationship/,
    );
    await assert.rejects(
        () => new EntityMetadataApi({getRequest: async () => ({
            ...response, fields: [response.fields[0], {...response.fields[0]}],
        })}).get('Contact'),
        /invalid field/,
    );
    await assert.rejects(
        () => new EntityMetadataApi({getRequest: async () => ({
            ...response,
            relationships: [{...response.relationships[0], type: 'untrustedLinkType'}],
        })}).get('Contact'),
        /invalid relationship/,
    );
    await assert.rejects(
        () => new EntityMetadataApi({getRequest: async () => ({
            ...response, fields: [{...response.fields[0], label: 'Unsafe\nlabel'}],
        })}).get('Contact'),
        /invalid field/,
    );

    const nodes = new Map([['', {status: 'ready', node}]]);
    const allRows = MetadataBrowser.flatten(nodes, new Set());
    assert.equal(allRows.find(item => item.name === 'displayCode').fieldKind, 'Calculated');
    assert.equal(allRows.find(item => item.name === 'courses').relationshipKind, 'Collection');
    assert.deepEqual(MetadataBrowser.flatten(nodes, new Set(), 'prenume').map(item => item.name), ['firstName']);
    assert.deepEqual(MetadataBrowser.flatten(nodes, new Set(), 'courses').map(item => item.name), ['courses']);
    console.log('Phase 25 client metadata validation, stable identifiers, classification, and search tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
