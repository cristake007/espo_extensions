define([
    'view',
    'document-builder:editor/state/editor-state',
    'document-builder:editor/validation/layout-precheck',
    'document-builder:services/draft-api',
    'document-builder:editor/save/draft-save-coordinator',
    'document-builder:editor/save/keyboard',
    'document-builder:editor/save/dirty-guard',
], (
    View,
    EditorState,
    LayoutPrecheck,
    DraftApi,
    DraftSaveCoordinator,
    Keyboard,
    DirtyGuard,
) => {
    return class extends View {
        template = 'document-builder:editor/shell'

        events = {
            'click [data-action="backToTemplate"]': 'actionBackToTemplate',
            'click [data-action="retry"]': 'actionRetry',
            'click [data-action="undo"]': 'actionUndo',
            'click [data-action="redo"]': 'actionRedo',
            'click [data-action="save"]': 'actionSave',
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

            document.addEventListener('keydown', this.keydownHandler);
        }

        data() {
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

                this.editorState = new EditorState(this.model.get('currentDraftLayout'));
                this.saveCoordinator = new DraftSaveCoordinator({
                    editorState: this.editorState,
                    draftApi: new DraftApi(),
                    precheck: new LayoutPrecheck(),
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
