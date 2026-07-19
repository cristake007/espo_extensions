define([
    'document-builder:editor/state/json',
    'document-builder:editor/state/node-tree',
    'document-builder:editor/variables/variable-identity',
    'document-builder:editor/variables/variable-presentation',
], (Json, NodeTree, VariableIdentity, VariablePresentation) => {
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
                type, orientation: 'horizontal', lineStyle: 'solid', color: '#666666',
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
            const validateStyle = (style, path) => {
                if (style === undefined) return;
                const keys=['margin','padding','backgroundColor','border','opacity','horizontalAlignment','verticalAlignment','width','height','color','fontFamily','fontSize','fontWeight','fontStyle','textDecoration','lineHeight','letterSpacing','textTransform'];
                if (!Json.isPlainObject(style)||Object.keys(style).some(key=>!keys.includes(key))) { errors.push(`${path}.structure`); return; }
                ['backgroundColor','color'].forEach(key=>{if(key in style&&!/^#[0-9A-Fa-f]{6}$/.test(style[key]))errors.push(`${path}.${key}`);});
                if ('fontFamily' in style && (typeof style.fontFamily!=='string'||style.fontFamily.length<1||style.fontFamily.length>100||!/^[A-Za-z][A-Za-z0-9 ._-]*$/.test(style.fontFamily))) errors.push(`${path}.fontFamily`);
                const enums={fontWeight:['normal','bold','100','200','300','400','500','600','700','800','900'],fontStyle:['normal','italic'],textDecoration:['none','underline'],textTransform:['none','uppercase','lowercase','capitalize'],horizontalAlignment:['start','center','end','stretch'],verticalAlignment:['start','center','end']};
                Object.entries(enums).forEach(([key,values])=>{if(key in style&&!values.includes(style[key]))errors.push(`${path}.${key}`);});
                if ('opacity' in style&&(!Number.isFinite(style.opacity)||style.opacity<0||style.opacity>1))errors.push(`${path}.opacity`);
                if ('lineHeight' in style&&(!Number.isFinite(style.lineHeight)||style.lineHeight<.5||style.lineHeight>5))errors.push(`${path}.lineHeight`);
                if ('fontSize' in style&&(!Json.isPlainObject(style.fontSize)||style.fontSize.unit!=='pt'||!Number.isFinite(style.fontSize.value)||style.fontSize.value<1||style.fontSize.value>512))errors.push(`${path}.fontSize`);
                if ('letterSpacing' in style&&(!Json.isPlainObject(style.letterSpacing)||style.letterSpacing.unit!=='pt'||!Number.isFinite(style.letterSpacing.value)||style.letterSpacing.value < -20||style.letterSpacing.value>100))errors.push(`${path}.letterSpacing`);
                ['margin','padding'].forEach(key=>{if(key in style&&!isBox(style[key]))errors.push(`${path}.${key}`);});
                ['width','height'].forEach(key=>{if(!(key in style))return;const value=style[key];if(!Json.isPlainObject(value)||!Number.isFinite(value.value)||value.value<0||!['mm','percent'].includes(value.unit)||(value.unit==='mm'&&value.value>2000)||(value.unit==='percent'&&value.value>100)||Object.keys(value).some(item=>!['value','unit'].includes(item)))errors.push(`${path}.${key}`);});
                if ('border' in style) { const border=style.border; if(!Json.isPlainObject(border)||Object.keys(border).some(key=>!['width','style','color'].includes(key))||!Json.isPlainObject(border.width)||border.width.unit!=='pt'||!Number.isFinite(border.width.value)||border.width.value<0||border.width.value>512||!['none','solid','dashed','dotted','double'].includes(border.style)||!/^#[0-9A-Fa-f]{6}$/.test(border.color||''))errors.push(`${path}.border`); }
            };
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
                        let scalarIdentity = false;
                        let validPresentation = false;
                        try {
                            scalarIdentity = VariableIdentity.usage(item.identity) === 'scalar';
                            VariablePresentation.create(item.presentation);
                            validPresentation = true;
                        } catch (error) {}
                        if (!/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(item.tokenId || '') ||
                            typeof item.label !== 'string' || item.label.length < 1 || item.label.length > 100 ||
                            !scalarIdentity || !validPresentation ||
                            Object.keys(item).some(key => ![
                                'type', 'tokenId', 'label', 'identity', 'presentation',
                            ].includes(key))) errors.push(`${itemPath}.values`);
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
                        if (Object.keys(node).some(key => !['id', 'type', 'style'].includes(key))) errors.push(`${path}.structure`);
                    } else if (expectedType === 'spacer') {
                        if (Object.keys(node).some(key => !['id', 'type', 'height', 'style'].includes(key)) ||
                            !isMeasurement(node.height) || node.height.value < 0.1 || node.height.value > 500) errors.push(`${path}.values`);
                    } else if (Object.keys(node).some(key => !['id', 'type', 'orientation', 'lineStyle', 'color', 'thickness', 'length', 'style'].includes(key)) ||
                        !['horizontal', 'vertical'].includes(node.orientation) ||
                        !['solid', 'dashed', 'dotted', 'double'].includes(node.lineStyle) ||
                        !/^#[0-9A-Fa-f]{6}$/.test(node.color || '') ||
                        !isMeasurement(node.thickness) || node.thickness.value < 0.1 || node.thickness.value > 20 ||
                        !isMeasurement(node.length) || node.length.value < 1) errors.push(`${path}.values`);
                    validateStyle(node.style, `${path}.style`); return;
                }
                if (CONTENT_TYPES.includes(expectedType)) {
                    elements++;
                    if (!Json.isPlainObject(node) || node.type !== expectedType ||
                        !/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(node.id || '')) {
                        errors.push(`${path}.structure`); return;
                    }
                    if (expectedType === 'static-text') {
                        if (!Json.isPlainObject(node) || Object.keys(node).some(key => !['id', 'type', 'text', 'style'].includes(key)) ||
                            typeof node.text !== 'string' || node.text.length > 10000) errors.push(`${path}.values`);
                    } else {
                        const extra = expectedType === 'heading' ? ['level', 'keepWithNext', 'style'] : ['alignment', 'style'];
                        if (!Json.isPlainObject(node) || Object.keys(node).some(key => !['id', 'type', 'content', ...extra].includes(key))) errors.push(`${path}.structure`);
                        validateInline(node.content, `${path}.content`);
                        if (expectedType === 'heading' && (!Number.isInteger(node.level) || node.level < 1 || node.level > 6 || typeof node.keepWithNext !== 'boolean')) errors.push(`${path}.values`);
                        if (expectedType === 'paragraph' && !['start', 'center', 'end', 'justify'].includes(node.alignment)) errors.push(`${path}.values`);
                    }
                    validateStyle(node.style, `${path}.style`); return;
                }
                const required = expectedType === SECTION_TYPE ?
                    ['id', 'type', 'children', 'margin', 'padding', 'minHeight', 'keepTogether', 'startNewPage'] :
                    ['id', 'type', 'children', 'margin', 'padding', 'minHeight', 'keepTogether'];
                const allowed = [...required, 'style'];

                if (!Json.isPlainObject(node) || node.type !== expectedType ||
                    !required.every(key => key in node) ||
                    Object.keys(node).some(key => !allowed.includes(key))) {
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
                validateStyle(node.style, `${path}.style`);
                if (expectedType === CONTAINER_TYPE && ++elements > this.maxElements) {
                    errors.push('flow.elements.limit');
                }
                node.children.forEach((child, index) => {
                    validateNode(child, child.type, depth + 1, `${path}.children.${index}`);
                });
            };

            if (layout.sections.length > this.maxSections) errors.push('flow.sections.limit');
            validateStyle(layout.document.style, 'document.style');
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
