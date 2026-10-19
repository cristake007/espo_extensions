define(['views/modal', 'model'], (ModalView, Model) => {
    return class extends ModalView {
        scope = 'HolidayProfile'

        backdrop = true

        templateContent = `
            <p class="text-muted">{{userName}}</p>
            <p>{{translate 'initialSetupExplanation' category='messages' scope='Admin'}}</p>
            <div class="form-group">
                <label class="control-label">
                    {{translate 'Opening Balance' category='labels' scope='Admin'}}
                </label>
                <div class="field" data-name="openingBalanceField"></div>
            </div>
        `

        setup() {
            this.profileId = String(this.options.profileId || '');
            this.userName = String(this.options.userName || '');
            this.balanceModel = new Model({balance: null});
            this.balanceModel.entityType = 'HolidayProfile';
            this.headerText = this.translate('Complete Initial Setup', 'labels', 'Admin');
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
                    'Admin',
                ));
                return;
            }

            const openingBalance = Number(rawValue);

            if (!Number.isFinite(openingBalance)) {
                Espo.Ui.warning(this.translate(
                    'openingBalanceRequired',
                    'messages',
                    'Admin',
                ));
                return;
            }

            this.$el.closest('.modal').find('button').prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'HolidayManagement/completeInitialSetup',
                    {
                        profileId: this.profileId,
                        openingBalance,
                        idempotencyKey: `opening-setup:${Date.now()}:${this.profileId}`,
                    },
                );
                this.trigger('saved', result);
                this.close();
            } catch (error) {
                this.$el.closest('.modal').find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'initialSetupSaveFailed',
                    'messages',
                    'Admin',
                ));
            }
        }
    };
});
