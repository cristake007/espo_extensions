define(['views/modal', 'model'], (ModalView, Model) => {
    return class extends ModalView {
        scope = 'EmployeeHolidayProfile'

        backdrop = true

        templateContent = `
            <p class="text-muted">{{userName}}</p>
            <div class="form-group">
                <label class="control-label">
                    {{translate 'Current Balance' category='labels' scope='EmployeeAdministration'}}
                </label>
                <p class="form-control-static">{{currentBalance}}</p>
            </div>
            <div class="form-group">
                <label class="control-label">
                    {{translate 'New Balance' category='labels' scope='EmployeeAdministration'}}
                </label>
                <div class="field" data-name="newBalanceField"></div>
            </div>
            <div class="form-group">
                <label class="control-label">
                    {{translate 'Correction Reason' category='labels' scope='EmployeeAdministration'}}
                </label>
                <div class="field" data-name="correctionReasonField"></div>
            </div>
        `

        setup() {
            this.operationKey = `correction:${Date.now()}:${Math.random().toString(16).slice(2)}`;
            this.profileId = String(this.options.profileId || '');
            this.userName = String(this.options.userName || '');
            this.currentBalance = Number(this.options.currentBalance);
            this.balanceModel = new Model({balance: this.currentBalance});
            this.balanceModel.entityType = 'EmployeeHolidayProfile';
            this.reasonModel = new Model({reason: ''});
            this.reasonModel.entityType = 'EmployeeHolidayLedger';
            this.headerText = this.translate('Correct Balance', 'labels', 'EmployeeAdministration');
            this.buttonList = [
                {
                    name: 'submit',
                    label: 'Correct Balance',
                    style: 'primary',
                    onClick: () => this.submit(),
                },
                {name: 'cancel', label: 'Cancel'},
            ];
        }

        data() {
            return {
                userName: this.userName,
                currentBalance: this.currentBalance,
            };
        }

        async afterRender() {
            await Promise.all([
                this.createView(
                    'newBalance',
                    'views/fields/float',
                    {
                        selector: '[data-name="newBalanceField"]',
                        mode: 'edit',
                        model: this.balanceModel,
                        name: 'balance',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()),
                this.createView(
                    'correctionReason',
                    'views/fields/text',
                    {
                        selector: '[data-name="correctionReasonField"]',
                        mode: 'edit',
                        model: this.reasonModel,
                        name: 'reason',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()),
            ]);
        }

        async submit() {
            const newBalance = Number(this.getView('newBalance')?.fetch().balance);
            const reason = String(
                this.getView('correctionReason')?.fetch().reason || ''
            ).trim();

            if (!Number.isFinite(newBalance)) {
                Espo.Ui.warning(this.translate(
                    'correctionBalanceRequired',
                    'messages',
                    'EmployeeAdministration',
                ));
                return;
            }

            if (!reason) {
                Espo.Ui.warning(this.translate(
                    'correctionReasonRequired',
                    'messages',
                    'EmployeeAdministration',
                ));
                return;
            }

            const delta = newBalance - this.currentBalance;

            if (delta === 0) {
                Espo.Ui.warning(this.translate(
                    'correctionBalanceUnchanged',
                    'messages',
                    'EmployeeAdministration',
                ));
                return;
            }

            this.$el.closest('.modal').find('button').prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'EmployeeManagement/administration/profiles/correct',
                    {
                        profileId: this.profileId,
                        delta,
                        expectedBalance: this.currentBalance,
                        reason,
                        idempotencyKey: this.operationKey,
                    },
                );
                this.trigger('saved', result);
                this.close();
            } catch (error) {
                this.$el.closest('.modal').find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'correctionSaveFailed',
                    'messages',
                    'EmployeeAdministration',
                ));
            }
        }
    };
});
