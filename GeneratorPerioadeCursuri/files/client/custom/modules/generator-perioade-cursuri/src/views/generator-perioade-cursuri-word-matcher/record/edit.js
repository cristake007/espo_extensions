define(
    'generator-perioade-cursuri:views/generator-perioade-cursuri-word-matcher/record/edit',
    ['generator-perioade-cursuri:views/generator-perioade-cursuri/record/edit'],
    function (GeneratorEditRecordView) {
        return class extends GeneratorEditRecordView {
            setup() {
                this.isWide = true;
                this.sideDisabled = true;

                super.setup();
            }

            afterRender() {
                super.afterRender();
                this.element.classList.add('generator-perioade-cursuri-create');
                this.element.classList.add('generator-perioade-cursuri-word-matcher-page');
            }
        };
    }
);
