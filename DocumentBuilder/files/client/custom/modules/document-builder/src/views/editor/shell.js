define([
    'view',
    'document-builder:editor/state/editor-state',
    'document-builder:editor/state/node-tree',
    'document-builder:editor/validation/layout-precheck',
    'document-builder:services/draft-api',
    'document-builder:editor/save/draft-save-coordinator',
    'document-builder:editor/save/keyboard',
    'document-builder:editor/save/dirty-guard',
    'document-builder:editor/commands/update-document',
    'document-builder:editor/geometry/page-geometry',
    'document-builder:editor/page-settings',
    'document-builder:editor/flow/flow-structure',
    'document-builder:editor/content/rich-text',
    'document-builder:editor/content/wysiwyg',
    'document-builder:editor/style/style-resolver',
    'document-builder:editor/renderer/browser-renderer',
    'document-builder:editor/canvas/document-canvas',
    'document-builder:editor/validation/editor-validator',
    'document-builder:editor/commands/add-flow-node',
    'document-builder:editor/commands/move-flow-node',
    'document-builder:editor/commands/remove-flow-node',
    'document-builder:editor/commands/duplicate-node',
    'document-builder:editor/commands/update-node',
    'document-builder:editor/commands/update-data-source',
    'document-builder:services/entity-catalogue-api',
    'document-builder:services/entity-metadata-api',
    'document-builder:services/preview-api',
    'document-builder:editor/variables/metadata-browser',
    'document-builder:editor/variables/variable-presentation',
    'document-builder:editor/conditions/condition-builder',
    'document-builder:editor/commands/update-condition',
    'document-builder:editor/commands/update-page-chrome',
], (
    View,
    EditorState,
    NodeTree,
    LayoutPrecheck,
    DraftApi,
    DraftSaveCoordinator,
    Keyboard,
    DirtyGuard,
    UpdateDocumentCommand,
    PageGeometry,
    PageSettings,
    FlowStructure,
    RichText,
    Wysiwyg,
    StyleResolver,
    BrowserRenderer,
    DocumentCanvas,
    EditorValidator,
    AddFlowNodeCommand,
    MoveFlowNodeCommand,
    RemoveFlowNodeCommand,
    DuplicateNodeCommand,
    UpdateNodeCommand,
    UpdateDataSourceCommand,
    EntityCatalogueApi,
    EntityMetadataApi,
    PreviewApi,
    MetadataBrowser,
    VariablePresentation,
    ConditionBuilder,
    UpdateConditionCommand,
    UpdatePageChromeCommand,
) => {
    return class extends View {
        template = 'document-builder:editor/shell'

        events = {
            'click [data-action="backToTemplate"]': 'actionBackToTemplate',
            'click [data-action="retry"]': 'actionRetry',
            'click [data-action="undo"]': 'actionUndo',
            'click [data-action="redo"]': 'actionRedo',
            'click [data-action="save"]': 'actionSave',
            'click [data-action="previewSample"]': 'actionPreviewSample',
            'click [data-action="previewRecord"]': 'actionPreviewRecord',
            'click [data-action="pdfProof"]': 'actionPdfProof',
            'click [data-action="backToEdit"]': 'actionBackToEdit',
            'click [data-action="closePdfPreview"]': 'actionClosePdfPreview',
            'input [data-preview-record-id]': 'inputPreviewRecordId',
            'click [data-action="zoomIn"]': 'actionZoomIn',
            'click [data-action="zoomOut"]': 'actionZoomOut',
            'click [data-action="fitWidth"]': 'actionFitWidth',
            'click [data-action="fitPage"]': 'actionFitPage',
            'change [data-page-setting]': 'changePageSetting',
            'change [data-chrome-setting]': 'changeChromeSetting',
            'change [data-source-setting]': 'changeSourceSetting',
            'click [data-action="retryEntityCatalogue"]': 'actionRetryEntityCatalogue',
            'input [data-variable-search]': 'inputVariableSearch',
            'click [data-action="toggleMetadataRelationship"]': 'actionToggleMetadataRelationship',
            'click [data-action="retryMetadataNode"]': 'actionRetryMetadataNode',
            'click [data-action="focusVariables"]': 'actionFocusVariables',
            'click [data-action="showElementsTab"]': 'actionShowElementsTab',
            'click [data-action="showPropertiesTab"]': 'actionShowPropertiesTab',
            'click [data-action="addFlowSection"]': 'actionAddFlowSection',
            'click [data-action="addFlowContainer"]': 'actionAddFlowContainer',
            'click [data-action="addHeading"]': 'actionAddContent',
            'click [data-action="addStaticText"]': 'actionAddContent',
            'click [data-action="addParagraph"]': 'actionAddContent',
            'click [data-action="addDivider"]': 'actionAddContent',
            'click [data-action="addSpacer"]': 'actionAddContent',
            'click [data-action="addPageBreak"]': 'actionAddContent',
            'click [data-action="addVariable"]': 'actionAddVariable',
            'click [data-action="selectFlowNode"]': 'actionSelectFlowNode',
            'keydown [data-action="selectFlowNode"]': 'handleNodeKeydown',
            'click [data-action="selectBreadcrumb"]': 'actionSelectFlowNode',
            'click [data-action="focusValidationIssue"]': 'actionFocusValidationIssue',
            'click [data-action="removeFlowNode"]': 'actionRemoveFlowNode',
            'click [data-action="editFlowNode"]': 'actionEditFlowNode',
            'click [data-action="duplicateFlowNode"]': 'actionDuplicateFlowNode',
            'click [data-action="moveFlowUp"]': 'actionMoveFlowUp',
            'click [data-action="moveFlowDown"]': 'actionMoveFlowDown',
            'change [data-flow-setting]': 'changeFlowSetting',
            'change [data-content-setting]': 'changeContentSetting',
            'change [data-basic-flow-setting]': 'changeBasicFlowSetting',
            'change [data-style-setting]': 'changeStyleSetting',
            'paste [data-content-setting="text"]': 'pasteContentText',
            'focusin [data-rich-editor]': 'activateRichTextEditor',
            'mouseup [data-rich-editor]': 'captureRichTextSelection',
            'keyup [data-rich-editor]': 'captureRichTextSelection',
            'input [data-rich-editor]': 'inputRichText',
            'paste [data-rich-editor]': 'pasteRichText',
            'keydown [data-rich-editor]': 'handleRichTextKeydown',
            'mousedown [data-rich-mark], [data-rich-command]': 'preserveRichTextSelection',
            'click [data-rich-mark]': 'actionToggleRichMark',
            'click [data-rich-command]': 'actionRichTextCommand',
            'change [data-rich-color]': 'changeRichColor',
            'click [data-action="insertMetadataVariable"]': 'actionInsertMetadataVariable',
            'change [data-variable-presentation]': 'changeVariablePresentation',
            'click [data-action="applyCondition"]': 'actionApplyCondition',
            'click [data-action="addConditionRule"]': 'actionAddConditionRule',
            'click [data-action="removeConditionRule"]': 'actionRemoveConditionRule',
            'click [data-action="removeCondition"]': 'actionRemoveCondition',
            'dragstart [draggable="true"]': 'handleFlowDragStart',
            'dragover [data-flow-drop]': 'handleFlowDragOver',
            'dragleave [data-flow-drop]': 'handleFlowDragLeave',
            'drop [data-flow-drop]': 'handleFlowDrop',
            'dragend [draggable="true"]': 'handleFlowDragEnd',
            'mouseover [data-document-canvas]': 'handleCanvasHover',
            'focusin [data-document-canvas]': 'handleCanvasHover',
            'mouseleave [data-document-canvas]': 'hideCanvasHover',
        }

        setup() {
            this.state = 'loading';
            this.errorMessage = null;
            this.loadStarted = false;
            this.isRemoved = false;
            this.shouldFocusEntry = false;
            this.editorState = null;
            this.saveCoordinator = null;
            this.conflictDialogOpen = false;
            this.dirtyGuard = new DirtyGuard(this.getRouter(), this);
            this.keydownHandler = event => this.handleKeydown(event);
            this.selectionchangeHandler = () => this.captureDocumentSelection();
            const config = this.getConfig().get('documentBuilder') || {};
            const metadataDefaults = this.getMetadata().get(
                ['app', 'documentBuilder', 'defaults'],
            ) || {};

            this.customPageSizes = config.customPageSizeList ||
                metadataDefaults.customPageSizeList || [];
            this.allowedFonts = config.allowedFontList ||
                metadataDefaults.allowedFontList || ['DejaVu Sans'];
            this.flowLimits = {
                maxNestingDepth: config.maxNestingDepth || metadataDefaults.maxNestingDepth || 8,
                maxElements: config.maxElements || metadataDefaults.maxElements || 500,
                maxSections: config.maxSections || metadataDefaults.maxSections || 100,
            };
            this.flowStructure = new FlowStructure(this.flowLimits);
            this.styleResolver = new StyleResolver(this.allowedFonts);
            this.flowDrag = null;
            this.pageGeometry = new PageGeometry(this.customPageSizes);
            this.browserRenderer = new BrowserRenderer({
                styleResolver: this.styleResolver,
                pageGeometry: this.pageGeometry,
            });
            this.documentCanvas = new DocumentCanvas();
            this.editorValidator = new EditorValidator(this.customPageSizes, this.flowLimits);
            this.entityCatalogueApi = new EntityCatalogueApi();
            this.entityMetadataApi = new EntityMetadataApi();
            this.previewApi = new PreviewApi();
            this.previewStatus = 'idle';
            this.previewMode = null;
            this.previewRecordId = '';
            this.previewValues = new Map();
            this.previewPdfUrl = null;
            this.previewPageCount = 0;
            this.previewWarningCount = 0;
            this.canvasPreviewOpen = false;
            this.pdfProofLoading = false;
            this.pdfProofError = false;
            this.pendingCanvasScroll = null;
            this.entityCatalogue = [];
            this.entityCatalogueStatus = 'loading';
            this.metadataNodes = new Map();
            this.expandedMetadataPaths = new Set();
            this.metadataGeneration = 0;
            this.variableSearch = '';
            this.variablePresentationDraft = VariablePresentation.defaults();
            this.standaloneVariableDraft = null;
            this.maxRelationshipDepth = config.maxRelationshipDepth ||
                metadataDefaults.maxRelationshipDepth || 2;
            this.pendingFocusNodeId = null;
            this.rightSidebarTab = 'elements';
            this.richTextSelection = null;
            this.zoom = 100;

            document.addEventListener('keydown', this.keydownHandler);
            document.addEventListener('selectionchange', this.selectionchangeHandler);
        }

        data() {
            const pageSettings = this.getPageSettingsData();
            const flow = this.getFlowData();
            const validation = this.getValidationData();
            const source = this.getSourceData();
            const variableBrowser = this.getVariableBrowserData();

            return {
                isLoading: this.state === 'loading',
                isReady: this.state === 'ready',
                isError: this.state === 'error',
                canRetry: this.errorMessage === 'editorLoadFailed',
                errorMessage: this.errorMessage,
                templateName: this.model.get('name') || this.translate('Untitled'),
                revision: this.model.get('revision'),
                canUndo: this.editorState && !this.isSaveBusy() ? this.editorState.canUndo() : false,
                canRedo: this.editorState && !this.isSaveBusy() ? this.editorState.canRedo() : false,
                isDirty: this.editorState ? this.editorState.isDirty() : false,
                canSave: Boolean(
                    this.editorState &&
                    this.editorState.isDirty() &&
                    !this.isSaveBusy() &&
                    !validation.validationBlocking
                ),
                isSaving: this.saveCoordinator ? this.saveCoordinator.status === 'saving' : false,
                isReloading: this.saveCoordinator ? this.saveCoordinator.status === 'reloading' : false,
                isSaved: this.saveCoordinator ? this.saveCoordinator.status === 'saved' : false,
                saveError: this.saveCoordinator ? this.saveCoordinator.errorMessage : null,
                previewStatus: this.previewStatus,
                previewLoading: this.previewStatus === 'loading',
                previewActive: this.previewStatus === 'ready' && Boolean(this.editorState && !this.editorState.isDirty()),
                previewError: this.previewStatus === 'error',
                previewMode: this.previewMode,
                previewRecordId: this.previewRecordId,
                previewPdfOpen: Boolean(this.previewPdfUrl),
                previewPdfUrl: this.previewPdfUrl,
                previewPageCount: this.previewPageCount,
                previewWarningCount: this.previewWarningCount,
                canPreview: Boolean(this.editorState && !this.editorState.isDirty() && this.previewStatus !== 'loading'),
                canPreviewRecord: Boolean(this.editorState && !this.editorState.isDirty() &&
                    this.previewStatus !== 'loading' && /^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(this.previewRecordId)),
                cleanPreviewActive: Boolean(this.canvasPreviewOpen && this.previewStatus === 'ready' &&
                    this.editorState && !this.editorState.isDirty()),
                pdfProofLoading: this.pdfProofLoading,
                pdfProofError: this.pdfProofError,
                canPdfProof: Boolean(this.editorState && !this.editorState.isDirty() && !this.pdfProofLoading),
                canAddStandaloneVariable: Boolean(this.standaloneVariableDraft && flow.canAddFlowContainer),
                standaloneVariableLabel: this.standaloneVariableDraft?.label || '',
                rightTabElements: this.rightSidebarTab === 'elements',
                rightTabProperties: this.rightSidebarTab === 'properties',
                ...pageSettings,
                ...flow,
                ...validation,
                ...source,
                ...variableBrowser,
            };
        }

        getFlowData() {
            if (!this.editorState) {
                return {
                    selectedFlowNode: null,
                    flowBreadcrumbs: [],
                    approximatedPageCount: 0,
                };
            }

            const layout = this.editorState.getLayout();
            const selectedId = this.editorState.getSelectedId();
            const locations = NodeTree.index(layout);
            const rendered = this.browserRenderer.render(layout, {
                selectedId: this.canvasPreviewOpen ? null : selectedId,
                zoom: this.zoom,
                previewValues: this.editorState.isDirty() ? new Map() : this.previewValues,
                evaluateConditions: this.previewStatus === 'ready' && !this.editorState.isDirty(),
            });
            const selected = selectedId ? locations.get(selectedId) : null;
            const effectiveStyle = selected ? this.styleResolver.resolve(layout, selectedId) : null;
            const condition = selected?.node.condition || null;
            const conditionRules = condition?.rules || [{
                identity: null, valueType: 'text', operator: 'exists', operand: null,
            }];

            return {
                approximatedPageCount: rendered.pageCount,
                selectedFlowNode: selected ? {
                    ...selected.node,
                    label: ({
                        'flow-section': 'Flow Section', 'flow-container': 'Flow Container',
                        heading: 'Heading', 'static-text': 'Static Text', paragraph: 'Paragraph',
                        variable: 'Variable',
                        divider: 'Divider', spacer: 'Spacer', 'page-break': 'Page Break',
                    })[selected.node.type],
                    isSection: selected.node.type === 'flow-section',
                    isContainer: selected.node.type === 'flow-container',
                    isHeading: selected.node.type === 'heading',
                    isStaticText: selected.node.type === 'static-text',
                    isParagraph: selected.node.type === 'paragraph',
                    isVariable: selected.node.type === 'variable',
                    isDivider: selected.node.type === 'divider',
                    isSpacer: selected.node.type === 'spacer',
                    isPageBreak: selected.node.type === 'page-break',
                    isBasicFlow: ['divider', 'spacer', 'page-break'].includes(selected.node.type),
                    horizontal: selected.node.orientation === 'horizontal',
                    vertical: selected.node.orientation === 'vertical',
                    solid: selected.node.lineStyle === 'solid',
                    dashed: selected.node.lineStyle === 'dashed',
                    dotted: selected.node.lineStyle === 'dotted',
                    double: selected.node.lineStyle === 'double',
                    isContent: ['heading', 'static-text', 'paragraph'].includes(selected.node.type),
                    variablePath: selected.node.type === 'variable' ?
                        (selected.node.identity.path || [selected.node.identity.name]).join('.') : '',
                    variablePresentation: selected.node.type === 'variable' ?
                        this.variablePresentationData(selected.node.presentation) : null,
                    plainText: selected.node.type === 'static-text' ? selected.node.text :
                        RichText.toPlainText(selected.node.content),
                    alignmentChoices: {
                        start: selected.node.alignment === 'start',
                        center: selected.node.alignment === 'center',
                        end: selected.node.alignment === 'end',
                        justify: selected.node.alignment === 'justify',
                    },
                    hasCondition: Boolean(condition),
                    conditionEditor: {
                        targets: [['element', 'Element'], ['parent', 'Parent']].map(([value, label]) => ({
                            value, label, selected: value === (condition?.target || 'element'),
                        })),
                        modes: [['all', 'All rules'], ['any', 'Any rule']].map(([value, label]) => ({
                            value, label, selected: value === (condition?.mode || 'all'),
                        })),
                        rules: conditionRules.map((rule, index) => ({
                            index,
                            path: rule.identity?.path?.join('.') || '',
                            operand: rule.operand ?? '',
                            canRemove: conditionRules.length > 1,
                            valueTypes: [
                                ['text', 'Text'], ['date', 'Date'], ['datetime', 'Date/time'],
                                ['number', 'Number'], ['currency', 'Currency'], ['boolean', 'Boolean'],
                                ['enum', 'Enum'], ['multiValue', 'Multiple values'],
                            ].map(([value, label]) => ({
                                value, label, selected: value === rule.valueType,
                            })),
                            operators: [
                                ['exists', 'Exists'], ['missing', 'Missing'], ['equals', 'Equals'],
                                ['notEquals', 'Does not equal'], ['contains', 'Contains'],
                                ['startsWith', 'Starts with'], ['greaterThan', 'Greater than'],
                                ['greaterOrEqual', 'Greater or equal'], ['lessThan', 'Less than'],
                                ['lessOrEqual', 'Less or equal'], ['isTrue', 'Is true'],
                                ['isFalse', 'Is false'],
                            ].map(([value, label]) => ({
                                value, label, selected: value === rule.operator,
                            })),
                        })),
                    },
                    effectiveStyle,
                    inspectorStyle: {...effectiveStyle,
                        backgroundColor: effectiveStyle.backgroundColor || '#FFFFFF',
                        opacity: effectiveStyle.opacity ?? 1,
                        letterSpacing: effectiveStyle.letterSpacing || {value: 0, unit: 'pt'},
                        width: effectiveStyle.width || {value: '', unit: 'mm'},
                        height: effectiveStyle.height || {value: '', unit: 'mm'},
                        border: effectiveStyle.border || {width: {value: 0, unit: 'pt'}, style: 'none', color: '#000000'},
                    },
                    styleChoices: {
                        weightNormal: !effectiveStyle.fontWeight || effectiveStyle.fontWeight === 'normal',
                        weightBold: effectiveStyle.fontWeight === 'bold', weight400: effectiveStyle.fontWeight === '400', weight700: effectiveStyle.fontWeight === '700',
                        fontNormal: !effectiveStyle.fontStyle || effectiveStyle.fontStyle === 'normal', fontItalic: effectiveStyle.fontStyle === 'italic',
                        decorationNone: !effectiveStyle.textDecoration || effectiveStyle.textDecoration === 'none', decorationUnderline: effectiveStyle.textDecoration === 'underline',
                        transformNone: !effectiveStyle.textTransform || effectiveStyle.textTransform === 'none', transformUpper: effectiveStyle.textTransform === 'uppercase', transformLower: effectiveStyle.textTransform === 'lowercase', transformCapitalize: effectiveStyle.textTransform === 'capitalize',
                        alignStart: !effectiveStyle.horizontalAlignment || effectiveStyle.horizontalAlignment === 'start', alignCenter: effectiveStyle.horizontalAlignment === 'center', alignEnd: effectiveStyle.horizontalAlignment === 'end', alignStretch: effectiveStyle.horizontalAlignment === 'stretch',
                        valignStart: !effectiveStyle.verticalAlignment || effectiveStyle.verticalAlignment === 'start', valignCenter: effectiveStyle.verticalAlignment === 'center', valignEnd: effectiveStyle.verticalAlignment === 'end',
                    },
                    styleFontList: this.allowedFonts.map(name => ({name, selected: effectiveStyle.fontFamily === name})),
                } : null,
                flowBreadcrumbs: selectedId ? this.flowStructure.breadcrumbs(layout, selectedId) : [],
                canAddFlowContainer: Boolean(selected &&
                    ['flow-section', 'flow-container'].includes(selected.node.type)),
            };
        }

        getValidationData() {
            if (!this.editorState) {
                return {
                    validationIssues: [],
                    validationErrorCount: 0,
                    validationWarningCount: 0,
                    validationBlocking: false,
                    hasValidationIssues: false,
                };
            }

            const result = this.editorValidator.validate(this.editorState.getLayout());

            return {
                validationIssues: result.issues.map(issue => ({
                    ...issue,
                    canFocus: Boolean(issue.nodeId),
                    message: this.translate(
                        issue.messageKey,
                        'messages',
                        'DocumentBuilderTemplate',
                    ),
                    severityText: this.translate(
                        issue.severityLabel,
                        'labels',
                        'DocumentBuilderTemplate',
                    ),
                })),
                validationErrorCount: result.errorCount,
                validationWarningCount: result.warningCount,
                validationBlocking: result.blocking,
                hasValidationIssues: result.issues.length > 0,
            };
        }

        getSourceData() {
            if (!this.editorState) {
                return {
                    sourceOptions: [],
                    sourceCatalogueLoading: true,
                    sourceCatalogueError: false,
                };
            }

            const source = this.editorState.getLayout().dataSource;
            const selectedType = source.type === 'entity' ? source.entityType : '';
            const options = this.entityCatalogue.map(item => ({
                ...item,
                selected: item.entityType === selectedType,
            }));

            if (selectedType && !options.some(item => item.entityType === selectedType)) {
                options.unshift({
                    entityType: selectedType,
                    label: this.translate(
                        'editorUnavailableEntitySource',
                        'messages',
                        'DocumentBuilderTemplate',
                    ),
                    custom: false,
                    selected: true,
                });
            }

            options.unshift({
                entityType: '',
                label: this.translate('No Source', 'labels', 'DocumentBuilderTemplate'),
                custom: false,
                selected: selectedType === '',
            });

            return {
                sourceOptions: options,
                sourceCatalogueLoading: this.entityCatalogueStatus === 'loading',
                sourceCatalogueError: this.entityCatalogueStatus === 'error',
                sourceCatalogueReady: this.entityCatalogueStatus === 'ready',
            };
        }

        getVariableBrowserData() {
            if (!this.editorState) {
                return {variableRows: [], systemVariableRows: [], variableBrowserHasSource: false};
            }

            const source = this.editorState.getLayout().dataSource;

            if (source.type !== 'entity') {
                return {
                    variableRows: [],
                    systemVariableRows: MetadataBrowser.systemRows(),
                    variableBrowserHasSource: false,
                    variableSearch: this.variableSearch,
                    variablePresentation: this.variablePresentationData(),
                };
            }

            const rows = MetadataBrowser.flatten(
                this.metadataNodes,
                this.expandedMetadataPaths,
                this.variableSearch,
            );
            const root = this.metadataNodes.get('');

            return {
                variableRows: rows,
                systemVariableRows: MetadataBrowser.systemRows(),
                hasVariableRows: rows.length > 0,
                variableBrowserHasSource: true,
                variableBrowserLoading: root?.status === 'loading',
                variableBrowserError: root?.status === 'error',
                variableSearch: this.variableSearch,
                variablePresentation: this.variablePresentationData(),
            };
        }

        variablePresentationData(value = this.variablePresentationDraft) {
            const presentation = VariablePresentation.create(value);
            const format = presentation.format;
            const selected = (value, expected) => value === expected;

            return {
                ...format,
                missing: presentation.missing,
                formatOptions: VariablePresentation.FORMAT_TYPES.map(value => ({
                    value, selected: selected(format.type, value),
                })),
                missingOptions: VariablePresentation.MISSING_POLICIES.map(value => ({
                    value, selected: selected(presentation.missing, value),
                })),
                caseOptions: VariablePresentation.CASES.map(value => ({
                    value, selected: selected(format.case, value),
                })),
                dateStyleShort: format.dateStyle === 'short',
                dateStyleMedium: format.dateStyle === 'medium',
                dateStyleLong: format.dateStyle === 'long',
                timeStyleShort: format.timeStyle === 'short',
                timeStyleMedium: format.timeStyle === 'medium',
            };
        }

        getPageSettingsData() {
            if (!this.editorState) {
                return {pageSettings: null, pageFrameStyle: '', zoom: this.zoom};
            }

            const document = this.editorState.getLayout().document;
            const frame = this.pageGeometry.frame(
                document.page.size,
                document.page.orientation,
                this.zoom,
            );
            const margins = document.page.margins;
            const layout = this.editorState.getLayout();
            const px = value => this.pageGeometry.millimetresToPixels(value, this.zoom);
            const header = this.pageChromeData(layout, 'header', px);
            const footer = this.pageChromeData(layout, 'footer', px);

            return {
                pageSettings: {
                    ...document,
                    page: {...document.page, margins},
                    pageSizeList: this.pageGeometry.getSizeList().map(size => ({
                        ...size,
                        selected: size.id === document.page.size,
                    })),
                    portrait: document.page.orientation === 'portrait',
                    landscape: document.page.orientation === 'landscape',
                    fontList: this.allowedFonts.map(font => ({
                        name: font,
                        selected: font === document.defaults.fontFamily,
                    })),
                },
                pageFrameStyle: [
                    `width: ${frame.widthPx}px`,
                    `height: ${frame.heightPx}px`,
                    `padding: ${px(margins.top.value)}px ${px(margins.right.value)}px ` +
                        `${px(margins.bottom.value)}px ${px(margins.left.value)}px`,
                ].join('; '),
                pageChromeHeader: header,
                pageChromeFooter: footer,
                zoom: frame.zoom,
            };
        }

        pageChromeData(layout, region, px) {
            const settings = layout.document.chrome[region];
            const node = layout[region].find(item => item.type === 'paragraph');
            const pageNumber = node?.content?.some(item =>
                item.type === 'variable' && item.identity?.source === 'system' &&
                item.identity?.path?.[0] === 'pageNumber') || false;
            const text = node?.content?.find(item => item.type === 'text')?.text || '';
            const enabled = layout[region].length > 0;
            const margins = layout.document.page.margins;
            const sideStyle = `left:${px(margins.left.value)}px;right:${px(margins.right.value)}px`;
            const alignment = node?.alignment || 'start';
            const textAlign = {start: 'left', center: 'center', end: 'right'}[alignment];
            const offset = px(Math.max(0, margins[region === 'header' ? 'top' : 'bottom'].value -
                settings.height.value));

            return {
                region,
                enabled,
                text,
                includePageNumber: pageNumber,
                alignment,
                alignStart: alignment === 'start',
                alignCenter: alignment === 'center',
                alignEnd: alignment === 'end',
                height: settings.height.value,
                showOnFirstPage: settings.showOnFirstPage,
                disableOnFullPage: settings.disableOnFullPage,
                visibleOnCanvas: enabled && settings.showOnFirstPage,
                renderOnCanvas: enabled && (settings.showOnFirstPage || !this.canvasPreviewOpen),
                style: `${region === 'header' ? 'top' : 'bottom'}:${offset}px;` +
                    `height:${px(settings.height.value)}px;` +
                    `${sideStyle};text-align:${textAlign}`,
            };
        }

        afterRender() {
            this.renderContentNodes();
            if (this.pendingCanvasScroll) {
                const scroll = this.element.querySelector('.document-builder-editor__canvas-scroll');
                if (scroll) {
                    scroll.scrollLeft = this.pendingCanvasScroll.left;
                    scroll.scrollTop = this.pendingCanvasScroll.top;
                }
                this.pendingCanvasScroll = null;
            }
            if (!this.loadStarted) {
                this.loadStarted = true;
                this.loadModel();

                return;
            }

            if (this.previewPdfUrl) {
                this.element.querySelector('[data-action="closePdfPreview"]')?.focus({preventScroll: true});

                return;
            }

            if (this.pendingFocusNodeId) {
                const nodeId = this.pendingFocusNodeId;
                const focusTarget = [...this.element.querySelectorAll(
                    '[data-action="selectFlowNode"]',
                )].find(element => element.dataset.nodeId === nodeId);

                this.pendingFocusNodeId = null;
                if (focusTarget) {
                    focusTarget.focus({preventScroll: false});
                    focusTarget.scrollIntoView({block: 'center'});

                    return;
                }
            }

            if (!this.shouldFocusEntry) {
                return;
            }

            this.shouldFocusEntry = false;

            const focusTarget = this.element.querySelector(
                '.document-builder-editor__focus-target',
            );

            if (focusTarget) {
                focusTarget.focus({preventScroll: true});
            }
        }

        async loadModel() {
            try {
                await this.model.fetch({main: true});

                if (this.isRemoved) {
                    return;
                }

                if (this.getAcl().checkModel(this.model, 'edit') !== true) {
                    this.showError('editorAccessDenied');

                    return;
                }

                if (this.model.get('status') !== 'Draft') {
                    this.showError('editorDraftOnly');

                    return;
                }

                this.editorState = new EditorState(PageSettings.normalize(
                    this.model.get('currentDraftLayout'),
                ));
                this.saveCoordinator = new DraftSaveCoordinator({
                    editorState: this.editorState,
                    draftApi: new DraftApi(),
                    precheck: new LayoutPrecheck(this.customPageSizes, this.flowLimits),
                    templateId: this.model.id,
                    revision: this.model.get('revision'),
                });
                await this.loadEntityCatalogue();
                await this.resetMetadataBrowser();
                this.state = 'ready';
                this.shouldFocusEntry = true;
                this.syncDirtyGuard();
                await this.reRender();
            } catch (xhr) {
                if (this.isRemoved) {
                    return;
                }

                if (xhr) {
                    xhr.errorIsHandled = true;
                }

                if (xhr && xhr.status === 403) {
                    this.showError('editorAccessDenied');

                    return;
                }

                if (xhr && xhr.status === 404) {
                    this.showError('editorNotFound');

                    return;
                }

                this.showError('editorLoadFailed');
            }
        }

        async showError(message) {
            this.state = 'error';
            this.errorMessage = message;
            this.shouldFocusEntry = true;
            await this.reRender();
        }

        actionRetry() {
            this.state = 'loading';
            this.errorMessage = null;
            this.reRender().then(() => this.loadModel());
        }

        async loadEntityCatalogue() {
            this.entityCatalogueStatus = 'loading';

            try {
                this.entityCatalogue = await this.entityCatalogueApi.get();
                this.entityCatalogueStatus = 'ready';
            } catch (xhr) {
                if (xhr) xhr.errorIsHandled = true;
                this.entityCatalogue = [];
                this.entityCatalogueStatus = 'error';
            }
        }

        async actionRetryEntityCatalogue() {
            await this.loadEntityCatalogue();
            await this.resetMetadataBrowser();
            await this.reRender();
        }

        async resetMetadataBrowser() {
            this.metadataGeneration++;
            this.metadataNodes.clear();
            this.expandedMetadataPaths.clear();
            const source = this.editorState?.getLayout().dataSource;

            if (source?.type === 'entity') await this.loadMetadataNode([]);
        }

        async loadMetadataNode(path) {
            const source = this.editorState?.getLayout().dataSource;

            if (source?.type !== 'entity') return;
            const key = path.join('.');
            const entityType = source.entityType;
            const generation = this.metadataGeneration;
            this.metadataNodes.set(key, {status: 'loading', node: null});

            try {
                const node = await this.entityMetadataApi.get(entityType, path);
                if (generation !== this.metadataGeneration ||
                    this.editorState?.getLayout().dataSource.entityType !== entityType) return;
                this.metadataNodes.set(key, {status: 'ready', node});
            } catch (xhr) {
                if (xhr) xhr.errorIsHandled = true;
                if (generation !== this.metadataGeneration) return;
                this.metadataNodes.set(key, {status: 'error', node: null});
            }
        }

        inputVariableSearch(event) {
            this.variableSearch = event.currentTarget.value.slice(0, 100);
            const query = this.variableSearch.trim().toLocaleLowerCase();

            this.element.querySelectorAll('[data-variable-row]').forEach(row => {
                row.hidden = Boolean(query) && !row.textContent.toLocaleLowerCase().includes(query);
            });
        }

        async actionToggleMetadataRelationship(event) {
            const pathKey = event.currentTarget.dataset.path;
            const path = pathKey ? pathKey.split('.') : [];

            if (this.expandedMetadataPaths.has(pathKey)) {
                this.expandedMetadataPaths.delete(pathKey);
                await this.reRender();

                return;
            }

            this.expandedMetadataPaths.add(pathKey);
            if (!this.metadataNodes.has(pathKey)) await this.loadMetadataNode(path);
            await this.reRender();
        }

        async actionRetryMetadataNode(event) {
            const pathKey = event.currentTarget.dataset.path || '';

            await this.loadMetadataNode(pathKey ? pathKey.split('.') : []);
            await this.reRender();
        }

        actionBackToTemplate() {
            this.getRouter().navigate(
                `#DocumentBuilderTemplate/view/${this.model.id}`,
                {trigger: true},
            );
        }

        actionUndo() {
            if (!this.isSaveBusy() && this.editorState && this.editorState.undo()) {
                this.saveCoordinator.noteEdit();
                this.invalidatePreview();
                this.syncDirtyGuard();
                this.reRender();
            }
        }

        actionRedo() {
            if (!this.isSaveBusy() && this.editorState && this.editorState.redo()) {
                this.saveCoordinator.noteEdit();
                this.invalidatePreview();
                this.syncDirtyGuard();
                this.reRender();
            }
        }

        actionAddFlowSection() {
            this.addFlowNode('flow-section', {region: 'sections', parentId: null, index: null});
        }

        actionShowElementsTab() {
            if (this.rightSidebarTab === 'elements') return;
            this.rightSidebarTab = 'elements';
            this.reRender();
        }

        actionShowPropertiesTab() {
            if (this.rightSidebarTab === 'properties') return;
            this.rightSidebarTab = 'properties';
            this.reRender();
        }

        actionFocusVariables() {
            const search = this.element.querySelector('[data-variable-search]');
            if (search) search.focus();
        }

        actionAddFlowContainer() {
            const parentId = this.editorState && this.editorState.getSelectedId();

            if (parentId) this.addFlowNode('flow-container', {parentId, index: null});
        }

        actionAddContent(event) {
            const parentId = this.editorState && this.editorState.getSelectedId();
            const type = event.currentTarget.dataset.libraryType;

            if (parentId && type) this.addFlowNode(type, {parentId, index: null});
        }

        actionAddVariable() {
            const parentId = this.editorState && this.editorState.getSelectedId();
            if (!parentId || !this.standaloneVariableDraft) {
                this.actionFocusVariables();

                return;
            }
            this.addFlowNode('variable', {parentId, index: null}, this.standaloneVariableDraft);
        }

        renderContentNodes() {
            if (!this.editorState || !this.element) return;
            const layout = this.editorState.getLayout();
            const rendered = this.browserRenderer.render(layout, {
                selectedId: this.canvasPreviewOpen ? null : this.editorState.getSelectedId(),
                zoom: this.zoom,
                previewValues: this.editorState.isDirty() ? new Map() : this.previewValues,
                evaluateConditions: this.previewStatus === 'ready' && !this.editorState.isDirty(),
            });
            this.documentCanvas.render(
                this.element.querySelector('[data-document-canvas]'),
                rendered.tree,
                {
                    translate: (key, category) => this.translate(
                        key,
                        category,
                        'DocumentBuilderTemplate',
                    ),
                    variableResolver: identity => this.resolveCanvasVariable(identity),
                    preview: this.canvasPreviewOpen,
                },
            );
        }

        resolveCanvasVariable(identity) {
            const preview = this.editorState?.isDirty() ? null :
                this.previewValues.get(JSON.stringify(identity || {}));
            if (!preview) return null;
            let text = preview.value;
            if (preview.state === 'forbidden') text = '[restricted]';
            else if (preview.state === 'missing') text = '[missing]';
            else if (preview.state === 'invalid') text = '[invalid]';
            else if (Array.isArray(text)) text = text.join(', ');
            else if (text && typeof text === 'object') {
                text = preview.type === 'currency' ? `${text.amount} ${text.currency}` : '[invalid]';
            }

            return {text: String(text ?? '[missing]'), state: preview.state, origin: preview.origin};
        }

        selectedContentNode() {
            if (!this.editorState) return null;
            const id = this.editorState.getSelectedId();
            return id ? NodeTree.getLocation(this.editorState.getLayout(), id) : null;
        }

        changeContentSetting(event) {
            const location = this.selectedContentNode();
            if (!location || this.isSaveBusy()) return;
            const input = event.currentTarget;
            const patch = {};
            if (input.dataset.contentSetting === 'text') {
                patch[location.node.type === 'static-text' ? 'text' : 'content'] =
                    location.node.type === 'static-text' ? input.value : RichText.fromPlainText(input.value);
            } else if (input.dataset.contentSetting === 'level') patch.level = Number(input.value);
            else if (input.dataset.contentSetting === 'alignment' &&
                ['start', 'center', 'end', 'justify'].includes(input.value)) patch.alignment = input.value;
            else if (input.dataset.contentSetting === 'keepWithNext') patch.keepWithNext = input.checked;
            else return;
            this.executeCommand(new UpdateNodeCommand(location.node.id, patch));
        }

        changeBasicFlowSetting(event) {
            const location = this.selectedContentNode();
            if (!location || this.isSaveBusy()) return;
            const input = event.currentTarget;
            const setting = input.dataset.basicFlowSetting;
            const patch = {};

            if (setting === 'orientation' && ['horizontal', 'vertical'].includes(input.value)) {
                patch.orientation = input.value;
            } else if (setting === 'style' && ['solid', 'dashed', 'dotted', 'double'].includes(input.value)) {
                patch.lineStyle = input.value;
            } else if (setting === 'color' && /^#[0-9A-Fa-f]{6}$/.test(input.value)) {
                patch.color = input.value;
            } else if (['thickness', 'length', 'height'].includes(setting)) {
                const value = Number(input.value);
                const bounds = {thickness: [0.1, 20], length: [1, 2000], height: [0.1, 500]};

                if (!Number.isFinite(value) || value < bounds[setting][0] || value > bounds[setting][1]) {
                    return;
                }
                patch[setting] = {value, unit: 'mm'};
            } else return;

            this.executeCommand(new UpdateNodeCommand(location.node.id, patch));
        }

        changeStyleSetting(event) {
            const location = this.selectedContentNode();
            if (!location || this.isSaveBusy()) return;
            const input = event.currentTarget; const setting = input.dataset.styleSetting;
            const style = {...(location.node.style || {})};
            const enums = {
                fontWeight:['normal','bold','100','200','300','400','500','600','700','800','900'],
                fontStyle:['normal','italic'], textDecoration:['none','underline'],
                textTransform:['none','uppercase','lowercase','capitalize'],
                horizontalAlignment:['start','center','end','stretch'],
                verticalAlignment:['start','center','end'],
            };
            if (setting === 'fontFamily' && this.allowedFonts.includes(input.value)) style.fontFamily = input.value;
            else if (setting in enums && enums[setting].includes(input.value)) style[setting] = input.value;
            else if (['color','backgroundColor'].includes(setting) && /^#[0-9A-Fa-f]{6}$/.test(input.value)) style[setting] = input.value;
            else if (setting === 'opacity') { const value=Number(input.value); if(!Number.isFinite(value)||value<0||value>1)return; style.opacity=value; }
            else if (setting === 'lineHeight') { const value=Number(input.value); if(!Number.isFinite(value)||value<.5||value>5)return; style.lineHeight=value; }
            else if (setting === 'fontSize') { const value=Number(input.value); if(!Number.isFinite(value)||value<1||value>512)return; style.fontSize={value,unit:'pt'}; }
            else if (setting === 'letterSpacing') { const value=Number(input.value); if(!Number.isFinite(value)||value < -20||value>100)return; style.letterSpacing={value,unit:'pt'}; }
            else if (['width','height'].includes(setting)) { const value=Number(input.value); if(!Number.isFinite(value)||value<0||value>2000)return; style[setting]={value,unit:'mm'}; }
            else if (setting === 'borderWidth') { const value=Number(input.value); if(!Number.isFinite(value)||value<0||value>512)return; style.border={...(style.border||{}),width:{value,unit:'pt'},style:style.border?.style||'solid',color:style.border?.color||'#000000'}; }
            else if (setting === 'borderStyle' && ['none','solid','dashed','dotted','double'].includes(input.value)) style.border={...(style.border||{}),width:style.border?.width||{value:1,unit:'pt'},style:input.value,color:style.border?.color||'#000000'};
            else if (setting === 'borderColor' && /^#[0-9A-Fa-f]{6}$/.test(input.value)) style.border={...(style.border||{}),width:style.border?.width||{value:1,unit:'pt'},style:style.border?.style||'solid',color:input.value};
            else return;
            this.executeCommand(new UpdateNodeCommand(location.node.id, {style}));
        }

        pasteContentText(event) {
            event.preventDefault();
            const input = event.currentTarget;
            const text = event.originalEvent?.clipboardData?.getData('text/plain') ||
                event.clipboardData?.getData('text/plain') || '';
            input.value = input.value.slice(0, input.selectionStart) + text + input.value.slice(input.selectionEnd);
            this.changeContentSetting({currentTarget: input});
        }

        activateRichTextEditor(event) {
            event.stopPropagation();
            const surface = event.currentTarget;
            const nodeId = surface.dataset.nodeId;
            if (!nodeId || !this.editorState) return;
            this.editorState.select(nodeId);
            this.captureRichTextSelection({currentTarget: surface});
            this.element.querySelectorAll('.document-builder-editor__flow-node.is-selected')
                .forEach(element => element.classList.remove('is-selected'));
            surface.closest('.document-builder-editor__flow-node')?.classList.add('is-selected');
        }

        captureDocumentSelection() {
            if (!this.element) return;
            const selection = document.getSelection?.();
            if (!selection || selection.rangeCount < 1) return;
            const common = selection.getRangeAt(0).commonAncestorContainer;
            const owner = common.nodeType === 1 ? common : common.parentNode;
            const surface = owner?.closest?.('[data-rich-editor]');
            if (surface && this.element.contains(surface)) {
                this.captureRichTextSelection({currentTarget: surface});
            }
        }

        captureRichTextSelection(event) {
            const surface = event.currentTarget;
            const selection = surface.ownerDocument?.getSelection?.() || document.getSelection?.();
            const range = Wysiwyg.captureRange(surface, selection);
            if (range) this.richTextSelection = {nodeId: surface.dataset.nodeId, range};
        }

        inputRichText(event) {
            event.stopPropagation();
            if (event.currentTarget.textContent || event.currentTarget.querySelector('br, ul, ol')) {
                event.currentTarget.closest('.document-builder-editor__flow-node')?.classList.remove('is-sample');
                delete event.currentTarget.dataset.placeholder;
            }
            this.syncRichTextSurface(event.currentTarget);
            this.captureRichTextSelection(event);
        }

        pasteRichText(event) {
            event.preventDefault();
            event.stopPropagation();
            const text = event.originalEvent?.clipboardData?.getData('text/plain') ||
                event.clipboardData?.getData('text/plain') || '';
            const documentRef = event.currentTarget.ownerDocument || document;
            const selection = documentRef.getSelection?.();
            const range = Wysiwyg.captureRange(event.currentTarget, selection);
            if (!range) return;
            const inserted = documentRef.createTextNode(text);
            range.deleteContents();
            range.insertNode(inserted);
            range.setStartAfter(inserted);
            range.collapse(true);
            Wysiwyg.restoreRange(selection, range);
            this.syncRichTextSurface(event.currentTarget);
            this.captureRichTextSelection(event);
        }

        handleRichTextKeydown(event) {
            event.stopPropagation();
        }

        preserveRichTextSelection(event) {
            if (this.richTextSelection) event.preventDefault();
        }

        syncRichTextSurface(surface) {
            if (!this.editorState || this.isSaveBusy()) return false;
            const location = NodeTree.getLocation(this.editorState.getLayout(), surface.dataset.nodeId);
            if (!location || !['heading', 'paragraph'].includes(location.node.type)) return false;
            const content = Wysiwyg.read(surface, location.node.content);

            return this.executeCommand(
                new UpdateNodeCommand(location.node.id, {content}),
                {render: false},
            );
        }

        activeRichTextSurface() {
            const active = this.richTextSelection;
            if (!active || !this.element) return null;

            return [...this.element.querySelectorAll('[data-rich-editor]')]
                .find(surface => surface.dataset.nodeId === active.nodeId) || null;
        }

        applyRichTextCommand(command, value = null) {
            const surface = this.activeRichTextSurface();
            if (!surface || !this.richTextSelection) return false;
            const selection = surface.ownerDocument?.getSelection?.() || document.getSelection?.();
            if (!Wysiwyg.restoreRange(selection, this.richTextSelection.range)) return false;
            if (!Wysiwyg.applyCommand(surface.ownerDocument || document, command, value)) return false;
            this.syncRichTextSurface(surface);
            this.captureRichTextSelection({currentTarget: surface});
            surface.focus({preventScroll: true});

            return true;
        }

        actionToggleRichMark(event) {
            const commands = {bold: 'bold', italic: 'italic', underline: 'underline'};
            if (this.applyRichTextCommand(commands[event.currentTarget.dataset.richMark])) return;
            const location = this.selectedContentNode();
            if (!location || !location.node.content) return;
            this.executeCommand(new UpdateNodeCommand(location.node.id, {
                content: RichText.toggleMark(location.node.content, event.currentTarget.dataset.richMark),
            }));
        }

        changeRichColor(event) {
            if (this.applyRichTextCommand('foreColor', event.currentTarget.value)) return;
            const location = this.selectedContentNode();
            if (!location || !location.node.content) return;
            this.executeCommand(new UpdateNodeCommand(location.node.id, {
                content: RichText.setColor(location.node.content, event.currentTarget.value),
            }));
        }

        actionRichTextCommand(event) {
            this.applyRichTextCommand(event.currentTarget.dataset.richCommand);
        }

        actionInsertMetadataVariable(event) {
            const systemVariable = event.currentTarget.dataset.systemVariable;
            const identity = systemVariable ? MetadataBrowser.systemIdentityAt(systemVariable) :
                MetadataBrowser.identityAt(
                    this.metadataNodes,
                    event.currentTarget.dataset.variablePath,
                );
            const label = event.currentTarget.dataset.variableLabel;
            this.standaloneVariableDraft = {
                identity,
                label,
                presentation: this.variablePresentationDraft,
            };
            const location = this.selectedContentNode();
            if (!location || !location.node.content || location.node.type === 'static-text') {
                this.reRender();

                return;
            }
            const tokenId = this.editorState.idFactory.create('variable');
            const variable = {
                type: 'variable', tokenId, label, identity,
                presentation: this.variablePresentationDraft,
            };
            const surface = this.activeRichTextSurface();

            if (surface && this.richTextSelection?.nodeId === location.node.id) {
                const result = Wysiwyg.insertVariable(
                    surface,
                    this.richTextSelection.range,
                    variable,
                    location.node.content,
                    surface.ownerDocument || document,
                );
                this.richTextSelection = {nodeId: location.node.id, range: result.range.cloneRange()};
                this.executeCommand(
                    new UpdateNodeCommand(location.node.id, {content: result.content}),
                    {render: false},
                );
                surface.focus({preventScroll: true});

                return;
            }

            this.executeCommand(new UpdateNodeCommand(location.node.id, {
                content: RichText.appendVariable(
                    location.node.content,
                    tokenId,
                    label,
                    identity,
                    this.variablePresentationDraft,
                ),
            }));
        }

        changeVariablePresentation(event) {
            const input = event.currentTarget;
            const setting = input.dataset.variablePresentation;
            const selectedId = this.editorState && this.editorState.getSelectedId();
            const selected = selectedId ?
                NodeTree.getLocation(this.editorState.getLayout(), selectedId) : null;
            const current = selected?.node.type === 'variable' ?
                VariablePresentation.create(selected.node.presentation) :
                this.variablePresentationDraft;
            const next = {
                format: {...current.format},
                missing: current.missing,
            };

            if (setting === 'missing') {
                next.missing = input.value;
                if (input.value === 'fallback' && next.format.fallback === null) {
                    next.format.fallback = '';
                }
            }
            else if (setting === 'decimals') next.format.decimals = Number(input.value);
            else if (setting === 'trim') next.format.trim = input.checked;
            else if (setting === 'fallback') next.format.fallback = input.value;
            else if (['currency', 'trueLabel', 'falseLabel'].includes(setting)) {
                next.format[setting] = input.value === '' ? null : input.value;
            } else if (['type', 'dateStyle', 'timeStyle', 'separator', 'case', 'prefix', 'suffix'].includes(setting)) {
                next.format[setting] = input.value;
            } else return;

            try {
                const presentation = VariablePresentation.create(next);
                if (selected?.node.type === 'variable') {
                    this.executeCommand(new UpdateNodeCommand(selected.node.id, {presentation}));
                } else {
                    this.variablePresentationDraft = presentation;
                }
            } catch (error) {
                this.reRender();
            }
        }

        addFlowNode(type, target, options = {}) {
            const command = new AddFlowNodeCommand(this.flowStructure, type, target, options);

            try {
                if (this.executeCommand(command)) {
                    this.editorState.select(command.addedId);
                    this.reRender();
                }
            } catch (error) {
                this.showInvalidFlowDrop();
            }
        }

        actionSelectFlowNode(event) {
            event.stopPropagation();
            if (this.selectNode(event.currentTarget.dataset.nodeId)) this.reRender();
        }

        actionEditFlowNode(event) {
            event.stopPropagation();
            const nodeId = event.currentTarget.dataset.nodeId;

            if (!nodeId || !this.editorState ||
                !NodeTree.getLocation(this.editorState.getLayout(), nodeId)) return;
            this.selectNode(nodeId);
            this.rightSidebarTab = 'properties';
            this.reRender();
        }

        actionDuplicateFlowNode(event) {
            event.stopPropagation();
            if (!this.editorState || this.isSaveBusy()) return;
            const nodeId = event.currentTarget.dataset.nodeId;
            if (!nodeId) return;
            const command = new DuplicateNodeCommand(nodeId, null, this.flowStructure);

            try {
                if (this.executeCommand(command)) {
                    this.editorState.select(command.duplicateId);
                    this.reRender();
                }
            } catch (error) {
                this.showInvalidFlowDrop();
            }
        }

        actionFocusValidationIssue(event) {
            const nodeId = event.currentTarget.dataset.nodeId;

            if (!nodeId || !this.editorState ||
                !NodeTree.getLocation(this.editorState.getLayout(), nodeId)) return;
            this.selectNode(nodeId);
            this.pendingFocusNodeId = nodeId;
            this.reRender();
        }

        handleNodeKeydown(event) {
            event.stopPropagation();
            if (!['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
            const controls = [...this.element.querySelectorAll(
                '[data-action="selectFlowNode"]',
            )];
            const current = controls.indexOf(event.currentTarget);

            if (current < 0 || controls.length === 0) return;
            event.preventDefault();
            const target = event.key === 'Home' ? 0 : event.key === 'End' ? controls.length - 1 :
                Math.max(0, Math.min(controls.length - 1,
                    current + (event.key === 'ArrowDown' ? 1 : -1)));
            controls[target].focus();
        }

        async actionRemoveFlowNode(event = null) {
            if (event) event.stopPropagation();
            const nodeId = event?.currentTarget?.dataset.nodeId ||
                (this.editorState && this.editorState.getSelectedId());

            if (!nodeId) return;
            const location = NodeTree.getLocation(this.editorState.getLayout(), nodeId);

            if (location?.node.children?.length) {
                try {
                    await this.confirm({
                        message: this.translate(
                            'confirmRemoveComplexNode',
                            'messages',
                            'DocumentBuilderTemplate',
                        ),
                        confirmText: this.translate('Remove'),
                    });
                } catch (error) {
                    return;
                }
            }

            this.executeCommand(new RemoveFlowNodeCommand(this.flowStructure, nodeId));
        }

        actionMoveFlowUp() {
            this.moveSelectedFlow(-1);
        }

        actionMoveFlowDown() {
            this.moveSelectedFlow(1);
        }

        actionApplyCondition() {
            if (!this.editorState || this.isSaveBusy()) return;

            const nodeId = this.editorState.getSelectedId();

            try {
                if (nodeId) this.executeCommand(new UpdateConditionCommand(
                    nodeId,
                    this.buildConditionFromInspector(),
                ));
            } catch (error) {
                this.notify('The visibility condition is invalid.', 'warning');
            }
        }

        actionAddConditionRule() {
            const nodeId = this.editorState && this.editorState.getSelectedId();

            try {
                const current = this.buildConditionFromInspector();
                const last = current.rules[current.rules.length - 1];
                const condition = ConditionBuilder.create({
                    target: current.target,
                    mode: current.mode,
                    rules: [...current.rules, last],
                });
                if (nodeId) this.executeCommand(new UpdateConditionCommand(nodeId, condition));
            } catch (error) {
                this.notify('Apply a valid rule before adding another.', 'warning');
            }
        }

        actionRemoveConditionRule(event) {
            const nodeId = this.editorState && this.editorState.getSelectedId();
            const index = Number(event.currentTarget.dataset.ruleIndex);

            try {
                const current = this.buildConditionFromInspector();
                const rules = current.rules.filter((rule, ruleIndex) => ruleIndex !== index);
                if (!nodeId || rules.length === 0) return;
                this.executeCommand(new UpdateConditionCommand(nodeId, ConditionBuilder.create({
                    target: current.target,
                    mode: current.mode,
                    rules,
                })));
            } catch (error) {
                this.notify('The visibility condition is invalid.', 'warning');
            }
        }

        buildConditionFromInspector() {
            if (!this.editorState || !this.element) throw new TypeError('The editor is unavailable.');
            const source = this.editorState.getLayout().dataSource;
            if (source.type !== 'entity') throw new TypeError('An entity source is required.');
            const value = setting => this.element.querySelector(
                `[data-condition-setting="${setting}"]`,
            )?.value;
            const rules = [...this.element.querySelectorAll('[data-condition-rule]')].map(row => {
                const field = setting => row.querySelector(`[data-condition-rule-setting="${setting}"]`)?.value;
                const rawPath = field('path') || '';
                const path = rawPath.split('.');
                const valueType = field('valueType');
                const operator = field('operator');
                let operand = field('operand');

                if (path.some(segment => segment === '')) throw new TypeError('A path segment is empty.');
                if (['exists', 'missing', 'isTrue', 'isFalse'].includes(operator)) operand = null;
                else if (['number', 'currency'].includes(valueType)) operand = Number(operand);
                else if (valueType === 'boolean' && ['true', 'false'].includes(operand)) {
                    operand = operand === 'true';
                }

                return {
                    identity: {
                        source: 'entity',
                        type: path.length === 1 ? 'direct' : 'related',
                        entityType: source.entityType,
                        path,
                    },
                    valueType,
                    operator,
                    operand,
                };
            });

            return ConditionBuilder.create({target: value('target'), mode: value('mode'), rules});
        }

        actionRemoveCondition() {
            const nodeId = this.editorState && this.editorState.getSelectedId();
            if (nodeId) this.executeCommand(new UpdateConditionCommand(nodeId, null));
        }

        moveSelectedFlow(direction) {
            if (!this.editorState) return;

            const layout = this.editorState.getLayout();
            const nodeId = this.editorState.getSelectedId();
            const location = nodeId ? NodeTree.getLocation(layout, nodeId) : null;

            if (!location) return;

            const targetIndex = direction > 0 ? location.index + 2 : location.index - 1;
            const target = location.parentId ?
                {parentId: location.parentId, index: targetIndex} :
                {region: location.region, parentId: null, index: targetIndex};

            this.executeCommand(new MoveFlowNodeCommand(this.flowStructure, nodeId, target));
        }

        changeFlowSetting(event) {
            if (!this.editorState || this.isSaveBusy()) return;

            const nodeId = this.editorState.getSelectedId();
            const location = nodeId ? NodeTree.getLocation(this.editorState.getLayout(), nodeId) : null;

            if (!location) return;

            const input = event.currentTarget;
            const setting = input.dataset.flowSetting;
            const patch = {};

            if (setting === 'keepTogether' || setting === 'startNewPage') {
                patch[setting] = input.checked;
            } else if (setting === 'minHeight') {
                patch.minHeight = {value: Number(input.value), unit: 'mm'};
            } else if (/^(margin|padding)(Top|Right|Bottom|Left)$/.test(setting)) {
                const value = Number(input.value);
                const [, property, edgeName] = setting.match(/^(margin|padding)(Top|Right|Bottom|Left)$/);
                const edge = edgeName.toLowerCase();

                patch[property] = {...location.node[property]};
                patch[property][edge] = {value, unit: 'mm'};
            } else {
                return;
            }

            this.executeCommand(new UpdateNodeCommand(nodeId, patch));
        }

        handleFlowDragStart(event) {
            event.stopPropagation();
            const source = event.currentTarget;
            const dataTransfer = event.originalEvent?.dataTransfer;

            if (!dataTransfer) return;

            if (source.dataset.systemVariable || source.dataset.variablePath) {
                const identity = source.dataset.systemVariable ?
                    MetadataBrowser.systemIdentityAt(source.dataset.systemVariable) :
                    MetadataBrowser.identityAt(this.metadataNodes, source.dataset.variablePath);
                this.flowDrag = {
                    kind: 'variable',
                    type: 'variable',
                    options: {
                        identity,
                        label: source.dataset.variableLabel,
                        presentation: this.variablePresentationDraft,
                    },
                };
            } else if (source.dataset.libraryType === 'variable') {
                if (!this.standaloneVariableDraft) {
                    this.actionFocusVariables();

                    return;
                }
                this.flowDrag = {
                    kind: 'library', type: 'variable', options: this.standaloneVariableDraft,
                };
            } else {
                this.flowDrag = source.dataset.libraryType ?
                    {kind: 'library', type: source.dataset.libraryType} :
                    {kind: 'node', nodeId: source.dataset.nodeId};
            }
            this.element.classList.add('is-dragging');
            dataTransfer.effectAllowed = this.flowDrag.kind === 'node' ? 'move' : 'copy';
            dataTransfer.setData('text/plain', JSON.stringify(this.flowDrag));
            this.updateDropTargetCompatibility();
        }

        handleFlowDragOver(event) {
            if (!this.flowDrag || !this.editorState) return;
            const dataTransfer = event.originalEvent?.dataTransfer;

            if (!dataTransfer) return;

            if (!event.currentTarget.classList.contains('is-compatible')) return;

            event.preventDefault();
            dataTransfer.dropEffect = this.flowDrag.kind === 'node' ? 'move' : 'copy';
            event.currentTarget.classList.add('is-drag-over');
        }

        handleFlowDragLeave(event) {
            event.currentTarget.classList.remove('is-drag-over');
        }

        handleFlowDrop(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('is-drag-over');

            if (!this.flowDrag || !this.editorState) return;

            const target = this.flowDropTarget(event.currentTarget);

            try {
                if (this.flowDrag.kind !== 'node') {
                    this.addFlowNode(this.flowDrag.type, target, this.flowDrag.options || {});
                } else {
                    this.executeCommand(new MoveFlowNodeCommand(
                        this.flowStructure,
                        this.flowDrag.nodeId,
                        target,
                    ));
                }
            } catch (error) {
                this.showInvalidFlowDrop();
            } finally {
                this.cancelFlowDrag();
            }
        }

        showInvalidFlowDrop() {
            Espo.Ui.error(this.translate(
                'editorInvalidFlowDrop',
                'messages',
                'DocumentBuilderTemplate',
            ));
        }

        flowDropTarget(element) {
            const parentId = element.dataset.dropParent || null;
            const index = element.dataset.dropIndex === '' ? null : Number(element.dataset.dropIndex);

            return parentId ? {parentId, index} : {
                region: element.dataset.dropRegion || 'sections',
                parentId: null,
                index,
            };
        }

        handleFlowDragEnd(event) {
            event.stopPropagation();
            this.cancelFlowDrag();
        }

        updateDropTargetCompatibility() {
            if (!this.flowDrag || !this.editorState) return;
            const layout = this.editorState.getLayout();
            const node = this.flowDrag.kind !== 'node' ?
                this.flowStructure.createNode(this.flowDrag.type, this.flowDrag.options || {}) :
                NodeTree.getLocation(layout, this.flowDrag.nodeId)?.node;
            if (!node) return;

            this.element.querySelectorAll('[data-flow-drop]').forEach(element => {
                element.classList.remove('is-compatible', 'is-drag-over');
                try {
                    this.flowStructure.assertTarget(
                        layout,
                        node,
                        this.flowDropTarget(element),
                        this.flowDrag.kind === 'node' ? this.flowDrag.nodeId : null,
                    );
                    element.classList.add('is-compatible');
                } catch (error) {}
            });
        }

        cancelFlowDrag() {
            this.flowDrag = null;
            this.element.classList.remove('is-dragging');
            this.element.querySelectorAll('.is-drag-over, .is-compatible').forEach(element => {
                element.classList.remove('is-drag-over', 'is-compatible');
            });
        }

        handleCanvasHover(event) {
            const canvas = event.currentTarget;
            const target = event.target;
            if (!target || typeof target.closest !== 'function') return;
            const node = target.closest('.document-builder-editor__flow-node');
            const toolbar = canvas.querySelector('[data-hover-toolbar]');
            if (!toolbar) return;
            if (!node) {
                if (!target.closest('[data-hover-toolbar]')) toolbar.hidden = true;
                return;
            }

            toolbar.hidden = false;
            toolbar.dataset.nodeId = node.dataset.nodeId;
            toolbar.querySelectorAll('[data-hover-action]').forEach(button => {
                button.dataset.nodeId = node.dataset.nodeId;
            });
            const canvasRect = canvas.getBoundingClientRect();
            const nodeRect = node.getBoundingClientRect();
            const top = Math.max(0, nodeRect.top - canvasRect.top - toolbar.offsetHeight - 4);
            const left = Math.max(0, Math.min(
                canvas.clientWidth - toolbar.offsetWidth,
                nodeRect.right - canvasRect.left - toolbar.offsetWidth,
            ));
            toolbar.style.top = `${top}px`;
            toolbar.style.left = `${left}px`;
        }

        hideCanvasHover() {
            const toolbar = this.element.querySelector('[data-hover-toolbar]');
            if (toolbar) toolbar.hidden = true;
        }

        changePageSetting(event) {
            if (!this.editorState || this.isSaveBusy()) {
                return;
            }

            const input = event.currentTarget;
            const path = input.dataset.pageSetting;
            const document = this.editorState.getLayout().document;
            const numeric = input.dataset.valueType === 'number';
            const value = numeric ? Number(input.value) : input.value;

            if (numeric && !Number.isFinite(value)) {
                return;
            }

            const targets = {
                size: () => { document.page.size = value; },
                orientation: () => { document.page.orientation = value; },
                marginTop: () => { document.page.margins.top.value = value; },
                marginRight: () => { document.page.margins.right.value = value; },
                marginBottom: () => { document.page.margins.bottom.value = value; },
                marginLeft: () => { document.page.margins.left.value = value; },
                fontFamily: () => { document.defaults.fontFamily = value; },
                fontSize: () => { document.defaults.fontSize.value = value; },
                color: () => { document.defaults.color = value; },
                lineHeight: () => { document.defaults.lineHeight = value; },
                locale: () => { document.defaults.locale = value; },
                timezone: () => { document.defaults.timezone = value; },
                titlePattern: () => { document.titlePattern = value; },
                filenamePattern: () => { document.filenamePattern = value; },
            };

            if (!(path in targets)) {
                return;
            }

            targets[path]();
            this.executeCommand(new UpdateDocumentCommand(document));
        }

        changeChromeSetting(event) {
            if (!this.editorState || this.isSaveBusy()) return;
            const input = event.currentTarget;
            const region = input.dataset.chromeRegion;
            const setting = input.dataset.chromeSetting;

            if (!['header', 'footer'].includes(region)) return;
            const layout = this.editorState.getLayout();
            const configuration = layout.document.chrome[region];
            const node = layout[region].find(item => item.type === 'paragraph');
            const values = {
                enabled: layout[region].length > 0,
                text: node?.content?.find(item => item.type === 'text')?.text || '',
                includePageNumber: node?.content?.some(item =>
                    item.type === 'variable' && item.identity?.source === 'system' &&
                    item.identity?.path?.[0] === 'pageNumber') || false,
                alignment: node?.alignment || 'start',
                height: configuration.height.value,
                showOnFirstPage: configuration.showOnFirstPage,
                disableOnFullPage: configuration.disableOnFullPage,
                updateContent: ['enabled', 'text', 'pageNumber'].includes(setting),
            };

            if (setting === 'enabled') {
                values.enabled = input.checked;
                if (values.enabled && values.height === 0) {
                    const edge = region === 'header' ? 'top' : 'bottom';
                    values.height = Math.min(10, layout.document.page.margins[edge].value);
                }
            } else if (setting === 'text') values.text = input.value.slice(0, 10000);
            else if (setting === 'pageNumber') values.includePageNumber = input.checked;
            else if (setting === 'alignment') values.alignment = input.value;
            else if (setting === 'height') values.height = Number(input.value);
            else if (setting === 'showOnFirstPage') values.showOnFirstPage = input.checked;
            else if (setting === 'disableOnFullPage') values.disableOnFullPage = input.checked;
            else return;

            if (!Number.isFinite(values.height) || values.height < 0 || values.height > 100 ||
                !['start', 'center', 'end'].includes(values.alignment)) return;
            this.executeCommand(new UpdatePageChromeCommand(
                region,
                values,
                VariablePresentation.defaults(),
            ));
        }

        async changeSourceSetting(event) {
            if (!this.editorState || this.isSaveBusy()) return;
            const entityType = event.currentTarget.value;
            const current = this.editorState.getLayout().dataSource;
            const next = entityType ? {
                type: 'entity',
                entityType,
                relationshipDepth: this.maxRelationshipDepth,
            } : {type: 'none'};

            if (JSON.stringify(current) === JSON.stringify(next)) return;
            const allowed = entityType === '' ||
                this.entityCatalogue.some(item => item.entityType === entityType);

            if (!allowed) {
                await this.reRender();

                return;
            }

            if (this.executeCommand(new UpdateDataSourceCommand(next))) {
                this.saveCoordinator.resetSourceChangeConfirmation();
                this.standaloneVariableDraft = null;
                await this.resetMetadataBrowser();
                await this.reRender();
            }
        }

        actionZoomIn() {
            this.setZoom(this.zoom + 25);
        }

        actionZoomOut() {
            this.setZoom(this.zoom - 25);
        }

        actionFitWidth() {
            this.setFittedZoom('width');
        }

        actionFitPage() {
            this.setFittedZoom('page');
        }

        setFittedZoom(mode) {
            const host = this.element.querySelector('.document-builder-editor__canvas-host');

            if (!host || !this.editorState) {
                return;
            }

            const {page} = this.editorState.getLayout().document;
            const zoom = mode === 'width' ?
                this.pageGeometry.fitWidth(page.size, page.orientation, host.clientWidth) :
                this.pageGeometry.fitPage(
                    page.size,
                    page.orientation,
                    host.clientWidth,
                    host.clientHeight,
                );

            this.setZoom(zoom);
        }

        setZoom(zoom) {
            const normalized = this.pageGeometry.clampZoom(zoom);

            if (normalized === this.zoom) {
                return;
            }

            this.zoom = normalized;
            this.reRender();
        }

        inputPreviewRecordId(event) {
            this.previewRecordId = event.currentTarget.value.trim();
            this.reRender();
        }

        actionPreviewSample() {
            this.loadPreview('sample', null);
        }

        actionPreviewRecord() {
            this.loadPreview('record', this.previewRecordId);
        }

        rememberCanvasScroll() {
            const scroll = this.element?.querySelector('.document-builder-editor__canvas-scroll');
            if (scroll) this.pendingCanvasScroll = {left: scroll.scrollLeft, top: scroll.scrollTop};
        }

        actionBackToEdit() {
            if (!this.canvasPreviewOpen) return;
            this.rememberCanvasScroll();
            this.canvasPreviewOpen = false;
            this.reRender();
        }

        async loadPreview(mode, recordId) {
            if (!this.editorState || this.editorState.isDirty() || this.previewStatus === 'loading') {
                return;
            }

            this.previewStatus = 'loading';
            this.canvasPreviewOpen = false;
            this.rememberCanvasScroll();
            await this.reRender();

            try {
                const result = await this.previewApi.load(
                    this.model.id,
                    this.model.get('revision'),
                    mode,
                    recordId,
                );
                const values = Array.isArray(result.values) ? result.values : [];
                this.previewValues = new Map(values.map(value => [
                    JSON.stringify(value.identity || {}),
                    value,
                ]));
                this.previewMode = mode;
                this.previewStatus = 'ready';
                this.canvasPreviewOpen = true;
            } catch (xhr) {
                if (xhr) xhr.errorIsHandled = true;
                this.previewValues = new Map();
                this.previewMode = null;
                this.previewStatus = 'error';
                this.canvasPreviewOpen = false;
            }

            this.rememberCanvasScroll();
            await this.reRender();
        }

        async actionPdfProof() {
            if (!this.editorState || this.editorState.isDirty() || this.pdfProofLoading) return;
            const record = /^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(this.previewRecordId);
            const mode = this.previewMode || (record ? 'record' : 'sample');
            const recordId = mode === 'record' ? this.previewRecordId : null;
            this.pdfProofLoading = true;
            this.pdfProofError = false;
            this.rememberCanvasScroll();
            await this.reRender();

            try {
                const result = await this.previewApi.loadPdf(
                    this.model.id,
                    this.model.get('revision'),
                    mode,
                    recordId,
                );
                this.previewPdfUrl = this.createPdfUrl(result);
                this.previewPageCount = Number.isInteger(result.pageCount) ? result.pageCount : 0;
                this.previewWarningCount = Number.isInteger(result.warningCount) ? result.warningCount : 0;
            } catch (xhr) {
                if (xhr) xhr.errorIsHandled = true;
                this.pdfProofError = true;
            } finally {
                this.pdfProofLoading = false;
            }

            this.rememberCanvasScroll();
            await this.reRender();
        }

        createPdfUrl(result) {
            if (!result || result.mediaType !== 'application/pdf' || typeof result.content !== 'string') {
                throw new TypeError('PDF preview response is invalid.');
            }

            const binary = window.atob(result.content);
            const bytes = new Uint8Array(binary.length);

            for (let index = 0; index < binary.length; index++) {
                bytes[index] = binary.charCodeAt(index);
            }

            return URL.createObjectURL(new Blob([bytes], {type: result.mediaType}));
        }

        actionClosePdfPreview() {
            this.rememberCanvasScroll();
            this.releasePdfPreview();
            this.reRender();
        }

        releasePdfPreview() {
            if (this.previewPdfUrl) URL.revokeObjectURL(this.previewPdfUrl);
            this.previewPdfUrl = null;
            this.previewPageCount = 0;
            this.previewWarningCount = 0;
        }

        async actionSave() {
            if (
                !this.saveCoordinator ||
                !this.editorState.isDirty() ||
                this.isSaveBusy() ||
                this.conflictDialogOpen
            ) {
                return;
            }

            if (this.editorValidator.validate(this.editorState.getLayout()).blocking) {
                Espo.Ui.error(this.translate(
                    'editorValidationBlocksSave',
                    'messages',
                    'DocumentBuilderTemplate',
                ));

                return;
            }

            const savePromise = this.saveCoordinator.save();

            await this.reRender();
            await this.handleSaveOutcome(await savePromise);
        }

        async retryConflict(conflict) {
            if (!this.saveCoordinator || this.isSaveBusy()) {
                return;
            }

            const savePromise = this.saveCoordinator.retryConflict(conflict);

            await this.reRender();
            await this.handleSaveOutcome(await savePromise);
        }

        async handleSaveOutcome(outcome) {
            if (this.isRemoved) {
                return;
            }

            if (outcome.status === 'saved') {
                this.model.set({
                    revision: outcome.result.revision,
                    currentDraftLayout: outcome.result.layout,
                    draftChangeNote: outcome.result.changeNote,
                }, {silent: true});
                this.syncDirtyGuard();
                await this.reRender();
                Espo.Ui.success(
                    this.translate('editorSaved', 'messages', 'DocumentBuilderTemplate'),
                );

                return;
            }

            this.syncDirtyGuard();
            await this.reRender();

            if (outcome.status === 'conflict') {
                await this.showRevisionConflict(outcome.conflict);
            }

            if (outcome.status === 'source-change') {
                await this.showSourceChangeImpact(outcome.impact);
            }
        }

        async showSourceChangeImpact(impact) {
            const references = impact.unresolvedReferences;
            const details = references.length === 0 ?
                this.translate('sourceChangeNoBrokenReferences', 'messages', 'DocumentBuilderTemplate') :
                references.map(reference => `${reference.id}: ${reference.path}`).join('\n');

            try {
                await this.confirm({
                    message: `${this.translate(
                        'sourceChangeImpactReview',
                        'messages',
                        'DocumentBuilderTemplate',
                    )}\n\n${details}`,
                    confirmText: this.translate('Change Source', 'actions', 'DocumentBuilderTemplate'),
                });
            } catch (error) {
                return;
            }

            this.saveCoordinator.confirmSourceChange();
            await this.actionSave();
        }

        async showRevisionConflict(conflict) {
            this.conflictDialogOpen = true;
            const view = await this.createView(
                'dialog',
                'document-builder:views/editor/modals/revision-conflict',
                {actualRevision: conflict.actualRevision},
            );

            this.listenToOnce(view, 'retry', actualRevision => {
                this.retryConflict({...conflict, actualRevision});
            });
            this.listenToOnce(view, 'reload', () => this.reloadDraft());
            this.listenToOnce(view, 'remove', () => {
                this.conflictDialogOpen = false;
                this.stopListening(view);
            });
            await view.render();
        }

        async reloadDraft() {
            if (!this.saveCoordinator || !this.saveCoordinator.beginReload()) {
                return;
            }

            await this.reRender();

            try {
                await this.model.fetch({main: true});

                if (
                    this.getAcl().checkModel(this.model, 'edit') !== true ||
                    this.model.get('status') !== 'Draft'
                ) {
                    throw new Error('The reloaded record is not an editable draft.');
                }

                this.saveCoordinator.acceptReload(
                    this.model.get('currentDraftLayout'),
                    this.model.get('revision'),
                );
                this.syncDirtyGuard();
                await this.reRender();
                Espo.Ui.success(
                    this.translate('editorReloaded', 'messages', 'DocumentBuilderTemplate'),
                );
            } catch (xhr) {
                if (xhr) {
                    xhr.errorIsHandled = true;
                }

                this.saveCoordinator.failReload();
                this.syncDirtyGuard();
                await this.reRender();
            }
        }

        executeCommand(command, {render = true} = {}) {
            if (!this.editorState) {
                throw new Error('The editor state is not ready.');
            }

            if (this.isSaveBusy()) {
                return false;
            }

            const changed = this.editorState.execute(command);

            if (changed) {
                this.saveCoordinator.noteEdit();
                this.invalidatePreview();
                this.syncDirtyGuard();
                if (render) this.reRender();
            }

            return changed;
        }

        invalidatePreview() {
            this.releasePdfPreview();
            this.previewStatus = 'idle';
            this.previewMode = null;
            this.previewValues = new Map();
            this.canvasPreviewOpen = false;
            this.pdfProofError = false;
        }

        selectNode(nodeId) {
            if (!this.editorState) {
                return false;
            }

            return this.editorState.select(nodeId);
        }

        handleKeydown(event) {
            if (this.flowDrag && event.key === 'Escape') {
                event.preventDefault();
                this.cancelFlowDrag();

                return;
            }
            if (this.previewPdfUrl && event.key === 'Escape') {
                event.preventDefault();
                this.actionClosePdfPreview();

                return;
            }
            if (this.canvasPreviewOpen && event.key === 'Escape') {
                event.preventDefault();
                this.actionBackToEdit();

                return;
            }

            if (this.state !== 'ready' || !Keyboard.isManualSave(event)) {
                return;
            }

            event.preventDefault();
            this.actionSave();
        }

        isSaveBusy() {
            return this.saveCoordinator ? this.saveCoordinator.isBusy() : false;
        }

        syncDirtyGuard() {
            this.dirtyGuard.sync(Boolean(this.editorState && this.editorState.isDirty()));
        }

        remove() {
            this.isRemoved = true;
            this.model.abortLastFetch();
            document.removeEventListener('keydown', this.keydownHandler);
            document.removeEventListener('selectionchange', this.selectionchangeHandler);
            this.dirtyGuard.dispose();
            this.releasePdfPreview();

            return super.remove();
        }
    };
});
