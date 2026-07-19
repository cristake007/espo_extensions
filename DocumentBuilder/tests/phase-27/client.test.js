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

const VariablePresentation = load('document-builder:editor/variables/variable-presentation');
const RichText = load('document-builder:editor/content/rich-text');
const MetadataBrowser = load('document-builder:editor/variables/metadata-browser');
const defaults = VariablePresentation.defaults();

assert.equal(defaults.format.type, 'auto');
assert.equal(defaults.missing, 'empty');
assert.equal(Object.isFrozen(defaults), true);
assert.equal(Object.isFrozen(defaults.format), true);
assert.throws(() => VariablePresentation.create({...defaults, expression: 'process.exit()'}), /Invalid/);
assert.throws(() => VariablePresentation.create({
    ...defaults, format: {...defaults.format, decimals: 100},
}), /Invalid/);
assert.throws(() => VariablePresentation.create({
    ...defaults, format: {...defaults.format, prefix: '\u0000unsafe'},
}), /Invalid/);
assert.throws(() => VariablePresentation.create({...defaults, missing: 'javascript'}), /Invalid/);
assert.throws(() => VariablePresentation.create({...defaults, missing: 'fallback'}), /Invalid/);

const fallback = VariablePresentation.create({
    format: {...defaults.format, fallback: 'Indisponibil', case: 'upper', prefix: '[', suffix: ']'},
    missing: 'fallback',
});
const identity = {source: 'system', type: 'system', path: ['currentDate']};
const token = RichText.appendVariable([], 'variable_date', 'Data curentă', identity, fallback)[0];
assert.deepEqual(token.presentation, fallback);
assert.equal(token.label, 'Data curentă');
assert.deepEqual(token.identity, identity);
assert.deepEqual(MetadataBrowser.systemIdentityAt('pageNumber'), {
    source: 'system', type: 'system', path: ['pageNumber'],
});
assert.equal(MetadataBrowser.systemRows().find(item => item.name === 'pageNumber').rendererPlaceholder, true);
assert.throws(() => MetadataBrowser.systemIdentityAt('customExpression'), /unsupported/);

console.log('Phase 27 client presentation vocabulary, bounds, expression rejection, and token tests passed.');
