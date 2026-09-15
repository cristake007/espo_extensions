define(['views/fields/varchar'], (VarcharFieldView) => {
    return class extends VarcharFieldView {
        editTemplateContent = `
            <div class="attendance-schedule-editor">
                <div class="attendance-schedule-editor-intro">
                    <span class="fas fa-business-time attendance-schedule-editor-icon" aria-hidden="true"></span>
                    <div>
                        <strong>{{translate 'Employee Schedules' category='labels' scope='AttendanceRecord'}}</strong>
                        <div class="text-muted small">
                            {{translate 'Schedule Editor Guide' category='messages' scope='AttendanceRecord'}}
                        </div>
                    </div>
                </div>
                <div class="attendance-schedule-loading text-muted" data-role="schedule-loading">
                    <span class="fas fa-spinner fa-spin" aria-hidden="true"></span>
                    {{translate 'Loading Schedules' category='messages' scope='AttendanceRecord'}}
                </div>
                <div class="attendance-schedule-rows hidden" data-role="schedule-rows"></div>
            </div>
        `;

        setup() {
            super.setup();
            this.addHandler('click', '[data-action="save-work-schedule"]',
                (event, target) => this.saveSchedule(target));
            this.addHandler('change', '[data-schedule-field]',
                (event, target) => this.markDirty(target));
        }

        afterRender() {
            super.afterRender();
            this.loadSchedules();
        }

        async loadSchedules() {
            try {
                const data = await Espo.Ajax.getRequest('AttendanceManagement/schedules');

                if (!this.element) {
                    return;
                }

                this.renderRows(data.users || []);
                this.element.querySelector('[data-role="schedule-loading"]')?.classList.add('hidden');
                this.element.querySelector('[data-role="schedule-rows"]')?.classList.remove('hidden');
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Schedule Load Failed',
                    'messages',
                    'AttendanceRecord'
                ));
            }
        }

        renderRows(users) {
            const body = this.$el.find('[data-role="schedule-rows"]').empty();

            users.forEach(user => {
                const schedule = user.schedule || {};
                const row = $('<div>')
                    .addClass('attendance-schedule-row')
                    .attr('data-user-id', user.id || '');
                const employee = $('<div>').addClass('attendance-schedule-employee').append(
                    $('<span>').addClass('fas fa-user-clock').attr('aria-hidden', 'true'),
                    $('<strong>').text(user.name || '')
                );
                const startControl = this.createTimeControl(
                    'Start Time',
                    'startTime',
                    schedule.startTime || ''
                );
                const endControl = this.createTimeControl(
                    'End Time',
                    'endTime',
                    schedule.endTime || ''
                );
                const actions = $('<div>').addClass('attendance-schedule-actions').append(
                    $('<button>', {
                        type: 'button',
                        class: 'btn btn-default btn-sm',
                        'data-action': 'save-work-schedule',
                    }).append(
                        $('<span>').addClass('fas fa-save').attr('aria-hidden', 'true'),
                        ' ',
                        this.translate('Save Schedule', 'labels', 'AttendanceRecord')
                    )
                );

                row.append(employee, startControl, endControl, actions);
                body.append(row);
            });
        }

        createTimeControl(labelKey, fieldName, value) {
            const label = this.translate(labelKey, 'labels', 'AttendanceRecord');
            const select = $('<select>')
                .addClass('form-control input-sm')
                .attr({
                    'data-schedule-field': fieldName,
                    'aria-label': label,
                });
            const values = [];

            for (let minutes = 0; minutes < 24 * 60; minutes += 15) {
                values.push(
                    `${String(Math.floor(minutes / 60)).padStart(2, '0')}:` +
                    String(minutes % 60).padStart(2, '0')
                );
            }

            if (value && !values.includes(value)) {
                values.push(value);
                values.sort();
            }

            select.append($('<option>', {
                value: '',
                text: this.translate('Select Time', 'labels', 'AttendanceRecord'),
            }));
            values.forEach(time => select.append($('<option>', {value: time, text: time})));
            select.val(value);

            return $('<label>').addClass('attendance-schedule-control').append(
                $('<span>').text(label),
                select
            );
        }

        markDirty(target) {
            $(target).closest('[data-user-id]')
                .addClass('attendance-schedule-row-dirty')
                .find('[data-action="save-work-schedule"]')
                .removeClass('btn-default')
                .addClass('btn-primary');
        }

        async saveSchedule(target) {
            const button = $(target);
            const row = button.closest('[data-user-id]');
            const startTime = row.find('[data-schedule-field="startTime"]').val();
            const endTime = row.find('[data-schedule-field="endTime"]').val();

            if (!startTime || !endTime || startTime >= endTime) {
                Espo.Ui.error(this.translate(
                    'Invalid Schedule',
                    'messages',
                    'AttendanceRecord'
                ));

                return;
            }

            button.prop('disabled', true);

            try {
                await Espo.Ajax.postRequest('AttendanceManagement/overview/schedule', {
                    userId: row.attr('data-user-id'),
                    startTime,
                    endTime,
                });
                Espo.Ui.success(this.translate(
                    'Schedule Saved',
                    'messages',
                    'AttendanceRecord'
                ));
                row.removeClass('attendance-schedule-row-dirty');
                button.removeClass('btn-primary').addClass('btn-default');
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Schedule Save Failed',
                    'messages',
                    'AttendanceRecord'
                ));
            } finally {
                button.prop('disabled', false);
            }
        }

        fetch() {
            return {};
        }
    };
});
