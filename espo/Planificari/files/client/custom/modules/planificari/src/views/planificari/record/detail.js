define('planificari:views/planificari/record/detail', ['views/record/detail'], function (DetailRecordView) {
    return class extends DetailRecordView {
        setup() {
            super.setup();

            this.generatedScheduleResult = null;
            this.addButton({
                name: 'generate',
                label: 'Generate',
                style: 'primary',
                iconClass: 'fas fa-play'
            }, true);

            this.addButton({
                name: 'exportXlsx',
                label: 'Export XLSX',
                style: 'default',
                iconClass: 'fas fa-file-excel'
            }, true);

        }

        afterRender() {
            super.afterRender();

            this.updateGenerateButtonState();
            this.updateExportButtonState();

            if (this.generatedScheduleResult) {
                this.renderGeneratedScheduleTable(this.generatedScheduleResult);
            }
        }

        async actionGenerate() {
            if (this.model.get('generatedAt')) {
                Espo.Ui.warning(this.translate('alreadyGenerated', 'messages', 'Planificari'));

                return;
            }

            const payload = {
                id: this.model.id,
                name: this.model.get('name') || null,
                sourceFileId: this.model.get('sourceFileId') || null,
                sourceFileName: this.model.get('sourceFileName') || null,
                year: this.model.get('year') || null,
                selectedMonths: this.model.get('selectedMonths') || [],
                randomness: this.model.get('randomness') || null,
                holidays: this.model.get('holidays') || null
            };

            Espo.Ui.notify('Generating...');

            try {
                const result = await Espo.Ajax.postRequest('Planificari/generate', payload);

                Espo.Ui.notify(false);

                if (result.warningMessage) {
                    Espo.Ui.error(result.warningMessage);
                } else {
                    Espo.Ui.success(result.message || this.translate('generateRequestReceived', 'messages', 'Planificari'));
                }

                this.generatedScheduleResult = result;
                this.model.set('generatedAt', result.record ? result.record.generatedAt : null);
                this.model.set('exportFileId', result.record ? result.record.exportFileId : null);
                this.updateGenerateButtonState();
                this.updateExportButtonState();
                this.renderGeneratedScheduleTable(result);
            } catch (e) {
                Espo.Ui.notify(false);

                Espo.Ui.error(this.getGenerateErrorMessage(e));
            }
        }

        async actionExportXlsx() {
            const exportFileId = this.model.get('exportFileId');

            if (exportFileId) {
                window.open('?entryPoint=download&id=' + encodeURIComponent(exportFileId), '_blank');

                return;
            }

            Espo.Ui.notify('Exporting...');

            try {
                const result = await Espo.Ajax.postRequest('Planificari/' + this.model.id + '/exportXlsx', {});

                Espo.Ui.notify(false);

                if (result.downloadUrl) {
                    window.open(result.downloadUrl, '_blank');
                }
            } catch (e) {
                Espo.Ui.notify(false);
                Espo.Ui.error(this.getGenerateErrorMessage(e));
            }
        }

        getGenerateErrorMessage(xhr) {
            const fallback = this.translate('generateFailed', 'messages', 'Planificari');

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

        updateGenerateButtonState() {
            const button = this.element.querySelector('[data-action="generate"]');

            if (!button) {
                return;
            }

            const disabled = !!this.model.get('generatedAt');

            button.disabled = disabled;
            button.classList.toggle('disabled', disabled);
            button.title = disabled ? this.translate('alreadyGenerated', 'messages', 'Planificari') : '';
        }

        updateExportButtonState() {
            const button = this.element.querySelector('[data-action="exportXlsx"]');

            if (!button) {
                return;
            }

            const disabled = !this.model.get('exportFileId');

            button.disabled = disabled;
            button.classList.toggle('disabled', disabled);
            button.title = disabled ? this.translate('exportUnavailable', 'messages', 'Planificari') : '';
        }

        renderGeneratedScheduleTable(result) {
            const rows = this.buildCourseRows(result.rows || []);
            const months = this.getGeneratedMonths(result.rows || [], result.record || {});
            const container = this.getGeneratedScheduleContainer();

            if (!container) {
                return;
            }

            container.innerHTML = [
                '<div class="panel panel-default">',
                '<div class="panel-heading">',
                '<h4 class="panel-title">' + this.escapeHtml(this.translate('generatedSchedule', 'labels', 'Planificari')) + '</h4>',
                '</div>',
                '<div class="panel-body">',
                '<div data-role="generated-schedule-top-scroll" style="overflow-x: auto; overflow-y: hidden; height: 16px; margin-bottom: 6px;">',
                '<div style="height: 1px;"></div>',
                '</div>',
                '<div class="table-responsive" data-role="generated-schedule-scroll">',
                '<table class="table table-bordered table-striped table-hover text-nowrap" style="table-layout: fixed;">',
                '<thead>',
                '<tr>',
                '<th class="text-nowrap" data-sticky-column="0">' + this.escapeHtml(this.translate('rowNumber', 'labels', 'Planificari')) + '</th>',
                '<th class="text-nowrap" data-sticky-column="1">' + this.escapeHtml(this.translate('courseName', 'labels', 'Planificari')) + '</th>',
                '<th class="text-nowrap" data-sticky-column="2">' + this.escapeHtml(this.translate('durationLabel', 'labels', 'Planificari')) + '</th>',
                '<th class="text-nowrap" data-sticky-column="3">' + this.escapeHtml(this.translate('investment', 'labels', 'Planificari')) + '</th>',
                months.map(month => '<th class="text-nowrap" data-month-column>' + this.escapeHtml(month.name) + '</th>').join(''),
                '<th aria-hidden="true" data-role="generated-schedule-scroll-spacer"></th>',
                '</tr>',
                '</thead>',
                '<tbody>',
                rows.length ?
                    rows.map((row, index) => this.composeGeneratedScheduleRow(row, months, index)).join('') :
                    '<tr><td colspan="' + (4 + months.length) + '" class="text-muted">' +
                    this.escapeHtml(this.translate('noGeneratedRows', 'messages', 'Planificari')) +
                    '</td></tr>',
                '</tbody>',
                '</table>',
                '</div>',
                '</div>',
                '</div>'
            ].join('');

            this.setupGeneratedScheduleScrolling(container);
        }

        getGeneratedScheduleContainer() {
            let container = this.element.querySelector('[data-name="generated-schedule"]');

            if (container) {
                return container;
            }

            const recordContainer = this.element.querySelector('.record') || this.element;

            container = document.createElement('div');
            container.dataset.name = 'generated-schedule';

            recordContainer.appendChild(container);

            return container;
        }

        buildCourseRows(rows) {
            const map = {};

            rows.forEach(item => {
                const key = String(item.sourceRow || item.originalOrder || item.courseTitle || '');

                if (!map[key]) {
                    map[key] = {
                        originalOrder: item.originalOrder || 0,
                        courseTitle: item.courseTitle || '',
                        durationLabel: item.durationLabel || '',
                        investment: item.investment || '',
                        months: {}
                    };
                }

                map[key].months[String(item.month)] = item.dateRange || '';
            });

            return Object.values(map).sort((a, b) => a.originalOrder - b.originalOrder);
        }

        getGeneratedMonths(rows, record) {
            const monthMap = {};

            rows.forEach(item => {
                monthMap[String(item.month)] = item.monthName || item.month;
            });

            const selectedMonths = record.selectedMonths || [];
            const source = selectedMonths.length ? selectedMonths : Object.keys(monthMap);

            return source
                .filter(month => monthMap[String(month)])
                .map(month => ({
                    value: String(month),
                    name: monthMap[String(month)]
                }))
                .sort((a, b) => Number(a.value) - Number(b.value));
        }

        composeGeneratedScheduleRow(row, months, index) {
            return [
                '<tr>',
                '<td class="text-nowrap" data-sticky-column="0">' + this.escapeHtml(index + 1) + '</td>',
                '<td class="text-nowrap" data-sticky-column="1">' + this.escapeHtml(row.courseTitle) + '</td>',
                '<td class="text-nowrap" data-sticky-column="2">' + this.escapeHtml(row.durationLabel) + '</td>',
                '<td class="text-nowrap" data-sticky-column="3">' + this.escapeHtml(row.investment) + '</td>',
                months.map(month => '<td class="text-nowrap" data-month-column>' + this.escapeHtml(row.months[month.value] || '') + '</td>').join(''),
                '<td aria-hidden="true" data-role="generated-schedule-scroll-spacer"></td>',
                '</tr>'
            ].join('');
        }

        setupGeneratedScheduleScrolling(container) {
            const topScroller = container.querySelector('[data-role="generated-schedule-top-scroll"]');
            const mainScroller = container.querySelector('[data-role="generated-schedule-scroll"]');
            const table = mainScroller ? mainScroller.querySelector('table') : null;
            const topInner = topScroller ? topScroller.firstElementChild : null;

            if (!topScroller || !mainScroller || !table || !topInner) {
                return;
            }

            const stickyWidth = this.applyGeneratedScheduleStickyColumns(container);
            const monthWidth = 140;
            const monthColumnCount = table.querySelectorAll('thead [data-month-column]').length;
            const tableWidth = stickyWidth + monthColumnCount * monthWidth + stickyWidth;

            Array.from(table.querySelectorAll('[data-month-column]')).forEach(cell => {
                cell.style.minWidth = monthWidth + 'px';
                cell.style.width = monthWidth + 'px';
                cell.style.maxWidth = monthWidth + 'px';
            });

            Array.from(table.querySelectorAll('[data-role="generated-schedule-scroll-spacer"]')).forEach(cell => {
                cell.style.minWidth = stickyWidth + 'px';
                cell.style.width = stickyWidth + 'px';
                cell.style.padding = '0';
                cell.style.border = '0';
            });

            table.style.minWidth = tableWidth + 'px';
            table.style.width = tableWidth + 'px';
            topInner.style.width = table.scrollWidth + 'px';

            topScroller.addEventListener('scroll', () => {
                mainScroller.scrollLeft = topScroller.scrollLeft;
            });

            mainScroller.addEventListener('scroll', () => {
                topScroller.scrollLeft = mainScroller.scrollLeft;
            });

        }

        applyGeneratedScheduleStickyColumns(container) {
            const table = container.querySelector('table');
            const headerCells = table ? Array.from(table.querySelectorAll('thead th')) : [];

            if (!table || headerCells.length < 4) {
                return 0;
            }

            const widths = [56, 360, 120, 130];
            let left = 0;

            for (let index = 0; index < 4; index++) {
                const cells = Array.from(table.querySelectorAll('[data-sticky-column="' + index + '"]'));
                const width = widths[index];

                cells.forEach(cell => {
                    cell.style.position = 'sticky';
                    cell.style.left = left + 'px';
                    cell.style.zIndex = cell.tagName === 'TH' ? '6' : '4';
                    cell.style.minWidth = width + 'px';
                    cell.style.width = width + 'px';
                    cell.style.maxWidth = width + 'px';
                    cell.style.backgroundClip = 'padding-box';
                    cell.style.backgroundColor = 'var(--panel-bg-color, var(--body-bg, #fff))';

                    if (index === 1) {
                        cell.style.whiteSpace = 'normal';
                        cell.style.wordBreak = 'break-word';
                    }
                });

                left += width;
            }

            return left;
        }

        escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };
});
