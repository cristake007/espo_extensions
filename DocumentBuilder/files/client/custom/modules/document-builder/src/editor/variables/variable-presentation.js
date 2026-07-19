define([], () => {
    const FORMAT_TYPES = Object.freeze([
        'auto', 'date', 'datetime', 'number', 'currency', 'boolean', 'enum', 'multiValue',
    ]);
    const MISSING_POLICIES = Object.freeze([
        'empty', 'fallback', 'hideElement', 'hideRow', 'hideSection', 'warning', 'required',
    ]);
    const CASES = Object.freeze(['none', 'upper', 'lower', 'title']);
    const FORMAT_KEYS = Object.freeze([
        'type', 'decimals', 'dateStyle', 'timeStyle', 'currency', 'trueLabel', 'falseLabel',
        'separator', 'trim', 'case', 'prefix', 'suffix', 'fallback',
    ]);
    const safeText = (value, maximum, emptyAllowed = true) => typeof value === 'string' &&
        (emptyAllowed || value.length > 0) && value.length <= maximum &&
        !/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/.test(value);
    const hasExactKeys = (value, keys) => value && typeof value === 'object' &&
        !Array.isArray(value) && Object.keys(value).length === keys.length &&
        Object.keys(value).every(key => keys.includes(key));
    const defaults = () => create({
        format: {
            type: 'auto', decimals: 2, dateStyle: 'medium', timeStyle: 'short',
            currency: null, trueLabel: null, falseLabel: null, separator: ', ', trim: true,
            case: 'none', prefix: '', suffix: '', fallback: null,
        },
        missing: 'empty',
    });
    const create = value => {
        if (!hasExactKeys(value, ['format', 'missing']) ||
            !hasExactKeys(value.format, FORMAT_KEYS) ||
            !FORMAT_TYPES.includes(value.format.type) ||
            !Number.isInteger(value.format.decimals) || value.format.decimals < 0 ||
            value.format.decimals > 6 ||
            !['short', 'medium', 'long'].includes(value.format.dateStyle) ||
            !['short', 'medium'].includes(value.format.timeStyle) ||
            (value.format.currency !== null && !/^[A-Z]{3}$/.test(value.format.currency)) ||
            (value.format.trueLabel !== null && !safeText(value.format.trueLabel, 100)) ||
            (value.format.falseLabel !== null && !safeText(value.format.falseLabel, 100)) ||
            !safeText(value.format.separator, 10, false) ||
            typeof value.format.trim !== 'boolean' || !CASES.includes(value.format.case) ||
            !safeText(value.format.prefix, 100) || !safeText(value.format.suffix, 100) ||
            (value.format.fallback !== null && !safeText(value.format.fallback, 200)) ||
            !MISSING_POLICIES.includes(value.missing) ||
            (value.missing === 'fallback' && value.format.fallback === null)) {
            throw new TypeError('Invalid variable presentation.');
        }

        return Object.freeze({
            format: Object.freeze({...value.format}),
            missing: value.missing,
        });
    };

    return Object.freeze({create, defaults, FORMAT_TYPES, MISSING_POLICIES, CASES});
});
