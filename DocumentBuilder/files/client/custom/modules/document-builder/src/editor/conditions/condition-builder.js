define([
    'document-builder:editor/state/json',
    'document-builder:editor/variables/variable-identity',
], (Json, VariableIdentity) => {
    const TARGETS = Object.freeze(['element', 'parent']);
    const MODES = Object.freeze(['all', 'any']);
    const TYPES = Object.freeze([
        'text', 'date', 'datetime', 'number', 'currency', 'boolean', 'enum', 'multiValue',
    ]);
    const OPERATORS = Object.freeze([
        'exists', 'missing', 'equals', 'notEquals', 'contains', 'startsWith',
        'greaterThan', 'greaterOrEqual', 'lessThan', 'lessOrEqual', 'isTrue', 'isFalse',
    ]);
    const NO_OPERAND = new Set(['exists', 'missing', 'isTrue', 'isFalse']);
    const ORDERED = new Set(['greaterThan', 'greaterOrEqual', 'lessThan', 'lessOrEqual']);
    const TEXT_OPERATORS = new Set(['contains', 'startsWith']);
    const finiteNumber = value => typeof value === 'number' && Number.isFinite(value);

    const operandMatches = (valueType, operator, operand) => {
        if (NO_OPERAND.has(operator)) {
            return operand === null &&
                (!['isTrue', 'isFalse'].includes(operator) || valueType === 'boolean');
        }
        if (TEXT_OPERATORS.has(operator)) {
            return ['text', 'enum', 'multiValue'].includes(valueType) && typeof operand === 'string';
        }
        if (ORDERED.has(operator)) {
            return ['number', 'currency'].includes(valueType) ? finiteNumber(operand) :
                ['date', 'datetime'].includes(valueType) && typeof operand === 'string';
        }
        if (['number', 'currency'].includes(valueType)) return finiteNumber(operand);
        if (valueType === 'boolean') return typeof operand === 'boolean';
        if (valueType === 'multiValue') {
            return ['string', 'number', 'boolean'].includes(typeof operand) &&
                (typeof operand !== 'number' || finiteNumber(operand));
        }

        return typeof operand === 'string';
    };

    const createRule = value => {
        if (!Json.isPlainObject(value) ||
            Object.keys(value).sort().join(',') !== 'identity,operand,operator,valueType' ||
            !TYPES.includes(value.valueType) || !OPERATORS.includes(value.operator) ||
            !operandMatches(value.valueType, value.operator, value.operand) ||
            (typeof value.operand === 'string' && value.operand.length > 1000)) {
            throw new TypeError('Invalid condition rule.');
        }

        return Object.freeze({
            identity: VariableIdentity.create(value.identity),
            valueType: value.valueType,
            operator: value.operator,
            operand: value.operand,
        });
    };

    return Object.freeze({
        TARGETS,
        MODES,
        TYPES,
        OPERATORS,
        create(value) {
            if (!Json.isPlainObject(value) || !TARGETS.includes(value.target) ||
                !MODES.includes(value.mode) || !Array.isArray(value.rules) ||
                value.rules.length < 1 || value.rules.length > 25 ||
                Object.keys(value).some(key => !['target', 'mode', 'rules'].includes(key))) {
                throw new TypeError('Invalid visibility condition.');
            }

            return Object.freeze({
                target: value.target,
                mode: value.mode,
                rules: Object.freeze(value.rules.map(createRule)),
            });
        },
        single(identity, valueType = 'text', operator = 'exists', operand = null,
            target = 'element', mode = 'all') {
            return this.create({
                target,
                mode,
                rules: [{identity, valueType, operator, operand}],
            });
        },
    });
});
