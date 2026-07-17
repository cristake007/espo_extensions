define('generator-perioade-cursuri:views/fields/holidays', [
    'views/fields/varchar',
    'ui/datepicker',
    'generator-perioade-cursuri:views/shared/record-ui'
], function (VarcharFieldView, Datepicker, RecordUi) {
    return class extends VarcharFieldView {
        detailTemplateContent = `
            {{#if hasDates}}
                <div class="list-group" style="margin-bottom: 0;">
                    {{#each dateList}}
                        <div class="list-group-item" style="padding: 6px 10px;">{{this}}</div>
                    {{/each}}
                </div>
            {{else}}
                <span class="text-muted">{{emptyLabel}}</span>
            {{/if}}
        `;

        editTemplateContent = `
            <div class="generator-perioade-cursuri-holidays-field">
                <div data-role="date-list">
                    {{#each dateList}}
                        <div class="input-group" data-role="date-row" style="margin-bottom: 6px;">
                            <input type="text" class="form-control numeric-text holiday-date" value="{{this}}" autocomplete="off">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default btn-icon date-picker-btn" data-action="showHolidayDatePicker" tabindex="-1">
                                    <span class="far fa-calendar"></span>
                                </button>
                                <button type="button" class="btn btn-default" data-action="removeHolidayDate" title="{{removeLabel}}">
                                    <span class="fas fa-times"></span>
                                </button>
                            </span>
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
            this.addHandler('input', 'input.holiday-date', () => this.handleDateInput());
            this.addHandler('change', 'input.holiday-date', () => this.handleDateInput());

            this.holidayDatepickers = new WeakMap();
            this.holidayImportPending = false;
        }

        data() {
            const data = super.data();
            const dateList = this.parseValue(this.model.get(this.name));

            data.dateList = dateList.map(date => this.toDisplayDate(date));
            data.dateList = data.dateList.length ? data.dateList : (this.isEditMode() ? [''] : []);
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

        appendHolidayDateRow(value = '', focus = true) {
            const container = this.element.querySelector('[data-role="date-list"]');

            if (!container) {
                return false;
            }

            const row = document.createElement('div');
            row.className = 'input-group';
            row.dataset.role = 'date-row';
            row.style.marginBottom = '6px';
            row.innerHTML = [
                '<input type="text" class="form-control numeric-text holiday-date" autocomplete="off">',
                '<span class="input-group-btn">',
                '<button type="button" class="btn btn-default btn-icon date-picker-btn" data-action="showHolidayDatePicker" tabindex="-1">',
                '<span class="far fa-calendar"></span>',
                '</button>',
                '<button type="button" class="btn btn-default" data-action="removeHolidayDate" title="' + RecordUi.escapeHtml(this.translate('Remove holiday date', 'labels', 'GeneratorPerioadeCursuri')) + '">',
                '<span class="fas fa-times"></span>',
                '</button>',
                '</span>'
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
                let added = false;

                response.dates.forEach(isoDate => {
                    const internalDate = this.fromIsoHolidayDate(isoDate);

                    if (existingDates.has(internalDate)) {
                        return;
                    }

                    existingDates.add(internalDate);
                    added = this.appendHolidayDateRow(internalDate, false) || added;
                });

                const removedBlankRows = this.removeBlankHolidayRows();

                if (added || removedBlankRows) {
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
            return !!response && typeof response === 'object' && Array.isArray(response.dates) &&
                response.dates.every(date => this.isValidIsoHolidayDate(date));
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

        handleDateInput() {
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
                onChange: () => this.handleDateInput()
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

            return data;
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
    };
});
