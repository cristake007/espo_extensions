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
            const excelOnly = result.excelOnly || [];
            const wordOnlyCount = rows.filter(row => !row.matched).length;

            container.innerHTML = [
                '<div class="panel panel-default">',
                '<div class="panel-heading">',
                '<h4 class="panel-title">' + RecordUi.escapeHtml(this.composeComparisonTitle(result)) + '</h4>',
                '</div>',
                '<div class="panel-body">',
                '<p class="text-muted">' + RecordUi.escapeHtml(this.composeComparisonSummary(result, wordOnlyCount)) + '</p>',
                '<div class="table-responsive">',
                '<table class="table table-bordered table-striped table-hover" style="table-layout: auto;">',
                '<thead>',
                '<tr>',
                '<th>' + RecordUi.escapeHtml(this.translate('wordCourse', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('websiteCourse', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('duration', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('price', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '</tr>',
                '</thead>',
                '<tbody>',
                rows.length ?
                    rows.map(row => this.composeComparisonRow(row)).join('') :
                    '<tr><td colspan="4" class="text-muted">' +
                    RecordUi.escapeHtml(this.translate('noWordRows', 'messages', 'GeneratorPerioadeCursuriWordComparator')) +
                    '</td></tr>',
                '</tbody>',
                '</table>',
                '</div>',
                '<h5>' + RecordUi.escapeHtml(this.translate('onlyOnWebsite', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</h5>',
                excelOnly.length ?
                    this.composeExcelOnlyTable(excelOnly) :
                    '<p class="text-muted">' + RecordUi.escapeHtml(this.translate('noExcelOnlyRows', 'messages', 'GeneratorPerioadeCursuriWordComparator')) + '</p>',
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

        composeComparisonSummary(result, wordOnlyCount) {
            const matched = (result.rows || []).filter(row => row.matched).length;

            return this.translate('wordCompareSummary', 'messages', 'GeneratorPerioadeCursuriWordComparator')
                .replace('{matched}', String(matched))
                .replace('{different}', String(result.differentCount || 0))
                .replace('{wordOnly}', String(wordOnlyCount))
                .replace('{excelOnly}', String(result.excelOnlyCount || 0));
        }

        composeComparisonRow(row) {
            const rowClass = !row.matched ?
                'word-compare-row-missing' :
                (this.rowHasDifference(row) ? 'word-compare-row-different' : 'word-compare-row-same');

            return [
                '<tr class="' + rowClass + '">',
                '<td style="min-width: 220px; white-space: normal; vertical-align: top;">' + RecordUi.escapeHtml(row.wordTitle) + '</td>',
                '<td style="min-width: 220px; white-space: normal; vertical-align: top;">',
                row.matched ?
                    this.composeTitleCell(row.title) :
                    '<span class="label label-state word-compare-status-label label-warning">' +
                    RecordUi.escapeHtml(this.translate('notFoundOnWebsite', 'labels', 'GeneratorPerioadeCursuriWordComparator')) +
                    '</span>',
                '</td>',
                '<td style="min-width: 180px; vertical-align: top;">' + this.composeFieldCell(row.duration) + '</td>',
                '<td style="min-width: 180px; vertical-align: top;">' + this.composeFieldCell(row.price) + '</td>',
                '</tr>'
            ].join('');
        }

        rowHasDifference(row) {
            return ['title', 'duration', 'price'].some(field => row[field] && row[field].status !== 'same');
        }

        composeTitleCell(field) {
            if (!field) {
                return '';
            }

            const text = RecordUi.escapeHtml(field.excel || '—');

            if (field.status === 'same') {
                return text;
            }

            return text + ' ' + this.composeStatusBadge(field.status);
        }

        composeFieldCell(field) {
            if (!field) {
                return '';
            }

            return [
                '<div class="word-compare-value-pair">',
                '<div><span class="text-muted">Word:</span> ' + RecordUi.escapeHtml(field.word || '—') + '</div>',
                '<div><span class="text-muted">Site:</span> ' + RecordUi.escapeHtml(field.excel === null ? '—' : (field.excel || '—')) + '</div>',
                '</div>',
                this.composeStatusBadge(field.status)
            ].join('');
        }

        composeStatusBadge(status) {
            const statusClassMap = {
                same: 'label-success',
                different: 'label-danger',
                missingWord: 'label-warning',
                missingExcel: 'label-warning',
                missingBoth: 'label-default'
            };
            const statusClass = statusClassMap[status] || 'label-default';

            return '<span class="label label-state word-compare-status-label ' + statusClass + '">' +
                RecordUi.escapeHtml(this.translate(status, 'labels', 'GeneratorPerioadeCursuriWordComparator')) +
                '</span>';
        }

        composeExcelOnlyTable(excelOnly) {
            return [
                '<div class="table-responsive">',
                '<table class="table table-bordered table-striped" style="table-layout: auto;">',
                '<thead>',
                '<tr>',
                '<th>' + RecordUi.escapeHtml(this.translate('websiteCourse', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('duration', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '<th>' + RecordUi.escapeHtml(this.translate('price', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</th>',
                '</tr>',
                '</thead>',
                '<tbody>',
                excelOnly.map(row => [
                    '<tr>',
                    '<td style="white-space: normal;">' + RecordUi.escapeHtml(row.title) + '</td>',
                    '<td>' + RecordUi.escapeHtml(row.duration || '—') + '</td>',
                    '<td>' + RecordUi.escapeHtml(row.price || '—') + '</td>',
                    '</tr>'
                ].join('')).join(''),
                '</tbody>',
                '</table>',
                '</div>'
            ].join('');
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
