'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

class Element {
    constructor(tag = 'div') {
        this.tag = tag;
        this.length = 1;
        this.children = [];
        this.attributes = {};
        this.textValue = '';
    }

    addClass() { return this; }
    attr(name, value) { this.attributes[name] = value; return this; }
    text(value) { this.textValue = value; return this; }
    append(child) { this.children.push(child); return this; }
    empty() { this.children = []; return this; }
}

const $ = value => new Element(value.replace(/[<>]/g, ''));
let definition;

const define = (dependencies, factory) => {
    definition = {dependencies, factory};
};

const source = fs.readFileSync(
    path.resolve(
        __dirname,
        '../../files/client/custom/modules/document-builder/src/views/' +
            'document-builder-document/record/list.js'
    ),
    'utf8'
);

new Function('define', '$', source)(define, $);

class BaseListView {
    afterRender() {
        this.baseAfterRenderCalled = true;
    }

    translate(key) {
        return key;
    }
}

assert.deepEqual(definition.dependencies, ['views/record/list']);
const ListView = definition.factory(BaseListView);
const emptyNode = new Element();
const view = new ListView();
view.collection = {length: 0};
view.$el = {find: selector => selector === '.no-data' ? emptyNode : new Element()};
view.afterRender();

assert.equal(view.baseAfterRenderCalled, true);
assert.equal(emptyNode.children.length, 1);
assert.equal(emptyNode.children[0].children[1].textValue, 'generatedDocumentsEmptyTitle');
assert.equal(emptyNode.children[0].children[2].textValue, 'generatedDocumentsEmptyText');
assert.equal(emptyNode.children[0].children[3].attributes.href, '#DocumentBuilderTemplate/create');
assert.equal(emptyNode.children[0].children[3].textValue, 'createTemplate');

const populatedNode = new Element();
const populatedView = new ListView();
populatedView.collection = {length: 1};
populatedView.$el = {find: () => populatedNode};
populatedView.afterRender();
assert.equal(populatedNode.children.length, 0);

console.log('Phase 36 generated-document empty-state client tests passed.');
