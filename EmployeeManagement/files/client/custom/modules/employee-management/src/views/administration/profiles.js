define(['view', 'model'], (Dep, Model) => {
    return class extends Dep {
        templateContent = `
            <main class="employee-administration-profiles-page">
                <header class="employee-administration-profiles-hero">
                    <div class="employee-administration-profiles-hero-copy">
                        <a class="employee-administration-profiles-back" href="#EmployeeAdministration"
                            aria-label="{{translate 'Back to Administration' category='labels' scope='EmployeeAdministration'}}"
                            title="{{translate 'Back to Administration' category='labels' scope='EmployeeAdministration'}}">
                            <span class="fas fa-arrow-left" aria-hidden="true"></span>
                        </a>
                        <div class="employee-administration-profiles-hero-icon" aria-hidden="true">
                            <span class="fas fa-id-card"></span>
                        </div>
                        <div>
                            <div class="employee-administration-profiles-eyebrow">
                                {{translate 'Employee Administration' category='labels' scope='EmployeeAdministration'}}
                            </div>
                            <h3>{{translate 'Holiday Profiles' category='labels' scope='EmployeeAdministration'}}</h3>
                            <p>{{translate 'profilesPageDescription' category='messages' scope='EmployeeAdministration'}}</p>
                        </div>
                    </div>
                    <div class="employee-administration-profiles-count">
                        <strong>{{rowCount}}</strong>
                        <span>{{translate 'Employees' category='labels' scope='EmployeeAdministration'}}</span>
                    </div>
                </header>

                <section class="employee-administration-profiles-panel">
                    <div class="employee-administration-profiles-heading">
                        <div class="employee-administration-profiles-heading-copy">
                            <div class="employee-administration-profiles-section-icon" aria-hidden="true">
                                <span class="fas fa-users-cog"></span>
                            </div>
                            <div>
                                <h4>{{translate 'Employee Holiday Balances' category='labels' scope='EmployeeAdministration'}}</h4>
                                <p>{{translate 'profilesTableHelp' category='messages' scope='EmployeeAdministration'}}</p>
                            </div>
                        </div>
                        {{#if hasRows}}
                        <button class="btn btn-primary" data-action="save" disabled>
                            <span class="fas fa-save" aria-hidden="true"></span>
                            {{translate 'Save'}}
                        </button>
                        {{/if}}
                    </div>

                    {{#if loadFailed}}
                    <div class="employee-administration-profiles-state employee-administration-profiles-state-danger">
                        <span class="fas fa-exclamation-triangle" aria-hidden="true"></span>
                        <strong>{{translate 'Profiles Unavailable' category='labels' scope='EmployeeAdministration'}}</strong>
                        <p>{{translate 'holidayProfilesLoadFailed' category='messages' scope='EmployeeAdministration'}}</p>
                    </div>
                    {{else}}
                    {{#unless hasRows}}
                    <div class="employee-administration-profiles-state">
                        <span class="fas fa-user-slash" aria-hidden="true"></span>
                        <strong>{{translate 'No Employee Profiles' category='labels' scope='EmployeeAdministration'}}</strong>
                        <p>{{translate 'noEmployeeProfilesHelp' category='messages' scope='EmployeeAdministration'}}</p>
                    </div>
                    {{/unless}}
                    {{#if hasRows}}
                    <div class="employee-administration-profiles-table-wrap">
                    <table class="table employee-administration-profiles-table">
                        <thead><tr>
                            <th>{{translate 'User'}}</th>
                            <th>{{translate 'Annual Entitlement' category='labels' scope='EmployeeAdministration'}}</th>
                            <th>{{translate 'Opening / Current Balance' category='labels' scope='EmployeeAdministration'}}</th>
                            <th>{{translate 'Next Reset Date' category='labels' scope='EmployeeAdministration'}}</th>
                            <th>{{translate 'calendarColor' category='fields' scope='EmployeeHolidayProfile'}}</th>
                            <th>{{translate 'Status'}}</th>
                        </tr></thead>
                        <tbody>
                        {{#each rows}}
                            <tr data-user-id="{{userId}}" data-row-index="{{rowIndex}}">
                                <td class="employee-administration-profile-identity">
                                    <div class="employee-administration-profile-identity-inner">
                                        <span class="employee-administration-profile-avatar" aria-hidden="true">
                                            <span class="fas fa-user"></span>
                                        </span>
                                        <span class="employee-administration-profile-person">
                                            <strong>{{userName}}</strong>
                                            <small class="employee-administration-profile-dirty hidden" data-role="row-dirty">
                                                <span class="fas fa-circle" aria-hidden="true"></span>
                                                {{translate 'Unsaved Changes' category='labels' scope='EmployeeAdministration'}}
                                            </small>
                                        </span>
                                    </div>
                                </td>
                                <td><div class="field" data-annual-entitlement-field="{{rowIndex}}"></div></td>
                                <td><div class="holiday-profile-balance-control">
                                    <div class="field holiday-profile-balance-value" data-balance-field="{{rowIndex}}"></div>
                                    {{#if canCorrect}}
                                    <button type="button" class="btn btn-default btn-xs"
                                        data-action="correctBalance" data-row-index="{{rowIndex}}">
                                        <span class="fas fa-pen" aria-hidden="true"></span>
                                        {{translate 'Correct Balance' category='labels' scope='EmployeeAdministration'}}
                                    </button>
                                    {{/if}}
                                    {{#if canViewHistory}}
                                    <button type="button" class="btn btn-default btn-xs"
                                        data-action="viewCorrectionHistory" data-row-index="{{rowIndex}}">
                                        <span class="fas fa-history" aria-hidden="true"></span>
                                        {{translate 'Correction History' category='labels' scope='EmployeeAdministration'}}
                                    </button>
                                    {{/if}}
                                </div></td>
                                <td><div class="field" data-reset-date-field="{{rowIndex}}"></div></td>
                                <td><div class="field holiday-profile-color-field" data-calendar-color-field="{{rowIndex}}"></div></td>
                                <td class="employee-administration-profile-status">
                                    {{#if isInitialized}}
                                        <span class="employee-administration-profile-state employee-administration-profile-state-success">
                                            <span class="fas fa-check-circle" aria-hidden="true"></span>
                                            {{translate 'Initialized' category='labels' scope='EmployeeAdministration'}}
                                        </span>
                                        {{#if canCompleteInitialSetup}}
                                        <button type="button" class="btn btn-warning btn-xs"
                                            data-action="completeInitialSetup" data-row-index="{{rowIndex}}">
                                            {{translate 'Complete Initial Setup' category='labels' scope='EmployeeAdministration'}}
                                        </button>
                                        {{/if}}
                                    {{else}}
                                        <span class="employee-administration-profile-state employee-administration-profile-state-warning">
                                            <span class="fas fa-exclamation-circle" aria-hidden="true"></span>
                                            {{translate 'Not Initialized' category='labels' scope='EmployeeAdministration'}}
                                        </span>
                                        <button type="button" class="btn btn-primary btn-xs"
                                            data-action="initializeProfile" data-row-index="{{rowIndex}}">
                                            {{translate 'Initialize Profile' category='labels' scope='EmployeeAdministration'}}
                                        </button>
                                    {{/if}}
                                </td>
                            </tr>
                        {{/each}}
                        </tbody>
                    </table>
                    </div>
                    {{/if}}
                    {{/if}}
                </section>
            </main>
        `

        events = {
            'click [data-action="save"]': 'actionSave',
            'click [data-action="correctBalance"]': 'actionCorrectBalance',
            'click [data-action="viewCorrectionHistory"]': 'actionViewCorrectionHistory',
            'click [data-action="initializeProfile"]': 'actionInitializeProfile',
            'click [data-action="completeInitialSetup"]': 'actionCompleteInitialSetup',
            'change tbody tr[data-user-id] :input': 'markRowDirty',
            'input tbody tr[data-user-id] :input': 'markRowDirty',
        }

        setup() {
            this.rows = [];
            this.profileModels = [];
            this.loadFailed = false;
            this.wait(this.loadProfiles());
        }

        data() {
            return {
                rows: this.rows,
                rowCount: this.rows.length,
                hasRows: this.rows.length > 0,
                loadFailed: this.loadFailed,
            };
        }

        async loadProfiles() {
            let response;

            try {
                response = await Espo.Ajax.getRequest('EmployeeManagement/administration/profiles');
            } catch (error) {
                this.rows = [];
                this.profileModels = [];
                this.loadFailed = true;
                Espo.Ui.error(this.translate(
                    'holidayProfilesLoadFailed',
                    'messages',
                    'EmployeeAdministration',
                ));

                return;
            }

            this.loadFailed = false;

            const canCorrectBalance = this.getAcl().checkScope('EmployeeHolidayBalanceCorrection');
            this.rows = (response.list || []).map((row, rowIndex) => ({
                ...row,
                rowIndex,
                canCorrect: Boolean(row.isInitialized && canCorrectBalance),
                canViewHistory: Boolean(row.isInitialized && canCorrectBalance),
            }));
            this.profileModels = this.rows.map(row => {
                const model = new Model({
                    annualEntitlement: row.annualEntitlement,
                    balance: row.balance,
                    nextResetDate: row.nextResetDate,
                    calendarColor: row.calendarColor,
                });

                model.entityType = 'EmployeeHolidayProfile';

                return model;
            });
        }

        afterRender() {
            this.setPageTitle(this.translate('Holiday Profiles', 'labels', 'EmployeeAdministration'));
            this.renderProfileFields();
        }

        markRowDirty(event) {
            $(event.currentTarget).closest('tr[data-user-id]')
                .addClass('employee-administration-profile-row-dirty')
                .find('[data-role="row-dirty"]')
                .removeClass('hidden');
        }

        async renderProfileFields() {
            const promises = [];

            this.rows.forEach(row => {
                const model = this.profileModels[row.rowIndex];

                promises.push(this.createView(
                    `annualEntitlement-${row.rowIndex}`,
                    'views/fields/float',
                    {
                        selector: `[data-annual-entitlement-field="${row.rowIndex}"]`,
                        mode: 'edit',
                        model,
                        name: 'annualEntitlement',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()));

                promises.push(this.createView(
                    `balance-${row.rowIndex}`,
                    'views/fields/float',
                    {
                        selector: `[data-balance-field="${row.rowIndex}"]`,
                        mode: row.isInitialized ? 'list' : 'edit',
                        model,
                        name: 'balance',
                        readOnlyDisabled: !row.isInitialized,
                    },
                ).then(view => view.render()));

                promises.push(this.createView(
                    `nextResetDate-${row.rowIndex}`,
                    'views/fields/date',
                    {
                        selector: `[data-reset-date-field="${row.rowIndex}"]`,
                        mode: 'edit',
                        model,
                        name: 'nextResetDate',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()));

                promises.push(this.createView(
                    `calendarColor-${row.rowIndex}`,
                    'views/fields/colorpicker',
                    {
                        selector: `[data-calendar-color-field="${row.rowIndex}"]`,
                        mode: 'edit',
                        model,
                        name: 'calendarColor',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()));
            });

            await Promise.all(promises);
            this.$el.find('[data-action="save"]').prop('disabled', false);
        }

        async actionSave() {
            const operation = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            const items = [];

            this.$el.find('tbody tr[data-user-id]').each((index, element) => {
                const row = $(element);
                const rowIndex = Number(row.data('row-index'));
                const annualEntitlement = this.getView(`annualEntitlement-${rowIndex}`)
                    .fetch().annualEntitlement;
                const profileRow = this.rows[rowIndex];
                const nextResetDate = this.getView(`nextResetDate-${rowIndex}`)
                    .fetch().nextResetDate;
                const calendarColor = this.getView(`calendarColor-${rowIndex}`)
                    .fetch().calendarColor;

                if (!profileRow.isInitialized) {
                    return;
                }

                const unchanged =
                    Number(annualEntitlement) === Number(profileRow.annualEntitlement) &&
                    String(nextResetDate || '') === String(profileRow.nextResetDate || '') &&
                    String(calendarColor || '').toUpperCase() ===
                        String(profileRow.calendarColor || '').toUpperCase();

                if (unchanged) {
                    return;
                }

                const item = {
                    userId: row.data('user-id'),
                    annualEntitlement: Number(annualEntitlement),
                    nextResetDate,
                    calendarColor,
                    idempotencyKey: `bulk:${operation}:${row.data('user-id')}`,
                };

                items.push(item);
            });

            if (!items.length) {
                Espo.Ui.warning(this.translate('No Data'));
                return;
            }

            const saveButton = this.$el.find('[data-action="save"]');
            saveButton.prop('disabled', true);

            try {
                await Espo.Ajax.postRequest('EmployeeManagement/administration/profiles/bulkInitialize', {items});
                Espo.Ui.success(this.translate('Saved'));
                await this.loadProfiles();
                await this.reRender();
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'holidayProfilesSaveFailed',
                    'messages',
                    'EmployeeAdministration',
                ));
            } finally {
                saveButton.prop('disabled', false);
            }
        }

        async actionInitializeProfile(event) {
            const rowIndex = Number($(event.currentTarget).data('row-index'));
            const row = this.rows[rowIndex];

            if (!row || row.isInitialized) {
                return;
            }

            const fetchedAnnualEntitlement = this.getView(`annualEntitlement-${rowIndex}`)
                .fetch().annualEntitlement;
            const openingBalance = this.getView(`balance-${rowIndex}`).fetch().balance;
            const fetchedNextResetDate = this.getView(`nextResetDate-${rowIndex}`)
                .fetch().nextResetDate;
            const fetchedCalendarColor = this.getView(`calendarColor-${rowIndex}`)
                .fetch().calendarColor;
            const annualEntitlement = fetchedAnnualEntitlement ?? row.annualEntitlement;
            const nextResetDate = fetchedNextResetDate || row.nextResetDate;
            const calendarColor = fetchedCalendarColor || row.calendarColor;

            if (
                annualEntitlement === null || annualEntitlement === undefined ||
                annualEntitlement === '' || !Number.isFinite(Number(annualEntitlement))
            ) {
                Espo.Ui.warning(this.translate(
                    'annualEntitlementRequired',
                    'messages',
                    'EmployeeAdministration',
                ));
                return;
            }

            if (
                openingBalance === null || openingBalance === undefined ||
                openingBalance === '' || !Number.isFinite(Number(openingBalance))
            ) {
                Espo.Ui.warning(this.translate(
                    'openingBalanceRequired',
                    'messages',
                    'EmployeeAdministration',
                ));
                return;
            }

            if (!nextResetDate) {
                Espo.Ui.warning(this.translate(
                    'resetDateRequired',
                    'messages',
                    'EmployeeAdministration',
                ));
                return;
            }

            const button = $(event.currentTarget);
            button.prop('disabled', true);

            try {
                await Espo.Ajax.postRequest('EmployeeManagement/administration/profiles/bulkInitialize', {
                    items: [{
                        userId: row.userId,
                        annualEntitlement: Number(annualEntitlement),
                        openingBalance: Number(openingBalance),
                        nextResetDate,
                        calendarColor,
                        idempotencyKey: `initialize:${Date.now()}:${row.userId}`,
                    }],
                });
                Espo.Ui.success(this.translate(
                    'profileInitialized',
                    'messages',
                    'EmployeeAdministration',
                ));
                await this.loadProfiles();
                await this.reRender();
            } catch (error) {
                button.prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'profileInitializationFailed',
                    'messages',
                    'EmployeeAdministration',
                ));
            }
        }

        actionCompleteInitialSetup(event) {
            const rowIndex = Number($(event.currentTarget).data('row-index'));
            const row = this.rows[rowIndex];

            if (!row?.isInitialized || !row?.canCompleteInitialSetup || !row.profileId) {
                return;
            }

            this.createView(
                `completeInitialSetup-${Date.now()}`,
                'employee-management:views/modals/complete-initial-setup',
                {
                    profileId: row.profileId,
                    userName: row.userName,
                },
            ).then(modal => {
                this.listenToOnce(modal, 'saved', async () => {
                    Espo.Ui.success(this.translate(
                        'initialSetupCompleted',
                        'messages',
                        'EmployeeAdministration',
                    ));
                    await this.loadProfiles();
                    await this.reRender();
                });
                modal.render();
            });
        }

        actionCorrectBalance(event) {
            const rowIndex = Number($(event.currentTarget).data('row-index'));
            const row = this.rows[rowIndex];

            if (!row?.isInitialized || !row.profileId ||
                !this.getAcl().check('EmployeeHolidayBalanceCorrection')) {
                throw new Espo.Exceptions.AccessDenied();
            }

            const viewName = `holidayBalanceCorrection-${Date.now()}`;

            this.createView(
                viewName,
                'employee-management:views/modals/balance-correction',
                {
                    profileId: row.profileId,
                    userName: row.userName,
                    currentBalance: Number(row.balance),
                },
            ).then(modal => {
                this.listenToOnce(modal, 'saved', async () => {
                    Espo.Ui.success(this.translate(
                        'correctionSaved',
                        'messages',
                        'EmployeeAdministration',
                    ));
                    await this.loadProfiles();
                    await this.reRender();
                });
                modal.render();
            });
        }

        actionViewCorrectionHistory(event) {
            const rowIndex = Number($(event.currentTarget).data('row-index'));
            const row = this.rows[rowIndex];

            if (!row?.isInitialized || !row.profileId) {
                return;
            }

            this.createView(
                `holidayCorrectionHistory-${Date.now()}`,
                'employee-management:views/modals/correction-history',
                {
                    profileId: row.profileId,
                    userName: row.userName,
                },
            ).then(modal => modal.render());
        }
    }
});
