define('generator-perioade-cursuri:views/fields/source-file', ['views/fields/file'], function (FileFieldView) {
    return class extends FileFieldView {
        createEditTemplateContent = `
            <div class="attachment-upload generator-source-upload">
                <div class="attachment-button{{#if id}} hidden{{/if}}">
                    <label class="attach-file-label generator-source-upload-label" title="{{translate 'Attach File'}}" tabindex="0">
                        <span class="generator-source-upload-icon fas fa-cloud-upload-alt text-primary" aria-hidden="true"></span>
                        <span class="generator-source-upload-title">{{translate 'uploadSourceTitle' category='labels' scope='GeneratorPerioadeCursuri'}}</span>
                        <span class="generator-source-upload-action">
                            <span class="btn btn-default generator-source-upload-button">{{translate 'uploadSourceButton' category='labels' scope='GeneratorPerioadeCursuri'}}</span>
                            <span class="generator-source-upload-drop-hint text-muted">{{translate 'uploadSourceAction' category='labels' scope='GeneratorPerioadeCursuri'}}</span>
                        </span>
                        <span class="generator-source-upload-formats" aria-label="{{translate 'uploadSourceFormats' category='labels' scope='GeneratorPerioadeCursuri'}}">
                            <span class="label label-default generator-source-upload-format">
                                <span class="fas fa-file-csv" aria-hidden="true"></span>
                                CSV
                            </span>
                            <span class="label label-default generator-source-upload-format">
                                <span class="fas fa-file-excel" aria-hidden="true"></span>
                                XLSX
                            </span>
                        </span>
                        <input
                            type="file"
                            class="file"
                            {{#if acceptAttribute}}accept="{{acceptAttribute}}"{{/if}}
                            tabindex="-1"
                        >
                    </label>
                </div>
                <div class="attachment"></div>
            </div>
        `;

        init() {
            if (this.model.isNew()) {
                this.editTemplateContent = this.createEditTemplateContent;
            }

            super.init();
        }

        afterRender() {
            super.afterRender();

            if (!this.model.isNew() || !this.isEditMode()) {
                return;
            }

            const dropZone = this.$el.find('.generator-source-upload-label');

            this.$el.off('.generatorSourceUpload');
            this.$el.on('dragover.generatorSourceUpload', () => dropZone.addClass('is-dragover'));
            this.$el.on('dragleave.generatorSourceUpload drop.generatorSourceUpload', () => dropZone.removeClass('is-dragover'));
            this.$el.on('keydown.generatorSourceUpload', '.generator-source-upload-label', event => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                this.$el.find('input.file').trigger('click');
            });
        }
    };
});
