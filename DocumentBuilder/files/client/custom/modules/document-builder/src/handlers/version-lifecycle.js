define(['action-handler'], (ActionHandler) => {
    return class extends ActionHandler {
        async createDraftFromVersion() {
            const templateId = this.view.model.get('templateId');

            if (!templateId) {
                return;
            }

            await this.view.confirm({
                message: this.view.translate(
                    'confirmDraftFromVersion',
                    'messages',
                    'DocumentBuilderTemplateVersion',
                ),
                confirmText: this.view.translate(
                    'Create Draft from Version',
                    'actions',
                    'DocumentBuilderTemplateVersion',
                ),
            });
            this.view.disableMenuItem('draftFromVersion');

            try {
                const template = await Espo.Ajax.getRequest(
                    `DocumentBuilderTemplate/${templateId}`,
                );
                await Espo.Ajax.postRequest(
                    `DocumentBuilder/template/${templateId}/draft-from-version`,
                    {
                        expectedRevision: template.revision,
                        versionId: this.view.model.id,
                    },
                );
                Espo.Ui.success(
                    this.view.translate(
                        'draftCreated',
                        'messages',
                        'DocumentBuilderTemplateVersion',
                    ),
                );
                this.view.getRouter().navigate(
                    `#DocumentBuilderTemplate/view/${templateId}`,
                    {trigger: true},
                );
            } finally {
                this.view.enableMenuItem('draftFromVersion');
            }
        }

        isDraftFromVersionVisible() {
            return Boolean(this.view.model.get('templateId'));
        }
    };
});
