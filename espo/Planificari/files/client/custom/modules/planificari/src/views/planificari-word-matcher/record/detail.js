define('planificari:views/planificari-word-matcher/record/detail', ['views/record/detail'], function (DetailRecordView) {
    return class extends DetailRecordView {
        setup() {
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

            this.updatePreviewButtonState();
            this.updateDownloadWordButtonState(false);

            if (this.wordConversionPreviewResult) {
                this.renderWordConversionPreview(this.wordConversionPreviewResult);
            }
        }

        async actionPreviewWordConversion() {
            Espo.Ui.notify('Preparing preview...');

            try {
                const result = await Espo.Ajax.postRequest('PlanificariWordMatcher/' + this.model.id + '/previewWordConversion', {});

                Espo.Ui.notify(false);

                this.wordConversionPreviewResult = result;
                this.renderWordConversionPreview(result);
                Espo.Ui.success(this.translate('wordPreviewReady', 'messages', 'PlanificariWordMatcher'));
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

            const selects = Array.from(container.querySelectorAll('[data-word-row-index]'));

            if (selects.some(select => select.value === '')) {
                Espo.Ui.warning(this.translate('wordReviewRequiresAllRows', 'messages', 'PlanificariWordMatcher'));

                return;
            }

            const matches = selects.map(select => ({
                wordRowIndex: Number(select.dataset.wordRowIndex),
                scheduleRowIndex: Number(select.value)
            }));

            Espo.Ui.notify('Converting...');

            try {
                const result = await Espo.Ajax.postRequest('PlanificariWordMatcher/' + this.model.id + '/convertWord', {matches: matches});

                Espo.Ui.notify(false);

                this.model.set('wordConvertedFileId', result.record ? result.record.wordConvertedFileId : null);
                this.model.set('wordConvertedAt', result.record ? result.record.wordConvertedAt : null);
                this.updatePreviewButtonState();
                this.updateDownloadWordButtonState(true);

                Espo.Ui.success(
                    (result.message || this.translate('Generate Reviewed Word', 'labels', 'PlanificariWordMatcher')) +
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
            if (this.model.get('wordConvertedFileId')) {
                window.open('?entryPoint=download&id=' + encodeURIComponent(this.model.get('wordConvertedFileId')), '_blank');

                return;
            }

            this.actionGenerateReviewedWord();
        }

        updatePreviewButtonState() {
            const button = this.element.querySelector('[data-action="previewWordConversion"]');

            if (!button) {
                return;
            }

            const hasConvertedFile = !!this.model.get('wordConvertedFileId');
            const disabled = !hasConvertedFile && (!this.model.get('wordTemplateFileId') || !this.model.get('wordScheduleFileId'));

            button.disabled = disabled;
            button.classList.toggle('disabled', disabled);
            button.title = disabled ? this.translate('wordConvertUnavailable', 'messages', 'PlanificariWordMatcher') : '';
        }

        updateDownloadWordButtonState(complete) {
            const button = this.element.querySelector('[data-action="downloadWord"]');

            if (!button) {
                return;
            }

            const enabled = !!this.model.get('wordConvertedFileId') || complete;

            button.disabled = !enabled;
            button.classList.toggle('disabled', !enabled);
            button.title = enabled ? '' : this.translate('wordReviewRequiresAllRows', 'messages', 'PlanificariWordMatcher');
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
                '<h4 class="panel-title">' + this.escapeHtml(this.composePreviewTitle(rows, scheduleOptions)) + '</h4>',
                '</div>',
                '<div class="panel-body">',
                '<p class="text-muted" data-role="word-preview-summary">' + this.escapeHtml(this.composeWordPreviewSummary(rows)) + '</p>',
                '<div class="table-responsive">',
                '<table class="table table-bordered table-striped table-hover" style="table-layout: auto;">',
                '<thead>',
                '<tr>',
                '<th>' + this.escapeHtml(this.translate('wordCourse', 'labels', 'PlanificariWordMatcher')) + '</th>',
                '<th>' + this.escapeHtml(this.translate('status', 'labels', 'PlanificariWordMatcher')) + '</th>',
                '<th>' + this.escapeHtml(this.translate('selectedScheduleRow', 'labels', 'PlanificariWordMatcher')) + '</th>',
                '<th>' + this.escapeHtml(this.translate('suggestions', 'labels', 'PlanificariWordMatcher')) + '</th>',
                '<th>' + this.escapeHtml(this.translate('filledPeriods', 'labels', 'PlanificariWordMatcher')) + '</th>',
                '</tr>',
                '</thead>',
                '<tbody>',
                rows.length ?
                    rows.map(row => this.composeWordPreviewRow(row, scheduleOptions)).join('') :
                    '<tr><td colspan="5" class="text-muted">' +
                    this.escapeHtml(this.translate('noWordRows', 'messages', 'PlanificariWordMatcher')) +
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
                        '[data-word-row-index="' + button.dataset.wordRowIndex + '"]'
                    );

                    if (!select) {
                        return;
                    }

                    select.value = button.dataset.candidateRowIndex;
                    this.updateWordPreviewSelectState(select, scheduleOptions);
                    this.updateWordPreviewCompletionState(container);
                });
            });

            Array.from(container.querySelectorAll('[data-word-row-index]')).forEach(select => {
                if (select.dataset.selectedRowIndex !== '') {
                    select.value = select.dataset.selectedRowIndex;
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
            let container = this.element.querySelector('[data-name="word-conversion-preview"]');

            if (container) {
                return container;
            }

            const recordContainer = this.element.querySelector('.record') || this.element;

            container = document.createElement('div');
            container.dataset.name = 'word-conversion-preview';

            recordContainer.appendChild(container);

            return container;
        }

        composeWordPreviewSummary(rows) {
            const selected = rows.filter(row => row.selectedRowIndex !== null).length;

            return this.translate('wordPreviewSummary', 'messages', 'PlanificariWordMatcher')
                .replace('{selected}', String(selected))
                .replace('{total}', String(rows.length));
        }

        composePreviewTitle(rows, scheduleOptions) {
            return this.translate('wordConversionPreviewWithCounts', 'labels', 'PlanificariWordMatcher')
                .replace('{wordCount}', String(rows.length))
                .replace('{excelCount}', String(scheduleOptions.length));
        }

        composeWordPreviewRow(row, scheduleOptions) {
            const candidateButtons = (row.candidates || []).map(candidate => [
                '<button type="button" class="btn btn-default btn-xs" style="display: block; width: 100%; height: auto; min-height: 24px; margin-bottom: 6px; padding: 4px 8px; white-space: normal; text-align: left; line-height: 1.35;"',
                ' data-word-row-index="' + this.escapeHtml(row.wordRowIndex) + '"',
                ' data-candidate-row-index="' + this.escapeHtml(candidate.rowIndex) + '">',
                this.escapeHtml(candidate.title) + ' (' + this.escapeHtml(candidate.score) + ')',
                '</button>'
            ].join('')).join('');

            return [
                '<tr style="height: auto;">',
                '<td style="min-width: 260px; white-space: normal; vertical-align: top;">' + this.escapeHtml(row.wordTitle) + '</td>',
                '<td data-role="word-preview-status" style="vertical-align: top;"></td>',
                '<td style="min-width: 320px; vertical-align: top;">',
                '<select class="form-control input-sm" data-word-row-index="' + this.escapeHtml(row.wordRowIndex) + '" data-selected-row-index="' + this.escapeHtml(row.selectedRowIndex ?? '') + '">',
                '<option value="">' + this.escapeHtml(this.translate('leaveUnchanged', 'labels', 'PlanificariWordMatcher')) + '</option>',
                scheduleOptions.map(option => {
                    const selected = String(option.rowIndex) === String(row.selectedRowIndex);
                    const exact = this.isExactCandidate(row, option.rowIndex);

                    return [
                    '<option value="' + this.escapeHtml(option.rowIndex) + '"' +
                    ' data-dates="' + this.escapeHtml(JSON.stringify(option.dates || [])) + '"' +
                    ' data-exact="' + (exact ? '1' : '0') + '"' +
                    (selected ? ' selected' : '') + '>',
                    this.escapeHtml(option.title),
                    '</option>'
                ].join('');
                }).join(''),
                '</select>',
                '</td>',
                '<td style="min-width: 260px; white-space: normal; vertical-align: top;">' + (candidateButtons || this.escapeHtml(this.translate('noSuggestions', 'messages', 'PlanificariWordMatcher'))) + '</td>',
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
                status.textContent = optionAssigned ?
                    this.translate('selected', 'labels', 'PlanificariWordMatcher') :
                    this.translate('unchanged', 'labels', 'PlanificariWordMatcher');
                status.className = optionAssigned ? 'text-success' : 'text-muted';
            }

            if (dates) {
                const selectedDates = this.getSelectedOptionDates(selectedOption);

                dates.innerHTML = optionAssigned && selectedDates.length ?
                    selectedDates.map(value => '<div class="text-nowrap">' + this.escapeHtml(value) + '</div>').join('') :
                    '<span class="text-muted">' + this.escapeHtml(this.translate('unchanged', 'labels', 'PlanificariWordMatcher')) + '</span>';
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
                Number(candidate.rowIndex) === Number(selectedValue) &&
                (candidate.exact || Number(candidate.score) === 100)
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
            const selects = Array.from(container.querySelectorAll('[data-word-row-index]'));
            const selected = selects.filter(select => select.value !== '').length;
            const complete = selects.length > 0 && selected === selects.length;
            const summary = container.querySelector('[data-role="word-preview-summary"]');

            if (summary) {
                summary.textContent = this.translate('wordPreviewSummary', 'messages', 'PlanificariWordMatcher')
                    .replace('{selected}', String(selected))
                    .replace('{total}', String(selects.length));
            }

            this.updateDownloadWordButtonState(complete);
        }

        getWordConvertErrorMessage(xhr) {
            const fallback = this.translate('wordConvertFailed', 'messages', 'PlanificariWordMatcher');

            if (!xhr) {
                return fallback;
            }

            if (xhr.responseJSON && xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }

            if (xhr.responseText && xhr.responseText.charAt(0) === '{') {
                try {
                    const data = JSON.parse(xhr.responseText);

                    if (data.message) {
                        return data.message;
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

        escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };
});
