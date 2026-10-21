define(['view', 'model'], (View, Model) => {
    return class extends View {
        templateContent = `
            <main class="attendance-settings-page employee-attendance-metadata-page">
                <header class="employee-attendance-metadata-hero">
                    <div class="employee-attendance-metadata-hero-icon" aria-hidden="true">
                        <span class="fas fa-business-time"></span>
                    </div>
                    <div>
                        <div class="employee-attendance-metadata-eyebrow">{{translate 'Attendance Configuration' category='labels' scope='EmployeeAttendanceRecord'}}</div>
                        <h3>{{translate 'Employee Schedules' category='labels' scope='EmployeeAttendanceRecord'}}</h3>
                        <p>{{translate 'Schedule Editor Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                    </div>
                </header>

                <section class="attendance-metadata-panel attendance-schedules-page attendance-schedule-editor">
                    <div class="attendance-metadata-heading">
                        <div class="attendance-metadata-heading-copy">
                            <div class="attendance-metadata-section-icon">
                                <span class="fas fa-users-cog" aria-hidden="true"></span>
                            </div>
                            <div>
                                <h4>{{translate 'Schedule Configuration' category='labels' scope='EmployeeAttendanceRecord'}}</h4>
                                <p>{{translate 'Schedule Configuration Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                            </div>
                        </div>
                        <div class="attendance-metadata-count" data-role="schedule-count"></div>
                    </div>
                    <div class="attendance-schedule-loading" data-role="schedule-loading">
                        <span class="fas fa-spinner fa-spin" aria-hidden="true"></span>
                        {{translate 'Loading Schedules' category='messages' scope='EmployeeAttendanceRecord'}}
                    </div>
                    <div class="attendance-schedule-empty hidden" data-role="schedule-empty">
                        <span class="fas fa-user-clock" aria-hidden="true"></span>
                        <strong>{{translate 'No Employee Schedules' category='labels' scope='EmployeeAttendanceRecord'}}</strong>
                        <p>{{translate 'No Employee Schedules Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                    </div>
                    <div class="attendance-schedule-rows hidden" data-role="schedule-rows"></div>
                </section>
            </main>
        `;

        setup() {
            this.addHandler('click', '[data-action="save-work-schedule"]',
                (event, target) => this.saveSchedule(target));
            this.addHandler('change', '[data-schedule-field]',
                (event, target) => this.markDirty(target));
            this.addHandler('change', '[data-schedule-field="cic"]',
                (event, target) => this.toggleCicControls($(target).closest('[data-user-id]')));
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
            this.scheduleModels = new Map();
            this.scheduleIndexes = new Map();
            this.wait(this.loadSchedules());
        }

        async loadSettings() {
            try {
                this.settings = await Espo.Ajax.getRequest('EmployeeManagement/attendance/settings');
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Settings Load Failed',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            }
        }

        async loadSchedules() {
            try {
                this.schedules = await Espo.Ajax.getRequest('EmployeeManagement/attendance/schedules');
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Schedule Load Failed',
                    'messages',
                    'EmployeeAttendanceRecord'
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

            if ((this.schedules.users || []).length > 0) {
                this.element.querySelector('[data-role="schedule-rows"]')?.classList.remove('hidden');
            }
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
                    'EmployeeAttendanceRecord'
                ));

                return;
            }

            button.prop('disabled', true);

            try {
                this.settings = await Espo.Ajax.putRequest('EmployeeManagement/attendance/settings', {
                    editablePastMonths,
                    allowIncompleteExports,
                });
                Espo.Ui.success(this.translate(
                    'Settings Saved',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Settings Save Failed',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            } finally {
                button.prop('disabled', false);
            }
        }

        renderArchiveStatus() {
            const text = this.settings.archiveStartMonth
                ? this.translate('Archive Initialized From', 'messages', 'EmployeeAttendanceRecord')
                    .replace('{month}', this.settings.archiveStartMonth)
                : this.translate('Archive Automatic Detection', 'messages', 'EmployeeAttendanceRecord');

            this.$el.find('[data-role="archive-start-month"]').text(text);
        }

        async initializeArchive(target) {
            await this.confirm({
                message: this.translate(
                    'Confirm Initialize Attendance',
                    'messages',
                    'EmployeeAttendanceRecord'
                ),
                confirmText: this.translate(
                    'Initialize Attendance',
                    'labels',
                    'EmployeeAttendanceRecord'
                ),
            });

            const button = $(target);
            button.prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'EmployeeManagement/attendance/initialize'
                );
                this.settings.archiveStartMonth = result.archiveStartMonth || '';
                this.renderArchiveStatus();
                Espo.Ui.success(this.translate(
                    'Attendance Initialized',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Attendance Initialize Failed',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            } finally {
                button.prop('disabled', false);
            }
        }

        renderRows(users) {
            const body = this.$el.find('[data-role="schedule-rows"]').empty();
            const countText = this.translate(
                'Schedule Employee Count',
                'messages',
                'EmployeeAttendanceRecord'
            ).replace('{count}', users.length);

            this.$el.find('[data-role="schedule-count"]').text(countText);
            this.$el.find('[data-role="schedule-empty"]').toggleClass('hidden', users.length > 0);
            body.toggleClass('hidden', users.length === 0);

            this.scheduleModels = new Map();
            this.scheduleIndexes = new Map();

            users.forEach((user, index) => {
                const schedule = user.schedule || {};
                const row = $('<div>')
                    .addClass('attendance-schedule-row')
                    .attr({'data-user-id': user.id || '', 'data-row-index': index});
                const employee = $('<div>').addClass('attendance-schedule-employee').append(
                    $('<span>').addClass('attendance-schedule-employee-icon fas fa-user-clock')
                        .attr('aria-hidden', 'true'),
                    $('<div>').addClass('attendance-schedule-employee-copy').append(
                        $('<span>').addClass('attendance-schedule-employee-label').text(
                            this.translate('Employee', 'labels', 'EmployeeAttendanceRecord')
                        ),
                        $('<strong>').text(user.name || ''),
                        $('<span>').addClass('attendance-schedule-dirty-indicator hidden').append(
                            $('<span>').addClass('fas fa-circle').attr('aria-hidden', 'true'),
                            ' ',
                            this.translate('Unsaved Changes', 'labels', 'EmployeeAttendanceRecord')
                        )
                    )
                );
                const remoteControl = this.createRemoteControl(schedule.remote === true);
                const cicControl = this.createCicControl(schedule.cic === true);
                const cicDates = this.createCicDateControls(index, schedule.cic === true);
                const configuration = $('<div>')
                    .addClass('attendance-schedule-configuration')
                    .append(remoteControl, cicControl, cicDates);
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
                    configuration,
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

        createRemoteControl(checked) {
            const label = this.translate('Remote', 'labels', 'EmployeeAttendanceRecord');
            const checkbox = $('<input>', {type: 'checkbox'})
                .attr({
                    'data-schedule-field': 'remote',
                    'aria-label': label,
                })
                .prop('checked', checked);

            return $('<label>')
                .addClass('attendance-schedule-choice attendance-schedule-remote')
                .append(
                    $('<span>').addClass('attendance-schedule-choice-icon fas fa-house-user')
                        .attr('aria-hidden', 'true'),
                    $('<span>').addClass('attendance-schedule-choice-copy').append(
                        $('<strong>').text(label),
                        $('<small>').text(this.translate(
                            'Remote Schedule Help', 'messages', 'EmployeeAttendanceRecord'
                        ))
                    ),
                    $('<span>').addClass('attendance-schedule-checkbox').append(checkbox)
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
                .addClass('attendance-schedule-choice attendance-schedule-cic')
                .append(
                    $('<span>').addClass('attendance-schedule-choice-icon fas fa-child')
                        .attr('aria-hidden', 'true'),
                    $('<span>').addClass('attendance-schedule-choice-copy').append(
                        $('<strong>').text(label),
                        $('<small>').text(this.translate(
                            'CIC Schedule Help', 'messages', 'EmployeeAttendanceRecord'
                        ))
                    ),
                    $('<span>').addClass('attendance-schedule-checkbox').append(checkbox)
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
            const row = $(target).closest('[data-user-id]');

            row.addClass('attendance-schedule-row-dirty')
                .find('.attendance-schedule-dirty-indicator')
                .removeClass('hidden');
            row.find('[data-action="save-work-schedule"]')
                .removeClass('btn-default')
                .addClass('btn-primary');
        }

        async saveSchedule(target) {
            const button = $(target);
            const row = button.closest('[data-user-id]');
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
                await Espo.Ajax.postRequest('EmployeeManagement/attendance/schedule', {
                    userId,
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
                row.find('.attendance-schedule-dirty-indicator').addClass('hidden');
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
    };
});
