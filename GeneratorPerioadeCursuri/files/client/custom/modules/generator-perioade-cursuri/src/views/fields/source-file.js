define('generator-perioade-cursuri:views/fields/source-file', ['views/fields/file'], function (FileFieldView) {
    return class extends FileFieldView {
        createEditTemplateContent = `
            <div class="attachment-upload generator-source-upload">
                <div class="attachment-button{{#if id}} hidden{{/if}}">
                    <label class="attach-file-label generator-source-upload-label" title="{{translate 'Attach File'}}" tabindex="0">
                        <span class="generator-source-upload-icon fas fa-cloud-upload-alt" aria-hidden="true"></span>
                        <span class="generator-source-upload-title">{{translate 'uploadSourceTitle' category='labels' scope='GeneratorPerioadeCursuri'}}</span>
                        <span class="generator-source-upload-action">{{translate 'uploadSourceAction' category='labels' scope='GeneratorPerioadeCursuri'}}</span>
                        <span class="generator-source-upload-formats">{{translate 'uploadSourceFormats' category='labels' scope='GeneratorPerioadeCursuri'}}</span>
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

        setup() {
            if (this.model.isNew()) {
                this.editTemplateContent = this.createEditTemplateContent;
            }

            super.setup();
        }

        afterRender() {
            super.afterRender();

            if (!this.model.isNew() || !this.isEditMode()) {
                return;
            }

            const dropZone = this.$el.find('.generator-source-upload-label');

            this.$el.on('dragover.generatorSourceUpload', () => dropZone.addClass('is-dragover'));
            this.$el.on('dragleave.generatorSourceUpload drop.generatorSourceUpload', () => dropZone.removeClass('is-dragover'));
        }
    };
});
