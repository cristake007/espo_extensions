define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/json',
], (Command, Json) => {
    return class UpdateDataSourceCommand extends Command {
        constructor(dataSource) {
            super();

            if (!Json.isPlainObject(dataSource) || !['none', 'entity'].includes(dataSource.type)) {
                throw new TypeError('The data-source descriptor is invalid.');
            }

            if (dataSource.type === 'none' && Object.keys(dataSource).length !== 1) {
                throw new TypeError('A source-neutral descriptor cannot contain additional values.');
            }

            if (dataSource.type === 'entity' && (
                !/^[A-Za-z][A-Za-z0-9]{0,99}$/.test(dataSource.entityType || '') ||
                !Number.isInteger(dataSource.relationshipDepth) ||
                dataSource.relationshipDepth < 1 ||
                dataSource.relationshipDepth > 3 ||
                Object.keys(dataSource).some(key =>
                    !['type', 'entityType', 'relationshipDepth'].includes(key))
            )) {
                throw new TypeError('An entity source descriptor is invalid.');
            }

            this.dataSource = Json.clone(dataSource);
        }

        apply(layout) {
            layout.dataSource = Json.clone(this.dataSource);

            return true;
        }
    };
});
