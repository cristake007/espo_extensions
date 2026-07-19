'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const cache = new Map([['dompurify', {sanitize: value => String(value).replace(/<[^>]*>/g, '')}]]);
let active;
function define(dependencies, factory) { active = {dependencies, factory}; }
function load(name) {
    if (cache.has(name)) return cache.get(name);
    active = null;
    const file = path.join(sourceRoot, `${name.replace(/^document-builder:/, '')}.js`);
    new Function('define', fs.readFileSync(file, 'utf8'))(define);
    const value = active.factory(...active.dependencies.map(load)); cache.set(name, value); return value;
}

const VariableIdentity = load('document-builder:editor/variables/variable-identity');
const MetadataBrowser = load('document-builder:editor/variables/metadata-browser');
const RichText = load('document-builder:editor/content/rich-text');
const direct = VariableIdentity.entityField('Contact', ['firstName']);
const related = VariableIdentity.entityField('Contact', ['account', 'name']);
const collection = VariableIdentity.entityCollection('Contact', ['courses']);

assert.deepEqual(direct, {source: 'entity', type: 'direct', entityType: 'Contact', path: ['firstName']});
assert.deepEqual(related, {source: 'entity', type: 'related', entityType: 'Contact', path: ['account', 'name']});
assert.equal(VariableIdentity.usage(collection), 'collection');
assert.equal(VariableIdentity.serialize(related), '{"source":"entity","type":"related","entityType":"Contact","path":["account","name"]}');
assert.throws(() => VariableIdentity.create({...direct, label: 'not identity'}), /Invalid/);
assert.throws(() => VariableIdentity.entityField('Contact', ['account', '../secret']), /Invalid/);

const nodes = new Map([
    ['', {status: 'ready', node: {
        rootEntityType: 'Contact', fields: [],
        relationships: [{name: 'account', label: 'Account', type: 'belongsTo', targetEntityType: 'Account', single: true, collection: false, custom: false, expandable: true, circular: false, depthLimited: false}],
    }}],
    ['account', {status: 'ready', node: {
        rootEntityType: 'Contact',
        fields: [{name: 'name', label: 'Translated name', type: 'varchar', direct: true, calculated: false, required: false, readOnly: false, custom: false}],
        relationships: [],
    }}],
]);
assert.deepEqual(MetadataBrowser.identityAt(nodes, 'account.name'), related);
nodes.get('account').node.fields[0].label = 'Renamed label';
assert.deepEqual(MetadataBrowser.identityAt(nodes, 'account.name'), related);
assert.throws(() => MetadataBrowser.identityAt(nodes, 'account.password'), /readable metadata/);

const token = RichText.appendVariable([], 'variable_1', 'Display only', related)[0];
assert.deepEqual(token.identity, related);
assert.equal(token.label, 'Display only');
assert.throws(() => RichText.appendVariable([], 'variable_2', 'Courses', collection), /scalar/);

console.log('Phase 26 client identity, label independence, safe insertion, and scalar-usage tests passed.');
