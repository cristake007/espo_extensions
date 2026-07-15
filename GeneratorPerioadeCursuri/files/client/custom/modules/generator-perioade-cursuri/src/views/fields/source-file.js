define('generator-perioade-cursuri:views/fields/source-file', ['views/fields/file'], function (FileFieldView) {
    return class extends FileFieldView {
        createEditTemplateContent = `
            <div class="attachment-upload generator-source-upload">
                <div class="attachment-button{{#if id}} hidden{{/if}}">
                    <label class="attach-file-label generator-source-upload-label" title="{{translate 'Attach File'}}" tabindex="0">
                        <span class="generator-source-upload-icon fas fa-cloud-upload-alt text-primary" aria-hidden="true"></span>
                        <span class="generator-source-upload-title">{{uploadTitle}}</span>
                        <span class="generator-source-upload-action">
                            <span class="btn btn-default generator-source-upload-button">{{uploadButton}}</span>
                            <span class="generator-source-upload-drop-hint text-muted">{{uploadAction}}</span>
                        </span>
                        <span class="generator-source-upload-formats" aria-label="{{uploadFormats}}">
                            {{#each uploadFormatList}}
                            <span class="label label-default generator-source-upload-format">
                                <span class="{{iconClass}}" aria-hidden="true"></span>
                                {{label}}
                            </span>
                            {{/each}}
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
            this.editTemplateContent = this.createEditTemplateContent;

            super.init();
        }

        data() {
            const data = super.data();
            const scope = this.entityType || 'GeneratorPerioadeCursuri';
            const titleKey = {
                sourceFile: 'uploadSourceTitle',
                wordTemplateFile: 'uploadWordTitle',
                wordScheduleFile: 'uploadScheduleTitle',
                xmlScheduleFile: 'uploadXmlScheduleTitle',
                wpScheduleFile: 'uploadWpScheduleTitle'
            }[this.name] || 'uploadFileTitle';
            const acceptList = Array.isArray(this.accept) ? this.accept : [];

            data.uploadTitle = this.translate(titleKey, 'labels', scope);
            data.uploadButton = this.translate('uploadSourceButton', 'labels', scope);
            data.uploadAction = this.translate('uploadSourceAction', 'labels', scope);
            data.uploadFormatList = acceptList.map(value => {
                const label = value.replace(/^\./, '').toUpperCase();

                return {
                    label: label,
                    iconClass: this.getFormatIconClass(label)
                };
            });
            data.uploadFormats = data.uploadFormatList.map(item => item.label).join(', ');

            return data;
        }

        getFormatIconClass(format) {
            if (format === 'DOC' || format === 'DOCX') {
                return 'fas fa-file-word';
            }

            if (format === 'XLS' || format === 'XLSX') {
                return 'fas fa-file-excel';
            }

            if (format === 'CSV') {
                return 'fas fa-file-csv';
            }

            return 'fas fa-file';
        }

        afterRender() {
            super.afterRender();

            if (!this.isEditMode()) {
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
