define(['views/admin/integrations/edit'], (IntegrationsEditView) => {
    return class NagerDateIntegrationView extends IntegrationsEditView {

        template = 'zile-sarbatoare:admin/integrations/nager-date'

        statusFields = [
            'lastAttemptedAt',
            'lastSuccessfulAt',
            'lastResult',
            'lastRequestedYears',
            'lastAcceptedCount',
            'lastCreatedCount',
            'lastUpdatedCount',
            'lastRemovedCount',
            'lastError',
            'nextRunAt',
        ]

        settingFields = [
            'enabled',
            'countryCode',
            'years',
            'holidayTypes',
            'nationalOnly',
            'automaticSync',
            'frequency',
            'timeOfDay',
            'dayOfWeek',
            'dayOfMonth',
        ]

        setup() {
            super.setup();

            this.addActionHandler('synchronize', () => this.synchronize());
        }

        createFieldView(type, name, readOnly, params) {
            super.createFieldView(type, name, this.statusFields.includes(name), params);
        }

        afterRender() {
            super.afterRender();

            this.updateDynamicFields();
            this.updateSynchronizeButton();

            this.listenTo(this.model, 'change:frequency change:automaticSync change:enabled', () => {
                this.updateDynamicFields();
                this.updateSynchronizeButton();
            });

            this.refreshStatus();
        }

        updateDynamicFields() {
            this.statusFields.forEach(name => this.showField(name));

            if (!this.model.get('enabled')) {
                return;
            }

            const automatic = this.model.get('automaticSync') && this.model.get('frequency') !== 'ManualOnly';

            automatic ? this.showField('timeOfDay') : this.hideField('timeOfDay');
            automatic && this.model.get('frequency') === 'Weekly' ?
                this.showField('dayOfWeek') : this.hideField('dayOfWeek');
            automatic && this.model.get('frequency') === 'Monthly' ?
                this.showField('dayOfMonth') : this.hideField('dayOfMonth');
        }

        updateSynchronizeButton(inProgress = false) {
            const disabled = inProgress || !this.model.get('enabled');
            const $button = this.$el.find('[data-action="synchronize"]');

            $button.prop('disabled', disabled);
            $button.find('.synchronize-label').text(this.translate(
                inProgress ? 'synchronizing' : 'synchronizeNow',
                'labels',
                'Integration'
            ));
        }

        async refreshStatus() {
            const values = await Espo.Ajax.getRequest('NagerDate/action/status');
            this.model.set(values);
            this.updateDynamicFields();
            this.updateSynchronizeButton();
        }

        save() {
            this.settingFields.forEach(name => {
                const view = this.getFieldView(name);

                if (view && !view.readOnly) {
                    view.fetchToModel();
                }
            });

            let notValid = false;

            this.settingFields.forEach(name => {
                const view = this.getFieldView(name);

                if (view && !view.disabled) {
                    notValid = view.validate() || notValid;
                }
            });

            if (notValid) {
                Espo.Ui.error(this.translate('Not valid'));

                return;
            }

            const data = {};
            this.settingFields.forEach(name => data[name] = this.model.get(name));

            Espo.Ui.notify(this.translate('saving', 'messages'));

            Espo.Ajax.postRequest('NagerDate/action/saveSettings', data)
                .then(values => {
                    this.model.set(values);
                    Espo.Ui.success(this.translate('Saved'));
                    this.updateDynamicFields();
                    this.updateSynchronizeButton();
                });
        }

        async synchronize() {
            if (!this.model.get('enabled')) {
                return;
            }

            this.updateSynchronizeButton(true);
            Espo.Ui.notifyWait();

            try {
                const result = await Espo.Ajax.postRequest('NagerDate/action/synchronize', {});
                Espo.Ui.notify(result.message, 'info', undefined, {closeButton: true});
                await this.refreshStatus();
            } finally {
                Espo.Ui.notify(false);
                this.updateSynchronizeButton();
            }
        }
    };
});
