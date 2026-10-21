define(['views/fields/varchar', 'model'], (VarcharFieldView, Model) => {
    return class extends VarcharFieldView {
        editTemplateContent = `
            <div class="attendance-schedule-editor">
                <div class="attendance-schedule-editor-intro">
                    <span class="fas fa-business-time attendance-schedule-editor-icon" aria-hidden="true"></span>
                    <div>
                        <strong>{{translate 'Employee Schedules' category='labels' scope='EmployeeAttendanceRecord'}}</strong>
                        <div class="text-muted small">
                            {{translate 'Schedule Editor Guide' category='messages' scope='EmployeeAttendanceRecord'}}
                        </div>
                    </div>
                </div>
                <div class="attendance-schedule-loading text-muted" data-role="schedule-loading">
                    <span class="fas fa-spinner fa-spin" aria-hidden="true"></span>
                    {{translate 'Loading Schedules' category='messages' scope='EmployeeAttendanceRecord'}}
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
            this.addHandler('change', '[data-schedule-field="cic"]',
                (event, target) => this.toggleCicControls($(target).closest('[data-user-id]')));
            this.scheduleModels = new Map();
            this.scheduleIndexes = new Map();
        }

        afterRender() {
            super.afterRender();
            this.loadSchedules();
        }

        async loadSchedules() {
            try {
                const data = await Espo.Ajax.getRequest('EmployeeManagement/schedules');

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
                    'EmployeeAttendanceRecord'
                ));
            }
        }

        renderRows(users) {
            const body = this.$el.find('[data-role="schedule-rows"]').empty();

            this.scheduleModels = new Map();
            this.scheduleIndexes = new Map();

            users.forEach((user, index) => {
                const schedule = user.schedule || {};
                const row = $('<div>')
                    .addClass('attendance-schedule-row')
                    .attr({'data-user-id': user.id || '', 'data-row-index': index});
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
                const remoteControl = this.createRemoteControl(schedule.remote === true);
                const cicControl = this.createCicControl(schedule.cic === true);
                const cicDates = this.createCicDateControls(index, schedule.cic === true);
                const actions = $('<div>').addClass('attendance-schedule-actions').append(
                    $('<button>', {
                        type: 'button',
                        class: 'btn btn-default btn-sm',
                        'data-action': 'save-work-schedule',
                    }).append(
                        $('<span>').addClass('fas fa-save').attr('aria-hidden', 'true'),
                        ' ',
                        this.translate('Save Schedule', 'labels', 'EmployeeAttendanceRecord')
                    )
                );

                const model = new Model({
                    cicStartDate: schedule.cicStartDate || null,
                    cicEndDate: schedule.cicEndDate || null,
                });
                model.entityType = 'EmployeeAttendanceWorkSchedule';
                model.on('change:cicStartDate change:cicEndDate', () => this.markDirty(row));
                this.scheduleModels.set(String(user.id || ''), model);
                this.scheduleIndexes.set(String(user.id || ''), index);

                row.append(
                    employee,
                    startControl,
                    endControl,
                    remoteControl,
                    cicControl,
                    cicDates,
                    actions
                );
                body.append(row);
            });

            body.find('[data-action="save-work-schedule"]').prop('disabled', true);
            this.renderCicDateFields(users).then(() => {
                body.find('[data-action="save-work-schedule"]').prop('disabled', false);
            }).catch(() => {
                Espo.Ui.error(this.translate(
                    'Schedule Load Failed',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            });
        }

        createTimeControl(labelKey, fieldName, value) {
            const label = this.translate(labelKey, 'labels', 'EmployeeAttendanceRecord');
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
                text: this.translate('Select Time', 'labels', 'EmployeeAttendanceRecord'),
            }));
            values.forEach(time => select.append($('<option>', {value: time, text: time})));
            select.val(value);

            return $('<label>').addClass('attendance-schedule-control').append(
                $('<span>').text(label),
                select
            );
        }

        createRemoteControl(checked) {
            const label = this.translate('Remote', 'labels', 'EmployeeAttendanceRecord');
            const checkbox = $('<input>', {type: 'checkbox'})
                .attr({
                    'data-schedule-field': 'remote',
                    'aria-label': label,
                })
                .prop('checked', checked);

            return $('<label>')
                .addClass('attendance-schedule-control attendance-schedule-remote')
                .append(
                    $('<span>').text(label),
                    $('<span>').addClass('attendance-schedule-checkbox').append(checkbox, ' ', label)
                );
        }

        createCicControl(checked) {
            const label = this.translate('CIC', 'labels', 'EmployeeAttendanceRecord');
            const checkbox = $('<input>', {type: 'checkbox'})
                .attr({
                    'data-schedule-field': 'cic',
                    'aria-label': label,
                })
                .prop('checked', checked);

            return $('<label>')
                .addClass('attendance-schedule-control attendance-schedule-cic')
                .append(
                    $('<span>').text(label),
                    $('<span>').addClass('attendance-schedule-checkbox').append(checkbox, ' ', label)
                );
        }

        createCicDateControls(index, enabled) {
            return $('<div>')
                .addClass('attendance-schedule-cic-dates')
                .toggleClass('hidden', !enabled)
                .append(
                    $('<div>').addClass('attendance-schedule-control').append(
                        $('<span>').text(this.translate(
                            'CIC Start Date', 'labels', 'EmployeeAttendanceRecord'
                        )),
                        $('<div>').addClass('field').attr('data-cic-start-field', index)
                    ),
                    $('<div>').addClass('attendance-schedule-control').append(
                        $('<span>').text(this.translate(
                            'CIC End Date', 'labels', 'EmployeeAttendanceRecord'
                        )),
                        $('<div>').addClass('field').attr('data-cic-end-field', index)
                    )
                );
        }

        renderCicDateFields(users) {
            const promises = [];

            users.forEach((user, index) => {
                const model = this.scheduleModels.get(String(user.id || ''));

                if (!model) {
                    return;
                }

                promises.push(this.createView(`cicStartDate-${index}`, 'views/fields/date', {
                    selector: `[data-cic-start-field="${index}"]`,
                    mode: 'edit',
                    model,
                    name: 'cicStartDate',
                    readOnlyDisabled: true,
                }).then(view => view.render()));
                promises.push(this.createView(`cicEndDate-${index}`, 'views/fields/date', {
                    selector: `[data-cic-end-field="${index}"]`,
                    mode: 'edit',
                    model,
                    name: 'cicEndDate',
                    readOnlyDisabled: true,
                }).then(view => view.render()));
            });

            return Promise.all(promises);
        }

        toggleCicControls(row) {
            const enabled = row.find('[data-schedule-field="cic"]').prop('checked') === true;
            row.find('.attendance-schedule-cic-dates').toggleClass('hidden', !enabled);
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
            const remote = row.find('[data-schedule-field="remote"]').prop('checked') === true;
            const cic = row.find('[data-schedule-field="cic"]').prop('checked') === true;
            const userId = String(row.attr('data-user-id') || '');
            const index = this.scheduleIndexes.get(userId);
            const cicStartDate = index === undefined
                ? null
                : (this.getView(`cicStartDate-${index}`)?.fetch().cicStartDate || null);
            const cicEndDate = index === undefined
                ? null
                : (this.getView(`cicEndDate-${index}`)?.fetch().cicEndDate || null);

            if (!startTime || !endTime || startTime >= endTime) {
                Espo.Ui.error(this.translate(
                    'Invalid Schedule',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));

                return;
            }

            if (cic && (!cicStartDate || !cicEndDate || cicStartDate > cicEndDate)) {
                Espo.Ui.error(this.translate(
                    'Invalid CIC Period',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));

                return;
            }

            button.prop('disabled', true);

            try {
                await Espo.Ajax.postRequest('EmployeeManagement/overview/schedule', {
                    userId,
                    startTime,
                    endTime,
                    remote,
                    cic,
                    cicStartDate: cic ? cicStartDate : null,
                    cicEndDate: cic ? cicEndDate : null,
                });
                Espo.Ui.success(this.translate(
                    'Schedule Saved',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
                row.removeClass('attendance-schedule-row-dirty');
                button.removeClass('btn-primary').addClass('btn-default');
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Schedule Save Failed',
                    'messages',
                    'EmployeeAttendanceRecord'
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
