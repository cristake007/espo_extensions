define(['views/modal'], (ModalView) => {
    return class extends ModalView {
        template = 'document-builder:editor/modals/revision-conflict'

        scope = 'DocumentBuilderTemplate'

        backdrop = true

        setup() {
            this.headerText = this.translate(
                'editorRevisionConflictTitle',
                'labels',
                'DocumentBuilderTemplate',
            );
            this.actualRevision = this.options.actualRevision;
            this.buttonList = [
                {
                    name: 'retry',
                    label: 'Retry Save',
                    style: 'danger',
                    onClick: () => {
                        this.trigger('retry', this.actualRevision);
                        this.close();
                    },
                },
                {
                    name: 'reload',
                    label: 'Reload Draft',
                    style: 'primary',
                    onClick: () => {
                        this.trigger('reload');
                        this.close();
                    },
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];
        }

        data() {
            return {actualRevision: this.actualRevision};
        }
    };
});
