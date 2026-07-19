define([
    'view',
    'document-builder:editor/state/editor-state',
    'document-builder:editor/validation/layout-precheck',
    'document-builder:services/draft-api',
    'document-builder:editor/save/draft-save-coordinator',
    'document-builder:editor/save/keyboard',
    'document-builder:editor/save/dirty-guard',
    'document-builder:editor/commands/update-document',
    'document-builder:editor/geometry/page-geometry',
    'document-builder:editor/page-settings',
], (
    View,
    EditorState,
    LayoutPrecheck,
    DraftApi,
    DraftSaveCoordinator,
    Keyboard,
    DirtyGuard,
    UpdateDocumentCommand,
    PageGeometry,
    PageSettings,
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
            this.pageGeometry = new PageGeometry(this.customPageSizes);
            this.zoom = 100;

            document.addEventListener('keydown', this.keydownHandler);
        }

        data() {
            const pageSettings = this.getPageSettingsData();

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
                    !this.isSaveBusy()
                ),
                isSaving: this.saveCoordinator ? this.saveCoordinator.status === 'saving' : false,
                isReloading: this.saveCoordinator ? this.saveCoordinator.status === 'reloading' : false,
                isSaved: this.saveCoordinator ? this.saveCoordinator.status === 'saved' : false,
                saveError: this.saveCoordinator ? this.saveCoordinator.errorMessage : null,
                ...pageSettings,
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
            if (!this.loadStarted) {
                this.loadStarted = true;
                this.loadModel();

                return;
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
                    precheck: new LayoutPrecheck(this.customPageSizes),
                    templateId: this.model.id,
                    revision: this.model.get('revision'),
                });
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
