define(['controllers/record'], (RecordController) => {
    return class extends RecordController {
        beforeEditor() {
            this.handleCheckAccess('edit');
        }

        async actionEditor(options) {
            if (!options.id) {
                this.baseController.error404();

                return;
            }

            const model = await this.modelFactory.create(this.name);

            model.id = options.id;

            this.main('document-builder:views/editor/shell', {
                model,
                scope: this.name,
            });
        }
    };
});
