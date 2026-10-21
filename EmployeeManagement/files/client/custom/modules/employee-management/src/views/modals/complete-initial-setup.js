define(['views/modal', 'model'], (ModalView, Model) => {
    return class extends ModalView {
        scope = 'EmployeeHolidayProfile'

        backdrop = true

        templateContent = `
            <p class="text-muted">{{userName}}</p>
            <p>{{translate 'initialSetupExplanation' category='messages' scope='EmployeeAdministration'}}</p>
            <div class="form-group">
                <label class="control-label">
                    {{translate 'Opening Balance' category='labels' scope='EmployeeAdministration'}}
                </label>
                <div class="field" data-name="openingBalanceField"></div>
            </div>
        `

        setup() {
            this.operationKey = `opening-setup:${Date.now()}:${this.options.profileId}`;
            this.profileId = String(this.options.profileId || '');
            this.userName = String(this.options.userName || '');
            this.balanceModel = new Model({balance: null});
            this.balanceModel.entityType = 'EmployeeHolidayProfile';
            this.headerText = this.translate('Complete Initial Setup', 'labels', 'EmployeeAdministration');
            this.buttonList = [
                {
                    name: 'submit',
                    label: 'Save',
                    style: 'primary',
                    onClick: () => this.submit(),
                },
                {name: 'cancel', label: 'Cancel'},
            ];
        }

        data() {
            return {userName: this.userName};
        }

        async afterRender() {
            await this.createView(
                'openingBalance',
                'views/fields/float',
                {
                    selector: '[data-name="openingBalanceField"]',
                    mode: 'edit',
                    model: this.balanceModel,
                    name: 'balance',
                    readOnlyDisabled: true,
                },
            ).then(view => view.render());
        }

        async submit() {
            const rawValue = this.getView('openingBalance')?.fetch().balance;

            if (rawValue === null || rawValue === undefined || rawValue === '') {
                Espo.Ui.warning(this.translate(
                    'openingBalanceRequired',
                    'messages',
                    'EmployeeAdministration',
                ));
                return;
            }

            const openingBalance = Number(rawValue);

            if (!Number.isFinite(openingBalance)) {
                Espo.Ui.warning(this.translate(
                    'openingBalanceRequired',
                    'messages',
                    'EmployeeAdministration',
                ));
                return;
            }

            this.$el.closest('.modal').find('button').prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'EmployeeManagement/administration/profiles/completeInitialSetup',
                    {
                        profileId: this.profileId,
                        openingBalance,
                        idempotencyKey: this.operationKey,
                    },
                );
                this.trigger('saved', result);
                this.close();
            } catch (error) {
                this.$el.closest('.modal').find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'initialSetupSaveFailed',
                    'messages',
                    'EmployeeAdministration',
                ));
            }
        }
    };
});
