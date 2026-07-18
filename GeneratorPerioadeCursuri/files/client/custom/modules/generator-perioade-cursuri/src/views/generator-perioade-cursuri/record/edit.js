define('generator-perioade-cursuri:views/generator-perioade-cursuri/record/edit', ['views/record/edit'], function (EditRecordView) {
    return class extends EditRecordView {
        setup() {
            if (this.isGeneratorMainPage() || this.model.isNew()) {
                this.isWide = true;
                this.sideDisabled = true;
            }

            super.setup();
        }

        isGeneratorMainPage() {
            const entityType = this.entityType || this.scope ||
                this.model.entityType || this.model.name;

            return entityType === 'GeneratorPerioadeCursuri';
        }

        onInvalid(invalidFieldList) {
            this.generatorInvalidFieldList = invalidFieldList.slice();

            super.onInvalid(invalidFieldList);
        }

        afterNotValid() {
            const invalidFieldList = this.generatorInvalidFieldList || [];

            this.generatorInvalidFieldList = [];

            if (invalidFieldList.includes('name') && this.isGenerationNameMissing()) {
                Espo.Ui.error(
                    this.translate(
                        'generationNameRequired',
                        'messages',
                        'GeneratorPerioadeCursuri'
                    )
                );
                this.enableActionItems();

                return;
            }

            super.afterNotValid();
        }

        isGenerationNameMissing() {
            const value = this.model.get('name');

            return value === null || value === '' ||
                (typeof value === 'string' && value.trim() === '');
        }

        afterRender() {
            super.afterRender();

            if (this.isGeneratorMainPage()) {
                this.element.classList.add('generator-perioade-cursuri-page');

                return;
            }

            if (!this.model.isNew()) {
                return;
            }

            this.element.classList.add('generator-perioade-cursuri-create');
        }
    };
});
