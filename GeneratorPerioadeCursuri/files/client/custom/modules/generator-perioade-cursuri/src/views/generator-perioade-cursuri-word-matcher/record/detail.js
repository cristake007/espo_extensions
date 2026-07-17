define('generator-perioade-cursuri:views/generator-perioade-cursuri-word-matcher/record/detail', [
    'views/record/detail',
    'generator-perioade-cursuri:views/shared/record-ui'
], function (DetailRecordView, RecordUi) {
    return class extends DetailRecordView {
        setup() {
            this.isWide = true;
            this.sideDisabled = true;

            super.setup();

            this.wordConversionPreviewResult = null;

            this.addButton({
                name: 'previewWordConversion',
                label: 'Review Matches',
                style: 'primary',
                iconClass: 'fas fa-tasks'
            }, true);

            this.addButton({
                name: 'downloadWord',
                label: 'Download Word',
                style: 'default',
                iconClass: 'fas fa-file-word'
            }, true);
        }

        afterRender() {
            super.afterRender();

            this.element.classList.add('generator-perioade-cursuri-word-matcher-page');
            this.updatePreviewButtonState();
            this.updateDownloadWordButtonState(false);

            if (this.wordConversionPreviewResult) {
                this.renderWordConversionPreview(this.wordConversionPreviewResult);
            }
        }

        async actionPreviewWordConversion() {
            Espo.Ui.notify('Preparing preview...');

            try {
                const result = await Espo.Ajax.postRequest('GeneratorPerioadeCursuriWordMatcher/' + this.model.id + '/previewWordConversion', {});

                Espo.Ui.notify(false);

                this.wordConversionPreviewResult = result;
                this.renderWordConversionPreview(result);
                Espo.Ui.success(this.translate('wordPreviewReady', 'messages', 'GeneratorPerioadeCursuriWordMatcher'));
            } catch (e) {
                Espo.Ui.notify(false);
                Espo.Ui.error(this.getWordConvertErrorMessage(e));
            }
        }

        async actionGenerateReviewedWord() {
            const container = this.element.querySelector('[data-name="word-conversion-preview"]');

            if (!container) {
                return;
            }

            const selects = Array.from(container.querySelectorAll('select[data-word-row-index]'));
            const matches = selects
                .filter(select => select.value !== '')
                .map(select => ({
                    wordRowIndex: Number(select.dataset.wordRowIndex),
                    scheduleRowIndex: Number(select.value)
                }));

            if (selects.length === 0 || matches.length !== selects.length) {
                Espo.Ui.warning(this.translate('wordReviewRequiresAllRows', 'messages', 'GeneratorPerioadeCursuriWordMatcher'));

                return;
            }

            Espo.Ui.notify('Converting...');

            try {
                const result = await Espo.Ajax.postRequest('GeneratorPerioadeCursuriWordMatcher/' + this.model.id + '/convertWord', {matches: matches});

                Espo.Ui.notify(false);

                this.model.set('wordConvertedFileId', result.record ? result.record.wordConvertedFileId : null);
                this.model.set('wordConvertedAt', result.record ? result.record.wordConvertedAt : null);
                this.updatePreviewButtonState();
                this.updateDownloadWordButtonState(true);

                Espo.Ui.success(
                    (result.message || this.translate('Generate Reviewed Word', 'labels', 'GeneratorPerioadeCursuriWordMatcher')) +
                    ' Matched: ' + String(result.matchedCount || 0) +
                    '. Skipped: ' + String(result.skippedCount || 0) + '.'
                );

                if (result.downloadUrl) {
                    window.open(result.downloadUrl, '_blank');
                }
            } catch (e) {
                Espo.Ui.notify(false);
                Espo.Ui.error(this.getWordConvertErrorMessage(e));
            }
        }

        actionDownloadWord() {
            const container = this.element.querySelector('[data-name="word-conversion-preview"]');

            if (!container) {
                return;
            }

            this.actionGenerateReviewedWord();
        }

        updatePreviewButtonState() {
            // File attributes are not guaranteed to be hydrated when Espo restores a
            // cached detail view. The preview endpoint reads and validates the saved
            // record, so a persisted matcher record is the authoritative prerequisite.
            const disabled = !this.model.id;

            RecordUi.setActionButtonState(
                this.element,
                'previewWordConversion',
                disabled,
                disabled ? this.translate('wordConvertUnavailable', 'messages', 'GeneratorPerioadeCursuriWordMatcher') : ''
            );
        }

        updateDownloadWordButtonState(canGenerate) {
            const disabled = !canGenerate;

            RecordUi.setActionButtonState(
                this.element,
                'downloadWord',
                disabled,
                disabled ? this.translate('wordReviewRequiresAllRows', 'messages', 'GeneratorPerioadeCursuriWordMatcher') : ''
            );
        }

        renderWordConversionPreview(result) {
            const container = this.getWordConversionPreviewContainer();

            if (!container) {
                return;
            }

            const rows = result.rows || [];
            const scheduleOptions = result.scheduleOptions || [];

            container.innerHTML = [
                '<div class="panel panel-default">',
                '<div class="panel-heading">',
                '<h4 class="panel-title">' + RecordUi.escapeHtml(this.composePreviewTitle(rows, scheduleOptions)) + '</h4>',
                '</div>',
                '<div class="panel-body">',
                '<p class="text-muted" data-role="word-preview-summary">' + RecordUi.escapeHtml(this.composeWordPreviewSummary(rows)) + '</p>',
                '<div class="table-responsive">',
                '<table class="table table-bordered table-striped table-hover" style="table-layout: auto;">',
                '<thead>',
                '<tr>',
                '<th>' + RecordUi.escapeHtml(this.translate('wordCourse', 'labels', 'GeneratorPerioadeCursuriWordMatcher')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('status', 'labels', 'GeneratorPerioadeCursuriWordMatcher')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('selectedScheduleRow', 'labels', 'GeneratorPerioadeCursuriWordMatcher')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('suggestions', 'labels', 'GeneratorPerioadeCursuriWordMatcher')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('filledPeriods', 'labels', 'GeneratorPerioadeCursuriWordMatcher')) + '</th>',
                '</tr>',
                '</thead>',
                '<tbody>',
                rows.length ?
                    rows.map(row => this.composeWordPreviewRow(row, scheduleOptions)).join('') :
                    '<tr><td colspan="5" class="text-muted">' +
                    RecordUi.escapeHtml(this.translate('noWordRows', 'messages', 'GeneratorPerioadeCursuriWordMatcher')) +
                    '</td></tr>',
                '</tbody>',
                '</table>',
                '</div>',
                '</div>',
                '</div>'
            ].join('');

            Array.from(container.querySelectorAll('[data-candidate-row-index]')).forEach(button => {
                button.addEventListener('click', () => {
                    const select = container.querySelector(
                        'select[data-word-row-index="' + button.dataset.wordRowIndex + '"]'
                    );

                    if (!select) {
                        return;
                    }

                    select.value = button.dataset.candidateRowIndex;
                    this.updateWordPreviewSelectState(select, scheduleOptions);
                    this.updateWordPreviewCompletionState(container);
                });
            });

            Array.from(container.querySelectorAll('[data-generated-row-index]')).forEach(button => {
                button.addEventListener('click', () => {
                    const select = container.querySelector(
                        'select[data-word-row-index="' + button.dataset.wordRowIndex + '"]'
                    );

                    if (!select) {
                        return;
                    }

                    select.value = button.dataset.generatedRowIndex;
                    this.updateWordPreviewSelectState(select, scheduleOptions);
                    this.updateWordPreviewCompletionState(container);
                });
            });

            Array.from(container.querySelectorAll('select[data-word-row-index]')).forEach(select => {
                const selectedRowIndex = select.dataset.selectedRowIndex;

                if (selectedRowIndex !== '') {
                    const exactOption = Array.from(select.options).find(option =>
                        option.value === selectedRowIndex && option.dataset.exact === '1'
                    );

                    select.value = exactOption ? selectedRowIndex : '';
                }

                select.addEventListener('change', () => {
                    this.updateWordPreviewSelectState(select, scheduleOptions);
                    this.updateWordPreviewCompletionState(container);
                });
                this.updateWordPreviewSelectState(select, scheduleOptions);
            });

            this.updateWordPreviewCompletionState(container);
            container.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        getWordConversionPreviewContainer() {
            return RecordUi.ensureRecordRegion(this.element, 'word-conversion-preview');
        }

        composeWordPreviewSummary(rows) {
            const selected = rows.filter(row => row.selectedRowIndex !== null).length;

            return this.translate('wordPreviewSummary', 'messages', 'GeneratorPerioadeCursuriWordMatcher')
                .replace('{selected}', String(selected))
                .replace('{total}', String(rows.length));
        }

        composePreviewTitle(rows, scheduleOptions) {
            return this.translate('wordConversionPreviewWithCounts', 'labels', 'GeneratorPerioadeCursuriWordMatcher')
                .replace('{wordCount}', String(rows.length))
                .replace('{excelCount}', String(scheduleOptions.length));
        }

        composeWordPreviewRow(row, scheduleOptions) {
            const generatedOption = row.generatedOption || null;
            const rowScheduleOptions = generatedOption ? scheduleOptions.concat([generatedOption]) : scheduleOptions;
            const candidateButtons = (row.candidates || []).map(candidate => [
                '<button type="button" class="btn btn-default btn-xs" style="display: block; width: 100%; height: auto; min-height: 24px; margin-bottom: 6px; padding: 4px 8px; white-space: normal; text-align: left; line-height: 1.35;"',
                ' data-word-row-index="' + RecordUi.escapeHtml(row.wordRowIndex) + '"',
                ' data-candidate-row-index="' + RecordUi.escapeHtml(candidate.rowIndex) + '">',
                RecordUi.escapeHtml(candidate.title) + ' (' + RecordUi.escapeHtml(candidate.score) + ')',
                '</button>'
            ].join('')).join('');
            const generatedButton = generatedOption ? [
                '<button type="button" class="btn word-preview-generated-button ' + (generatedOption.generationMode === 'primary' ? 'btn-warning' : 'btn-info') + ' btn-xs" style="display: block; width: 100%; height: auto; min-height: 28px; margin-bottom: 6px; padding: 5px 8px; white-space: normal; text-align: left; line-height: 1.35; font-weight: 600;"',
                ' data-word-row-index="' + RecordUi.escapeHtml(row.wordRowIndex) + '"',
                ' data-generated-row-index="' + RecordUi.escapeHtml(generatedOption.rowIndex) + '">',
                RecordUi.escapeHtml(generatedOption.title),
                '</button>'
            ].join('') : '';
            const suggestionContent = [generatedButton, candidateButtons]
                .filter(value => value !== '')
                .join('') || RecordUi.escapeHtml(this.translate('noSuggestions', 'messages', 'GeneratorPerioadeCursuriWordMatcher'));

            return [
                '<tr style="height: auto;">',
                '<td style="min-width: 260px; white-space: normal; vertical-align: top;">' + RecordUi.escapeHtml(row.wordTitle) + '</td>',
                '<td data-role="word-preview-status" style="vertical-align: top;"></td>',
                '<td style="min-width: 320px; vertical-align: top;">',
                '<select class="form-control input-sm" data-word-row-index="' + RecordUi.escapeHtml(row.wordRowIndex) + '" data-selected-row-index="' + RecordUi.escapeHtml(row.selectedRowIndex ?? '') + '">',
                '<option value="">' + RecordUi.escapeHtml(this.translate('leaveUnchanged', 'labels', 'GeneratorPerioadeCursuriWordMatcher')) + '</option>',
                rowScheduleOptions.map(option => {
                    const generated = option.generated === true;
                    const exact = !generated && this.isExactCandidate(row, option.rowIndex);
                    const selected = exact && String(option.rowIndex) === String(row.selectedRowIndex);

                    return [
                    '<option value="' + RecordUi.escapeHtml(option.rowIndex) + '"' +
                    ' data-dates="' + RecordUi.escapeHtml(JSON.stringify(option.dates || [])) + '"' +
                    ' data-exact="' + (exact ? '1' : '0') + '"' +
                    ' data-generated="' + (generated ? '1' : '0') + '"' +
                    (selected ? ' selected' : '') + '>',
                    RecordUi.escapeHtml(option.title),
                    '</option>'
                ].join('');
                }).join(''),
                '</select>',
                '</td>',
                '<td style="min-width: 260px; white-space: normal; vertical-align: top;">' + suggestionContent + '</td>',
                '<td data-role="word-preview-dates" style="min-width: 160px; vertical-align: top;"></td>',
                '</tr>'
            ].join('');
        }

        updateWordPreviewSelectState(select, scheduleOptions) {
            const row = select.closest('tr');
            const status = row ? row.querySelector('[data-role="word-preview-status"]') : null;
            const dates = row ? row.querySelector('[data-role="word-preview-dates"]') : null;
            const selectedOption = select.options[select.selectedIndex] || null;
            const optionAssigned = !!selectedOption && select.value !== '';
            const previewRow = this.findPreviewRow(Number(select.dataset.wordRowIndex));

            if (previewRow) {
                previewRow.selectedRowIndex = select.value === '' ? null : Number(select.value);
            }

            if (status) {
                const statusText = optionAssigned ?
                    this.translate('selected', 'labels', 'GeneratorPerioadeCursuriWordMatcher') :
                    this.translate('unchanged', 'labels', 'GeneratorPerioadeCursuriWordMatcher');
                const statusClass = optionAssigned ? 'label-success' : 'label-danger';

                status.innerHTML = '<span class="label label-state word-preview-status-label ' + statusClass + '">' +
                    RecordUi.escapeHtml(statusText) +
                    '</span>';
            }

            if (dates) {
                const selectedDates = this.getSelectedOptionDates(selectedOption);

                dates.innerHTML = optionAssigned && selectedDates.length ?
                    selectedDates.map(value => '<div class="text-nowrap">' + RecordUi.escapeHtml(value) + '</div>').join('') :
                    '<span class="text-muted">' + RecordUi.escapeHtml(this.translate('unchanged', 'labels', 'GeneratorPerioadeCursuriWordMatcher')) + '</span>';
            }
        }

        findPreviewRow(wordRowIndex) {
            const rows = this.wordConversionPreviewResult ? this.wordConversionPreviewResult.rows || [] : [];

            return rows.find(row => Number(row.wordRowIndex) === wordRowIndex) || null;
        }

        isExactCandidate(row, selectedValue) {
            if (!row || selectedValue === null || selectedValue === '') {
                return false;
            }

            return (row.candidates || []).some(candidate =>
                Number(candidate.rowIndex) === Number(selectedValue) && candidate.exact === true
            );
        }

        getSelectedOptionDates(option) {
            if (!option || !option.dataset.dates) {
                return [];
            }

            try {
                const dates = JSON.parse(option.dataset.dates);

                return Array.isArray(dates) ? dates.filter(Boolean) : [];
            } catch (e) {
                return [];
            }
        }

        updateWordPreviewCompletionState(container) {
            const selects = Array.from(container.querySelectorAll('select[data-word-row-index]'));
            const selected = selects.filter(select => select.value !== '').length;
            const complete = selects.length > 0 && selected === selects.length;
            const summary = container.querySelector('[data-role="word-preview-summary"]');

            if (summary) {
                summary.textContent = this.translate('wordPreviewSummary', 'messages', 'GeneratorPerioadeCursuriWordMatcher')
                    .replace('{selected}', String(selected))
                    .replace('{total}', String(selects.length));
            }

            this.updateDownloadWordButtonState(complete);
        }

        getWordConvertErrorMessage(xhr) {
            const fallback = this.translate('wordConvertFailed', 'messages', 'GeneratorPerioadeCursuriWordMatcher');

            if (!xhr) {
                return fallback;
            }

            if (typeof xhr === 'string') {
                return xhr || fallback;
            }

            if (xhr.message && typeof xhr.message === 'string') {
                return xhr.message;
            }

            if (xhr.error && typeof xhr.error.message === 'string') {
                return xhr.error.message;
            }

            if (xhr.data && typeof xhr.data.message === 'string') {
                return xhr.data.message;
            }

            if (xhr.responseJSON) {
                if (xhr.responseJSON.message) {
                    return xhr.responseJSON.message;
                }

                if (xhr.responseJSON.error && typeof xhr.responseJSON.error === 'string') {
                    return xhr.responseJSON.error;
                }
            }

            if (xhr.responseText) {
                const responseText = String(xhr.responseText).trim();

                if (responseText.charAt(0) === '{') {
                    try {
                        const data = JSON.parse(responseText);

                        if (data.message) {
                            return data.message;
                        }

                        if (data.error && typeof data.error === 'string') {
                            return data.error;
                        }
                    } catch (e) {}
                }
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
});
