define(['view', 'model', 'ajax'], (View, Model, Ajax) => class extends View {
    templateContent = `
        <main class="employee-administration-settings-page employee-administration-{{section}}-page">
            <header class="employee-administration-settings-hero">
                <div class="employee-administration-settings-hero-copy">
                    <a class="employee-administration-settings-back" href="#EmployeeAdministration"
                        aria-label="{{translate 'Back to Administration' category='labels' scope='EmployeeAdministration'}}"
                        title="{{translate 'Back to Administration' category='labels' scope='EmployeeAdministration'}}">
                        <span class="fas fa-arrow-left" aria-hidden="true"></span>
                    </a>
                    <div class="employee-administration-settings-hero-icon" aria-hidden="true">
                        <span class="fas {{heroIcon}}"></span>
                    </div>
                    <div>
                        <div class="employee-administration-settings-eyebrow">
                            {{translate 'Employee Administration' category='labels' scope='EmployeeAdministration'}}
                        </div>
                        <h3>{{title}}</h3>
                        <p>{{description}}</p>
                    </div>
                </div>
            </header>

            {{#each groups}}
            <section class="employee-administration-settings-panel">
                <div class="employee-administration-settings-heading">
                    <div class="employee-administration-settings-heading-icon" aria-hidden="true">
                        <span class="fas {{icon}}"></span>
                    </div>
                    <div>
                        <h4>{{title}}</h4>
                        <p>{{description}}</p>
                    </div>
                </div>
                <div class="employee-administration-settings-grid">
                    {{#each fields}}
                    <div class="employee-administration-setting-card{{#if wide}} employee-administration-setting-card-wide{{/if}}{{#if readOnly}} employee-administration-setting-card-readonly{{/if}}">
                        <div class="employee-administration-setting-label">
                            <label>{{label}}</label>
                            {{#if readOnly}}
                            <span class="employee-administration-readonly-label">
                                <span class="fas fa-lock" aria-hidden="true"></span>
                                {{translate 'Read-only' category='labels' scope='EmployeeAdministration'}}
                            </span>
                            {{/if}}
                        </div>
                        <p>{{help}}</p>
                        <div class="field employee-administration-setting-field" data-name="{{name}}"></div>
                    </div>
                    {{/each}}
                </div>
            </section>
            {{/each}}

            <div class="employee-administration-settings-footer">
                <div class="employee-administration-unsaved hidden" data-role="unsaved-indicator">
                    <span class="fas fa-circle" aria-hidden="true"></span>
                    {{translate 'Unsaved Changes' category='labels' scope='EmployeeAdministration'}}
                </div>
                <div class="employee-administration-settings-actions">
                    {{#if attendance}}
                    <button class="btn btn-default" data-action="initializeArchive">
                        <span class="fas fa-archive" aria-hidden="true"></span>
                        {{translate 'Initialize Attendance' category='labels' scope='EmployeeAttendanceRecord'}}
                    </button>
                    {{/if}}
                    <button class="btn btn-primary" data-action="save" disabled>
                        <span class="fas fa-save" aria-hidden="true"></span>
                        {{translate 'Save'}}
                    </button>
                </div>
            </div>
        </main>
    `;

    events = {
        'click [data-action="save"]': 'actionSave',
        'click [data-action="initializeArchive"]': 'initializeArchive',
        'change .employee-administration-setting-card:not(.employee-administration-setting-card-wide) .employee-administration-setting-field :input': 'markDirty',
        'input .employee-administration-setting-card:not(.employee-administration-setting-card-wide) .employee-administration-setting-field :input': 'markDirty',
    };

    setup() {
        this.section = this.options.section;
        this.wait(this.load());
    }

    async load() {
        try {
            const values = await Ajax.getRequest('EmployeeManagement/administration/' + this.section);
            this.fields = this.getMetadata().get('app.employeeManagement.settings.' + this.section);

            if (this.section === 'attendance' && values.attendanceTestDataResetAllowed !== true) {
                this.fields = Object.fromEntries(
                    Object.entries(this.fields).filter(
                        ([name]) => name !== 'employeeAttendanceTestDataReset'
                    )
                );
            }

            if (this.section === 'holiday' && values.holidayTestDataResetAllowed !== true) {
                this.fields = Object.fromEntries(
                    Object.entries(this.fields).filter(([name]) => name !== 'employeeHolidayTestDataReset')
                );
            }

            this.model = new Model(values, {
                entityType: 'EmployeeAdministration',
                defs: {fields: this.fields},
            });
        } catch (error) {
            Espo.Ui.error(this.translate(
                'administrationSettingsLoadFailed',
                'messages',
                'EmployeeAdministration',
            ));
            throw error;
        }
    }

    data() {
        const attendance = this.section === 'attendance';

        return {
            attendance,
            section: this.section,
            title: this.translate(this.section, 'labels', 'EmployeeAdministration'),
            description: this.translate(
                `${this.section}SettingsDescription`,
                'messages',
                'EmployeeAdministration',
            ),
            heroIcon: attendance ? 'fa-clock' : 'fa-umbrella-beach',
            groups: this.getGroups(),
        };
    }

    getGroups() {
        const definitions = this.section === 'attendance' ? [
            {
                key: 'Attendance Rules',
                icon: 'fa-sliders-h',
                fields: ['editablePastMonths', 'allowIncompleteExports', 'archiveStartMonth'],
            },
            {
                key: 'Attendance Data Maintenance',
                icon: 'fa-tools',
                fields: ['employeeAttendanceTestDataReset'],
            },
        ] : [
            {
                key: 'Holiday Balance Rules',
                icon: 'fa-balance-scale',
                fields: [
                    'employeeHolidayAnnualEntitlementDays',
                    'employeeHolidayResetDate',
                    'employeeHolidayAutomaticAnnualGrantEnabled',
                    'employeeHolidayCarryOverLimitDays',
                    'employeeHolidayNegativeBalanceLimitDays',
                ],
            },
            {
                key: 'Approval Document',
                icon: 'fa-file-signature',
                fields: [
                    'employeeHolidayApprovalBlock1Title',
                    'employeeHolidayApprovalBlock1Name',
                    'employeeHolidayApprovalBlock2Title',
                    'employeeHolidayApprovalBlock2Name',
                ],
            },
            {
                key: 'Holiday Data Maintenance',
                icon: 'fa-tools',
                fields: ['employeeHolidayTestDataReset'],
            },
        ];

        return definitions.map(group => ({
            icon: group.icon,
            title: this.translate(group.key, 'labels', 'EmployeeAdministration'),
            description: this.translate(
                `${group.key} Help`,
                'messages',
                'EmployeeAdministration',
            ),
            fields: group.fields
                .filter(name => this.fields[name])
                .map(name => ({
                    name,
                    label: this.translate(name, 'fields', 'EmployeeAdministration'),
                    help: this.translate(`${name}Help`, 'messages', 'EmployeeAdministration'),
                    readOnly: this.fields[name].readOnly === true,
                    wide: [
                        'employeeAttendanceTestDataReset',
                        'employeeHolidayTestDataReset',
                    ].includes(name),
                })),
        })).filter(group => group.fields.length);
    }

    async afterRender() {
        this.setPageTitle(this.translate(this.section, 'labels', 'EmployeeAdministration'));

        await Promise.all(Object.entries(this.fields).map(async ([name, defs]) => {
            const params = {...defs};
            const view = await this.createView(name, defs.fieldView || 'views/fields/' + defs.viewType, {
                selector: `.field[data-name="${name}"]`,
                model: this.model,
                name,
                mode: defs.readOnly ? 'detail' : 'edit',
                params,
            });
            if ([
                'employeeAttendanceTestDataReset',
                'employeeHolidayTestDataReset',
            ].includes(name)) {
                this.listenTo(view, 'reset', async () => {
                    await this.load();
                    await this.reRender();
                });
            }
            await view.render();
        }));
        this.$el.find('[data-action="save"]').prop('disabled', false);
    }

    markDirty() {
        this.$el.find('[data-role="unsaved-indicator"]').removeClass('hidden');
        this.$el.find('.employee-administration-settings-footer')
            .addClass('employee-administration-settings-footer-dirty');
    }

    clearDirty() {
        this.$el.find('[data-role="unsaved-indicator"]').addClass('hidden');
        this.$el.find('.employee-administration-settings-footer')
            .removeClass('employee-administration-settings-footer-dirty');
    }

    async actionSave() {
        const data = {};

        for (const [name, defs] of Object.entries(this.fields)) {
            if (defs.readOnly) {
                continue;
            }

            const view = this.getView(name);
            view.fetchToModel();

            if (view.validate()) {
                return;
            }

            Object.assign(data, view.fetch());
        }

        const button = this.$el.find('[data-action="save"]');
        button.prop('disabled', true);

        try {
            await Ajax.putRequest('EmployeeManagement/administration/' + this.section, data);
            this.clearDirty();
            Espo.Ui.success(this.translate('Saved'));
        } catch (error) {
            Espo.Ui.error(this.translate(
                'administrationSettingsSaveFailed',
                'messages',
                'EmployeeAdministration',
            ));
        } finally {
            button.prop('disabled', false);
        }
    }

    async initializeArchive() {
        await this.confirm(this.translate(
            'Confirm Initialize Attendance',
            'messages',
            'EmployeeAttendanceRecord',
        ));

        const button = this.$el.find('[data-action="initializeArchive"]');
        button.prop('disabled', true);

        try {
            await Ajax.postRequest('EmployeeManagement/attendance/initialize', {});
            Espo.Ui.success(this.translate(
                'attendanceArchiveInitialized',
                'messages',
                'EmployeeAdministration',
            ));
            await this.load();
            await this.reRender();
        } catch (error) {
            button.prop('disabled', false);
            Espo.Ui.error(this.translate(
                'attendanceArchiveInitializationFailed',
                'messages',
                'EmployeeAdministration',
            ));
        }
    }
});
