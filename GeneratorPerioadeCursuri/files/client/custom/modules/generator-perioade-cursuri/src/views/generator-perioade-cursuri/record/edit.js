define('generator-perioade-cursuri:views/generator-perioade-cursuri/record/edit', ['views/record/edit'], function (EditRecordView) {
    return class extends EditRecordView {
        setup() {
            if (this.model.isNew()) {
                this.isWide = true;
                this.sideDisabled = true;
            }

            super.setup();
        }

        afterRender() {
            super.afterRender();

            if (!this.model.isNew()) {
                return;
            }

            this.element.classList.add('generator-perioade-cursuri-create');
        }
    };
});
