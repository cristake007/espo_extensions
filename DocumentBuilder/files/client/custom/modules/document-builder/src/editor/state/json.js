define([], () => {
    const isPlainObject = value => {
        if (value === null || typeof value !== 'object' || Array.isArray(value)) {
            return false;
        }

        const prototype = Object.getPrototypeOf(value);

        return prototype === Object.prototype || prototype === null;
    };

    const canonicalize = value => {
        if (value === null || typeof value === 'string' || typeof value === 'boolean') {
            return value;
        }

        if (typeof value === 'number') {
            if (!Number.isFinite(value)) {
                throw new TypeError('Editor state only accepts finite JSON numbers.');
            }

            return value;
        }

        if (Array.isArray(value)) {
            return value.map(item => canonicalize(item));
        }

        if (!isPlainObject(value)) {
            throw new TypeError('Editor state only accepts plain JSON values.');
        }

        return Object.keys(value)
            .sort()
            .reduce((result, key) => {
                if (typeof value[key] === 'undefined') {
                    throw new TypeError('Editor state does not accept undefined values.');
                }

                result[key] = canonicalize(value[key]);

                return result;
            }, {});
    };

    const clone = value => canonicalize(value);
    const stringify = value => JSON.stringify(canonicalize(value));

    return Object.freeze({
        canonicalize,
        clone,
        stringify,
        isPlainObject,
    });
});
