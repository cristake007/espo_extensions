define('generator-perioade-cursuri:views/fields/holidays', [
    'views/fields/varchar',
    'ui/datepicker',
    'generator-perioade-cursuri:views/shared/record-ui'
], function (VarcharFieldView, Datepicker, RecordUi) {
    return class extends VarcharFieldView {
        detailTemplateContent = `
            {{#if hasDates}}
                <div class="list-group" style="margin-bottom: 0;">
                    {{#each holidayRows}}
                        <div class="list-group-item" style="padding: 6px 10px;">
                            <span>{{displayDate}}</span>
                            <span class="label {{badgeClass}}" style="margin-left: 8px;">{{typeLabel}}</span>
                            {{#if name}}<span class="text-muted" style="margin-left: 8px;">{{name}}</span>{{/if}}
                        </div>
                    {{/each}}
                </div>
            {{else}}
                <span class="text-muted">{{emptyLabel}}</span>
            {{/if}}
        `;

        editTemplateContent = `
            <div class="generator-perioade-cursuri-holidays-field">
                <div data-role="date-list">
                    {{#each holidayRows}}
                        <div data-role="date-row" data-holiday-date="{{isoDate}}" data-holiday-name="{{name}}" data-holiday-type="{{type}}" data-holiday-source="{{source}}" style="margin-bottom: 8px;">
                            <div class="input-group">
                                <input type="text" class="form-control numeric-text holiday-date" value="{{displayDate}}" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default btn-icon date-picker-btn" data-action="showHolidayDatePicker" tabindex="-1">
                                        <span class="far fa-calendar"></span>
                                    </button>
                                    <button type="button" class="btn btn-default" data-action="removeHolidayDate" title="{{../removeLabel}}">
                                        <span class="fas fa-times"></span>
                                    </button>
                                </span>
                            </div>
                            <div class="small" data-role="holiday-metadata" style="margin-top: 3px;">
                                <span class="label {{badgeClass}}">{{typeLabel}}</span>
                                {{#if name}}<span class="text-muted" style="margin-left: 6px;">{{name}}</span>{{/if}}
                            </div>
                        </div>
                    {{/each}}
                </div>
                <button type="button" class="btn btn-default btn-sm" data-action="addHolidayDate">
                    <span class="fas fa-plus"></span>
                    <span>{{addLabel}}</span>
                </button>
                <button type="button" class="btn btn-default btn-sm" data-action="importHolidayDates">
                    <span class="fas fa-cloud-download-alt"></span>
                    <span>{{importLabel}}</span>
                </button>
                <input type="hidden" class="main-element" data-name="{{name}}" value="{{value}}">
                <div class="text-muted small" style="margin-top: 6px;">{{helpText}}</div>
            </div>
        `;

        validations = ['required', 'holidayDates'];

        setup() {
            super.setup();

            this.addHandler('click', '[data-action="addHolidayDate"]', () => this.addHolidayDate());
            this.addHandler('click', '[data-action="importHolidayDates"]', () => this.importHolidayDates());
            this.addHandler('click', '[data-action="removeHolidayDate"]', (e, target) => this.removeHolidayDate(target));
            this.addHandler('click', '[data-action="showHolidayDatePicker"]', (e, target) => this.showHolidayDatePicker(target));
            this.addHandler('input', 'input.holiday-date', (e, target) => this.handleDateInput(target));
            this.addHandler('change', 'input.holiday-date', (e, target) => this.handleDateInput(target));

            this.holidayDatepickers = new WeakMap();
            this.holidayImportPending = false;
        }

        data() {
            const data = super.data();
            const dateList = this.parseValue(this.model.get(this.name));
            const holidayRows = this.buildHolidayRows(dateList);

            data.holidayRows = holidayRows.length ? holidayRows :
                (this.isEditMode() ? [this.buildHolidayRow('', null)] : []);
            data.hasDates = dateList.length > 0;
            data.value = this.serializeDateList(dateList);
            data.addLabel = this.translate('Add holiday date', 'labels', 'GeneratorPerioadeCursuri');
            data.importLabel = this.translate('Import holiday dates', 'labels', 'GeneratorPerioadeCursuri');
            data.removeLabel = this.translate('Remove holiday date', 'labels', 'GeneratorPerioadeCursuri');
            data.emptyLabel = this.translate('No holiday dates', 'labels', 'GeneratorPerioadeCursuri');
            data.helpText = this.translate('holidayPickerHelp', 'messages', 'GeneratorPerioadeCursuri');

            return data;
        }

        afterRender() {
            super.afterRender();

            this.element.querySelectorAll('input.holiday-date').forEach(input => this.initHolidayDatePicker(input));
            this.syncHiddenInput();
        }

        addHolidayDate() {
            if (!this.appendHolidayDateRow()) {
                return;
            }

            this.syncHiddenInput();
            this.trigger('change');
        }

        appendHolidayDateRow(value = '', focus = true, detail = null) {
            const container = this.element.querySelector('[data-role="date-list"]');

            if (!container) {
                return false;
            }

            const row = document.createElement('div');
            row.dataset.role = 'date-row';
            row.style.marginBottom = '8px';
            row.innerHTML = [
                '<div class="input-group">',
                '<input type="text" class="form-control numeric-text holiday-date" autocomplete="off">',
                '<span class="input-group-btn">',
                '<button type="button" class="btn btn-default btn-icon date-picker-btn" data-action="showHolidayDatePicker" tabindex="-1">',
                '<span class="far fa-calendar"></span>',
                '</button>',
                '<button type="button" class="btn btn-default" data-action="removeHolidayDate" title="' + RecordUi.escapeHtml(this.translate('Remove holiday date', 'labels', 'GeneratorPerioadeCursuri')) + '">',
                '<span class="fas fa-times"></span>',
                '</button>',
                '</span>',
                '</div>',
                '<div class="small" data-role="holiday-metadata" style="margin-top: 3px;"></div>'
            ].join('');

            container.appendChild(row);

            const input = row.querySelector('input.holiday-date');

            if (input) {
                input.value = value ? this.toDisplayDate(value) : '';
                this.initHolidayDatePicker(input);

                if (focus) {
                    input.focus();
                }
            }

            this.setHolidayRowMetadata(row, detail);

            return true;
        }

        async importHolidayDates() {
            if (this.holidayImportPending) {
                return;
            }

            const year = this.normalizeHolidayImportYear(this.model.get('year'));

            if (year === null) {
                this.notifyHolidayImport('holidayImportMissingYear');

                return;
            }

            const months = this.normalizeHolidayImportMonths(this.model.get('selectedMonths'));

            if (months === null) {
                this.notifyHolidayImport('holidayImportMissingMonths');

                return;
            }

            this.setHolidayImportPending(true);

            try {
                const response = await Espo.Ajax.postRequest(
                    'ZileLibere/availableDates',
                    {year: year, months: months}
                );

                if (!this.isValidHolidayImportResponse(response)) {
                    this.notifyHolidayImport('holidayImportInvalidResponse');

                    return;
                }

                if (response.dates.length === 0) {
                    this.notifyHolidayImport('holidayImportNoResults');

                    return;
                }

                const existingDates = new Set(this.getInputDateList());
                const importedDetails = this.getImportedHolidayDetails(response);
                let added = false;
                let metadataChanged = false;

                response.dates.forEach(isoDate => {
                    const internalDate = this.fromIsoHolidayDate(isoDate);
                    const detail = importedDetails.get(isoDate) || {
                        date: isoDate,
                        name: '',
                        type: 'legal',
                        source: 'zile-sarbatoare'
                    };

                    if (existingDates.has(internalDate)) {
                        const existingRow = this.findHolidayRowByDate(internalDate);

                        if (existingRow) {
                            this.setHolidayRowMetadata(existingRow, detail);
                            metadataChanged = true;
                        }

                        return;
                    }

                    existingDates.add(internalDate);
                    added = this.appendHolidayDateRow(internalDate, false, detail) || added;
                });

                const removedBlankRows = this.removeBlankHolidayRows();

                if (added || removedBlankRows || metadataChanged) {
                    this.syncHiddenInput();
                    this.trigger('change');
                }
            } catch (error) {
                this.markHolidayImportErrorHandled(error);
                this.notifyHolidayImport(this.getHolidayImportErrorKey(error));
            } finally {
                this.setHolidayImportPending(false);
            }
        }

        markHolidayImportErrorHandled(error) {
            if (!error || typeof error !== 'object') {
                return;
            }

            error.errorIsHandled = true;

            if (error.xhr && typeof error.xhr === 'object') {
                error.xhr.errorIsHandled = true;
            }
        }

        normalizeHolidayImportYear(value) {
            if (typeof value === 'string' && /^\d+$/.test(value)) {
                value = Number(value);
            }

            return Number.isInteger(value) && value >= 1 && value <= 9998 ? value : null;
        }

        normalizeHolidayImportMonths(value) {
            if (!Array.isArray(value) || value.length === 0) {
                return null;
            }

            const months = value.map(month => {
                if (typeof month === 'string' && /^\d+$/.test(month)) {
                    return Number(month);
                }

                return month;
            });

            return months.every(month => Number.isInteger(month) && month >= 1 && month <= 12) ?
                months : null;
        }

        isValidHolidayImportResponse(response) {
            if (!response || typeof response !== 'object' || !Array.isArray(response.dates) ||
                !response.dates.every(date => this.isValidIsoHolidayDate(date))) {
                return false;
            }

            if (response.holidays === undefined) {
                return true;
            }

            return Array.isArray(response.holidays) && response.holidays.every(holiday =>
                holiday && typeof holiday === 'object' &&
                this.isValidIsoHolidayDate(holiday.date) &&
                response.dates.includes(holiday.date) &&
                typeof holiday.name === 'string' &&
                ['legal', 'internal'].includes(holiday.type) &&
                holiday.source === 'zile-sarbatoare'
            );
        }

        getImportedHolidayDetails(response) {
            return new Map((response.holidays || []).map(holiday => [holiday.date, holiday]));
        }

        buildHolidayRows(dateList) {
            const details = new Map(this.parseHolidayDetails(this.model.get('holidayDetails'))
                .map(detail => [detail.date, detail]));

            return dateList.map(date => this.buildHolidayRow(date, details.get(this.toIsoHolidayDate(date))));
        }

        buildHolidayRow(date, detail) {
            const imported = detail && detail.source === 'zile-sarbatoare';
            const legal = imported && detail.type === 'legal';

            return {
                displayDate: date ? this.toDisplayDate(date) : '',
                isoDate: date ? this.toIsoHolidayDate(date) : '',
                name: imported && typeof detail.name === 'string' ? detail.name : '',
                type: legal ? 'legal' : 'internal',
                source: imported ? 'zile-sarbatoare' : 'manual',
                typeLabel: this.translate(
                    legal ? 'Legal holiday' : 'Internal day off',
                    'labels',
                    'GeneratorPerioadeCursuri'
                ),
                badgeClass: legal ? 'label-success' : (imported ? 'label-info' : 'label-default')
            };
        }

        parseHolidayDetails(value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value.filter(detail =>
                detail && typeof detail === 'object' &&
                this.isValidIsoHolidayDate(detail.date) &&
                typeof detail.name === 'string' &&
                ['legal', 'internal'].includes(detail.type) &&
                ['zile-sarbatoare', 'manual'].includes(detail.source)
            );
        }

        setHolidayRowMetadata(row, detail) {
            const imported = detail && detail.source === 'zile-sarbatoare' &&
                ['legal', 'internal'].includes(detail.type);
            const legal = imported && detail.type === 'legal';

            row.dataset.holidayDate = imported && this.isValidIsoHolidayDate(detail.date) ? detail.date : '';
            row.dataset.holidayName = imported && typeof detail.name === 'string' ? detail.name : '';
            row.dataset.holidayType = legal ? 'legal' : 'internal';
            row.dataset.holidaySource = imported ? 'zile-sarbatoare' : 'manual';
            this.renderHolidayRowMetadata(row);
        }

        renderHolidayRowMetadata(row) {
            const container = row.querySelector('[data-role="holiday-metadata"]');

            if (!container) {
                return;
            }

            const legal = row.dataset.holidayType === 'legal' &&
                row.dataset.holidaySource === 'zile-sarbatoare';
            const imported = row.dataset.holidaySource === 'zile-sarbatoare';
            const label = this.translate(
                legal ? 'Legal holiday' : 'Internal day off',
                'labels',
                'GeneratorPerioadeCursuri'
            );
            const name = imported ? row.dataset.holidayName || '' : '';

            container.innerHTML = '<span class="label ' +
                (legal ? 'label-success' : (imported ? 'label-info' : 'label-default')) + '">' +
                RecordUi.escapeHtml(label) + '</span>' +
                (name ? '<span class="text-muted" style="margin-left: 6px;">' +
                    RecordUi.escapeHtml(name) + '</span>' : '');
        }

        findHolidayRowByDate(date) {
            if (!this.element) {
                return null;
            }

            return Array.from(this.element.querySelectorAll('[data-role="date-row"]')).find(row => {
                const input = row.querySelector('input.holiday-date');

                return input && this.fromDisplayDate(input.value.trim()) === date;
            }) || null;
        }

        isValidIsoHolidayDate(value) {
            if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                return false;
            }

            const date = new Date(value + 'T00:00:00Z');

            return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
        }

        fromIsoHolidayDate(value) {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

            return [match[3], match[2], match[1]].join('.');
        }

        removeBlankHolidayRows() {
            if (!this.element) {
                return false;
            }

            let removed = false;

            this.element.querySelectorAll('[data-role="date-row"]').forEach(row => {
                const input = row.querySelector('input.holiday-date');

                if (input && input.value.trim() === '') {
                    row.remove();
                    removed = true;
                }
            });

            return removed;
        }

        getHolidayImportErrorKey(error) {
            const status = Number(error && (error.status || (error.xhr && error.xhr.status))) || 0;

            if (status === 400) {
                return 'holidayImportBadRequest';
            }

            if (status === 403) {
                return 'holidayImportForbidden';
            }

            if (status === 404 || status === 405) {
                return 'holidayImportUnavailable';
            }

            return 'holidayImportTemporaryUnavailable';
        }

        notifyHolidayImport(messageKey) {
            Espo.Ui.notify(
                this.translate(messageKey, 'messages', 'GeneratorPerioadeCursuri'),
                'warning'
            );
        }

        setHolidayImportPending(pending) {
            this.holidayImportPending = pending;

            const button = this.element ?
                this.element.querySelector('[data-action="importHolidayDates"]') : null;

            if (button) {
                button.disabled = pending;
            }
        }

        removeHolidayDate(target) {
            const row = target.closest('[data-role="date-row"]');

            if (row) {
                row.remove();
            }

            const container = this.element.querySelector('[data-role="date-list"]');

            if (container && !container.querySelector('[data-role="date-row"]')) {
                this.addHolidayDate();
                return;
            }

            this.syncHiddenInput();
            this.trigger('change');
        }

        handleDateInput(target) {
            const row = target && target.closest ? target.closest('[data-role="date-row"]') : null;

            if (row && row.dataset.holidaySource === 'zile-sarbatoare') {
                const date = this.toIsoHolidayDate(this.fromDisplayDate(target.value.trim()));

                if (date !== row.dataset.holidayDate) {
                    this.setHolidayRowMetadata(row, null);
                }
            }

            this.syncHiddenInput();
            this.trigger('change');
        }

        initHolidayDatePicker(input) {
            if (!(input instanceof HTMLInputElement) || this.holidayDatepickers.has(input)) {
                return;
            }

            const datepicker = new Datepicker(input, {
                format: this.getDateTime().dateFormat,
                weekStart: this.getDateTime().weekStart,
                todayButton: this.getConfig().get('datepickerTodayButton') || false,
                onChange: () => this.handleDateInput(input)
            });

            this.holidayDatepickers.set(input, datepicker);
        }

        showHolidayDatePicker(target) {
            const input = target.closest('[data-role="date-row"]')?.querySelector('input.holiday-date');
            const datepicker = input ? this.holidayDatepickers.get(input) : null;

            if (datepicker) {
                datepicker.show();
            }
        }

        fetch() {
            const data = {};
            const value = this.serializeDateList(this.getInputDateList());

            data[this.name] = value || null;
            data.holidayDetails = this.getHolidayDetails();

            return data;
        }

        getHolidayDetails() {
            if (!this.element) {
                return [];
            }

            return Array.from(this.element.querySelectorAll('[data-role="date-row"]'))
                .map(row => {
                    const input = row.querySelector('input.holiday-date');
                    const date = input ? this.toIsoHolidayDate(this.fromDisplayDate(input.value.trim())) : '';

                    if (!this.isValidIsoHolidayDate(date)) {
                        return null;
                    }

                    const imported = row.dataset.holidaySource === 'zile-sarbatoare' &&
                        row.dataset.holidayDate === date;
                    const legal = imported && row.dataset.holidayType === 'legal';

                    return {
                        date: date,
                        name: imported ? row.dataset.holidayName || '' : '',
                        type: legal ? 'legal' : 'internal',
                        source: imported ? 'zile-sarbatoare' : 'manual'
                    };
                })
                .filter(detail => detail !== null);
        }

        validateHolidayDates() {
            const dates = this.getInputDateList();
            const seen = {};

            for (const date of dates) {
                if (!/^\d{2}\.\d{2}\.\d{4}$/.test(date)) {
                    this.showValidationMessage(
                        this.translate('holidayPickerInvalidDate', 'messages', 'GeneratorPerioadeCursuri'),
                        '[data-name="' + this.name + '"]'
                    );

                    return true;
                }

                if (seen[date]) {
                    this.showValidationMessage(
                        this.translate('holidayPickerDuplicateDate', 'messages', 'GeneratorPerioadeCursuri').replace('{date}', date),
                        '[data-name="' + this.name + '"]'
                    );

                    return true;
                }

                seen[date] = true;
            }

            return false;
        }

        syncHiddenInput() {
            const input = this.element ? this.element.querySelector('input.main-element') : null;

            if (input) {
                input.value = this.serializeDateList(this.getInputDateList());
            }
        }

        getInputDateList() {
            if (!this.element) {
                return [];
            }

            return Array.from(this.element.querySelectorAll('input.holiday-date'))
                .map(input => input.value.trim())
                .filter(value => value !== '')
                .map(value => this.fromDisplayDate(value));
        }

        parseValue(value) {
            if (!value || typeof value !== 'string') {
                return [];
            }

            return value.split(',')
                .map(item => item.trim())
                .filter(item => item !== '');
        }

        serializeDateList(dateList) {
            return dateList.join(', ');
        }

        toDisplayDate(value) {
            const match = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(value);

            if (!match) {
                return value;
            }

            return this.getDateTime().toDisplayDate([match[3], match[2], match[1]].join('-'));
        }

        fromDisplayDate(value) {
            const internalDate = this.getDateTime().fromDisplayDate(value);
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(internalDate);

            if (!match) {
                return value;
            }

            return [match[3], match[2], match[1]].join('.');
        }

        toIsoHolidayDate(value) {
            const match = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(value);

            return match ? [match[3], match[2], match[1]].join('-') : value;
        }
    };
});
