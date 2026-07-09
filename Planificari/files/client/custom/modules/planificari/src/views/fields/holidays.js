define('planificari:views/fields/holidays', ['views/fields/varchar'], function (VarcharFieldView) {
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
            <div class="planificari-holidays-field">
                <div data-role="date-list">
                    {{#each dateList}}
                        <div class="input-group" data-role="date-row" style="margin-bottom: 6px;">
                            <input type="text" class="form-control holiday-date" value="{{this}}" placeholder="dd.mm.yyyy" maxlength="10" inputmode="numeric" autocomplete="off">
                            <span class="input-group-btn">
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
                <input type="hidden" class="main-element" data-name="{{name}}" value="{{value}}">
                <div class="text-muted small" style="margin-top: 6px;">{{helpText}}</div>
            </div>
        `;

        validations = ['required', 'holidayDates'];

        setup() {
            super.setup();

            this.addHandler('click', '[data-action="addHolidayDate"]', () => this.addHolidayDate());
            this.addHandler('click', '[data-action="removeHolidayDate"]', (e, target) => this.removeHolidayDate(target));
            this.addHandler('input', 'input.holiday-date', () => this.handleDateInput());
            this.addHandler('change', 'input.holiday-date', () => this.handleDateInput());
        }

        data() {
            const data = super.data();
            const dateList = this.parseValue(this.model.get(this.name));

            data.dateList = dateList.length ? dateList : (this.isEditMode() ? [''] : []);
            data.hasDates = dateList.length > 0;
            data.value = this.serializeDateList(dateList);
            data.addLabel = this.translate('Add holiday date', 'labels', 'Planificari');
            data.removeLabel = this.translate('Remove holiday date', 'labels', 'Planificari');
            data.emptyLabel = this.translate('No holiday dates', 'labels', 'Planificari');
            data.helpText = this.translate('holidayPickerHelp', 'messages', 'Planificari');

            return data;
        }

        afterRender() {
            super.afterRender();

            this.syncHiddenInput();
        }

        addHolidayDate() {
            const container = this.element.querySelector('[data-role="date-list"]');

            if (!container) {
                return;
            }

            const row = document.createElement('div');
            row.className = 'input-group';
            row.dataset.role = 'date-row';
            row.style.marginBottom = '6px';
            row.innerHTML = [
                '<input type="text" class="form-control holiday-date" placeholder="dd.mm.yyyy" maxlength="10" inputmode="numeric" autocomplete="off">',
                '<span class="input-group-btn">',
                '<button type="button" class="btn btn-default" data-action="removeHolidayDate" title="' + this.escapeHtml(this.translate('Remove holiday date', 'labels', 'Planificari')) + '">',
                '<span class="fas fa-times"></span>',
                '</button>',
                '</span>'
            ].join('');

            container.appendChild(row);

            const input = row.querySelector('input.holiday-date');

            if (input) {
                input.focus();
            }

            this.syncHiddenInput();
            this.trigger('change');
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
                        this.translate('holidayPickerInvalidDate', 'messages', 'Planificari'),
                        '[data-name="' + this.name + '"]'
                    );

                    return true;
                }

                if (seen[date]) {
                    this.showValidationMessage(
                        this.translate('holidayPickerDuplicateDate', 'messages', 'Planificari').replace('{date}', date),
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
                .filter(value => value !== '');
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

        escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    };
});
