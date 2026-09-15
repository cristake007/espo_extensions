define(['views/fields/varchar'], (VarcharFieldView) => {
    return class extends VarcharFieldView {
        editTemplateContent = `
            <div class="attendance-schedule-settings">
                <div class="text-muted" data-role="schedule-loading">
                    {{translate 'Loading Schedules' category='messages' scope='AttendanceRecord'}}
                </div>
                <div class="table-responsive hidden" data-role="schedule-table-wrap">
                    <table class="table table-bordered table-condensed attendance-schedule-settings-table">
                        <thead>
                            <tr>
                                <th>{{translate 'Employee' category='labels' scope='AttendanceRecord'}}</th>
                                <th>{{translate 'Start Time' category='labels' scope='AttendanceRecord'}}</th>
                                <th>{{translate 'End Time' category='labels' scope='AttendanceRecord'}}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-role="schedule-rows"></tbody>
                    </table>
                </div>
            </div>
        `;

        setup() {
            super.setup();
            this.addHandler('click', '[data-action="save-work-schedule"]',
                (event, target) => this.saveSchedule(target));
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
                this.element.querySelector('[data-role="schedule-table-wrap"]')?.classList.remove('hidden');
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
                const row = $('<tr>').attr('data-user-id', user.id || '');

                row.append(
                    $('<td>').text(user.name || ''),
                    $('<td>').append($('<input>', {
                        type: 'time',
                        class: 'form-control input-sm',
                        value: schedule.startTime || '',
                        'data-schedule-field': 'startTime',
                    })),
                    $('<td>').append($('<input>', {
                        type: 'time',
                        class: 'form-control input-sm',
                        value: schedule.endTime || '',
                        'data-schedule-field': 'endTime',
                    })),
                    $('<td>').append($('<button>', {
                        type: 'button',
                        class: 'btn btn-default btn-sm',
                        'data-action': 'save-work-schedule',
                    }).append(
                        $('<span>').addClass('fas fa-save').attr('aria-hidden', 'true'),
                        ' ',
                        this.translate('Save Schedule', 'labels', 'AttendanceRecord')
                    ))
                );
                body.append(row);
            });
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
