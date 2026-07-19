define(['action-handler'], (ActionHandler) => {
    return class extends ActionHandler {
        openEditor() {
            this.view.getRouter().navigate(
                `#DocumentBuilderTemplate/editor/${this.view.model.id}`,
                {trigger: true},
            );
        }

        async duplicate() {
            await this.view.confirm({
                message: this.view.translate('confirmDuplicate', 'messages', 'DocumentBuilderTemplate'),
                confirmText: this.view.translate('Duplicate', 'actions', 'DocumentBuilderTemplate'),
            });

            this.view.disableMenuItem('duplicateTemplate');

            try {
                const result = await Espo.Ajax.postRequest(
                    `DocumentBuilder/template/${this.view.model.id}/duplicate`,
                    {expectedRevision: this.view.model.get('revision')},
                );

                Espo.Ui.success(
                    this.view.translate('duplicated', 'messages', 'DocumentBuilderTemplate'),
                );
                this.view.getRouter().navigate(
                    `#DocumentBuilderTemplate/view/${result.templateId}`,
                    {trigger: true},
                );
            } finally {
                this.view.enableMenuItem('duplicateTemplate');
            }
        }

        async archive() {
            await this.view.confirm({
                message: this.view.translate('confirmArchive', 'messages', 'DocumentBuilderTemplate'),
                confirmText: this.view.translate('Archive', 'actions', 'DocumentBuilderTemplate'),
            });

            this.view.disableMenuItem('archiveTemplate');

            try {
                await Espo.Ajax.postRequest(
                    `DocumentBuilder/template/${this.view.model.id}/archive`,
                    {expectedRevision: this.view.model.get('revision')},
                );
                await this.view.model.fetch();
                Espo.Ui.success(
                    this.view.translate('archived', 'messages', 'DocumentBuilderTemplate'),
                );
            } finally {
                this.view.enableMenuItem('archiveTemplate');
            }
        }

        async createDraftFromPublishedVersion() {
            await this.restoreVersion(this.view.model.get('currentPublishedVersionId'));
        }

        async restoreVersion(versionId) {
            if (!versionId) {
                return;
            }

            await this.view.confirm({
                message: this.view.translate('confirmDraftFromVersion', 'messages', 'DocumentBuilderTemplate'),
                confirmText: this.view.translate(
                    'Create Draft from Version',
                    'actions',
                    'DocumentBuilderTemplate',
                ),
            });

            this.view.disableMenuItem('draftFromPublishedVersion');

            try {
                await Espo.Ajax.postRequest(
                    `DocumentBuilder/template/${this.view.model.id}/draft-from-version`,
                    {
                        expectedRevision: this.view.model.get('revision'),
                        versionId,
                    },
                );
                await this.view.model.fetch();
                Espo.Ui.success(
                    this.view.translate('draftCreated', 'messages', 'DocumentBuilderTemplate'),
                );
            } finally {
                this.view.enableMenuItem('draftFromPublishedVersion');
            }
        }

        viewVersions() {
            this.view.getRouter().navigate(
                `#DocumentBuilderTemplate/related/${this.view.model.id}/versions`,
                {trigger: true},
            );
        }

        isArchiveVisible() {
            return this.view.model.get('status') !== 'Archived';
        }

        isEditorVisible() {
            return this.view.model.get('status') === 'Draft';
        }

        isDraftFromPublishedVersionVisible() {
            return this.view.model.get('status') === 'Published' &&
                Boolean(this.view.model.get('currentPublishedVersionId'));
        }

        isVersionHistoryVisible() {
            return Boolean(this.view.model.get('currentPublishedVersionId'));
        }
    };
});
