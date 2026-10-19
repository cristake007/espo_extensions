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
                                <td>{{#if isInitialized}}{{translate 'Initialized' category='labels' scope='Admin'}}{{else}}{{translate 'Not Initialized' category='labels' scope='Admin'}}{{/if}}</td>
                            </tr>
                        {{/each}}
                        </tbody>
                    </table>
                </div>
            </div>
        `

        events = {
            'click [data-action="save"]': 'actionSave',
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
                        mode: row.isInitialized ? 'detail' : 'edit',
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
                const openingBalance = profileRow.isInitialized ? undefined :
                    this.getView(`balance-${rowIndex}`).fetch().balance;
                const nextResetDate = this.getView(`nextResetDate-${rowIndex}`)
                    .fetch().nextResetDate;
                const calendarColor = this.getView(`calendarColor-${rowIndex}`)
                    .fetch().calendarColor;

                if (
                    (annualEntitlement === null || annualEntitlement === undefined) &&
                    (openingBalance === null || openingBalance === undefined) &&
                    !nextResetDate
                ) {
                    return;
                }

                const item = {
                    userId: row.data('user-id'),
                    annualEntitlement: Number(annualEntitlement),
                    nextResetDate,
                    calendarColor,
                    idempotencyKey: `bulk:${operation}:${row.data('user-id')}`,
                };

                if (!profileRow.isInitialized) {
                    item.openingBalance = Number(openingBalance);
                }

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
