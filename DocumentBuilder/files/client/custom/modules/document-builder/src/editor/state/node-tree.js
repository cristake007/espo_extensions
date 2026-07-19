define(['document-builder:editor/state/json'], (Json) => {
    const REGION_LIST = Object.freeze(['header', 'sections', 'footer']);
    const STABLE_ID_PATTERN = /^[A-Za-z][A-Za-z0-9_-]{0,63}$/;

    const assertNode = (node, ids) => {
        if (!Json.isPlainObject(node)) {
            throw new TypeError('A layout node must be an object.');
        }

        if (typeof node.id !== 'string' || !STABLE_ID_PATTERN.test(node.id)) {
            throw new TypeError('A layout node must have a canonical stable ID.');
        }

        if (ids.has(node.id)) {
            throw new TypeError(`Duplicate layout node ID: ${node.id}.`);
        }

        if (typeof node.type !== 'string' || !/^[a-z][a-z0-9-]{0,63}$/.test(node.type)) {
            throw new TypeError('A layout node must have a canonical type.');
        }

        ids.add(node.id);

        if ('children' in node && !Array.isArray(node.children)) {
            throw new TypeError(`Node ${node.id} has a non-list children value.`);
        }
    };

    const index = layout => {
        if (!Json.isPlainObject(layout)) {
            throw new TypeError('The editor layout must be an object.');
        }

        const result = new Map();
        const ids = new Set();

        const visit = (node, container, nodeIndex, region, parentId) => {
            assertNode(node, ids);
            result.set(node.id, {node, container, index: nodeIndex, region, parentId});

            (node.children || []).forEach((child, childIndex) => {
                visit(child, node.children, childIndex, region, node.id);
            });
        };

        REGION_LIST.forEach(region => {
            if (!Array.isArray(layout[region])) {
                throw new TypeError(`Layout region ${region} must be a list.`);
            }

            layout[region].forEach((node, nodeIndex) => {
                visit(node, layout[region], nodeIndex, region, null);
            });
        });

        return result;
    };

    const getLocation = (layout, nodeId) => index(layout).get(nodeId) || null;

    const getContainer = (layout, target) => {
        if (target.parentId) {
            const parent = getLocation(layout, target.parentId);

            if (!parent) {
                throw new TypeError(`Target parent ${target.parentId} does not exist.`);
            }

            if (!('children' in parent.node)) {
                parent.node.children = [];
            }

            if (!Array.isArray(parent.node.children)) {
                throw new TypeError(`Target parent ${target.parentId} cannot contain nodes.`);
            }

            return parent.node.children;
        }

        if (!REGION_LIST.includes(target.region)) {
            throw new TypeError('A root command target must name a canonical layout region.');
        }

        return layout[target.region];
    };

    const normalizeIndex = (container, requestedIndex) => {
        if (requestedIndex === null || typeof requestedIndex === 'undefined') {
            return container.length;
        }

        if (!Number.isInteger(requestedIndex)) {
            throw new TypeError('A command target index must be an integer.');
        }

        return Math.max(0, Math.min(requestedIndex, container.length));
    };

    const contains = (node, candidateId) => {
        if (node.id === candidateId) {
            return true;
        }

        return (node.children || []).some(child => contains(child, candidateId));
    };

    const prepareNewSubtree = (node, idFactory, replaceIds = false) => {
        const copy = Json.clone(node);

        const prepare = item => {
            if (replaceIds || !item.id) {
                item.id = idFactory.create(item.type || 'node');
            } else {
                idFactory.reserve(item.id);
            }

            (item.children || []).forEach(prepare);
        };

        prepare(copy);

        return copy;
    };

    return Object.freeze({
        REGION_LIST,
        STABLE_ID_PATTERN,
        index,
        getLocation,
        getContainer,
        normalizeIndex,
        contains,
        prepareNewSubtree,
    });
});
