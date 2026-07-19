define([], () => {
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

    return Object.freeze({flatten});
});
