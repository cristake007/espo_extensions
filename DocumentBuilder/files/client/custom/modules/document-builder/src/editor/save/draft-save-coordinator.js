define([], () => {
    return class DraftSaveCoordinator {
        constructor(options) {
            this.editorState = options.editorState;
            this.draftApi = options.draftApi;
            this.precheck = options.precheck;
            this.templateId = options.templateId;
            this.revision = options.revision;
            this.status = 'idle';
            this.errorMessage = null;
            this.conflict = null;
            this.inFlight = false;
            this.sourceChangeConfirmed = false;
        }

        async save() {
            return this.saveWithRevision(this.revision);
        }

        async retryConflict(conflict) {
            if (!conflict || !Number.isInteger(conflict.actualRevision)) {
                throw new TypeError('A conflict retry requires the server revision.');
            }

            return this.saveWithRevision(conflict.actualRevision);
        }

        async saveWithRevision(expectedRevision) {
            if (this.inFlight) {
                return {status: 'busy'};
            }

            const layout = this.editorState.getLayout();
            const validation = this.precheck.check(layout);

            if (!validation.valid) {
                this.status = 'error';
                this.errorMessage = 'editorClientValidationFailed';

                return {status: 'error', validationErrors: validation.errors};
            }

            this.inFlight = true;
            this.status = 'saving';
            this.errorMessage = null;
            this.conflict = null;

            try {
                const result = await this.draftApi.save(
                    this.templateId,
                    layout,
                    expectedRevision,
                    this.sourceChangeConfirmed,
                );

                if (
                    !result ||
                    !Number.isInteger(result.revision) ||
                    result.revision < 0 ||
                    !result.layout ||
                    typeof result.layout !== 'object'
                ) {
                    this.status = 'error';
                    this.errorMessage = 'editorInvalidSaveResponse';

                    return {status: 'error', error: new TypeError('The draft save response is invalid.')};
                }

                try {
                    this.editorState.acceptSavedLayout(result.layout);
                } catch (error) {
                    this.status = 'error';
                    this.errorMessage = 'editorInvalidSaveResponse';

                    return {status: 'error', error};
                }

                this.revision = result.revision;
                this.status = 'saved';
                this.sourceChangeConfirmed = false;

                return {status: 'saved', result};
            } catch (error) {
                const conflict = this.draftApi.getRevisionConflict(error);

                if (conflict) {
                    this.status = 'conflict';
                    this.errorMessage = 'editorRevisionConflict';
                    this.conflict = conflict;

                    return {status: 'conflict', conflict};
                }

                this.status = 'error';
                this.errorMessage = this.draftApi.getErrorMessage(error);

                return {status: 'error', error};
            } finally {
                this.inFlight = false;
            }
        }

        acceptReload(layout, revision) {
            if (!Number.isInteger(revision) || revision < 0) {
                throw new TypeError('A reloaded draft must have a valid revision.');
            }

            this.editorState.reloadSavedLayout(layout);
            this.revision = revision;
            this.status = 'idle';
            this.errorMessage = null;
            this.conflict = null;
            this.sourceChangeConfirmed = false;
        }

        beginReload() {
            if (this.inFlight) {
                return false;
            }

            this.status = 'reloading';
            this.errorMessage = null;

            return true;
        }

        failReload() {
            this.status = 'error';
            this.errorMessage = 'editorReloadFailed';
        }

        noteEdit() {
            if (this.status !== 'saving') {
                this.status = 'idle';
                this.errorMessage = null;
            }
        }

        confirmSourceChange() {
            this.sourceChangeConfirmed = true;
        }

        isSaving() {
            return this.inFlight;
        }

        isBusy() {
            return this.inFlight || this.status === 'reloading';
        }
    };
});
