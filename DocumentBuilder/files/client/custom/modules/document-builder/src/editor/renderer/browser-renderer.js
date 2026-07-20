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
        variable: 'Variable',
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
        if (item.type === 'list') {
            return (item.items || []).map(listItem => textFromContent(listItem, previewValues)).join('\n');
        }
        if (item.type === 'variable') {
            return previewText(previewValues.get(previewKey(item.identity))) ?? `{{${item.label}}}`;
        }
        return item.type === 'text' ? item.text : '';
    }).join('');
    const contentOf = node => Array.isArray(node.content) ? node.content :
        node.type === 'static-text' && typeof node.text === 'string' ?
            [{type: 'text', text: node.text, marks: []}] : [];
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
            const tree = [];
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
                if (hidden.has(node.id)) return null;
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
                const flowStyle = [];
                const canContain = ['flow-section', 'flow-container'].includes(node.type);
                const orientation = node.orientation === 'vertical' ? 'vertical' : 'horizontal';
                const lineStyle = ['solid', 'dashed', 'dotted', 'double'].includes(node.lineStyle) ?
                    node.lineStyle : 'solid';
                const lineColor = /^#[0-9A-Fa-f]{6}$/.test(node.color || '') ? node.color : '#666666';
                const content = contentOf(node);
                const plainText = textFromContent(content, previewValues);
                const variableText = node.type === 'variable' ?
                    previewText(previewValues.get(previewKey(node.identity))) ?? `{{${node.label}}}` : null;
                const isContent = CONTENT_TYPES.includes(node.type);
                const isEmpty = isContent && plainText.trim() === '';

                if (canContain) {
                    flowStyle.push(
                        `--document-builder-margin-left: ${px(node.margin.left.value)}px`,
                        `--document-builder-node-padding-top: ${px(node.padding.top.value)}px`,
                        `--document-builder-node-padding-right: ${px(node.padding.right.value)}px`,
                        `--document-builder-node-padding-bottom: ${px(node.padding.bottom.value)}px`,
                        `--document-builder-node-padding-left: ${px(node.padding.left.value)}px`,
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
                if (['heading', 'paragraph'].includes(node.type) &&
                    ['start', 'center', 'end', 'justify'].includes(node.alignment)) {
                    flowStyle.push(`text-align: ${{start: 'left', end: 'right'}[node.alignment] || node.alignment}`);
                }

                const projection = {
                    ...Json.clone(node),
                    content: Json.clone(content),
                    children: [],
                    region,
                    parentId,
                    index,
                    depth,
                    selected: node.id === selectedId,
                    label: LABELS[node.type],
                    pageNumber,
                    startsPage: explicitBreak || automaticBreak,
                    automaticPageBreak: automaticBreak,
                    canContain,
                    isSection: node.type === 'flow-section',
                    isContainer: node.type === 'flow-container',
                    isHeading: node.type === 'heading',
                    isStaticText: node.type === 'static-text',
                    isParagraph: node.type === 'paragraph',
                    isVariable: node.type === 'variable',
                    isDivider: node.type === 'divider',
                    isSpacer: node.type === 'spacer',
                    isPageBreak: node.type === 'page-break',
                    isContent,
                    isEmpty,
                    hasContent: isContent && !isEmpty,
                    hasCondition: Boolean(node.condition),
                    variableText,
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
                };
                rows.push(projection);

                if (node.type !== 'page-break') occupiedHeight += estimate;
                projection.children = (node.children || []).map((child, childIndex) =>
                    visit(child, depth + 1, region, node.id, childIndex)
                ).filter(Boolean);

                return projection;
            };

            source.sections.forEach((section, index) => {
                const projection = visit(section, 0, 'sections', null, index);
                if (projection) tree.push(projection);
            });

            return Object.freeze({
                rows,
                tree,
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
            const text = node.type === 'variable' ? node.label : textFromContent(contentOf(node));

            return Math.max(8, Math.ceil(Math.max(1, text.length) / 70) * 6);
        }
    };
});
