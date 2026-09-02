define('generator-perioade-cursuri:views/generator-perioade-cursuri-word-comparator/record/detail', [
    'views/record/detail',
    'generator-perioade-cursuri:views/shared/record-ui'
], function (DetailRecordView, RecordUi) {
    const DIACRITICS_MAP = {'ă': 'a', 'â': 'a', 'î': 'i', 'ș': 's', 'ş': 's', 'ț': 't', 'ţ': 't'};

    function normalizeTitle(value) {
        let text = String(value === null || value === undefined ? '' : value).replace(/\u00A0/g, ' ');
        text = text.toLowerCase().trim();
        text = text.replace(/[ăâîșşțţ]/g, char => DIACRITICS_MAP[char] || char);
        text = text.replace(/[^\p{L}\p{N}_\s]+/gu, ' ');

        return text.replace(/\s+/g, ' ').trim();
    }

    function normalizeForCompare(value) {
        return String(value === null || value === undefined ? '' : value).toLowerCase().trim().replace(/\s+/g, ' ');
    }

    function extractNumber(value) {
        const match = String(value).match(/-?\d[\d.,]*/);

        if (!match) {
            return null;
        }

        let digits = match[0].replace(/\s+/g, '');
        const hasComma = digits.indexOf(',') !== -1;
        const hasDot = digits.indexOf('.') !== -1;

        if (hasComma && hasDot) {
            digits = digits.replace(/\./g, '').replace(',', '.');
        } else if (hasComma) {
            digits = digits.replace(',', '.');
        } else {
            digits = digits.replace(/,/g, '');
        }

        const number = parseFloat(digits);

        return Number.isNaN(number) ? null : number;
    }

    function valuesMatch(a, b, mode) {
        if (mode === 'title') {
            return normalizeTitle(a) === normalizeTitle(b);
        }

        const numberA = extractNumber(a);
        const numberB = extractNumber(b);

        if (numberA !== null && numberB !== null) {
            return Math.abs(numberA - numberB) < 0.005;
        }

        return normalizeForCompare(a) === normalizeForCompare(b);
    }

    // Mirrors WordComparisonService::fieldDiff() so the browser can recompute the
    // diff instantly whenever the user changes a row's selected website course,
    // without a round trip to the server.
    function buildFieldDiff(wordValue, excelValue, mode) {
        const word = String(wordValue === null || wordValue === undefined ? '' : wordValue).trim();
        const excel = excelValue === null || excelValue === undefined ? null : String(excelValue).trim();

        let status;

        if (excel === null) {
            status = word === '' ? 'missingBoth' : 'missingExcel';
        } else if (word === '' && excel === '') {
            status = 'missingBoth';
        } else if (word === '') {
            status = 'missingWord';
        } else if (excel === '') {
            status = 'missingExcel';
        } else if (valuesMatch(word, excel, mode)) {
            status = 'same';
        } else {
            status = 'different';
        }

        return {status: status, word: word, excel: excel};
    }

    return class extends DetailRecordView {
        setup() {
            this.isWide = true;
            this.sideDisabled = true;

            super.setup();

            this.wordComparisonData = null;
            this.wordComparisonSelections = {};

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

            if (this.wordComparisonData) {
                this.renderWordComparisonResult();
            }
        }

        async actionCompareWord() {
            Espo.Ui.notify('Comparing...');

            try {
                const result = await Espo.Ajax.postRequest('GeneratorPerioadeCursuriWordComparator/' + this.model.id + '/compareWord', {});

                Espo.Ui.notify(false);

                this.wordComparisonData = result;
                this.wordComparisonSelections = {};

                (result.wordRows || []).forEach(row => {
                    this.wordComparisonSelections[row.wordIndex] = row.selectedExcelIndex;
                });

                this.renderWordComparisonResult();
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

        renderWordComparisonResult() {
            const container = this.getWordComparisonContainer();

            if (!container) {
                return;
            }

            container.innerHTML = [
                '<div class="panel panel-default">',
                '<div class="panel-heading">',
                '<h4 class="panel-title" data-role="word-compare-title"></h4>',
                '</div>',
                '<div class="panel-body">',
                '<p class="text-muted" data-role="word-compare-summary"></p>',
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
                '<tbody data-role="word-compare-body"></tbody>',
                '</table>',
                '</div>',
                '</div>',
                '</div>'
            ].join('');

            const table = container.querySelector('.word-compare-table');

            table.addEventListener('change', event => {
                const select = event.target.closest('select[data-word-row-index]');

                if (!select) {
                    return;
                }

                this.wordComparisonSelections[Number(select.dataset.wordRowIndex)] =
                    select.value === '' ? null : Number(select.value);
                this.renderTableBody();
            });

            table.addEventListener('click', event => {
                const button = event.target.closest('button[data-candidate-excel-index]');

                if (!button) {
                    return;
                }

                this.wordComparisonSelections[Number(button.dataset.wordRowIndex)] =
                    Number(button.dataset.candidateExcelIndex);
                this.renderTableBody();
            });

            this.renderTableBody();
            container.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        renderTableBody() {
            const container = this.getWordComparisonContainer();

            if (!container) {
                return;
            }

            const data = this.wordComparisonData;
            const wordRows = data.wordRows || [];
            const excelOptions = data.excelOptions || [];
            const excelByIndex = {};

            excelOptions.forEach(option => {
                excelByIndex[option.excelIndex] = option;
            });

            const assignedExcelIndexes = {};
            let matchedCount = 0;
            let differentCount = 0;

            wordRows.forEach(row => {
                const selected = this.wordComparisonSelections[row.wordIndex];

                if (selected === null || selected === undefined) {
                    return;
                }

                assignedExcelIndexes[selected] = true;
                matchedCount++;

                const option = excelByIndex[selected];
                const titleDiff = buildFieldDiff(row.title, option ? option.title : null, 'title');
                const durationDiff = buildFieldDiff(row.duration, option ? option.duration : null, 'numeric');
                const priceDiff = buildFieldDiff(row.price, option ? option.price : null, 'numeric');

                if (titleDiff.status !== 'same' || durationDiff.status !== 'same' || priceDiff.status !== 'same') {
                    differentCount++;
                }
            });

            const excelOnly = excelOptions.filter(option => !assignedExcelIndexes[option.excelIndex]);
            const wordOnlyCount = wordRows.length - matchedCount;

            const body = container.querySelector('[data-role="word-compare-body"]');

            if (body) {
                body.innerHTML = wordRows.length || excelOnly.length ?
                    wordRows.map(row => this.composeWordRowTr(row, excelByIndex)).join('') +
                    excelOnly.map(option => this.composeExcelOnlyTr(option)).join('') :
                    '<tr><td colspan="7" class="text-muted">' +
                    RecordUi.escapeHtml(this.translate('noWordRows', 'messages', 'GeneratorPerioadeCursuriWordComparator')) +
                    '</td></tr>';
            }

            const title = container.querySelector('[data-role="word-compare-title"]');

            if (title) {
                title.textContent = this.translate('wordComparisonResultWithCounts', 'labels', 'GeneratorPerioadeCursuriWordComparator')
                    .replace('{wordCount}', String(wordRows.length))
                    .replace('{excelCount}', String(excelOptions.length));
            }

            const summary = container.querySelector('[data-role="word-compare-summary"]');

            if (summary) {
                summary.textContent = this.translate('wordCompareSummary', 'messages', 'GeneratorPerioadeCursuriWordComparator')
                    .replace('{matched}', String(matchedCount))
                    .replace('{different}', String(differentCount))
                    .replace('{wordOnly}', String(wordOnlyCount))
                    .replace('{excelOnly}', String(excelOnly.length));
            }
        }

        composeWordRowTr(row, excelByIndex) {
            const selected = this.wordComparisonSelections[row.wordIndex];
            const hasSelection = selected !== null && selected !== undefined;
            const option = hasSelection ? excelByIndex[selected] : null;

            const titleDiff = buildFieldDiff(row.title, option ? option.title : null, 'title');
            const durationDiff = buildFieldDiff(row.duration, option ? option.duration : null, 'numeric');
            const priceDiff = buildFieldDiff(row.price, option ? option.price : null, 'numeric');

            let rowClass;
            let rowStatusKey;

            if (!hasSelection) {
                rowClass = 'word-compare-row-missing';
                rowStatusKey = 'rowStatusMissingWebsite';
            } else if (titleDiff.status !== 'same' || durationDiff.status !== 'same' || priceDiff.status !== 'same') {
                rowClass = 'word-compare-row-different';
                rowStatusKey = 'rowStatusChanged';
            } else {
                rowClass = 'word-compare-row-same';
                rowStatusKey = 'rowStatusSame';
            }

            const courseSelect = this.composeCourseSelect(row, selected);
            const duration = this.composeValueCells(durationDiff);
            const price = this.composeValueCells(priceDiff);

            return [
                '<tr class="' + rowClass + '">',
                '<td class="word-compare-status-cell">',
                '<span class="label label-state word-compare-row-status-label">' +
                RecordUi.escapeHtml(this.translate(rowStatusKey, 'labels', 'GeneratorPerioadeCursuriWordComparator')) +
                '</span>',
                '</td>',
                '<td style="min-width: 220px; white-space: normal; vertical-align: top;">' + RecordUi.escapeHtml(row.title) + '</td>',
                '<td style="min-width: 260px; vertical-align: top;">' + courseSelect + '</td>',
                duration.word,
                duration.excel,
                price.word,
                price.excel,
                '</tr>'
            ].join('');
        }

        composeExcelOnlyTr(option) {
            const rowClass = 'word-compare-row-missing';
            const durationCell = '<td>' + RecordUi.escapeHtml(option.duration || '—') + '</td>';
            const priceCell = '<td>' + RecordUi.escapeHtml(option.price || '—') + '</td>';

            return [
                '<tr class="' + rowClass + '">',
                '<td class="word-compare-status-cell">',
                '<span class="label label-state word-compare-row-status-label">' +
                RecordUi.escapeHtml(this.translate('rowStatusMissingWord', 'labels', 'GeneratorPerioadeCursuriWordComparator')) +
                '</span>',
                '</td>',
                '<td><span class="text-muted">—</span></td>',
                '<td style="white-space: normal;">' + RecordUi.escapeHtml(option.title) + '</td>',
                '<td><span class="text-muted">—</span></td>',
                durationCell,
                '<td><span class="text-muted">—</span></td>',
                priceCell,
                '</tr>'
            ].join('');
        }

        composeCourseSelect(row, selected) {
            const options = (this.wordComparisonData.excelOptions || []).map(option => {
                const isSelected = selected !== null && selected !== undefined && Number(selected) === option.excelIndex;

                return '<option value="' + option.excelIndex + '"' + (isSelected ? ' selected' : '') + '>' +
                    RecordUi.escapeHtml(option.title) +
                    '</option>';
            }).join('');

            const candidateButtons = (row.candidates || []).map(candidate => [
                '<button type="button" class="btn btn-default btn-xs word-compare-candidate-button"',
                ' data-word-row-index="' + row.wordIndex + '"',
                ' data-candidate-excel-index="' + candidate.excelIndex + '">',
                RecordUi.escapeHtml(candidate.title) + ' (' + RecordUi.escapeHtml(candidate.score) + ')',
                '</button>'
            ].join('')).join('');

            return [
                '<select class="form-control input-sm" data-word-row-index="' + row.wordIndex + '">',
                '<option value="">' + RecordUi.escapeHtml(this.translate('notOnWebsite', 'labels', 'GeneratorPerioadeCursuriWordComparator')) + '</option>',
                options,
                '</select>',
                candidateButtons ? '<div class="word-compare-candidates">' + candidateButtons + '</div>' : ''
            ].join('');
        }

        composeValueCells(field) {
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

        getWordComparisonContainer() {
            return RecordUi.ensureRecordRegion(this.element, 'word-comparison-result');
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
