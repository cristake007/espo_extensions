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
    'document-builder:editor/style/style-resolver',
    'document-builder:editor/renderer/browser-renderer',
    'document-builder:editor/validation/editor-validator',
    'document-builder:editor/commands/add-flow-node',
    'document-builder:editor/commands/move-flow-node',
    'document-builder:editor/commands/remove-flow-node',
    'document-builder:editor/commands/update-node',
    'document-builder:editor/commands/update-data-source',
    'document-builder:services/entity-catalogue-api',
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
    StyleResolver,
    BrowserRenderer,
    EditorValidator,
    AddFlowNodeCommand,
    MoveFlowNodeCommand,
    RemoveFlowNodeCommand,
    UpdateNodeCommand,
    UpdateDataSourceCommand,
    EntityCatalogueApi,
) => {
    return class extends View {
        template = 'document-builder:editor/shell'

        events = {
            'click [data-action="backToTemplate"]': 'actionBackToTemplate',
            'click [data-action="retry"]': 'actionRetry',
            'click [data-action="undo"]': 'actionUndo',
            'click [data-action="redo"]': 'actionRedo',
            'click [data-action="save"]': 'actionSave',
            'click [data-action="zoomIn"]': 'actionZoomIn',
            'click [data-action="zoomOut"]': 'actionZoomOut',
            'click [data-action="fitWidth"]': 'actionFitWidth',
            'click [data-action="fitPage"]': 'actionFitPage',
            'change [data-page-setting]': 'changePageSetting',
            'change [data-source-setting]': 'changeSourceSetting',
            'click [data-action="retryEntityCatalogue"]': 'actionRetryEntityCatalogue',
            'click [data-action="addFlowSection"]': 'actionAddFlowSection',
            'click [data-action="addFlowContainer"]': 'actionAddFlowContainer',
            'click [data-action="addHeading"]': 'actionAddContent',
            'click [data-action="addStaticText"]': 'actionAddContent',
            'click [data-action="addParagraph"]': 'actionAddContent',
            'click [data-action="addDivider"]': 'actionAddContent',
            'click [data-action="addSpacer"]': 'actionAddContent',
            'click [data-action="addPageBreak"]': 'actionAddContent',
            'click [data-action="selectFlowNode"]': 'actionSelectFlowNode',
            'keydown [data-action="selectFlowNode"]': 'handleNodeKeydown',
            'click [data-action="selectBreadcrumb"]': 'actionSelectFlowNode',
            'click [data-action="focusValidationIssue"]': 'actionFocusValidationIssue',
            'click [data-action="removeFlowNode"]': 'actionRemoveFlowNode',
            'click [data-action="moveFlowUp"]': 'actionMoveFlowUp',
            'click [data-action="moveFlowDown"]': 'actionMoveFlowDown',
            'change [data-flow-setting]': 'changeFlowSetting',
            'change [data-content-setting]': 'changeContentSetting',
            'change [data-basic-flow-setting]': 'changeBasicFlowSetting',
            'change [data-style-setting]': 'changeStyleSetting',
            'paste [data-content-setting="text"]': 'pasteContentText',
            'click [data-rich-mark]': 'actionToggleRichMark',
            'change [data-rich-color]': 'changeRichColor',
            'click [data-action="addInlineVariable"]': 'actionAddInlineVariable',
            'dragstart [draggable="true"]': 'handleFlowDragStart',
            'dragover [data-flow-drop]': 'handleFlowDragOver',
            'dragleave [data-flow-drop]': 'handleFlowDragLeave',
            'drop [data-flow-drop]': 'handleFlowDrop',
            'dragend [draggable="true"]': 'handleFlowDragEnd',
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
            this.editorValidator = new EditorValidator(this.customPageSizes, this.flowLimits);
            this.entityCatalogueApi = new EntityCatalogueApi();
            this.entityCatalogue = [];
            this.entityCatalogueStatus = 'loading';
            this.maxRelationshipDepth = config.maxRelationshipDepth ||
                metadataDefaults.maxRelationshipDepth || 2;
            this.pendingFocusNodeId = null;
            this.zoom = 100;

            document.addEventListener('keydown', this.keydownHandler);
        }

        data() {
            const pageSettings = this.getPageSettingsData();
            const flow = this.getFlowData();
            const validation = this.getValidationData();
            const source = this.getSourceData();

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
                ...pageSettings,
                ...flow,
                ...validation,
                ...source,
            };
        }

        getFlowData() {
            if (!this.editorState) {
                return {flowRows: [], selectedFlowNode: null, flowBreadcrumbs: []};
            }

            const layout = this.editorState.getLayout();
            const selectedId = this.editorState.getSelectedId();
            const locations = NodeTree.index(layout);
            const rendered = this.browserRenderer.render(layout, {selectedId, zoom: this.zoom});
            const rows = rendered.rows;
            const selected = selectedId ? locations.get(selectedId) : null;
            const effectiveStyle = selected ? this.styleResolver.resolve(layout, selectedId) : null;

            return {
                flowRows: rows,
                hasFlowRows: rows.length > 0,
                approximatedPageCount: rendered.pageCount,
                selectedFlowNode: selected ? {
                    ...selected.node,
                    label: ({
                        'flow-section': 'Flow Section', 'flow-container': 'Flow Container',
                        heading: 'Heading', 'static-text': 'Static Text', paragraph: 'Paragraph',
                        divider: 'Divider', spacer: 'Spacer', 'page-break': 'Page Break',
                    })[selected.node.type],
                    isSection: selected.node.type === 'flow-section',
                    isContainer: selected.node.type === 'flow-container',
                    isHeading: selected.node.type === 'heading',
                    isStaticText: selected.node.type === 'static-text',
                    isParagraph: selected.node.type === 'paragraph',
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
                    plainText: selected.node.type === 'static-text' ? selected.node.text :
                        RichText.toPlainText(selected.node.content),
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
            const px = value => this.pageGeometry.millimetresToPixels(value, this.zoom);

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
                zoom: frame.zoom,
            };
        }

        afterRender() {
            this.renderContentNodes();
            if (!this.loadStarted) {
                this.loadStarted = true;
                this.loadModel();

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
                this.syncDirtyGuard();
                this.reRender();
            }
        }

        actionRedo() {
            if (!this.isSaveBusy() && this.editorState && this.editorState.redo()) {
                this.saveCoordinator.noteEdit();
                this.syncDirtyGuard();
                this.reRender();
            }
        }

        actionAddFlowSection() {
            this.addFlowNode('flow-section', {region: 'sections', parentId: null, index: null});
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

        renderContentNodes() {
            if (!this.editorState || !this.element) return;
            const layout = this.editorState.getLayout();
            const locations = NodeTree.index(layout);
            const rendered = this.browserRenderer.render(layout, {
                selectedId: this.editorState.getSelectedId(),
                zoom: this.zoom,
            });
            const rows = new Map(rendered.rows.map(row => [row.id, row]));
            this.element.querySelectorAll('[data-rich-content-id]').forEach(host => {
                const node = locations.get(host.dataset.richContentId)?.node;
                const row = rows.get(host.dataset.richContentId);
                if (!node) return;
                host.classList.toggle('is-sample', Boolean(row?.isEmpty));
                if (row?.isEmpty) {
                    host.textContent = this.translate(
                        row.sampleKey,
                        'messages',
                        'DocumentBuilderTemplate',
                    );
                    host.setAttribute('aria-label', this.translate(
                        'editorEmptyContentLabel',
                        'labels',
                        'DocumentBuilderTemplate',
                    ));

                    return;
                }
                host.removeAttribute('aria-label');
                if (node.type === 'static-text') host.textContent = node.text;
                else RichText.render(host, node.content);
            });
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
            else if (input.dataset.contentSetting === 'alignment') patch.alignment = input.value;
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

        actionToggleRichMark(event) {
            const location = this.selectedContentNode();
            if (!location || !location.node.content) return;
            this.executeCommand(new UpdateNodeCommand(location.node.id, {
                content: RichText.toggleMark(location.node.content, event.currentTarget.dataset.richMark),
            }));
        }

        changeRichColor(event) {
            const location = this.selectedContentNode();
            if (!location || !location.node.content) return;
            this.executeCommand(new UpdateNodeCommand(location.node.id, {
                content: RichText.setColor(location.node.content, event.currentTarget.value),
            }));
        }

        actionAddInlineVariable() {
            const location = this.selectedContentNode();
            if (!location || !location.node.content) return;
            const tokenId = `token_${Date.now().toString(36)}`;
            this.executeCommand(new UpdateNodeCommand(location.node.id, {
                content: RichText.appendVariable(location.node.content, tokenId, 'Variable'),
            }));
        }

        addFlowNode(type, target) {
            const command = new AddFlowNodeCommand(this.flowStructure, type, target);

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
            if (this.selectNode(event.currentTarget.dataset.nodeId)) this.reRender();
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

        async actionRemoveFlowNode() {
            const nodeId = this.editorState && this.editorState.getSelectedId();

            if (!nodeId) return;
            const location = NodeTree.getLocation(this.editorState.getLayout(), nodeId);

            if (location?.node.children?.length) {
                await this.confirm({
                    message: this.translate(
                        'confirmRemoveComplexNode',
                        'messages',
                        'DocumentBuilderTemplate',
                    ),
                    confirmText: this.translate('Remove'),
                });
            }

            this.executeCommand(new RemoveFlowNodeCommand(this.flowStructure, nodeId));
        }

        actionMoveFlowUp() {
            this.moveSelectedFlow(-1);
        }

        actionMoveFlowDown() {
            this.moveSelectedFlow(1);
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
            const source = event.currentTarget;

            this.flowDrag = source.dataset.libraryType ?
                {kind: 'library', type: source.dataset.libraryType} :
                {kind: 'node', nodeId: source.dataset.nodeId};
            event.dataTransfer.effectAllowed = this.flowDrag.kind === 'library' ? 'copy' : 'move';
            event.dataTransfer.setData('text/plain', JSON.stringify(this.flowDrag));
        }

        handleFlowDragOver(event) {
            if (!this.flowDrag || !this.editorState) return;

            const layout = this.editorState.getLayout();
            const target = this.flowDropTarget(event.currentTarget);
            const node = this.flowDrag.kind === 'library' ?
                this.flowStructure.createNode(this.flowDrag.type) :
                NodeTree.getLocation(layout, this.flowDrag.nodeId)?.node;

            if (!node) return;

            try {
                this.flowStructure.assertTarget(
                    layout,
                    node,
                    target,
                    this.flowDrag.kind === 'node' ? this.flowDrag.nodeId : null,
                );
            } catch (error) {
                event.currentTarget.classList.remove('is-drag-over');

                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = this.flowDrag.kind === 'library' ? 'copy' : 'move';
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
                if (this.flowDrag.kind === 'library') {
                    this.addFlowNode(this.flowDrag.type, target);
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
                this.flowDrag = null;
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

        handleFlowDragEnd() {
            this.flowDrag = null;
            this.element.querySelectorAll('.is-drag-over').forEach(element => {
                element.classList.remove('is-drag-over');
            });
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

            await this.confirm({
                message: this.translate(
                    'confirmEntitySourceChange',
                    'messages',
                    'DocumentBuilderTemplate',
                ),
                confirmText: this.translate('Change Source', 'actions', 'DocumentBuilderTemplate'),
            });

            if (this.executeCommand(new UpdateDataSourceCommand(next))) {
                this.saveCoordinator.confirmSourceChange();
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

        executeCommand(command) {
            if (!this.editorState) {
                throw new Error('The editor state is not ready.');
            }

            if (this.isSaveBusy()) {
                return false;
            }

            const changed = this.editorState.execute(command);

            if (changed) {
                this.saveCoordinator.noteEdit();
                this.syncDirtyGuard();
                this.reRender();
            }

            return changed;
        }

        selectNode(nodeId) {
            if (!this.editorState) {
                return false;
            }

            return this.editorState.select(nodeId);
        }

        handleKeydown(event) {
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
            this.dirtyGuard.dispose();

            return super.remove();
        }
    };
});
