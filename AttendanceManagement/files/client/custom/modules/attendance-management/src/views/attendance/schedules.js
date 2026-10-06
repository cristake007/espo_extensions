define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <div class="attendance-settings-page">
            <div class="header page-header attendance-page-header attendance-settings-header">
                <div class="attendance-settings-title">
                    <span class="fas fa-clipboard-check" aria-hidden="true"></span>
                    <h3>{{translate 'Attendance Management' category='labels' scope='Admin'}}</h3>
                </div>
                <p class="text-muted attendance-page-intro">
                    {{translate 'Settings Page Guide' category='messages' scope='AttendanceRecord'}}
                </p>
            </div>
            <div class="attendance-settings-section attendance-manager-settings-panel">
                <div class="attendance-settings-section-heading">
                    <span class="fas fa-sliders-h attendance-settings-section-icon" aria-hidden="true"></span>
                    <div>
                        <strong>{{translate 'Attendance Configuration' category='labels' scope='AttendanceRecord'}}</strong>
                        <div class="text-muted small">
                            {{translate 'Attendance Configuration Help' category='messages' scope='AttendanceRecord'}}
                        </div>
                    </div>
                </div>
                <div class="attendance-settings-section-body">
                    <div class="attendance-settings-card-grid">
                        <label class="attendance-settings-card attendance-settings-card-editing">
                            <span class="attendance-settings-card-icon fas fa-calendar-check" aria-hidden="true"></span>
                            <span class="attendance-settings-card-content">
                                <strong>{{translate 'Editable Previous Months' category='labels' scope='AttendanceRecord'}}</strong>
                                <small class="text-muted">
                                    {{translate 'Editable Previous Months Help' category='messages' scope='AttendanceRecord'}}
                                </small>
                                <span class="attendance-number-control">
                                    <input class="form-control" type="number" min="0" max="120"
                                        data-setting="editablePastMonths">
                                    <span>{{translate 'Months' category='labels' scope='AttendanceRecord'}}</span>
                                </span>
                            </span>
                        </label>
                        <label class="attendance-settings-card attendance-settings-card-export">
                            <span class="attendance-settings-card-icon fas fa-file-export" aria-hidden="true"></span>
                            <span class="attendance-settings-card-content">
                                <strong>{{translate 'Allow Incomplete Exports' category='labels' scope='AttendanceRecord'}}</strong>
                                <small class="text-muted">
                                    {{translate 'Allow Incomplete Exports Help' category='messages' scope='AttendanceRecord'}}
                                </small>
                                <span class="attendance-checkbox-control">
                                    <input type="checkbox" data-setting="allowIncompleteExports">
                                    <span>{{translate 'Enable Option' category='labels' scope='AttendanceRecord'}}</span>
                                </span>
                            </span>
                        </label>
                    </div>
                    <div class="attendance-archive-card">
                        <span class="attendance-settings-card-icon fas fa-history" aria-hidden="true"></span>
                        <div class="attendance-archive-card-content">
                            <strong>{{translate 'Attendance Archive' category='labels' scope='AttendanceRecord'}}</strong>
                            <span class="attendance-archive-status" data-role="archive-start-month"></span>
                            <small class="text-muted">
                                {{translate 'Initialize Attendance Help' category='messages' scope='AttendanceRecord'}}
                            </small>
                        </div>
                        <button type="button" class="btn btn-default attendance-archive-action"
                            data-action="initialize-attendance-archive">
                            <span class="fas fa-sync-alt" aria-hidden="true"></span>
                            {{translate 'Initialize Attendance' category='labels' scope='AttendanceRecord'}}
                        </button>
                    </div>
                    <div class="attendance-settings-actions">
                        <button type="button" class="btn btn-primary" data-action="save-attendance-settings">
                            <span class="fas fa-save" aria-hidden="true"></span>
                            {{translate 'Save Settings' category='labels' scope='AttendanceRecord'}}
                        </button>
                    </div>
                </div>
            </div>
            <div class="attendance-schedules-page attendance-schedule-editor">
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
            </div>
        `;

        setup() {
            this.addHandler('click', '[data-action="save-work-schedule"]',
                (event, target) => this.saveSchedule(target));
            this.addHandler('change', '[data-schedule-field]',
                (event, target) => this.markDirty(target));
            this.addHandler('click', '[data-action="save-attendance-settings"]',
                (event, target) => this.saveSettings(target));
            this.addHandler('click', '[data-action="initialize-attendance-archive"]',
                (event, target) => this.initializeArchive(target));
            this.settings = {
                editablePastMonths: 1,
                allowIncompleteExports: false,
                archiveStartMonth: '',
            };
            this.schedules = {users: []};
            this.wait(Promise.all([this.loadSettings(), this.loadSchedules()]));
        }

        async loadSettings() {
            try {
                this.settings = await Espo.Ajax.getRequest('AttendanceManagement/settings');
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Settings Load Failed',
                    'messages',
                    'AttendanceRecord'
                ));
            }
        }

        async loadSchedules() {
            try {
                this.schedules = await Espo.Ajax.getRequest('AttendanceManagement/schedules');
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Schedule Load Failed',
                    'messages',
                    'AttendanceRecord'
                ));
            }
        }

        afterRender() {
            this.$el.find('[data-setting="editablePastMonths"]')
                .val(this.settings.editablePastMonths);
            this.$el.find('[data-setting="allowIncompleteExports"]')
                .prop('checked', this.settings.allowIncompleteExports === true);
            this.renderArchiveStatus();
            this.renderRows(this.schedules.users || []);
            this.element.querySelector('[data-role="schedule-loading"]')?.classList.add('hidden');
            this.element.querySelector('[data-role="schedule-rows"]')?.classList.remove('hidden');
        }

        async saveSettings(target) {
            const button = $(target);
            const editablePastMonths = Number(
                this.$el.find('[data-setting="editablePastMonths"]').val()
            );
            const allowIncompleteExports = this.$el
                .find('[data-setting="allowIncompleteExports"]')
                .prop('checked') === true;

            if (
                !Number.isInteger(editablePastMonths) ||
                editablePastMonths < 0 ||
                editablePastMonths > 120
            ) {
                Espo.Ui.error(this.translate(
                    'Invalid Settings',
                    'messages',
                    'AttendanceRecord'
                ));

                return;
            }

            button.prop('disabled', true);

            try {
                this.settings = await Espo.Ajax.putRequest('AttendanceManagement/settings', {
                    editablePastMonths,
                    allowIncompleteExports,
                });
                Espo.Ui.success(this.translate(
                    'Settings Saved',
                    'messages',
                    'AttendanceRecord'
                ));
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Settings Save Failed',
                    'messages',
                    'AttendanceRecord'
                ));
            } finally {
                button.prop('disabled', false);
            }
        }

        renderArchiveStatus() {
            const text = this.settings.archiveStartMonth
                ? this.translate('Archive Initialized From', 'messages', 'AttendanceRecord')
                    .replace('{month}', this.settings.archiveStartMonth)
                : this.translate('Archive Automatic Detection', 'messages', 'AttendanceRecord');

            this.$el.find('[data-role="archive-start-month"]').text(text);
        }

        async initializeArchive(target) {
            await this.confirm({
                message: this.translate(
                    'Confirm Initialize Attendance',
                    'messages',
                    'AttendanceRecord'
                ),
                confirmText: this.translate(
                    'Initialize Attendance',
                    'labels',
                    'AttendanceRecord'
                ),
            });

            const button = $(target);
            button.prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'AttendanceManagement/settings/initialize'
                );
                this.settings.archiveStartMonth = result.archiveStartMonth || '';
                this.renderArchiveStatus();
                Espo.Ui.success(this.translate(
                    'Attendance Initialized',
                    'messages',
                    'AttendanceRecord'
                ));
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Attendance Initialize Failed',
                    'messages',
                    'AttendanceRecord'
                ));
            } finally {
                button.prop('disabled', false);
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
    };
});
