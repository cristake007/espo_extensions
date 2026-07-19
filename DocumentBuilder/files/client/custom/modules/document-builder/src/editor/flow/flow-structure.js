define([
    'document-builder:editor/state/json',
    'document-builder:editor/state/node-tree',
], (Json, NodeTree) => {
    const FLOW_CAPABILITY = 'layout.flow';
    const SECTION_TYPE = 'flow-section';
    const CONTAINER_TYPE = 'flow-container';
    const CONTENT_TYPES = Object.freeze(['heading', 'static-text', 'paragraph']);
    const BASIC_TYPES = Object.freeze(['divider', 'spacer', 'page-break']);
    const EDGE_LIST = Object.freeze(['top', 'right', 'bottom', 'left']);
    const measurement = value => ({value, unit: 'mm'});
    const box = value => ({
        top: measurement(value),
        right: measurement(value),
        bottom: measurement(value),
        left: measurement(value),
    });
    const isMeasurement = value => Json.isPlainObject(value) &&
        Object.keys(value).length === 2 &&
        typeof value.value === 'number' && value.value >= 0 && value.value <= 2000 &&
        value.unit === 'mm';
    const isBox = value => Json.isPlainObject(value) &&
        Object.keys(value).length === EDGE_LIST.length &&
        EDGE_LIST.every(edge => edge in value && isMeasurement(value[edge]));
    const subtreeDepth = node => 1 + Math.max(0, ...(node.children || []).map(subtreeDepth));
    const subtreeCount = node => 1 +
        (node.children || []).reduce((count, child) => count + subtreeCount(child), 0);

    return class FlowStructure {
        constructor({maxNestingDepth = 8, maxElements = 500, maxSections = 100} = {}) {
            this.maxNestingDepth = maxNestingDepth;
            this.maxElements = maxElements;
            this.maxSections = maxSections;
        }

        createNode(type) {
            if (type === 'heading') return {
                type, content: [{type: 'text', text: 'Heading', marks: []}],
                level: 2, keepWithNext: true,
            };
            if (type === 'static-text') return {type, text: 'Text'};
            if (type === 'paragraph') return {
                type, content: [{type: 'text', text: 'Paragraph', marks: []}], alignment: 'start',
            };
            if (type === 'divider') return {
                type, orientation: 'horizontal', style: 'solid', color: '#666666',
                thickness: measurement(0.5), length: measurement(100),
            };
            if (type === 'spacer') return {type, height: measurement(10)};
            if (type === 'page-break') return {type};

            const common = {
                type,
                children: [],
                margin: box(0),
                padding: box(0),
                minHeight: measurement(type === SECTION_TYPE ? 20 : 10),
                keepTogether: false,
            };

            if (type === SECTION_TYPE) {
                common.startNewPage = false;
            } else if (type !== CONTAINER_TYPE) {
                throw new TypeError(`Unsupported flow node type: ${type}.`);
            }

            return common;
        }

        ensureCapability(layout) {
            if (!layout.capabilities.includes(FLOW_CAPABILITY)) {
                layout.capabilities.push(FLOW_CAPABILITY);
                layout.capabilities.sort();
            }
        }

        assertTarget(layout, node, target, movingNodeId = null) {
            const index = NodeTree.index(layout);

            if (node.type === SECTION_TYPE) {
                if (target.parentId !== null || target.region !== 'sections') {
                    throw new TypeError('Flow sections can only be placed in the sections region.');
                }

                const existingSections = layout.sections.length -
                    (movingNodeId && index.get(movingNodeId)?.parentId === null ? 1 : 0);

                if (existingSections >= this.maxSections) {
                    throw new RangeError('The configured section limit has been reached.');
                }

                return;
            }

            if (![CONTAINER_TYPE, ...CONTENT_TYPES, ...BASIC_TYPES].includes(node.type) || !target.parentId) {
                throw new TypeError('Flow elements require a flow section or container parent.');
            }

            const parent = index.get(target.parentId);

            if (!parent || ![SECTION_TYPE, CONTAINER_TYPE].includes(parent.node.type)) {
                throw new TypeError('The target cannot contain a flow element.');
            }

            if (movingNodeId && NodeTree.contains(node, target.parentId)) {
                throw new TypeError('A flow node cannot be moved into its own subtree.');
            }

            let parentDepth = 1;
            let ancestor = parent;

            while (ancestor.parentId) {
                parentDepth++;
                ancestor = index.get(ancestor.parentId);
            }

            if (parentDepth + subtreeDepth(node) > this.maxNestingDepth) {
                throw new RangeError('The configured nesting limit would be exceeded.');
            }

            if (!movingNodeId) {
                const elementCount = [...index.values()]
                    .filter(location => location.node.type !== SECTION_TYPE).length;

                if (elementCount + subtreeCount(node) > this.maxElements) {
                    throw new RangeError('The configured element limit has been reached.');
                }
            }
        }

        removeUnusedCapability(layout) {
            if (layout.sections.length === 0) {
                layout.capabilities = layout.capabilities.filter(marker => marker !== FLOW_CAPABILITY);
            }
        }

        flatten(layout, selectedId = null) {
            const rows = [];

            const visit = (node, depth, region, parentId, index) => {
                rows.push({
                    ...Json.clone(node),
                    depth,
                    depthStyle: `--document-builder-depth: ${depth}`,
                    region,
                    parentId,
                    index,
                    selected: node.id === selectedId,
                    isSection: node.type === SECTION_TYPE,
                    isContainer: node.type === CONTAINER_TYPE,
                    isHeading: node.type === 'heading',
                    isStaticText: node.type === 'static-text',
                    isParagraph: node.type === 'paragraph',
                    isDivider: node.type === 'divider',
                    isSpacer: node.type === 'spacer',
                    isPageBreak: node.type === 'page-break',
                    canContain: [SECTION_TYPE, CONTAINER_TYPE].includes(node.type),
                    label: ({
                        [SECTION_TYPE]: 'Flow Section', [CONTAINER_TYPE]: 'Flow Container',
                        heading: 'Heading', 'static-text': 'Static Text', paragraph: 'Paragraph',
                        divider: 'Divider', spacer: 'Spacer', 'page-break': 'Page Break',
                    })[node.type],
                });
                (node.children || []).forEach((child, childIndex) => {
                    visit(child, depth + 1, region, node.id, childIndex);
                });
            };

            layout.sections.forEach((section, index) => visit(section, 0, 'sections', null, index));

            return rows;
        }

        breadcrumbs(layout, nodeId) {
            const index = NodeTree.index(layout);
            const result = [];
            let location = index.get(nodeId);

            while (location) {
                result.unshift({
                    id: location.node.id,
                    label: ({
                        [SECTION_TYPE]: 'Flow Section', [CONTAINER_TYPE]: 'Flow Container',
                        heading: 'Heading', 'static-text': 'Static Text', paragraph: 'Paragraph',
                        divider: 'Divider', spacer: 'Spacer', 'page-break': 'Page Break',
                    })[location.node.type],
                    current: location.node.id === nodeId,
                });
                location = location.parentId ? index.get(location.parentId) : null;
            }

            return result;
        }

        validateLayout(layout) {
            const errors = [];
            let elements = 0;
            const validateInline = (content, path) => {
                if (!Array.isArray(content) || content.length > 1000) {
                    errors.push(`${path}.structure`); return;
                }
                content.forEach((item, index) => {
                    const itemPath = `${path}.${index}`;
                    if (!Json.isPlainObject(item)) { errors.push(`${itemPath}.structure`); return; }
                    if (item.type === 'text') {
                        const keys = ['type', 'text', 'marks', 'color'];
                        if (typeof item.text !== 'string' || item.text.length > 10000 ||
                            !Array.isArray(item.marks) || new Set(item.marks).size !== item.marks.length ||
                            item.marks.some(mark => !['bold', 'italic', 'underline'].includes(mark)) ||
                            Object.keys(item).some(key => !keys.includes(key)) ||
                            ('color' in item && !/^#[0-9A-Fa-f]{6}$/.test(item.color))) errors.push(`${itemPath}.values`);
                    } else if (item.type === 'break') {
                        if (Object.keys(item).length !== 1) errors.push(`${itemPath}.structure`);
                    } else if (item.type === 'variable') {
                        if (!/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(item.tokenId || '') ||
                            typeof item.label !== 'string' || item.label.length < 1 || item.label.length > 100 ||
                            Object.keys(item).some(key => !['type', 'tokenId', 'label'].includes(key))) errors.push(`${itemPath}.values`);
                    } else errors.push(`${itemPath}.type`);
                });
            };
            const validateNode = (node, expectedType, depth, path) => {
                if (BASIC_TYPES.includes(expectedType)) {
                    elements++;
                    if (!Json.isPlainObject(node) || node.type !== expectedType ||
                        !/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(node.id || '')) {
                        errors.push(`${path}.structure`); return;
                    }
                    if (expectedType === 'page-break') {
                        if (Object.keys(node).some(key => !['id', 'type'].includes(key))) errors.push(`${path}.structure`);
                    } else if (expectedType === 'spacer') {
                        if (Object.keys(node).some(key => !['id', 'type', 'height'].includes(key)) ||
                            !isMeasurement(node.height) || node.height.value < 0.1 || node.height.value > 500) errors.push(`${path}.values`);
                    } else if (Object.keys(node).some(key => !['id', 'type', 'orientation', 'style', 'color', 'thickness', 'length'].includes(key)) ||
                        !['horizontal', 'vertical'].includes(node.orientation) ||
                        !['solid', 'dashed', 'dotted', 'double'].includes(node.style) ||
                        !/^#[0-9A-Fa-f]{6}$/.test(node.color || '') ||
                        !isMeasurement(node.thickness) || node.thickness.value < 0.1 || node.thickness.value > 20 ||
                        !isMeasurement(node.length) || node.length.value < 1) errors.push(`${path}.values`);
                    return;
                }
                if (CONTENT_TYPES.includes(expectedType)) {
                    elements++;
                    if (!Json.isPlainObject(node) || node.type !== expectedType ||
                        !/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(node.id || '')) {
                        errors.push(`${path}.structure`); return;
                    }
                    if (expectedType === 'static-text') {
                        if (!Json.isPlainObject(node) || Object.keys(node).some(key => !['id', 'type', 'text'].includes(key)) ||
                            typeof node.text !== 'string' || node.text.length > 10000) errors.push(`${path}.values`);
                    } else {
                        const extra = expectedType === 'heading' ? ['level', 'keepWithNext'] : ['alignment'];
                        if (!Json.isPlainObject(node) || Object.keys(node).some(key => !['id', 'type', 'content', ...extra].includes(key))) errors.push(`${path}.structure`);
                        validateInline(node.content, `${path}.content`);
                        if (expectedType === 'heading' && (!Number.isInteger(node.level) || node.level < 1 || node.level > 6 || typeof node.keepWithNext !== 'boolean')) errors.push(`${path}.values`);
                        if (expectedType === 'paragraph' && !['start', 'center', 'end', 'justify'].includes(node.alignment)) errors.push(`${path}.values`);
                    }
                    return;
                }
                const required = expectedType === SECTION_TYPE ?
                    ['id', 'type', 'children', 'margin', 'padding', 'minHeight', 'keepTogether', 'startNewPage'] :
                    ['id', 'type', 'children', 'margin', 'padding', 'minHeight', 'keepTogether'];

                if (!Json.isPlainObject(node) || node.type !== expectedType ||
                    !required.every(key => key in node) ||
                    Object.keys(node).some(key => !required.includes(key))) {
                    errors.push(`${path}.structure`);

                    return;
                }

                if (!Array.isArray(node.children) || !isBox(node.margin) || !isBox(node.padding) ||
                    !isMeasurement(node.minHeight) || typeof node.keepTogether !== 'boolean' ||
                    (expectedType === SECTION_TYPE && typeof node.startNewPage !== 'boolean')) {
                    errors.push(`${path}.values`);

                    return;
                }

                if (depth > this.maxNestingDepth) errors.push(`${path}.depth`);
                if (expectedType === CONTAINER_TYPE && ++elements > this.maxElements) {
                    errors.push('flow.elements.limit');
                }
                node.children.forEach((child, index) => {
                    validateNode(child, child.type, depth + 1, `${path}.children.${index}`);
                });
            };

            if (layout.sections.length > this.maxSections) errors.push('flow.sections.limit');
            layout.sections.forEach((section, index) => {
                validateNode(section, SECTION_TYPE, 1, `flow.sections.${index}`);
            });

            const hasFlow = layout.sections.length > 0;
            const declaresFlow = layout.capabilities.includes(FLOW_CAPABILITY);

            if (hasFlow !== declaresFlow) errors.push('flow.capability');

            return errors;
        }
    };
});
