define(['document-builder:editor/variables/variable-identity'], VariableIdentity => {
    const SYSTEM_VARIABLES = Object.freeze([
        Object.freeze({name: 'currentDate', label: 'Current Date', valueType: 'date'}),
        Object.freeze({name: 'currentDateTime', label: 'Current Date and Time', valueType: 'datetime'}),
        Object.freeze({name: 'currentUserName', label: 'Current User Name', valueType: 'text'}),
        Object.freeze({name: 'pageNumber', label: 'Page Number', valueType: 'number', rendererPlaceholder: true}),
        Object.freeze({name: 'pageCount', label: 'Page Count', valueType: 'number', rendererPlaceholder: true}),
    ]);
    const flatten = (nodes, expandedPaths, search = '') => {
        const rows = [];
        const query = search.trim().toLocaleLowerCase();
        const matches = item => !query || [item.label, item.name, item.type]
            .some(value => String(value).toLocaleLowerCase().includes(query));
        const visit = (path, depth) => {
            const key = path.join('.');
            const state = nodes.get(key);

            if (!state || state.status !== 'ready') return;
            state.node.fields.forEach(field => {
                if (!matches(field)) return;
                rows.push({
                    ...field,
                    isField: true,
                    variablePathKey: [...path, field.name].join('.'),
                    identity: VariableIdentity.entityField(
                        state.node.rootEntityType,
                        [...path, field.name],
                    ),
                    depthStyle: `--document-builder-variable-depth: ${depth}`,
                    fieldKind: field.calculated ? 'Calculated' : 'Direct',
                });
            });
            state.node.relationships.forEach(relationship => {
                const childPath = [...path, relationship.name];
                const pathKey = childPath.join('.');
                const expanded = expandedPaths.has(pathKey);

                if (matches(relationship) || !query) rows.push({
                    ...relationship,
                    isRelationship: true,
                    pathKey,
                    identity: relationship.collection ? VariableIdentity.entityCollection(
                        state.node.rootEntityType,
                        childPath,
                    ) : null,
                    expanded,
                    depthStyle: `--document-builder-variable-depth: ${depth}`,
                    relationshipKind: relationship.collection ? 'Collection' : 'Single',
                });
                if (expanded) {
                    const child = nodes.get(pathKey);
                    if (child?.status === 'loading') rows.push({
                        isLoading: true, pathKey,
                        depthStyle: `--document-builder-variable-depth: ${depth + 1}`,
                    });
                    if (child?.status === 'error') rows.push({
                        isLoadError: true, pathKey,
                        depthStyle: `--document-builder-variable-depth: ${depth + 1}`,
                    });
                    visit(childPath, depth + 1);
                }
            });
        };

        visit([], 0);

        return rows;
    };

    const identityAt = (nodes, pathKey) => {
        const path = pathKey ? pathKey.split('.') : [];
        const name = path.pop();
        const state = nodes.get(path.join('.'));
        const field = state?.status === 'ready' ?
            state.node.fields.find(item => item.name === name) : null;

        if (!field) throw new TypeError('The selected variable is not loaded readable metadata.');

        return VariableIdentity.entityField(state.node.rootEntityType, [...path, name]);
    };

    const systemRows = () => SYSTEM_VARIABLES.map(item => ({
        ...item,
        identity: VariableIdentity.system(item.name),
    }));

    const systemIdentityAt = name => {
        if (!SYSTEM_VARIABLES.some(item => item.name === name)) {
            throw new TypeError('The selected system variable is unsupported.');
        }

        return VariableIdentity.system(name);
    };

    return Object.freeze({flatten, identityAt, systemRows, systemIdentityAt});
});
