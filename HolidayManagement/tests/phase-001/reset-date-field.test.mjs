import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(resolve(
    import.meta.dirname,
    '../../files/client/custom/modules/holiday-management/src/views/fields/annual-reset-date.js'
), 'utf8');

class NativeEspoDateField {
    stringifyDateValue(value) {
        return `display:${value}`;
    }

    parseDate(value) {
        return value === '01/01/2027' ? '2027-01-01' : -1;
    }
}

let AnnualResetDateField;
vm.runInNewContext(source, {
    define(dependencies, factory) {
        assert.deepEqual([...dependencies], ['views/fields/date']);
        AnnualResetDateField = factory(NativeEspoDateField);
    },
});

test('MM-DD shorthand is normalized before native date validation', () => {
    const field = new AnnualResetDateField();

    assert.equal(field.parseDate('01-01'), '2000-01-01');
    assert.equal(field.parseDate('02-29'), '2000-02-29');
    assert.equal(field.stringifyDateValue('01-01'), 'display:2000-01-01');
});

test('full dates and invalid shorthand remain owned by the native EspoCRM field', () => {
    const field = new AnnualResetDateField();

    assert.equal(field.parseDate('01/01/2027'), '2027-01-01');
    assert.equal(field.parseDate('13-01'), -1);
    assert.equal(field.parseDate('02-30'), -1);
});
