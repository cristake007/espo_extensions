define(
    'generator-perioade-cursuri:views/generator-perioade-cursuri-xml-converter/record/detail',
    ['views/record/detail'],
    function (DetailRecordView) {
        return class extends DetailRecordView {
            setup() {
                super.setup();

                this.xmlGenerationInProgress = false;

                this.addButton({
                    name: 'generateXml',
                    label: 'Generate XML',
                    style: 'primary',
                    iconClass: 'fas fa-code'
                }, true);

                this.addButton({
                    name: 'downloadXml',
                    label: 'Download XML',
                    style: 'default',
                    iconClass: 'fas fa-download'
                }, true);
            }

            afterRender() {
                super.afterRender();

                this.updateGenerateXmlButtonState();
                this.updateDownloadXmlButtonState();
            }

            async actionGenerateXml() {
                if (this.xmlGenerationInProgress || !this.hasRequiredXmlInput()) {
                    if (!this.xmlGenerationInProgress) {
                        Espo.Ui.warning(this.translate(
                            'xmlGenerateUnavailable',
                            'messages',
                            'GeneratorPerioadeCursuriXmlConverter'
                        ));
                    }

                    return;
                }

                this.xmlGenerationInProgress = true;
                this.updateGenerateXmlButtonState();
                Espo.Ui.notify(this.translate(
                    'xmlGenerating',
                    'messages',
                    'GeneratorPerioadeCursuriXmlConverter'
                ));

                try {
                    const result = await Espo.Ajax.postRequest(
                        'GeneratorPerioadeCursuriXmlConverter/' + encodeURIComponent(this.model.id) + '/generateXml',
                        {}
                    );

                    this.model.set({
                        xmlConvertedFileId: result.attachmentId || null,
                        xmlConvertedFileName: result.filename || null,
                        xmlConvertedFileType: 'application/xml',
                        xmlConvertedAt: result.timestamp || null
                    });

                    try {
                        await this.model.fetch();
                    } catch (e) {}

                    Espo.Ui.notify(false);
                    Espo.Ui.success(this.translate(
                        'xmlGenerateSucceeded',
                        'messages',
                        'GeneratorPerioadeCursuriXmlConverter'
                    ).replace('{count}', String(result.eventCount || 0)));
                } catch (e) {
                    Espo.Ui.notify(false);
                    Espo.Ui.error(this.getXmlErrorMessage(e));
                } finally {
                    this.xmlGenerationInProgress = false;
                    this.updateGenerateXmlButtonState();
                    this.updateDownloadXmlButtonState();
                }
            }

            actionDownloadXml() {
                const attachmentId = this.model.get('xmlConvertedFileId');

                if (!attachmentId) {
                    Espo.Ui.warning(this.translate(
                        'xmlDownloadUnavailable',
                        'messages',
                        'GeneratorPerioadeCursuriXmlConverter'
                    ));

                    return;
                }

                window.open(
                    '?entryPoint=download&id=' + encodeURIComponent(attachmentId),
                    '_blank',
                    'noopener'
                );
            }

            hasRequiredXmlInput() {
                const startPostId = this.model.get('startPostId');

                return !!this.model.id &&
                    !!this.model.get('xmlScheduleFileId') &&
                    startPostId !== null &&
                    startPostId !== undefined &&
                    startPostId !== '';
            }

            updateGenerateXmlButtonState() {
                const button = this.element.querySelector('[data-action="generateXml"]');

                if (!button) {
                    return;
                }

                const missingInput = !this.hasRequiredXmlInput();
                const disabled = missingInput || this.xmlGenerationInProgress;

                button.disabled = disabled;
                button.classList.toggle('disabled', disabled);
                button.title = disabled ? this.translate(
                    missingInput ? 'xmlGenerateUnavailable' : 'xmlGenerating',
                    'messages',
                    'GeneratorPerioadeCursuriXmlConverter'
                ) : '';
            }

            updateDownloadXmlButtonState() {
                const button = this.element.querySelector('[data-action="downloadXml"]');

                if (!button) {
                    return;
                }

                const disabled = !this.model.get('xmlConvertedFileId');

                button.disabled = disabled;
                button.classList.toggle('disabled', disabled);
                button.title = disabled ? this.translate(
                    'xmlDownloadUnavailable',
                    'messages',
                    'GeneratorPerioadeCursuriXmlConverter'
                ) : '';
            }

            getXmlErrorMessage(xhr) {
                const fallback = this.translate(
                    'xmlGenerateFailed',
                    'messages',
                    'GeneratorPerioadeCursuriXmlConverter'
                );

                if (!xhr) {
                    return fallback;
                }

                if (xhr.responseJSON) {
                    if (xhr.responseJSON.error) {
                        return xhr.responseJSON.error;
                    }

                    if (xhr.responseJSON.message) {
                        return xhr.responseJSON.message;
                    }
                }

                if (xhr.responseText && xhr.responseText.charAt(0) === '{') {
                    try {
                        const data = JSON.parse(xhr.responseText);

                        if (data.error || data.message) {
                            return data.error || data.message;
                        }
                    } catch (e) {}
                }

                if (typeof xhr.getResponseHeader === 'function') {
                    const statusReason = xhr.getResponseHeader('X-Status-Reason');

                    if (statusReason) {
                        return statusReason;
                    }
                }

                return fallback;
            }
        };
    }
);
