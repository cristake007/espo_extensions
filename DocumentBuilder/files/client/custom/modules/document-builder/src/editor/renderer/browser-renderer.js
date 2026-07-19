define([
    'document-builder:editor/state/json',
    'document-builder:editor/state/node-tree',
    'document-builder:editor/conditions/condition-evaluator',
], (Json, NodeTree, ConditionEvaluator) => {
    const LABELS = Object.freeze({
        'flow-section': 'Flow Section',
        'flow-container': 'Flow Container',
        heading: 'Heading',
        'static-text': 'Static Text',
        paragraph: 'Paragraph',
        divider: 'Divider',
        spacer: 'Spacer',
        'page-break': 'Page Break',
    });
    const CONTENT_TYPES = Object.freeze(['heading', 'static-text', 'paragraph']);
    const SAMPLE_KEYS = Object.freeze({
        heading: 'editorHeadingSample',
        'static-text': 'editorStaticTextSample',
        paragraph: 'editorParagraphSample',
    });
    const ICONS = Object.freeze({
        'flow-section': 'fa-layer-group',
        'flow-container': 'fa-object-group',
        heading: 'fa-heading',
        'static-text': 'fa-font',
        paragraph: 'fa-align-left',
        divider: 'fa-minus',
        spacer: 'fa-arrows-alt-v',
        'page-break': 'fa-cut',
    });
    const previewKey = identity => JSON.stringify(identity || {});
    const previewText = value => {
        if (!value) return null;
        if (value.state === 'restricted' || value.state === 'forbidden') return '[restricted]';
        if (value.state === 'missing') return '[missing]';
        if (value.state === 'invalid') return '[invalid]';
        if (Array.isArray(value.value)) return value.value.join(', ');
        if (value.value && typeof value.value === 'object') {
            if (value.type === 'currency') return `${value.value.amount} ${value.value.currency}`;
            return '[invalid]';
        }
        return value.value === null || value.value === undefined ? '[missing]' : String(value.value);
    };
    const textFromContent = (content, previewValues = new Map()) => (content || []).map(item => {
        if (item.type === 'break') return '\n';
        if (item.type === 'variable') {
            return previewText(previewValues.get(previewKey(item.identity))) ?? `{{${item.label}}}`;
        }
        return item.type === 'text' ? item.text : '';
    }).join('');
    const bounded = (value, minimum, maximum, fallback) =>
        Number.isFinite(value) && value >= minimum && value <= maximum ? value : fallback;

    return class BrowserRenderer {
        constructor({styleResolver, pageGeometry}) {
            this.styleResolver = styleResolver;
            this.pageGeometry = pageGeometry;
        }

        render(layout, {
            selectedId = null,
            zoom = 100,
            previewValues = new Map(),
            evaluateConditions = false,
        } = {}) {
            const source = Json.clone(layout);
            const locations = NodeTree.index(source);
            const rows = [];
            const hidden = new Set();
            const pageHeight = this.printableHeight(source.document);
            let pageNumber = 1;
            let occupiedHeight = 0;

            if (evaluateConditions) {
                locations.forEach(location => {
                    if (!location.node.condition) return;
                    const result = ConditionEvaluator.evaluate(location.node.condition, previewValues);
                    if (!result.visible) {
                        hidden.add(result.target === 'parent' ?
                            (location.parentId || location.node.id) : location.node.id);
                    }
                });
            }

            const visit = (node, depth, region, parentId, index) => {
                if (hidden.has(node.id)) return;
                const location = locations.get(node.id);
                const estimate = this.estimateHeight(node);
                const explicitBreak = node.type === 'page-break' ||
                    (node.type === 'flow-section' && node.startNewPage && rows.length > 0);
                const automaticBreak = !explicitBreak && occupiedHeight > 0 &&
                    occupiedHeight + estimate > pageHeight;

                if (explicitBreak || automaticBreak) {
                    pageNumber++;
                    occupiedHeight = 0;
                }

                const px = value => this.pageGeometry.millimetresToPixels(value, zoom);
                const effectiveStyle = this.styleResolver.resolve(source, node.id);
                const flowStyle = [
                    `--document-builder-depth: ${depth}`,
                    '--document-builder-margin-left: 0px',
                ];
                const canContain = ['flow-section', 'flow-container'].includes(node.type);
                const orientation = node.orientation === 'vertical' ? 'vertical' : 'horizontal';
                const lineStyle = ['solid', 'dashed', 'dotted', 'double'].includes(node.lineStyle) ?
                    node.lineStyle : 'solid';
                const lineColor = /^#[0-9A-Fa-f]{6}$/.test(node.color || '') ? node.color : '#666666';
                const plainText = node.type === 'static-text' ? node.text : textFromContent(node.content, previewValues);
                const isContent = CONTENT_TYPES.includes(node.type);
                const isEmpty = isContent && plainText.trim() === '';

                if (canContain) {
                    flowStyle.push(
                        `--document-builder-margin-left: ${px(node.margin.left.value)}px`,
                        `min-height: ${px(node.minHeight.value)}px`,
                        `margin: ${px(node.margin.top.value)}px ${px(node.margin.right.value)}px ` +
                            `${px(node.margin.bottom.value)}px ${px(node.margin.left.value)}px`,
                        `padding: ${px(node.padding.top.value)}px ${px(node.padding.right.value)}px ` +
                            `${px(node.padding.bottom.value)}px ${px(node.padding.left.value)}px`,
                    );
                }
                if (node.type === 'spacer') {
                    flowStyle.push(`height: ${px(bounded(node.height?.value, 0.1, 500, 10))}px`);
                }
                flowStyle.push(this.styleResolver.toCss(effectiveStyle, px));

                rows.push({
                    ...Json.clone(node),
                    depth,
                    region,
                    parentId,
                    index,
                    selected: node.id === selectedId,
                    label: LABELS[node.type],
                    badgeLabel: canContain ? 'Structure' : 'Element',
                    iconClass: ICONS[node.type],
                    depthLabel: depth + 1,
                    hasParent: parentId !== null,
                    childCount: Array.isArray(node.children) ? node.children.length : 0,
                    pageNumber,
                    startsPage: explicitBreak || automaticBreak,
                    automaticPageBreak: automaticBreak,
                    canContain,
                    isSection: node.type === 'flow-section',
                    isContainer: node.type === 'flow-container',
                    isHeading: node.type === 'heading',
                    isStaticText: node.type === 'static-text',
                    isParagraph: node.type === 'paragraph',
                    isDivider: node.type === 'divider',
                    isSpacer: node.type === 'spacer',
                    isPageBreak: node.type === 'page-break',
                    isContent,
                    isEmpty,
                    hasContent: isContent && !isEmpty,
                    sampleKey: isEmpty ? SAMPLE_KEYS[node.type] : null,
                    flowStyle: flowStyle.filter(Boolean).join('; '),
                    dividerOrientation: orientation,
                    dividerStyle: node.type === 'divider' ? (orientation === 'horizontal' ? [
                        `width: ${px(bounded(node.length?.value, 1, 2000, 100))}px`,
                        `border-top: ${px(bounded(node.thickness?.value, 0.1, 20, 0.5))}px ` +
                            `${lineStyle} ${lineColor}`,
                    ] : [
                        `height: ${px(bounded(node.length?.value, 1, 2000, 100))}px`,
                        `border-left: ${px(bounded(node.thickness?.value, 0.1, 20, 0.5))}px ` +
                            `${lineStyle} ${lineColor}`,
                    ]).join('; ') : '',
                    canMoveUp: location.index > 0,
                    canMoveDown: location.index < location.container.length - 1,
                });

                if (node.type !== 'page-break') occupiedHeight += estimate;
                (node.children || []).forEach((child, childIndex) => {
                    visit(child, depth + 1, region, node.id, childIndex);
                });
            };

            source.sections.forEach((section, index) => visit(section, 0, 'sections', null, index));

            return Object.freeze({
                rows,
                pageCount: pageNumber,
                nodeCount: rows.length,
            });
        }

        printableHeight(document) {
            const dimensions = this.pageGeometry.getPage(
                document.page.size,
                document.page.orientation,
            );

            return Math.max(1, dimensions.heightMm -
                document.page.margins.top.value - document.page.margins.bottom.value);
        }

        estimateHeight(node) {
            if (node.type === 'page-break') return 0;
            if (node.type === 'spacer') return bounded(node.height?.value, 0.1, 500, 10);
            if (node.type === 'divider') return node.orientation === 'vertical' ?
                bounded(node.length?.value, 1, 2000, 100) : 4;
            if (['flow-section', 'flow-container'].includes(node.type)) {
                return bounded(node.minHeight?.value, 0, 2000, 10) +
                    (node.margin?.top?.value || 0) + (node.margin?.bottom?.value || 0);
            }
            const text = node.type === 'static-text' ? node.text : textFromContent(node.content);

            return Math.max(8, Math.ceil(Math.max(1, text.length) / 70) * 6);
        }
    };
});
