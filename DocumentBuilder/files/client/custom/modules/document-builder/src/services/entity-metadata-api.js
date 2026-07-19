define([], () => {
    const IDENTIFIER = /^[A-Za-z][A-Za-z0-9_]{0,99}$/;
    const TEXT_TYPES = new Set([
        'address', 'array', 'bool', 'currency', 'date', 'datetime', 'duration',
        'email', 'enum', 'float', 'int', 'multiEnum', 'number', 'percent',
        'personName', 'phone', 'text', 'url', 'varchar',
    ]);
    const LINK_TYPES = new Set([
        'belongsTo', 'belongsToParent', 'hasOne', 'hasChildren', 'hasMany', 'hasManyRight', 'manyMany',
    ]);
    const validLabel = value => typeof value === 'string' && value.trim() &&
        value.length <= 200 && !/[\u0000-\u001F\u007F]/.test(value);
    const validFlags = (item, flags) => flags.every(flag => typeof item[flag] === 'boolean');

    return class EntityMetadataApi {
        constructor(ajax = Espo.Ajax) {
            this.ajax = ajax;
        }

        async get(rootEntityType, path = []) {
            if (!IDENTIFIER.test(rootEntityType || '') || !Array.isArray(path) ||
                path.length > 3 || path.some(item => !IDENTIFIER.test(item))) {
                throw new TypeError('Entity metadata request identifiers are invalid.');
            }

            const query = path.length ? `?path=${encodeURIComponent(path.join('.'))}` : '';
            const response = await this.ajax.getRequest(
                `DocumentBuilder/entity-catalogue/${rootEntityType}/metadata-tree${query}`,
            );

            if (!response || response.rootEntityType !== rootEntityType ||
                !IDENTIFIER.test(response.entityType || '') ||
                !Array.isArray(response.path) || response.path.join('.') !== path.join('.') ||
                !Array.isArray(response.fields) || !Array.isArray(response.relationships)) {
                throw new TypeError('The entity metadata response is invalid.');
            }

            const fieldNames = new Set();
            const fields = response.fields.map(item => {
                if (!item || !IDENTIFIER.test(item.name || '') || !validLabel(item.label) ||
                    !TEXT_TYPES.has(item.type) ||
                    !validFlags(item, ['direct', 'calculated', 'required', 'readOnly', 'custom']) ||
                    fieldNames.has(item.name)) {
                    throw new TypeError('The entity metadata contains an invalid field.');
                }

                fieldNames.add(item.name);

                return Object.freeze({...item});
            });
            const relationshipNames = new Set();
            const relationships = response.relationships.map(item => {
                if (!item || !IDENTIFIER.test(item.name || '') || !validLabel(item.label) ||
                    !IDENTIFIER.test(item.targetEntityType || '') || !LINK_TYPES.has(item.type) ||
                    !validFlags(item, [
                        'single', 'collection', 'custom', 'expandable', 'circular', 'depthLimited',
                    ]) || item.single === item.collection || relationshipNames.has(item.name)) {
                    throw new TypeError('The entity metadata contains an invalid relationship.');
                }

                relationshipNames.add(item.name);

                return Object.freeze({...item});
            });

            return Object.freeze({
                rootEntityType,
                entityType: response.entityType,
                path: Object.freeze([...path]),
                fields: Object.freeze(fields),
                relationships: Object.freeze(relationships),
            });
        }
    };
});
