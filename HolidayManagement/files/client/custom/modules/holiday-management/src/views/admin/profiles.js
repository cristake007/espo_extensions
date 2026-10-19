define(['view', 'model'], (Dep, Model) => {
    return class extends Dep {
        templateContent = `
            <div class="header page-header"><h3>{{translate 'Holiday Profiles' category='labels' scope='Admin'}}</h3></div>
            <div class="record">
                <div class="button-container margin-bottom">
                    <button class="btn btn-primary" data-action="save" disabled>{{translate 'Save'}}</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr>
                            <th>{{translate 'User'}}</th>
                            <th>{{translate 'Annual Entitlement' category='labels' scope='Admin'}}</th>
                            <th>{{translate 'Opening / Current Balance' category='labels' scope='Admin'}}</th>
                            <th>{{translate 'Next Reset Date' category='labels' scope='Admin'}}</th>
                            <th>{{translate 'calendarColor' category='fields' scope='HolidayProfile'}}</th>
                            <th>{{translate 'Status'}}</th>
                        </tr></thead>
                        <tbody>
                        {{#each rows}}
                            <tr data-user-id="{{userId}}" data-row-index="{{rowIndex}}">
                                <td>{{userName}}</td>
                                <td><div class="field" data-annual-entitlement-field="{{rowIndex}}"></div></td>
                                <td><div class="holiday-profile-balance-control">
                                    <div class="field holiday-profile-balance-value" data-balance-field="{{rowIndex}}"></div>
                                    {{#if canCorrect}}
                                    <button type="button" class="btn btn-default btn-xs"
                                        data-action="correctBalance" data-row-index="{{rowIndex}}">
                                        <span class="fas fa-pen" aria-hidden="true"></span>
                                        {{translate 'Correct Balance' category='labels' scope='Admin'}}
                                    </button>
                                    {{/if}}
                                    {{#if canViewHistory}}
                                    <button type="button" class="btn btn-default btn-xs"
                                        data-action="viewCorrectionHistory" data-row-index="{{rowIndex}}">
                                        <span class="fas fa-history" aria-hidden="true"></span>
                                        {{translate 'Correction History' category='labels' scope='Admin'}}
                                    </button>
                                    {{/if}}
                                </div></td>
                                <td><div class="field" data-reset-date-field="{{rowIndex}}"></div></td>
                                <td><div class="field holiday-profile-color-field" data-calendar-color-field="{{rowIndex}}"></div></td>
                                <td>
                                    {{#if isInitialized}}
                                        <span>{{translate 'Initialized' category='labels' scope='Admin'}}</span>
                                        {{#if canCompleteInitialSetup}}
                                        <button type="button" class="btn btn-warning btn-xs"
                                            data-action="completeInitialSetup" data-row-index="{{rowIndex}}">
                                            {{translate 'Complete Initial Setup' category='labels' scope='Admin'}}
                                        </button>
                                        {{/if}}
                                    {{else}}
                                        <button type="button" class="btn btn-primary btn-xs"
                                            data-action="initializeProfile" data-row-index="{{rowIndex}}">
                                            {{translate 'Initialize Profile' category='labels' scope='Admin'}}
                                        </button>
                                    {{/if}}
                                </td>
                            </tr>
                        {{/each}}
                        </tbody>
                    </table>
                </div>
            </div>
        `

        events = {
            'click [data-action="save"]': 'actionSave',
            'click [data-action="initializeProfile"]': 'actionInitializeProfile',
            'click [data-action="completeInitialSetup"]': 'actionCompleteInitialSetup',
            'click [data-action="correctBalance"]': 'actionCorrectBalance',
            'click [data-action="viewCorrectionHistory"]': 'actionViewCorrectionHistory',
        }

        setup() {
            this.rows = [];
            this.profileModels = [];
            this.wait(this.loadProfiles());
        }

        data() {
            return {rows: this.rows};
        }

        async loadProfiles() {
            let response;

            try {
                response = await Espo.Ajax.getRequest('HolidayManagement/profiles');
            } catch (error) {
                this.rows = [];
                this.profileModels = [];
                Espo.Ui.error(this.translate(
                    'holidayProfilesLoadFailed',
                    'messages',
                    'Admin',
                ));

                return;
            }

            const canCorrectBalance = this.getAcl().check('HolidayBalanceCorrection');
            this.rows = (response.list || []).map((row, rowIndex) => ({
                ...row,
                rowIndex,
                canCorrect: Boolean(row.isInitialized && canCorrectBalance),
                canViewHistory: Boolean(row.isInitialized && row.profileId),
            }));
            this.profileModels = this.rows.map(row => {
                const model = new Model({
                    annualEntitlement: row.annualEntitlement,
                    balance: row.balance,
                    nextResetDate: row.nextResetDate,
                    calendarColor: row.calendarColor,
                });

                model.entityType = 'HolidayProfile';

                return model;
            });
        }

        afterRender() {
            this.renderProfileFields();
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
                await Espo.Ajax.postRequest('HolidayManagement/bulkInitialize', {items});
                Espo.Ui.success(this.translate('Saved'));
                await this.loadProfiles();
                await this.reRender();
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'holidayProfilesSaveFailed',
                    'messages',
                    'Admin',
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

            const annualEntitlement = this.getView(`annualEntitlement-${rowIndex}`)
                .fetch().annualEntitlement;
            const openingBalance = this.getView(`balance-${rowIndex}`).fetch().balance;
            const nextResetDate = this.getView(`nextResetDate-${rowIndex}`)
                .fetch().nextResetDate;
            const calendarColor = this.getView(`calendarColor-${rowIndex}`)
                .fetch().calendarColor;

            if (
                annualEntitlement === null || annualEntitlement === undefined ||
                annualEntitlement === '' || openingBalance === null ||
                openingBalance === undefined || openingBalance === '' || !nextResetDate
            ) {
                Espo.Ui.warning(this.translate(
                    'initializationFieldsRequired',
                    'messages',
                    'Admin',
                ));
                return;
            }

            const button = $(event.currentTarget);
            button.prop('disabled', true);

            try {
                await Espo.Ajax.postRequest('HolidayManagement/bulkInitialize', {
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
                    'Admin',
                ));
                await this.loadProfiles();
                await this.reRender();
            } catch (error) {
                button.prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'profileInitializationFailed',
                    'messages',
                    'Admin',
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
                'holiday-management:views/modals/complete-initial-setup',
                {
                    profileId: row.profileId,
                    userName: row.userName,
                },
            ).then(modal => {
                this.listenToOnce(modal, 'saved', async () => {
                    Espo.Ui.success(this.translate(
                        'initialSetupCompleted',
                        'messages',
                        'Admin',
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
                !this.getAcl().check('HolidayBalanceCorrection')) {
                throw new Espo.Exceptions.AccessDenied();
            }

            const viewName = `holidayBalanceCorrection-${Date.now()}`;

            this.createView(
                viewName,
                'holiday-management:views/modals/balance-correction',
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
                        'Admin',
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
                'holiday-management:views/modals/correction-history',
                {
                    profileId: row.profileId,
                    userName: row.userName,
                },
            ).then(modal => modal.render());
        }
    }
});
