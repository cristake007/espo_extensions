define([], () => {
    const IDENTIFIER = /^[A-Za-z][A-Za-z0-9_]{0,99}$/;
    const ENTITY_TYPE = /^[A-Za-z][A-Za-z0-9]{0,99}$/;
    const SOURCE_TYPES = Object.freeze({
        entity: Object.freeze(['direct', 'related', 'collection']),
        system: Object.freeze(['system']),
        spreadsheet: Object.freeze(['spreadsheet']),
    });

    const create = value => {
        if (!value || typeof value !== 'object' || Array.isArray(value) ||
            !Object.prototype.hasOwnProperty.call(SOURCE_TYPES, value.source) ||
            !SOURCE_TYPES[value.source].includes(value.type) ||
            !Array.isArray(value.path) || value.path.length < 1 || value.path.length > 4 ||
            [...value.path].some(segment => !IDENTIFIER.test(segment))) {
            throw new TypeError('Invalid variable identity.');
        }

        const entity = value.source === 'entity';
        const allowedKeys = entity ? ['source', 'type', 'entityType', 'path'] :
            ['source', 'type', 'path'];

        if (Object.keys(value).some(key => !allowedKeys.includes(key)) ||
            allowedKeys.some(key => !(key in value)) ||
            (entity && !ENTITY_TYPE.test(value.entityType || '')) ||
            (!entity && 'entityType' in value) ||
            (value.type === 'direct' && value.path.length !== 1) ||
            (value.type === 'related' && value.path.length < 2) ||
            (value.source !== 'entity' && value.path.length !== 1)) {
            throw new TypeError('Invalid variable identity structure.');
        }

        const result = {source: value.source, type: value.type};
        if (entity) result.entityType = value.entityType;
        result.path = Object.freeze([...value.path]);

        return Object.freeze(result);
    };
    const entityField = (entityType, path) => create({
        source: 'entity',
        type: path.length === 1 ? 'direct' : 'related',
        entityType,
        path,
    });
    const entityCollection = (entityType, path) => create({
        source: 'entity', type: 'collection', entityType, path,
    });
    const system = name => create({source: 'system', type: 'system', path: [name]});
    const spreadsheet = column => create({source: 'spreadsheet', type: 'spreadsheet', path: [column]});
    const usage = identity => create(identity).type === 'collection' ? 'collection' : 'scalar';
    const serialize = identity => JSON.stringify(create(identity));

    return Object.freeze({create, entityField, entityCollection, system, spreadsheet, usage, serialize});
});
