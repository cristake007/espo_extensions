define('generator-perioade-cursuri:views/generator-perioade-cursuri-word-comparator/record/detail', [
    'views/record/detail',
    'generator-perioade-cursuri:views/shared/record-ui'
], function (DetailRecordView, RecordUi) {
    return class extends DetailRecordView {
        setup() {
            this.isWide = true;
            this.sideDisabled = true;

            super.setup();

            this.wordComparisonResult = null;

            this.addButton({
                name: 'compareWord',
                label: 'Compare',
                style: 'primary',
                iconClass: 'fas fa-balance-scale'
            }, true);
        }

        afterRender() {
            super.afterRender();

            this.element.classList.add('generator-perioade-cursuri-word-comparator-page');
            this.updateCompareButtonState();

            if (this.wordComparisonResult) {
                this.renderWordComparisonResult(this.wordComparisonResult);
            }
        }

        async actionCompareWord() {
            Espo.Ui.notify('Comparing...');

            try {
                const result = await Espo.Ajax.postRequest('GeneratorPerioadeCursuriWordComparator/' + this.model.id + '/compareWord', {});

                Espo.Ui.notify(false);

                this.wordComparisonResult = result;
                this.renderWordComparisonResult(result);
                Espo.Ui.success(this.translate('wordCompareReady', 'messages', 'GeneratorPerioadeCursuriWordComparator'));
            } catch (e) {
                Espo.Ui.notify(false);
                Espo.Ui.error(this.getWordCompareErrorMessage(e));
            }
        }

        updateCompareButtonState() {
            // File attributes are not guaranteed to be hydrated when Espo restores a
            // cached detail view. The compare endpoint reads and validates the saved
            // record, so a persisted comparator record is the authoritative prerequisite.
            const disabled = !this.model.id;

            RecordUi.setActionButtonState(
                this.element,
                'compareWord',
                disabled,
                disabled ? this.translate('wordCompareUnavailable', 'messages', 'GeneratorPerioadeCursuriWordComparator') : ''
            );
        }

        renderWordComparisonResult(result) {
            const container = this.getWordComparisonContainer();

            if (!container) {
                return;
            }

            const rows = result.rows || [];

            container.innerHTML = [
                '<div class="panel panel-default">',
                '<div class="panel-heading">',
                '<h4 class="panel-title">' + RecordUi.escapeHtml(this.composeComparisonTitle(result)) + '</h4>',
                '</div>',
                '<div class="panel-body">',
                '<p class="text-muted">' + RecordUi.escapeHtml(this.composeComparisonSummary(result)) + '</p>',
                '<div class="table-responsive">',
                '<table class="table table-bordered table-striped table-hover word-compare-table" style="table-layout: auto;">',
                '<thead>',
                '<tr>',
                '<th rowspan="2">' + RecordUi.escapeHtml(this.translate('status', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th colspan="2">' + RecordUi.escapeHtml(this.translate('course', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th colspan="2">' + RecordUi.escapeHtml(this.translate('duration', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th colspan="2">' + RecordUi.escapeHtml(this.translate('price', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '</tr>',
                '<tr>',
                '<th class="word-compare-subhead">' + RecordUi.escapeHtml(this.translate('word', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th class="word-compare-subhead">' + RecordUi.escapeHtml(this.translate('website', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th class="word-compare-subhead">' + RecordUi.escapeHtml(this.translate('word', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th class="word-compare-subhead">' + RecordUi.escapeHtml(this.translate('website', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th class="word-compare-subhead">' + RecordUi.escapeHtml(this.translate('word', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th class="word-compare-subhead">' + RecordUi.escapeHtml(this.translate('website', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '</tr>',
                '</thead>',
                '<tbody>',
                rows.length ?
                    rows.map(row => this.composeComparisonRow(row)).join('') :
                    '<tr><td colspan="7" class="text-muted">' +
                    RecordUi.escapeHtml(this.translate('noWordRows', 'messages', 'GeneratorPerioadeCursuriWordComparator')) +
                    '</td></tr>',
                '</tbody>',
                '</table>',
                '</div>',
                '</div>',
                '</div>'
            ].join('');

            container.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        getWordComparisonContainer() {
            return RecordUi.ensureRecordRegion(this.element, 'word-comparison-result');
        }

        composeComparisonTitle(result) {
            return this.translate('wordComparisonResultWithCounts', 'labels', 'GeneratorPerioadeCursuriWordComparator')
                .replace('{wordCount}', String(result.wordCount || 0))
                .replace('{excelCount}', String(result.excelCount || 0));
        }

        composeComparisonSummary(result) {
            return this.translate('wordCompareSummary', 'messages', 'GeneratorPerioadeCursuriWordComparator')
                .replace('{matched}', String(result.matchedCount || 0))
                .replace('{different}', String(result.differentCount || 0))
                .replace('{wordOnly}', String(result.wordOnlyCount || 0))
                .replace('{excelOnly}', String(result.excelOnlyCount || 0));
        }

        composeComparisonRow(row) {
            const missingWebsite = row.excelTitle === null;
            const missingWord = row.wordTitle === null;

            let rowClass;
            let rowStatusKey;

            if (missingWebsite) {
                rowClass = 'word-compare-row-missing';
                rowStatusKey = 'rowStatusMissingWebsite';
            } else if (missingWord) {
                rowClass = 'word-compare-row-missing';
                rowStatusKey = 'rowStatusMissingWord';
            } else if (this.rowHasDifference(row)) {
                rowClass = 'word-compare-row-different';
                rowStatusKey = 'rowStatusChanged';
            } else {
                rowClass = 'word-compare-row-same';
                rowStatusKey = 'rowStatusSame';
            }

            const course = this.composeValueCells(row.title);
            const duration = this.composeValueCells(row.duration);
            const price = this.composeValueCells(row.price);

            return [
                '<tr class="' + rowClass + '">',
                '<td class="word-compare-status-cell">',
                '<span class="label label-state word-compare-row-status-label">' +
                RecordUi.escapeHtml(this.translate(rowStatusKey, 'labels', 'GeneratorPerioadeCursuriWordComparator')) +
                '</span>',
                '</td>',
                course.word,
                course.excel,
                duration.word,
                duration.excel,
                price.word,
                price.excel,
                '</tr>'
            ].join('');
        }

        rowHasDifference(row) {
            return ['title', 'duration', 'price'].some(field => row[field] && row[field].status !== 'same');
        }

        composeValueCells(field) {
            if (!field) {
                return {word: '<td></td>', excel: '<td></td>'};
            }

            const tintClass = field.status === 'same' ?
                '' :
                (field.status === 'different' ? 'word-compare-cell-diff' : 'word-compare-cell-missing');
            const wordValue = field.word ?
                RecordUi.escapeHtml(field.word) :
                '<span class="text-muted">—</span>';
            const excelValue = field.excel ?
                RecordUi.escapeHtml(field.excel) :
                '<span class="text-muted">—</span>';

            return {
                word: '<td class="' + tintClass + '">' + wordValue + '</td>',
                excel: '<td class="' + tintClass + '">' + excelValue + '</td>'
            };
        }

        getWordCompareErrorMessage(xhr) {
            const fallback = this.translate('wordCompareFailed', 'messages', 'GeneratorPerioadeCursuriWordComparator');

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
