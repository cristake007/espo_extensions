define([
    'view',
    'content-factory:generator-config',
    'content-factory:views/previews/content',
], (View, ConfigFactory, renderPreview) => {
    return class extends View {
        templateContent = `
            <div class="header page-header content-factory-page-header">
                <h3><span class="fas fa-magic" aria-hidden="true"></span> <span data-title></span></h3>
                <p class="text-muted" data-description></p>
            </div>
            <div class="content-factory-workspace">
                <section class="panel panel-default content-factory-generator-panel">
                    <div class="panel-heading">
                        <strong data-brief-title></strong>
                        <div class="small text-muted" data-brief-description></div>
                    </div>
                    <div class="panel-body">
                        <form data-generator-form>
                            <fieldset>
                                <legend data-target-title></legend>
                                <div class="row">
                                    <div class="form-group col-sm-6">
                                        <label for="content-factory-platform" data-platform-label></label>
                                        <select id="content-factory-platform" class="form-control" data-platform></select>
                                    </div>
                                    <div class="form-group col-sm-6">
                                        <label for="content-factory-format" data-format-label></label>
                                        <select id="content-factory-format" class="form-control" data-format></select>
                                        <small class="text-muted content-factory-carousel-help hidden" data-carousel-help></small>
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset class="content-factory-style-fieldset">
                                <legend data-style-title></legend>
                                <p class="small text-muted" data-style-description></p>
                                <div class="content-factory-style-options" data-style-options></div>
                            </fieldset>
                            <div class="form-group content-factory-custom-instructions hidden" data-custom-wrap>
                                <label for="content-factory-custom-instructions" data-custom-label></label>
                                <textarea id="content-factory-custom-instructions" class="form-control" rows="3" data-custom></textarea>
                            </div>
                            <div class="form-group">
                                <label for="content-factory-subject-type" data-subject-type-label></label>
                                <select id="content-factory-subject-type" class="form-control" data-subject-type></select>
                            </div>
                            <div class="form-group content-factory-subject-wrap hidden" data-subject-wrap>
                                <label for="content-factory-subject-search" data-subject-label></label>
                                <div class="content-factory-subject-picker">
                                    <div class="input-group">
                                        <span class="input-group-addon" aria-hidden="true"><span class="fas fa-search"></span></span>
                                        <input id="content-factory-subject-search" type="search" class="form-control"
                                            autocomplete="off" data-subject-search>
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default hidden" data-subject-clear
                                                aria-label="Clear">&times;</button>
                                        </span>
                                    </div>
                                    <div class="content-factory-subject-results hidden" data-subject-results></div>
                                </div>
                                <small class="text-muted content-factory-subject-status" data-subject-status></small>
                            </div>
                            <div class="form-group">
                                <label for="content-factory-brief" data-brief-label></label>
                                <textarea id="content-factory-brief" class="form-control" rows="8" required data-brief></textarea>
                            </div>
                            <div class="content-factory-generator-actions">
                                <button type="submit" class="btn btn-primary" data-generate disabled></button>
                                <small class="text-muted" data-mock-notice></small>
                            </div>
                        </form>
                    </div>
                </section>
                <section class="panel panel-default content-factory-preview-panel">
                    <div class="panel-heading">
                        <strong data-preview-title></strong>
                        <div class="small text-muted" data-preview-description></div>
                    </div>
                    <div class="panel-body" data-preview-region aria-live="polite"></div>
                </section>
            </div>
        `

        events = {
            'change [data-platform]': 'changePlatform',
            'change [data-format]': 'changeFormat',
            'change [name="content-factory-style"]': 'changeStyle',
            'change [data-subject-type]': 'changeSubjectType',
            'input [data-subject-search]': 'changeSubjectSearch',
            'click [data-subject-option]': 'selectSubject',
            'click [data-subject-clear]': 'clearSubject',
            'input [data-custom]': 'invalidatePreview',
            'input [data-brief]': 'changeBrief',
            'submit [data-generator-form]': 'generatePreview',
        }

        setup() {
            this.generatorConfig = ConfigFactory.create(
                this.getMetadata().get(['app', 'contentFactory', 'generator'])
            );
            this.target = this.generatorConfig.defaultTarget();
            this.style = 'automatic';
            this.previewVisible = false;
            this.generatedContent = null;
            this.isGenerating = false;
            this.readOnly = Boolean(this.options.readOnly && this.options.historyId);
            this.historyId = this.options.historyId || null;
            this.contentType = 'general';
            this.course = null;
            this.courseResults = [];
            this.courseSearchSequence = 0;
            this.courseSearchTimer = null;
            this.courseSearchRequest = null;
        }

        afterRender() {
            this.renderStaticTranslations();
            this.renderPlatformOptions();
            this.renderFormatOptions();
            this.renderStyleOptions();
            this.renderSubjectTypeOptions();
            this.renderSubjectState();
            this.renderPreviewRegion();

            if (this.readOnly) {
                this.loadHistoryRecord();
            }
        }

        renderStaticTranslations() {
            const labels = {
                '[data-title]': 'Content Factory Generator',
                '[data-brief-title]': 'Content Brief',
                '[data-target-title]': 'Target Variant',
                '[data-platform-label]': 'Platform',
                '[data-format-label]': 'Format',
                '[data-style-title]': 'Style',
                '[data-custom-label]': 'Additional Instructions',
                '[data-subject-type-label]': 'Content Type',
                '[data-subject-label]': 'Course Subject',
                '[data-brief-label]': 'Brief',
                '[data-generate]': this.readOnly ? 'Back to History' : 'Generate Content',
                '[data-preview-title]': 'Preview',
            };
            const messages = {
                '[data-description]': 'Content Factory Generator Description',
                '[data-brief-description]': 'Content Brief Description',
                '[data-style-description]': 'Automatic Style Description',
                '[data-carousel-help]': 'Carousel Help',
                '[data-mock-notice]': 'Generator Mock Notice',
                '[data-preview-description]': 'Preview Description',
            };

            Object.entries(labels).forEach(([selector, key]) => {
                this.$el.find(selector).text(this.translate(key, 'labels'));
            });
            Object.entries(messages).forEach(([selector, key]) => {
                this.$el.find(selector).text(this.translate(key, 'messages'));
            });
            this.$el.find('[data-custom]').attr(
                'placeholder', this.translate('Custom Instructions Placeholder', 'messages')
            );
            this.$el.find('[data-brief]').attr(
                'placeholder', this.translate('Brief Placeholder', 'messages')
            );
            this.$el.find('[data-subject-search]').attr(
                'placeholder', this.translate('Subject Search Placeholder', 'messages')
            );

            if (this.readOnly) {
                this.$el.find('[data-title]').text(this.translate('Generated Content History', 'labels'));
                this.$el.find('[data-description]').text(
                    this.translate('Generated Content History Description', 'messages')
                );
                this.$el.find('[data-mock-notice]').text(
                    this.translate('History Read Only Notice', 'messages')
                );
            }
        }

        renderPlatformOptions() {
            const select = this.$el.find('[data-platform]').empty();

            this.generatorConfig.platforms.forEach(platform => select.append(
                $('<option>', {value: platform.id}).text(this.translate(platform.label, 'labels'))
            ));
            select.val(this.target.platform);
        }

        renderFormatOptions() {
            const platform = this.generatorConfig.findPlatform(this.target.platform);
            const select = this.$el.find('[data-format]').empty();

            platform.formats.forEach(format => select.append(
                $('<option>', {value: format}).text(
                    this.translate(this.generatorConfig.formatLabels[format], 'labels')
                )
            ));
            select.val(this.target.format);
            this.$el.find('[data-carousel-help]').toggleClass(
                'hidden', this.target.format !== 'carousel'
            );
        }

        renderStyleOptions() {
            const container = this.$el.find('[data-style-options]').empty();

            this.generatorConfig.styles.forEach(style => container.append(
                $('<label>').addClass('content-factory-style-option').append(
                    $('<input>', {
                        type: 'radio',
                        name: 'content-factory-style',
                        value: style.id,
                    }).prop('checked', style.id === this.style),
                    $('<span>').text(this.translate(style.label, 'labels'))
                )
            ));
        }

        renderSubjectTypeOptions() {
            const select = this.$el.find('[data-subject-type]').empty();
            const types = [
                ['general', 'General'],
                ['course', 'Course'],
                ['webinar', 'Webinar'],
                ['top10', 'Top 10'],
                ['service', 'Service'],
                ['postEvent', 'Event / Post-event'],
            ];

            types.forEach(([value, label]) => select.append(
                $('<option>', {value}).text(this.translate(label, 'labels'))
            ));
            select.val(this.contentType);
        }

        changeSubjectType(event) {
            const type = String($(event.currentTarget).val());

            this.courseSearchSequence += 1;
            clearTimeout(this.courseSearchTimer);

            if (this.courseSearchRequest && typeof this.courseSearchRequest.abort === 'function') {
                this.courseSearchRequest.abort();
            }

            this.courseSearchRequest = null;
            this.courseResults = [];
            this.course = null;
            this.contentType = type;
            this.renderSubjectState();
            this.updateGenerateButton();
            this.invalidatePreview();
        }

        renderSubjectState() {
            const isCourse = this.contentType === 'course';
            const input = this.$el.find('[data-subject-search]');

            this.$el.find('[data-subject-wrap]').toggleClass('hidden', !isCourse);
            input.val(isCourse && this.course ? this.course.name : '');
            this.$el.find('[data-subject-clear]').toggleClass('hidden', !this.course);
            this.$el.find('[data-subject-results]').addClass('hidden').empty();
            this.$el.find('[data-subject-status]').text('');
        }

        changeSubjectSearch(event) {
            if (this.contentType !== 'course') {
                return;
            }

            const query = String($(event.currentTarget).val()).trim();

            clearTimeout(this.courseSearchTimer);
            this.courseSearchSequence += 1;

            if (this.courseSearchRequest && typeof this.courseSearchRequest.abort === 'function') {
                this.courseSearchRequest.abort();
            }

            this.courseSearchRequest = null;

            if (this.course && query !== this.course.name) {
                this.course = null;
                this.$el.find('[data-subject-clear]').addClass('hidden');
                this.updateGenerateButton();
            }

            if (query.length < 2) {
                this.courseResults = [];
                this.renderSubjectResults();
                this.$el.find('[data-subject-status]').text(
                    this.translate('Subject Search Hint', 'messages')
                );
                return;
            }

            this.$el.find('[data-subject-status]').text(
                this.translate('Loading Subjects', 'messages')
            );
            this.courseSearchTimer = setTimeout(() => this.searchSubjects(query), 300);
        }

        async searchSubjects(query) {
            if (this.contentType !== 'course') {
                return;
            }

            const sequence = ++this.courseSearchSequence;
            const endpoint = 'ContentFactory/subjects?' + $.param({type: 'course', q: query, limit: 20});
            const request = Espo.Ajax.getRequest(endpoint);

            this.courseSearchRequest = request;

            try {
                const response = await request;

                if (sequence !== this.courseSearchSequence || this.contentType !== 'course') {
                    return;
                }

                this.courseResults = response && Array.isArray(response.list) ? response.list : [];
                this.renderSubjectResults();

                if (!this.courseResults.length) {
                    this.$el.find('[data-subject-status]').text(
                        this.translate('No Subjects Found', 'messages')
                    );
                } else {
                    this.$el.find('[data-subject-status]').text('');
                }
            } catch (error) {
                if (sequence !== this.courseSearchSequence || this.contentType !== 'course') {
                    return;
                }

                this.courseResults = [];
                this.renderSubjectResults();
                this.$el.find('[data-subject-status]').text(
                    this.getSubjectErrorMessage(error)
                ).addClass('text-danger');
            } finally {
                if (this.courseSearchRequest === request) {
                    this.courseSearchRequest = null;
                }
            }
        }

        renderSubjectResults() {
            const container = this.$el.find('[data-subject-results]').empty();

            this.$el.find('[data-subject-status]').removeClass('text-danger');

            if (!this.courseResults.length) {
                container.addClass('hidden');
                return;
            }

            this.courseResults.forEach((course, index) => container.append(
                $('<button>', {
                    type: 'button',
                    class: 'content-factory-subject-option',
                    'data-subject-option': index,
                }).text(String(course.label || ''))
            ));
            container.removeClass('hidden');
        }

        selectSubject(event) {
            const index = Number($(event.currentTarget).attr('data-subject-option'));
            const selected = this.courseResults[index];

            if (!selected || this.contentType !== 'course') {
                return;
            }

            this.course = {
                id: String(selected.id),
                name: String(selected.label || selected.name || ''),
                slug: selected.slug ? String(selected.slug) : null,
                url: selected.url ? String(selected.url) : null,
            };
            this.courseResults = [];
            this.renderSubjectState();
            this.updateGenerateButton();
            this.invalidatePreview();
        }

        clearSubject() {
            this.course = null;
            this.courseResults = [];
            this.renderSubjectState();
            this.updateGenerateButton();
            this.$el.find('[data-subject-search]').trigger('focus');
            this.invalidatePreview();
        }

        changePlatform(event) {
            this.target = this.generatorConfig.selectPlatform(
                this.target,
                String($(event.currentTarget).val())
            );
            this.renderFormatOptions();
            this.invalidatePreview();
        }

        changeFormat(event) {
            this.target = this.generatorConfig.selectFormat(
                this.target,
                String($(event.currentTarget).val())
            );
            this.renderFormatOptions();
            this.invalidatePreview();
        }

        changeStyle(event) {
            this.style = String($(event.currentTarget).val());
            this.$el.find('[data-custom-wrap]').toggleClass('hidden', this.style !== 'custom');
            this.invalidatePreview();
        }

        changeBrief() {
            this.updateGenerateButton();
            this.invalidatePreview();
        }

        updateGenerateButton() {
            const hasBrief = Boolean(String(this.$el.find('[data-brief]').val()).trim());
            const hasRequiredCourse = this.contentType !== 'course' || Boolean(this.course && this.course.id);

            this.$el.find('[data-generate]').prop(
                'disabled', !hasBrief || !hasRequiredCourse || this.isGenerating
            );
        }

        invalidatePreview() {
            if (this.readOnly) {
                return;
            }

            this.previewVisible = false;
            this.generatedContent = null;
            this.renderPreviewRegion();
        }

        async generatePreview(event) {
            event.preventDefault();

            if (this.readOnly) {
                this.getRouter().navigate('#ContentFactoryContent', {trigger: true});

                return;
            }

            if (this.isGenerating) {
                return;
            }

            const brief = String(this.$el.find('[data-brief]').val()).trim();
            const hasRequiredCourse = this.contentType !== 'course' || Boolean(this.course && this.course.id);

            if (!brief || !hasRequiredCourse) {
                return;
            }

            const button = this.$el.find('[data-generate]');

            this.isGenerating = true;
            button.prop('disabled', true);
            Espo.Ui.notify(this.translate('Saving Generated Content', 'messages'));

            try {
                const response = await Espo.Ajax.postRequest(
                    'ContentFactory/generate',
                    this.buildGenerationPayload(brief)
                );

                if (
                    !response ||
                    response.success !== true ||
                    !response.record ||
                    !response.record.id ||
                    !response.generation
                ) {
                    throw new Error('Invalid Content Factory response.');
                }

                this.generatedContent = response.generation;
                this.previewVisible = true;
                this.renderPreviewRegion();
                Espo.Ui.notify(false);
                Espo.Ui.success(this.translate('Generated Content Saved', 'messages'));
            } catch (error) {
                Espo.Ui.notify(false);
                Espo.Ui.error(this.getGenerationErrorMessage(error));
            } finally {
                this.isGenerating = false;
                this.updateGenerateButton();
            }
        }

        buildGenerationPayload(brief) {
            return {
                brief,
                platform: this.target.platform,
                format: this.target.format,
                style: this.style,
                customInstructions: String(this.$el.find('[data-custom]').val()).trim(),
                locale: this.getLocale(),
                contentType: this.contentType,
                course: this.course ? {...this.course} : null,
            };
        }

        getLocale() {
            const language = String(this.getPreferences().get('language') || 'ro_RO');
            const locale = language.slice(0, 2).toLowerCase();

            return ['ro', 'en'].includes(locale) ? locale : 'ro';
        }

        getGenerationErrorMessage(error) {
            const response = error && (
                error.responseJSON || (error.xhr && error.xhr.responseJSON)
            );

            if (response && response.error && response.error.message) {
                return String(response.error.message);
            }

            return this.translate('Generated Content Save Failed', 'messages');
        }

        getSubjectErrorMessage(error) {
            const response = error && (
                error.responseJSON || (error.xhr && error.xhr.responseJSON)
            );

            if (response && response.error && response.error.message) {
                return String(response.error.message);
            }

            return this.translate('Subject Load Failed', 'messages');
        }

        async loadHistoryRecord() {
            const region = this.$el.find('[data-preview-region]').empty();

            region.append(
                $('<p>').addClass('content-factory-empty text-muted').text(
                    this.translate('Loading Generated Content', 'messages')
                )
            );

            try {
                const record = await Espo.Ajax.getRequest(
                    `ContentFactoryContent/${encodeURIComponent(this.historyId)}`
                );

                this.applyHistoryRecord(record);
            } catch (error) {
                region.empty().append(
                    $('<p>').addClass('content-factory-empty text-danger').text(
                        this.translate('Generated Content Load Failed', 'messages')
                    )
                );
            }
        }

        applyHistoryRecord(record) {
            const platform = String(record.platform || '').toLowerCase();
            const format = Object.entries(this.generatorConfig.formats)
                .find(([, value]) => value.entityValue === record.format)?.[0];
            const style = this.generatorConfig.styles
                .find(item => item.entityValue === record.style)?.id;

            if (this.generatorConfig.findPlatform(platform)) {
                this.target = this.generatorConfig.selectPlatform(this.target, platform);
            }

            if (format) {
                this.target = this.generatorConfig.selectFormat(this.target, format);
            }

            if (style) {
                this.style = style;
            }

            this.contentType = String(record.contentType || 'general');
            this.course = this.contentType === 'course' && record.courseId ? {
                id: String(record.courseId),
                name: String(record.courseName || ''),
                slug: record.courseSlug ? String(record.courseSlug) : null,
                url: record.courseUrl ? String(record.courseUrl) : null,
            } : null;

            this.generatedContent = this.parseGeneratedContent(record.generatedContent);
            this.previewVisible = true;
            this.renderPlatformOptions();
            this.renderFormatOptions();
            this.renderStyleOptions();
            this.renderSubjectTypeOptions();
            this.renderSubjectState();
            this.$el.find('[data-brief]').val(record.brief || '');
            this.$el.find('[data-custom]').val(record.customInstructions || '');
            this.$el.find('[data-custom-wrap]').toggleClass('hidden', this.style !== 'custom');
            this.$el.find('select, textarea, input').prop('disabled', true);
            this.$el.find('[data-generate]').prop('disabled', false);
            this.renderPreviewRegion();
        }

        parseGeneratedContent(value) {
            try {
                const stored = JSON.parse(String(value || ''));

                return stored && stored.content ? stored : null;
            } catch (error) {
                return null;
            }
        }

        renderPreviewRegion() {
            const region = this.$el.find('[data-preview-region]').empty();

            if (!this.previewVisible) {
                region.append(
                    $('<p>').addClass('content-factory-empty text-muted').text(
                        this.translate('Empty Preview', 'messages')
                    )
                );

                return;
            }

            region.append(renderPreview(
                this,
                this.target,
                String(this.$el.find('[data-brief]').val()),
                this.generatedContent
            ));
        }
    };
});
