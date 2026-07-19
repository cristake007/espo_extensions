define([
    'document-builder:editor/conditions/condition-builder',
], ConditionBuilder => {
    const key = identity => JSON.stringify(identity);
    const ordered = new Set(['greaterThan', 'greaterOrEqual', 'lessThan', 'lessOrEqual']);

    const compare = (operator, actual, operand) => ({
        equals: actual === operand,
        notEquals: actual !== operand,
        greaterThan: actual > operand,
        greaterOrEqual: actual >= operand,
        lessThan: actual < operand,
        lessOrEqual: actual <= operand,
    })[operator] === true;

    const evaluateRule = (rule, values) => {
        const resolved = values.get(key(rule.identity));
        const present = resolved?.state === 'present' && resolved.type === rule.valueType;

        if (rule.operator === 'exists') return present;
        if (rule.operator === 'missing') return !resolved || resolved.state === 'missing';
        if (!present) return false;

        let actual = rule.valueType === 'currency' ? resolved.value?.amount : resolved.value;
        let operand = rule.operand;

        if (ordered.has(rule.operator) && ['date', 'datetime'].includes(rule.valueType)) {
            actual = Date.parse(actual);
            operand = Date.parse(operand);
            if (!Number.isFinite(actual) || !Number.isFinite(operand)) return false;
        }
        if (['number', 'currency'].includes(rule.valueType) &&
            ['equals', 'notEquals'].includes(rule.operator)) {
            actual = Number(actual);
            operand = Number(operand);
        }
        if (rule.operator === 'contains') {
            return Array.isArray(actual) ? actual.includes(operand) :
                typeof actual === 'string' && actual.includes(operand);
        }
        if (rule.operator === 'startsWith') {
            return typeof actual === 'string' && actual.startsWith(operand);
        }
        if (rule.operator === 'isTrue') return actual === true;
        if (rule.operator === 'isFalse') return actual === false;

        return compare(rule.operator, actual, operand);
    };

    return Object.freeze({
        evaluate(value, previewValues) {
            const condition = ConditionBuilder.create(value);
            const results = condition.rules.map(rule => evaluateRule(rule, previewValues));

            return Object.freeze({
                visible: condition.mode === 'all' ? results.every(Boolean) : results.some(Boolean),
                target: condition.target,
            });
        },
    });
});
